<?php

use App\Analytics\CreatorReport;
use App\Enums\Platform;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-11 12:00:00'); // a Wednesday
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->creator = Influencer::factory()->create(['name' => 'Gold Creator']);
    $this->youtube = InfluencerAccount::factory()->for($this->creator)->create(['platform' => Platform::YouTube]);
    $this->user->follow($this->creator);
});

function analyticsPost(InfluencerAccount $account, string $ago, array $metrics = [], array $extra = []): Post
{
    return Post::factory()->for($account, 'account')->create(['published_at' => now()->modify("-{$ago}"), 'metrics' => $metrics] + $extra);
}

it('works out the percent change, with no figure when the previous period is zero', function () {
    expect(CreatorReport::change(118, 100))->toBe(18.0)
        ->and(CreatorReport::change(50, 100))->toBe(-50.0)
        ->and(CreatorReport::change(10, 0))->toBeNull()
        ->and(CreatorReport::change(0, 0))->toBeNull();
});

it('compares the KPIs with the previous period of the same length', function () {
    analyticsPost($this->youtube, '2 days', ['views' => 300, 'likes' => 30, 'comments' => 3]);
    analyticsPost($this->youtube, '5 days', ['views' => 100, 'likes' => 10, 'comments' => 1]);
    analyticsPost($this->youtube, '10 days', ['views' => 200, 'likes' => 40, 'comments' => 0]); // previous 7 days
    analyticsPost($this->youtube, '20 days', ['views' => 999]); // outside both periods

    $kpis = (new CreatorReport([$this->creator->id], 7))->kpis();

    expect($kpis['posts'])->toMatchArray(['value' => 2, 'change' => 100.0])
        ->and($kpis['views'])->toMatchArray(['value' => 400, 'change' => 100.0])
        ->and($kpis['likes'])->toMatchArray(['value' => 40, 'change' => 0.0])
        ->and($kpis['comments'])->toMatchArray(['value' => 4, 'change' => null])
        ->and($kpis)->not->toHaveKey('shares');
});

it('shows Shares only when the creator has share data', function () {
    $tiktok = InfluencerAccount::factory()->for($this->creator)->create(['platform' => Platform::TikTok]);
    analyticsPost($this->youtube, '1 day', ['views' => 10]);

    Volt::test('influencer-analytics', ['influencer' => $this->creator])->assertSee('Total Views')->assertDontSee('Shares');

    analyticsPost($tiktok, '1 day', ['views' => 10, 'shares' => 7]);

    expect((new CreatorReport([$this->creator->id], 30))->kpis()['shares']['value'])->toBe(7);
    Volt::test('influencer-analytics', ['influencer' => $this->creator])->assertSee('Shares');
});

it('handles a creator with no posts', function () {
    $report = new CreatorReport([$this->creator->id], 30);

    expect($report->kpis()['posts'])->toMatchArray(['value' => 0, 'change' => null])
        ->and($report->mostActiveDay())->toBeNull()
        ->and($report->primaryFormat())->toBeNull()
        ->and($report->best())->toBeEmpty()
        ->and(array_sum($report->daily('views')))->toBe(0);

    $this->get(route('analytics'))->assertOk()->assertSee('Gold Creator')->assertSee('No posts in the last 30 days');
});

it('buckets the heatmap by week and weekday', function () {
    analyticsPost($this->youtube, '0 days'); // Wed Nov 11
    analyticsPost($this->youtube, '1 day');  // Tue Nov 10
    analyticsPost($this->youtube, '1 day');
    analyticsPost($this->youtube, '3 days'); // Sun Nov 8, the week before

    $weeks = (new CreatorReport([$this->creator->id], 7))->heatmap();

    expect(array_keys($weeks))->toBe(['2026-11-02', '2026-11-09'])
        ->and($weeks['2026-11-09'])->toBe([1 => 0, 2 => 2, 3 => 1, 4 => 0, 5 => 0, 6 => 0, 7 => 0])
        ->and($weeks['2026-11-02'][7])->toBe(1)
        ->and((new CreatorReport([$this->creator->id], 7))->mostActiveDay())->toBe('Tuesday');
});

it('sorts posts into content formats', function () {
    analyticsPost($this->youtube, '1 day', [], ['duration_seconds' => 600]);
    analyticsPost($this->youtube, '1 day', [], ['duration_seconds' => 45]);
    analyticsPost($this->youtube, '1 day', [], ['type' => 'image']);
    analyticsPost($this->youtube, '1 day', [], ['type' => 'text']);

    expect((new CreatorReport([$this->creator->id], 30))->breakdown())->toBe(['Videos' => 1, 'Shorts/Reels' => 1, 'Images' => 1, 'Other' => 1]);
});

it('only reports on creators the user follows', function () {
    $stranger = Influencer::factory()->create(['name' => 'Stranger Danger']);

    $this->get(route('analytics', ['influencer' => $stranger->id]))
        ->assertOk()
        ->assertSee('Gold Creator')
        ->assertDontSee('Stranger Danger');

    $this->get(route('analytics', ['tab' => 'top']))->assertOk()->assertDontSee('Stranger Danger');
});

it('switches the date range and falls back to 30 days for others', function () {
    analyticsPost($this->youtube, '20 days', ['views' => 5000]);

    Volt::test('analytics')
        ->set('days', 7)->assertSee('No posts in the last 7 days')
        ->set('days', 30)->assertDontSee('No posts in the last 30 days')
        ->set('days', 12)->assertSee('Last 30 Days');

    expect(Volt::test('analytics')->set('days', 90)->instance()->report->posts)->toHaveCount(1);
});

it('ranks followed creators in Top Performers and aggregates Activity Trends', function () {
    $other = Influencer::factory()->create(['name' => 'Second Star']);
    $account = InfluencerAccount::factory()->for($other)->create(['platform' => Platform::X]);
    $this->user->follow($other);
    analyticsPost($this->youtube, '1 day', ['views' => 100]);
    analyticsPost($account, '1 day', ['views' => 900]);

    $this->get(route('analytics', ['tab' => 'top']))->assertOk()->assertSeeInOrder(['Second Star', 'Gold Creator']);
    $this->get(route('analytics', ['tab' => 'trends']))->assertOk()->assertSee('2 posts')->assertSee('Posting Activity');
});

it('links the influencer page analytics tab and the sidebar to the report', function () {
    $this->get(route('influencers.show', $this->creator))->assertSee(route('analytics', ['influencer' => $this->creator->id]), false);
    $this->get(route('analytics'))->assertSee('Creator Analytics')->assertSee('Insights and activity trends');
});
