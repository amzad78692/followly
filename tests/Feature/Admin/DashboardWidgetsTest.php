<?php

use App\Enums\Platform;
use App\Enums\SyncStatus;
use App\Filament\Widgets\ActiveUsersChart;
use App\Filament\Widgets\ErrorsAndAlerts;
use App\Filament\Widgets\GoogleAnalyticsWidget;
use App\Filament\Widgets\Performance;
use App\Filament\Widgets\PlatformStatus;
use App\Filament\Widgets\SignupsChart;
use App\Models\Admin;
use App\Models\ApifyRun;
use App\Models\InfluencerAccount;
use App\Models\User;
use App\Services\GoogleAnalytics;
use Filament\Facades\Filament;
use Google\Analytics\Data\V1beta\BatchRunReportsRequest;
use Google\Analytics\Data\V1beta\BatchRunReportsResponse;
use Google\Analytics\Data\V1beta\DimensionValue;
use Google\Analytics\Data\V1beta\MetricValue;
use Google\Analytics\Data\V1beta\Row;
use Google\Analytics\Data\V1beta\RunReportResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(superAdmin(), 'admin');
});

/**
 * Binds a GoogleAnalytics whose API call returns $response (or throws it), counting calls.
 */
function fakeAnalytics(BatchRunReportsResponse|Throwable $response): object
{
    config(['services.google_analytics.property_id' => '123', 'services.google_analytics.credentials' => '/keys/ga.json']);

    $fake = new class($response) extends GoogleAnalytics
    {
        public int $calls = 0;

        public function __construct(public BatchRunReportsResponse|Throwable $response) {}

        protected function request(BatchRunReportsRequest $request): BatchRunReportsResponse
        {
            $this->calls++;

            return $this->response instanceof Throwable ? throw $this->response : $this->response;
        }
    };

    app()->instance(GoogleAnalytics::class, $fake);

    return $fake;
}

function gaReport(array $rows): RunReportResponse
{
    return new RunReportResponse(['rows' => array_map(fn (array $row) => new Row([
        'dimension_values' => [new DimensionValue(['value' => $row[0]])],
        'metric_values' => array_map(fn ($v) => new MetricValue(['value' => (string) $v]), array_slice($row, 1)),
    ]), $rows)]);
}

it('counts sign-ups per day split by email, Google and Apple', function () {
    User::factory()->create();
    User::factory()->create(['google_id' => 'g-1']);
    User::factory()->create(['apple_id' => 'a-1']);
    User::factory()->create(['google_id' => 'g-2', 'created_at' => now()->subDays(2)]);
    User::factory()->create(['created_at' => now()->subDays(45)]);

    $widget = new SignupsChart;
    $data = collect($widget->getData()['datasets'])->mapWithKeys(fn ($set) => [$set['label'] => $set['data']]);

    expect($data['Email'])->toHaveCount(30)
        ->and(last($data['Email']))->toBe(1)
        ->and(last($data['Google']))->toBe(1)
        ->and(last($data['Apple']))->toBe(1)
        ->and($data['Google'][27])->toBe(1)
        ->and(array_sum($data['Email']))->toBe(1)
        ->and($widget->getDescription())->toStartWith('Today 3');
});

it('counts daily and monthly active users', function () {
    [$a, $b, $c] = User::factory()->count(3)->create();
    DB::table('user_activity_days')->insert([
        ['user_id' => $a->id, 'date' => today()->toDateString()],
        ['user_id' => $b->id, 'date' => today()->toDateString()],
        ['user_id' => $a->id, 'date' => today()->subDays(3)->toDateString()],
        ['user_id' => $c->id, 'date' => today()->subDays(10)->toDateString()],
        ['user_id' => $c->id, 'date' => today()->subDays(40)->toDateString()],
    ]);

    $widget = new ActiveUsersChart;
    $daily = $widget->getData()['datasets'][0]['data'];

    expect($widget->getDescription())->toBe('DAU 2 · MAU 3 (last 30 days)')
        ->and($daily)->toHaveCount(30)
        ->and(last($daily))->toBe(2)
        ->and($daily[26])->toBe(1)
        ->and(array_sum($daily))->toBe(4);
});

it('shows each platform\'s last good sync and its failures', function () {
    InfluencerAccount::factory()->create(['platform' => Platform::YouTube, 'sync_status' => SyncStatus::Synced, 'last_synced_at' => now()->subHours(2)]);
    InfluencerAccount::factory()->create(['platform' => Platform::YouTube, 'sync_status' => SyncStatus::Failed, 'last_synced_at' => now()->subDays(3)]);
    $old = InfluencerAccount::factory()->create(['platform' => Platform::TikTok, 'sync_status' => SyncStatus::Failed]);
    $old->forceFill(['updated_at' => now()->subDays(2)])->saveQuietly();

    $status = (new PlatformStatus)->status();

    expect($status['youtube']['last_synced_at']->diffInHours(now()))->toBeLessThan(3)
        ->and($status['youtube']['failed_24h'])->toBe(1)
        ->and($status['youtube']['errors'])->toBe(1)
        ->and($status['tiktok'])->toMatchArray(['last_synced_at' => null, 'failed_24h' => 0, 'errors' => 1])
        ->and($status['x']['errors'])->toBe(0);

    Livewire::test(PlatformStatus::class)->assertSee('YouTube')->assertSee('Never synced');
});

