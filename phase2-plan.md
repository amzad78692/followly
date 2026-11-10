# Followly Phase 2 Plan

**Goal:** upgrade the user website with a new Master Dashboard, better ways to organise influencers, better search and alerts, Google and Apple login, and an admin overview page.

**Out of scope:** AI features (Daily Briefing, Catch-Up, Advanced AI), Followly Compare, new platforms (Facebook, Threads, LinkedIn, Spotify, Apple Podcasts, Discord), Supabase, Grafana, Clarity, Sentry and Cloudflare.

**Stack (unchanged):** Laravel, Livewire with Volt, Flux UI, Tailwind, Filament (admin), MySQL.

Legend: `[x]` done · `[ ]` to do

## Progress

- [ ] Phase 0: Decisions and setup
- [x] Phase 1: Google and Apple login (needs real Google and Apple keys to try live)
- [x] Phase 2: Database groundwork
- [x] Phase 3: Master Dashboard and Smart Feed (switching the main navigation is left for Phase 8)
- [x] Phase 4: Watchlist and Collections
- [x] Phase 5: Search and Discovery
- [x] Phase 6: Notifications, Alerts and Timeline
- [x] Phase 7: Admin dashboard
- [ ] Phase 8: Mobile polish, testing and launch

**Build order:** Phases 1 and 7 do not depend on the others and can be built in parallel. Phase 2 must come before Phases 3 to 6.

---

## Phase 0: Decisions and setup

- [x] **Followly Compare:** dropped from Phase 2.
- [ ] **Master Dashboard URL:** build it at `/dashboard` and leave `/feed` (My Feed) running. After launch, `/feed` redirects to `/dashboard`.
- [ ] **SmartLink rule (no AI):** posts are the same story when they come from the same influencer, are posted within 24 hours of each other, and either share a link or have captions that are at least 80% similar after cleanup. Tune the threshold with real data.
- [ ] **Smart Feed ranking (no AI):** newer posts score higher; posts from Watchlist influencers get a boost; high engagement compared with the influencer's usual numbers adds a small boost; SmartLink groups show as one card.
- [ ] **Watch app:** Phase 2 is website only. The watch API stays as it is.
- [ ] Get a Google Cloud OAuth client (Web) with the production and local redirect URLs.
- [ ] Get Apple Sign In credentials: Services ID, Key ID, Team ID and a `.p8` key. Apple needs an HTTPS redirect URL, so test on a tunnel or on staging.
- [ ] Get a GA4 property ID and a Google service account with Viewer access to that property.

---

## Phase 1: Google and Apple login

- [x] Install `laravel/socialite` and `socialiteproviders/apple`
- [x] Migration: add `google_id` and `apple_id` (unique, nullable); `password` was already nullable
- [x] Routes: `auth/{provider}/redirect` and `auth/{provider}/callback` (only `google` and `apple` allowed)
- [x] Callback logic: find the user by provider ID → otherwise by verified email (link the accounts) → otherwise create a new user
- [x] Apple sends the user's name only on the first login, so save it then
- [x] Handle Apple's private relay emails (`@privaterelay.appleid.com`)
- [x] Add "Continue with Google" and "Continue with Apple" buttons to the login and register pages
- [x] Settings: a user with no password can set one, and confirms account deletion by typing their email
- [x] Existing email-and-password accounts keep working unchanged
- [x] Tests: new user, existing user linked by email, returning user, cancelled login

---

## Phase 2: Database groundwork

One migration batch for everything Phases 3 to 6 need.

- [x] `follows.watchlisted_at` (nullable timestamp). There is a limit of 10 per user, checked in code.
- [x] `collections` table: `id`, `user_id`, `name`, `share_token` (nullable, unique), `timestamps`
- [x] `collection_influencer` pivot: `collection_id`, `influencer_id`
- [x] `posts.group_id` (nullable, indexed) for SmartLink groups
- [x] `keyword_alerts` table: `id`, `user_id`, `keyword`, `timestamps`
- [x] `user_activity_days` table: `user_id`, `date` (unique pair) for daily and monthly active users
- [x] Middleware that records today's activity once per user per day (cached, so it costs nothing per request)
- [x] Models and relations: `Collection`, `KeywordAlert`, `User::collections()`, `User::watchlist()`

---

## Phase 3: Master Dashboard and Smart Feed

