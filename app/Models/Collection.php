<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A user's named group of influencers, optionally shared by link and listed publicly in Explore.
 */
class Collection extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'share_token',
        'is_public',
    ];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function influencers(): BelongsToMany
    {
        return $this->belongsToMany(Influencer::class);
    }

    public function coverUrl(): ?string
    {
        return $this->getFirstMediaUrl('cover') ?: null;
    }

    /**
     * The public link, creating its token on first use.
     */
    public function shareUrl(): string
    {
        if (! $this->share_token) {
            $this->update(['share_token' => Str::random(32)]);
        }

        return route('collections.shared', $this->share_token);
    }
}
