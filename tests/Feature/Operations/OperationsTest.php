<?php

use App\Enums\Platform;
use App\Enums\SyncStatus;
use App\Models\Admin;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Models\User;
use App\Notifications\HealthAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
    Notification::fake();
});

function failJob(string $job, string $error): void
{
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'platforms',
        'payload' => json_encode(['displayName' => $job]), 'exception' => "{$error}\n#0 stack trace", 'failed_at' => now(),
    ]);
}

/**
 * One tracked account per status given, spread over creators (one account per platform each).
 */
function trackedAccounts(SyncStatus ...$statuses): void
{
    $user = User::factory()->create();

    collect($statuses)->chunk(count(Platform::cases()))->each(function ($chunk) use ($user) {
        $influencer = Influencer::factory()->create();
        $accounts = $chunk->values()->map(fn (SyncStatus $status, int $i) => InfluencerAccount::factory()->for($influencer)->create([
            'platform' => Platform::cases()[$i], 'sync_status' => $status, 'sync_error' => $status === SyncStatus::Failed ? 'HTTP 500' : null,
        ]));

        $user->follow($influencer, $accounts->pluck('id')->all());
    });
}

it('emails admins about jobs that failed since the last check, once', function () {
    failJob('App\Jobs\SyncApifyBatch', 'Apify is unreachable');
    failJob('App\Jobs\SyncApifyBatch', 'Apify is unreachable');
    failJob('App\Jobs\SyncAccountPosts', 'Server error');

    $this->artisan('monitor:check')->assertSuccessful();
    $this->artisan('monitor:check')->assertSuccessful();

    Notification::assertSentToTimes($this->admin, HealthAlert::class, 1);
    Notification::assertSentTo($this->admin, HealthAlert::class, fn (HealthAlert $alert) => $alert->subject === 'Followly: 3 queued jobs failed'
        && in_array('App\Jobs\SyncApifyBatch: 2', $alert->lines, true)
        && collect($alert->lines)->contains(fn ($line) => str_starts_with($line, 'Latest error: ')));
});

it('emails admins once a day when many tracked accounts fail to sync', function () {
    trackedAccounts(SyncStatus::Failed, SyncStatus::Failed, SyncStatus::Failed, SyncStatus::Synced);

    $this->artisan('monitor:check')->assertSuccessful();
    $this->artisan('monitor:check')->assertSuccessful();

    Notification::assertSentToTimes($this->admin, HealthAlert::class, 1);
    Notification::assertSentTo($this->admin, HealthAlert::class, fn (HealthAlert $alert) => $alert->subject === 'Followly: 3 tracked accounts are failing to sync'
        && in_array('Most common error: HTTP 500', $alert->lines, true));
});

it('stays quiet below the error rate or the minimum count', function (array $statuses) {
    trackedAccounts(...$statuses);

    $this->artisan('monitor:check')->assertSuccessful();

    Notification::assertNothingSent();
})->with([
    'rate under 25%' => [[SyncStatus::Failed, SyncStatus::Failed, SyncStatus::Failed, ...array_fill(0, 10, SyncStatus::Synced)]],
    'fewer than 3 failed' => [[SyncStatus::Failed, SyncStatus::Failed]],
]);

it('backs up the database gzipped and deletes old backups', function () {
    Storage::fake('local');
    Storage::disk('local')->put('backups/followly-old.sql.gz', 'old');
    touch(Storage::disk('local')->path('backups/followly-old.sql.gz'), now()->subDays(15)->getTimestamp());
    Storage::disk('local')->put('backups/followly-recent.sql.gz', 'recent');

    Process::fake(function (PendingProcess $process) {
        $file = Str::after(collect($process->command)->first(fn ($arg) => str_starts_with($arg, '--result-file=')), '=');
        file_put_contents($file, 'CREATE TABLE users;');

        return Process::result();
    });

    config([
        'database.default' => 'mysql',
        'database.connections.mysql' => ['driver' => 'mysql', 'host' => 'db', 'port' => 3306, 'database' => 'followly', 'username' => 'app', 'password' => 'secret', 'unix_socket' => ''],
    ]);

    try {
        $this->artisan('db:backup')->assertSuccessful();
    } finally {
        config(['database.default' => 'sqlite']);
    }

    Process::assertRan(fn (PendingProcess $process) => in_array('--single-transaction', $process->command, true)
        && end($process->command) === 'followly'
        && $process->environment === ['MYSQL_PWD' => 'secret']
        && ! collect($process->command)->contains(fn ($arg) => str_contains($arg, 'secret')));

    $backups = collect(Storage::disk('local')->files('backups'));

    expect($backups)->toHaveCount(2)
        ->and($backups)->toContain('backups/followly-recent.sql.gz')
        ->and(gzdecode(Storage::disk('local')->get($backups->first(fn ($file) => str_starts_with($file, 'backups/followly-2')))))->toBe('CREATE TABLE users;');
    Notification::assertNothingSent();
});

it('emails admins when the backup fails', function () {
    // The test database is SQLite, which db:backup does not support.
    $this->artisan('db:backup')->assertFailed();

    Notification::assertSentTo($this->admin, HealthAlert::class, fn (HealthAlert $alert) => $alert->subject === 'Followly: the database backup failed'
        && str_contains($alert->lines[0], 'supports MySQL and MariaDB, not sqlite'));
});
