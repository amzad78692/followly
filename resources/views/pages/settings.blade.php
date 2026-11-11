<?php

use App\Enums\DigestFrequency;
use App\Enums\Platform;
use App\Livewire\Actions\Logout;
use App\Models\User;
use App\Watch\Pairing;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.dashboard')] #[Title('Settings')] class extends Component {
    use WithFileUploads;

    // Profile
    public string $name = '';

    public string $email = '';

    public $avatar = null;

    public $banner = null;

    // Password
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    // Notifications
    public bool $notifyInApp = true;

    /** DigestFrequency value, or "off". */
    public string $digest = 'off';

    /** Platform values the user gets new post notifications for. */
    public array $platforms = [];

    // Connected devices
    public string $pairingCode = '';

    /** The device waiting on the entered code, shown for the user to confirm. */
    public ?array $pendingDevice = null;

    // Delete account
    public string $delete_password = '';

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->notifyInApp = $user->notify_in_app;
        $this->digest = $user->email_digest?->value ?? 'off';
        $this->platforms = collect(Platform::cases())
            ->filter(fn (Platform $platform) => $user->wantsPostsFrom($platform))
            ->map->value
            ->values()
            ->all();
    }

    public function updatedAvatar(): void
    {
        $this->validateOnly('avatar', ['avatar' => ['image', 'max:2048']]);
    }

    public function updatedBanner(): void
    {
        $this->validateOnly('banner', ['banner' => ['image', 'max:4096']]);
    }

    public function saveProfile(): void
    {
        $user = auth()->user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'banner' => ['nullable', 'image', 'max:4096'],
        ]);

        $user->fill(['name' => $validated['name'], 'email' => $validated['email']]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($this->avatar) {
            $user->addMedia($this->avatar->getRealPath())
                ->usingFileName($this->avatar->hashName())
                ->toMediaCollection('users');

            $this->avatar = null;
        }

        if ($this->banner) {
            $user->addMedia($this->banner->getRealPath())
                ->usingFileName($this->banner->hashName())
                ->toMediaCollection('banners');

            $this->banner = null;
        }

        $this->dispatch('profile-saved');
    }

    public function removeAvatar(): void
    {
        auth()->user()->load('media')->clearMediaCollection('users');
        $this->avatar = null;
    }

    public function removeBanner(): void
    {
        auth()->user()->load('media')->clearMediaCollection('banners');
        $this->banner = null;
    }

    public function updatePassword(): void
    {
        $validated = $this->validate([
            // Google/Apple accounts have no password yet, so they set one without the current one.
            'current_password' => auth()->user()->password ? ['required', 'string', 'current_password'] : ['nullable'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        auth()->user()->update(['password' => $validated['password']]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-saved');
    }

    public function saveNotifications(): void
    {
        $this->validate([
            'notifyInApp' => ['boolean'],
            'digest' => ['required', Rule::in(['off', ...array_column(DigestFrequency::cases(), 'value')])],
            'platforms' => ['array'],
            'platforms.*' => [new Enum(Platform::class)],
        ]);

        $muted = collect(Platform::cases())->reject(fn (Platform $platform) => in_array($platform->value, $this->platforms, true));

        auth()->user()->update([
            'notify_in_app' => $this->notifyInApp,
            'email_digest' => DigestFrequency::tryFrom($this->digest),
            'muted_platforms' => $muted->isEmpty() ? null : $muted->values(),
        ]);

        // The badge hides when in-app alerts are off, so let it recount.
        $this->dispatch('notifications-read');
        $this->dispatch('notifications-saved');
    }

    #[Computed]
    public function devices()
    {
        return auth()->user()->tokens()->latest()->get();
    }

    public function findDevice(Pairing $pairing): void
    {
        $this->validate(['pairingCode' => ['required', 'string', 'size:6']]);

        $key = 'watch-pairing-entry:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('pairingCode', 'Too many attempts. Try again in a minute.');

            return;
        }

        $this->pendingDevice = $pairing->find($this->pairingCode);

        if (! $this->pendingDevice) {
            RateLimiter::hit($key);
            $this->addError('pairingCode', 'That code is wrong or has expired.');
        }
    }

    public function confirmDevice(Pairing $pairing): void
    {
        if (! $pairing->approve($this->pairingCode, auth()->user())) {
            $this->pendingDevice = null;
            $this->addError('pairingCode', 'That code is wrong or has expired.');

            return;
        }

        $this->cancelDevice();
        $this->dispatch('device-connected');
    }

    public function cancelDevice(): void
    {
        $this->reset('pairingCode', 'pendingDevice');
    }

    public function disconnectDevice(int $id): void
    {
        auth()->user()->tokens()->whereKey($id)->delete();

        unset($this->devices);
    }

    public function deleteAccount(Logout $logout): void
    {
        // Without a password, typing the account email confirms instead.
        $this->validate(['delete_password' => auth()->user()->password
            ? ['required', 'string', 'current_password']
            : ['required', 'string', Rule::in([auth()->user()->email])]]);

        $user = auth()->user();

        $logout();
        $user->deleteAccount();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl" level="1" class="font-bold!">Settings</flux:heading>
        <flux:text class="mt-1">Your account, sign-in, notifications, alerts and devices.</flux:text>

        <nav class="mt-4 flex flex-wrap gap-2 text-sm">
            @foreach (['account' => 'Account', 'login' => 'Login methods', 'notifications' => 'Notifications', 'alerts' => 'Alerts', 'devices' => 'Devices'] as $anchor => $label)
                <a href="#{{ $anchor }}" class="rounded-full border border-zinc-200 bg-white px-3 py-1.5 font-medium text-zinc-700 transition hover:bg-zinc-50">{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    {{-- Account --}}
    <section id="account" class="scroll-mt-24 card p-5 sm:p-6">
        <flux:heading size="lg">Account</flux:heading>
        <flux:text class="mt-1">Your banner, photo, name and email address.</flux:text>

        <form wire:submit="saveProfile" class="mt-5 space-y-5">
            <div class="space-y-2">
                @php($bannerSrc = $banner && ! $errors->has('banner') ? $banner->temporaryUrl() : auth()->user()->bannerUrl())
                <div class="relative h-32 overflow-hidden rounded-xl bg-gradient-to-r from-zinc-100 to-zinc-200 sm:h-40">
                    @if ($bannerSrc)
                        <img src="{{ $bannerSrc }}" alt="" class="size-full object-cover">
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 shadow-xs transition hover:bg-zinc-50">
                        <flux:icon.photo class="size-4" />
                        <span wire:loading.remove wire:target="banner">Upload banner</span>
                        <span wire:loading wire:target="banner">Uploading…</span>
                        <input type="file" wire:model="banner" accept="image/*" class="sr-only">
                    </label>
                    @if (auth()->user()->bannerUrl() && ! $banner)
                        <flux:button size="sm" variant="ghost" wire:click="removeBanner">Remove</flux:button>
                    @endif
                    <flux:text class="text-xs">Wide image, about 1500×500. Up to 4 MB.</flux:text>
                </div>
                <flux:error name="banner" />
            </div>

            <div class="flex items-center gap-4">
                @if ($avatar && ! $errors->has('avatar'))
                    <img src="{{ $avatar->temporaryUrl() }}" alt="" class="size-16 rounded-full object-cover">
                @else
                    <flux:avatar circle size="xl" :name="auth()->user()->name" :src="auth()->user()->avatarUrl()" />
                @endif

                <div class="space-y-2">
                    <div class="flex flex-wrap gap-2">
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 shadow-xs transition hover:bg-zinc-50">
                            <flux:icon.arrow-up-tray class="size-4" />
                            <span wire:loading.remove wire:target="avatar">Upload photo</span>
                            <span wire:loading wire:target="avatar">Uploading…</span>
                            <input type="file" wire:model="avatar" accept="image/*" class="sr-only">
                        </label>
                        @if (auth()->user()->avatarUrl() && ! $avatar)
                            <flux:button size="sm" variant="ghost" wire:click="removeAvatar">Remove</flux:button>
                        @endif
                    </div>
                    <flux:text class="text-xs">JPG, PNG or WebP, up to 2 MB.</flux:text>
                    <flux:error name="avatar" />
                </div>
            </div>

            <flux:input wire:model="name" label="Name" type="text" required autocomplete="name" />
            <flux:input wire:model="email" label="Email" type="email" required autocomplete="email" />

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary">Save</flux:button>
                <x-saved-message on="profile-saved" />
            </div>
        </form>
    </section>

    {{-- Login methods --}}
    <section id="login" class="scroll-mt-24 card p-5 sm:p-6">
        <flux:heading size="lg">Login methods</flux:heading>
        <flux:text class="mt-1">The ways you can sign in to {{ config('app.name') }}.</flux:text>

        <ul class="mt-5 divide-y divide-zinc-100 rounded-xl border border-zinc-200">
            @foreach (['Email and password' => (bool) auth()->user()->password, 'Google' => (bool) auth()->user()->google_id, 'Apple' => (bool) auth()->user()->apple_id] as $method => $connected)
                <li class="flex items-center justify-between px-4 py-3 text-sm">
                    <span class="font-medium text-zinc-800">{{ $method }}</span>
                    <flux:badge size="sm" :color="$connected ? 'green' : 'zinc'">{{ $connected ? 'Connected' : 'Not set up' }}</flux:badge>
                </li>
            @endforeach
        </ul>

        <flux:heading class="mt-6">Password</flux:heading>
        <flux:text class="mt-1">Use a long password you don't use anywhere else.</flux:text>

        <form wire:submit="updatePassword" class="mt-5 space-y-5">
            @if (auth()->user()->password)
                <flux:input wire:model="current_password" label="Current password" type="password" required autocomplete="current-password" viewable />
            @else
                <flux:text>You sign in with Google or Apple. Set a password to also sign in with your email.</flux:text>
            @endif
            <flux:input wire:model="password" label="New password" type="password" required autocomplete="new-password" viewable />
            <flux:input wire:model="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" viewable />

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary">Update password</flux:button>
                <x-saved-message on="password-saved" />
            </div>
        </form>
    </section>

    {{-- Notifications --}}
    <section id="notifications" class="scroll-mt-24 card p-5 sm:p-6">
        <flux:heading size="lg">Notifications</flux:heading>
        <flux:text class="mt-1">How we tell you about new posts from the creators you track.</flux:text>

        <form wire:submit="saveNotifications" class="mt-5 space-y-6">
            <flux:switch wire:model="notifyInApp" label="In-app notifications" description="Show new posts on the bell and in the sidebar." />

            <flux:radio.group wire:model="digest" label="Email digest" description="A summary of the new posts you haven't seen yet. No email when there's nothing new.">
                <flux:radio value="off" label="Off" />
                @foreach (DigestFrequency::cases() as $frequency)
                    <flux:radio :value="$frequency->value" :label="$frequency->label()" :description="$frequency === DigestFrequency::Daily ? 'Every morning' : 'Monday mornings'" />
                @endforeach
            </flux:radio.group>

            <flux:checkbox.group wire:model="platforms" label="Platforms" description="Get notified about new posts on these platforms.">
                @foreach (Platform::cases() as $platform)
                    <flux:checkbox :value="$platform->value" :label="$platform->label()" />
                @endforeach
            </flux:checkbox.group>

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary">Save preferences</flux:button>
                <x-saved-message on="notifications-saved" />
            </div>
        </form>
    </section>

    {{-- Alerts --}}
    <section id="alerts" class="scroll-mt-24 card p-5 sm:p-6">
        <flux:heading size="lg">Alerts</flux:heading>
        <flux:text class="mt-1">Keyword alerts and their on/off switches now have their own page.</flux:text>
        <flux:button :href="route('alerts')" wire:navigate icon="bell-alert" class="mt-4">Manage alerts</flux:button>
    </section>

    {{-- Devices --}}
    <section id="devices" class="scroll-mt-24 card p-5 sm:p-6">
        <flux:heading size="lg">Devices</flux:heading>
        <flux:text class="mt-1">Enter the code shown on your watch to connect it to your account.</flux:text>

        @if ($pendingDevice)
            <div class="mt-5 rounded-xl border border-zinc-200 p-4">
                <flux:heading>Connect "{{ $pendingDevice['name'] }}"?</flux:heading>
                <flux:text class="mt-1">Only continue if this is your watch and it is showing this code right now.</flux:text>

                <div class="mt-4 flex gap-2">
                    <flux:button variant="primary" wire:click="confirmDevice">Connect</flux:button>
                    <flux:button variant="ghost" wire:click="cancelDevice">Cancel</flux:button>
                </div>
            </div>
        @else
            <form wire:submit="findDevice" class="mt-5 flex items-start gap-3">
                <div class="w-40">
                    <flux:input wire:model="pairingCode" aria-label="Pairing code" placeholder="ABC123" maxlength="6" autocomplete="off" class="uppercase" />
                    <flux:error name="pairingCode" />
                </div>
                <flux:button type="submit" variant="primary">Continue</flux:button>
                <x-saved-message on="device-connected" />
            </form>
        @endif

        <div class="mt-5 divide-y divide-zinc-100">
            @forelse ($this->devices as $device)
                <div wire:key="device-{{ $device->id }}" class="flex items-center justify-between gap-3 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-zinc-900">{{ $device->name }}</p>
                        <flux:text class="text-xs">
                            {{ $device->last_used_at ? 'Last used '.$device->last_used_at->diffForHumans() : 'Not used yet' }}
                        </flux:text>
                    </div>
                    <flux:button size="sm" variant="ghost" wire:click="disconnectDevice({{ $device->id }})">Disconnect</flux:button>
                </div>
            @empty
                <flux:text>No devices connected.</flux:text>
            @endforelse
        </div>
    </section>

    {{-- Delete account --}}
    <section id="delete" class="rounded-2xl border border-red-200 bg-white p-5 sm:p-6">
        <flux:heading size="lg">Delete account</flux:heading>
        <flux:text class="mt-1">Your follows, notifications and settings are removed for good. Creators you added stay on Followly.</flux:text>

        <flux:modal.trigger name="delete-account">
            <flux:button variant="danger" class="mt-5">Delete account</flux:button>
        </flux:modal.trigger>

        <flux:modal name="delete-account" class="w-full max-w-md">
            <form wire:submit="deleteAccount" class="space-y-5">
                <div>
                    <flux:heading size="lg">Delete your account?</flux:heading>
                    <flux:text class="mt-1">This can't be undone. Enter your {{ auth()->user()->password ? 'password' : 'email address' }} to confirm.</flux:text>
                </div>

                @if (auth()->user()->password)
                    <flux:input wire:model="delete_password" label="Password" type="password" required autocomplete="current-password" viewable />
                @else
                    <flux:input wire:model="delete_password" label="Email address" type="email" required />
                @endif

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger">Delete account</flux:button>
                </div>
            </form>
        </flux:modal>
    </section>
</div>
