# Followly Watch App Plan

**Goal:** bring the Followly creator updates experience to smart watches, using the 10-screen design as the reference.

**Scope:** Tier 1 is Apple Watch and Wear OS. Tier 2 is Huawei only.

**Effort estimate:** about 16–21 developer-weeks for one experienced developer. Store and vendor approvals may add waiting time.

## Progress

- [ ] Phase 0: Accounts, devices and tools
- [ ] Phase 1: Laravel backend
- [ ] Phase 2: Wear OS app
- [ ] Phase 3: Apple Watch app
- [ ] Phase 4: Flutter phone companion and Huawei bridge
- [ ] Phase 5: Huawei Watch app (ArkTS)
- [ ] Phase 6: Huawei Watch GT app (Lite JS)
- [ ] Phase 7: Release and monitoring

---

## Key decisions

| Target | Watches | Technology | Internet path | Scope |
|---|---|---|---|---|
| Apple Watch | watchOS 10 and newer | Swift, SwiftUI, WidgetKit | Direct, standalone | Full design |
| Wear OS | Galaxy Watch 4 and newer, Pixel Watch, other Wear OS 3+ | Kotlin, Compose for Wear OS | Direct, standalone | Full design |
| Huawei full wearable | Watch 5, Watch Ultimate 2; Watch 4 and first Ultimate to be confirmed | ArkTS, ArkUI | Through Flutter phone companion | Full design, without complication |
| Huawei lite wearable | Watch GT 3, 4, 5, 6 to be confirmed | JS (ES5), HML, CSS | Through Flutter phone companion | Reduced text-first version |
| Phone companion | Android first, iPhone after the Huawei test | Flutter plus a small native Wear Engine bridge | Direct | Sign-in, bridge, phone alerts |

**Where Flutter is used:** only in the phone companion. Flutter lists smart watch support as a non-goal, and its Apple Watch option is an unofficial project. Complications also require native code. Wear OS, Apple Watch and Huawei watches therefore stay native.

**Why Huawei needs two apps:** Watch GT models run a different runtime and cannot install ArkTS apps. GT apps also have no direct internet access, so a phone companion is required.

**Why Huawei uses one data path:** both Huawei apps will talk to the phone companion over Wear Engine. This avoids building and testing a second networking path. Direct internet on full wearables can be added later if the Huawei test shows it works.

### Screen coverage

| Screen | Apple Watch | Wear OS | Huawei Watch | Huawei GT |
|---|---|---|---|---|
| Home | Yes | Yes | Yes | Simple menu |
| Updates | Yes | Yes | Yes | Text only |
| Update Detail | Yes | Yes | Yes | Text only |
| Open on Phone | Handoff | Yes | Through companion | Through companion |
| My Creators | Yes | Yes | Yes | No, manage on phone |
| Trending | Yes | Yes | Yes | No |
| Notification settings | Yes | Yes | Yes | No, manage on phone |
| Daily Summary | Yes | Yes | Yes | Text only |
| Quick Actions | Yes | Yes | Yes | No, manage on phone |
| Complication | Yes | Yes | Not planned | Not planned |

### Not in version 1

- Splash screen: watch apps should open straight to content.
- Video playback on the watch: Update Detail shows a thumbnail and Open on Phone, without a play button.
- Trending Alerts: the backend does not produce these yet.
- A full custom Followly watch face: Apple does not allow third-party faces, so version 1 uses complications.
- Other watch brands, Huawei bands and Watch Fit models.
- Native phone apps on HarmonyOS NEXT, because the Flutter Android build does not run there.

---

## Architecture

```text
Apple Watch ────── HTTPS / APNs ───────┐
Wear OS ────────── HTTPS / FCM ────────┤
                                       ├── Laravel API and push
Flutter companion ─ HTTPS / FCM, HMS ──┘
       │
       └── Wear Engine messages over Bluetooth
              ├── Huawei Watch app
              └── Huawei GT app
```

Business rules stay in Laravel. Watch apps only sign in, display API data, cache the latest response and send user actions.

### Folder layout

