<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * One Apify actor run, logged so spending can be watched and capped (ApifyBudget).
 */
class ApifyRun extends Model
{
    use Prunable;

    protected $fillable = [
        'platform',
        'actor',
        'run_id',
        'status',
        'items_count',
        'usage_usd',
        'duration_ms',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'items_count' => 'integer',
            'usage_usd' => 'float',
            'duration_ms' => 'integer',
        ];
    }

    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->startOfMonth());
    }

    public static function spentThisMonth(): float
    {
        return (float) static::thisMonth()->sum('usage_usd');
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(config('platforms.sync.apify_run_retention_days')));
    }
}
