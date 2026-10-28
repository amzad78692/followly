<?php

namespace App\Filament\Resources\Influencers\Pages;

use App\Filament\Resources\Influencers\InfluencerResource;
use App\Models\Influencer;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateInfluencer extends CreateRecord
{
    protected static string $resource = InfluencerResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $influencer = new Influencer($data);
        $influencer->createdBy()->associate(auth()->user());
        $influencer->save();

        return $influencer;
    }
}