```text
D:\Flutter\followly-watch\
├── wear-os\            Kotlin, Android Studio
├── apple-watch\        Swift, Xcode on a Mac
├── phone-companion\    Flutter
├── huawei-watch\       ArkTS, DevEco Studio
├── huawei-gt\          JS, DevEco Studio
├── shared\
│   ├── design-tokens.md
│   ├── bridge-protocol.md
│   └── assets\
└── README.md
```

The Laravel work stays in `D:\Projects\followly`.

### App identifiers

Confirm these before creating store records, because they are hard to change after publishing.

| App | Identifier |
|---|---|
| Wear OS | `com.followly.wear` |
| Apple Watch | `com.followly.watch` |
| Phone companion | `com.followly.companion` |
| Huawei Watch | `com.followly.huawei` |
| Huawei GT | `com.followly.huawei.gt` |

---

## Phase 0: Accounts, devices and tools

**Estimate:** 2–3 days of work, plus approval time.

### Accounts

- [ ] Confirm the final app identifiers
- [ ] Decide whether to publish as a company or an individual
- [ ] Create or confirm the Apple Developer account ($99/year)
- [ ] Create or confirm the Google Play Console account ($25 one-time)
- [ ] If Google Play uses a new personal account, recruit 12 testers for the required 14-day closed test
- [ ] Register and verify a Huawei Developer account
- [ ] Create a Firebase project for Wear OS and Android push
- [ ] Create the Huawei AppGallery Connect project and companion app record
- [ ] Apply for the Huawei Wear Engine service early, because the docs do not state a review time

### Test devices

- [ ] Mac that can run the current Xcode, or a rented cloud Mac
- [ ] Apple Watch and paired iPhone
- [ ] Wear OS watch and paired Android phone
- [ ] Huawei Watch 5 or Watch Ultimate 2
- [ ] Huawei Watch GT 5 or GT 6
- [ ] Non-Huawei Android phone with Huawei Health
- [ ] Huawei Android or EMUI phone without Google services

### Windows setup

Flutter and Android Studio are already installed. Java and `adb` are not on the command path yet.

- [ ] Set `JAVA_HOME` to `C:\Program Files\Android\Android Studio\jbr`
- [ ] Add `%ANDROID_HOME%\platform-tools` to `Path`
- [ ] Install Android API 36 and the Wear OS system images
- [ ] Install DevEco Studio
- [ ] Install the HarmonyOS wearable and lite wearable SDKs in DevEco Studio
- [ ] Run `flutter doctor -v` and fix Android issues

### Project folder

```powershell
New-Item -ItemType Directory -Force D:\Flutter\followly-watch\shared\assets
Set-Location D:\Flutter\followly-watch
git init
```

- [ ] Create `D:\Flutter\followly-watch`
- [ ] Initialize the git repository and connect a private remote, so the Mac can pull the project
- [ ] Add a `.gitignore` that excludes signing keys, `google-services.json` and `agconnect-services.json`
- [ ] Add `shared/design-tokens.md` with the watch colors, spacing and type sizes
- [ ] Export the Followly logo for watch icons and complications

---

## Phase 1: Laravel backend

**Estimate:** 2–3 weeks.

**Reuse:** Sanctum, `User::follow()` and `unfollow()`, `Influencer::trending()`, stored `NewPostNotification` data, `RemoteImage::url()`, and Scramble for API documentation.

### Authentication

Watches use a short pairing code. Pairing state lives in the cache because it only exists for a few minutes.

- [x] Add `POST /api/watch/pair/start`, returning a display code, private device secret and expiry
- [x] Add `POST /api/watch/pair/poll`, returning `pending` or the token once
- [x] Store only hashes of pairing codes and device secrets
- [x] Expire pairing attempts after 5 minutes and make them single-use
- [x] Rate-limit pairing start, poll and code entry
- [x] Add a Connect Watch page where a signed-in user enters the code and sees the device name
- [x] Create named Sanctum tokens with a `watch` ability
- [x] Add `POST /api/mobile/login` and logout for the phone companion, with rate limiting
- [x] Add Connected Devices to Settings with a Disconnect action
- [ ] Add Arabic text for the new website UI (the site has no translation files yet, so this text is English like the rest of the page)

