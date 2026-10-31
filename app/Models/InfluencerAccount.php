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

    /**
     * The stored account a profile link points to, if any.
     */
    public static function findFromUrl(Platform $platform, string $url): ?self
    {
        $path = trim(parse_url(str_contains($url, '://') ? $url : "https://{$url}", PHP_URL_PATH) ?? '', '/');
        $segments = array_values(array_filter(explode('/', $path)));

        if ($segments === []) {
            return null;
        }

        // youtube.com/channel/UC... carries the channel id rather than a handle.
        if ($platform === Platform::YouTube && $segments[0] === 'channel' && isset($segments[1])) {
            return static::where('platform', $platform)->where('external_id', $segments[1])->whereHas('influencer')->first();
        }

        $handle = $platform === Platform::YouTube && in_array($segments[0], ['c', 'user']) && isset($segments[1])
            ? $segments[1]
            : $segments[0];

        return static::findByHandle(ltrim($handle, '@'), $platform);
    }

    /**
     * The stored account with this handle ("name" or "@name"), on any platform unless one is given.
     */
    public static function findByHandle(string $handle, ?Platform $platform = null): ?self
    {
        $handle = Str::lower(ltrim($handle, '@'));

        if ($handle === '' || str_contains($handle, ' ')) {
            return null;
        }

        return static::query()
            ->when($platform, fn ($q) => $q->where('platform', $platform))
            ->where(fn ($q) => $q
                ->whereRaw('lower(handle) = ?', [$handle])
                ->orWhereRaw('lower(handle) = ?', ['@'.$handle]))
            ->whereHas('influencer')
            ->first();
    }

    /**
     * The stored profile in the shape drivers return, so lookups can use it without an API call.
     */
    public function toAccountData(): AccountData
    {
        return new AccountData(
            platform: $this->platform,
            externalId: $this->external_id,
            handle: ltrim($this->handle, '@'),
            name: $this->influencer?->name ?? ltrim($this->handle, '@'),
            url: $this->url,
            followers: $this->followers,
            isVerified: $this->is_verified,
        );
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
