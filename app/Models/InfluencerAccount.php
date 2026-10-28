<?php

namespace App\Models;

use App\Enums\Platform;
use App\Enums\SyncStatus;
use App\Platforms\Data\AccountData;
use Database\Factories\InfluencerAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Throwable;

class InfluencerAccount extends Model
{
    /** @use HasFactory<InfluencerAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'influencer_id',
        'platform',
        'handle',
        'external_id',
        'url',
        'followers',
        'is_verified',
        'sync_status',
        'sync_error',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'sync_status' => SyncStatus::class,
            'followers' => 'integer',
            'is_verified' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (InfluencerAccount $account) {
            $account->url ??= $account->platform?->profileUrl($account->handle);
        });

        // Keep the influencer's combined reach in step with account follower counts.
        static::saved(function (InfluencerAccount $account) {
            if ($account->wasRecentlyCreated || $account->wasChanged('followers')) {
                $account->influencer?->refreshCounts();
            }
        });

        static::deleted(fn (InfluencerAccount $account) => $account->influencer?->refreshCounts());
    }

    public function influencer(): BelongsTo
    {
        return $this->belongsTo(Influencer::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(AccountSnapshot::class);
    }

    public function getFormattedFollowersAttribute(): string
    {
        return Number::abbreviate($this->followers, maxPrecision: 1);
    }

    /**
     * Store today's follower count; one row per account per day.
     */
    public function snapshot(): AccountSnapshot
    {
        // whereDate: the date cast stores a time part on some drivers, so match on the date only.
        $snapshot = $this->snapshots()->whereDate('captured_on', today())->firstOrNew();

        return tap($snapshot->fill(['followers' => $this->followers, 'captured_on' => today()]))->save();
    }

    /**
     * Store a freshly fetched profile and clear any earlier sync error.
     */
    public function applyProfile(AccountData $data): void
    {
        $this->fill([
            ...collect($data->toAccountAttributes())->except('platform', 'external_id')->all(),
            'sync_status' => SyncStatus::Synced,
            'sync_error' => null,
        ])->save();
    }

    public function markSyncFailed(Throwable $e): void
    {
        $this->forceFill([
            'sync_status' => SyncStatus::Failed,
            'sync_error' => Str::limit($e->getMessage(), 1000),
        ])->save();
    }
}
