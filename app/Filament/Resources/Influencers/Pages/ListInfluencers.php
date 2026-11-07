<?php

namespace App\Filament\Resources\Influencers\Pages;

use App\Filament\Imports\InfluencerImporter;
use App\Filament\Resources\Influencers\Actions\AddFromLinkAction;
use App\Filament\Resources\Influencers\InfluencerResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListInfluencers extends ListRecords
{
    protected static string $resource = InfluencerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AddFromLinkAction::make(),
            ImportAction::make()
                ->importer(InfluencerImporter::class)
                ->authorize(fn () => InfluencerResource::canCreate())
                ->color('gray'),
            CreateAction::make()
                ->label('Add by hand')
                ->color('gray'),
        ];
    }
}
