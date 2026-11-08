<?php

namespace App\Http\Controllers\Api;

use App\Enums\DevicePlatform;
use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\Follow;
use App\Models\Influencer;
use App\Models\Taxonomy;
use App\Models\User;
use App\Watch\DailySummary;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\Rule;

/**
 * Short responses for small screens. Each user only ever reaches their own data, through
 * relations on the signed-in user.
 */
class WatchController extends Controller
{
    protected const AVATAR_SIZE = 96;

    protected const MAX_PER_PAGE = 30;

    /**
     * Register this device for push, or move an existing token to the signed-in user.
     */
    public function registerDevice(Request $request): Response
    {
        $validated = $request->validate([
            'platform' => ['required', Rule::enum(DevicePlatform::class)],
            'token' => ['required', 'string', 'max:512'],
            'timezone' => ['nullable', 'timezone:all'],
        ]);

        DeviceToken::updateOrCreate(['token' => $validated['token']], [
            'user_id' => $request->user()->id,
            'personal_access_token_id' => $request->user()->currentAccessToken()->id,
            'platform' => $validated['platform'],
            'timezone' => $validated['timezone'] ?? null,
            'last_seen_at' => now(),
        ]);

        return response()->noContent();
    }

    public function unregisterDevice(Request $request): Response
    {
        $validated = $request->validate(['token' => ['required', 'string', 'max:512']]);

        $request->user()->deviceTokens()->where('token', $validated['token'])->delete();

        return response()->noContent();
    }

    public function home(Request $request): array
    {
        return [
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'creators_count' => $this->follows($request->user())->count(),
        ];
    }

    public function updates(Request $request): array
    {
        $page = $request->user()->notifications()
            ->where('type', 'new-post')
            ->reorder()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($this->perPage($request));

        return [
            'items' => $page->getCollection()->map($this->formatUpdate(...))->values(),
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }

    public function update(Request $request, string $id): array
    {
        return $this->formatUpdate($request->user()->notifications()->findOrFail($id));
    }

    public function read(Request $request, string $id): array
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return ['unread_count' => $request->user()->unreadNotifications()->count()];
    }

    public function readAll(Request $request): array
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return ['unread_count' => 0];
    }

    public function summary(Request $request): array
    {
        $validated = $request->validate(['timezone' => ['nullable', 'timezone:all']]);

        return DailySummary::for($request->user(), $validated['timezone'] ?? null);
    }

    /**
     * The creators the user follows, pinned ones first.
     */
    public function creators(Request $request): array
    {
        $page = $this->follows($request->user())
            ->with('influencer')
            ->orderByRaw('pinned_at is null')
            ->orderByDesc('pinned_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return [
            'items' => $page->getCollection()->map(fn (Follow $follow) => [
                ...$this->formatCreator($follow->influencer),
                'notify' => $follow->notify,
                'pinned' => $follow->pinned_at !== null,
            ])->values(),
            'total' => $page->total(),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ];
    }

    public function updateCreator(Request $request, Influencer $influencer): array
    {
        $validated = $request->validate([
            'notify' => ['sometimes', 'boolean'],
            'pinned' => ['sometimes', 'boolean'],
        ]);

        $follow = $request->user()->follows()->where('influencer_id', $influencer->id)->firstOrFail();

        if (array_key_exists('notify', $validated)) {
            $follow->notify = $validated['notify'];
        }

        if (array_key_exists('pinned', $validated)) {
            $follow->pinned_at = $validated['pinned'] ? now() : null;
        }

        $follow->save();

        return ['notify' => $follow->notify, 'pinned' => $follow->pinned_at !== null];
    }

    public function unfollow(Request $request, Influencer $influencer): Response
    {
        $request->user()->unfollow($influencer);

        return response()->noContent();
    }

    public function categories(): array
    {
        return Taxonomy::where('type', 'category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map->only('slug', 'name')
            ->all();
    }

    public function trending(Request $request): array
    {
        $validated = $request->validate(['category' => ['nullable', 'string']]);

        $category = isset($validated['category'])
            ? Taxonomy::where('type', 'category')->where('slug', $validated['category'])->firstOrFail()
            : null;

        $influencers = Influencer::query()
            ->when($category, fn ($q) => $q->where('category_id', $category->id))
            ->trending()
            ->limit(self::MAX_PER_PAGE)
            ->get();

        $followed = $request->user()->follows()->whereIn('influencer_id', $influencers->modelKeys())->pluck('influencer_id');

        return $influencers->map(fn (Influencer $influencer) => [
            ...$this->formatCreator($influencer),
            'following' => $followed->contains($influencer->id),
        ])->all();
    }

    public function settings(Request $request): array
    {
        return $this->formatSettings($request->user());
    }

    public function updateSettings(Request $request): array
    {
        $validated = $request->validate([
            'instant_updates' => ['sometimes', 'boolean'],
            'daily_summary' => ['sometimes', 'boolean'],
        ]);

        $request->user()->update(array_filter([
            'notify_push' => $validated['instant_updates'] ?? null,
            'push_daily_summary' => $validated['daily_summary'] ?? null,
        ], fn ($value) => $value !== null));

        return $this->formatSettings($request->user());
    }

    /**
     * Follows of creators who still exist: creators can be soft deleted.
     */
    protected function follows(User $user)
    {
        return $user->follows()->whereHas('influencer');
    }

    protected function perPage(Request $request): int
    {
        return max(1, min($request->integer('limit', 20), self::MAX_PER_PAGE));
    }

    protected function formatUpdate(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'read' => $notification->read(),
            'creator' => [
                'slug' => $data['influencer_slug'],
                'name' => $data['influencer_name'],
                'avatar' => image_url($data['avatar'] ?? null, self::AVATAR_SIZE, self::AVATAR_SIZE),
            ],
            'platform' => $data['platform'] ?? null,
            'count' => $data['count'] ?? 1,
            'title' => ($data['title'] ?? '') ?: null,
            'image' => image_url($data['image'] ?? null, 320, 180),
            'permalink' => $data['permalink'] ?? null,
            'published_at' => $data['published_at'] ?? null,
            'created_at' => $notification->created_at->toIso8601String(),
        ];
    }

    protected function formatCreator(Influencer $influencer): array
    {
        return [
            'slug' => $influencer->slug,
            'name' => $influencer->name,
            'avatar' => image_url($influencer->avatar, self::AVATAR_SIZE, self::AVATAR_SIZE),
            'verified' => $influencer->is_verified,
        ];
    }

    protected function formatSettings(User $user): array
    {
        return [
            'instant_updates' => $user->notify_push,
            'daily_summary' => $user->push_daily_summary,
        ];
    }
}
