<?php

use App\Enums\Platform;
use App\Enums\SyncStatus;
use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Filament\Resources\Influencers\Pages\CreateInfluencer;
use App\Filament\Resources\Influencers\Pages\ListInfluencers;
use App\Filament\Resources\Influencers\Pages\ViewInfluencer;
use App\Filament\Resources\Influencers\RelationManagers\AccountsRelationManager;
use App\Filament\Resources\Posts\Pages\ManagePosts;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\SyncHealth;
use App\Jobs\SyncAccountPosts;
use App\Jobs\SyncYouTubeProfiles;
use App\Models\Admin;
use App\Models\ApifyRun;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Models\Post;
use App\Models\Taxonomy;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->admin = superAdmin();
    $this->actingAs($this->admin, 'admin');
});

it('opens the content pages', function (string $uri) {
    $this->get($uri)->assertOk();
})->with([
    'influencers' => '/admin/influencers',
    'create influencer' => '/admin/influencers/create',
    'posts' => '/admin/posts',
    'categories' => '/admin/categories',
]);

it('opens an influencer and their edit page', function () {
    $influencer = Influencer::factory()->create(['name' => 'Chef Mo']);
    InfluencerAccount::factory()->for($influencer)->create(['platform' => Platform::YouTube, 'handle' => '@chefmo']);

    $this->get("/admin/influencers/{$influencer->slug}")->assertOk()->assertSee('Chef Mo');
    $this->get("/admin/influencers/{$influencer->slug}/edit")->assertOk();
});

it('keeps admins without permissions out of the content pages', function () {
    $this->actingAs(Admin::factory()->create(['email' => 'no-role@example.com']), 'admin');

    $this->get('/admin/influencers')->assertForbidden();
    $this->get('/admin/posts')->assertForbidden();
    $this->get('/admin/categories')->assertForbidden();
});

it('lists influencers with who added them, and filters by platform', function () {
    $user = User::factory()->create(['name' => 'Sara']);
    $byUser = Influencer::factory()->create();
    $byUser->createdBy()->associate($user)->save();
    InfluencerAccount::factory()->for($byUser)->create(['platform' => Platform::TikTok]);

    $byAdmin = Influencer::factory()->create();
    $byAdmin->createdBy()->associate($this->admin)->save();
    InfluencerAccount::factory()->for($byAdmin)->create(['platform' => Platform::YouTube]);

    Livewire::test(ListInfluencers::class)
        ->assertCanSeeTableRecords([$byUser, $byAdmin])
        ->assertSee('Sara')
        ->filterTable('platform', 'tiktok')
        ->assertCanSeeTableRecords([$byUser])
        ->assertCanNotSeeTableRecords([$byAdmin])
        ->resetTableFilters()
        ->filterTable('added_by', 'admin')
        ->assertCanSeeTableRecords([$byAdmin])
        ->assertCanNotSeeTableRecords([$byUser]);
});

it('toggles verified and featured from the table', function () {
    $influencer = Influencer::factory()->create(['is_verified' => false, 'is_featured' => false]);

    Livewire::test(ListInfluencers::class)
        ->call('updateTableColumnState', 'is_verified', (string) $influencer->getKey(), true)
        ->call('updateTableColumnState', 'is_featured', (string) $influencer->getKey(), true);

    expect($influencer->refresh())
        ->is_verified->toBeTrue()
        ->is_featured->toBeTrue();
});

