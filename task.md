# Followly: Implementation Plan

**Goal:** a user follows a creator once and sees that creator's posts from YouTube, Instagram, TikTok and X in one feed.

**Stack:** Laravel 13, Livewire 4 with Volt (class-based), Flux UI (free), Tailwind 4, Filament 5 (admin), MySQL, database queue/cache.

**Current state:** every front-end screen is built and runs on fake data (`App\Support\Demo`, with follow state in the session). Everything below replaces that fake data with a real backend.

Legend: `[x]` done · `[ ]` to do

---

## Phase 0: Decisions ✅

- [x] **Platform data:** use the platform's free official API where one exists; otherwise use [Apify](https://apify.com) actors.
  - **YouTube:** YouTube Data API v3 (free, 10,000 quota units per day, so it needs caching and batching).
  - **Instagram:** Apify. There is no free official API for reading other people's public profiles.
  - **TikTok:** Apify. The Display API needs the creator to log in, and the Research API is for academics only.
  - **X:** Apify. The official API charges to read timelines.
  - Each platform gets its own driver, so a platform can later move from Apify to an official API without touching the rest of the app.
- [x] **Login:** email and password only. No social login.
- [x] **Adding creators:** both users and admins can add creators. No approval step.
- [x] **Brand name:** Followly.
- [x] **Language:** English only for now. Leave the existing `en`/`ar` setup and `SetLocale` middleware as they are; bilingual support may come later.

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
- [x] Rename "FLWLEE" to "Followly" everywhere: `x-brand`, page titles in `partials/head`, "tracking on …" text, footer, `APP_NAME`

---

## Phase 2: Database and models ✅

- [x] Enums: `App\Enums\Platform` (label, unit, profile URL, detect platform from a link), `PostType`, `SyncStatus`
- [x] Migration and model **`Influencer`**:
  - `name`, `slug` (unique, generated automatically), `real_name`, `bio`
  - `avatar_url` / `cover_url` (from the platform) plus `avatar` / `cover` media-library collections for uploads
  - `category_id`, `country_id` (nnjeim/world), `language`
  - `is_verified`, `is_featured`, `followers_count` (cached), `combined_reach` (cached)
  - `created_by_type` / `created_by_id` (morph to `User` or `Admin`)
  - timestamps and soft deletes
- [x] Categories: `category_id` on influencers, pointing at the existing `taxonomies` table (type `category`). A plain foreign key is used instead of the package's `taxonomables` link table, because that table is set up for UUID model ids and each influencer has one category.
- [x] Migration and model **`InfluencerAccount`**: one per influencer per platform, unique on (`platform`, `external_id`). Saving it keeps the influencer's combined reach up to date.
- [x] Migration and model **`Post`**: `influencer_id` and `platform` are copied from the account automatically, unique on (`platform`, `external_id`), index on (`influencer_id`, `published_at`)
- [x] Migrations and models **`Follow`** + **`FollowAccount`** (`follows` and `follow_accounts`), so a user can choose which platforms to track
- [x] Migration and model **`AccountSnapshot`**: one row per account per day (`InfluencerAccount::snapshot()`)
- [x] Relationships and helpers:
  - `User::follows()`, `followedInfluencers()`, `isFollowing()`, `follow($influencer, $accountIds)`, `unfollow()`
  - `Influencer::accounts()`, `posts()`, `followers()`, `category()`, `country()`, `createdBy()`, `scopeSearch()`, `scopeMostTracked()`
  - `Post::scopeFeedFor(User $user)`, `scopePlatform()`, formatted `duration`
- [x] Number formatting uses Laravel's `Number::abbreviate()` (`420000000` becomes `420M`) through `formatted_*` accessors
- [x] Factories (`Influencer`, `InfluencerAccount`, `Post`) and `DemoSeeder` (7 creators, 27 accounts, 11 posts, test user following 6). `DatabaseSeeder` runs it in the local environment only.
- [x] Pest tests in `tests/Feature/Models/InfluencerTest.php`

