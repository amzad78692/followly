<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Follow extends Model
{
    protected $fillable = [
        'user_id',
        'influencer_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function influencer(): BelongsTo
    {
        return $this->belongsTo(Influencer::class);
    }

    /**
     * The influencer's accounts this user chose to track.
     */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(InfluencerAccount::class, 'follow_accounts')->using(FollowAccount::class);
    }
}
