<?php

use App\Enums\Platform;
use App\Platforms\Data\AccountData;
use App\Platforms\PlatformManager;
use Tests\Fakes\FakeDriver;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Platform fakes
|--------------------------------------------------------------------------
*/

function profile(Platform $platform, string $handle, array $attributes = []): AccountData
{
    return new AccountData(...[
        'platform' => $platform,
        'externalId' => "{$platform->value}-{$handle}",
        'handle' => $handle,
        'name' => ucfirst($handle),
        'followers' => 1000,
        ...$attributes,
    ]);
}

/**
 * Swap every driver for a fake answering with the given profiles.
 *
 * @return array<string, FakeDriver>
 */
function fakePlatforms(AccountData ...$profiles): array
{
    $manager = app(PlatformManager::class);

    return collect(Platform::cases())->mapWithKeys(function (Platform $platform) use ($manager, $profiles) {
        $driver = FakeDriver::with($platform, ...array_filter($profiles, fn (AccountData $p) => $p->platform === $platform));
        $manager->extend($platform, $driver);

        return [$platform->value => $driver];
    })->all();
}
