{{-- No custom Filament theme, so layout uses inline styles and Filament components only. --}}
<x-filament-widgets::widget>
    <x-filament::section heading="Google Analytics" description="Cached for an hour">
        @if (! $summary)
            <x-filament::badge color="gray">Unavailable</x-filament::badge>
            <div style="font-size: .875rem; opacity: .7; margin-top: .5rem">
                {{ $configured ? 'Google Analytics did not respond. The error is in the log.' : 'Set GA4_PROPERTY_ID and GA4_CREDENTIALS_PATH in .env to show traffic here.' }}
            </div>
        @else
            @php $cell = 'padding: .25rem .75rem .25rem 0; text-align: left'; @endphp
            <div style="display: flex; flex-wrap: wrap; gap: 2rem; font-size: .875rem">
                <table>
                    <tr style="opacity: .7"><th style="{{ $cell }}"></th><th style="{{ $cell }}">Visitors</th><th style="{{ $cell }}">Sessions</th><th style="{{ $cell }}">Page views</th></tr>
                    @foreach (['7d' => 'Last 7 days', '30d' => 'Last 30 days'] as $key => $label)
                        <tr>
                            <th style="{{ $cell }}">{{ $label }}</th>
                            <td style="{{ $cell }}">{{ Number::format($summary['totals'][$key]['visitors'] ?? 0) }}</td>
                            <td style="{{ $cell }}">{{ Number::format($summary['totals'][$key]['sessions'] ?? 0) }}</td>
                            <td style="{{ $cell }}">{{ Number::format($summary['totals'][$key]['views'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                </table>

                <table>
                    <tr style="opacity: .7"><th style="{{ $cell }}">Top pages (30 days)</th><th style="{{ $cell }}">Views</th></tr>
                    @foreach ($summary['pages'] as $page)
                        <tr><td style="{{ $cell }}; overflow-wrap: anywhere">{{ $page['path'] }}</td><td style="{{ $cell }}">{{ Number::format($page['views']) }}</td></tr>
                    @endforeach
                </table>

                <table>
                    <tr style="opacity: .7"><th style="{{ $cell }}">Traffic sources (30 days)</th><th style="{{ $cell }}">Sessions</th></tr>
                    @foreach ($summary['sources'] as $source)
                        <tr><td style="{{ $cell }}">{{ $source['source'] }}</td><td style="{{ $cell }}">{{ Number::format($source['sessions']) }}</td></tr>
                    @endforeach
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