### Watch API

All routes use `auth:sanctum`, rate limiting and JSON errors.

- [x] `GET /api/watch/home`: unread count and menu state
- [x] `GET /api/watch/updates?cursor=`: compact notification list
- [x] `POST /api/watch/updates/{id}/read`
- [x] `POST /api/watch/updates/read-all`
- [x] `GET /api/watch/summary`: today's post count, creator count and top avatars
- [x] `GET /api/watch/creators`: pinned creators first
- [x] `PATCH /api/watch/creators/{influencer}`: Notify Me and Pin to Top
- [x] `DELETE /api/watch/creators/{influencer}`: reuse `User::unfollow()`
- [x] `GET /api/watch/updates/{id}`: opens the update a push points to
- [x] `GET /api/watch/categories`
- [x] `GET /api/watch/trending?category=`
- [x] `GET` and `PATCH /api/watch/settings`
- [x] Limit responses to the fields the watch shows
- [x] Ensure every query only returns the signed-in user's data
- [x] Return resized images through `RemoteImage::url()` and handle local avatar paths
- [ ] Set the API language from `Accept-Language` once the API returns translated text (nothing it returns is translated today)
- [x] Confirm the Scramble API documentation covers the new routes

### Data changes

- [x] Add `notify` (default true) and `pinned_at` to `follows`
- [x] Add `notify_push` and `push_daily_summary` to `users`
- [x] Create `device_tokens`: user, platform, provider, token, timezone, last seen
- [x] Delete device tokens with the user and when a watch is disconnected
- [x] Update `User::wantsPostNotifications()` so push-only users still receive updates

### Push notifications

- [x] Use direct HTTP calls for FCM, APNs and Huawei Push, with no new packages
- [x] Add `POST` and `DELETE /api/watch/devices`
- [x] Add a queued push delivery job, so large follower lists do not slow the sync listener
- [x] Send through FCM for Wear OS and Android
- [x] Send through APNs for Apple Watch and iPhone
- [x] Send through Huawei Push Kit for Huawei phones without Google services
- [x] Respect `notify_push`, muted platforms and each follow's `notify` setting
- [x] Keep the Updates feed row even when a per-creator alert is off
- [x] Remove tokens that the push provider reports as invalid
- [x] Add a scheduled daily summary push based on each device's timezone
- [x] Prevent duplicate alerts when a user has both a watch app and phone companion
- [x] Add watch data refresh messages for complications
- [ ] Add production credentials and confirm real delivery through Firebase
- [ ] Add production credentials and confirm real delivery through Apple APNs
- [ ] Add production credentials and confirm real delivery through Huawei Push

### Tests

- [x] Pairing success, wrong code, expired code and reused code
- [x] Rate limits on pairing and login
- [x] Revoked tokens stop working
- [x] Users cannot access or change another user's data
- [x] API pagination and resized image URLs
- [x] Notify Me, Pin to Top and Unfollow
- [x] Push respects all notification settings
- [x] Account deletion removes device tokens
- [x] Existing notification, settings and sync tests still pass

### Done when

- [x] Automated tests can pair, read every screen's data and change settings
- [ ] A real watch or phone receives a test push

---

## Phase 2: Wear OS app

**Estimate:** 3–4 weeks.

### Project setup

In Android Studio, choose **New Project → Wear OS → Empty Compose App** and save to `D:\Flutter\followly-watch\wear-os`.

- [ ] Create the project with package `com.followly.wear`
- [ ] Set `minSdk = 30` and `targetSdk = 36`
- [ ] Mark the app as standalone with `com.google.android.wearable.standalone`
- [ ] Add Compose for Wear OS Material 3 and Wear Navigation
- [ ] Add OkHttp, kotlinx.serialization and Coil
- [ ] Add DataStore for cached responses
- [ ] Encrypt the API token with Android Keystore
- [ ] Add Firebase Messaging and `google-services.json`
- [ ] Add `watchface-complications-data-source-ktx`
- [ ] Add `wear-remote-interactions` for Open on Phone
- [ ] Create round small and large emulators

