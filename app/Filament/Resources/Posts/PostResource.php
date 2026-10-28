<?php

namespace App\Filament\Resources\Posts;

use App\Enums\Platform;
use App\Enums\PostType;
use App\Filament\Resources\Influencers\InfluencerResource;
use App\Filament\Resources\Posts\Pages\ManagePosts;
use App\Models\Post;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Synced posts, read-only. Admins can hide a post (it stays stored, so a sync doesn't bring it
 * back) or delete it.
 */
class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                ImageEntry::make('images')
                    ->hiddenLabel()
                    ->state(fn (Post $record) => $record->images[0] ?? null)
                    ->imageHeight(180)
                    ->columnSpanFull(),
                TextEntry::make('influencer.name')->label('Influencer'),
                TextEntry::make('platform')->badge(),
                TextEntry::make('type')->placeholder('—'),
                TextEntry::make('published_at')->dateTime(),
                TextEntry::make('title')->placeholder('—')->columnSpanFull(),
                TextEntry::make('body')->placeholder('—')->columnSpanFull(),
                TextEntry::make('permalink')->url(fn (Post $record) => $record->permalink)->openUrlInNewTab()->columnSpanFull(),
                KeyValueEntry::make('metrics')->placeholder('—')->columnSpanFull(),
                TextEntry::make('hidden_at')->label('Hidden')->dateTime()->placeholder('No'),
                TextEntry::make('created_at')->label('Stored')->dateTime(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('influencer'))
            ->defaultSort('published_at', 'desc')
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('')
                    ->state(fn (Post $record) => $record->images[0] ?? null)
                    ->imageHeight(40),
                TextColumn::make('title')
                    ->label('Post')
                    ->state(fn (Post $record) => $record->title ?: Str::limit((string) $record->body, 80) ?: 'Untitled')
                    ->searchable(['title', 'body'])
                    ->limit(70)
                    ->wrap()
                    ->description(fn (Post $record) => $record->influencer?->name),
                TextColumn::make('platform')
                    ->badge(),
                TextColumn::make('type')
                    ->toggleable(),
                TextColumn::make('published_at')
                    ->label('Published')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                IconColumn::make('hidden_at')
                    ->label('Hidden')
                    ->state(fn (Post $record) => $record->isHidden())
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedEyeSlash)
                    ->falseIcon(Heroicon::OutlinedEye)
                    ->trueColor('danger')
                    ->falseColor('gray'),
            ])
            ->filters([
                SelectFilter::make('influencer')
                    ->relationship('influencer', 'name')
                    ->searchable(),
                SelectFilter::make('platform')
                    ->options(Platform::class),
                SelectFilter::make('type')
                    ->options(PostType::class),
                TernaryFilter::make('hidden')
                    ->label('Hidden')
                    ->nullable()
                    ->attribute('hidden_at'),
                Filter::make('today')
                    ->label('Stored today')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('created_at', '>=', today())),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    Action::make('open')
                        ->label('Open on platform')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (Post $record) => $record->permalink)
                        ->openUrlInNewTab(),
                    Action::make('influencer')
                        ->label('View influencer')
                        ->icon(Heroicon::OutlinedUser)
                        ->url(fn (Post $record) => InfluencerResource::getUrl('view', ['record' => $record->influencer_id])),
                    Action::make('hide')
                        ->label('Hide')
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->color('warning')
                        ->authorize('update')
                        ->requiresConfirmation()
                        ->modalDescription('It disappears from feeds and the profile. Syncs won\'t store it again.')
                        ->visible(fn (Post $record) => ! $record->isHidden())
                        ->action(fn (Post $record) => $record->update(['hidden_at' => now()])),
                    Action::make('unhide')
                        ->label('Show again')
                        ->icon(Heroicon::OutlinedEye)
                        ->authorize('update')
                        ->visible(fn (Post $record) => $record->isHidden())
                        ->action(fn (Post $record) => $record->update(['hidden_at' => null])),
                    DeleteAction::make()
                        ->label('Remove')
                        ->modalDescription('The post is deleted. If it is still among the account\'s newest posts, the next sync may store it again; hide it to keep it out for good.'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('hide')
                        ->label('Hide')
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => Post::whereKey($records->modelKeys())->whereNull('hidden_at')->update(['hidden_at' => now()])),
                    BulkAction::make('unhide')
                        ->label('Show again')
                        ->icon(Heroicon::OutlinedEye)
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => Post::whereKey($records->modelKeys())->update(['hidden_at' => null])),
                    DeleteBulkAction::make()
                        ->label('Remove'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePosts::route('/'),
        ];
    }
}
