# Spec: AdMob advertising and OneSignal push notifications in the mobile app

Status: ready-for-agent

This spec is the complete problem statement and decision record. Implementation is
broken into six tickets under `issues/`, numbered in dependency order; work them
in frontier order rather than strictly top to bottom.

## Problem Statement

The MyStores Flutter companion app gives a store operator live visibility into
orders, stock, and pick lists, but only while they have the app open and are
staring at it. There is no way for an operator to be told that a new order came
in, that a token has gone stale and a store is about to stop syncing, or that a
teammate commented on a pick list — unless they happen to reopen the app and see
the in-app badge.

The app has a notification inbox, but it is pull-only. It fetches on screen
mount and on manual refresh. An operator running three stores cannot be expected
to poll it, and the badge is invisible to them until they unlock the phone and
open the app.

Separately, the app generates no revenue. It is distributed through the same
channel as the paid backend, and as a free companion to a self-hosted product it
has no monetisation path at all. The owner wants to change both of these at once
and, explicitly, without over-engineering either.

## Solution

The mobile app gains two things.

**Push notifications.** A logged-in device subscribes to OneSignal, bound to the
workspace it belongs to. The operator composes messages by hand in the OneSignal
dashboard and they arrive on every registered device for that workspace. Tapping
one opens the app at the screen that message is about.

**Advertising.** A single AdMob banner sits above the Dashboard's bottom
navigation, and an interstitial is shown on bottom-nav tab changes, throttled.
This funds the free companion without putting ads in the pick-list or stock
verification flows where the app is actually used for work.

The design is deliberately shaped around the fact that MyStores is
**self-hosted**: many independent installations each run the same code against
their own database, and each one's `workspaces` table starts numbering at 1.
The identity used to target pushes therefore has to be unique *outside* any one
installation, and the app itself must never decide which workspace it belongs to.

## User Stories

1. As a store operator, I want to receive a push on my phone when the OneSignal
   dashboard broadcasts a message to my workspace, so that I learn about it
   without reopening the app.
2. As a store operator, I want the push to be delivered to my device even when
   the app is closed, so that I am not required to be actively using MyStores.
3. As a store operator, I want a push to open directly at the screen it is about
   rather than at a blank app, so that I do not have to navigate there myself.
4. As a store operator, I want a push about an order to land on the Orders
   screen, so that I can see the order it referred to.
5. As a store operator, I want a push about a pick-list comment to land on the
   correct pick-list screen, so that I can read the comment in context.
6. As a store operator, I want a push about team activity to land on the Team
   screen, so that I can see who changed what.
7. As a store operator, I want a push about a store needing reauthorization to
   land on the Dashboard, so that I can see the affected store's status.
8. As a store operator, I want to be asked once for notification permission, so
   that I am not repeatedly prompted and can grant it in a single tap.
9. As a store operator, I want to be able to open system settings from the
   permission flow if I previously denied notifications, so that I can change my
   mind without reinstalling.
10. As a store operator, I want the notification inbox and unread badge to
    refresh when a push arrives or is tapped, so that the badge is not stale
    after I have been caught up elsewhere.
11. As a store operator who uses several self-hosted installations, I want
    pushes for one installation to never reach a device registered to another,
    so that I am never shown another tenant's order information.
12. As a store operator who switches between store servers, I want the previous
    server's pushes to stop immediately when I log out or switch, so that I do
    not keep receiving messages about a shop I no longer have open.
13. As a store operator whose login token has expired, I want the app to stop
    claiming my device is registered for that session, so that my account and my
    device are not left loosely associated.
14. As a store operator, I want logging in again to re-register my device
    against the correct workspace, so that a token expiry does not permanently
    break push delivery.
15. As a store operator, I want push registration to fail silently rather than
    blocking the app on startup, so that an unreachable server never stops me
    from opening MyStores.
16. As a store operator, I want push to work even though the app is pointed at
    whatever server I connected to, so that I do not have to configure anything
    extra per install beyond what connect-store already asks.