### Features

- [ ] Pairing code and waiting screen
- [ ] Home with unread badge
- [ ] Updates with paging and read state
- [ ] Update Detail with thumbnail
- [ ] Open on Phone with a clear failure message
- [ ] My Creators
- [ ] Quick Actions with confirmation before Unfollow
- [ ] Trending categories and creators
- [ ] Notification settings
- [ ] Daily Summary
- [ ] Unread-count complication
- [ ] Push notifications that open the correct update
- [ ] Loading, empty, offline, expired sign-in and error states
- [ ] Show cached updates when offline

### Quality

- [ ] English and Arabic right-to-left layout
- [ ] Rotary crown scrolling
- [ ] TalkBack labels
- [ ] Small and large round screens
- [ ] Complication updates after push and during normal refresh
- [ ] Battery check with background refresh enabled
- [ ] Test on a real Wear OS watch

---

## Phase 3: Apple Watch app

**Estimate:** 3–4 weeks.

This phase must be built on a Mac. Create the project on the Mac, push it to the private repository, then pull it into `D:\Flutter\followly-watch\apple-watch` on Windows.

### Project setup

In Xcode, choose **File → New → Project → watchOS → App**, then select a **Watch-only App**.

- [ ] Create the project with bundle ID `com.followly.watch`
- [ ] Set the deployment target to watchOS 10
- [ ] Add a WidgetKit extension for complications
- [ ] Enable Push Notifications, Background Modes, App Groups and Keychain Sharing
- [ ] Create the APNs key and add it to the Laravel production secrets
- [ ] Use `URLSession` and `Codable` for the API
- [ ] Use `AsyncImage` or a small image cache for avatars
- [ ] Store the API token in Keychain
- [ ] Add 41 mm, 45 mm and Ultra simulators

### Features

- [ ] Pairing code and waiting screen
- [ ] Home with unread badge
- [ ] Updates with paging and read state
- [ ] Update Detail with thumbnail
- [ ] Open on Phone with Handoff and clear instructions
- [ ] My Creators
- [ ] Quick Actions with confirmation before Unfollow
- [ ] Trending categories and creators
- [ ] Notification settings
- [ ] Daily Summary
- [ ] Circular, corner, inline and rectangular complications
- [ ] Push notifications that open the correct update
- [ ] Background refresh and WidgetKit reload
- [ ] Loading, empty, offline, expired sign-in and error states
- [ ] Show cached updates when offline

### Quality

- [ ] Adapt the circular mockups to rounded-rectangle screens
- [ ] English and Arabic right-to-left layout
- [ ] Digital Crown scrolling
- [ ] VoiceOver labels
- [ ] Test on smallest and largest supported screens
- [ ] Confirm the app works when the iPhone is away and the watch has internet
- [ ] Battery check with background refresh enabled
- [ ] Test on a real Apple Watch

---

## Phase 4: Flutter phone companion and Huawei bridge

**Estimate:** 2–3 weeks.

The first task is a short technical test. Do not build the full Huawei apps until it passes.

### Project setup

```powershell
Set-Location D:\Flutter\followly-watch
flutter create --org com.followly --project-name followly_companion --platforms=android,ios phone-companion
```

Rename the Android and iOS application IDs to `com.followly.companion` if Flutter generates a different one.

- [ ] Create the Flutter project
- [ ] Add `http`, `flutter_secure_storage`, `firebase_messaging`, `flutter_local_notifications`, `url_launcher` and `huawei_push`
- [ ] Add the Huawei Maven repository and the Wear Engine Android SDK
- [ ] Add a small Android `MethodChannel` for Wear Engine
- [ ] Add `agconnect-services.json` and the release signing fingerprint

### Huawei technical test

