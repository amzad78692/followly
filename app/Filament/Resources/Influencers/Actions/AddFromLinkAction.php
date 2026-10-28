<?php

namespace App\Filament\Resources\Influencers\Actions;

use App\Filament\Resources\Influencers\InfluencerResource;
use App\Models\Influencer;
use App\Platforms\Data\AccountData;
use App\Platforms\Exceptions\PlatformException;
use App\Support\InfluencerImporter;
use App\Support\InfluencerLookup;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;

/**
 * Add a creator from a profile link or handle, through the same lookup as the user-facing
 * Add Influencer page: step one finds the accounts, step two picks which ones to store.
 */
class AddFromLinkAction
{
    public static function make(): Action
    {
        return Action::make('addFromLink')
            ->label('Add from link')
            ->icon(Heroicon::OutlinedLink)
            ->authorize(fn () => InfluencerResource::canCreate())
            ->steps([
                Step::make('Link')
                    ->description('Profile link or username')
                    ->schema([
                        TextInput::make('query')
                            ->label('Link or username')
                            ->placeholder('https://www.youtube.com/@MrBeast')
                            ->required()
                            ->maxLength(255),
                        Hidden::make('influencer_id'),
                        Hidden::make('candidates'),
                    ])
                    ->afterValidation(fn (Get $get, Set $set) => static::lookup($get('query'), $set)),
                Step::make('Accounts')
                    ->description('Choose what to add')
                    ->schema([
                        CheckboxList::make('selected')
                            ->label(fn (Get $get) => $get('influencer_id')
                                ? 'New accounts for '.Influencer::find($get('influencer_id'))?->name
                                : 'Accounts found')
                            ->options(fn (Get $get) => collect($get('candidates') ?? [])
                                ->map(fn (array $account) => static::describe(AccountData::fromArray($account)))
                                ->all())
                            ->required(),
                    ]),
            ])
            ->action(function (array $data, InfluencerImporter $importer, Action $action) {
                $accounts = collect($data['candidates'] ?? [])
                    ->only($data['selected'])
                    ->map(fn (array $account) => AccountData::fromArray($account));

                $influencer = $importer->import($accounts, Influencer::find($data['influencer_id'] ?? null), auth()->user());

                Notification::make()->success()->title("{$influencer->name} added")->body('Their first sync is queued.')->send();

                $action->redirect(InfluencerResource::getUrl('view', ['record' => $influencer]));
            });
    }

    /**
     * Find the accounts for the second step, or stop on the first with a notification.
     */
    protected static function lookup(?string $query, Set $set): void
    {
        try {
            $result = app(InfluencerLookup::class)->lookup((string) $query);
        } catch (PlatformException $e) {
            report($e);
            Notification::make()->danger()->title("Couldn't reach {$e->platform->label()}")->body('Please try again in a minute.')->send();

            throw new Halt;
        }

        if ($result->candidates->isEmpty()) {
            $notification = $result->influencer
                ? Notification::make()->warning()->title("{$result->influencer->name} is already on Followly")
                    ->body('Every account we found is stored.')
                    ->actions([Action::make('open')->label('Open')->url(InfluencerResource::getUrl('view', ['record' => $result->influencer]))])
                : Notification::make()->warning()->title('No accounts found')->body('Check the link or username and try again.');

            $notification->send();

            throw new Halt;
        }

        $set('influencer_id', $result->influencer?->id);
        $set('candidates', $result->candidates->map->toArray()->all());
        // Same-username matches are guesses, so they start unticked.
        $set('selected', $result->candidates->reject(fn (AccountData $account) => $account->source === 'handle')->keys()->values()->all());
    }

    protected static function describe(AccountData $account): string
    {
        $source = match ($account->source) {
            'handle' => ' (same username, check it\'s them)',
            'link' => ' (linked from their profile)',
            default => '',
        };

        return "{$account->platform->label()}: {$account->displayHandle()} · {$account->name}, ".number_format($account->followers).' '.strtolower($account->platform->unit()).$source;
    }
}
