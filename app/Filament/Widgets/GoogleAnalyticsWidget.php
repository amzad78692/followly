<?php

namespace App\Filament\Widgets;

use App\Services\GoogleAnalytics;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;

/**
 * Site traffic from GA4. Shows "unavailable" instead of failing when GA is not set up or not reachable.
 */
class GoogleAnalyticsWidget extends Widget
{
    use HasWidgetShield;

    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.google-analytics';

    protected function getViewData(): array
    {
        $analytics = app(GoogleAnalytics::class);

        return ['configured' => $analytics->configured(), 'summary' => $analytics->summary()];
    }
}
