# Phase 3 Improvements Plan: Robust Stock & Price Management

This document outlines the implementation plan to upgrade the Stock & Price Management module (Phase 3). It combines structural hardening (correctness, maintainability, performance) with new feature work (SKU-based cross-store sync, audit logging, async processing, webhooks).

---

## Objectives

1. **Correctness & Data Integrity** — Validate Shopee API responses before updating local DB; wrap mutations in transactions; decouple business logic from HTTP so it works in both controllers and queue jobs.
2. **Batch API Optimization** — Replace 1-by-1 `getItemBaseInfo` calls with batch queries (50 items/call) to avoid Shopee rate limits and reduce sync time.
3. **Audit Logging** — `inventory_logs` table tracking every stock/price mutation with source, status, and error context.
4. **Asynchronous Processing** — Offload Shopee API writes to Laravel queue jobs with optimistic React UI updates.
5. **SKU-Based Cross-Store Sync** — Auto-propagate stock changes to matching SKUs across connected stores.
6. **Caching Layer** — Redis-backed cache for tokens, rate-limit state, catalog metadata, and webhook event dedup.
7. **Webhook Ingestion** — Receive Shopee stock-change notifications, deduplicate via event ID, and trigger local updates.
8. **UX Completeness** — Pagination, search, sortable columns, toast notifications, and extracted components.

---

## 1. Database Schema Changes

### 1.1 [MODIFY] `products` Table
- **Add**: `item_sku` (string, nullable, indexed)

### 1.2 [MODIFY] `product_variants` Table
- **Add**: `model_sku` (string, nullable, indexed)

### 1.3 [NEW] `inventory_logs` Table
Tracks all stock and price modifications.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| product_id | bigint FK nullable | |
| product_variant_id | bigint FK nullable | |
| sku | string nullable | |
| old_stock | integer nullable | |
| new_stock | integer nullable | |
| old_price | decimal nullable | |
| new_price | decimal nullable | |
| source | enum | `'user'`, `'order_webhook'`, `'sync'`, `'linked_sync'` |
| status | enum | `'pending'`, `'success'`, `'failed'` |
| error_message | text nullable | |
| created_at / updated_at | timestamps | |

### 1.4 [NEW] Database Indexes (standalone migration)
- `products`: index on `store_id`, `item_name`, `[store_id, stock]`
- `product_variants`: index on `product_id`

---

## 2. Caching & Rate Limiting Strategy

Uses **Redis** as the caching driver:

| Cache Key | Purpose | TTL |
|-----------|---------|-----|
| `shopee:token:shop_{shop_id}` | Decrypted access token (avoids DB lookup/decrypt on every call) | 3.5 hours |
| `shopee:ratelimit:shop_{shop_id}` | Token bucket for rate limiting outgoing Shopee API calls | Rolling |
| `shopee:webhook:event_{event_id}` | Deduplicate incoming Shopee webhook payloads | 24 hours |
| `shopee:store_{id}:catalog` | Static product metadata (names, categories, SKUs) | 2 hours |

---

## 3. Infrastructure Prerequisites

- **Redis** must be installed and configured as the cache driver (`CACHE_DRIVER=redis` in `.env`)
- **Queue worker** must be running (`php artisan queue:work` — the `composer.json` `dev` script already starts `queue:listen`)
- **Shopee developer app** must be configured with webhook URL pointing to `https://<domain>/api/shopee/webhook`

---

## 4. Implementation Steps

### Step 1: Foundation — Structural Cleanup

**Files affected:**
- `backend/app/Services/ProductService.php`
- `backend/app/Console/Commands/SyncShopeeProducts.php`
- `backend/app/Services/ShopeeService.php`
- `bootstrap/app.php`
- New: `app/Exceptions/StoreNotActiveException.php`
- New: `app/Exceptions/ProductAuthorizationException.php`
- New: `app/Http/Requests/UpdateStockRequest.php`
- New: `app/Http/Requests/UpdatePriceRequest.php`
- New: helper class (ShopeeResponseHelper or similar)

**Tasks:**

1.1 **Eliminate sync duplication** — Refactor `ProductService::syncForStore()` to accept optional `onProgress`/`onError` callables. Have `SyncShopeeProducts` command delegate to it instead of duplicating the loop.

1.2 **Decouple services from HTTP** — Replace `abort()` calls in `ProductService` with domain exceptions (`StoreNotActiveException`, `ProductAuthorizationException`). Register JSON renderers in `bootstrap/app.php` for consistent API error responses (404, 401, 429, generic 500).

