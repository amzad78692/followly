<?php

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] #[Title('Forgot password')] class extends Component {
    public string $email = '';

    public function sendPasswordResetLink(): void
    {
        $this->validate(['email' => ['required', 'string', 'email']]);

        $key = 'password-reset:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        RateLimiter::hit($key);

        // Same message whether or not the email exists, so accounts can't be probed.
        Password::sendResetLink($this->only('email'));

        session()->flash('status', __('A reset link will be sent if the account exists.'));
    }
}; ?>

<div>
    <flux:heading size="xl" level="1" class="font-bold!">Forgot your password?</flux:heading>
    <flux:text class="mt-1">Enter your email and we'll send you a reset link.</flux:text>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" class="mt-6" :heading="session('status')" />
    @endif

    <form wire:submit="sendPasswordResetLink" class="mt-6 space-y-5">
        <flux:input wire:model="email" label="Email" type="email" required autofocus autocomplete="email" />

        <flux:button type="submit" variant="primary" class="w-full">Email reset link</flux:button>
    </form>

    <flux:text class="mt-6 text-center">
        Remembered it?
        <flux:link :href="route('login')" wire:navigate>Back to sign in</flux:link>
    </flux:text>
</div>
