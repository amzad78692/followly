<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] #[Title('Create account')] class extends Component {
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);
        Session::regenerate();

        $this->redirectIntended(default: route('feed', absolute: false), navigate: true);
    }
}; ?>

<div>
    <flux:heading size="xl" level="1" class="font-bold!">Create your account</flux:heading>
    <flux:text class="mt-1">Follow once. Track everywhere.</flux:text>

    <form wire:submit="register" class="mt-6 space-y-5">
        <flux:input wire:model="name" label="Name" type="text" required autofocus autocomplete="name" />
        <flux:input wire:model="email" label="Email" type="email" required autocomplete="email" />
        <flux:input wire:model="password" label="Password" type="password" required autocomplete="new-password" viewable />
        <flux:input wire:model="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" viewable />

        <flux:button type="submit" variant="primary" class="w-full">Get Started</flux:button>
    </form>

    <flux:text class="mt-6 text-center">
        Already have an account?
        <flux:link :href="route('login')" wire:navigate>Sign in</flux:link>
    </flux:text>
</div>
