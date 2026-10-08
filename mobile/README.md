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
| Plant ID | `/plant-id` | Identify a plant from a photo via the PlantNet API |

Plus an Android home-screen widget showing dashboard stats (`home_widget`).

## Stack

`flutter_riverpod` for state, `dio` for HTTP, `go_router` for navigation, `flutter_secure_storage` for tokens, `image_picker` for photos, `infinite_scroll_pagination` for paginated lists.

## Getting Started

```bash
flutter pub get
flutter run
```

Point the app at your backend in `lib/core/api/api_client.dart` (base URL).

### Build flags

The plant identification screen needs a [PlantNet](https://my.plantnet.org/) API key. Without it the screen is inert:

```bash
flutter run --dart-define=PLANTNET_API_KEY=your_key
```

## Layout

```
lib/
├── core/
│   ├── api/           # Dio client, endpoints, error handling, logging
│   ├── models/        # Store, product, order, dashboard, notification, workspace
│   ├── providers/     # Riverpod providers
│   ├── auth/          # Secure token storage
│   └── widgets/       # Home-screen widget
├── features/
│   ├── auth/ dashboard/ stock/ orders/ pick_list/ notifications/ team/
│   └── plant_id/      # Screen, PlantNet service, result/organ widgets
├── shared/            # Theme + shared widgets
└── app.dart / main.dart
```
