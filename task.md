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
  - trending was ranked by Followly followers; Phase 7 added the real trending score
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
  - [x] Creating creators that are not in the database yet: done in Phase 5
- [x] Delete `App\Support\Demo`
- [x] Empty states on every list, skeletons on feed filter changes, dimming on Explore and My Influencers filter changes
- [x] Pest tests in `tests/Feature/Pages/DashboardPagesTest.php`

## Phase 5: Platform integration layer ✅

- [x] `App\Platforms\Contracts\PlatformDriver`: `platform()`, `matchesUrl()`, `resolve()`, `fetchProfile()`, `fetchRecentPosts(?since)`. Shared plumbing (rate limit, retries, URL parsing, resolve cache) is in the abstract `App\Platforms\Driver`.
- [x] DTOs `App\Platforms\Data\AccountData` and `PostData`, with `toAccountAttributes()` / `toPostAttributes()` and array round-tripping for Livewire state
- [x] Exceptions: `PlatformException`, `AccountNotFoundException`, `RateLimitedException` (carries `retryAfter` for Phase 6 jobs)
- [x] `YouTubeDriver` (YouTube Data API v3):
  - resolves `@handle`, `/channel/<id>`, `/user/`, `/c/`, bare handles and channel ids, and video links (`watch?v=`, `youtu.be`, `/shorts/`, `/live/`) through the video's channel
  - new uploads from the uploads playlist (`UU…`), then one `videos` call for duration and stats; scheduled premieres are skipped
  - `fetchProfiles()` batches 50 channels per call
  - counts quota units per Pacific day (`quotaUsedToday()`) and stops at `YOUTUBE_DAILY_QUOTA`; `quotaExceeded` becomes `RateLimitedException`
  - the API has no verified badge, so YouTube accounts are never marked verified
- [x] `App\Platforms\Apify\ApifyClient`:
  - starts a run with `waitForFinish` (blocks up to 60 s, so quick actors act synchronously) and polls slow ones until `APIFY_TIMEOUT_SECONDS`, then aborts. This replaces `run-sync-get-dataset-items`, whose response has no run object, so cost could not be logged.
  - every run is stored in `apify_runs` (status, items, `usage_usd`, duration, error) and logged; `ApifyRun::spentThisMonth()` is ready for the Phase 6 budget
- [x] `InstagramDriver` (`apify/instagram-profile-scraper`), `TikTokDriver` (`clockworks/tiktok-scraper`), `XDriver` (`apidojo/tweet-scraper`) on a shared `ApifyDriver`. Each actor returns the profile and recent posts together, and `fetchProfile` + `fetchRecentPosts` reuse one run for 10 minutes. Actor ids are in `config/services.php` (`APIFY_*_ACTOR`).
  - X leaves out retweets and replies. TikTok and X read the profile from the posts, so an account with no posts cannot be resolved.
  - [x] **Checked against the live actors** (natgeo, nasa, NASA). Profiles and posts map correctly. Fixed from what the real output showed:
    - all three actors are pay-per-event, and the charges reach the run 1–3 s after it finishes, so `run()` logs most runs at $0. The cost is `usageTotalUsd`, or `chargedEventCounts` × event price when that is higher, and `php artisan apify:costs` (every 5 minutes) fills in runs from the last day that are still at $0. The budget can lag by up to 5 minutes.
    - TikTok slideshows take their images from `slideshowImageLinks` and get no duration (the actor reports 0); `bioLink` comes without a scheme, so `https://` is added
    - X searches `from:<handle> -filter:retweets -filter:replies` instead of `twitterHandles`, so retweets and replies are neither fetched nor charged. A creator whose recent tweets are all retweets or replies cannot be resolved.
    - TikTok's pinned video is returned with the newest ones
