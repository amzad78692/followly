{{-- Details come from the admin panel (Settings > Contact); empty ones are left out. --}}
@php
    $details = array_filter([
        'Email' => setting('contact.email'),
        'Phone' => setting('contact.mobile'),
        'WhatsApp' => setting('contact.whatsapp'),
        'Address' => setting('contact.address'),
    ]);
@endphp

<x-layouts.public title="Contact">
    <article class="mx-auto max-w-3xl rounded-3xl border border-zinc-200 bg-white p-6 sm:p-10">
        <h1 class="text-4xl font-bold tracking-tight text-zinc-900">Contact</h1>
        <p class="mt-3 text-base text-zinc-500">Questions, feedback or a creator you can't find? We'd love to hear from you.</p>

        @if ($details)
            <dl class="mt-8 grid gap-4 sm:grid-cols-2">
                @foreach ($details as $label => $value)
                    <div class="rounded-2xl bg-zinc-50 p-4">
                        <dt class="text-xs text-zinc-500">{{ $label }}</dt>
                        <dd class="mt-1 font-medium break-words text-zinc-900">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        @else
            <p class="mt-8 text-sm text-zinc-500">Contact details are coming soon.</p>
        @endif
    </article>
</x-layouts.public>
