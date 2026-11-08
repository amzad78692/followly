<?php

use App\Enums\Platform;
use App\Models\DeviceToken;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Models\Post;
use App\Models\Taxonomy;
use App\Models\User;
use App\Notifications\NewPostNotification;
use App\Support\RemoteImage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('My Watch', ['watch'])->plainTextToken;
    $this->withToken($this->token);
    $this->influencer = Influencer::factory()->create(['name' => 'Poster', 'avatar_url' => 'https://cdn.example.com/a.jpg']);
    $this->account = InfluencerAccount::factory()->for($this->influencer)->create(['platform' => Platform::YouTube]);
});

function storeUpdate(User $user, InfluencerAccount $account, int $posts = 1): void
{
    $user->notifyNow(new NewPostNotification($account, new Collection(
        Post::factory()->count($posts)->for($account, 'account')->create(['title' => 'A video', 'media' => ['images' => ['https://cdn.example.com/i.jpg']]])->all()
    )));
}

it('requires a token', function () {
    $this->withToken('nope')->getJson('/api/watch/home')->assertUnauthorized();
});

it('returns JSON errors without an Accept header', function () {
    $this->withToken('nope')->get('/api/watch/home')->assertUnauthorized()->assertJsonStructure(['message']);
});

it('shows the home counts', function () {
    $this->user->follow($this->influencer);
    storeUpdate($this->user, $this->account);

    $this->getJson('/api/watch/home')->assertOk()->assertExactJson(['unread_count' => 1, 'creators_count' => 1]);
});

it('pages through updates without repeating any, even within one second', function () {
    foreach (range(1, 5) as $i) {
        storeUpdate($this->user, $this->account);
    }

    $first = $this->getJson('/api/watch/updates?limit=3')->assertOk()->json();
    $second = $this->getJson('/api/watch/updates?limit=3&cursor='.$first['next_cursor'])->assertOk()->json();

    $ids = collect([...$first['items'], ...$second['items']])->pluck('id');

    expect($first['items'])->toHaveCount(3)
        ->and($second['items'])->toHaveCount(2)
        ->and($second['next_cursor'])->toBeNull()
        ->and($ids->unique())->toHaveCount(5);
});

it('caps the page size', function () {
    foreach (range(1, 31) as $i) {
        storeUpdate($this->user, $this->account);
    }

    $this->getJson('/api/watch/updates?limit=500')->assertOk()->assertJsonCount(30, 'items');
});

it('returns compact updates with resized images', function () {
    config(['images.proxy' => true]);
    storeUpdate($this->user, $this->account, 2);

    $id = $this->user->notifications()->first()->id;

    $update = $this->getJson("/api/watch/updates/{$id}")->assertOk()->json();

    expect($update)->toMatchArray(['id' => $id, 'read' => false, 'platform' => 'youtube', 'count' => 2, 'title' => 'A video'])
        ->and($update['creator']['name'])->toBe('Poster')
        ->and($update['creator']['avatar'])->toBe(RemoteImage::url('https://cdn.example.com/a.jpg', 96, 96))
        ->and($update['image'])->toBe(RemoteImage::url('https://cdn.example.com/i.jpg', 320, 180))
        ->and(array_keys($update))->not->toContain('data');
});

it('leaves local images as they are', function () {
    config(['images.proxy' => true, 'app.url' => 'https://followly.test']);
    $this->influencer->update(['avatar_url' => 'https://followly.test/storage/a.jpg']);
    storeUpdate($this->user, $this->account->load('influencer'));

    $this->getJson('/api/watch/updates')->assertJsonPath('items.0.creator.avatar', 'https://followly.test/storage/a.jpg');
});

it('marks updates read one at a time and all at once', function () {
    storeUpdate($this->user, $this->account);
    storeUpdate($this->user, $this->account);

    $id = $this->user->notifications()->first()->id;

    $this->postJson("/api/watch/updates/{$id}/read")->assertOk()->assertExactJson(['unread_count' => 1]);
    $this->postJson('/api/watch/updates/read-all')->assertOk()->assertExactJson(['unread_count' => 0]);

    expect($this->user->unreadNotifications()->count())->toBe(0);
});

it("can't read or change another user's updates", function () {
    $other = User::factory()->create();
    storeUpdate($other, $this->account);
    $id = $other->notifications()->first()->id;

    $this->getJson("/api/watch/updates/{$id}")->assertNotFound();
    $this->postJson("/api/watch/updates/{$id}/read")->assertNotFound();
    $this->postJson('/api/watch/updates/read-all')->assertOk();
    $this->getJson('/api/watch/updates')->assertJsonCount(0, 'items');

    expect($other->unreadNotifications()->count())->toBe(1);
});

it('summarises today in the requested timezone', function () {
    $this->travelTo('2026-11-08 22:00:00');
    storeUpdate($this->user, $this->account, 2);
    $this->travelTo('2026-11-09 01:00:00');

    $second = InfluencerAccount::factory()->for(Influencer::factory()->create())->create();
    storeUpdate($this->user, $second);

    // 01:00 UTC is still 8 November in New York, so both count there and only one in UTC.
    $this->getJson('/api/watch/summary?timezone=America/New_York')->assertOk()
        ->assertJsonPath('posts', 3)->assertJsonPath('creators', 2)->assertJsonCount(2, 'avatars');

    $this->getJson('/api/watch/summary?timezone=UTC')->assertOk()
        ->assertJsonPath('posts', 1)->assertJsonPath('creators', 1);

    $this->getJson('/api/watch/summary?timezone=Not/AZone')->assertUnprocessable();
});