it('lists recent failed jobs and raises the CheckHealth sync check', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => 'x-1', 'connection' => 'database', 'queue' => 'platforms', 'failed_at' => now()->subHour(),
        'payload' => json_encode(['displayName' => 'App\\Jobs\\SyncAccountPosts']),
        'exception' => "RuntimeException: Apify said no\n#0 stack",
    ]);
    config(['operations.sync_errors.min_failed' => 1, 'operations.sync_errors.rate' => 0.5]);
    $failing = InfluencerAccount::factory()->create(['sync_status' => SyncStatus::Failed]);
    InfluencerAccount::factory()->create(['sync_status' => SyncStatus::Failed]); // not tracked, so not counted
    User::factory()->create()->follow($failing->influencer, [$failing->id]);

    $widget = new ErrorsAndAlerts;

    expect($widget->failedJobs()[0])->toMatchArray(['job' => 'SyncAccountPosts', 'error' => 'RuntimeException: Apify said no'])
        ->and($widget->checks())->sequence(
            fn ($check) => $check->toMatchArray(['value' => '1', 'alerting' => true]),
            fn ($check) => $check->toMatchArray(['value' => '1 of 1', 'alerting' => true]),
        );

    Livewire::test(ErrorsAndAlerts::class)->assertSee('SyncAccountPosts')->assertSee('Open log viewer');
});

it('reports queue sizes and the average Apify run time', function () {
    ApifyRun::create(['actor' => 'a', 'status' => 'SUCCEEDED', 'duration_ms' => 4000]);
    ApifyRun::create(['actor' => 'a', 'status' => 'SUCCEEDED', 'duration_ms' => 6000]);
    ApifyRun::create(['actor' => 'a', 'status' => 'SUCCEEDED', 'duration_ms' => 60000])->forceFill(['created_at' => now()->subDays(2)])->save();

    $widget = new Performance;

    expect($widget->averageApifyRunSeconds())->toBe(5.0)
        ->and($widget->queueSizes())->toHaveKeys(['default', config('platforms.sync.queue')]);

    Livewire::test(Performance::class)->assertSee('Jobs waiting')->assertSee('5s');
});

it('shows Google Analytics as unavailable when it is not configured', function () {
    config(['services.google_analytics.property_id' => null]);

    expect(app(GoogleAnalytics::class)->summary())->toBeNull();

    Livewire::test(GoogleAnalyticsWidget::class)->assertSee('Unavailable')->assertSee('GA4_PROPERTY_ID');
});

it('shows Google Analytics as unavailable when the API fails', function () {
    fakeAnalytics(new RuntimeException('PERMISSION_DENIED'));

    Livewire::test(GoogleAnalyticsWidget::class)->assertOk()->assertSee('Unavailable')->assertSee('did not respond');
});

it('reads Google Analytics totals, top pages and sources, cached for an hour', function () {
    $fake = fakeAnalytics(new BatchRunReportsResponse(['reports' => [
        gaReport([['7d', 120, 150, 400], ['30d', 500, 640, 1900]]),
        gaReport([['/influencers/chef-mo', 321], ['/', 200]]),
        gaReport([['Organic Search', 300], ['Direct', 120]]),
    ]]));

    $summary = app(GoogleAnalytics::class)->summary();
    app(GoogleAnalytics::class)->summary();

    expect($fake->calls)->toBe(1)
        ->and($summary['totals']['7d'])->toBe(['visitors' => 120, 'sessions' => 150, 'views' => 400])
        ->and($summary['totals']['30d']['views'])->toBe(1900)
        ->and($summary['pages'][0])->toBe(['path' => '/influencers/chef-mo', 'views' => 321])
        ->and($summary['sources'][1])->toBe(['source' => 'Direct', 'sessions' => 120]);

    Livewire::test(GoogleAnalyticsWidget::class)->assertSee('1,900')->assertSee('/influencers/chef-mo')->assertSee('Organic Search');
});

it('shows the dashboard widgets only to admins with the Shield permission', function () {
    $widgets = [GoogleAnalyticsWidget::class, SignupsChart::class, ActiveUsersChart::class, PlatformStatus::class, ErrorsAndAlerts::class, Performance::class];

    foreach ($widgets as $widget) {
        expect($widget::canView())->toBeTrue();
    }

    $this->actingAs(Admin::factory()->create(['email' => 'no-role@example.com']), 'admin');

    foreach ($widgets as $widget) {
        expect($widget::canView())->toBeFalse();
    }

    $this->get('/admin')->assertOk();
});
