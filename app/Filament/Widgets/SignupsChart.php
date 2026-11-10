<?php

namespace App\Filament\Widgets;

use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Number;

/**
 * New users per day for 30 days, split by how they signed up.
 */
class SignupsChart extends ChartWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Sign-ups';

    // shortcut: an email account later linked to Google/Apple counts as that provider; store a signup source column if the split must be exact.
    protected const SOURCE = "case when apple_id is not null then 'apple' when google_id is not null then 'google' else 'email' end";

    public function getDescription(): ?string
    {
        $since = fn ($date) => Number::format(User::where('created_at', '>=', $date)->count());

        return "Today {$since(today())} · This week {$since(now()->startOfWeek())} · This month {$since(now()->startOfMonth())}";
    }

    public function getData(): array
    {
        $days = collect(range(29, 0))->map(fn (int $i) => today()->subDays($i)->toDateString());

        $rows = User::toBase()
            ->where('created_at', '>=', today()->subDays(29))
            ->selectRaw('date(created_at) as day, '.self::SOURCE.' as source, count(*) as total')
            ->groupBy('day', 'source')
            ->get();

        $series = fn (string $source) => $days->map(fn ($day) => (int) $rows->where('day', $day)->where('source', $source)->sum('total'))->all();

        return [
            'datasets' => [
                ['label' => 'Email', 'data' => $series('email'), 'borderColor' => '#f59e0b', 'backgroundColor' => '#f59e0b'],
                ['label' => 'Google', 'data' => $series('google'), 'borderColor' => '#3b82f6', 'backgroundColor' => '#3b82f6'],
                ['label' => 'Apple', 'data' => $series('apple'), 'borderColor' => '#6b7280', 'backgroundColor' => '#6b7280'],
            ],
            'labels' => $days->map(fn ($day) => substr($day, 5))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return ['scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true, 'ticks' => ['precision' => 0]]]];
    }
}
