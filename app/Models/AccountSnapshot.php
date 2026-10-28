<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'influencer_account_id',
        'followers',
        'captured_on',
    ];

    protected function casts(): array
    {
        return [
            'followers' => 'integer',
            'captured_on' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(InfluencerAccount::class, 'influencer_account_id');
    }
}
