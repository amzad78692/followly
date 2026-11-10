{{-- Google and Apple sign-in buttons, shared by the login and register pages. --}}
<div class="mt-6 space-y-3">
    @error('social')
        <flux:callout variant="danger" icon="exclamation-circle" :heading="$message" />
    @enderror

    <flux:button :href="route('social.redirect', 'google')" class="w-full">
        <svg class="size-4" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5a5.6 5.6 0 0 1-2.4 3.6v3h3.9c2.2-2.1 3.5-5.1 3.5-8.8z"/>
            <path fill="#34A853" d="M12 24c3.2 0 6-1.1 8-2.9l-3.9-3c-1.1.7-2.5 1.2-4.1 1.2-3.1 0-5.8-2.1-6.7-5H1.3v3.1A12 12 0 0 0 12 24z"/>
            <path fill="#FBBC05" d="M5.3 14.3a7.2 7.2 0 0 1 0-4.6V6.6h-4a12 12 0 0 0 0 10.8l4-3.1z"/>
            <path fill="#EA4335" d="M12 4.8c1.8 0 3.3.6 4.6 1.8l3.4-3.4A12 12 0 0 0 1.3 6.6l4 3.1c.9-2.9 3.6-4.9 6.7-4.9z"/>
        </svg>
        Continue with Google
    </flux:button>

    <flux:button :href="route('social.redirect', 'apple')" class="w-full">
        <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M16.4 12.7c0-2.7 2.2-4 2.3-4.1a5 5 0 0 0-3.9-2.1c-1.7-.2-3.2 1-4.1 1-.8 0-2.1-1-3.5-.9a5.2 5.2 0 0 0-4.4 2.7c-1.9 3.3-.5 8.1 1.3 10.8.9 1.3 2 2.7 3.4 2.7 1.3-.1 1.9-.9 3.5-.9s2.1.9 3.5.8c1.5 0 2.4-1.3 3.3-2.7a11 11 0 0 0 1.5-3.1c-.1 0-2.9-1.1-2.9-4.2zM13.7 4.7a4.7 4.7 0 0 0 1.1-3.4 4.8 4.8 0 0 0-3.1 1.6 4.5 4.5 0 0 0-1.1 3.3 4 4 0 0 0 3.1-1.5z"/>
        </svg>
        Continue with Apple
    </flux:button>

    {{-- Google/Apple can create a new account, so it needs the same consent as the register form. --}}
    <flux:text class="text-center text-xs">
        By continuing with Google or Apple, you confirm you are 18 or older and accept the
        <flux:link :href="route('terms')" target="_blank">Terms</flux:link> and <flux:link :href="route('privacy')" target="_blank">Privacy Policy</flux:link>.
    </flux:text>

    <div class="flex items-center gap-3 pt-2 text-sm text-zinc-500">
        <span class="h-px flex-1 bg-zinc-200"></span>
        or use email
        <span class="h-px flex-1 bg-zinc-200"></span>
    </div>
</div>