1.3 **Extract validation to FormRequests** — Create `UpdateStockRequest` and `UpdatePriceRequest` with validation rules. Type-hint them in `ProductController`.

1.4 **Abstract response key resolution** — Create a helper with static methods (`extractItems()`, `extractItemData()`, `extractStock()`, `extractPrice()`, `extractHasVariants()`) to centralize the fragile `??` fallback chains.

1.5 **Database indexes** — Add indexes for `store_id`, `item_name`, `[store_id, stock]` on `products` and `product_id` on `product_variants`.

---

### Step 2: Batch API Syncing

**Files affected:**
- `backend/app/Services/ProductService.php`
- `backend/app/Console/Commands/SyncShopeeProducts.php`

**Tasks:**

2.1 Update `syncForStore()` to collect item IDs from `getItemList` into batches of 50.

2.2 Call `getItemBaseInfo` once per batch (the Shopee API accepts `item_id_list` as an array parameter) instead of once per item.

2.3 Save `item_sku` (from `item_data['item_sku']`) and `model_sku` (from `model['model_sku']`) during sync.

---

### Step 3: Data Integrity Guards

**Files affected:**
- `backend/app/Services/ProductService.php`

**Tasks:**

3.1 **Validate Shopee API responses** — After each `$client->Product->call(...)` in `updateStock()` and `updatePrice()`, check for error keys in the response. Throw on API failure. Only update local DB on success.

3.2 **Wrap in DB transactions** — Use `DB::transaction()` to make variant batch updates atomic. Log a warning if the DB write fails after a successful API call.

---

### Step 4: Database Migrations

**Files affected:**
- New migration: Add `item_sku` to `products`, `model_sku` to `product_variants`
- New migration: Create `inventory_logs` table

**Tasks:**

4.1 Run Step 2 first (so SKUs are populated on next sync), then add the columns + inventory_logs table.

4.2 `inventory_logs` schema as specified in the database section above.

---

### Step 5: Combined Stock-Price Endpoint

**Files affected:**
- `backend/routes/api.php` — new route
- `backend/app/Http/Controllers/Api/ProductController.php` — new method
- `backend/app/Services/ProductService.php` — new `updateStockAndPrice()` method
- `frontend/src/api/client.ts` — new API function
- `frontend/src/pages/Stock.tsx` or `frontend/src/components/stock/EditModal.tsx` — use single call

**Tasks:**

5.1 Add `PUT /products/{product}/stock-price` with `throttle:mutation`.

5.2 Controller method accepts both `stock_list` and `price_list`, delegates to `ProductService::updateStockAndPrice()`.

5.3 Service method calls both Shopee APIs (stock then price) inside a single transaction. If either fails, roll back the transaction and return error.

5.4 Frontend EditModal uses the combined endpoint instead of two sequential calls.

---

### Step 6: Queue / Async Processing

**Files affected:**
- New: `app/Jobs/UpdateShopeeStockJob.php`
- New: `app/Jobs/UpdateShopeePriceJob.php`
- New: `app/Jobs/SyncShopeeStoreJob.php` (for manual sync dispatch)
- `backend/app/Services/ProductService.php` — dispatch jobs instead of synchronous API calls (for mutations), or keep synchronous and add optional dispatch
- `backend/app/Http/Controllers/Api/ProductController.php` — return 202 for sync endpoint
- `backend/app/Services/ProductService.php::syncForUser()` — dispatch per-store jobs
- `frontend/src/pages/Stock.tsx` — handle 202 + polling

**Tasks:**

6.1 Create `UpdateShopeeStockJob` / `UpdateShopeePriceJob` — receive product ID and payload, call Shopee API, write audit log with result.

6.2 Create `SyncShopeeStoreJob` — receives a store ID, calls `ProductService::syncForStore()`.

6.3 Modify `syncForUser()` to dispatch one job per store instead of calling synchronously. Controller returns 202 with `{ "message": "Sync started for N stores." }`.

6.4 Record all job outcomes (success/failure payloads) in `inventory_logs`.

---

### Step 7: SKU-Based Cross-Store Sync

**Files affected:**
- `backend/app/Services/ProductService.php` — new `syncLinkedStores()` method
- New: `app/Jobs/LinkedStoreSyncJob.php`

**Tasks:**

7.1 Implement a lookup method to find all products/variants matching a given SKU across all stores.

7.2 After a stock update via `updateStock()`, check if the product/variant has a SKU. If so, dispatch `LinkedStoreSyncJob` for each matching product in other stores.

7.3 `LinkedStoreSyncJob` calls `updateStock()` (or the Shopee API directly) with the new stock value for the matched product/variant. Source is `'linked_sync'`.

