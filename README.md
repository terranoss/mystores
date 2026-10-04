# ShopeeManager

> **Multi-store Shopee management dashboard** — manage products, stock, pricing, and orders across multiple Shopee stores from a single interface.

[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![React](https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=white)](https://react.dev)
[![TypeScript](https://img.shields.io/badge/TypeScript-5-3178C6?logo=typescript&logoColor=white)](https://www.typescriptlang.org)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-33%20passing-22c55e)](backend/tests)

**Live:** [mystores.melur.com](https://mystores.melur.com)

---

## Overview

ShopeeManager connects to your Shopee stores via the Shopee Open Platform API, giving you a unified dashboard to:

- **Link** multiple Shopee stores and manage OAuth tokens
- **View & edit** product stock and pricing across all stores
- **Cross-store sync** — update stock for identical SKUs across stores in one action
- **Manage orders** — view, filter, and cancel orders from a single interface
- **Monitor** store health, low stock alerts, and pending order counts

Built with a **Laravel 12** backend (REST API) and a **React 19 + TypeScript** SPA frontend, deployed on cPanel hosting.

---

## Architecture

```
┌──────────────────────────────────────────────────────┐
│                    Frontend (SPA)                     │
│     React 19 · TypeScript · Tailwind CSS 4 · Vite    │
│         Hosted at mystores.melur.com (via Laravel)      │
└──────────────────┬───────────────────────────────────┘
                   │  HTTP/JSON (Bearer Token)
                   ▼
┌──────────────────────────────────────────────────────┐
│                  Backend (REST API)                   │
│     Laravel 12 · Sanctum Auth · MySQL / SQLite       │
│     Domain: mystores.melur.com/api/                     │
└──────┬──────────────────────────────┬────────────────┘
       │                              │
       ▼                              ▼
┌──────────────┐           ┌──────────────────────┐
│   Shopee     │           │    Laravel Queue     │
│  Open API    │◄─────────►│  Async Jobs + Sync   │
│ (ecomphp/    │           │  Scheduler (cron)    │
│  shopee-php) │           └──────────────────────┘
└──────────────┘
```

### Key Design Decisions

- **Monorepo structure** — `backend/` (Laravel) and `frontend/` (React) in one repository
- **API token auth** via Laravel Sanctum with configurable 30-day token expiry
- **Encrypted token storage** — Shopee OAuth access/refresh tokens are encrypted at rest using Laravel's AES-256-CBC encryption
- **Queued cross-store sync** — stock updates dispatch `UpdateShopeeStockJob` asynchronously via `dispatch()` for non-blocking propagation across stores
- **SKU-based cross-store updates** — when stock changes for a product with a SKU, all matching products/variants across stores are automatically updated
- **Inventory logging** — every stock change is recorded in `inventory_logs` with before/after values and source tracking
- **Automated token refresh** — API calls refresh near-expiry access tokens on demand; a scheduled command also refreshes tokens every 15 minutes as a backup
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
| Guzzle | (HTTP client for Shopee API) |
| Database | MySQL (production) / SQLite (local dev) |
| Queue | Database-driven queue |

### Frontend (`frontend/`)
| Technology | Version |
|---|---|
| React | ^19.2 |
| TypeScript | ^6.0 |
| Vite | ^8.0 |
| Tailwind CSS | ^4.3 |
| React Router | ^7.18 |
| Axios | ^1.18 |

### Deploy (`deploy/`)
| Tool | Purpose |
|---|---|
| Python 3 + Paramiko | SSH deploy script |
| SCP | File transfer |
| cPanel | Hosting |

---

## Features

### 🔐 Authentication
- User registration (toggleable/disabled for single-tenant administration) and login with form validation
- Sanctum token-based API authentication with configurable 30-day expiry
- Rate-limited auth endpoints (10/min per email + 20/min per IP for login, 10/hour per IP for registration)
- Auto-logout on 401 responses with global Axios interceptor
- Expired tokens are rejected by Sanctum's guard on every request

### 🏪 Multi-Store Management
- Link multiple Shopee stores via OAuth 2.0 with CSRF-protected state parameter
- Reauthorize existing stores (`/api/shopee/reauthorize-url/{store}`) retaining products and order history
- Disconnect store tokens (`/api/shopee/disconnect/{store}`) while preserving store records and audit logs
- Token refresh error tracking (`last_refresh_attempt_at`, `last_refresh_error`, `refresh_attempts`)
- Visual store health indicators (🟢 healthy, 🟡 expiring soon, 🟠 refresh failed, 🔴 expired) with diagnostic tooltips
- Automated token refresh on API calls (near expiry) plus every 15 minutes via scheduled command
- Unlink stores with atomic cascade cleanup (token + store in one transaction)
- CORS configured to restrict API access to the frontend origin only

### 📦 Product Stock & Price Management
- View products across all stores with search and sort
- Filter by individual store
- Edit stock and price with inline modal forms
- **Variant support** — products with variants (model_id) have per-variant stock/price editing
- **Cross-store consolidated editing** — products sharing the same SKU across stores can be edited together
- "Set all stores to same stock" checkbox for bulk updates
- Stock displayed with color-coded badges (green > 10, amber 4–10, red ≤ 3)
- Pagination (50 per page) with page navigation

### 🔄 Cross-Store Sync
- When stock updates for a SKU'd product, all products with the same SKU across other stores are **queued** for automatic update
- Simple products matched by `item_sku`; variants matched by `model_sku`
- Sync runs asynchronously via `UpdateShopeeStockJob` (implementing `ShouldQueue`)
- Cross-store sync does NOT trigger further sync chains (prevents infinite loops)
- Inventory logs track sync source (`user`, `linked_sync`, `sync`, `order_webhook`)

### 📋 Order Management
- View orders from all stores in a unified table
- Filter by store and order status (9 statuses supported)
- Color-coded status badges
- Cancel orders with reason selection (Out of Stock, Undeliverable Area, Others)
- Order detail with items displayed inline
- Cursor-based pagination via Shopee API

### 📊 Dashboard
- Statistics cards: linked stores, orders today, pending orders, low stock count
- Low stock alert banner (links to Stock page)
- Store list with health badges and linking dates
- "Link Store" button triggers OAuth flow
- Skeleton loading states and error+retry handling

### ⏰ Scheduled Tasks
| Command | Schedule | Purpose |
|---|---|---|
| `shopee:refresh-tokens` | Every 15 minutes | Refresh expiring API tokens (backup to on-demand refresh) |
| `shopee:sync-products` | Every 30 minutes | Queue staggered paced product pulls (one job per store) |
| `shopee:sync-orders` | Every 15 minutes | Sync orders from the last 14 days |
| `sanctum:prune-expired` | Daily | Remove expired API tokens |
| OAuth state cleanup | Daily | Remove stale OAuth state records |

### 🛡️ Security

#### Authentication & Authorization
- Password hashed via Laravel's automatic `hashed` cast (bcrypt)
- Login returns generic `Invalid email or password` — no email enumeration
- Sanctum tokens created with configurable expiry (default 30 days, enforced per-request)
- Logout revokes the current token immediately
- Expired tokens are rejected before reaching any controller logic
- Registration rate-limited to 10 requests per hour per IP

#### API Security
- All mutation endpoints rate-limited (60/min per user)
- Product/order sync rate-limited (5/min per user)
- CORS configured with explicit `FRONTEND_URL` origin (no wildcard)
- Webhook signatures verified via HMAC-SHA256 on every request — no debug bypass
- Input validation on all API endpoints via FormRequest classes
- Stock/price values bounded (0–999999 for stock, 0–100000000 for price)

#### OAuth Flow Security
- Anti-CSRF state parameter: `Str::random(64)` → SHA-256 hash stored in database
- State is single-use: `consumed_at` timestamp set atomically inside `lockForUpdate()` transaction
- State expires after 10 minutes; stale states cleaned daily
- Shopee API tokens encrypted at rest using Laravel's `encrypt()` (AES-256-CBC)
- OAuth callback validates all parameters manually and redirects safely on error (no raw JSON exposed)

#### Store Management Security
- Multi-user isolation: every query scoped through `whereHas('store', user_id)` patterns
- Explicit ownership checks with `(int)` comparison guards
- Product authorization enforced via custom `ProductAuthorizationException` (403)
- Store unlink: authorization check before atomic transaction + cascade cleanup
- Store without a token is correctly reported as `expired` (not `healthy`)

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
- CI: dependency auditing (`composer audit`, `npm audit`) + gitleaks secret scanning on push

---

## Project Structure

```
stores/
├── backend/                    # Laravel PHP backend
│   ├── app/
│   │   ├── Console/
│   │   │   └── Commands/
│   │   │       ├── RefreshShopeeTokens.php    # Token refresh scheduler
│   │   │       ├── SyncShopeeOrders.php        # Order sync command
│   │   │       └── SyncShopeeProducts.php      # Product sync command
│   │   ├── Exceptions/
│   │   │   ├── ProductAuthorizationException.php
│   │   │   └── StoreNotActiveException.php
│   │   ├── Helpers/
│   │   │   └── ShopeeResponseHelper.php        # Shopee API response parsing
│   │   ├── Http/
│   │   │   ├── Controllers/Api/
│   │   │   │   ├── AuthController.php          # Login/register/logout
│   │   │   │   ├── DashboardController.php     # Dashboard stats
│   │   │   │   ├── OrderController.php         # Order CRUD + cancel
│   │   │   │   ├── ProductController.php       # Product CRUD + stock/price
│   │   │   │   ├── ShopeeAuthController.php    # OAuth linking
│   │   │   │   └── ShopeeWebhookController.php # Webhook handler
│   │   │   ├── Middleware/
│   │   │   │   └── SecurityHeaders.php         # Security headers middleware
│   │   │   ├── Requests/                       # Form validation requests
│   │   │   └── Resources/
│   │   ├── Jobs/
│   │   │   ├── SyncShopeeStoreJob.php          # Background store sync
│   │   │   ├── UpdateShopeePriceJob.php        # Price update job
│   │   │   └── UpdateShopeeStockJob.php        # Stock update + linked sync
│   │   ├── Models/
│   │   │   ├── InventoryLog.php                # Stock change audit trail
│   │   │   ├── Order.php / OrderItem.php       # Order models
│   │   │   ├── Product.php / ProductVariant.php# Product models
│   │   │   ├── ShopeeOAuthState.php            # OAuth state tracking
│   │   │   ├── ShopeeStore.php                 # Linked store
│   │   │   ├── ShopeeToken.php                 # Encrypted token storage
│   │   │   └── User.php                        # Sanctum HasApiTokens
│   │   └── Services/
│   │       ├── ShopeeService.php               # Shopee SDK wrapper
│   │       ├── ProductService.php              # Product business logic
│   │       └── OrderService.php                # Order business logic
│   ├── config/
│   │   ├── cors.php                            # CORS config (FRONTEND_URL)
│   │   └── shopee.php                          # Shopee API config
│   ├── database/
│   │   └── migrations/                         # 12 migration files
│   ├── routes/
│   │   ├── api.php                             # API routes
│   │   ├── web.php                             # SPA fallback route
│   │   └── console.php                         # Scheduler definitions
│   ├── tests/
│   │   └── Feature/
│   │       ├── AuthenticationTest.php          # Auth flow tests (10)
│   │       ├── SecurityHardeningTest.php       # Security regression tests (10)
│   │       └── ShopeeOAuthTest.php             # OAuth/store tests (8)
│   └── composer.json
│
├── frontend/                   # React + TypeScript SPA
│   └── src/
│       ├── api/
│       │   └── client.ts                       # Axios client + API functions
│       ├── components/
│       │   ├── ErrorBoundary.tsx                # React error boundary
│       │   ├── Layout.tsx                       # App shell (sidebar + header)
│       │   ├── ProtectedRoute.tsx               # Auth guard
│       │   └── StoreSwitcher.tsx                # Store dropdown
│       ├── context/
│       │   ├── AuthContext.tsx                   # Auth state management
│       │   ├── StoreContext.tsx                  # Store selection state
│       │   └── ToastContext.tsx                  # Toast notifications
│       ├── pages/
│       │   ├── Dashboard.tsx                    # Main dashboard
│       │   ├── Login.tsx                        # Login form
│       │   ├── Orders.tsx                       # Order list + cancel
│       │   ├── Register.tsx                     # Registration form
│       │   └── Stock.tsx                        # Product stock/price editing
│       ├── App.tsx                              # Router + providers
│       ├── main.tsx                             # Entry point
│       └── index.css                            # Tailwind + global styles
│
├── deploy/                      # Deployment automation
│   ├── deploy.py                               # SSH deployment script
│   ├── process_queue.py                         # Queue worker script
│   ├── SERVER_SETUP.md                          # cPanel setup guide
│   ├── deploy.config.json.example               # SSH/Git config template
│   └── .env.production.example                  # Production env template
│
├── Docs/                        # Project documentation
│   ├── PROJECT_PHASES.md                        # Development phase tracker
│   ├── AUDIT_REPORT.md                          # Full security audit
│   └── phase_3_improvements_plan.md
│
├── .env.test.example             # E2E test environment template
├── e2e-stock-flow.spec.ts       # Playwright E2E test (stock flow)
├── verify-cross-stock.spec.ts   # Playwright test (cross-stock)
├── verify-stock.spec.ts         # Playwright test (stock)
└── scrape-shopee-docs.spec.ts   # Playwright script (docs scrape)
```

---

## Database Schema

The database consists of **9 core tables**:

| Table | Purpose |
|---|---|
| `users` | User accounts (Sanctum API tokens) |
| `shopee_stores` | Linked Shopee stores (per user, unique per user+shop) |
| `shopee_tokens` | Encrypted OAuth tokens (per store, cascading delete) |
| `shopee_oauth_states` | One-time OAuth state parameters (SHA-256 hashed) |
| `products` | Synced product data (unique per store+item) |
| `product_variants` | Variant/model data for multi-variant products |
| `orders` | Synced order data (unique per store+order_sn) |
| `order_items` | Line items per order |
| `inventory_logs` | Audit trail for stock changes (nullOnDelete preserves history) |

### Key Relationships

- **User** 1→* **ShopeeStore** — one user can link many stores
- **ShopeeStore** 1→1 **ShopeeToken** — each store has one encrypted token record (cascade)
- **ShopeeStore** 1→* **Product** — products belong to a store (cascade)
- **Product** 1→* **ProductVariant** — products with variants have many model/variant entries (cascade)
- **ShopeeStore** 1→* **Order** — orders belong to a store (cascade)
- **Order** 1→* **OrderItem** — each order has line items (cascade)
- **Product** 1→* **InventoryLog** — stock changes are logged with before/after values (nullOnDelete preserves history)
- **User** 1→* **ShopeeOAuthState** — OAuth states belong to a user (cascade)

---

## Getting Started (Local Development)

### Prerequisites

- PHP ^8.2
- Composer
- Node.js ^20
- SQLite (for local dev) or MySQL
- Shopee Partner ID & Key (from [open.shopee.com](https://open.shopee.com))

### 1. Clone & Install Backend

```bash
git clone https://github.com/terranoss/stores.git
cd stores

# Install PHP dependencies
cd backend
composer install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database (SQLite for dev)
touch database/database.sqlite
php artisan migrate

# Create a symbolic link for storage
php artisan storage:link
```

### 2. Configure `.env`

```ini
APP_NAME=ShopeeManager
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

DB_CONNECTION=sqlite
# Or for MySQL:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=shopee_manager
# DB_USERNAME=root
# DB_PASSWORD=

SHOPEE_PARTNER_ID=your_partner_id
SHOPEE_PARTNER_KEY=your_partner_key
SHOPEE_REDIRECT_URL=http://localhost:8000/api/shopee/callback
SHOPEE_SANDBOX=true
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

### 5. Run Scheduled Tasks Locally

```bash
php artisan schedule:work
```

### 6. Run Tests

```bash
# Full test suite (30 tests, 123 assertions)
cd backend && php artisan test

# Run specific test file
php artisan test --filter=Authentication
php artisan test --filter=ShopeeOAuth
php artisan test --filter=SecurityHardening
```

---

## API Overview

All API routes are prefixed with `/api/`. Protected routes require a `Bearer {token}` Authorization header. CORS is configured to allow only the origin specified in `FRONTEND_URL`.

### Authentication

| Method | Endpoint | Auth | Throttle | Description |
|---|---|---|---|---|
| POST | `/api/register` | No | 10/hour per IP | Register a new user (name, email, password, confirmation) |
| POST | `/api/login` | No | 10/min per email + 20/min per IP | Login, returns Bearer token |
| POST | `/api/logout` | Yes | — | Revoke current Sanctum token |
| GET | `/api/me` | Yes | — | Get current user info (id, name, email, created_at) |

### Shopee Store Linking

| Method | Endpoint | Auth | Throttle | Description |
|---|---|---|---|---|
| GET | `/api/shopee/auth-url` | Yes | 20/min | Generate Shopee OAuth authorization URL |
| GET | `/api/shopee/callback` | No | 20/min | OAuth callback handler (public, Shopee redirects here) |
| GET | `/api/shopee/stores` | Yes | — | List linked stores with health status |
| DELETE | `/api/shopee/stores/{store}` | Yes | 60/min | Unlink a store (atomic delete + cascade) |
| POST | `/api/shopee/webhook` | No | — | Shopee webhook receiver (HMAC-SHA256 verified) |

### Products & Stock

| Method | Endpoint | Auth | Throttle | Description |
|---|---|---|---|---|
| GET | `/api/products` | Yes | — | List products (paginated, filterable by store/search) |
| GET | `/api/products/grouped` | Yes | — | List products grouped by SKU for cross-store editing |
| GET | `/api/products/{id}` | Yes | — | Get product with variants |
| PUT | `/api/products/{id}/stock` | Yes | 60/min | Update stock (Shopee API + local DB + queued linked sync) |
| PUT | `/api/products/{id}/price` | Yes | 60/min | Update price (Shopee API + local DB) |
| PUT | `/api/products/{id}/stock-price` | Yes | 60/min | Update both stock and price |
| PUT | `/api/products/stock-by-sku` | Yes | 60/min | Update stock for all products matching a SKU across stores |
| POST | `/api/products/pull` | Yes | 5/min | Queue catalog pull (`store_id` and/or `product_ids`); returns 202 |
| POST | `/api/products/push` | Yes | 5/min | Queue push of local stock to Shopee for `product_ids`; returns 202 |
| POST | `/api/products/sync` | Yes | 5/min | Alias of `/products/pull` (async) |

### Orders

| Method | Endpoint | Auth | Throttle | Description |
|---|---|---|---|---|
| GET | `/api/orders` | Yes | — | List orders (filterable by store/status) |
| GET | `/api/orders/{id}` | Yes | — | Get order with items |
| POST | `/api/orders/{id}/cancel` | Yes | 60/min | Cancel an order (reason required) |
| POST | `/api/orders/sync` | Yes | 5/min | Trigger order sync for all active stores |

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
   Edit both files with your production credentials.
   **Important:** Neither file is tracked by git — `.gitignore` covers both.

### Deploy

```bash
cd deploy
python deploy.py
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

The project has **42 backend PHPUnit tests** covering authentication, Shopee OAuth, store reauthorization, on-demand and scheduled token refresh, store management, and security hardening. All tests use an in-memory SQLite database, are fully deterministic, and require no external network access or real Shopee credentials.

### Backend Tests (PHPUnit)

```bash
cd backend
php artisan test
```

#### Authentication Tests (`tests/Feature/AuthenticationTest.php`) — 8 tests

| Test | What It Verifies |
|---|---|
| `test_user_can_register_successfully` | Valid registration returns 201 with token, user data, and hashed password |
| `test_duplicate_email_registration_is_rejected` | Same email returns 422 with validation error |
| `test_weak_password_is_rejected` | Password under 8 characters returns 422 |
| `test_user_can_login_successfully` | Valid credentials return 200 with token and user |
| `test_invalid_login_returns_generic_401` | Wrong password OR nonexistent email both return same message (no enumeration) |
| `test_me_requires_authentication` | No token → 401 |
| `test_me_returns_authenticated_user` | Valid Bearer token → 200 with correct user data |
| `test_logout_revokes_current_token` | Login→logout→re-login produces a different token; old token is removed from DB |
| `test_expired_token_is_rejected` | Token with past `expires_at` → 401 on protected route |
| `test_registration_is_rate_limited` | 11th registration from same IP in the same hour → 429 |

#### Shopee OAuth & Token Tests (`tests/Feature/ShopeeOAuthTest.php`) — 14 tests

| Test | What It Verifies |
|---|---|
| `test_unauthenticated_user_cannot_generate_auth_url` | No auth token → 401 on `/api/shopee/auth-url` |
| `test_authenticated_user_can_generate_auth_url` | Auth user gets a URL containing `partner_id=` |
| `test_expired_oauth_state_is_rejected` | OAuth state with past `expires_at` → redirect with `error=oauth_failed` |
| `test_reused_oauth_state_is_rejected` | OAuth state with `consumed_at` set → redirect with `error=oauth_failed` |
| `test_oauth_callback_validates_state_before_shopee_api_call` | Valid state is consumed atomically before any external API call |
| `test_store_and_token_can_be_created_and_encrypted` | Encrypted tokens are correctly persisted and decryptable |
| `test_store_listing_shows_only_owned_stores` | User A sees only their stores, not User B's |
| `test_store_listing_excludes_other_users_stores` | Multi-user isolation: User B's stores do not appear in User A's response |
| `test_refresh_failure_stores_error_and_increments_failure_count` | Refresh errors and failure counts are recorded on token refresh failure |
| `test_refresh_success_clears_failure_fields` | Successful token refresh resets error logging and failure counts |
| `test_refresh_store_token_persists_new_pair_and_clears_failures` | On-demand refresh stores new access/refresh tokens and clears cache |
| `test_for_store_refreshes_when_access_token_expired` | `forStore` refreshes before API use when access token is expired |
| `test_call_with_token_retry_refreshes_and_retries_without_disconnect` | Invalid access_token triggers refresh+retry instead of immediate disconnect |
| `test_refresh_tokens_command_schedule_is_every_fifteen_minutes` | Token refresh cron runs every 15 minutes |
| `test_store_listing_exposes_operational_status_but_not_tokens` | Store status endpoint exposes token health/error metadata without leaking raw tokens |
| `test_disconnect_keeps_store_products_orders_and_logs` | Disconnecting store revokes token but retains store records, products, and order logs |
| `test_reauthorize_creates_state_tied_to_intended_store` | Reauthorization URL generates OAuth state bound to the specific target store |
| `test_reauthorize_rejects_mismatched_shop_id` | OAuth callback rejects reauthorization attempts with non-matching Shopee shop IDs |

#### Security Hardening Tests (`tests/Feature/SecurityHardeningTest.php`) — 10 tests

| Test | What It Verifies |
|---|---|
| `test_new_tokens_expire_without_changing_login_response_contract` | Token expiry timestamp is set correctly (within configured window) |
| `test_login_is_rate_limited` | 11th failed login attempt → 429 (after 10 × 401) |
| `test_oauth_state_is_opaque_and_stored_with_an_expiry` | State is 64-char random, stored as SHA-256 hash, with future expiry |
| `test_invalid_oauth_state_redirects_without_leaking_details` | Bogus state redirects to frontend with `?error=oauth_failed` (no info leakage) |
| `test_security_headers_are_added` | Response includes `X-Content-Type-Options`, `X-Frame-Options`, and CSP header |
| `test_webhook_rejects_invalid_signature_even_in_debug_mode` | Invalid webhook signature → 401 even when `APP_DEBUG=true` |
| `test_store_without_token_shows_expired_health` | Store with no token returns `token_health: expired` (not healthy) |
| `test_oauth_callback_missing_code_redirects_with_error` | Missing `code` parameter → redirect with `?error=oauth_failed` (no raw JSON) |
| `test_unlink_removes_store_and_token` | Unlink deletes both the store record and its token |
| `test_unlink_forbidden_for_other_user` | User B cannot unlink User A's store → 403 |

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
- **API**: RESTful JSON responses, snake_case for backend (PHP/Laravel convention), camelCase for frontend (TypeScript convention)
- **Sync**: Queue jobs (`ShouldQueue`) for async cross-store sync; `dispatch()` instead of `dispatchSync()` to avoid blocking HTTP responses
- **Errors**: Graceful error states in all UI components with retry buttons; 401 auto-redirect to login via Axios interceptor; 429 retry with exponential backoff
- **Tests**: Feature tests with `RefreshDatabase`, in-memory SQLite, no external API calls, no production credentials

---

## Configuration Reference

### Backend `.env`

| Variable | Default | Description |
|---|---|---|
| `APP_NAME` | `ShopeeManager` | Application name |
| `APP_URL` | `http://localhost` | Public app URL |
| `FRONTEND_URL` | `http://localhost:5173` | Frontend URL for OAuth redirects and CORS |
| `DB_CONNECTION` | `sqlite` | Database driver |
| `SANCTUM_NEW_TOKEN_EXPIRATION` | `43200` | Token expiry in minutes (43200 = 30 days) |
| `SANCTUM_TOKEN_PREFIX` | `` | Token prefix for GitHub secret scanning detection |
| `QUEUE_CONNECTION` | `database` | Queue driver |
| `SHOPEE_PARTNER_ID` | — | Shopee Open Platform Partner ID |
| `SHOPEE_PARTNER_KEY` | — | Shopee Open Platform Partner Key |
| `SHOPEE_REDIRECT_URL` | `http://localhost:8000/api/shopee/callback` | OAuth callback URL (production: `https://mystores.melur.com/api/shopee/callback`) |
| `SHOPEE_SANDBOX` | `true` | Use Shopee sandbox environment |

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

---

## Security

A comprehensive security audit and hardening was completed in July 2026 (see `Docs/AUDIT_REPORT.md` and `Docs/COMPLETION_AUDIT.md`). Key findings and remediations:

### Critical Fixes Applied

| Area | Fix | Status |
|---|---|---|
| Webhook signature verification | Removed `APP_DEBUG` bypass — invalid signatures always return 401 | ✅ Deployed |
| Store health computation | Null token no longer shows as `healthy` — returns `expired` | ✅ Deployed |
| CORS configuration | Created explicit config with `FRONTEND_URL` origin + `HandleCors` middleware | ✅ Deployed |
| Queue dispatch | Changed `dispatchSync()` → `dispatch()` for all cross-store sync jobs | ✅ Deployed |
| Store unlink | Wrapped token + store deletion in `DB::transaction()` | ✅ Deployed |
| OAuth callback validation | Replaced `$request->validate()` with safe redirect on invalid params | ✅ Deployed |

### Security Architecture

- **Token storage**: Access and refresh tokens are encrypted via Laravel's `encrypt()` (AES-256-CBC) before persisting; APP_KEY required for decryption
- **OAuth flow**: Anti-CSRF state parameter is hashed (SHA-256), single-use with `lockForUpdate()`, expires in 10 minutes
- **Rate limiting**: ThrottleMiddleware on registrations, login (dual email+IP), OAuth, sync, and mutation endpoints
- **Input validation**: All API inputs validated via FormRequest classes; stock (0–999999) and price (0–100000000) bounded
- **HTTP security**: HSTS (1 year, includeSubDomains), CSP (report-only), X-Frame-Options (DENY), Referrer-Policy, Permissions-Policy, X-Content-Type-Options (nosniff)
- **CI security**: GitHub Actions runs `composer audit` and `npm audit` on push/PR; gitleaks secret scanning
- **Deployment security**: SSH host keys verified (`RejectPolicy`), shell inputs sanitized by regex, database backed up before deploy, deploy aborts if backup fails

---

## License

MIT — see [LICENSE](LICENSE) for details.

---

## Related Documentation

- [`Docs/SHOPEE_CONSOLE_CHECKLIST.md`](Docs/SHOPEE_CONSOLE_CHECKLIST.md) — Shopee console callback & webhook setup
- [`Docs/AUDIT_REPORT.md`](Docs/AUDIT_REPORT.md) — Full security audit with findings, severity, and remediation plan
- [`Docs/COMPLETION_AUDIT.md`](Docs/COMPLETION_AUDIT.md) — Verification of all security remediation work
- [`Docs/PROJECT_PHASES.md`](Docs/PROJECT_PHASES.md) — Full development phase tracker with task status
- [`Docs/phase_3_improvements_plan.md`](Docs/phase_3_improvements_plan.md) — Improvement roadmap
- [`deploy/SERVER_SETUP.md`](deploy/SERVER_SETUP.md) — cPanel server setup guide
- [`backend/composer.json`](backend/composer.json) — PHP dependencies
- [`frontend/package.json`](frontend/package.json) — Frontend dependencies
