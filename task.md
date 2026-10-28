# FLWLEE (Followly): Implementation Plan

**Goal:** a user follows a creator once and sees that creator's posts from YouTube, Instagram, TikTok and X in one feed.

**Stack:** Laravel 13, Livewire 4 with Volt (class-based), Flux UI (free), Tailwind 4, Filament 5 (admin), MySQL, database queue/cache.

**Current state:** every front-end screen is built and runs on fake data (`App\Support\Demo`, with follow state in the session). Everything below replaces that fake data with a real backend.

Legend: `[x]` done · `[ ]` to do · ⚠️ needs a decision

---

## Phase 0: Decisions to make before building

- [ ] ⚠️ **Where platform data comes from.** This is the biggest risk in the project.
  - **YouTube:** the official Data API v3 works for public channels. The free quota is 10,000 units per day, so it needs caching and batching.
  - **Instagram:** the official Graph API only covers Business/Creator accounts that authorize our app. There is no official API for reading other people's public profiles.
  - **TikTok:** the Display API needs the creator to log in. The Research API is for academics only.
  - **X:** the API is paid (Basic tier or higher) to read timelines.
  - **Options:**
    - (a) Official APIs only: YouTube full, the others limited.
    - (b) A third-party data provider, such as a social-data API reseller.
    - (c) A mix, with an adapter per platform so providers can be swapped.
  - **Recommendation:** (c).
- [ ] ⚠️ **How users log in:** email and password only, or Google/Apple social login too.
- [ ] ⚠️ **Who can add creators:**
  - Any user, with an auto-created influencer record.
  - Or admin approval first.
- [ ] ⚠️ **Final brand name:** "FLWLEE" or "Followly". It is currently set in `x-brand` and the page titles.
- [ ] ⚠️ **Whether Arabic/RTL is needed at launch.** The project already has `en`/`ar` locales and the `SetLocale` middleware.

---

## Phase 1: Front-end UI ✅

- [x] Landing page (`/`) on the default `app` layout
- [x] Dashboard layout (sidebar + top bar)
- [x] My Feed (`/feed`): platform filter, sort
- [x] Explore (`/explore`): tabs, trending cards, browse by category
- [x] My Influencers (`/influencers`): search, filters, unfollow
- [x] Add Influencer (`/influencers/add`): lookup, account picker, start tracking
- [x] Influencer Profile (`/influencers/{slug}`): stats, Posts/About/Related tabs
- [x] Shared components: `platform-icon`, `post-card`, `influencer-card`, `brand`, `verified`

---

## Phase 2: Database and models

- [ ] `App\Enums\Platform` (`youtube`, `instagram`, `tiktok`, `x`) with label, colour, unit ("Subscribers"/"Followers") and URL patterns
- [ ] Migration and model **`Influencer`**:
  - `name`, `slug` (unique), `real_name`, `bio`
  - `avatar` and `cover` (media library)
  - `country_id` (nnjeim/world), `language`
  - `is_verified`, `is_featured`, `followers_count` (cached), `combined_reach` (cached)
  - timestamps and soft deletes
- [ ] Categories: use the existing `Taxonomy` model (aliziodev/laravel-taxonomy) with a `category` type, plus an `influencer ↔ taxonomy` link table
- [ ] Migration and model **`InfluencerAccount`**:
  - `influencer_id`, `platform`, `handle`, `external_id`, `url`
  - `followers` (bigint), `is_verified`, `last_synced_at`, `sync_status`, `sync_error`
  - unique on (`platform`, `external_id`)
- [ ] Migration and model **`Post`**:
  - `influencer_account_id`, `influencer_id` (stored again to make feed queries fast), `platform`, `external_id` (unique per platform)
  - `type` (video/image/carousel/text), `title`, `body`, `media` (json: urls, thumbnail), `duration_seconds`, `permalink`, `published_at`, `metrics` (json)
  - index on (`influencer_id`, `published_at`)
- [ ] Migration **`follows`**:
  - `user_id`, `influencer_id`, `created_at`, unique pair
  - plus `follow_accounts` (`follow_id`, `influencer_account_id`) so a user can pick which platforms to track (the checkboxes on the Add page)
- [ ] Migration **`account_snapshots`**: `influencer_account_id`, `followers`, `captured_on`. This feeds the trending and growth numbers.
- [ ] Relationships and scopes:
  - `User::followedInfluencers()`
  - `Influencer::followers()`, `accounts()`, `posts()`
  - `Post::scopeFeedFor(User $user)`
- [ ] Helper to format big numbers: `420000000` becomes `420M`. This replaces the hard-coded strings.
- [ ] Factories and `DemoSeeder` that move the current `Demo` data into the database

## Phase 3: Authentication

- [ ] Volt pages styled with Flux:
  - `login`, `register`, `forgot-password`, `reset-password`
  - `verify-email`, if email verification is turned on
- [ ] Make "Sign in" and "Get Started" on the landing page go to these pages
- [ ] Put the dashboard routes in an `auth` middleware group, and send logged-in visitors from `/` to `/feed`
- [ ] Log out button in the avatar dropdown
- [ ] Optional: social login with Laravel Socialite (depends on the Phase 0 decision)

## Phase 4: Replace fake data with Eloquent

- [ ] **Feed:**
  - `Post::feedFor(auth()->user())`
  - filter by platform, sort
  - cursor pagination with a "load more" / infinite scroll (`wire:intersect`)
- [ ] **Explore:**
  - trending query (see Phase 7)
  - category, country and language groups
  - recommended list
  - follow/unfollow actions
