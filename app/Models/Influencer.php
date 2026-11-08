<?php

namespace App\Models;

use App\Enums\Platform;
use Database\Factories\InfluencerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Influencer extends Model implements HasMedia
{
    /** @use HasFactory<InfluencerFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'real_name',
        'bio',
        'avatar_url',
        'cover_url',
        'category_id',
        'country_id',
        'language',
        'is_verified',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'is_featured' => 'boolean',
            'followers_count' => 'integer',
            'combined_reach' => 'integer',
            'trending_score' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Influencer $influencer) {
            $influencer->slug ??= static::uniqueSlug($influencer->name);
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: Str::lower(Str::random(8));
        $slug = $base;

        for ($i = 2; static::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
        $this->addMediaCollection('cover')->singleFile();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class, 'category_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(InfluencerAccount::class)->orderByRaw(Platform::orderSql());
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function follows(): HasMany
    {
        return $this->hasMany(Follow::class);
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows')->withTimestamps();
    }

    /**
     * The user or admin who added this influencer.
     */
    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(fn (Builder $q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('real_name', 'like', "%{$term}%")
            ->orWhereHas('accounts', fn (Builder $q) => $q->where('handle', 'like', '%'.ltrim($term, '@').'%'))));
    }

    public function scopeMostTracked(Builder $query): Builder
    {
        return $query->orderByDesc('followers_count');
    }

    /**
     * Highest trending score first (see App\Discovery\TrendingRanker), then most tracked.
     */
    public function scopeTrending(Builder $query): Builder
    {
        return $query->orderByDesc('trending_score')->mostTracked();
    }

    /**
     * Creators in the user's most-followed categories that they don't follow yet, most tracked first.
     * Users who follow no categorised creators get the most tracked creators overall.
     */
    public function scopeRecommendedFor(Builder $query, User $user): Builder
    {
        $followed = $user->follows()->select('influencer_id');

        $categoryIds = static::whereIn('id', $followed)
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->orderByRaw('count(*) desc')
            ->limit(config('discovery.recommended_categories'))
            ->pluck('category_id');

        return $query
            ->whereNotIn('id', $followed)
            ->when($categoryIds->isNotEmpty(), fn (Builder $q) => $q->whereIn('category_id', $categoryIds))
            ->mostTracked();
    }

    public function getAvatarAttribute(): string
    {
        return $this->getFirstMediaUrl('avatar')
            ?: $this->avatar_url
            ?: 'https://ui-avatars.com/api/?background=eaf3fe&color=006bdb&name='.urlencode($this->name);
    }

    public function getCoverAttribute(): ?string
    {
        return $this->getFirstMediaUrl('cover') ?: $this->cover_url;
    }

    public function getFormattedTrackingAttribute(): string
    {
        return Number::abbreviate($this->followers_count, maxPrecision: 1);
    }

    public function getFormattedReachAttribute(): string
    {
        return Number::abbreviate($this->combined_reach, maxPrecision: 1);
    }

    /**
     * Recalculate every influencer's cached Followly follower count in one query. Follows deleted
     * by the database (a user's account removed) skip refreshCounts(), so this runs hourly.
     */
    public static function recountFollowers(): void
    {
        static::query()->toBase()->update([
            'followers_count' => DB::raw('(select count(*) from follows where follows.influencer_id = influencers.id)'),
        ]);
    }

    /**
     * Recalculate the cached follower totals.
     */
    public function refreshCounts(): void
    {
        $this->forceFill([
            'followers_count' => $this->follows()->count(),
            'combined_reach' => (int) $this->accounts()->sum('followers'),
        ])->saveQuietly();
    }
}
