{{-- One player for the whole page: post cards dispatch "play-post" with Post::$embed. --}}
<div
    x-data="{ embed: null }"
    x-on:play-post.window="embed = $event.detail"
    x-on:keydown.escape.window="embed = null"
    x-effect="document.documentElement.classList.toggle('overflow-hidden', !! embed)"
    x-show="embed"
    x-cloak
    x-transition.opacity
    x-on:click.self="embed = null"
    role="dialog"
    aria-modal="true"
    aria-label="Post player"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm"
>
    <button type="button" x-on:click="embed = null" aria-label="Close player" class="absolute top-3 end-3 flex size-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20">
        <flux:icon.x-mark class="size-5" />
    </button>

    <template x-if="embed">
        <div
            x-bind:class="{
                'aspect-video w-full max-w-4xl bg-black': embed.shape === 'landscape',
                'aspect-[9/16] w-[min(100%,calc(85dvh*9/16))] bg-black': embed.shape === 'portrait',
                'h-[85dvh] w-full max-w-xl bg-white': embed.shape === 'tall',
            }"
            class="overflow-hidden rounded-xl shadow-2xl"
        >
            <iframe x-bind:src="embed.url" class="size-full" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
    </template>
</div>