- [x] New `/dashboard` page (Volt), with My Feed left untouched
- [x] Layout: Watchlist strip at the top, Smart Feed in the middle, Collections and Trending in the side panel (stacked on mobile)
- [x] Filters: all, watchlist, one collection, one platform
- [x] **SmartLink job:** after each sync (`PostsSynced` event), group new posts with matching posts using the Phase 0 rule and set `posts.group_id`
- [x] Backfill command: run SmartLink over existing posts once
- [x] **SmartLink card:** one post card with platform icons; clicking an icon opens that platform's version
- [x] **Smart Feed query:** use the Phase 0 ranking, show a group once, and paginate with infinite scroll
- [x] Empty states: no follows yet, no posts yet
- [ ] Point the main navigation to Dashboard after launch (Phase 8)
- [x] Tests: SmartLink grouping (same story is grouped; different stories are not), feed ranking order

---

## Phase 4: Watchlist and Collections

- [x] Star button on influencer cards and the profile page to add or remove from Watchlist
- [x] Block an 11th star with a clear message
- [x] Collections page: create, rename and delete collections
- [x] Add or remove an influencer to a collection from their card or profile page
- [x] A collection opens as a filtered feed on the Dashboard
- [x] **Shared Collections:** a Share button creates `share_token`; Unshare clears it
- [x] Public page `/c/{token}` (no login needed): collection name and influencer list with a Follow button (login prompt for guests)
- [x] Shared pages show only public data and never the owner's email
- [x] Tests: watchlist limit, a user cannot edit another user's collection, shared link works and stops working after unshare

---

## Phase 5: Search and Discovery

- [x] **Unified Search:** extend `App\Discovery\GlobalSearch` to return influencers, posts (caption text) and categories in one grouped result
- [x] Search bar dropdown shows the top results; the full search page has tabs
- [x] Add MySQL full-text indexes on post captions and influencer names if search is slow (measured 16ms with LIKE, not needed yet)
- [x] **Discover and Trending:** upgrade the Explore page with `TrendingRanker`; sections for trending now, rising this week, by category, and by country
- [x] **Creator Analytics:** an "Analytics" tab on the influencer page, built from `account_snapshots`
  - [x] Follower growth chart per platform
  - [x] Posts per week
  - [x] Average likes, comments and views per post
  - [x] Most popular posts
- [x] Tests: search returns each result type; analytics handles an influencer with no snapshots

---

## Phase 6: Notifications, Alerts and Timeline

- [x] **Notifications Center:** upgrade the notifications page with type filters (new posts, alerts, system), mark as read, mark all as read, and pagination
- [x] **Followly Alerts:**
  - [x] Settings section to add and remove keywords (limit of 20 per user)
  - [x] After each sync, match new post captions against keywords (whole word, case-insensitive)
  - [x] Send one notification per post per user, using the existing in-app, email and push channels and respecting the user's preferences
- [x] **Followly Timeline:** a "Timeline" tab on the influencer page showing all their posts across platforms by date, grouped by day, with SmartLink groups shown once
- [x] **Settings and Preferences:** reorganise the settings page into sections (Account, Login methods, Notifications, Alerts, Devices)
- [x] Tests: keyword matching (no partial-word matches), no duplicate alerts, preferences respected

---

## Phase 7: Admin dashboard (Filament)

Google Analytics is the only outside service. Every other number comes from our own database.

- [x] Install `google/analytics-data` (GA4 Data API) and store the service account credentials in `.env`, never in git
- [x] Add the GA4 tracking tag to the public site layout
- [x] Filament dashboard widgets:
  - [x] **Google Analytics:** visitors, sessions and page views (last 7 and 30 days), top pages, and traffic sources. Cache for 1 hour.
  - [x] **Sign-ups:** today, this week, this month, and a 30-day chart, including Google and Apple sign-ups
  - [x] **Active users:** DAU, MAU, and a 30-day chart from `user_activity_days`
  - [x] **Platform status:** per platform, the last successful sync, failed syncs in the last 24 hours, and accounts with errors
  - [x] **API usage:** Apify runs and spend against the budget (`ApifyBudget`) and YouTube quota used
  - [x] **Errors and alerts:** recent failed jobs and `CheckHealth` results, with a link to the log viewer
  - [x] **Performance:** queue size, and average sync job time (Apify runs only; YouTube syncs are not timed)
- [x] If Google Analytics is unreachable, show "unavailable" in that widget without breaking the page
- [x] Only admins with the right Shield permission can see the dashboard

---

## Phase 8: Mobile polish, testing and launch

- [ ] Check every new page at phone width (375px) and tablet width
- [ ] Touch-friendly star, share and filter buttons
- [ ] Run the full test suite; fix failures
- [ ] Manual run-through on staging: Google and Apple login, dashboard, watchlist, collections, sharing, search, alerts, admin dashboard
- [ ] Check page speed of the Dashboard with real data (watch for N+1 queries)
- [ ] Back up the database before running migrations on production (`BackupDatabase` command)
- [ ] Run migrations and the SmartLink backfill on production
- [ ] Switch the main navigation to Dashboard and redirect `/feed`
- [ ] Watch errors and the admin dashboard for the first week
