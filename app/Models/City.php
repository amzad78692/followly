<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'country_id',
        'state_id',
        'name',
        'country_code',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }
}
