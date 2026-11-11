<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] #[Title('Verify email')] class extends Component {
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        session()->flash('status', 'verification-link-sent');
    }

    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <flux:heading size="xl" level="1" class="font-bold!">Verify your email</flux:heading>
    <flux:text class="mt-1">We sent a link to your email address. Click it to finish setting up your account.</flux:text>

    @if (session('status') === 'verification-link-sent')
        <flux:callout variant="success" icon="check-circle" class="mt-6" heading="A new verification link has been sent." />
    @endif

    <div class="mt-6 space-y-3">
        <flux:button wire:click="sendVerification" variant="primary" class="w-full">Resend verification email</flux:button>
        <flux:button wire:click="logout" variant="ghost" class="w-full">Log out</flux:button>
    </div>
</div>
