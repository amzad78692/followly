<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'country_id',
        'name',
        'country_code',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function cities()
    {
        return $this->hasMany(City::class);
    }
}
