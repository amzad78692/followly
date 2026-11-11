<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\DigestFrequency;
use App\Enums\Platform;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

// !IMPORTANT : This is a base user model. It is not for direct use.
class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, InteractsWithMedia, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'mobile',
        'password',
        'email_digest',
        'notify_in_app',
        'notify_push',
        'push_daily_summary',
        'muted_platforms',
        'alerts_enabled',
    ];

    /**
     * Matches the column defaults, so new models have them before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'notify_in_app' => true,
        'notify_push' => true,
        'push_daily_summary' => false,
        'alerts_enabled' => true,
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'email_digest' => DigestFrequency::class,
            'digest_sent_at' => 'datetime',
            'notify_in_app' => 'boolean',
            'notify_push' => 'boolean',
            'push_daily_summary' => 'boolean',
            'alerts_enabled' => 'boolean',
            'muted_platforms' => AsEnumCollection::of(Platform::class),
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('users')->singleFile();
        $this->addMediaCollection('banners')->singleFile();
    }

    /**
     * Whether new posts on this platform should notify the user (in the app or in the digest).
     */
    public function wantsPostsFrom(Platform $platform): bool
    {
        return ! $this->muted_platforms?->contains($platform);
    }

    /**
     * Whether the user gets new post notifications at all: shown in the app, collected for the
     * digest, or pushed to a connected watch or phone.
     */
    public function wantsPostNotifications(): bool
    {
        return $this->notify_in_app
            || $this->email_digest !== null
            || ($this->notify_push && $this->deviceTokens()->exists());
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Delete the account. Follower counts of the creators they followed are refreshed, since the
     * follows go with the user through a database cascade.
     */
    public function deleteAccount(): void
    {
        $influencers = $this->followedInfluencers()->get();

        DB::transaction(function () {
            $this->notifications()->delete();
            $this->tokens()->delete();
            $this->delete();
        });

        $influencers->each->refreshCounts();
    }

    public function follows(): HasMany
    {
        return $this->hasMany(Follow::class);
    }

    public function followedInfluencers(): BelongsToMany
    {
        return $this->belongsToMany(Influencer::class, 'follows')->withTimestamps();
    }

    /**
     * Followed influencers the user put on their watchlist (max 10, checked where it is set).
     */
    public function watchlist(): BelongsToMany
    {
        return $this->followedInfluencers()->wherePivotNotNull('watchlisted_at')->withPivot('watchlisted_at');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    public function keywordAlerts(): HasMany
    {
        return $this->hasMany(KeywordAlert::class);
    }

    /** Bookmarked posts, newest save first via orderByPivot('created_at', 'desc'). */
    public function savedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'saved_posts')->withTimestamps();
    }

    /** Saved post ids, loaded once per request so a page of post cards costs one query. */
    protected ?array $savedPostIds = null;

    public function hasSaved(Post $post): bool
    {
        $this->savedPostIds ??= $this->savedPosts()->pluck('posts.id')->all();

        return in_array($post->id, $this->savedPostIds);
    }

    public function isFollowing(Influencer $influencer): bool
    {
        return $this->follows()->where('influencer_id', $influencer->id)->exists();
    }

    /**
     * Follow an influencer, tracking the given account ids (all of their accounts when null).
     */
    public function follow(Influencer $influencer, ?array $accountIds = null): Follow
    {
        return DB::transaction(function () use ($influencer, $accountIds) {
            $follow = $this->follows()->firstOrCreate(['influencer_id' => $influencer->id]);

            $follow->accounts()->sync(
                $influencer->accounts()
                    ->when($accountIds !== null, fn ($q) => $q->whereKey($accountIds))
                    ->pluck('id')
            );

            $influencer->refreshCounts();

            return $follow;
        });
    }

    public function unfollow(Influencer $influencer): void
    {
        $this->follows()->where('influencer_id', $influencer->id)->delete();

        $influencer->refreshCounts();
    }

    public function getImageUrlAttribute(): string
    {
        return $this->avatarUrl() ?: 'https://ui-avatars.com/api/?name='.urlencode($this->name);
    }

    /**
     * The uploaded avatar, or null so the UI falls back to initials.
     */
    public function avatarUrl(): ?string
    {
        return $this->getFirstMediaUrl('users') ?: null;
    }

    /**
     * The uploaded profile banner, or null so the UI shows a plain background.
     */
    public function bannerUrl(): ?string
    {
        return $this->getFirstMediaUrl('banners') ?: null;
    }
}