- [ ] Send a message from a Huawei Watch app to the Android companion and back
- [ ] Send a message from a Huawei GT app to the Android companion and back
- [ ] Confirm the bridge answers while the phone app is in the background
- [ ] Confirm it works on Android 15 and newer; Huawei's docs currently list Android 6.0–14.0 for non-Huawei phones
- [ ] Measure the message size limit and response time
- [ ] Test whether a full wearable can make direct HTTPS calls
- [ ] Test whether Wear Engine is available on iPhone for both watch types
- [ ] Finalize the supported Huawei model list
- [ ] Decide go, reduce scope, or stop for each Huawei watch type

### Companion features

- [ ] Email and password sign-in
- [ ] Secure token storage
- [ ] Connected Huawei watch status
- [ ] Instructions for installing Huawei Health and pairing the watch
- [ ] Register FCM or Huawei Push token with the backend
- [ ] Show new-post phone notifications so Huawei Health can mirror them to the watch
- [ ] Handle watch requests through the Laravel API
- [ ] Cache the latest bridge response
- [ ] Open posts through a tappable phone notification, to avoid Android background launch limits
- [ ] Sign out and revoke the token
- [ ] Add English and Arabic

### Bridge protocol

Document this in `shared/bridge-protocol.md`.

- [ ] Include a protocol version and request ID in every message
- [ ] Define `get_home`, `get_updates`, `get_detail`, `get_summary`
- [ ] Define `get_creators`, `get_trending`, `get_settings`
- [ ] Define `mark_read`, `update_creator`, `unfollow`, `update_settings`, `open_on_phone`
- [ ] Define `not_signed_in`, `offline`, `not_found` and `server_error`
- [ ] Keep responses small enough for the measured Wear Engine limit
- [ ] Ensure only the signed Followly watch apps can use the bridge

### Quality

- [ ] Test on non-Huawei Android with Huawei Health
- [ ] Test on Huawei phone without Google services
- [ ] Test phone restart, app force-stop and Bluetooth reconnect
- [ ] Confirm battery optimization guidance is clear for users
- [ ] Add iPhone support only if the technical test passes

---

## Phase 5: Huawei Watch app (ArkTS)

**Estimate:** 2–3 weeks.

### Project setup

In DevEco Studio, create an **Empty Ability** project using the ArkTS Stage model, select **Wearable**, and save to `D:\Flutter\followly-watch\huawei-watch`.

- [ ] Create the project with bundle name `com.followly.huawei`
- [ ] Configure automatic signing with the Huawei account
- [ ] Add Wear Engine messaging and the companion's package and signing fingerprint
- [ ] Add a small bridge client that follows `shared/bridge-protocol.md`
- [ ] Add local storage for the latest response
- [ ] Create a 466 × 466 round simulator or test device profile

### Features

- [ ] Companion-required and not-signed-in screens
- [ ] Home with unread badge
- [ ] Updates with paging and read state
- [ ] Update Detail with thumbnail, if image transfer passes the size test
- [ ] Open on Phone through the companion
- [ ] My Creators
- [ ] Quick Actions with confirmation before Unfollow
- [ ] Trending categories and creators
- [ ] Notification settings
- [ ] Daily Summary
- [ ] Phone unavailable, offline and error states
- [ ] Show cached updates when the phone is unavailable

### Quality

- [ ] `ArcList` or suitable circular list behavior
- [ ] English and Arabic right-to-left layout
- [ ] Crown scrolling
- [ ] Accessibility labels
- [ ] Test each model on the final supported list
- [ ] Test Bluetooth disconnect and reconnect
- [ ] Battery check on a real watch

---

## Phase 6: Huawei Watch GT app (Lite JS)

**Estimate:** 2–3 weeks.

### Project setup

In DevEco Studio, create a **Lite Wearable** JS project and save to `D:\Flutter\followly-watch\huawei-gt`.

- [ ] Create the project with bundle name `com.followly.huawei.gt`
- [ ] Use ES5-compatible JavaScript only
- [ ] Add the lite Wear Engine messaging SDK and the companion's package and signing fingerprint
- [ ] Install HUAWEI DevEco Assistant on the phone for device installation and logs
- [ ] Test the project on a real GT watch before building screens

### Features

- [ ] Companion-required and not-signed-in screens
- [ ] Simple menu with unread count
- [ ] Latest 10 updates as text
- [ ] Update Detail as text
- [ ] Mark as read
- [ ] Open on Phone through the companion
- [ ] Daily Summary as text
- [ ] Phone unavailable, offline and error states

