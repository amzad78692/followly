<?php

namespace App\Filament\Resources\Influencers\Pages;

use App\Filament\Resources\Influencers\Actions\SyncNowAction;
use App\Filament\Resources\Influencers\InfluencerResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewInfluencer extends ViewRecord
{
    protected static string $resource = InfluencerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openProfile')
                ->label('Open on site')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn () => route('influencers.show', $this->record))
                ->openUrlInNewTab()
                ->hidden(fn () => $this->record->trashed()),
            SyncNowAction::make(),
            EditAction::make(),
        ];
    }
}
