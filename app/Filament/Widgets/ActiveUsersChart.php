<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

/**
 * Daily and monthly active users, from the days RecordActivity logs per signed-in user.
 */
class ActiveUsersChart extends ChartWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 4;

    protected ?string $heading = 'Active users';

    public function getDescription(): ?string
    {
        $dau = DB::table('user_activity_days')->where('date', today()->toDateString())->count();
        $mau = DB::table('user_activity_days')->where('date', '>=', today()->subDays(29)->toDateString())->distinct()->count('user_id');

        return 'DAU '.Number::format($dau).' · MAU '.Number::format($mau).' (last 30 days)';
    }

    public function getData(): array
    {
        $days = collect(range(29, 0))->map(fn (int $i) => today()->subDays($i)->toDateString());

        $counts = DB::table('user_activity_days')
            ->where('date', '>=', $days->first())
            ->selectRaw('date, count(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date')
            ->mapWithKeys(fn ($total, $date) => [substr($date, 0, 10) => (int) $total]);

        return [
            'datasets' => [['label' => 'Daily active users', 'data' => $days->map(fn ($day) => $counts[$day] ?? 0)->all()]],
            'labels' => $days->map(fn ($day) => substr($day, 5))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