### Limits to respect

Community testing reports these limits. Confirm them during Phase 4.

- [ ] App package under 10 MB
- [ ] Each page under 48 KB
- [ ] Fits in about 1 MB of app memory
- [ ] Only small bundled icons; no remote images
- [ ] Short bridge messages only
- [ ] App state restores safely after restart

### Quality

- [ ] Confirm Arabic text and right-to-left display, or ship English first on GT
- [ ] Test each model on the final supported list
- [ ] Test Bluetooth disconnect and reconnect
- [ ] Battery check on a real watch

---

## Phase 7: Release and monitoring

**Estimate:** 2 weeks, plus store review time.

### Before submission

- [ ] Update the privacy policy for watch data, device tokens and push notifications
- [ ] Complete Apple privacy details and Google Data Safety
- [ ] Create a demo account and written pairing steps for store reviewers
- [ ] Prepare icons, screenshots and descriptions for each store in English and Arabic
- [ ] Add production Laravel secrets for FCM, APNs and Huawei Push
- [ ] Run the production migrations and confirm the queue worker handles push jobs
- [ ] Publish a Connect Your Watch help page, including Huawei requirements

### Beta testing

- [ ] Wear OS closed test through Google Play
- [ ] Apple Watch beta through TestFlight
- [ ] Huawei test release through AppGallery Connect
- [ ] Fix sign-in, push, duplicate alert and battery issues from beta testing

### Store release

- [ ] Publish the Wear OS app on Google Play, targeting API 35 or newer
- [ ] Publish the Apple Watch app on the App Store
- [ ] Publish the phone companion on Google Play and AppGallery
- [ ] Publish the Huawei Watch app on AppGallery
- [ ] Publish the Huawei GT app on AppGallery

### After launch

- [ ] Monitor API errors and response times
- [ ] Monitor failed push delivery and queue delays
- [ ] Track active devices by platform
- [ ] Review crash reports weekly for the first month
- [ ] Decide what to build next from usage data

---

## Main risks

| Risk | Impact | Response |
|---|---|---|
| Wear Engine approval is delayed | Huawei phases wait | Apply in Phase 0 and continue Tier 1 work |
| Companion bridge is unreliable in the background | Huawei apps are not dependable | Test before building Huawei screens; reduce or stop if needed |
| Wear Engine does not work on new Android versions | Some Huawei users cannot use the app | Test Android 15+ and publish a clear support list |
| No Mac is available | Apple Watch work is blocked | Arrange a Mac or cloud Mac in Phase 0 |
| Google Play new-account testing rule | Release is delayed by at least 14 days | Check account type early and recruit testers |
| GT memory limits | Reduced GT experience | Keep GT text-first and test on a real device early |
| Duplicate watch and phone alerts | Users may turn notifications off | Test routing in Phase 1 and beta |

## Reference sources (checked 8 November 2026)

- [Huawei wearable development](https://developer.huawei.com/consumer/en/multidevice/wearables/get-started/)
- [Huawei Wear Engine service introduction](https://developer.huawei.com/consumer/en/doc/connectivity-Guides/service-introduction-0000000000018585)
- [Huawei Wear Engine getting started](https://developer.huawei.com/consumer/en/doc/connectivity-Guides/dev-process-0000001051058039)
- [Huawei full wearable practices](https://developer.huawei.com/consumer/en/doc/best-practices/bpta-smartwatch)
- [Community Huawei platform field test](https://github.com/gentslava/Home-Assistant-HarmonyOS-Next/blob/master/docs/platform-constraints.md)
- [Google Play target API requirements](https://support.google.com/googleplay/android-developer/answer/11926878?hl=en)
- [Flutter on Wear OS status](https://verygood.ventures/blog/building-wear-os-apps-with-flutter-a-very-good-guide/)
- [Unofficial Flutter on watchOS project](https://itnext.io/yes-you-can-build-apple-watch-apps-with-flutter-now-909c3dc84804)
