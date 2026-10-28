<?php

namespace App\Filament\Resources\Influencers\Pages;

use App\Filament\Resources\Influencers\Actions\SyncNowAction;
use App\Filament\Resources\Influencers\InfluencerResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditInfluencer extends EditRecord
{
    protected static string $resource = InfluencerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            SyncNowAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
