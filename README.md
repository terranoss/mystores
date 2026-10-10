# MyStores

> **Multi-marketplace store management** — manage products, stock, pricing, orders, fulfillment, pick lists, and promotions across your **Shopee** and **Lazada** stores from a single interface.

[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![React](https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=white)](https://react.dev)
[![TypeScript](https://img.shields.io/badge/TypeScript-6-3178C6?logo=typescript&logoColor=white)](https://www.typescriptlang.org)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![Flutter](https://img.shields.io/badge/Flutter-3-02569B?logo=flutter&logoColor=white)](https://flutter.dev)
[![Tests](https://img.shields.io/badge/tests-185%20passing-22c55e)](backend/tests)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

**Live:** [mystores.melur.com](https://mystores.melur.com)

---

## Overview

MyStores connects to your Shopee and Lazada stores via their open platform APIs, providing a unified multi-marketplace management platform:

### Core Features

- **Store linking** — OAuth 2.0 for both platforms (Shopee & Lazada), encrypted tokens at rest (AES-256-CBC), real-time health badges, on-demand + scheduled token refresh, reauth / disconnect / unlink, and Shopee vacation mode toggle.
- **Products & inventory** — Cross-store stock and price editing (SKU-grouped, variant support), bulk "set all stores" matching SKU, `needs_push` tracking for pending updates, queued cross-store sync (`UpdateShopeeStockJob`), and complete inventory audit logs.
- **Orders & fulfillment** — Unified + per-platform order views, cancellation with reason codes, shipping arrangement, single and bulk shipping-label PDFs (`dompdf` + `pdf-lib`), and Lazada-specific pack / ready-to-ship / bulk AWB assignment.
- **Pick lists** — Today's and pending pick lists, photo completions with server compression, team comments with read tracking, and hourly cleanup of unneeded photos.
- **Promotions** — Normalized promotions dashboard supporting Shopee discounts and vouchers, plus Lazada seller vouchers (creation, activation/deactivation, and SKU assignment).
- **Workspaces & team** — Multi-tenant team workspaces with member invites, role-scoped activity logging, in-app notifications with unread tracking, and owner-only marketplace credentials.
- **Dashboard** — Live operational statistics, low-stock alert banners, pick-list counts, and store connection health monitoring.
- **Mobile app (Flutter)** — Companion app covering dashboard, stock, orders, pick lists, notifications, and team, plus an Android home-screen widget.
- **Security & ops** — Laravel Sanctum token auth with expiration, HMAC webhook verification, strict multi-tenant workspace isolation, mutation rate limits, security headers, 9 scheduled background cron jobs, and 185 passing automated tests.

Built with a **Laravel 12** backend (REST API), a **React 19 + TypeScript** SPA frontend, and a **Flutter** mobile app, deployed on cPanel hosting.

---

## Architecture

```
┌──────────────────────────────────────────────────────────┐
│                        Clients                           │
│   React 19 SPA (web)  ·  Flutter mobile app (Android)    │
│        Hosted at mystores.melur.com (served by Laravel)  │
└──────────────────┬───────────────────────────────────────┘
                   │  HTTP/JSON (Bearer Token, Sanctum)
                   ▼
┌──────────────────────────────────────────────────────────┐
│                     Backend (REST API)                    │
│      Laravel 12 · Sanctum Auth · MySQL / SQLite          │
│      Domain: mystores.melur.com/api/                     │
└──────┬──────────────────────────────┬────────────────────┘
       │                              │
       ▼                              ▼
┌──────────────┐           ┌──────────────────────┐
│   Shopee     │           │       Lazada         │
│  Open API    │◄─────────►│  Open Platform API   │
│ (ecomphp/    │           └──────────────────────┘
│  shopee-php) │
└──────┬───────┘
       │
       ▼
┌──────────────────────────────────────────────────────────┐
│  Laravel Queue (database) · Scheduler (cron)              │
└──────────────────────────────────────────────────────────┘
```

### Key Design Decisions

- **Monorepo structure** — `backend/` (Laravel), `frontend/` (React), and `mobile/` (Flutter) in one repository
- **Multi-marketplace services** — `ShopeeService` and `LazadaService` wrap their respective SDKs/APIs behind a shared product/order model; `products.platform` and `shopee_stores.platform` distinguish records
- **API token auth** via Laravel Sanctum with configurable 30-day token expiry
- **Encrypted token storage** — Shopee/Lazada OAuth access/refresh tokens are encrypted at rest using Laravel's AES-256-CBC encryption
- **Queued cross-store sync** — stock updates dispatch `UpdateShopeeStockJob` asynchronously via `dispatch()` for non-blocking propagation across stores
- **SKU-based cross-store updates** — when stock changes for a product with a SKU, all matching products/variants across stores are automatically updated
- **Inventory logging** — every stock change is recorded in `inventory_logs` with before/after values and source tracking
- **Automated token refresh** — API calls refresh near-expiry access tokens on demand; scheduled commands refresh tokens as a backup (Shopee every 15 minutes, Lazada hourly)
- **OAuth replay protection** — state parameter uses `lockForUpdate()` + `consumed_at` to prevent reuse
- **Convention over configuration** — follows Laravel best practices throughout

---

## Tech Stack

### Backend (`backend/`)
| Technology | Version |
|---|---|
| PHP | ^8.2 |
| Laravel | ^12.0 |
| Laravel Sanctum | ^4.3 (API auth) |
| Shopee PHP SDK | ^1.6 (`ecomphp/shopee-php`) |
| dompdf | ^3.1 (packing/shipping label PDFs) |
| Laravel Tinker | ^2.10 |
| Database | MySQL (production) / SQLite (local dev) |
| Queue | Database-driven queue |

### Frontend (`frontend/`)
| Technology | Version |
|---|---|
| React | ^19.2 |
| TypeScript | ~6.0 |
| Vite | ^8.0 |
| Tailwind CSS | ^4.3 |
| React Router | ^7.18 |
| Axios | ^1.18 |
| pdf-lib | ^1.17 (client-side label printing) |

### Mobile (`mobile/`)
| Technology | Purpose |
|---|---|
| Flutter / Dart (>=3.4.0) | App framework (v1.0.5+6) |
| flutter_riverpod | State management |
| dio | HTTP client |
| flutter_secure_storage | Secure token storage |
| go_router | Navigation |
| fl_chart | Dashboard charts |
| image_picker | Pick-list photo capture |
| infinite_scroll_pagination | Paginated lists |
| home_widget | Android home-screen widget |

### Deploy (`deploy/`)
| Tool | Purpose |
|---|---|
| Python 3 + Paramiko | SSH deploy script |
| SCP | File transfer |
| cPanel | Hosting |

---

## Features

### 🔐 Authentication
- Login with form validation (registration is **disabled** — accounts are created via `artisan tinker` or direct DB insert)
- Sanctum token-based API authentication with configurable 30-day expiry
- Password change endpoint (verifies current password, revokes other sessions, 5/min rate limit)
- Rate-limited auth endpoints (login throttled per email + IP)
- Optional Google reCAPTCHA v2 support (`RECAPTCHA_ENABLED`)
- Auto-logout on 401 responses with global Axios interceptor
- Expired tokens are rejected by Sanctum's guard on every request

### 🏪 Multi-Marketplace Store Management
- Link Shopee **and** Lazada stores via OAuth 2.0 with CSRF-protected state parameter
- Reauthorize existing stores retaining products and order history
- Disconnect store tokens while preserving store records and audit logs
- Unlink stores with atomic cascade cleanup (token + store in one transaction)
- Token refresh error tracking (`last_refresh_attempt_at`, `last_refresh_error`, `refresh_attempts`)
- Visual store health indicators (🟢 healthy, 🟡 expiring soon, 🟠 refresh failed, 🔴 expired) with diagnostic tooltips
- Automated token refresh on API calls (near expiry) plus scheduled refresh (Shopee every 15 min, Lazada hourly)
- Shopee vacation mode toggle per store
- CORS configured to restrict API access to the frontend origin only

### 📦 Product Stock & Price Management
- View products across all stores with search and sort
- Filter by individual store and platform
- Edit stock and price with inline modal forms
- **Variant support** — products with variants (model_id) have per-variant stock/price editing
- **Cross-store consolidated editing** — products sharing the same SKU across stores can be edited together
- "Set all stores to same stock" checkbox for bulk updates
- Stock displayed with color-coded badges (green > 10, amber 4–10, red ≤ 3)
- `needs_push` flag tracks products whose local stock differs from the marketplace
- Pagination (50 per page) with page navigation

### 🔄 Cross-Store Sync
- When stock updates for a SKU'd product, all products with the same SKU across other stores are **queued** for automatic update
- Simple products matched by `item_sku`; variants matched by `model_sku`
- Sync runs asynchronously via `UpdateShopeeStockJob` (implementing `ShouldQueue`)
- Cross-store sync does NOT trigger further sync chains (prevents infinite loops)
- Inventory logs track sync source (`user`, `linked_sync`, `sync`, `order_webhook`)
- `needs_push` flag marks products whose local stock differs from the marketplace

> **Removed:** the Melur POS/ERP connector (`pos.melur.com`) was deleted — the `pos_inventories` table was dropped in `2026_10_04_000001_drop_pos_inventories_table.php` and no ERP code, routes, or config remain. Any `ERP_*` documentation or env keys left in older revisions are obsolete.

### 📋 Order Management
- View orders from all stores in a unified table, plus per-platform panels (Shopee / Lazada)
- Filter by store, platform, and order status
- Color-coded status badges
- Cancel orders with reason selection (Out of Stock, Undeliverable Area, Others)
- Order detail with items displayed inline
- `ordered_at` tracking mapped from marketplace create time
- Cursor-based pagination via marketplace APIs

### 🚚 Fulfillment & Shipping
- Arrange shipment — fetch shipping parameters, submit shipment with mode/dropoff/package number
- Shipping label download (PDF generated server-side with dompdf, printed client-side with pdf-lib)
- Bulk shipping labels for multiple orders (skips foreign/non-printable orders with per-order errors)
- Lazada: pack orders, mark ready-to-ship, bulk AWB assignment, fulfillment options

### ✅ Pick Lists
- Today's pick list and pending pick list from the dashboard
- Mark items complete with a photo (compressed server-side, cleaned up hourly once no open order needs the item)
- Workspace-shared completions — any member sees completions; any member can unpick
- Pick-list comments with read tracking and notifications
- Photo access is workspace-scoped (outsiders get 404)

### 🏷️ Promotions
- Normalized promotion list across platforms
- Shopee discounts — create, add items, update
- Shopee vouchers — create and update
- Lazada vouchers — create, update, activate, deactivate, manage SKUs

### 👥 Workspaces & Team
- Multi-tenant workspaces with members, invites (accept workflow), and owner-only marketplace credentials
- Role-scoped activity log with action filtering
- In-app notifications — new orders, status changes, urgent orders, pick-list comments, team activity, store reauth
- Unread count, mark-as-read, mark-all-read, delete
- Every query scoped through workspace access services — users only see their own workspace's data

### 📊 Dashboard
- Statistics cards: linked stores, orders today, pending orders, low stock count
- Pick-list counts (today / pending)
- Low stock alert banner (links to Stock page)
- Store list with health badges and linking dates
- "Link Store" button triggers OAuth flow
- Skeleton loading states and error+retry handling

### 📱 Mobile App (Flutter)
- Talks to the same Laravel API with a dedicated Dio client; token stored via `flutter_secure_storage`
- Screens: login, dashboard (stats + fl_chart graphs), stock, orders, pick list, notifications, team
- Android home-screen widget showing dashboard stats (`home_widget`)
- Riverpod providers for auth, stores, products, orders, dashboard, and notifications

### ⏰ Scheduled Tasks
| Command | Schedule | Purpose |
|---|---|---|
| `shopee:refresh-tokens` | Every 15 minutes | Refresh expiring Shopee tokens (backup to on-demand refresh) |
| `lazada:refresh-tokens` | Hourly | Refresh Lazada access tokens |
| `shopee:sync-products` | Every 30 minutes | Queue staggered paced product pulls (one job per store) |
| `shopee:sync-orders` | Every 15 minutes | Sync Shopee orders from the last 14 days |
| `lazada:sync-orders` | Every 15 minutes | Sync Lazada orders |
| `picklist:cleanup-photos` | Hourly | Delete pick-list photos no longer needed by open orders |
| `sanctum:prune-expired` | Daily | Remove expired API tokens |
| OAuth state cleanup | Daily | Remove stale OAuth state records |
| `orders:purge-buyer-data` | Daily at 02:00 | Purge buyer PII from orders older than 90 days (Shopee requirement) |

### 🛡️ Security

#### Authentication & Authorization
- Password hashed via Laravel's automatic `hashed` cast (bcrypt)
- Login returns generic `Invalid email or password` — no email enumeration
- Sanctum tokens created with configurable expiry (default 30 days, enforced per-request)
- Logout revokes the current token immediately
- Password change revokes other sessions but keeps the current one
- Expired tokens are rejected before reaching any controller logic
- Registration is disabled by default

#### API Security
- All mutation endpoints rate-limited (60/min per user, 5/min for sync, 5/min for password change)
- Product/order sync rate-limited (5/min per user)
- CORS configured with explicit `FRONTEND_URL` origin (no wildcard)
- Webhook signatures verified via HMAC-SHA256 on every request — no debug bypass
- Input validation on all API endpoints via FormRequest classes
- Stock/price values bounded (0–999999 for stock, 0–100000000 for price)

#### OAuth Flow Security
- Anti-CSRF state parameter: `Str::random(64)` → SHA-256 hash stored in database
- State is single-use: `consumed_at` timestamp set atomically inside `lockForUpdate()` transaction
- State expires after 10 minutes; stale states cleaned daily
- Marketplace API tokens encrypted at rest using Laravel's `encrypt()` (AES-256-CBC)
- OAuth callback validates all parameters manually and redirects safely on error (no raw JSON exposed)

#### Workspace Isolation
- Every query scoped through workspace access services (`WorkspaceAccess`, `WorkspaceActivityLogger`, `WorkspaceNotificationService`, `WorkspaceConfig`)
- Multi-user isolation: store/product/order queries scoped through `whereHas('store', user_id)` patterns
- Explicit ownership checks with `(int)` comparison guards
- Product authorization enforced via custom `ProductAuthorizationException` (403)
- Store unlink: authorization check before atomic transaction + cascade cleanup
- Store without a token is correctly reported as `expired` (not `healthy`)
- Workspace credentials readable/editable only by the workspace owner

#### HTTP Security Headers
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`
- `Content-Security-Policy-Report-Only` (self, unsafe-inline styles, data: images)
- `Strict-Transport-Security: max-age=31536000; includeSubDomains` (on HTTPS)

#### Operational Security
- SSH host key verification (`RejectPolicy` for unknown hosts)
- Shell input sanitization via regex pattern validation
- Mandatory compressed database backup before every deploy
- Corrupted deploy blocks on failed backup
- `.env` files, `deploy.config.json`, and local `.env.test` excluded from version control via `.gitignore`
- On-demand CI: dependency auditing (`composer audit`, `npm audit`) + gitleaks secret scanning (workflow runs on `workflow_dispatch`)

---

## Project Structure

```
mystores/
├── backend/                    # Laravel PHP backend
│   ├── app/
│   │   ├── Console/
│   │   │   └── Commands/
│   │   │       ├── RefreshShopeeTokens.php        # Shopee token refresh scheduler
│   │   │       ├── RefreshLazadaTokens.php        # Lazada token refresh scheduler
│   │   │       ├── SyncShopeeProducts.php         # Product sync command
│   │   │       ├── SyncShopeeOrders.php           # Shopee order sync command
│   │   │       ├── SyncLazadaOrders.php           # Lazada order sync command
│   │   │       ├── PurgeOldBuyerData.php          # 90-day buyer PII purge
│   │   │       ├── CleanupPickListPhotos.php      # Pick-list photo cleanup
│   │   │       ├── BackfillShopeeOrderQuantities.php
│   │   │       └── BackfillOrderOrderedAt.php
│   │   ├── Exceptions/
│   │   │   ├── ProductAuthorizationException.php
│   │   │   └── StoreNotActiveException.php
│   │   ├── Helpers/
│   │   │   └── ShopeeResponseHelper.php           # Shopee API response parsing
│   │   ├── Http/
│   │   │   ├── Controllers/Api/
│   │   │   │   ├── AuthController.php             # Login/logout/me/change-password
│   │   │   │   ├── DashboardController.php        # Dashboard stats + pick lists
│   │   │   │   ├── OrderController.php            # Order CRUD + cancel + fulfillment
│   │   │   │   ├── ProductController.php          # Product CRUD + stock/price
│   │   │   │   ├── PromotionController.php        # Promotions (both platforms)
│   │   │   │   ├── ShopeeAuthController.php       # Shopee OAuth linking
│   │   │   │   ├── ShopeeWebhookController.php    # Shopee webhook handler
│   │   │   │   ├── LazadaAuthController.php       # Lazada OAuth linking
│   │   │   │   ├── LazadaOrderController.php      # Lazada orders/AWB
│   │   │   │   ├── NotificationController.php     # In-app notifications
│   │   │   │   ├── PickListCompletionController.php
│   │   │   │   ├── PickListCommentController.php
│   │   │   │   ├── WorkspaceController.php        # Workspace/team/activity
│   │   │   │   └── WorkspaceCredentialController.php  # Owner-only credentials
│   │   │   ├── Middleware/
│   │   │   │   └── SecurityHeaders.php            # Security headers middleware
│   │   │   ├── Requests/                          # Form validation requests
│   │   │   └── Resources/
│   │   ├── Jobs/
│   │   │   ├── SyncShopeeStoreJob.php             # Background store sync
│   │   │   ├── SyncShopeeOrderJob.php             # Background order sync
│   │   │   ├── UpdateShopeePriceJob.php           # Price update job
│   │   │   └── UpdateShopeeStockJob.php           # Stock update + linked sync
│   │   ├── Models/
│   │   │   ├── InventoryLog.php                   # Stock change audit trail
│   │   │   ├── Order.php / OrderItem.php          # Order models
│   │   │   ├── Product.php / ProductVariant.php   # Product models
│   │   │   ├── ShopeeOAuthState.php               # OAuth state tracking
│   │   │   ├── ShopeeStore.php                    # Linked store (Shopee/Lazada)
│   │   │   ├── ShopeeToken.php                    # Encrypted token storage
│   │   │   ├── Workspace.php                      # Team workspace
│   │   │   ├── WorkspaceMember.php / WorkspaceInvite.php
│   │   │   ├── WorkspaceActivityLog.php / WorkspaceCredential.php
│   │   │   ├── PickListCompletion.php / PickListComment.php / PickListCommentRead.php
│   │   │   └── User.php                           # Sanctum HasApiTokens
│   │   ├── Notifications/
│   │   │   ├── OrderNewNotification.php / OrderStatusChangedNotification.php
│   │   │   ├── OrderUrgentNotification.php / PickListCommentNotification.php
│   │   │   ├── TeamActivityNotification.php / StoreReauthNotification.php
│   │   └── Services/
│   │       ├── ShopeeService.php / ShopeePromotionService.php
│   │       ├── LazadaService.php / LazadaPromotionService.php / LazadaOrderService.php
│   │       ├── ProductService.php / OrderService.php / LogisticsService.php
│   │       ├── PackingListPdfService.php          # PDF generation
│   │       ├── PickListPhotoCompressor.php / PickListPhotoCleanupService.php
│   │       ├── NotificationGeneratorService.php
│   │       └── Workspace*.php                     # Workspace access/logging/notifications/config
│   ├── config/
│   │   ├── cors.php                               # CORS config (FRONTEND_URL)
│   │   ├── shopee.php                             # Shopee API config
│   │   └── lazada.php                             # Lazada API config
│   ├── database/
│   │   └── migrations/                            # 35 migration files
│   ├── routes/
│   │   ├── api.php                                # API routes
│   │   ├── web.php                                # SPA fallback route
│   │   └── console.php                            # Scheduler definitions
│   ├── tests/
│   │   ├── Feature/                               # 34 feature test files
│   │   └── Unit/                                  # 3 unit test files
│   └── composer.json
│
├── frontend/                   # React + TypeScript SPA
│   └── src/
│       ├── api/
│       │   ├── client.ts                          # Axios client + API functions
│       │   └── getCache.ts                        # Request deduplication cache
│       ├── components/
│       │   ├── AppShell.tsx / Layout.tsx          # App shell (sidebar + header)
│       │   ├── ProtectedRoute.tsx                 # Auth guard
│       │   ├── StoreSwitcher.tsx                  # Store dropdown
│       │   ├── ErrorBoundary.tsx                  # React error boundary
│       │   ├── NotificationBell.tsx               # Notification dropdown
│       │   ├── OrderDetailDrawer.tsx              # Order detail side panel
│       │   ├── ArrangeShipmentModal.tsx           # Fulfillment modal
│       │   ├── PrintLabelButton.tsx               # Label printing
│       │   ├── PromotionProductPicker.tsx         # Promotion SKU picker
│       │   ├── PageShell.tsx / Spinner.tsx / KeepAlivePages.tsx
│       │   └── orderHelpers.tsx
│       ├── context/
│       │   ├── AuthContext.tsx                    # Auth state management
│       │   ├── StoreContext.tsx                   # Store selection state
│       │   └── ToastContext.tsx                   # Toast notifications
│       ├── lib/
│       │   └── shippingLabelPdf.ts                # Client-side label PDF
│       ├── pages/
│       │   ├── Dashboard.tsx                      # Main dashboard
│       │   ├── Login.tsx / Register.tsx           # Auth forms
│       │   ├── Orders.tsx                         # Unified order list
│       │   ├── ShopeeOrdersPanel.tsx / LazadaOrdersPanel.tsx
│       │   ├── UnifiedOrdersPanel.tsx
│       │   ├── Stock.tsx                          # Product stock/price editing
│       │   ├── Promotions.tsx                     # Promotion management
│       │   ├── PickList.tsx                       # Daily pick list
│       │   ├── Team.tsx                           # Workspace members/invites
│       │   └── Settings.tsx                       # App settings
│       ├── utils/
│       │   └── tokenRemaining.ts                  # Token expiry display
│       ├── App.tsx                                # Router + providers
│       ├── main.tsx                               # Entry point
│       └── index.css                              # Tailwind + global styles
│
├── mobile/                     # Flutter companion app (v1.0.5+6)
│   └── lib/
│       ├── features/
│       │   ├── auth/login_screen.dart
│       │   ├── dashboard/dashboard_screen.dart    # Stats + charts (fl_chart)
│       │   ├── stock/stock_screen.dart
│       │   ├── orders/orders_screen.dart
│       │   ├── pick_list/pick_list_screen.dart
│       │   ├── notifications/notifications_screen.dart
│       │   ├── team/team_screen.dart
│       ├── core/
│       │   ├── api/                               # Dio client, endpoints, error handling
│       │   ├── models/                            # Store, product, order, dashboard, notification, workspace
│       │   ├── providers/                         # Riverpod providers (auth, store, product, order, dashboard, notification)
│       │   ├── auth/auth_storage.dart             # Secure token storage
│       │   └── widgets/home_screen_widget.dart    # Android home-screen widget
│       ├── shared/                                # Theme + shared widgets
│       ├── app.dart / main.dart
│   └── pubspec.yaml
│
├── deploy/                      # Deployment automation
│   ├── deploy.py                                # SSH deployment script
│   ├── process_queue.py                         # Queue worker script
│   ├── SERVER_SETUP.md                          # cPanel setup guide
│   ├── deploy.config.json.example               # SSH/Git config template
│   └── .env.production.example                  # Production env template
│
├── .env.test.example             # E2E test environment template
├── e2e-stock-flow.spec.ts       # Playwright E2E test (stock flow)
├── verify-cross-stock.spec.ts   # Playwright test (cross-stock)
├── verify-stock.spec.ts         # Playwright test (stock)
└── scrape-shopee-docs.spec.ts   # Playwright script (docs scrape)
```

---

## Database Schema

The database consists of **18 application tables** (plus Laravel's `jobs`, `job_batches`, `failed_jobs`, `cache`, `sessions`, `password_reset_tokens`, and `personal_access_tokens` tables):

| Table | Purpose |
|---|---|
| `users` | User accounts (Sanctum API tokens) |
| `shopee_stores` | Linked stores, per user, per platform (`shopee`/`lazada`), unique per user+shop |
| `shopee_tokens` | Encrypted OAuth tokens (per store, cascading delete) |
| `shopee_oauth_states` | One-time OAuth state parameters (SHA-256 hashed) |
| `products` | Synced product data (unique per store+item, `platform` column) |
| `product_variants` | Variant/model data for multi-variant products |
| `orders` | Synced order data (unique per store+order_sn, `ordered_at`) |
| `order_items` | Line items per order (with SKU) |
| `inventory_logs` | Audit trail for stock changes (nullOnDelete preserves history) |
| `workspaces` | Team workspaces |
| `workspace_members` | Workspace membership (roles) |
| `workspace_invites` | Pending invitations with accept tokens |
| `workspace_activity_logs` | Role-scoped activity audit trail |
| `workspace_credentials` | Shared marketplace credentials (owner-managed) |
| `notifications` | In-app notifications |
| `pick_list_completions` | Pick-list item completions with photos |
| `pick_list_comments` | Pick-list comments |
| `pick_list_comment_reads` | Per-user comment read tracking |

### Key Relationships

- **User** 1→* **ShopeeStore** — one user can link many stores (both platforms)
- **ShopeeStore** 1→1 **ShopeeToken** — each store has one encrypted token record (cascade)
- **ShopeeStore** 1→* **Product** — products belong to a store (cascade)
- **Product** 1→* **ProductVariant** — products with variants have many model/variant entries (cascade)
- **ShopeeStore** 1→* **Order** — orders belong to a store (cascade)
- **Order** 1→* **OrderItem** — each order has line items (cascade)
- **Product** 1→* **InventoryLog** — stock changes are logged with before/after values (nullOnDelete preserves history)
- **User** 1→* **ShopeeOAuthState** — OAuth states belong to a user (cascade)
- **Workspace** 1→* **WorkspaceMember / WorkspaceInvite / WorkspaceActivityLog**
- **PickListCompletion** 1→* **PickListComment** → **PickListCommentRead**

---

## Getting Started (Local Development)

### Prerequisites

- PHP ^8.2
- Composer
- Node.js 20+
- SQLite (for local dev) or MySQL
- Flutter 3.x with Dart >=3.4.0 (for the mobile app)
- Shopee Partner ID & Key (from [open.shopee.com](https://open.shopee.com))
- Lazada App Key & Secret (optional — for Lazada store linking)

### 1. Clone & Install Backend

```bash
git clone https://github.com/terranoss/mystores.git
cd mystores

# Install PHP dependencies
cd backend
composer install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database (SQLite for dev)
touch database/database.sqlite
php artisan migrate
```

### 2. Configure `.env`

```ini
APP_NAME=MyStores
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

DB_CONNECTION=sqlite
# Or for MySQL:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=mystores
# DB_USERNAME=root
# DB_PASSWORD=

SANCTUM_NEW_TOKEN_EXPIRATION=43200

# Shopee Open Platform
SHOPEE_PARTNER_ID=your_partner_id
SHOPEE_PARTNER_KEY=your_partner_key
SHOPEE_REDIRECT_URL=http://localhost:8000/api/shopee/callback
SHOPEE_WEBHOOK_URL=
SHOPEE_SANDBOX=true

# Lazada Open Platform (optional)
LAZADA_APP_KEY=your_app_key
LAZADA_APP_SECRET=your_app_secret
LAZADA_REDIRECT_URL=http://localhost:8000/api/lazada/callback
LAZADA_SANDBOX=true

# Optional: Google reCAPTCHA v2 on login
RECAPTCHA_ENABLED=false
RECAPTCHA_SITE_KEY=
RECAPTCHA_SECRET_KEY=
```

### 3. Install Frontend & Start Dev

```bash
# From the project root
cd frontend
npm install
npm run dev
```

### 4. Start Backend Dev Server

```bash
# From backend/
php artisan serve

# In another terminal, start the queue worker:
php artisan queue:work --tries=1 --timeout=0
```

Or run the entire app stack (Laravel + queue + Vite) together:

```bash
cd backend
composer dev
```

### 5. Mobile App (optional)

```bash
cd mobile
flutter pub get
flutter run
```

The mobile app talks to the same API — set its base URL to your backend (see `mobile/lib/core/api/api_client.dart`).

### 6. Run Scheduled Tasks Locally

```bash
php artisan schedule:work
```

### 7. Run Tests

```bash
# Full backend suite (185 tests)
cd backend && php artisan test

# Or via Composer
composer test

# Run specific test files
php artisan test --filter=Authentication
php artisan test --filter=ShopeeOAuth
php artisan test --filter=LazadaOrderManagement
php artisan test --filter=SecurityHardening
php artisan test --filter=WorkspaceAccess
php artisan test --filter=Promotion
```

---

## API Overview

All API routes are prefixed with `/api/`. Protected routes require a `Bearer {token}` Authorization header. CORS is configured to allow only the origin specified in `FRONTEND_URL`. Registration is disabled — the endpoint returns 405.

### Authentication

| Method | Endpoint | Auth | Throttle | Description |
|---|---|---|---|---|
| POST | `/api/login` | No | login | Login, returns Bearer token |
| POST | `/api/logout` | Yes | — | Revoke current Sanctum token |
| GET | `/api/me` | Yes | — | Get current user info |
| POST | `/api/change-password` | Yes | 5/min | Change password (revokes other sessions) |

### Shopee Store Linking

| Method | Endpoint | Auth | Throttle | Description |
|---|---|---|---|---|
| GET | `/api/shopee/auth-url` | Yes | oauth | Generate Shopee OAuth authorization URL |
| GET | `/api/shopee/callback` | No | oauth | OAuth callback handler (public, Shopee redirects here) |
| GET | `/api/shopee/stores` | Yes | — | List linked stores with health status |
| POST | `/api/shopee/stores/{store}/reauthorize` | Yes | — | Reauthorize a store |
| POST | `/api/shopee/stores/{store}/disconnect` | Yes | mutation | Disconnect store token (keeps records) |
| POST | `/api/shopee/stores/{store}/vacation-mode` | Yes | mutation | Set vacation mode |
| GET | `/api/shopee/stores/{store}/vacation-mode` | Yes | — | Get vacation mode status |
| DELETE | `/api/shopee/stores/{store}` | Yes | mutation | Unlink a store (atomic delete + cascade) |
| POST | `/api/shopee/webhook` | No | — | Shopee webhook receiver (HMAC-SHA256 verified) |

### Lazada Store Linking

| Method | Endpoint | Auth | Throttle | Description |
|---|---|---|---|---|
| GET | `/api/lazada/auth-url` | Yes | oauth | Generate Lazada OAuth authorization URL |
| GET | `/api/lazada/callback` | No | oauth | OAuth callback handler (public, Lazada redirects here) |
| POST | `/api/lazada/stores/{store}/reauthorize` | Yes | oauth | Reauthorize a Lazada store |
| POST | `/api/lazada/stores/{store}/disconnect` | Yes | mutation | Disconnect store token |
| DELETE | `/api/lazada/stores/{store}` | Yes | mutation | Unlink a Lazada store |

### Products & Stock

| Method | Endpoint | Auth | Throttle | Description |
|---|---|---|---|---|
| GET | `/api/products` | Yes | — | List products (paginated, filterable by store/search/platform) |
| GET | `/api/products/grouped` | Yes | — | List products grouped by SKU for cross-store editing |
| GET | `/api/products/{product}` | Yes | — | Get product with variants |
| PUT | `/api/products/{product}/stock` | Yes | mutation | Update stock (marketplace API + local DB + queued linked sync) |
| PUT | `/api/products/{product}/price` | Yes | mutation | Update price (marketplace API + local DB) |
| PUT | `/api/products/{product}/stock-price` | Yes | mutation | Update both stock and price |
| PUT | `/api/products/stock-by-sku` | Yes | mutation | Update stock for all products matching a SKU across stores |
| PUT | `/api/products/price-by-sku` | Yes | mutation | Update price for all products matching a SKU across stores |
| POST | `/api/products/pull` | Yes | sync | Queue catalog pull (`store_id` and/or `product_ids`); returns 202 |
| POST | `/api/products/push` | Yes | sync | Queue push of local stock to marketplace for `product_ids`; returns 202 |
| POST | `/api/products/sync` | Yes | sync | Alias of `/products/pull` (async) |

### Orders

| Method | Endpoint | Auth | Throttle | Description |
|---|---|---|---|---|
| GET | `/api/orders` | Yes | — | List orders (filterable by store/status/platform) |
| GET | `/api/orders/{order}` | Yes | — | Get order with items |
| POST | `/api/orders/{order}/cancel` | Yes | mutation | Cancel an order (reason required) |
| POST | `/api/orders/sync` | Yes | sync | Trigger order sync for all active stores |
| POST | `/api/orders/shipping-labels` | Yes | — | Bulk shipping labels (PDF) |

### Fulfillment

| Method | Endpoint | Auth | Throttle | Description |
|---|---|---|---|---|
| GET | `/api/orders/{order}/shipping-parameters` | Yes | — | Get shipping parameters for arranging shipment |
| POST | `/api/orders/{order}/ship` | Yes | mutation | Submit shipment (mode, dropoff, package number) |
| GET | `/api/orders/{order}/shipping-label` | Yes | — | Download shipping label PDF |

### Lazada Orders

| Method | Endpoint | Auth | Throttle | Description |
|---|---|---|---|---|
| POST | `/api/lazada/orders/sync` | Yes | sync | Sync Lazada orders |
| POST | `/api/lazada/orders/awb-bulk` | Yes | mutation | Bulk AWB assignment |
| POST | `/api/lazada/orders/{order}/cancel` | Yes | mutation | Cancel a Lazada order |
| GET | `/api/lazada/orders/{order}/fulfillment-options` | Yes | — | List fulfillment options |
| POST | `/api/lazada/orders/{order}/pack` | Yes | mutation | Mark order as packed |
| GET | `/api/lazada/orders/{order}/awb` | Yes | — | Get order AWB |
| POST | `/api/lazada/orders/{order}/ready-to-ship` | Yes | mutation | Mark order ready to ship |

### Pick Lists

| Method | Endpoint | Auth | Throttle | Description |
|---|---|---|---|---|
| GET | `/api/dashboard/today-picklist` | Yes | — | Today's pick list |
| GET | `/api/dashboard/pending-picklist` | Yes | — | Pending pick list |
| POST | `/api/dashboard/picklist-completions` | Yes | mutation | Complete a pick-list item (photo required) |
| DELETE | `/api/dashboard/picklist-completions` | Yes | mutation | Unpick an item |
| GET | `/api/dashboard/picklist-photos/{completion}` | Yes | — | Get completion photo (workspace-scoped) |
| GET | `/api/dashboard/picklist-comments` | Yes | — | List pick-list comments |
| POST | `/api/dashboard/picklist-comments` | Yes | mutation | Add a comment |
| POST | `/api/dashboard/picklist-comments/read` | Yes | mutation | Mark comments as read |
| DELETE | `/api/dashboard/picklist-comments/{comment}` | Yes | mutation | Delete a comment |

### Promotions

| Method | Endpoint | Auth | Throttle | Description |
|---|---|---|---|---|
| GET | `/api/promotions` | Yes | — | Normalized promotion list across platforms |
| GET | `/api/promotions/{platform}/{type}/{remoteId}` | Yes | — | Promotion detail |
| POST | `/api/shopee/promotions/discounts` | Yes | mutation | Create Shopee discount |
| POST | `/api/shopee/promotions/discounts/{id}/items` | Yes | mutation | Add items to Shopee discount |
| PUT | `/api/shopee/promotions/discounts/{id}` | Yes | mutation | Update Shopee discount |
| POST | `/api/shopee/promotions/vouchers` | Yes | mutation | Create Shopee voucher |
| PUT | `/api/shopee/promotions/vouchers/{id}` | Yes | mutation | Update Shopee voucher |
| POST | `/api/lazada/promotions/vouchers` | Yes | mutation | Create Lazada voucher |
| PUT | `/api/lazada/promotions/vouchers/{id}` | Yes | mutation | Update Lazada voucher |
| POST | `/api/lazada/promotions/vouchers/{id}/activate` | Yes | mutation | Activate Lazada voucher |
| POST | `/api/lazada/promotions/vouchers/{id}/deactivate` | Yes | mutation | Deactivate Lazada voucher |
| POST | `/api/lazada/promotions/vouchers/{id}/skus` | Yes | mutation | Add/remove voucher SKUs |

### Workspace & Team

| Method | Endpoint | Auth | Throttle | Description |
|---|---|---|---|---|
| GET | `/api/workspace` | Yes | — | Workspace details |
| GET | `/api/workspace/activity` | Yes | — | Activity log (role-scoped, filterable) |
| POST | `/api/workspace/invites` | Yes | mutation | Invite a member |
| POST | `/api/workspace/invites/accept` | Yes | mutation | Accept an invite |
| DELETE | `/api/workspace/members/{memberId}` | Yes | mutation | Remove a member |
| GET | `/api/workspace/credentials` | Yes (owner) | — | Get shared marketplace credentials |
| PUT | `/api/workspace/credentials` | Yes (owner) | mutation | Update shared credentials |
| DELETE | `/api/workspace/credentials` | Yes (owner) | mutation | Delete shared credentials |

### Notifications

| Method | Endpoint | Auth | Throttle | Description |
|---|---|---|---|---|
| GET | `/api/notifications` | Yes | — | List notifications |
| GET | `/api/notifications/unread-count` | Yes | — | Unread notification count |
| POST | `/api/notifications/read-all` | Yes | — | Mark all as read |
| POST | `/api/notifications/{id}/read` | Yes | — | Mark one as read |
| DELETE | `/api/notifications/{id}` | Yes | — | Delete a notification |

### Dashboard

| Method | Endpoint | Auth | Throttle | Description |
|---|---|---|---|---|
| GET | `/api/dashboard` | Yes | — | Get aggregated stats (stores, orders today, pending, low stock) |

---

## Deployment

Deployment is automated via a Python script to a cPanel server. The script performs a pre-deploy database backup and aborts if backup fails.

### Prerequisites (one-time)

1. **Server setup** — see `deploy/SERVER_SETUP.md` for cPanel configuration
   - Create subdomain `mystores.melur.com`
   - Set PHP to 8.2 in MultiPHP Manager
   - Configure SSH access with public key
   - Create MySQL database and user
   - Run AutoSSL for HTTPS

2. **Local configuration**
    ```bash
    cd deploy
    cp deploy.config.json.example deploy.config.json
    cp .env.production.example .env.production
    ```
    Edit both files with your production credentials (GitHub repo URL, SSH details, Shopee/Lazada keys, database).
    **Important:** Neither file is tracked by git — `.gitignore` covers both.

### Deploy

```bash
cd deploy
python deploy.py

# Or from the project root:
npm run deploy
```

### What the Deploy Script Does

1. Connects via SSH (password or key-based auth with encrypted key support)
2. Creates a compressed MySQL database backup (aborts if backup fails)
3. Syncs code from GitHub (initializes repo on first deploy, `git pull --ff-only` on subsequent)
4. Installs Composer dependencies (`--no-dev --optimize-autoloader`)
5. Uploads `.env` and compiled frontend assets
6. Runs Laravel commands (key generation only if missing, `package:discover`, `migrate --force`, `config:cache`, `route:cache`, `storage:link`)
7. Configures `.htaccess` with HTTPS redirect, security headers, and cPanel PHP handler preservation
8. Sets up cron jobs for the scheduler and queue worker (appends to existing crontab)
9. Verifies the app responds with HTTP 200

### Running Queue Workers

The deploy script configures a cron job that runs the queue worker every minute:

```cron
* * * * * php /path/to/backend/artisan queue:work --stop-when-empty --timeout=60
```

When a cross-store stock update is saved, the HTTP response returns immediately and the queued `UpdateShopeeStockJob` processes the linked stores in the background. The cron polls the `jobs` table every minute and processes up to one job per invocation.

For burst processing (e.g., running down a backlog), use the helper script:

```bash
python deploy/process_queue.py
```

This SSH's into the server and runs `queue:work --once` in a loop (up to 12 iterations).

---

## Testing

The project has **185 backend PHPUnit tests** across **31 test files** (29 feature, 2 unit) covering authentication, Shopee and Lazada OAuth, store reauthorization, on-demand and scheduled token refresh, store management, cross-store sync, order fulfillment, shipping labels, pick lists, promotions, workspaces, buyer-data purging, and security hardening. All tests use an in-memory SQLite database, are fully deterministic, and require no external network access or real marketplace credentials.

```bash
cd backend
php artisan test
```

Notable test suites (`backend/tests/Feature/`):

| Suite | Coverage |
|---|---|
| `AuthenticationTest.php` | Login, generic 401s, token expiry, registration disabled (405) |
| `ChangePasswordTest.php` | Current password verification, session revocation, rate limiting |
| `ShopeeOAuthTest.php` | OAuth state replay protection, store listing isolation, token refresh |
| `ShopeeTokenRefreshTest.php` | On-demand and scheduled Shopee token refresh |
| `ShopeeOrderPushTest.php` | Webhook signature verification, order status push, stock push |
| `ShopeeVacationModeTest.php` | Vacation mode toggle and status |
| `ShopeeOrderQuantityTest.php` | Order line quantity backfill |
| `LazadaOrderManagementTest.php` | Lazada order sync, status mapping, pack/ready-to-ship |
| `LazadaProductUpdateTest.php` | Lazada stock/price updates, mixed-platform bulk updates |
| `LazadaBulkAwbTest.php` | Bulk AWB assignment |
| `LazadaSellerNameTest.php` | Lazada seller-name mapping |
| `PromotionTest.php` | Shopee discounts/vouchers, Lazada vouchers, validation |
| `OrderListTest.php` / `OrderOrderedAtTest.php` | Order listing, filters, `ordered_at` mapping |
| `NeedsPushStockTest.php` | `needs_push` flag tracking local vs marketplace stock |
| `ProductGroupedFilterTest.php` | SKU-grouped product listing and filtering |
| `ArrangeShipmentTest.php` | Shipping parameters, ship submission, error mapping |
| `ShippingLabelTest.php` / `BulkShippingLabelTest.php` | Label generation and bulk labels |
| `DashboardTest.php` | Dashboard stats and pick lists |
| `PickListCompletionTest.php` | Photo completion, workspace sharing, photo cleanup |
| `PickListCommentTest.php` | Comments, read tracking, notifications |
| `WorkspaceAccessTest.php` / `WorkspaceActivityLogTest.php` / `WorkspaceNotificationTest.php` | Workspace isolation, activity logging, notifications |
| `PurgeBuyerDataTest.php` | 90-day buyer PII retention purge |
| `SecurityHardeningTest.php` | Security regression tests (headers, CORS, webhook signatures, isolation) |

Unit suites (`backend/tests/Unit/`): `PickListPhotoCompressorTest.php` and a default `ExampleTest.php`.

> **Note:** the ERP/POS test suites (`Erp*Test.php`) were removed along with the connector. `WorkspaceActivityLogTest.php` still uses `erp.update_stock` as an arbitrary action string for filtering — it is not ERP-dependent.

### E2E Tests (Playwright)

E2E tests use environment variables for credentials — no secrets are hardcoded.

```bash
# Set credentials (or use .env.test — see .env.test.example)
export TEST_BASE_URL=https://mystores.melur.com
export TEST_USER_EMAIL=your-email@example.com
export TEST_USER_PASSWORD=your-password

# Run individual tests
npx playwright test e2e-stock-flow.spec.ts
npx playwright test verify-cross-stock.spec.ts
npx playwright test verify-stock.spec.ts
```

E2E tests cover the full stock editing flow: login → navigate to stock → edit product → save → sync → dashboard verification.

---

## Development Conventions

- **Backend**: Laravel conventions — PSR-4 autoloading, service-oriented controllers, form request validation
- **Frontend**: Functional components with hooks, TypeScript interfaces for all API types, Tailwind CSS utility classes
- **Mobile**: Flutter with Riverpod for state, Dio for HTTP, go_router for navigation, secure storage for tokens
- **API**: RESTful JSON responses, snake_case for backend (PHP/Laravel convention), camelCase for frontend (TypeScript convention)
- **Sync**: Queue jobs (`ShouldQueue`) for async cross-store sync; `dispatch()` instead of `dispatchSync()` to avoid blocking HTTP responses
- **Multi-platform**: Platform-specific logic lives in `ShopeeService` / `LazadaService`; shared flows go through `ProductService` / `OrderService`
- **Workspace scoping**: All tenant queries go through workspace access services — never raw model queries for tenant data
- **Errors**: Graceful error states in all UI components with retry buttons; 401 auto-redirect to login via Axios interceptor; 429 retry with exponential backoff
- **Tests**: Feature tests with `RefreshDatabase`, in-memory SQLite, no external API calls, no production credentials

---

## Configuration Reference

### Backend `.env`

| Variable | Default | Description |
|---|---|---|
| `APP_NAME` | `MyStores` | Application name |
| `APP_URL` | `http://localhost` | Public app URL |
| `FRONTEND_URL` | `http://localhost:5173` | Frontend URL for OAuth redirects and CORS |
| `DB_CONNECTION` | `sqlite` | Database driver |
| `SANCTUM_NEW_TOKEN_EXPIRATION` | `43200` | Token expiry in minutes (43200 = 30 days) |
| `QUEUE_CONNECTION` | `database` | Queue driver |
| `SHOPEE_PARTNER_ID` | — | Shopee Open Platform Partner ID |
| `SHOPEE_PARTNER_KEY` | — | Shopee Open Platform Partner Key |
| `SHOPEE_PUSH_PARTNER_KEY` | — | Push Partner Key from Shopee Push settings (preferred for webhook signature verification) |
| `SHOPEE_REDIRECT_URL` | `http://localhost:8000/api/shopee/callback` | OAuth callback URL |
| `SHOPEE_WEBHOOK_URL` | — | Exact public webhook URL registered in Shopee Push Console (used for signature verification) |
| `SHOPEE_SANDBOX` | `true` | Use Shopee sandbox environment |
| `LAZADA_APP_KEY` | — | Lazada Open Platform App Key |
| `LAZADA_APP_SECRET` | — | Lazada Open Platform App Secret |
| `LAZADA_REDIRECT_URL` | `https://mystores.melur.com/api/lazada/callback` | Lazada OAuth callback URL |
| `LAZADA_SANDBOX` | `false` | Use Lazada sandbox environment |
| `RECAPTCHA_ENABLED` | `false` | Enable Google reCAPTCHA v2 on login |
| `RECAPTCHA_SITE_KEY` / `RECAPTCHA_SECRET_KEY` | — | reCAPTCHA v2 keys |

> The former `ERP_*` keys (Melur POS/ERP connector) were removed along with the integration and are no longer read by the app.

> The former `PLANTNET_API_KEY` mobile build flag was removed along with the Plant ID feature.

### `config/cors.php`

```php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
    ],
    'allowed_headers' => ['*'],
    'supports_credentials' => false,  // Bearer token auth, not cookies
];
```

The `HandleCors` middleware is registered globally in `bootstrap/app.php`.

### `config/shopee.php`

```php
return [
    'partner_id' => env('SHOPEE_PARTNER_ID'),
    'partner_key' => env('SHOPEE_PARTNER_KEY'),
    'redirect_url' => env('SHOPEE_REDIRECT_URL', 'http://localhost:8000/api/shopee/callback'),
    'sandbox' => env('SHOPEE_SANDBOX', true),
];
```

### `config/lazada.php`

```php
return [
    'app_key' => env('LAZADA_APP_KEY'),
    'app_secret' => env('LAZADA_APP_SECRET'),
    'redirect_url' => env('LAZADA_REDIRECT_URL', 'https://mystores.melur.com/api/lazada/callback'),
    'sandbox' => env('LAZADA_SANDBOX', false),
];
```

---

## Security

Key security properties (see the security section above for the full list):

- **Token storage**: Access and refresh tokens are encrypted via Laravel's `encrypt()` (AES-256-CBC) before persisting; APP_KEY required for decryption
- **OAuth flow**: Anti-CSRF state parameter is hashed (SHA-256), single-use with `lockForUpdate()`, expires in 10 minutes
- **Webhooks**: HMAC-SHA256 signature verification on every request — invalid signatures always return 401, even in debug mode
- **Rate limiting**: Throttle middleware on login, registration (if re-enabled), OAuth, sync, mutations, and password changes
- **Input validation**: All API inputs validated via FormRequest classes; stock (0–999999) and price (0–100000000) bounded
- **Workspace isolation**: Every tenant query scoped through workspace access services
- **HTTP security**: HSTS (1 year, includeSubDomains), CSP (report-only), X-Frame-Options (DENY), Referrer-Policy, Permissions-Policy, X-Content-Type-Options (nosniff)
- **On-demand CI**: `composer audit`, `npm audit`, and gitleaks secret scanning available via `workflow_dispatch`
- **Deployment security**: SSH host keys verified (`RejectPolicy`), shell inputs sanitized by regex, database backed up before deploy, deploy aborts if backup fails
- **Data retention**: Buyer PII purged from orders older than 90 days (Shopee requirement), daily at 02:00

---

## License

MIT — see [LICENSE](LICENSE) for details.

---

## Related Documentation

- [`deploy/SERVER_SETUP.md`](deploy/SERVER_SETUP.md) — cPanel server setup guide
- [`backend/composer.json`](backend/composer.json) — PHP dependencies
- [`frontend/package.json`](frontend/package.json) — Frontend dependencies
- [`mobile/pubspec.yaml`](mobile/pubspec.yaml) — Mobile app dependencies
- [`.env.test.example`](.env.test.example) — E2E test environment template
