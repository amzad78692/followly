<?php

namespace App\Filament\Resources\Influencers\Pages;

use App\Filament\Resources\Influencers\Actions\AddFromLinkAction;
use App\Filament\Resources\Influencers\InfluencerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInfluencers extends ListRecords
{
    protected static string $resource = InfluencerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AddFromLinkAction::make(),
            CreateAction::make()
                ->label('Add by hand')
                ->color('gray'),
        ];
    }
}
