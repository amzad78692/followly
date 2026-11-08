<?php

namespace App\Models;

use App\Enums\DevicePlatform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A watch or phone that can receive push notifications.
 */
class DeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'personal_access_token_id',
        'platform',
        'token',
        'timezone',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