- [ ] **My Influencers:** the user's follows, with search, category and platform filters, and unfollow (with a confirm modal)
- [ ] **Profile:**
  - route model binding on `slug`
  - posts paginated
  - Related tab (same category, most tracked)
  - "Across Platforms" box showing the last post time per account
- [ ] **Add Influencer:** connect it to the URL resolver (Phase 5). Store the follow plus the chosen accounts in a transaction.
- [ ] Delete `App\Support\Demo` once every page uses real data
- [ ] Empty and loading states (`wire:loading`, Flux skeletons)

## Phase 5: Platform integration layer

- [ ] `App\Platforms\Contracts\PlatformDriver`:
  - `matchesUrl(string): bool`
  - `resolve(string $urlOrHandle): ?AccountData`
  - `fetchProfile(InfluencerAccount): AccountData`
  - `fetchRecentPosts(InfluencerAccount, ?Carbon $since): Collection<PostData>`
- [ ] DTOs: `AccountData` and `PostData`
- [ ] Drivers: `YouTubeDriver` (Data API v3), `InstagramDriver`, `TikTokDriver` and `XDriver`, each using the source chosen in Phase 0
- [ ] `PlatformManager` that picks a driver from a URL or handle
- [ ] **Finding a creator's other accounts:**
  - from one URL, find their profiles on other platforms
  - sources: links in the profile/bio, the same handle on other platforms, existing DB matches
  - the user confirms the matches on the Add page
- [ ] API keys and settings in `config/services.php` and `.env.example`
- [ ] Rate limiting and retry with backoff for each driver. Save errors to `sync_error`.

## Phase 6: Syncing (queues and scheduler)

- [ ] `SyncAccountProfile` job: updates followers and avatar, and writes an `account_snapshots` row each day
- [ ] `SyncAccountPosts` job: upserts posts by `external_id`, and only fetches posts newer than the last sync
- [ ] Scheduler (`routes/console.php`):
  - sync posts every 15–30 minutes for accounts that have followers
  - sync profiles every day
  - remove old snapshots
- [ ] Sync accounts with more followers first, to protect the API quota
- [ ] Run a first sync right away when someone adds a new influencer
- [ ] Production: queue worker plus `schedule:run` cron, added to `scripts/deploy.sh`

## Phase 7: Discovery and ranking

- [ ] **Trending score:** new FLWLEE followers in the last 7 days, plus platform follower growth from snapshots. Cache it for 1 hour.
- [ ] **Recommended:** creators in the user's most-followed categories that they don't follow yet, ranked by tracking count
- [ ] Counter for "X tracking on FLWLEE" (a `followers_count` cache on `influencers`)
- [ ] **Global search in the top bar:**
  - search influencers, categories and countries
  - dropdown with results
  - a `/search?q=` page

## Phase 8: Notifications

- [ ] `NewPostNotification` (database channel) sent to followers after a sync stores new posts
- [ ] Notifications page (`/notifications`): list, mark one read, mark all read
- [ ] Unread badge in the sidebar and on the bell (live from the DB, not hard-coded "3")
- [ ] Optional email digest (daily or weekly), sent through the queue

## Phase 9: Settings

- [ ] Settings page (`/settings`):
  - profile (name, email, avatar)
  - password
  - language (en/ar)
  - notification preferences (in-app, email digest, per platform)
  - delete account

## Phase 10: Admin (Filament)

- [ ] `InfluencerResource`: CRUD, accounts as a relation manager, verify/feature toggles, "Sync now" action
- [ ] `PostResource`: read-only list with filters, hide/remove action
- [ ] Category management through the existing taxonomy setup
- [ ] Dashboard widgets: users, follows, influencers, posts synced today, failed syncs
- [ ] Shield permissions for the new resources (`shield:generate`)

## Phase 11: Arabic / RTL

- [ ] Put every UI string in `__()` and add `lang/ar.json`
- [ ] Check every page in RTL. The UI uses logical `ms/me/ps/pe/start/end` classes, but the hero collage and some other details still need checking.
- [ ] Language switcher in the landing navbar and in Settings

## Phase 12: Polish and performance

- [ ] Store remote thumbnails and avatars locally, or proxy them through Glide (`image.manipulation` route), so slow external images don't hold up pages
- [ ] Embedded players in a modal for YouTube, TikTok and X when a user opens a post
- [ ] SEO for the landing page and public profiles (meta and OG tags)
- [ ] Mobile pass: sidebar toggle, card grids, filter rows
- [ ] Decide about dark mode. The UI is light-only right now; `@fluxAppearance` is not loaded.

## Phase 13: Testing

- [ ] Pest feature tests for each Volt page with `Volt::test()`: renders, filters, follow/unfollow, add-influencer flow
- [ ] Driver tests using `Http::fake()` and saved API responses
- [ ] Job tests: post upsert (no duplicates), snapshots, notifications sent
- [ ] Auth tests: login, register, guest redirects
- [ ] CI: Pint + Pest on each push to `main`

## Phase 14: Deployment

- [ ] `.env.example`: platform API keys, queue/cache drivers, mail
- [ ] `scripts/`: queue worker (Supervisor), scheduler cron, `npm run build`, `php artisan optimize`
- [ ] Monitoring: failed jobs alert, sync error rate
- [ ] Backup plan for the DB

---

## Suggested order

1. Phase 0 decisions
2. Phase 2 (database) and Phase 3 (auth)
3. Phase 4 (real data), using seeded data
4. Phase 5 + Phase 6, starting with **YouTube only**
5. The other platforms
6. Phases 7 → 8 → 9 → 10
7. Phases 11–14

**First milestone:** login works, YouTube creators sync for real, and Feed, My Influencers and Profile show real data.
