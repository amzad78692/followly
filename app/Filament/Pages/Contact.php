<?php

namespace App\Filament\Pages;

use App\Settings\ContactSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class Contact extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static string $settings = ContactSettings::class;

    protected static ?int $navigationSort = 2;

    public function getTitle(): string|Htmlable
    {
        return __('Contact');
    }

    public static function getNavigationLabel(): string
    {
        return __('Contact');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Settings');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->email(),
                TextInput::make('mobile')
                    ->numeric()
                    ->tel(),
                TextInput::make('whatsapp')
                    ->numeric()
                    ->tel(),
                TextInput::make('address'),
            ]);
    }
}
