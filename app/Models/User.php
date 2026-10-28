<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
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
        ];
    }

    public function follows(): HasMany
    {
        return $this->hasMany(Follow::class);
    }

    public function followedInfluencers(): BelongsToMany
    {
        return $this->belongsToMany(Influencer::class, 'follows')->withTimestamps();
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
        $url = $this->getFirstMediaUrl('users');

        return $url ?: 'https://ui-avatars.com/api/?name='.urlencode($this->name);
    }
}
