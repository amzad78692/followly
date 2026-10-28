<?php

namespace App\Models;

use App\Enums\Platform;
use App\Enums\PostType;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected $fillable = [
        'influencer_id',
        'influencer_account_id',
        'platform',
        'external_id',
        'type',
        'title',
        'body',
        'media',
        'duration_seconds',
        'permalink',
        'metrics',
        'published_at',
        'hidden_at',
    ];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'type' => PostType::class,
            'media' => 'array',
            'metrics' => 'array',
            'duration_seconds' => 'integer',
            'published_at' => 'datetime',
            'hidden_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Influencer and platform are copied from the account so feed queries need no join.
        static::creating(function (Post $post) {
            if ($post->influencer_account_id && (! $post->influencer_id || ! $post->platform)) {
                $account = InfluencerAccount::find($post->influencer_account_id);
                $post->influencer_id ??= $account?->influencer_id;
                $post->platform ??= $account?->platform;
            }
        });
    }

    public function influencer(): BelongsTo
    {
        return $this->belongsTo(Influencer::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(InfluencerAccount::class, 'influencer_account_id');
    }

    /**
     * Posts an admin has not hidden.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('hidden_at');
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    /**
     * Posts from the accounts a user tracks, newest first.
     */
    public function scopeFeedFor(Builder $query, User $user): Builder
    {
        return $query
            ->visible()
            ->whereIn('influencer_account_id', FollowAccount::query()
                ->select('follow_accounts.influencer_account_id')
                ->join('follows', 'follows.id', '=', 'follow_accounts.follow_id')
                ->where('follows.user_id', $user->id))
            ->latest('published_at');
    }

    public function scopePlatform(Builder $query, Platform|string|null $platform): Builder
    {
        return $query->when($platform, fn (Builder $q) => $q->where('platform', $platform));
    }

    /**
     * Image URLs for the post (thumbnail for videos, every image for carousels).
     */
    public function getImagesAttribute(): array
    {
        return $this->media['images'] ?? [];
    }

    /**
     * "12:34" or "2:18:05".
     */
    public function getDurationAttribute(): ?string
    {
        if (! $this->duration_seconds) {
            return null;
        }

        $h = intdiv($this->duration_seconds, 3600);
        $m = intdiv($this->duration_seconds % 3600, 60);
        $s = $this->duration_seconds % 60;

        return $h ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    }
}
