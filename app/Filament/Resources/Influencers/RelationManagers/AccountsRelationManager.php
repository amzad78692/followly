<?php

namespace App\Filament\Resources\Influencers\RelationManagers;

use App\Enums\SyncStatus;
use App\Filament\Resources\Influencers\Actions\SyncNowAction;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\PlatformManager;
use App\Support\InfluencerImporter;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The influencer's platform accounts. Accounts come from the platforms, so they are added by
 * link and never edited by hand; syncs keep them up to date.
 */
class AccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'accounts';

    /**
     * Show the actions on the view page too, not only on edit.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('handle')
            ->columns([
                TextColumn::make('platform')
                    ->badge(),
                TextColumn::make('handle')
                    ->searchable()
                    ->url(fn (InfluencerAccount $record) => $record->url)
                    ->openUrlInNewTab(),
                TextColumn::make('followers')
                    ->formatStateUsing(fn (InfluencerAccount $record) => $record->formatted_followers)
                    ->sortable(),
                IconColumn::make('is_verified')
                    ->label('Verified')
                    ->boolean(),
                TextColumn::make('sync_status')
                    ->label('Sync')
                    ->badge()
                    ->tooltip(fn (InfluencerAccount $record) => $record->sync_error),
                TextColumn::make('sync_error')
                    ->label('Error')
                    ->limit(60)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_synced_at')
                    ->label('Last synced')
                    ->since()
                    ->dateTimeTooltip()
                    ->placeholder('Never')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('sync_status')
                    ->label('Sync')
                    ->options(SyncStatus::class),
            ])
            ->headerActions([
                Action::make('addAccount')
                    ->label('Add account')
                    ->icon(Heroicon::OutlinedPlus)
                    ->authorize(fn () => $this->canCreate())
                    ->schema([
                        TextInput::make('query')
                            ->label('Profile link')
                            ->helperText('A link to their profile, or to one of their videos or posts.')
                            ->placeholder('https://www.instagram.com/handle')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(fn (array $data, Action $action) => $this->addAccount($data['query'], $action)),
            ])
            ->recordActions([
                SyncNowAction::make(),
                DeleteAction::make()
                    ->modalDescription('Its posts are deleted too, and users stop tracking it.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    SyncNowAction::bulk(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected function addAccount(string $query, Action $action): void
    {
        /** @var Influencer $influencer */
        $influencer = $this->getOwnerRecord();

        try {
            $account = app(PlatformManager::class)->resolve($query);
        } catch (PlatformException $e) {
            report($e);
            $this->warn("Couldn't reach {$e->platform->label()}", 'Please try again in a minute.', $action);
        }

        if (! $account) {
            $this->warn('No account found', 'Check the link and try again.', $action);
        }

        if ($influencer->accounts()->where('platform', $account->platform)->exists()) {
            $this->warn("{$influencer->name} already has a {$account->platform->label()} account", null, $action);
        }

        $owner = InfluencerAccount::where('platform', $account->platform)->where('external_id', $account->externalId)->first()?->influencer;

        if ($owner) {
            $this->warn("That account belongs to {$owner->name}", null, $action);
        }

        app(InfluencerImporter::class)->import(collect([$account->platform->value => $account]), $influencer);

        Notification::make()->success()->title("{$account->platform->label()} account added")->body('Its first sync is queued.')->send();
    }

    /**
     * Show a warning and keep the modal open.
     */
    protected function warn(string $title, ?string $body, Action $action): never
    {
        Notification::make()->warning()->title($title)->body($body)->send();

        $action->halt();
    }
}
