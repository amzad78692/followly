<?php

use App\Enums\Platform;
use App\Events\PostsSynced;
use App\Jobs\SendPush;
use App\Listeners\SendKeywordAlerts;
use App\Models\DeviceToken;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->keywordAlerts()->create(['keyword' => 'Giveaway']);

    $this->influencer = Influencer::factory()->create(['name' => 'Poster']);
    $this->account = InfluencerAccount::factory()->for($this->influencer)->create(['platform' => Platform::Instagram]);
});

function syncCaption(InfluencerAccount $account, string $caption, array $attributes = []): Post
{
    $post = Post::factory()->for($account, 'account')->create(['title' => $caption, 'body' => null, 'published_at' => now(), ...$attributes]);

    PostsSynced::dispatch($account, new Collection([$post]));

    return $post;
}

function alerts(User $user)
{
    return $user->notifications()->where('type', 'keyword-alert')->get();
}

it('matches whole words, case-insensitively, never part of a word', function (string $keyword, string $text, bool $match) {
    expect(SendKeywordAlerts::matches($keyword, $text))->toBe($match);
})->with([
    ['giveaway', 'Big GIVEAWAY today!', true],
    ['giveaway', '#giveaway time', true],
    ['cat', 'my cat.', true],
    ['cat', 'new category', false],
    ['cat', 'concat', false],
    ['cat', 'cat_food', false],
    ['new car', 'my New Car is here', true],
    ['new car', 'new cart', false],
    ['c++', 'learning C++ now', true],
    ['عرض', 'عرض خاص اليوم', true],
    ['عرض', 'عروض', false],
]);

it('sends one alert per matching post with the matched keywords', function () {
    $this->user->keywordAlerts()->create(['keyword' => 'iphone']);

    $post = syncCaption($this->account, 'iPhone giveaway starts now');
    syncCaption($this->account, 'Giveaways are coming'); // partial word only

    $alerts = alerts($this->user);

    expect($alerts)->toHaveCount(1)
        ->and($alerts->first()->data)->toMatchArray([
            'post_id' => $post->id,
            'keywords' => ['Giveaway', 'iphone'],
            'influencer_name' => 'Poster',
            'platform' => 'instagram',
        ]);
});

it('does not alert twice for the same post', function () {
    $post = syncCaption($this->account, 'giveaway');

    PostsSynced::dispatch($this->account, new Collection([$post]));

    expect(alerts($this->user))->toHaveCount(1);
});

it('respects muted platforms, turned off notifications and old posts', function () {
    $muted = User::factory()->create(['muted_platforms' => [Platform::Instagram]]);
    $muted->keywordAlerts()->create(['keyword' => 'giveaway']);

    $off = User::factory()->create(['notify_in_app' => false, 'email_digest' => null, 'notify_push' => false]);
    $off->keywordAlerts()->create(['keyword' => 'giveaway']);

    syncCaption($this->account, 'giveaway');
    syncCaption($this->account, 'old giveaway', ['published_at' => now()->subDays(30)]);

    expect(alerts($muted))->toHaveCount(0)
        ->and(alerts($off))->toHaveCount(0)
        ->and(alerts($this->user))->toHaveCount(1);
});

it('pushes the alert only when push is on', function (bool $push) {
    Queue::fake([SendPush::class]);
    $this->user->update(['notify_push' => $push]);
    DeviceToken::create([
        'user_id' => $this->user->id,
        'personal_access_token_id' => $this->user->createToken('Device', ['watch'])->accessToken->id,
        'platform' => 'ios',
        'token' => 'device-token',
        'last_seen_at' => now(),
    ]);

    syncCaption($this->account, 'giveaway');

    $push ? Queue::assertPushed(SendPush::class, fn ($job) => $job->data['type'] === 'keyword-alert') : Queue::assertNotPushed(SendPush::class);
})->with([true, false]);

it('lets users add and remove keywords, up to 20', function () {
    $this->actingAs($this->user);
    $page = Volt::test('settings');

    $page->set('keyword', '  black   friday ')->call('addKeyword')->assertHasNoErrors();
    expect($this->user->keywordAlerts()->pluck('keyword')->all())->toBe(['Giveaway', 'black friday']);

    $page->set('keyword', 'black friday')->call('addKeyword')->assertHasErrors('keyword');

    foreach (range(3, 20) as $i) {
        $this->user->keywordAlerts()->create(['keyword' => "word {$i}"]);
    }
    $page->set('keyword', 'one too many')->call('addKeyword')->assertHasErrors('keyword');
    expect($this->user->keywordAlerts()->count())->toBe(20);

    $page->call('removeKeyword', $this->user->keywordAlerts()->first()->id);
    expect($this->user->keywordAlerts()->count())->toBe(19);
});

it('filters the notifications center by type', function () {
    $this->actingAs($this->user);
    syncCaption($this->account, 'giveaway');
    $this->user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'welcome', 'data' => ['subject' => 'Welcome', 'message' => 'Hello there']]);

    Volt::test('notifications.index')
        ->set('filter', 'alerts')->assertSee('mentioned it on')->assertDontSee('Hello there')
        ->set('filter', 'system')->assertSee('Hello there')->assertDontSee('mentioned it on')
        ->set('filter', 'posts')->assertDontSee('Hello there')->assertDontSee('mentioned it on');
});

it('shows the timeline grouped by day with SmartLink groups once', function () {
    $x = InfluencerAccount::factory()->for($this->influencer)->create(['platform' => Platform::X]);
    $first = Post::factory()->for($this->account, 'account')->create(['title' => 'Same story', 'published_at' => now()->subHour()]);
    Post::factory()->for($x, 'account')->create(['title' => 'Same story', 'group_id' => $first->id, 'published_at' => now()->subHours(2)]);
    $first->update(['group_id' => $first->id]);
    Post::factory()->for($x, 'account')->create(['title' => 'Older post', 'published_at' => now()->subDays(3)]);

    $component = Volt::test('influencer-timeline', ['influencer' => $this->influencer])
        ->assertSee('Today')->assertSee('Also posted on')->assertSee('Older post');

    expect(substr_count($component->html(), 'Same story'))->toBe(1);
});