17. As an app user, I want a banner ad on the Dashboard, so that I can use the
    app for free.
18. As an app user, I want the banner ad to appear only on the Dashboard and not
    on the Orders, Stock, or pick-list screens, so that ads never sit under the
    screens I use to do actual work.
19. As an app user, I want the banner's space to stay reserved even when no ad
    fills, so that the Dashboard does not visibly jump while I am scrolling.
20. As an app user, I want interstitial ads to be shown at most once every five
    minutes, so that the app does not feel like it is nagging me.
21. As an app user, I want interstitial ads to be triggered only by my own
    deliberate navigation between bottom tabs, so that I am never interrupted
    mid-task.
22. As an app user, I want no ad on app launch, so that opening MyStores is
    immediate.
23. As an app user, I want no ad immediately after I have captured a pick-list
    photo or submitted a stock edit, so that my confirmation of a completed
    action is never delayed by a full-screen ad.
24. As a developer, I want release builds to require an explicit flag to use
    live ad unit IDs, so that a debug build can never earn a Google policy strike
    from accidental clicks on my own ads.
25. As a developer, I want every external credential supplied at build time
    rather than hardcoded, so that no secret is committed and no build breaks
    before credentials exist.
26. As a developer, I want the app's push lifecycle covered by an automated
    test, so that the register/logout ordering cannot silently regress into a
    cross-tenant leak.
27. As a developer, I want the tenant-scoping logic covered by backend tests at
    the API boundary, so that the security property is verified where it is
    enforced.
28. As a self-hoster, I want the minimum supported Android version raised only
    as far as the ad SDK strictly requires, so that the fewest devices are
    dropped.
29. As a self-hoster, I want that minimum-version change written down, so that
    I understand why an older handset can no longer install an update.
30. As a maintainer, I want the vocabulary distinguishing an in-app
    notification from a device push to be written down, so that the two are not
    conflated in future work.

## Implementation Decisions

### Vocabulary

The domain glossary distinguishes three terms and the implementation must use
them consistently:

- **Notification** — a row in the backend's notifications table, scoped to one
  user, rendered in the in-app inbox. Pull-based.
- **Push Notification** — a OneSignal message. It is *transport for* a
  Notification, not a Notification itself. Any push originating from a domain
  event is backed by exactly one Notification row.
- **Broadcast** — a push composed by hand in the OneSignal dashboard, outside any
  domain event. Has no backing Notification row. This is the only kind of push
  the product sends in this spec.

Consequence: the payload shape of a Broadcast mirrors the Notification `data`
shape (`category`, `title`, `message`, `action_type`, and where relevant
`kind`), so a deep link can be resolved by the same mapping the inbox already
uses. A Broadcast will not appear in the inbox or the unread count, and must not
pretend to.

### Tenant identity

Workspace integer ids are not unique across self-hosted installations. Using one
as a push target would let one installation's messages reach another's devices.

A `workspaces.public_id` UUID column is added. It is the Workspace's identity
outside its own database and exists specifically so an integer id is unambiguous
when two installations exist. It is assigned on create via a model hook and
backfilled by migration for existing rows.

The hook must tolerate the column not yet existing, because a historical
migration creates Workspaces through this model before the column is added. A
naive hook breaks that migration on a fresh install. The check is memoised so it
runs once.

This is recorded as ADR 0001, which also documents the two rejected
alternatives (per-install OneSignal app id, and install-URL-prefixed keys).

### Device registration

A `device_registrations` table binds an OneSignal player id to exactly one
workspace and one user, with a unique constraint on the player id, and cascading
deletion from `workspaces`.

The client never names a workspace. The API resolves the caller's **Primary
Workspace** — the same server-side resolution every existing controller uses —
so a device can only be registered against a workspace the caller already
belongs to. Registering is upsert-on-player-id, which means re-registering moves
a device rather than duplicating it.

