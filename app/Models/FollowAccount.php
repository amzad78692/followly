<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class FollowAccount extends Pivot
{
    protected $table = 'follow_accounts';

    public $timestamps = false;
}
