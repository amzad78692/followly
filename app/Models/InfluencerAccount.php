<?php

namespace App\Models;

use App\Enums\Platform;
use App\Enums\SyncStatus;
use App\Platforms\Data\AccountData;
use Database\Factories\InfluencerAccountFactory;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Accounts at least one user tracks.
     */
    public function scopeTracked(Builder $query): Builder
    {
        return $query->whereIn('id', FollowAccount::query()->select('influencer_account_id'));
    }

    /**
     * Accounts whose posts were last synced more than $minutes ago (or never). Skips accounts
     * queued less than an hour ago and failed ones until failed_retry_hours have passed.
     */
    public function scopeDueForSync(Builder $query, int $minutes): Builder
    {
        return $query
            ->where(fn (Builder $q) => $q->whereNull('last_synced_at')->orWhere('last_synced_at', '<=', now()->subMinutes($minutes)))
            ->where(fn (Builder $q) => $q
                ->whereNotIn('sync_status', [SyncStatus::Syncing, SyncStatus::Failed])
                ->orWhere(fn (Builder $q) => $q->where('sync_status', SyncStatus::Syncing)->where('updated_at', '<=', now()->subHour()))
                ->orWhere(fn (Builder $q) => $q->where('sync_status', SyncStatus::Failed)->where('updated_at', '<=', now()->subHours(config('platforms.sync.failed_retry_hours')))));
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
