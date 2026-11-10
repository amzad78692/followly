{{-- No custom Filament theme, so layout uses inline styles and Filament components only. --}}
<x-filament-widgets::widget>
    <x-filament::section heading="Errors and alerts">
        <x-slot name="afterHeader">
            <x-filament::link :href="$this->logViewerUrl()" icon="heroicon-o-document-text">Open log viewer</x-filament::link>
        </x-slot>

        <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 1rem">
            @foreach ($this->checks() as $check)
                <div>
                    <div style="font-size: .875rem; opacity: .7">{{ $check['label'] }}</div>
                    <x-filament::badge :color="$check['alerting'] ? 'danger' : 'success'" size="lg">{{ $check['value'] }}</x-filament::badge>
                </div>
            @endforeach
        </div>

        <div style="font-size: .875rem; font-weight: 600; margin-bottom: .5rem">Recent failed jobs</div>
        @forelse ($this->failedJobs() as $job)
            <div style="font-size: .875rem; padding: .5rem 0; border-top: 1px solid rgb(127 127 127 / .2)">
                <strong>{{ $job['job'] }}</strong>
                <span style="opacity: .7">· {{ $job['failed_at']->diffForHumans() }}</span>
                <div style="opacity: .85; overflow-wrap: anywhere">{{ $job['error'] }}</div>
            </div>
        @empty
            <div style="font-size: .875rem; opacity: .7">No failed jobs.</div>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
