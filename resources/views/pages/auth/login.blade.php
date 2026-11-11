<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] #[Title('Sign in')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}; ?>

<div>
    <flux:heading size="xl" level="1" class="font-bold!">Welcome back</flux:heading>
    <flux:text class="mt-1">Sign in to see your feed.</flux:text>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" class="mt-6" :heading="session('status')" />
    @endif

    <x-social-login />

    <form wire:submit="login" class="mt-6 space-y-5">
        <flux:input wire:model="email" label="Email" type="email" required autofocus autocomplete="email" />

        <div class="space-y-2">
            <flux:input wire:model="password" label="Password" type="password" required autocomplete="current-password" viewable />
            <div class="text-end">
                <flux:link :href="route('password.request')" wire:navigate class="text-sm">Forgot password?</flux:link>
            </div>
        </div>

        <flux:checkbox wire:model="remember" label="Remember me" />

        <flux:button type="submit" variant="primary" class="w-full">Sign in</flux:button>
    </form>

    <flux:text class="mt-6 text-center">
        New to {{ config('app.name') }}?
        <flux:link :href="route('register')" wire:navigate>Create an account</flux:link>
    </flux:text>
</div>