## Phase 3: Authentication ✅

- [x] Volt pages styled with Flux on a new `auth` layout (`pages/auth/*`, routes in `routes/auth.php`):
  - `login`, `register`, `forgot-password`, `reset-password`
  - `verify-email` page and signed verify route are in place; to turn verification on, implement `MustVerifyEmail` on `User` and add the `verified` middleware
- [x] "Sign in" goes to `/login`; "Get Started" buttons go to `/register`
- [x] Dashboard routes are in an `auth` middleware group (guests go to `/login`); `/` uses `guest`, so logged-in visitors go to `/feed`
- [x] Log out button (POST `/logout`) and the user's name/email in the avatar dropdown. `Logout` now uses the default guard (`user`), since there is no `web` guard.
- [x] Rate limits: login 5 attempts per email + IP; password reset 5 requests per IP (plus the broker's own per-email throttle)
- [x] Pest tests in `tests/Feature/Auth/AuthenticationTest.php`

## Phase 4: Replace fake data with Eloquent ✅

- [x] **Feed:**
  - `Post::feedFor(auth()->user())`
  - filter by platform (the "Other" chip was dropped; every post belongs to a `Platform` case), sort latest/oldest
  - "load more" with `wire:intersect` plus a button fallback. It grows a `limit` (15 per step) rather than using `cursorPaginate`, which keeps the Livewire state small and needs no cursor bookkeeping; switch to cursors if feeds get very long.
- [x] **Explore:**
  - trending is ranked by Followly followers for now; Phase 7 replaces it with the real trending score
  - category, country and language groups; "Browse by Category" from the `category` taxonomies (emoji in `meta.emoji`)
  - recommended: unfollowed creators in the user's most-followed categories
  - follow/unfollow through the shared `App\Livewire\Concerns\TogglesFollow` trait (also used on the profile)
- [x] **My Influencers:** the user's follows with the platforms they track, search, category and platform filters, and unfollow with a confirm modal
- [x] **Profile:**
  - route model binding on `slug` (`influencers/{influencer}`)
  - posts with "load more"
  - Related tab (same category, most tracked)
  - "Across Platforms" box showing the last post time per account
  - accounts are ordered YouTube, Instagram, TikTok, X everywhere (`Platform::orderSql()` on `Influencer::accounts()`)
- [x] **Add Influencer:** `App\Support\InfluencerLookup` finds existing creators from a profile link (incl. `youtube.com/channel/<id>`), a handle or a name, and the user picks which accounts to track. Following again updates the tracked accounts.
  - [ ] Creating creators that are not in the database yet needs the platform drivers: moved to Phase 5 (wire `PlatformManager` into `InfluencerLookup`, then create the influencer + accounts in a transaction and follow)
- [x] Delete `App\Support\Demo`
- [x] Empty states on every list, skeletons on feed filter changes, dimming on Explore and My Influencers filter changes
- [x] Pest tests in `tests/Feature/Pages/DashboardPagesTest.php`

## Phase 5: Platform integration layer

- [ ] `App\Platforms\Contracts\PlatformDriver`:
  - `matchesUrl(string): bool`
  - `resolve(string $urlOrHandle): ?AccountData`
  - `fetchProfile(InfluencerAccount): AccountData`
  - `fetchRecentPosts(InfluencerAccount, ?Carbon $since): Collection<PostData>`
- [ ] DTOs: `AccountData` and `PostData`
- [ ] `YouTubeDriver` using the YouTube Data API v3 (free):
  - resolve `@handle`, channel URLs and video URLs to a channel
  - read new uploads from the channel's uploads playlist (1 quota unit per call, much cheaper than search)
  - batch up to 50 channels per stats request
- [ ] `App\Platforms\Apify\ApifyClient`:
  - runs an actor synchronously (`run-sync-get-dataset-items`), with a timeout and a fallback to async run + poll for slow actors
  - API token in `.env` (`APIFY_TOKEN`)
  - logs each run's cost and usage so spending can be watched
- [ ] `InstagramDriver`, `TikTokDriver` and `XDriver` built on `ApifyClient`. Pick and test one actor per platform (profile + recent posts), and keep the actor IDs in config so they can be swapped.
- [ ] Map each actor's output into the shared `AccountData` / `PostData` DTOs
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
- [ ] Sync accounts with more followers first, to protect the YouTube quota and Apify spend
- [ ] Sync Apify platforms less often than YouTube (for example every 1–2 hours), and batch many handles into one actor run where the actor supports it
- [ ] Monthly Apify budget setting; pause Apify syncs and alert admins when it is reached
- [ ] Run a first sync right away when someone adds a new influencer
- [ ] Production: queue worker plus `schedule:run` cron, added to `scripts/deploy.sh`

## Phase 7: Discovery and ranking

- [ ] **Trending score:** new Followly followers in the last 7 days, plus platform follower growth from snapshots. Cache it for 1 hour.
- [ ] **Recommended:** creators in the user's most-followed categories that they don't follow yet, ranked by tracking count
- [ ] Counter for "X tracking on Followly" (a `followers_count` cache on `influencers`)
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
  - notification preferences (in-app, email digest, per platform)
  - delete account

## Phase 10: Admin (Filament)

- [ ] `InfluencerResource`: CRUD, accounts as a relation manager, verify/feature toggles, "Sync now" action
  - "Add from link" action that uses the same resolver as the user-facing Add Influencer page
  - show who added each creator (user or admin)
- [ ] `PostResource`: read-only list with filters, hide/remove action
- [ ] Category management through the existing taxonomy setup
- [ ] Dashboard widgets: users, follows, influencers, posts synced today, failed syncs, YouTube quota used, Apify spend this month
- [ ] Shield permissions for the new resources (`shield:generate`)

## Phase 11: Arabic / RTL (future, not in current scope)

The site is English only for now. Leave the existing `en`/`ar` config and `SetLocale` middleware untouched. If bilingual support is added later:

- [ ] Put every UI string in `__()` and add `lang/ar.json`
- [ ] Check every page in RTL. The UI already uses logical `ms/me/ps/pe/start/end` classes.
- [ ] Language switcher in the landing navbar and in Settings

## Phase 12: Polish and performance

- [ ] Store remote thumbnails and avatars locally, or proxy them through Glide (`image.manipulation` route), so slow external images don't hold up pages
- [ ] Embedded players in a modal for YouTube, TikTok and X when a user opens a post
- [ ] SEO for the landing page and public profiles (meta and OG tags)
- [ ] Mobile pass: sidebar toggle, card grids, filter rows
- [ ] Decide about dark mode. The UI is light-only right now; `@fluxAppearance` is not loaded.

## Phase 13: Testing

- [ ] Pest feature tests for each Volt page with `Volt::test()`: renders, filters, follow/unfollow, add-influencer flow
- [ ] Driver tests using `Http::fake()` and saved YouTube API and Apify actor responses
- [ ] Job tests: post upsert (no duplicates), snapshots, notifications sent
- [ ] Auth tests: login, register, guest redirects
- [ ] CI: Pint + Pest on each push to `main`

## Phase 14: Deployment

- [ ] `.env.example`: `YOUTUBE_API_KEY`, `APIFY_TOKEN`, Apify actor IDs, queue/cache drivers, mail
- [ ] `scripts/`: queue worker (Supervisor), scheduler cron, `npm run build`, `php artisan optimize`
- [ ] Monitoring: failed jobs alert, sync error rate
- [ ] Backup plan for the DB

---

## Suggested order

1. Phase 2 (database) and Phase 3 (auth)
2. Phase 4 (real data), using seeded data
3. Phase 5 + Phase 6, starting with **YouTube only** (free API)
4. Instagram, TikTok and X through Apify
5. Phases 7 → 8 → 9 → 10
6. Phases 12–14 (Phase 11 is future work)

**First milestone:** login works, YouTube creators sync for real, and Feed, My Influencers and Profile show real data.
