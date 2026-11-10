<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A user's named group of influencers, optionally shared by link.
 */
class Collection extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'share_token',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function influencers(): BelongsToMany
    {
        return $this->belongsToMany(Influencer::class);
    }
}
