<?php

namespace App\Filament\Resources\Influencers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class InfluencerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('real_name')
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->helperText('Used in the profile link. Left empty, it is made from the name.')
                            ->alphaDash()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('language')
                            ->maxLength(10)
                            ->placeholder('en'),
                        Textarea::make('bio')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Section::make('Classification')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name', fn (Builder $query) => $query->where('type', 'category')->orderBy('sort_order')->orderBy('name'))
                            ->searchable()
                            ->preload(),
                        Select::make('country_id')
                            ->label('Country')
                            ->relationship('country', 'name')
                            ->searchable(),
                        Toggle::make('is_verified')
                            ->label('Verified')
                            ->helperText('Shows the verified badge.'),
                        Toggle::make('is_featured')
                            ->label('Featured'),
                    ]),
                Section::make('Images')
                    ->columnSpanFull()
                    ->description('An upload replaces the image from the platform.')
                    ->columns(2)
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('avatar')
                            ->collection('avatar')
                            ->image()
                            ->avatar(),
                        SpatieMediaLibraryFileUpload::make('cover')
                            ->collection('cover')
                            ->image(),
                        TextInput::make('avatar_url')
                            ->label('Avatar URL (from the platform)')
                            ->url()
                            ->maxLength(2048),
                        TextInput::make('cover_url')
                            ->label('Cover URL (from the platform)')
                            ->url()
                            ->maxLength(2048),
                    ]),
            ]);
    }
}
