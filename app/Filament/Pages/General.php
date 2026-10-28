<?php

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class General extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string $settings = GeneralSettings::class;

    protected static ?int $navigationSort = 1;

    public function getTitle(): string|Htmlable
    {
        return __('General');
    }

    public static function getNavigationLabel(): string
    {
        return __('General');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Settings');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('website_name')
                    ->columnSpanFull(),
                FileUpload::make('logo')
                    ->image()
                    ->directory('settings'),
                FileUpload::make('favicon')
                    ->image()
                    ->directory('settings'),
            ]);
    }
}
