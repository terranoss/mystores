# MyStores — Backend

Laravel 12 REST API for [MyStores](../README.md), the multi-marketplace (Shopee + Lazada) store manager. Serves the React SPA at the app root and the Flutter mobile app's API at `/api/`.

Stack: PHP ^8.2, Laravel Sanctum (API tokens), `ecomphp/shopee-php` SDK, `dompdf` (shipping labels), MySQL (production) / SQLite (local dev), database queue.

## Commands

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite     # local dev
php artisan migrate

php artisan serve                   # API + SPA
php artisan queue:work --tries=1 --timeout=0
php artisan schedule:work           # run cron jobs locally
php artisan test                    # 185 tests
composer dev                        # serve + queue + Vite together
```

## Structure

```
app/
├── Console/Commands/   # Scheduled commands (token refresh, product/order sync, PII purge, photo cleanup)
├── Exceptions/         # ProductAuthorizationException, StoreNotActiveException
├── Helpers/            # Shopee response parsing
├── Http/
│   ├── Controllers/Api/  # Auth, Dashboard, Products, Orders, Promotions, PickList,
│   │                     # Shopee/Lazada auth + orders, Webhooks, Workspace, Notifications
│   ├── Middleware/       # SecurityHeaders
│   ├── Requests/         # FormRequest validation
│   └── Resources/
├── Jobs/               # Queued store/product/order sync and cross-store stock updates
├── Models/             # Store, Token, OAuthState, Product(+Variant), Order(+Item),
│                       # InventoryLog, Workspace*, PickList*, Notification, User
├── Notifications/      # Order new/status/urgent, pick-list comment, team activity, store reauth
└── Services/           # ShopeeService, LazadaService (+Promotion/Order),
                        # ProductService, OrderService, LogisticsService,
                        # PackingListPdfService, PickListPhoto*, Workspace*, Notification*
routes/                 # api.php (REST), web.php (SPA fallback), console.php (scheduler)
config/                 # shopee.php, lazada.php, cors.php
tests/                  # Feature (29 files) + Unit (2 files)
```

## Notes

- Registration is disabled — create accounts via `php artisan tinker`.
- Set `FRONTEND_URL` to the web origin; it drives CORS and OAuth redirects.
- Full endpoint list: [README API Overview](../README.md#api-overview).
- The former ERP/POS connector (`pos.melur.com`) was removed; `pos_inventories` is dropped by migration `2026_10_04_000001`.
