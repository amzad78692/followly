<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Taxonomy;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * Influencer categories: rows of the taxonomies table with type "category". The emoji shown on
 * Explore lives in meta.emoji.
 */
class CategoryResource extends Resource
{
    public const TYPE = 'category';

    protected static ?string $model = Taxonomy::class;

    protected static ?string $slug = 'categories';

    protected static ?string $modelLabel = 'category';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', self::TYPE);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('meta.emoji')
                    ->label('Emoji')
                    ->helperText('Shown next to the name on Explore.')
                    ->maxLength(16),
                TextInput::make('slug')
                    ->helperText('Left empty, it is made from the name.')
                    ->alphaDash()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('type', self::TYPE)),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('influencers'))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->prefix(fn (Taxonomy $record) => filled($record->meta['emoji'] ?? null) ? $record->meta['emoji'].'  ' : null),
                TextColumn::make('slug')
                    ->color('gray'),
                TextColumn::make('influencers_count')
                    ->label('Influencers')
                    ->numeric()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription(fn (Taxonomy $record) => $record->influencers_count
                        ? "{$record->influencers_count} influencers in this category become uncategorised."
                        : null),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCategories::route('/'),
        ];
    }
}
