<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Str;

class StatsOverview extends BaseWidget
{
    use HasWidgetShield;

    protected function getStats(): array
    {
        $stats = [];
        $exclude = [];
        $custom = [];

        foreach (filament()->getResources() as $path) {
            $label = __($path::getLabel());
            if (! $label) {
                $classBaseName = class_basename($path);
                $rawName = Str::before($classBaseName, 'Resource');
                $label = Str::headline($rawName);
            }

            if (in_array($label, $exclude)) {
                continue;
            }

            $icon = $path::getNavigationIcon();
            $url = $path::getUrl('index');

            $modelClass = $path::getModel();
            $count = class_exists($modelClass) ? $modelClass::count() : 0;

            $stats[] = Stat::make(__($label), $count)
                ->icon($icon)
                ->url($url);
        }

        return array_merge($stats, $custom);
    }
}
