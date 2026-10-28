<?php

namespace App\Filament\Resources\Influencers\Schemas;

use App\Models\Admin;
use App\Models\Influencer;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InfluencerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        ImageEntry::make('avatar')
                            ->hiddenLabel()
                            ->circular()
                            ->imageSize(96)
                            ->state(fn (Influencer $record) => $record->avatar),
                        TextEntry::make('name')
                            ->weight('bold')
                            ->url(fn (Influencer $record) => route('influencers.show', $record))
                            ->openUrlInNewTab(),
                        TextEntry::make('real_name')->placeholder('—'),
                        TextEntry::make('category.name')->label('Category')->placeholder('—'),
                        TextEntry::make('country.name')->label('Country')->placeholder('—'),
                        TextEntry::make('language')->placeholder('—'),
                        IconEntry::make('is_verified')->label('Verified')->boolean(),
                        IconEntry::make('is_featured')->label('Featured')->boolean(),
                        TextEntry::make('bio')->placeholder('—')->columnSpanFull(),
                    ]),
                Section::make('Numbers')
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('followers_count')->label('Tracking on Followly')->numeric(),
                        TextEntry::make('combined_reach')->label('Combined reach')->numeric(),
                        TextEntry::make('trending_score')->label('Trending score')->numeric(decimalPlaces: 1),
                        TextEntry::make('posts_count')->label('Posts')->state(fn (Influencer $record) => $record->posts()->count())->numeric(),
                    ]),
                Section::make('Added')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('createdBy.name')
                            ->label('Added by')
                            ->placeholder('Seeded or unknown')
                            ->suffix(fn (Influencer $record) => match (true) {
                                $record->createdBy instanceof Admin => ' (admin)',
                                $record->createdBy !== null => ' (user)',
                                default => '',
                            }),
                        TextEntry::make('created_at')->label('Added on')->dateTime(),
                    ]),
            ]);
    }
}