it('lists creators with pinned ones first', function () {
    $older = Influencer::factory()->create(['name' => 'Pinned']);
    $this->user->follow($older);
    $this->user->follow($this->influencer);

    $this->patchJson("/api/watch/creators/{$older->slug}", ['pinned' => true, 'notify' => false])
        ->assertOk()->assertExactJson(['notify' => false, 'pinned' => true]);

    $this->getJson('/api/watch/creators')->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('next_page', null)
        ->assertJsonPath('items.0.name', 'Pinned')
        ->assertJsonPath('items.0.notify', false)
        ->assertJsonPath('items.1.pinned', false)
        ->assertJsonPath('items.1.notify', true);
});

it('leaves out soft deleted creators', function () {
    $this->user->follow($this->influencer);
    $this->influencer->delete();

    $this->getJson('/api/watch/creators')->assertOk()->assertJsonPath('total', 0);
    $this->getJson('/api/watch/home')->assertJsonPath('creators_count', 0);
});

it('can unpin a creator without changing their alert setting', function () {
    $this->user->follow($this->influencer);

    $this->patchJson("/api/watch/creators/{$this->influencer->slug}", ['pinned' => true]);
    $this->patchJson("/api/watch/creators/{$this->influencer->slug}", ['pinned' => false])
        ->assertExactJson(['notify' => true, 'pinned' => false]);
});

it("can't change a creator the user doesn't follow", function () {
    $this->patchJson("/api/watch/creators/{$this->influencer->slug}", ['pinned' => true])->assertNotFound();
});

it('unfollows a creator and refreshes the count', function () {
    $this->user->follow($this->influencer);

    $this->deleteJson("/api/watch/creators/{$this->influencer->slug}")->assertNoContent();

    expect($this->user->isFollowing($this->influencer))->toBeFalse()
        ->and($this->influencer->fresh()->followers_count)->toBe(0);
});

it('lists categories and trending creators', function () {
    $category = Taxonomy::create(['name' => 'Travel', 'slug' => 'travel', 'type' => 'category']);
    $inCategory = Influencer::factory()->create(['name' => 'Traveller', 'category_id' => $category->id]);
    $this->user->follow($inCategory);

    $this->getJson('/api/watch/categories')->assertOk()->assertExactJson([['slug' => 'travel', 'name' => 'Travel']]);

    $this->getJson('/api/watch/trending?category=travel')->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Traveller')
        ->assertJsonPath('0.following', true);

    $this->getJson('/api/watch/trending')->assertOk()->assertJsonCount(2);
    $this->getJson('/api/watch/trending?category=missing')->assertNotFound();
});

it('reads and updates settings', function () {
    $this->getJson('/api/watch/settings')->assertOk()->assertExactJson(['instant_updates' => true, 'daily_summary' => false]);

    $this->patchJson('/api/watch/settings', ['instant_updates' => false])
        ->assertOk()->assertExactJson(['instant_updates' => false, 'daily_summary' => false]);

    $this->patchJson('/api/watch/settings', ['daily_summary' => true])
        ->assertExactJson(['instant_updates' => false, 'daily_summary' => true]);
});

it('registers a device, and moves a token when another user signs in on it', function () {
    $payload = ['platform' => 'wear_os', 'token' => 'device-1', 'timezone' => 'Asia/Kuwait'];

    $this->postJson('/api/watch/devices', $payload)->assertNoContent();

    expect(DeviceToken::first())->user_id->toBe($this->user->id)->timezone->toBe('Asia/Kuwait');

    $other = User::factory()->create();
    app('auth')->forgetGuards();
    $this->withToken($other->createToken('Watch', ['watch'])->plainTextToken)->postJson('/api/watch/devices', $payload)->assertNoContent();

    expect(DeviceToken::count())->toBe(1)
        ->and(DeviceToken::first()->user_id)->toBe($other->id);
});

it('rejects bad device registrations', function (array $payload) {
    $this->postJson('/api/watch/devices', $payload)->assertUnprocessable();
})->with([
    'bad platform' => [['platform' => 'pager', 'token' => 'x']],
    'no token' => [['platform' => 'wear_os']],
    'bad timezone' => [['platform' => 'wear_os', 'token' => 'x', 'timezone' => 'Nowhere']],
]);

it("only unregisters the user's own device", function () {
    $other = User::factory()->create();
    $otherToken = $other->createToken('Watch', ['watch']);
    DeviceToken::create(['user_id' => $other->id, 'personal_access_token_id' => $otherToken->accessToken->id, 'platform' => 'wear_os', 'token' => 'theirs']);

    $this->deleteJson('/api/watch/devices', ['token' => 'theirs'])->assertNoContent();

    expect(DeviceToken::count())->toBe(1);
});

it('removes device tokens when the device signs out', function () {
    $this->postJson('/api/watch/devices', ['platform' => 'wear_os', 'token' => 'device-1'])->assertNoContent();

    $this->postJson('/api/mobile/logout')->assertNoContent();

    expect(DeviceToken::count())->toBe(0);
});

it('removes device tokens when the account is deleted', function () {
    $this->postJson('/api/watch/devices', ['platform' => 'wear_os', 'token' => 'device-1'])->assertNoContent();

    $this->user->deleteAccount();

    expect(DeviceToken::count())->toBe(0);
});

it('rate limits the API per user', function () {
    foreach (range(1, 120) as $i) {
        $this->getJson('/api/watch/settings')->assertOk();
    }

    $this->getJson('/api/watch/settings')->assertTooManyRequests();
});

it('documents the new routes', function () {
    $this->artisan('scramble:analyze')->assertSuccessful();
});
