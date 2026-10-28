<?php

namespace App\Filament\Logger\Tables;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Jacobtims\FilamentLogger\Resources\ActivityResource\Tables\ActivitiesTable as BaseActivitiesTable;

/**
 * jacobtims/filament-logger 1.2 names filters "properties->old" / "properties->attributes"
 * and reads $data keys without fallbacks, which throws "Undefined array key" on Filament 5.
 * Same filters with safe names and null-safe state access.
 */
class ActivitiesTable extends BaseActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return parent::configure($table)
            ->filters([
                SelectFilter::make('log_name')
                    ->label(__('filament-logger::filament-logger.resource.label.type'))
                    ->options(static::getLogNameList()),

                SelectFilter::make('subject_type')
                    ->label(__('filament-logger::filament-logger.resource.label.subject_type'))
                    ->options(static::getSubjectTypeList()),

                static::propertiesFilter('old_properties', 'old', 'properties->old', 'old_attributes'),

                static::propertiesFilter('new_properties', 'new', 'properties->attributes', 'new_attributes'),

                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('logged_at')
                            ->label(__('filament-logger::filament-logger.resource.label.logged_at'))
                            ->displayFormat(config('filament-logger.date_format', 'd/m/Y')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['logged_at'] ?? null,
                        fn (Builder $query, $date): Builder => $query->whereDate('created_at', $date),
                    )),
            ]);
    }

    protected static function propertiesFilter(string $name, string $field, string $column, string $indicatorLabel): Filter
    {
        return Filter::make($name)
            ->indicateUsing(function (array $data) use ($field, $indicatorLabel): ?string {
                if (blank($data[$field] ?? null)) {
                    return null;
                }

                return __("filament-logger::filament-logger.resource.label.{$indicatorLabel}").$data[$field];
            })
            ->schema([
                TextInput::make($field)
                    ->label(__("filament-logger::filament-logger.resource.label.{$field}"))
                    ->hint(__('filament-logger::filament-logger.resource.label.properties_hint')),
            ])
            ->query(fn (Builder $query, array $data): Builder => $query->when(
                $data[$field] ?? null,
                fn (Builder $query, $value): Builder => $query->where($column, 'like', "%{$value}%"),
            ));
    }
}