- [x] `PlatformManager` (singleton): `driver()`, `drivers()`, `forUrl()`, `extend()` to swap a driver, `resolve()` (a link goes to its platform; a bare handle tries YouTube first, then the others)
- [x] **Finding a creator's other accounts** (`App\Platforms\AccountFinder`):
  - follows links in profiles and bios, including chains (YouTube description → Instagram bio → X)
  - then tries the same handle on the remaining platforms (`PLATFORM_DISCOVER_SAME_HANDLE`; each guess on an Apify platform is one actor run)
  - `InfluencerLookup::lookup()` checks the database first (no API calls for stored creators), matches found accounts to stored ones by platform id (so changed handles still match), and returns a `LookupResult` with the influencer and the accounts not stored yet
  - Add page lists the new accounts with "Linked from their profile" / "Same username, check it's them" badges; same-handle guesses start unticked. `InfluencerImporter` creates the influencer (`created_by` = the user) and accounts in a transaction, skipping accounts another creator owns, then the user follows the chosen accounts.
  - an unreachable platform shows "We couldn't reach … right now" instead of an error page
- [x] Keys and settings: `YOUTUBE_API_KEY`, `APIFY_TOKEN`, actor ids and timeouts in `config/services.php`; rate limits, retry backoff, resolve cache, posts per fetch and discovery in `config/platforms.php`; all in `.env.example`
- [x] Per-platform rate limit (`config/platforms.php`), HTTP retry with backoff on 429/5xx/connection errors, and `InfluencerAccount::markSyncFailed()` / `applyProfile()` to save errors to `sync_error` and clear them on success
- [x] Tests in `tests/Feature/Platforms` with `Http::fake()` (YouTube, the three Apify drivers, the client's polling, timeout and cost log) and a `Tests\Fakes\FakeDriver` for lookup and the Add page. `Http::preventStrayRequests()` is on for every test.
- New creators have no posts until Phase 6 runs their first sync.

## Phase 6: Syncing (queues and scheduler) ✅

- [x] `App\Sync\AccountSyncer` does the storing:
  - `syncProfile()`: followers, handle and verified flag, today's `account_snapshots` row, and the influencer's avatar from their biggest account (platform image links expire)
  - `syncPosts()`: stores posts newer than the account's latest stored post (no duplicates on `platform` + `external_id`), sets `last_synced_at`, and fires `App\Events\PostsSynced` with the new posts (for Phase 8 notifications)
- [x] Jobs on the `platforms` queue, sharing `App\Jobs\Concerns\HandlesPlatformErrors`. A rate limit releases the job until the limit resets, without counting as a failure. A missing account is marked failed and not retried. Other errors are saved to `sync_error` and retried after 1, 5 and 15 minutes, with up to 3 errors within 6 hours.
  - `SyncAccountPosts` and `SyncAccountProfile`: one account each, unique per account
  - `SyncYouTubeProfiles`: 50 channels in one API call
  - `SyncApifyBatch`: one actor run for up to `APIFY_BATCH_SIZE` handles. The run returns profiles and posts together, so it updates both. Handles looked up in the last 10 minutes reuse the cached result, so the first sync after the Add page costs nothing extra.
- [x] `App\Sync\SyncDispatcher` and the `php artisan platforms:sync {posts|profiles} {--platform=*}` command:
  - only tracked accounts (`InfluencerAccount::tracked()`), biggest first, up to `*_SYNC_MAX` per platform per run
  - due when the last sync is older than the platform's interval: YouTube 30 min, Instagram/TikTok/X 120 min (`*_SYNC_MINUTES`). Queued accounts are marked `syncing` and skipped for up to an hour. Failed ones wait `PLATFORM_FAILED_RETRY_HOURS` (24).
  - the daily profile refresh covers YouTube only, because Apify runs return the profile with every post sync
- [x] Scheduler (`routes/console.php`): posts every 15 minutes, YouTube profiles at 03:00, and `model:prune` at 03:30. Pruning removes snapshots and `apify_runs` rows older than 400 days (`SNAPSHOT_RETENTION_DAYS`, `APIFY_RUN_RETENTION_DAYS`). The worker now reads `--queue=default,platforms`.
- [x] Monthly Apify budget (`APIFY_MONTHLY_BUDGET_USD`, 0 means no limit). Spend is the sum of `apify_runs.usage_usd` for the month. Once it reaches the budget:
  - the dispatcher skips Apify platforms
  - `ApifyClient` refuses any run, including Add page lookups
  - every admin gets one `ApifyBudgetReached` email per month

  It is an `.env` setting for now; Phase 10 did not add a Filament setting for it.
- [x] First sync right away: `InfluencerImporter` takes a snapshot of each new account and queues its sync once the transaction commits
- [x] `DB_QUEUE_RETRY_AFTER` raised to 660 s so a slow Apify batch (up to 300 s, plus download) is not picked up twice
- [x] Production: `scripts/queue.sh` (run by `deploy.sh`) installs the `schedule:run` cron if it is missing and runs `queue:restart`. Hosting looks like cPanel, so the cron-started `queue:work --stop-when-empty` stays; the script explains how to switch to Supervisor.
- [x] Tests in `tests/Feature/Sync/SyncTest.php`; the shared `profile()` / `fakePlatforms()` helpers are now in `tests/Pest.php`
- Checked end to end with live Apify (NASA from an Instagram link: lookup found TikTok and X by handle, import, queued sync, 42 posts in the feed; a forced re-sync made real runs and stored no duplicates). YouTube checked too: `@NASA` matched the stored NASA, 20 videos synced, and the daily profile refresh covers 8 channels in one call. Fixes from that run:
  - adding a platform to a stored creator re-ran Apify for every account it already had (~50 s, ~$0.08 per lookup); discovery now takes stored accounts from the database (`InfluencerAccount::findFromUrl()` / `findByHandle()`)
  - every deploy wiped the app cache (`optimize:clear`), resetting the YouTube quota count; `scripts/optimize.sh` now keeps it
  - demo YouTube accounts have fake channel ids, so a profile refresh marks them failed locally

## Phase 7: Discovery and ranking ✅

- [x] **Trending score** (`App\Discovery\TrendingRanker`, settings in `config/discovery.php`):
  - new Followly followers in the last 7 days (`TRENDING_DAYS`) × `TRENDING_FOLLOW_WEIGHT` (1), plus platform follower growth in percent × `TRENDING_GROWTH_WEIGHT` (2)
  - growth compares each account's first and last snapshot inside the window, summed across the creator's accounts; accounts with one snapshot in the window are left out, and growth counts up to ±`TRENDING_GROWTH_CAP` (50%) so small accounts can't swamp the list
  - stored on `influencers.trending_score` by `php artisan influencers:rank`, which runs hourly (this is the 1-hour cache, and lets Explore sort on an indexed column)
  - Explore "Trending Now" uses `Influencer::trending()`: score first, then most tracked, so it still works before any snapshots exist
- [x] **Recommended:** `Influencer::recommendedFor($user)`: unfollowed creators in the user's top `RECOMMENDED_CATEGORIES` (3) categories, most tracked first; users who follow no categorised creators get the most tracked creators overall
- [x] Counter for "X tracking on Followly": `influencers.followers_count` (from Phase 2) is kept by `follow()` / `unfollow()`, and `influencers:rank` also recounts every influencer in one query (`Influencer::recountFollowers()`), which fixes follows removed by database cascades
- [x] **Global search in the top bar** (`App\Discovery\GlobalSearch`):
  - the `search-bar` Volt component (`resources/views/livewire`) searches as you type (2+ characters): influencers by name, handle, category or country; categories and countries that have creators, with counts
  - dropdown with up to 5 results per group and "See all results"; Enter opens the page
  - `/search?q=` page: categories and countries as chips, creator cards with follow buttons and "load more"
  - categories link to Explore's category filter; countries link to a new Explore country filter (`/explore?tab=country&country=US`)
- [x] Tests in `tests/Feature/Discovery/DiscoveryTest.php`

## Phase 8: Notifications ✅

- [x] `NewPostNotification` (database channel, type `new-post`) from the queued `App\Listeners\NotifyFollowersOfNewPosts`, which listens for `PostsSynced`:
  - only users who track that account (`follow_accounts`), not everyone who follows the creator
  - all new posts from one sync become one notification ("posted 3 times on YouTube"), newest post's title, image and link stored in `data`
  - posts older than `NOTIFY_MAX_POST_AGE_HOURS` (48) are left out, so a creator's first sync (their back catalogue) doesn't flood anyone, and so are posts published before the user followed
- [x] Notifications page (`/notifications`, component `notifications.index`, because Filament already registers a Livewire component called `notifications`):
  - All / Unread filter, "load more", unread rows highlighted
  - clicking a notification marks it read and opens the creator's profile; per-row "mark as read" and "open on platform"; "Mark all as read"
- [x] Unread badge in the sidebar and on the top bar bell: the `notification-count` Volt component, counted from the DB, polling every 60 s while visible and refreshing at once when the page marks something read (`notifications-read` event). Shows `99+` above 99.
- [x] Email digest, sent through the queue:
  - `users.email_digest` (`App\Enums\DigestFrequency`: daily / weekly, null = off) and `users.digest_sent_at`; the setting's UI is in Phase 9
  - `php artisan notifications:digest {daily|weekly}` mails `NewPostsDigest` with the unread new post notifications since the last digest (up to `DIGEST_MAX_ITEMS`, 20); users with nothing new get no email
  - scheduled daily at `DIGEST_TIME` (08:00) and weekly on Mondays
- [x] Read notifications older than `NOTIFICATION_RETENTION_DAYS` (90) are deleted daily at 03:45
- [x] `DemoSeeder` adds a notification per tracked account with posts (newest two unread)
- [x] Tests in `tests/Feature/Notifications/NotificationsTest.php`

## Phase 9: Settings ✅

- [x] Settings page (`/settings`, Volt page `settings`), linked from the sidebar and the avatar menu:
  - profile: name, email (changing it clears `email_verified_at`), avatar upload/remove (`users` media collection, now single-file, max 2 MB). The top bar avatar shows it, falling back to initials (`User::avatarUrl()`).
  - password: needs the current password, `Password::defaults()` for the new one
  - notification preferences:
    - in-app on/off (`users.notify_in_app`, default on). Off hides the bell and sidebar badge, and the notifications page says so with a link to Settings.
    - email digest off / daily / weekly (`users.email_digest` from Phase 8)
    - per platform (`users.muted_platforms`, a JSON list of muted `Platform` values, null = none muted)
    - the new post listener skips users who muted the platform, and users with in-app and digest both off. With in-app off and the digest on, notifications are still stored, because the digest is built from them.
  - delete account: password confirm in a modal; `User::deleteAccount()` removes the user's notifications, API tokens and media, follows go by cascade, and the followed creators' follower counts are refreshed. Creators the user added stay.
- [x] "Saved." messages through the new `x-saved-message` component
- [x] Tests in `tests/Feature/Settings/SettingsTest.php`

## Phase 10: Admin (Filament) ✅

New resources sit in a "Content" navigation group, listed before Settings.

- [x] `InfluencerResource` (`/admin/influencers`, records by slug):
  - list: avatar, name, platform badges, category, reach, tracking count, verified/featured toggles, "Added by" (name + User/Admin); filters for category, platform, verified, featured, added by, "has a failed sync" and trashed
  - create, edit and view pages; soft delete, restore, force delete. Uploaded avatar/cover replace the platform images. Creating by hand credits the admin (`created_by`).
  - accounts relation manager: platform, handle (links to the profile), followers, sync status with the error as a tooltip, last synced. "Add account" resolves a link through `PlatformManager` and stores it with `InfluencerImporter`, refusing a platform the influencer already has or an account another influencer owns. Accounts are never edited by hand.
  - "Sync now" on an influencer, on accounts, and in bulk: `SyncDispatcher::now()` queues the post sync and, for YouTube, the profile refresh, ignoring the sync interval
  - "Add from link" (a two-step wizard) runs `InfluencerLookup` like the user-facing Add page: step one finds the accounts, step two picks which to store (same-username guesses start unticked). An influencer that is already fully stored stops on step one with a link to it.
- [x] `PostResource` (`/admin/posts`): read-only list and view modal, filters for influencer, platform, type, hidden and "stored today"
  - "Hide" sets `posts.hidden_at`. Hidden posts drop out of the feed (`Post::feedFor()`) and the profile (`Post::scopeVisible()`), and since the row stays, a sync doesn't store them again. "Show again" clears it. Both also work in bulk.
  - "Remove" deletes the post; the modal warns that a sync may store it again if it is still among the account's newest posts
- [x] `CategoryResource` (`/admin/categories`): `taxonomies` rows of type `category`, with name, emoji (`meta.emoji`), slug, sort order (drag to reorder) and description, plus an influencer count. Deleting a category leaves its influencers uncategorised (the foreign key nulls on delete).
- [x] Dashboard widgets:
  - `StatsOverview` (was a generic count of every resource): users (new this week), follows, influencers (how many are tracked), posts synced today
  - `SyncHealth`: failed syncs (links to the filtered list), YouTube quota used today against `YOUTUBE_DAILY_QUOTA`, and Apify spend this month against the budget
- [x] `Platform`, `SyncStatus` and `PostType` implement Filament's `HasLabel` (and `HasColor` for the first two), for badges and filter options
- [x] Shield: `InfluencerPolicy`, `PostPolicy` and `TaxonomyPolicy` generated; `scripts/migrate.sh` already runs `shield:generate --all` on deploy, which adds their permissions. Assign them to roles other than `super_admin` in Roles.
- [x] Tests in `tests/Feature/Admin/ContentAdminTest.php`; the `superAdmin()` helper moved to `tests/Pest.php`
- The Apify budget is still an `.env` setting (`APIFY_MONTHLY_BUDGET_USD`) and has no Filament setting yet

## Phase 12: Polish and performance ✅

- [x] Platform images go through a proxy instead of the platform CDNs. `image_url($url, $width, $height)` returns a signed `image.remote` URL (`/img`). `App\Support\RemoteImage` downloads each image once, resizes it to webp with Glide, keeps it in `storage/app/image-cache`, and serves it with a 30-day browser cache. Local URLs (uploads) pass through unchanged.
  - A failed download sends the browser to the original URL and isn't retried for an hour. Non-images, files over 10 MB and hosts that resolve to private addresses are refused.
  - `images:prune` (daily, 04:00) removes images nobody has requested for `IMAGE_CACHE_DAYS`. `IMAGE_PROXY=false` links straight to the CDNs.
  - Used for avatars, covers, post thumbnails and notification images. Email notifications still use the original URLs.
  - The first visitor still waits for the platform once per image; images are not prefetched on sync.
- [x] Embedded players: `Post::$embed` gives the iframe for YouTube (youtube-nocookie), TikTok (`/player/v1`) and X (`platform.twitter.com/embed/Tweet.html`). Clicking a post's media, or "Watch here" / "View here" in its menu, opens the shared `x-player-modal` (Escape or the backdrop closes it and stops playback). Instagram posts still open on Instagram.
- [x] SEO: `partials/seo` adds description, canonical, Open Graph, Twitter card and JSON-LD tags. Pages pass them with `layoutData()`.
  - Profiles (`/influencers/{slug}`) are now public. Guests get `components.layouts.public` (with the `x-site-header` navbar shared with the landing page); signed-in users keep the dashboard. A guest who presses Follow goes to Register and comes back to the profile.
  - Profiles get their bio (or a generated line) as the description, the avatar as the share image and a schema.org `Person` with the accounts as `sameAs`.
  - `/sitemap.xml` (the landing page and every profile, cached for an hour) and `/robots.txt` (a route now, so it can give the sitemap's full URL; `/admin` is disallowed)
- [x] Mobile pass at 375px: the sidebar toggle, feed, Explore grids, profile, notifications and settings already worked. My Influencers' filters now sit in two columns under the search box, and multi-image posts use two columns on phones.
- [x] Dark mode: staying light-only for now. Supporting it means `dark:` variants on every custom page and component, not just Flux's own, so it would be a phase of its own.
- [x] Tests in `tests/Feature/Images/RemoteImageTest.php` and `tests/Feature/Pages/PublicPagesTest.php`

## Phase 13: Testing

- [ ] Pest feature tests for each Volt page with `Volt::test()`: renders, filters, follow/unfollow, add-influencer flow
- [x] Driver tests using `Http::fake()` with YouTube API and Apify actor responses (Phase 5)
- [x] Job tests: post upsert (no duplicates), snapshots (Phase 6)
- [x] Job tests: notifications sent (Phase 8)
- [ ] Auth tests: login, register, guest redirects
- [ ] CI: Pint + Pest on each push to `main`

## Phase 14: Deployment

- [ ] `.env.example`: queue/cache drivers, mail (`YOUTUBE_API_KEY`, `APIFY_TOKEN` and actor IDs added in Phase 5)
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
6. Phases 12–14

**First milestone:** login works, YouTube creators sync for real, and Feed, My Influencers and Profile show real data.
