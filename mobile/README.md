# MyStores — Mobile App

Flutter (Android) companion app for the [MyStores](../README.md) multi-marketplace store manager. It talks to the same Laravel REST API as the web frontend — there is no mobile-specific backend.

## Features

| Screen | Route | Notes |
|---|---|---|
| Login | `/login` | Sanctum bearer token, stored via `flutter_secure_storage` |
| Dashboard | `/dashboard` | Stats cards + charts (`fl_chart`), mirrors the web dashboard |
| Orders | `/orders` | Paginated order list with infinite scroll |
| Stock | `/stock` | Browse stock per store/platform |
| Pick list | `/pick-list/today`, `/pick-list/pending` | Today's and pending pick lists |
| Notifications | `/notifications` | In-app notification feed |
| Team | `/team` | Workspace members |

Plus an Android home-screen widget showing dashboard stats (`home_widget`).

## Stack

`flutter_riverpod` for state, `dio` for HTTP, `go_router` for navigation, `flutter_secure_storage` for tokens, `image_picker` for pick-list photos, `infinite_scroll_pagination` for paginated lists.

## Getting Started

```bash
flutter pub get
flutter run
```

Point the app at your backend in `lib/core/api/api_client.dart` (base URL).

## Android Minimum SDK
The Android minimum SDK is raised to 21 (`minSdkVersion = 21`, Android 5.0 Lollipop) to satisfy the Google Mobile Ads SDK (`google_mobile_ads`) dependency.

## Push & Ads Manual Verification Checklist
1. **Install & Permissions**: Launch app on device or emulator (Android 13+); verify the system notification permission dialog prompts once on startup.
2. **Device Registration**: Log in with an authorized user; verify that `POST /api/devices` receives the OneSignal player ID and returns the caller workspace's `public_id`.
3. **External ID Binding**: Verify the app sets external ID on OneSignal to the workspace `public_id`.
4. **Test Broadcast**: Send a test broadcast from the OneSignal dashboard with `category` set (e.g. `order_new` or `picklist_comment` with `kind: pending`). Tap the notification on device; verify it deep links to `/orders` or `/pick-list/pending` and refreshes the unread count badge.
5. **Switch Store / Logout**: Log out explicitly or tap "Switch Store"; verify `DELETE /api/devices/{player_id}` is called *before* the session token is removed from storage, and local OneSignal session is detached.
6. **Involuntary Logout**: Trigger a 401 response; verify the session is cleared locally without calling `DELETE /devices`.
7. **Banner Ad**: Check `/dashboard`; verify the banner ad renders above bottom navigation. Verify no ads appear on `/orders`, `/stock`, `/pick-list`, or `/team`.
8. **Banner Fallback**: When an ad fails to load, verify the 50dp reserved height is preserved and marked with a subtle divider without content reflow.
9. **Interstitial Throttle**: Switch between bottom navigation tabs; verify interstitials appear at most once in 5 minutes, never on app start or first navigation, and never immediately after photo capture or stock update.