`POST /devices` returns the workspace `public_id`, which the app then uses as
its OneSignal external id. The app therefore learns which workspace it is in and
never has to work it out.

Both routes are write endpoints and carry the named `mutation` throttle, matching
every other write route in the API.

Route ordering matters: the literal `/devices` must be declared above any
wildcard under the same prefix.

### Push lifecycle on the client

Ordering is the security-critical part of this feature and is the single new
test seam in the codebase:

- On app start, the OneSignal SDK is initialised and the Android 13+ notification
  permission is requested once. Neither is gated on authentication.
- When the user becomes authenticated, the app registers the player id via
  `POST /devices`, receives the workspace `public_id`, and calls
  `OneSignal.login()` with it.
- On an explicit user logout, the app calls `DELETE /devices` *first*, while the
  token is still valid, then clears the local session and calls
  `OneSignal.logout()`.
- On an involuntary local logout — the 401 interceptor path, which is
  documented as never making network calls — the app calls `OneSignal.logout()`
  only, and does **not** call `DELETE /devices`. The stale row is harmless: the
  next successful login upserts by player id and corrects it.
- Any push registration failure is swallowed and logged. It must never block
  startup or login.

The `logout()` path is the one that must not regress. If `DELETE /devices` is
lost there, a user who switches store servers keeps receiving the previous
tenant's pushes, and no backend test can detect it.

### Deep linking

The category-to-route mapping that the notification inbox already performs is
extracted into one shared helper used by both the inbox and the push tap
handler. There must be exactly one implementation of that mapping; two copies
will drift.

Tapping a push refreshes the unread count.

### Advertising

- AdMob banner, Dashboard screen only, pinned above the shell's bottom navigation
  so it stacks above the existing navigation bar.
- On ad-load failure the banner's height stays reserved, with a thin divider so
  the space reads as intentional. The Dashboard must not reflow.
- Interstitial, shown on bottom-nav tab change only, throttled to one per five
  minutes.
- No app-open ad. No native ads. No rewarded ads.
- Interstitials are suppressed when the navigation was initiated by a push tap,
  and immediately after a pick-list photo capture or a stock/price edit.

### Build-time configuration

Ad unit IDs default to Google's published test IDs. Live IDs are only used when
an explicit build flag is set. The AdMob application ID is injected into the
Android manifest via a Gradle placeholder defaulting to Google's test
application ID.

Nothing is hardcoded. No credential is committed.

### Platform and dependency scope

Android only. There is no iOS target in this repository and creating one is
explicitly out of scope. Both SDKs are cross-platform, so iOS can be added later
without reworking this.

The Android minimum SDK is raised to satisfy the ad SDK. The reason is noted in
the mobile app's README.

Push delivery is one-way in this spec: nothing in the backend sends a OneSignal
message. There is no OneSignal REST key in configuration and no empty service
class waiting for one.

## Testing Decisions

A good test here asserts externally observable behaviour — what a request
returns, what state a device ends up in, what order calls happened in — and
never asserts on internal structure, private fields, or the fact that a
particular method was invoked with a particular implementation detail.

### Backend: the HTTP API seam (existing)

All backend coverage goes through the API boundary, which is the highest seam
available and the point at which tenant scoping is actually enforced. This is
the established pattern in this repo; no new backend seam is introduced.

Covered:

- Registering a device returns the caller's workspace `public_id`, and persists
  the player id against that workspace.
- A `player_id` is required and is length-bounded; malformed input is rejected
  as a validation error, not stored.
- Re-registering the same player id moves the device to the new caller's
  workspace and never creates a second row.
- A user who belongs to several workspaces is registered against their own
  workspace, not one they were merely invited to.
- Unregistering requires authentication.
- A user cannot unregister another user's player id — it is a no-op, and the
  other device remains registered.
- Deleting a workspace removes its device rows.
- Both write routes carry the named mutation throttle.

