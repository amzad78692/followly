<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] #[Title('Reset password')] class extends Component {
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->string('email');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PasswordReset) {
            $this->addError('email', __($status));

            return;
        }

        session()->flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div>
    <flux:heading size="xl" level="1" class="font-bold!">Choose a new password</flux:heading>
    <flux:text class="mt-1">Enter a new password for your account.</flux:text>

    <form wire:submit="resetPassword" class="mt-6 space-y-5">
        <flux:input wire:model="email" label="Email" type="email" required autocomplete="email" />
        <flux:input wire:model="password" label="New password" type="password" required autofocus autocomplete="new-password" viewable />
        <flux:input wire:model="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" viewable />

        <flux:button type="submit" variant="primary" class="w-full">Reset password</flux:button>
    </form>
</div>