7.4 Respect per-store token validity and active status.

---

### Step 8: Frontend UX Improvements

**Files affected:**
- `frontend/src/pages/Stock.tsx` — split out components, add features
- New: `frontend/src/components/stock/StockBadge.tsx`
- New: `frontend/src/components/stock/EditModal.tsx`
- New: `frontend/src/components/stock/index.ts`
- New: `frontend/src/components/Toast.tsx` + context
- `frontend/src/api/client.ts` — pagination metadata types

**Tasks:**

8.1 **Extract components** — Move `StockBadge` and `EditModal` into `components/stock/`. Add barrel export.

8.2 **Toast notifications** — Replace all `alert()` calls with a Toast system (success/error/info, auto-dismiss).

8.3 **Pagination controls** — Update `getProducts()` return type to include `current_page`, `last_page`, `total`. Add Previous/Next buttons and page info below the table.

8.4 **Search input** — Add a debounced (300ms) search field in the page header. Pass `search` param to `getProducts()`. Reset to page 1 on search change.

8.5 **Sortable columns** — Make table headers clickable. Toggle sort direction on click. Visual indicator for active sort column. Pass `sort_by`/`sort_dir` to `getProducts()`.

8.6 **Optimistic UI** — On the stock edit form, update local state instantly. Show a row-level spinner indicating the background queue job is processing. Transition to success/failure state.

---

### Step 9: Webhook Ingestion

**Files affected:**
- New: `app/Http/Controllers/Api/ShopeeWebhookController.php`
- `backend/routes/api.php` — public POST route
- Redis for event dedup

**Tasks:**

9.1 Create `POST /api/shopee/webhook` (public, signed by Shopee).

9.2 Extract `event_id` and check Redis — if already processed, return 200 immediately (idempotent).

9.3 Parse the payload: extract `item_id`, `model_id` (if variant), new stock/price values.

9.4 Update the local product/variant record.

9.5 If the item has a SKU, trigger cross-store sync (reuse Step 7 logic).

9.6 Store `event_id` in Redis (24h TTL) for deduplication.

---

### Step 10: Caching Layer

**Files affected:**
- `backend/app/Services/ShopeeService.php` — cache decrypted access tokens
- Redis store/cache config

**Tasks:**

10.1 Add token caching to `ShopeeService::forStore()` — cache `decrypt($store->token->access_token)` with 3.5h TTL, cache-bust on token refresh.

10.2 Add catalog caching — cache `Product` + `ProductVariant` data keyed by store with 2h TTL, invalidate on sync.

10.3 Implement token-bucket rate limiting for outgoing Shopee API calls (per-shop-id), keyed to Redis.

---

## 5. Recommended Build Order

| Phase | Steps | Rationale |
|-------|-------|-----------|
| **Phase A** | Step 1 (Foundation) | Prerequisite for everything — deduplication, service decoupling, validation extraction |
| **Phase B** | Step 2 (Batch sync) + Step 3 (Data integrity) | Performance + correctness block |
| **Phase C** | Step 4 (Migrations) + Step 5 (Combined endpoint) | DB schema + API consolidation |
| **Phase D** | Step 6 (Queue) + Step 8 (Frontend UX) | Async processing + user-visible improvements |
| **Phase E** | Step 7 (Cross-store sync) + Step 9 (Webhooks) | Feature work, depends on queue + SKU data |
| **Phase F** | Step 10 (Caching) | Optimization, saved for last |

---

## 6. Verification

1. **Sync parity** — Run `shopee:sync-products` and `POST /products/sync` against the same store; verify identical product/variant records including SKU fields.
2. **Batch performance** — Time sync of a store with 150+ products before and after batch optimization.
3. **API error format** — Hit endpoints with expired tokens, invalid product IDs, malformed payloads; confirm consistent JSON error shape.
4. **Data integrity** — Update stock + price, verify local DB matches Shopee API response, verify `inventory_logs` entry with correct old/new values.
5. **Queue processing** — Trigger sync via API, confirm 202 response, wait for queue worker to process, verify products updated.
6. **Cross-store sync** — Update stock on a product with matching SKU in another store, verify the linked store's stock also changes.
7. **Webhooks** — Send a mock Shopee webhook payload, verify local DB update and dedup (repeated payload is ignored).
8. **Frontend** — Test pagination (store with >50 products), search (partial match on item name), sort (click each column header), toast (trigger sync, confirm non-blocking notification).
9. **Frontend** — Open edit modal, modify stock/price, save; confirm single API call to combined endpoint, 1-second success state, and table refresh.