it('creates an influencer by hand, credited to the admin', function () {
    $category = Taxonomy::create(['type' => 'category', 'name' => 'Food']);

    Livewire::test(CreateInfluencer::class)
        ->fillForm(['name' => 'Chef Mo', 'category_id' => $category->id, 'is_featured' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $influencer = Influencer::sole();

    expect($influencer->slug)->toBe('chef-mo')
        ->and($influencer->category->is($category))->toBeTrue()
        ->and($influencer->is_featured)->toBeTrue()
        ->and($influencer->createdBy->is($this->admin))->toBeTrue();
});

it('adds an influencer from a link through the shared lookup', function () {
    Queue::fake();
    fakePlatforms(
        profile(Platform::YouTube, 'chef', ['name' => 'Chef Mo', 'links' => ['https://www.instagram.com/chef']]),
        profile(Platform::Instagram, 'chef'),
        profile(Platform::TikTok, 'chef'),
    );

    Livewire::test(ListInfluencers::class)
        ->mountAction('addFromLink')
        ->fillForm(['query' => 'https://www.youtube.com/@chef'])
        ->goToNextWizardStep()
        ->assertHasNoFormErrors()
        // The same-username TikTok guess starts unticked.
        ->assertSchemaStateSet(['selected' => ['youtube', 'instagram']])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $influencer = Influencer::sole();

    expect($influencer->name)->toBe('Chef Mo')
        ->and($influencer->accounts->pluck('platform')->all())->toBe([Platform::YouTube, Platform::Instagram])
        ->and($influencer->createdBy->is($this->admin))->toBeTrue();
    Queue::assertPushed(SyncAccountPosts::class);
});

it('stops on the first step when the creator is already stored', function () {
    fakePlatforms(profile(Platform::YouTube, 'chef'));
    $influencer = Influencer::factory()->create(['name' => 'Chef Mo']);
    InfluencerAccount::factory()->for($influencer)->create(['platform' => Platform::YouTube, 'handle' => '@chef', 'external_id' => 'youtube-chef']);

    Livewire::test(ListInfluencers::class)
        ->mountAction('addFromLink')
        ->fillForm(['query' => 'https://www.youtube.com/@chef'])
        ->goToNextWizardStep()
        ->assertNotified('Chef Mo is already on Followly');

    expect(Influencer::count())->toBe(1);
});

it('queues a sync now for every account of an influencer', function () {
    Queue::fake();
    fakePlatforms();
    $influencer = Influencer::factory()->create();
    $youtube = InfluencerAccount::factory()->for($influencer)->create(['platform' => Platform::YouTube]);
    InfluencerAccount::factory()->for($influencer)->create(['platform' => Platform::X]);

    Livewire::test(ViewInfluencer::class, ['record' => $influencer->getRouteKey()])
        ->callAction('syncNow')
        ->assertNotified('Sync queued for 2 accounts');

    // The fake drivers are not Apify drivers, so X syncs one account per job here.
    Queue::assertPushed(SyncAccountPosts::class, 2);
    Queue::assertPushed(SyncYouTubeProfiles::class, fn ($job) => $job->accountIds === [$youtube->id]);
    expect($youtube->refresh()->sync_status)->toBe(SyncStatus::Syncing);
});

it('lists accounts and adds one from a link', function () {
    Queue::fake();
    fakePlatforms(profile(Platform::Instagram, 'chef', ['followers' => 5000]));
    $influencer = Influencer::factory()->create();
    $youtube = InfluencerAccount::factory()->for($influencer)->create(['platform' => Platform::YouTube, 'sync_status' => SyncStatus::Failed, 'sync_error' => 'Quota exceeded']);

    Livewire::test(AccountsRelationManager::class, ['ownerRecord' => $influencer, 'pageClass' => ViewInfluencer::class])
        ->assertCanSeeTableRecords([$youtube])
        ->assertSee('Failed')
        ->callAction(TestAction::make('addAccount')->table(), ['query' => 'https://www.instagram.com/chef'])
        ->assertNotified('Instagram account added');

    expect($influencer->accounts()->where('platform', Platform::Instagram)->sole()->followers)->toBe(5000);
});

it('refuses an account that belongs to another influencer', function () {
    fakePlatforms(profile(Platform::Instagram, 'chef'));
    $owner = Influencer::factory()->create(['name' => 'Owner']);
    InfluencerAccount::factory()->for($owner)->create(['platform' => Platform::Instagram, 'external_id' => 'instagram-chef']);
    $influencer = Influencer::factory()->create();

    Livewire::test(AccountsRelationManager::class, ['ownerRecord' => $influencer, 'pageClass' => ViewInfluencer::class])
        ->callAction(TestAction::make('addAccount')->table(), ['query' => 'https://www.instagram.com/chef'])
        ->assertNotified('That account belongs to Owner');

    expect($influencer->accounts()->count())->toBe(0);
});

it('hides a post from feeds and profiles, and shows it again', function () {
    $user = User::factory()->create();
    $influencer = Influencer::factory()->create();
    $account = InfluencerAccount::factory()->for($influencer)->create();
    $user->follow($influencer);
    $post = Post::factory()->for($account, 'account')->create(['title' => 'Spoiler video']);

    Livewire::test(ManagePosts::class)
        ->assertCanSeeTableRecords([$post])
        ->callAction(TestAction::make('hide')->table($post));

    expect($post->refresh()->isHidden())->toBeTrue()
        ->and(Post::feedFor($user)->count())->toBe(0);

    $this->actingAs($user);
    Volt::test('influencers.show', ['influencer' => $influencer])->assertDontSee('Spoiler video');

    $this->actingAs($this->admin, 'admin');
    Livewire::test(ManagePosts::class)
        ->filterTable('hidden', true)
        ->assertCanSeeTableRecords([$post])
        ->callAction(TestAction::make('unhide')->table($post));

    expect($post->refresh()->isHidden())->toBeFalse()
        ->and(Post::feedFor($user)->count())->toBe(1);
});

it('removes posts one by one and in bulk', function () {
    [$one, $two, $three] = Post::factory()->count(3)->create()->all();

    Livewire::test(ManagePosts::class)->callAction(TestAction::make('delete')->table($one));

    expect(Post::find($one->id))->toBeNull();

    Livewire::test(ManagePosts::class)
        ->selectTableRecords([$two->id, $three->id])
        ->callAction(TestAction::make('hide')->table()->bulk())
        ->selectTableRecords([$two->id, $three->id])
        ->callAction(TestAction::make('delete')->table()->bulk());

    expect(Post::count())->toBe(0);
});

it('manages categories', function () {
    $influencer = Influencer::factory()->create();

    Livewire::test(ManageCategories::class)
        ->callAction('create', ['name' => 'Food & Drink', 'meta' => ['emoji' => '🍔']])
        ->assertHasNoFormErrors();

    $category = Taxonomy::sole();
    expect($category->type)->toBe('category')
        ->and($category->slug)->toBe('food-drink')
        ->and($category->meta['emoji'])->toBe('🍔');

    $influencer->update(['category_id' => $category->id]);
    Taxonomy::create(['type' => 'tag', 'name' => 'Not a category']);

    Livewire::test(ManageCategories::class)
        ->assertCanSeeTableRecords([$category])
        ->assertCountTableRecords(1)
        ->callAction(TestAction::make('delete')->table($category));

    expect(Taxonomy::where('type', 'category')->count())->toBe(0)
        ->and($influencer->refresh()->category_id)->toBeNull();
});

it('shows the dashboard widgets', function () {
    User::factory()->count(2)->create();
    InfluencerAccount::factory()->create(['sync_status' => SyncStatus::Failed]);
    Post::factory()->create();
    ApifyRun::create(['actor' => 'apify/instagram-profile-scraper', 'status' => 'SUCCEEDED', 'usage_usd' => 4.5]);
    config(['services.apify.monthly_budget_usd' => 10]);

    Livewire::test(StatsOverview::class)
        ->assertSee('Users')
        ->assertSee('Posts synced today');

    Livewire::test(SyncHealth::class)
        ->assertSee('Failed syncs')
        ->assertSee('YouTube quota today')
        ->assertSee('$4.50')
        ->assertSee('$10.00 budget');
});
