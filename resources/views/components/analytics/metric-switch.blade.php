@props(['metric'])

{{-- Picks the one series the Performance chart shows; sets the parent component's $metric. --}}
<fieldset class="flex gap-4 text-sm text-zinc-700">
    <legend class="sr-only">Show</legend>
    @foreach (\App\Analytics\CreatorReport::METRICS as $option)
        <label class="flex cursor-pointer items-center gap-1.5">
            <input type="checkbox" class="size-4 cursor-pointer accent-accent" @checked($metric === $option) wire:click="$set('metric', '{{ $option }}')">
            {{ ucfirst($option) }}
        </label>
    @endforeach
</fieldset>
