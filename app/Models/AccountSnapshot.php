<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountSnapshot extends Model
{
    use Prunable;

    public $timestamps = false;

    protected $fillable = [
        'influencer_account_id',
        'followers',
        'captured_on',
    ];

    protected function casts(): array
    {
        return [
            'followers' => 'integer',
            'captured_on' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(InfluencerAccount::class, 'influencer_account_id');
    }

    public function prunable(): Builder
    {
        return static::where('captured_on', '<', today()->subDays(config('platforms.sync.snapshot_retention_days')));
    }
}
