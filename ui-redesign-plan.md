# Followly UI Redesign Plan

**Goal:** make the user website look and work like the 7 mockups (My Feed, Analytics, Timeline, Alerts, Shared Collections, plus Catch-Up and Compare for reference only).

**Decisions (agreed):**
- Leave out AI Briefings, Catch-Up, Compare and Facebook. They are not shown anywhere, even though they appear in the mockups.
- Watchlist, Alerts, Timeline and Analytics get their own sidebar pages. The influencer page tabs link to them.
- New extras: save/bookmark posts, share buttons with a QR code, public collections that others can discover, and alert on/off switches.
- Shares are shown only where the platform gives them (TikTok today). The card is hidden when a creator has no share data.

**Reuse:** the warm gold theme (`--color-accent: #91754e`) is already close to the mockups. Reuse `SmartFeed`, `SmartLink`, `GlobalSearch`, `TrendingRanker`, `influencer-analytics`, `influencer-timeline`, `KeywordAlert`, `Collection`. Use `chillerlan/php-qrcode` (already installed) for QR codes. Draw charts with inline SVG, with no new chart library.

Legend: `[x]` done · `[ ]` to do

## Progress

- [x] Phase A: Shared look (layout, sidebar, header, page headers, cards)
- [ ] Phase B: My Feed page
- [ ] Phase C: Watchlist and Collections pages
- [x] Phase D: Alerts page
- [ ] Phase E: Timeline page
- [x] Phase F: Analytics report
- [ ] Phase G: Search, Discover, Notifications and Settings restyle
- [ ] Phase H: Mobile check, tests and push

**Order:** Phase A first, because every page uses it. After that, B to F touch different pages and can be built in parallel. G and H come last.

---

## Phase A: Shared look

- [x] Sidebar like the mockups, in this order: My Feed, Watchlist, Collections, Search, Alerts, Timeline, Discover, Analytics, Settings. It has icons, an active pill, and count badges (watchlist count, unread alerts).
- [x] Sidebar "My Collections" list with counts and a + button (see the Analytics mockup)
- [x] Top header: logo, wide rounded search bar (uses the existing search dropdown), notification bell with a badge, avatar menu
- [x] Page header component: large gold outline icon, title and subtitle (used on every page)
- [x] Card style: cream background, soft border, rounded corners, light shadow
- [x] Platform icon component in brand colours (YouTube, Instagram, TikTok, X)
- [x] Post card component: avatar, verified tick, time, platform icons, thumbnail with duration, title, text, bookmark and ⋯ menu
- [x] Remove any AI, Catch-Up, Compare or Facebook items if they exist

## Phase B: My Feed page (`/dashboard`)

- [ ] Header "My Feed", with header shortcut tiles for Alerts, Timeline and Shared only
- [ ] Tabs: My Feed · Watchlist · Collections; sort dropdown (Latest First / Smart); platform dropdown
- [ ] Feed posts use the new post card; SmartLink bar "Posted on N platforms" with icons and a link to the group
- [ ] Right column: Quick Actions (Follow Influencer, Search Content), My Alerts (keyword, platform icon, "N new posts"), My Watchlist (avatars + "+N", View All)
- [ ] **Save posts:** a `saved_posts` table (user_id, post_id, unique pair), a bookmark toggle on every post card, and a "Saved" filter tab
- [ ] Tests: bookmarking on and off, the Saved tab shows only your own saved posts

## Phase C: Watchlist and Collections pages

- [ ] `/watchlist` page: the 10 starred influencers with their latest posts, plus unstar and reorder
- [ ] Collections page with tabs: My Collections · Shared Collections · Explore Collections
- [ ] Collection card: cover image, name, public badge, description, avatars, "N Influencers", Share Link / Edit / ⋯
- [ ] **Cover image and description:** add `description` and a media library cover to `Collection`, with an upload in the edit form
- [ ] **Share panel:** link with a copy button; X, Facebook, WhatsApp, Telegram, LinkedIn and email share links (plain share URLs, with no SDKs); QR code from `chillerlan/php-qrcode`
- [ ] **Public collections:** an `is_public` column and a "Make this collection public" switch. Public collections show in Explore Collections, where others can follow every influencer in one click. The share link keeps working when a collection is not public.
- [ ] Shared and public pages never show the owner's email
- [ ] Tests: the public/private switch, Explore lists only public collections, the QR code renders, follow-all works

## Phase D: Alerts page (`/alerts`)

- [x] Header with a master **Alerts On** switch (`users.alerts_enabled`, default on)
- [x] My Keyword Alerts: list with an icon, "Across my followed influencers", an on/off switch per keyword (`keyword_alerts.enabled`), and a ⋯ menu (rename, delete), plus an Add Keyword button
- [x] "How Alerts Work" side card
- [x] Tabs: **Alert Posts** (posts that matched, with a "Matched: keyword" badge and a "Watch on YouTube"-style button) · Alert Settings
- [x] Alert matching skips keywords that are switched off, and skips users who switched Alerts off
- [x] Move the alerts section out of Settings (leave a link there)
- [x] Tests: switching off stops notifications; the matched posts list

## Phase E: Timeline page (`/timeline`)

- [ ] Influencer picker (followed influencers), defaulting to the last one viewed
- [ ] Profile card: avatar, verified tick, handle, total followers, Following button, alert bell, ⋯
- [ ] Platform chips with follower counts (YouTube 15.2M, X 3.9M, …) and All Posts, used as a filter
- [ ] Day groups ("Today, Nov 9", "Yesterday, Nov 8") on a vertical line; each row has a thumbnail, platform, time, text, platform-specific stats (likes, comments, views, reposts) and an open-link button
- [ ] Reuse the `influencer-timeline` component; the influencer page tab links here
- [ ] Tests: the platform filter, day grouping

## Phase F: Analytics report (`/analytics`)

- [x] Tabs: **My Creators** (one creator report) · **Top Performers** (followed creators ranked by views and engagement) · **Activity Trends** (posting activity across all followed creators). No Compare tab.
- [x] Date range dropdown: last 7 / 30 / 90 days
- [x] Creator header: avatar, verified tick, handle, total followers, platform icons, Following button
- [x] KPI cards: New Posts, Total Views, Total Likes, Comments, Shares (TikTok only; hidden if none). Each shows the % change against the previous period of the same length.
- [x] Performance Over Time: an SVG line chart with Views / Likes / Comments switches
- [x] Posting Activity heatmap: day of week × week
- [x] Platform Activity bars: posts and % per platform
- [x] Creator Overview: platforms, posting frequency, most active day, primary format
- [x] Content Breakdown donut: videos, shorts/reels, images, other (from `posts.type`)
- [x] Best Performing Posts: top posts by views, with thumbnail and duration
- [x] Reuse and extend the `influencer-analytics` component; the influencer page tab links here
- [x] Tests: the % change maths (including when the previous period is zero), a creator with no posts, shares hidden without TikTok

## Phase G: Restyle the remaining pages

- [ ] Search, Discover (Explore), Notifications, Settings and the influencer page get the new header, cards and post card
- [ ] Login and register pages match the new look

## Phase H: Mobile check, tests and push

- [ ] Every new page at 375px and tablet width; the sidebar becomes a slide-out menu
- [ ] Check for N+1 queries on the Feed, Analytics and Timeline pages with seeded data
- [ ] Run the full test suite; fix failures
- [ ] Push to main; add the new migrations to the launch checklist in `phase2-plan.md`
