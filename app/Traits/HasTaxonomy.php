<?php

namespace App\Traits;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use App\Models\Taxonomy;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasTaxonomy
{
    public function taxonomies(): MorphToMany
    {
        return $this->morphToMany(Taxonomy::class, 'taxonomable');
    }

    public function categories()
    {
        return $this->taxonomies()
            ->where('type', TaxonomyType::Category->value);
    }

    public function tags()
    {
        return $this->taxonomies()
            ->where('type', TaxonomyType::Tag->value);
    }
    public function colors()
    {
        return $this->taxonomies()
            ->where('type', TaxonomyType::Color->value);
    }

    public function sizes()
    {
        return $this->taxonomies()
            ->where('type', TaxonomyType::Size->value);
    }

    public function units()
    {
        return $this->taxonomies()
            ->where('type', TaxonomyType::Unit->value);
    }

    public function types()
    {
        return $this->taxonomies()
            ->where('type', TaxonomyType::Type->value);
    }

    public function brands()
    {
        return $this->taxonomies()
            ->where('type', TaxonomyType::Brand->value);
    }

    public function models()
    {
        return $this->taxonomies()
            ->where('type', TaxonomyType::Model->value);
    }

    public function variants()
    {
        return $this->taxonomies()
            ->where('type', TaxonomyType::Variant->value);
    }
}