Prior art: `WorkspaceAccessTest` for the workspace-scoping style and the
assertion style, and the existing notification feature tests for the API-boundary
pattern.

### Client: the auth-lifecycle seam (new, the only one)

One new seam is introduced, at the highest point it can sit: a narrow push
interface — register, unregister, login, external id, logout — with a fake, so
the auth state machine can be driven through a Riverpod container and the
sequence of calls asserted.

This seam exists because the ordering is unobservable from outside the SDK and
has a cross-tenant failure mode if it regresses.

Covered:

- Becoming authenticated registers the player id and then sets the external id
  from the returned workspace `public_id`.
- Explicit logout deletes the device *before* clearing the local session, and
  then logs out of OneSignal.
- Involuntary local logout performs the local OneSignal logout but makes no
  network call and does not delete the device.
- Re-authenticating after an involuntary logout re-registers and corrects the
  binding.
- A registration failure leaves the user logged in and the app usable.

Prior art: the widget tests already in the mobile test directory for assertion
style. There is no existing provider-test pattern in this app, so this establishes
the first; it should stay hand-written and synchronous, consistent with the
hand-written `StateNotifier` providers it exercises.

### Not covered by automated tests

The ad units are verified by hand. Asserting the banner's reserved height or the
interstitial's throttle would require faking the ad SDK behind its own
abstraction, which was ruled out as over-engineering. The failure mode being
guarded against is a layout jump, which is visible on a device in one second.

The manual checklist goes in the mobile app's README and covers: install, grant
permission, confirm the player id reaches `POST /devices` (visible in the app's
existing request log), send a test Broadcast from the OneSignal dashboard, tap
it and confirm the deep link lands correctly, switch store and confirm the device
row is gone.

## Out of Scope

- **iOS.** No iOS target exists in this repository. Creating one, adding a
  Podfile, and configuring APNs are all excluded.
- **Automated push dispatch.** No backend code sends a OneSignal message. No
  event in the product triggers a push. Domain-event-driven pushes, and the
  Notification-row backing that requires, are a later spec.
- **OneSignal REST credentials.** No API key is added to configuration or to the
  environment example. No stub service class is created.
- **Consent management.** No UMP/GDPR consent flow. If the audience later
  requires it, it is added as a separate gate ahead of ad initialisation, not a
  rewrite.
- **App-open, native, and rewarded ad formats.** Only a banner and an
  interstitial.
- **Ad-free tier or subscription gating.** The backend has no concept of a plan
  or premium user, and none is introduced.
- **Per-install OneSignal apps.** All installations are assumed to point at one
  shared OneSignal application, which is what makes the `public_id` requirement
  real.
- **Push from the React admin SPA.** Only the Flutter companion registers for
  push in this spec.
- **Automated coverage for ad behaviour.** See Testing Decisions.
- **Raising the Flutter or Dart SDK constraints** beyond what the two new
  dependencies require.

## Further Notes

- **Pre-existing work in the tree.** Backend code for this feature was written
  during a design discussion before it was authorised, and is sitting
  uncommitted in the working tree: a migration, a device model, a device
  controller, a workspace model hook, two edited backend files, and a backend
  test suite. It implements the decisions above and its tests pass. An
  implementer should treat it as a starting draft to be reviewed against this
  spec, not as authoritative — particularly the workspace hook, whose
  column-existence guard is subtle and easy to simplify into something that
  breaks fresh installs.
- **Rate limiters are named, not numeric.** Any new write endpoint must carry a
  named throttle. Numeric limits are not used in this API.
- **Queued work is fire-and-forget.** Not directly relevant here, since nothing
  in this spec queues anything, but it constrains any later attempt to add
  automated dispatch.
- **Route ordering is load-bearing.** Literal paths must be declared above
  wildcard siblings.
- **Tests run in memory.** Backend tests use in-memory SQLite and make no network
  calls, so nothing in this spec requires credentials or a live Google account
  to test.
