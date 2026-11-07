<?php

namespace App\Filament\Imports;

use App\Enums\Platform;
use App\Models\Country;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Models\Taxonomy;
use App\Platforms\Data\AccountData;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\PlatformManager;
use App\Support\InfluencerImporter as AccountImporter;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

/**
 * Import creators from a CSV of profile links. Each link is looked up with its platform driver
 * (same as "Add from link"); rows without a single real profile link fail and land in the
 * failed-rows CSV. A row whose link matches a stored account updates that influencer.
 */
class InfluencerImporter extends Importer
{
    protected static ?string $model = Influencer::class;

    /** Path segments of search pages, which are not profiles. */
    protected const SEARCH_PATHS = ['results', 'search', 'explore', 'hashtag'];

    /** @var Collection<int, AccountData> */
    protected Collection $accounts;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->guess(['creator', 'creator / channel', 'news outlet', 'channel'])
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('category')
                ->guess(['content category'])
                ->fillRecordUsing(function (Influencer $record, ?string $state) {
                    if (filled($state)) {
                        $record->category_id = Taxonomy::where('type', 'category')->where('name', $state)->value('id') ?? $record->category_id;
                    }
                }),
            ImportColumn::make('country')
                ->guess(['country / region'])
                ->fillRecordUsing(function (Influencer $record, ?string $state) {
                    if (filled($state)) {
                        $record->country_id = Country::where('name', $state)->value('id') ?? $record->country_id;
                    }
                }),
            ImportColumn::make('language')
                ->ignoreBlankState()
                ->rules(['nullable', 'max:50']),
            ...collect(Platform::cases())->map(fn (Platform $platform) => ImportColumn::make($platform->value)
                ->label($platform->label())
                ->guess($platform === Platform::X ? ['x (twitter)', 'twitter'] : [])
                ->fillRecordUsing(fn () => null)),
        ];
    }

    public function resolveRecord(): Influencer
    {
        $platforms = app(PlatformManager::class);
        $existing = null;
        $this->accounts = collect();

        foreach (Platform::cases() as $platform) {
            $url = trim((string) ($this->data[$platform->value] ?? ''));

            if (! $this->isProfileUrl($platform, $url)) {
                continue;
            }

            if ($stored = InfluencerAccount::findFromUrl($platform, $url)) {
                $existing ??= $stored->influencer;

                continue;
            }

            try {
                $account = $platforms->driver($platform)->resolve($url);
            } catch (PlatformException $e) {
                report($e);

                throw new RowImportFailedException("Couldn't reach {$platform->label()}; import this row again later.");
            }

            if (! $account) {
                continue;
            }

            // The link may be a new handle for a channel we already store.
            if ($stored = InfluencerAccount::where('platform', $platform)->where('external_id', $account->externalId)->first()) {
                $existing ??= $stored->influencer;
            } else {
                $this->accounts->push($account);
            }
        }

        if (! $existing && $this->accounts->isEmpty()) {
            throw new RowImportFailedException('No profile link found: search links and empty cells are skipped.');
        }

        return $existing ?? new Influencer;
    }

    protected function beforeCreate(): void
    {
        $this->record->fill([
            'bio' => $this->accounts->pluck('bio')->filter()->first(),
            'avatar_url' => $this->accounts->pluck('avatarUrl')->filter()->first(),
            'is_verified' => $this->accounts->contains(fn (AccountData $account) => $account->isVerified),
        ]);
        $this->record->createdBy()->associate($this->import->user);
    }

    protected function afterSave(): void
    {
        app(AccountImporter::class)->import($this->accounts, $this->record, $this->import->user);
    }

    protected function isProfileUrl(Platform $platform, string $url): bool
    {
        if ($url === '' || Platform::fromUrl($url) !== $platform) {
            return false;
        }

        $first = explode('/', trim(parse_url($url, PHP_URL_PATH) ?? '', '/'))[0];

        return $first !== '' && ! in_array(strtolower($first), self::SEARCH_PATHS, true);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your influencer import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
