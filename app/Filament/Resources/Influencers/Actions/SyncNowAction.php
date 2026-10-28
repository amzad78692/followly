<?php

namespace App\Filament\Resources\Influencers\Actions;

use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Sync\SyncDispatcher;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Queue a sync right away, skipping the scheduler's interval. Works on an influencer (all of
 * their accounts) or on accounts directly.
 */
class SyncNowAction
{
    public static function make(): Action
    {
        return Action::make('syncNow')
            ->label('Sync now')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->authorize('update')
            ->action(fn (Influencer|InfluencerAccount $record) => static::sync(collect([$record])));
    }

    public static function bulk(): BulkAction
    {
        return BulkAction::make('syncNow')
            ->label('Sync now')
            ->icon(Heroicon::OutlinedArrowPath)
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records) => static::sync($records));
    }

    /**
     * @param  Collection<int, Influencer|InfluencerAccount>  $records
     */
    protected static function sync(Collection $records): void
    {
        $accounts = $records->flatMap(fn ($record) => $record instanceof Influencer ? $record->accounts : [$record]);

        $queued = app(SyncDispatcher::class)->now($accounts);

        Notification::make()
            ->title($queued ? "Sync queued for {$queued} ".Str::plural('account', $queued) : 'No accounts to sync')
            ->{$queued ? 'success' : 'warning'}()
            ->send();
    }
}
