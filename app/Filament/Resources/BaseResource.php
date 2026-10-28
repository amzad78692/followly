<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use Illuminate\Support\Str;

abstract class BaseResource extends Resource
{
    public static function getLabel(): string
    {
        return __(static::generateDefaultLabel());
    }

    public static function getPluralLabel(): string
    {
        return __(Str::plural(static::generateDefaultLabel()));
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralLabel();
    }

    protected static function generateDefaultLabel(): string
    {
        $classBaseName = class_basename(static::class); // e.g. "UserResource"
        $rawName = Str::before($classBaseName, 'Resource'); // e.g. "User"
        return Str::headline($rawName); // e.g. "User" or "User Type"
    }
}
