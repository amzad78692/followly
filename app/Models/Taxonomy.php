<?php

namespace App\Models;

use Aliziodev\LaravelTaxonomy\Models\Taxonomy as ModelsTaxonomy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Taxonomy extends ModelsTaxonomy
{
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    protected static function booted()
    {
        static::deleted(function (Taxonomy $taxonomy) {
            if (! config('taxonomy.soft_deletes')) {
                $taxonomy->forceDelete();
            }
        });
    }
}
