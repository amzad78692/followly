<?php

namespace App\Filament\Logger;

use App\Filament\Logger\Tables\ActivitiesTable;
use Filament\Tables\Table;
use Jacobtims\FilamentLogger\Resources\ActivityResource as BaseActivityResource;

/**
 * Swapped in via config('filament-logger.activity_resource'); the package pages
 * resolve their resource from that config, so only the table is overridden here.
 */
class ActivityResource extends BaseActivityResource
{
    public static function table(Table $table): Table
    {
        return ActivitiesTable::configure($table);
    }
}
