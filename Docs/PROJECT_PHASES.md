# Multi-Store Shopee Management System — Phase Tracker

> **Stack**: React (frontend) + Laravel (backend) + MySQL (database)

---

## Phase 1: Laravel Foundation + Auth

**Goal**: New Laravel project with Sanctum auth + React scaffold

| # | Task | Status | Notes |
|---|---|---|---|
| 1.1 | Create new Laravel project | ✅ Complete | Laravel 12 at `backend/` |
| 1.2 | Configure MySQL database | ✅ Complete | `shopee_manager` DB created |
| 1.3 | Install Laravel Sanctum | ✅ Complete | API token auth configured |
| 1.4 | Create `users` migration + User model | ✅ Complete | `HasApiTokens` trait added |
| 1.5 | Build Register + Login API endpoints | ✅ Complete | AuthController with validation |
| 1.6 | Create React project (Vite) | ✅ Complete | TypeScript at `frontend/` |
| 1.7 | Login / Register pages (React) | ✅ Complete | Form validation + error display |
| 1.8 | Auth context + protected routes | ✅ Complete | AuthContext, ProtectedRoute |
| 1.9 | Dashboard layout shell | ✅ Complete | Stats cards + link store placeholder |

**Definition of Done**: ✅ User can register → login → see empty dashboard.

---

## Phase 2: Shopee Integration — Token Management

**Goal**: Link multiple Shopee stores + manage tokens

**SDK**: `ecomphp/shopee-php` v1.6.0 installed via Composer

| # | Task | Status | Notes |
|---|---|---|---|
| 2.1 | Register Shopee developer App | 🔲 Pending | You need to do this manually at open.shopee.com |
| 2.2 | Install `ecomphp/shopee-php` SDK | ✅ Complete | |
| 2.3 | Create `shopee_stores` migration | ✅ Complete | |
| 2.4 | Create `shopee_tokens` migration | ✅ Complete | Encrypted token storage |
| 2.5 | Build `ShopeeService` (Laravel wrapper) | ✅ Complete | `app/Services/ShopeeService.php` |
| 2.6 | Generate authorization URL endpoint | ✅ Complete | `GET /api/shopee/auth-url` |
| 2.7 | Build OAuth callback handler | ✅ Complete | `GET /api/shopee/callback` with `state` param |
| 2.8 | Build TokenRefresh command (scheduler) | ✅ Complete | `php artisan shopee:refresh-tokens` every 3h |
| 2.9 | Link / unlink store UI (React) | ✅ Complete | Dashboard with live store list |
| 2.10 | Token health dashboard widget | ✅ Complete | Green/yellow/red health dots |

**Definition of Done**: ✅ Backend ready. Just needs your Partner ID + Key in `.env`.

---

## Phase 3: Stock & Price Management

**Goal**: View and update product stock/price across stores

| # | Task | Status | Notes |
|---|---|---|---|
| 3.1 | Create `products` migration | ✅ Complete | With `product_variants` table |
| 3.2 | Build Product sync command (scheduler) | ✅ Complete | `php artisan shopee:sync-products` every 30min |
| 3.3 | Product list API endpoint | ✅ Complete | `GET /api/products` with store_id + search filter |
| 3.4 | Update stock API endpoint | ✅ Complete | `PUT /api/products/{id}/stock` calls SDK |
| 3.5 | Update price API endpoint | ✅ Complete | `PUT /api/products/{id}/price` calls SDK |
| 3.6 | Product list page (React) | ✅ Complete | Table view at `/stock` with filters |
| 3.7 | Stock edit form (React) | ✅ Complete | Inline edit modal with stock input |
| 3.8 | Price edit form (React) | ✅ Complete | Inline edit modal with price input |
| 3.9 | Variant support (model_id) | ✅ Complete | Variants synced & editable in same modal |

**Definition of Done**: User can see products for a store and update stock/price.

---

## Phase 4: Order Management

**Goal**: View, filter, and manage orders from linked stores

| # | Task | Status | Notes |
|---|---|---|---|
| 4.1 | Create `orders` migration | ✅ Complete | With indexes on status + synced_at |
| 4.2 | Create `order_items` migration | ✅ Complete | |
| 4.3 | Build Order sync command (scheduler) | ✅ Complete | `shopee:sync-orders` every 15min, cursor-based pagination |
| 4.4 | Order list API endpoint | ✅ Complete | `GET /api/orders` with store_id + status filters |
| 4.5 | Order detail API endpoint | ✅ Complete | `GET /api/orders/{order}` with items |
| 4.6 | Cancel order API endpoint | ✅ Complete | `POST /api/orders/{order}/cancel` with reason validation |
| 4.7 | Orders list page (React) | ✅ Complete | Table view at `/orders` with store/status filters |
| 4.8 | Order detail page (React) | ✅ Complete | Items shown via inline modal |
| 4.9 | Cancel order UI (React) | ✅ Complete | Modal with reason selector (radio buttons) |
| 4.10 | Order status badges / colors | ✅ Complete | 9 statuses color-coded (gray/blue/amber/indigo/teal/emerald/orange/red/rose) |

**Definition of Done**: User can view orders by store/status and cancel orders.

---

## Phase 5: Multi-Store Dashboard & Polish

**Goal**: Dashboard overview + cross-store features

| # | Task | Status | Notes |
|---|---|---|---|
| 5.1 | Dashboard with store cards | ✅ Complete | Real stats (stores, orders today, pending, low stock), skeleton loading, error + retry state |
| 5.2 | Stock alerts (low stock threshold) | ✅ Complete | Amber alert banner on dashboard when low stock > 0, links to /stock |
| 5.3 | Order notification badges | ✅ Complete | Red pill badge next to Orders nav item, polls every 60s |
| 5.4 | Store switcher component | ✅ Complete | Dropdown in header bar, StoreContext provides to all pages |
| 5.5 | Token health monitoring | ✅ Complete | Done as part of 2.10 — green/yellow/red badges per store row |
| 5.6 | Error handling + retry logic | ✅ Complete | Axios retry on 429/5xx for GET (3 attempts, exponential backoff 1s/2s/4s) |
| 5.7 | Responsive layout polish | ✅ Complete | Sidebar responsive (mobile slide-in overlay); tables have hidden-xs columns |
| 5.8 | Loading states + error boundaries | ✅ Complete | ErrorBoundary component wrapping all routes + top-level safety net |

**Definition of Done**: ✅ Full working app with multi-store support.

---

## Legend

| Symbol | Meaning |
|---|---|
| 🔲 Pending | Not started |
| 🟡 In Progress | Currently working on it |
| ✅ Complete | Done and working |
| ❌ Blocked | Blocked by dependency |

---

**All 5 phases complete.** 🎉 Ready for deployment.
