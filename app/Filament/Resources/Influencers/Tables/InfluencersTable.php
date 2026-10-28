<?php

namespace App\Filament\Resources\Influencers\Tables;

use App\Enums\Platform;
use App\Enums\SyncStatus;
use App\Filament\Resources\Influencers\Actions\SyncNowAction;
use App\Models\Admin;
use App\Models\Influencer;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InfluencersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'accounts', 'createdBy', 'media']))
            ->defaultSort('followers_count', 'desc')
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->circular()
                    ->state(fn (Influencer $record) => $record->avatar),
                TextColumn::make('name')
                    ->searchable(query: fn (Builder $query, string $search) => $query->search($search))
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Influencer $record) => $record->real_name !== $record->name ? $record->real_name : null),
                TextColumn::make('platforms')
                    ->label('Accounts')
                    ->badge()
                    ->state(fn (Influencer $record) => $record->accounts->pluck('platform')->all()),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('combined_reach')
                    ->label('Reach')
                    ->formatStateUsing(fn (Influencer $record) => $record->formatted_reach)
                    ->sortable(),
                TextColumn::make('followers_count')
                    ->label('Tracking')
                    ->numeric()
                    ->sortable(),
                ToggleColumn::make('is_verified')
                    ->label('Verified'),
                ToggleColumn::make('is_featured')
                    ->label('Featured'),
                TextColumn::make('createdBy.name')
                    ->label('Added by')
                    ->placeholder('—')
                    ->description(fn (Influencer $record) => match (true) {
                        $record->createdBy instanceof Admin => 'Admin',
                        $record->createdBy !== null => 'User',
                        default => null,
                    })
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name', fn (Builder $query) => $query->where('type', 'category'))
                    ->preload(),
                SelectFilter::make('platform')
                    ->options(Platform::class)
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn (Builder $q, $platform) => $q->whereHas('accounts', fn (Builder $q) => $q->where('platform', $platform)))),
                TernaryFilter::make('is_verified')->label('Verified'),
                TernaryFilter::make('is_featured')->label('Featured'),
                SelectFilter::make('added_by')
                    ->label('Added by')
                    ->options(['user' => 'A user', 'admin' => 'An admin'])
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn (Builder $q, $type) => $q->where('created_by_type', $type === 'admin' ? (new Admin)->getMorphClass() : (new User)->getMorphClass()))),
                Filter::make('sync_failed')
                    ->label('Has a failed sync')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereHas('accounts', fn (Builder $q) => $q->where('sync_status', SyncStatus::Failed))),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    SyncNowAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    SyncNowAction::bulk(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
