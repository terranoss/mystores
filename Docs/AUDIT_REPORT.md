# Audit Report: Authentication and Multi-Store Shopee Management

**Project:** ShopeeManager (Laravel 12 + React 19 + TypeScript)  
**Date:** 2026-07-02  
**Scope:** Authentication, Multi-store Shopee OAuth linking, security, tests, deployment

---

## 1. Executive Summary

| Area | Status | Risk Level | Verdict |
|------|--------|------------|---------|
| Authentication | Partial Pass | **Critical** | Functional auth flow with standard Sanctum patterns, but secrets committed to git, no CORS config, CSP not enforced, token stored in localStorage (XSS), and inadequate test coverage. Cryptography is sound. |
| Multi-store Shopee linking | Partial Pass | **High** | OAuth state handling is correct with replay protection. Tokens encrypted at rest. Multi-user isolation is consistent. But health status has a bug, inline dispatch bypasses queue, webhook debug-mode bypass, and essentially no backend tests. |

---

## 2. Files Inspected

### 2.1 Backend

| File | Purpose |
|------|---------|
| `backend/routes/api.php` | All API route definitions |
| `backend/app/Http/Controllers/Api/AuthController.php` | Register, login, logout, me |
| `backend/app/Http/Controllers/Api/ShopeeAuthController.php` | OAuth auth URL, callback, stores, unlink |
| `backend/app/Http/Controllers/Api/ShopeeWebhookController.php` | Webhook handler |
| `backend/app/Http/Controllers/Api/DashboardController.php` | Dashboard stats |
| `backend/app/Http/Controllers/Api/ProductController.php` | Product CRUD |
| `backend/app/Http/Controllers/Api/OrderController.php` | Order management |
| `backend/app/Http/Requests/RegisterRequest.php` | Registration validation |
| `backend/app/Http/Requests/LoginRequest.php` | Login validation |
| `backend/app/Http/Requests/UpdateStockRequest.php` | Stock update validation |
| `backend/app/Http/Requests/UpdatePriceRequest.php` | Price update validation |
| `backend/app/Http/Requests/UpdateStockPriceRequest.php` | Combined update validation |
| `backend/app/Http/Middleware/SecurityHeaders.php` | Security headers middleware |
| `backend/app/Http/Resources/UserResource.php` | User API resource |
| `backend/app/Providers/AppServiceProvider.php` | Rate limiter registration |
| `backend/bootstrap/app.php` | Exception handling, middleware registration |
| `backend/app/Models/User.php` | User model |
| `backend/app/Models/ShopeeStore.php` | Store model |
| `backend/app/Models/ShopeeToken.php` | Token model |
| `backend/app/Models/ShopeeOAuthState.php` | OAuth state model |
| `backend/app/Models/Product.php` | Product model |
| `backend/app/Models/ProductVariant.php` | Product variant model |
| `backend/app/Models/Order.php` | Order model |
| `backend/app/Models/OrderItem.php` | Order item model |
| `backend/app/Models/InventoryLog.php` | Inventory audit log model |
| `backend/app/Services/ShopeeService.php` | Shopee API client wrapper |
| `backend/app/Services/ProductService.php` | Product business logic |
| `backend/app/Services/OrderService.php` | Order business logic |
| `backend/app/Exceptions/ProductAuthorizationException.php` | Custom exception |
| `backend/app/Exceptions/StoreNotActiveException.php` | Custom exception |
| `backend/app/Jobs/UpdateShopeeStockJob.php` | Stock update job |
| `backend/app/Jobs/UpdateShopeePriceJob.php` | Price update job |
| `backend/app/Jobs/SyncShopeeStoreJob.php` | Product sync job |
| `backend/app/Console/Commands/RefreshShopeeTokens.php` | Token refresh command |
| `backend/app/Console/Commands/SyncShopeeProducts.php` | Product sync command |
| `backend/app/Console/Commands/SyncShopeeOrders.php` | Order sync command |
| `backend/app/Helpers/ShopeeResponseHelper.php` | API response parser |
| `backend/config/sanctum.php` | Sanctum config |
| `backend/config/auth.php` | Auth config |
| `backend/config/shopee.php` | Shopee API config |
| `backend/config/queue.php` | Queue config |
| `backend/config/app.php` | App config |
| `backend/config/cache.php` | Cache config |
| `backend/composer.json` | Dependencies (Sanctum ^4.3) |
| `backend/.env` | Local env (tracked in git) |
| `backend/.env.example` | Example env |
| All migrations in `backend/database/migrations/` (10 files) | Database schema |
| `backend/database/factories/UserFactory.php` | User factory |
| `backend/routes/console.php` | Schedule definitions |

### 2.2 Frontend

| File | Purpose |
|------|---------|
| `frontend/src/api/client.ts` | Axios client with interceptors |
| `frontend/src/context/AuthContext.tsx` | Auth state management |
| `frontend/src/context/StoreContext.tsx` | Store state management |
| `frontend/src/components/ProtectedRoute.tsx` | Auth guard component |
| `frontend/src/components/Layout.tsx` | App layout |
| `frontend/src/components/StoreSwitcher.tsx` | Store filter dropdown |
| `frontend/src/components/ErrorBoundary.tsx` | Error boundary |
| `frontend/src/pages/Login.tsx` | Login page |
| `frontend/src/pages/Register.tsx` | Register page |
| `frontend/src/pages/Dashboard.tsx` | Dashboard page |
| `frontend/src/pages/Stock.tsx` | Stock management page |
| `frontend/src/pages/Orders.tsx` | Orders page |
| `frontend/src/App.tsx` | Root component |
| `frontend/vite.config.ts` | Vite config |

### 2.3 Tests

| File | Purpose |
|------|---------|
| `backend/tests/Feature/SecurityHardeningTest.php` | 5 auth/OAuth/header tests |
| `backend/tests/Feature/ExampleTest.php` | Placeholder |
| `backend/tests/Unit/ExampleTest.php` | Placeholder |
| `e2e-stock-flow.spec.ts` | Playwright E2E (production) |
| `verify-cross-stock.spec.ts` | Playwright E2E (production) |
| `verify-stock.spec.ts` | Playwright E2E (production, 5 subtests) |

### 2.4 Deployment & CI

| File | Purpose |
|------|---------|
| `deploy/deploy.py` | Deployment script |
| `deploy/process_queue.py` | Queue worker script |
| `deploy/deploy.config.json` | Live config with secrets (tracked in git) |
| `deploy/deploy.config.json.example` | Template |
| `deploy/.env.production` | Production env (tracked in git) |
| `deploy/.env.production.example` | Template |
| `deploy/SERVER_SETUP.md` | Server setup guide |
| `.github/workflows/security.yml` | CI workflow |
| `backend/public/.htaccess` | Public .htaccess |
| `backend/.gitignore`, root `.gitignore` | Git ignore rules |

---

## 3. Authentication Audit

### 3.1 What is implemented correctly

1. **Password hashing** — The `User` model uses Laravel 12's `'password' => 'hashed'` cast (`backend/app/Models/User.php:37`), which automatically bcrypt-hashes the password on set. The `AuthController::register()` passes raw password to `User::create()` and the cast handles it.

2. **Registration validation** — `RegisterRequest.php` enforces: name required (string, max:255), email required (string, email format, unique in `users` table), password required (string, min:8, confirmed). All appropriate Laravel validation rules.

3. **Login validation** — `LoginRequest.php` enforces: email required (email format), password required. No early-exit hints about which field is wrong.

4. **Login failure message** — The login method returns `401 Unauthorized` with a generic `'message' => 'Invalid credentials'`, regardless of whether the email exists or the password is wrong. No email enumeration.

5. **Sanctum token with configurable expiry** — `AuthController::createToken()` reads `config('sanctum.new_token_expiration', 43200)` and passes `$expiresAt` to `$user->createToken()`. The Sanctum migration has the `expires_at` column with an index. The `sanctum:prune-expired` cron runs daily to clean up.

6. **Logout revokes current token only** — `AuthController::logout()` calls `request()->user()->currentAccessToken()->delete()`, which is correct.

7. **Protected routes** — All protected routes use `->middleware('auth:sanctum')` in `routes/api.php`. Login, register, Shopee callback, and webhook are properly public.

8. **Rate limiting** — Login endpoint has two parallel limits: 10/min per email + 20/min per IP, preventing both credential stuffing against a single account and brute-force from one IP. Registration is 10/hour per IP. All mutation endpoints have per-user limits.

9. **Security headers** — `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` restricting camera/mic/geolocation, `Strict-Transport-Security` (1 year, includeSubDomains on HTTPS). CSP is set in report-only mode.

10. **Frontend 401 handling** — The Axios response interceptor (`frontend/src/api/client.ts`) catches 401 responses, clears token and user from localStorage, and redirects to `/login`. It does NOT redirect if already on `/login`.

11. **ProtectedRoute component** — Redirects to `/login` when no user is authenticated, shows spinner during loading state.

12. **No stack trace leakage** — The two custom exception renderers in `bootstrap/app.php` return safe JSON. In production mode (`APP_DEBUG=false`), Laravel's default handler does not expose stack traces.

### 3.2 Problems found

| Severity | Problem | Evidence | Impact | Fix |
|----------|---------|----------|--------|-----|
| **CRITICAL** | Sensitive credentials committed to git | `backend/.env` (Shopee Partner ID/Key), `deploy/.env.production` (DB password, Shopee credentials, APP_KEY), `deploy/deploy.config.json` (SSH passphrase, GitHub PAT), E2E tests (production login credentials). All tracked in git history. | Complete compromise of all production secrets. Anyone with repo access has MySQL password, Shopee API keys, SSH access to server, and GitHub PAT. | Rotate ALL secrets immediately. Remove secrets from git history with `git-filter-repo`. Add files to `.gitignore` properly. Enforce pre-commit secret scanning. Set credentials via environment variables on the server, not by uploading files. |
| **CRITICAL** | No `config/cors.php` published — using framework defaults with `allowed_origins = ['*']` and `supports_credentials = false` | `backend/config/cors.php` does not exist. Framework default has wildcard origin. Confirmed by searching the entire project. | While the SPA proxy handles dev, in production if the frontend is ever on a separate domain, the API would be fully open to all origins. No CORS error protection. | Publish `config/cors.php` with explicit allowed origins and appropriate settings. |
| **HIGH** | Token stored in localStorage (XSS vulnerable) | `frontend/src/context/AuthContext.tsx` uses `localStorage.setItem('token', token)`. `frontend/src/api/client.ts` reads `localStorage.getItem('token')`. | Any XSS vulnerability can exfiltrate the token permanently. Unlike httpOnly cookies, localStorage is accessible to any JS on the origin. | Use httpOnly cookies with Sanctum SPA authentication, or use a short-lived access token with a refresh token pattern. At minimum, ensure CSP is enforced (not report-only). |
| **HIGH** | CSP in Report-Only mode | `SecurityHeaders.php` uses `Content-Security-Policy-Report-Only` instead of `Content-Security-Policy`. No reporting endpoint (`report-uri`/`report-to`) is configured. | CSP violations are logged but never blocked. A successful XSS injection would not be prevented. | Switch to enforced `Content-Security-Policy`, or keep report-only but configure a reporting endpoint to actually monitor violations. Add nonce/hash for inline scripts. |
| **HIGH** | Login revokes ALL existing tokens (`tokens()->delete()`) | `AuthController.php:44`: `$user->tokens()->delete()` | User can only have one active session. Logging in from a new device silently invalidates all other sessions. This is a design choice but undocumented and potentially surprising. | Either document the single-session behavior, or use `currentAccessToken()->delete()` to only invalidate the current token when issuing a new one during login. |
| **MEDIUM** | No email verification | `User.php` has `MustVerifyEmail` commented out. No email_verified_at checks anywhere. Auth routes don't have `verified` middleware. | Users can register with any email, including others' emails. No confirmation prevents account takeovers via email. | Implement email verification or at minimum document the decision not to verify. |
| **MEDIUM** | HSTS only on HTTPS requests (`isSecure()` check) | `SecurityHeaders.php`: `if ($request->isSecure()) { ... HSTS ... }` | First HTTP request before an HTTPS redirect does not receive HSTS. A MitM on the first connection can prevent the upgrade. | Move HSTS to the web server level (.htaccess / nginx config) where it applies to all responses. |
| **MEDIUM** | Token prefix not set | `config/sanctum.php`: `'token_prefix' => env('SANCTUM_TOKEN_PREFIX', '')` | No prefix means GitHub secret scanning cannot detect leaked Sanctum tokens. | Set `SANCTUM_TOKEN_PREFIX=shp_` to enable secret scanning detection. |
| **MEDIUM** | No global API rate limiter configured | `backend/bootstrap/app.php` does not call `$this->throttleApi('api')` | No catch-all rate limit on the API group. Individual endpoint limits exist but a misconfigured route would be unprotected. | Add `->withThrottle('api')` to the API middleware group with a reasonable global limit. |
| **MEDIUM** | Webhook signature verification skipped in debug mode | `ShopeeWebhookController.php`: `if (config('app.debug') && !$verified) { Log::warning(...); }` — continues processing even when verification fails if debug is on. | If `APP_DEBUG=true` is ever deployed to production, webhook signature verification is bypassed. Anyone who knows the webhook URL can send arbitrary stock/price updates. | Remove the debug bypass. Always fail on unverified webhooks regardless of debug mode. |
| **LOW** | No validation for `password_confirmation` in the login flow | LoginRequest only validates email and password. Correct for login, but the register form on the frontend expects password confirmation. | Low impact — registration correctly requires confirmation. The frontend only sends confirmation on register, not login. | Already correct, but consider validating no unexpected fields are present. |
| **LOW** | `AuthController::register()` returns 201 but no Location header | Standard Laravel API pattern but REST best practice suggests returning a Location header with the new resource URL. | Client cannot easily discover the user resource URI. | Add a `Location` header pointing to `/api/me`. |
| **LOW** | Frontend 401 interceptor uses full page reload instead of React Router navigation | `client.ts`: `window.location.href = '/login'` | Causes full page reload, losing all React state. Slower UX. | Use React Router's `navigate('/login')` via an event/callback, or use Axios response interceptor that calls the auth context's logout. |

### 3.3 Missing tests

1. **Successful registration flow** — No test that POST `/api/register` with valid data returns 201 with token and user fields.
2. **Duplicate email registration** — No test that registering twice with the same email returns 422.
3. **Weak/invalid password** — No test that passwords shorter than 8 characters are rejected with 422.
4. **Successful login** — No test that POST `/api/login` with valid credentials returns 200 with a token.
5. **Invalid login (wrong password)** — No test that wrong credentials return 401.
6. **Invalid login (nonexistent email)** — No test that nonexistent email returns 401 (same message as wrong password).
7. **Token-protected route access** — No test that `/api/me` without token returns 401, and with valid token returns 200.
8. **Logout revokes token** — No test that using the same token after logout returns 401.
9. **Expired token rejection** — No test that an expired token is rejected. The existing test only checks the `expires_at` timestamp is set, not that it's enforced.
10. **Rate limit behavior** — The login rate limit test exists in `SecurityHardeningTest.php`. Missing: register rate limit, mutation rate limit, sync rate limit, oauth rate limit tests.
11. **Frontend auth flow tests** — No component tests for login/register form validation display, ProtectedRoute behavior, or AuthContext integration.

---

## 4. Multi-Store Shopee Management Audit

### 4.1 What is implemented correctly

1. **OAuth state generation** — `ShopeeAuthController::authUrl()` generates state via `Str::random(64)`, stores only `hash('sha256', $state)` in the database, never the raw state. 10-minute TTL on the state.

2. **OAuth state validation with replay protection** — `ShopeeAuthController::callback()` uses `DB::transaction()` with `lockForUpdate()` (pessimistic lock) when reading the OAuth state, preventing race conditions. Checks: state exists in DB, not consumed, not expired. Sets `consumed_at = now()` inside the same transaction.

3. **Stale state cleanup** — `routes/console.php` schedules daily deletion of states older than 1 day via `ShopeeOAuthState::where('expires_at', '<', now()->subDay())->delete()`.

4. **Token encryption at rest** — Both `access_token` and `refresh_token` are encrypted via Laravel's `encrypt()` before storage in `shopee_tokens`. Decrypted via `decrypt()` for use.

5. **Multi-user isolation** — Consistent `whereHas('store', fn($q) => $q->where('user_id', $user->id))` pattern across all services (`ProductService`, `OrderService`, `DashboardController`). Explicit identity checks like `(int) $product->store->user_id !== (int) $user->id` throw `ProductAuthorizationException`.

6. **Foreign key cascade chain** — Deleting a user or store properly cascades: `shopee_stores` → `shopee_tokens`, `products` → `product_variants`, `orders` → `order_items`.

7. **Inventory log preservation** — `inventory_logs` uses `nullOnDelete()` for both `product_id` and `product_variant_id`, ensuring audit trails survive deletions.

8. **Unique constraints prevent duplicates** — `UNIQUE('user_id', 'shop_id')` on `shopee_stores`, `UNIQUE('store_id', 'item_id')` on `products`, `UNIQUE('store_id', 'order_sn')` on `orders`. `updateOrCreate` handles idempotent updates.

9. **Webhook idempotency** — Cache-based dedup with `shopee:webhook:event_{eventId}` key, 24-hour TTL, returns 200 for duplicates.

10. **Webhook signature verification** — `hash_hmac('sha256', $url . '|' . $body, $partnerKey)` compared via `hash_equals()` (timing-safe) against the Authorization header.

11. **Cross-store sync loop prevention** — `UpdateShopeeStockJob` checks `$source !== 'linked_sync'` to prevent recursive sync propagation.

12. **Token refresh command** — `RefreshShopeeTokens.php` handles per-token errors gracefully (catches and continues), reactivates previously-deactivated stores on successful refresh, clears cached tokens.

13. **Rate limiting on mutation/sync endpoints** — Stock/price/unlink mutations limited to 60/min per user. Sync limited to 5/min per user. OAuth endpoints limited to 20/min.

### 4.2 Problems found

| Severity | Problem | Evidence | Impact | Fix |
|----------|---------|----------|--------|-----|
| **HIGH** | Store health defaults to `healthy` when store has no token | `ShopeeAuthController::stores()`: health set to `'healthy'` initially, downgraded to `'expired'` only if `!$store->is_active` or `$store->token->auth_expires_at->isPast()`. If `$store->token` is null, health stays `'healthy'`. | A newly created store row with no token shows as healthy when it should be expired. User expects sync to work but it will fail. | Add explicit check: `if (!$store->is_active \|\| !$store->token) { $health = 'expired'; }` |
| **HIGH** | `dispatchSync()` runs cross-store sync inline, blocking the HTTP response | `ProductService.php` lines ~179, ~233: `dispatchSync($user, ...)` instead of `dispatch()`. The `UpdateShopeeStockJob` implements `ShouldQueue` which suggests queue was intended. | Cross-store sync happens during the API request. For a store with many linked SKUs, this can cause multi-second API response times. Queue workers sit idle because jobs are never queued. | Change `dispatchSync()` to `dispatch()`. |
| **HIGH** | Webhook processing continues even when signature verification fails in debug mode | `ShopeeWebhookController.php`: `if (config('app.debug') && !$verified) { Log::warning(...); }` — does NOT return 401 in debug mode. | If `APP_DEBUG=true` is accidentally deployed, anyone can send fake webhooks to manipulate stock/price data. | Remove the debug mode bypass. Always return 401 on verification failure. |
| **MEDIUM** | OAuth validation errors return raw JSON 422 in the browser | `ShopeeAuthController::callback()` uses `$request->validate([...])` which throws `ValidationException`. This is NOT caught by the outer `catch (\Throwable $e)` block. | If Shopee sends invalid parameters in the callback redirect, the user sees raw JSON in their browser instead of being redirected to the frontend with a friendly error. | Manually validate and redirect: `if ($request->missing('code') \|\| ...) { return redirect("$frontendUrl/dashboard?error=oauth_failed"); }` |
| **MEDIUM** | Webhook cache dedup set AFTER processing, leaving a race window | `ShopeeWebhookController.php`: `Cache::put($cacheKey, true, ...)` is called at the end of successful processing, not before. | If a duplicate webhook arrives during processing (before the cache is set), it would not be deduplicated. | Set the cache key BEFORE processing begins, or as the first step inside the handler. |
| **MEDIUM** | No soft deletes — unlink is permanent with no recovery | All models use hard deletes. `ShopeeAuthController::unlink()` deletes token and store, which cascades to delete all products, variants, orders, order items. | A mistaken unlink permanently destroys all store data. No recovery. | Add soft deletes (`deleted_at`) to `shopee_stores` and related models, or implement a "disable" flow. |
| **MEDIUM** | Unlink flow not wrapped in a transaction | `ShopeeAuthController::unlink()`: `$store->token()->delete(); $store->delete();` — not wrapped in `DB::transaction()`. | If one delete fails but the other succeeds, data is inconsistent. Low probability but not atomic. | Wrap in `DB::transaction()`. |
| **MEDIUM** | No transaction in webhook handler | `ShopeeWebhookController.php` does not wrap DB writes in a transaction. | Partial writes on failure. If only some products/variants are updated before a crash, data is inconsistent. | Wrap the update block in `DB::transaction()`. |
| **MEDIUM** | Raw Shopee API response stored in `orders.raw_response` column | `SyncShopeeOrders.php` stores the full API response as JSON, which includes buyer personal data (name, address, phone). | If the database is compromised, buyer PII is exposed beyond what the application needs. GDPR/data minimization concern. | Remove `raw_response` column or redact PII fields before storing. |
| **LOW** | Access token expiry not checked in health computation | Health check uses `auth_expires_at` and `refresh_token_expires_at` but NOT `access_token_expires_at`. | Store could show healthy but have an expired access token (short-lived ~4 hours). Token refresh cron handles this, but if it fails, the store appears healthy but API calls fail. | Add `access_token_expires_at` check to health computation. |
| **LOW** | Store listing returns all stores including those without tokens | `ShopeeAuthController::stores()` eager-loads `shopeeStores` with `token` relationship, but does not filter out stores without tokens. | A store stuck in a half-linked state appears in the list with incorrect `'healthy'` status. | Handle the null token case in health status (see HIGH finding above). |

### 4.3 Missing tests

1. **Authenticated user can generate auth URL** — No test that GET `/api/shopee/auth-url` returns a redirect URL with a state parameter.
2. **Unauthenticated user cannot generate auth URL** — No test that the endpoint returns 401 without a valid token.
3. **Expired state rejected** — Existing test covers invalid state but not specifically expired state.
4. **Reused state rejected** — No test that a consumed state is rejected on second use.
5. **Callback links store to correct user** — No test that after a successful callback, the store is linked to the authenticated user.
6. **Duplicate shop linking** — No test that linking the same shop_id twice updates rather than duplicates.
7. **User cannot access another user's store** — No authorization test for stores endpoint.
8. **Unlink only for owner** — No test that user B cannot unlink user A's store.
9. **Unlink cleanup** — No test that unlink deletes token and cascades to products/orders.
10. **Product authorization** — No test that user B cannot access user A's product.
11. **Store health computation** — No test for health status logic (healthy, expiring_soon, expired).
12. **Webhook handling** — No test for webhook signature verification, dedup, or stock update.
13. **Token refresh command** — No test for `shopee:refresh-tokens` command behavior.

---

## 5. Production Readiness Verdict

| Requirement | Ready? | Reason |
|-------------|--------|--------|
| Safe login/register | Partial | Validation is correct, password is hashed, rate limiting is in place, no email enumeration. But no email verification, token stored in localStorage (XSS), and CSP is report-only. **Critical: secrets committed to git compromise the entire deployment.** |
| Token expiry enforcement | Yes | Sanctum token expiry is configured at 30 days via `SANCTUM_NEW_TOKEN_EXPIRATION=43200`, enforced by Sanctum's middleware on every request. Expired tokens are pruned daily. |
| Protected API routes | Yes | All sensitive routes are behind `auth:sanctum` middleware. Public routes are correctly minimal (register, login, Shopee callback, webhook). |
| Frontend 401 handling | Yes | Axios interceptor catches 401, clears localStorage, redirects to /login. No redirect loop (check on /login already). Retry on 429/5xx GET is a bonus. |
| Secure Shopee OAuth | Partial | State generation is cryptographically sound. Replay protection via `lockForUpdate()` + `consumed_at` is excellent. But: validation errors show raw JSON in callback redirect, debug mode bypasses webhook checks, cache dedup has race window. |
| Safe store unlinking | Partial | Authorization check exists. Cascade deletes are configured at database level. But: no transaction wrapping, no soft deletes (data is permanently lost), no backup/archive step before deletion. |
| Multi-user isolation | Yes | Consistent `whereHas('store', user_id)` pattern across all query methods. Explicit `(int) comparison` checks. Custom `ProductAuthorizationException`. Rate limiter keys are per-user. |
| Adequate tests | **No** | Only 5 meaningful backend tests (all in one file). Zero tests for product/order/dashboard endpoints. Zero tests for OAuth callback success flow. Zero tests for unlinking or authorization. Zero tests for queue jobs or console commands. Zero frontend component tests. E2E tests run against production with hardcoded credentials committed to git. |

---

## 6. Fix Priority

### Must fix before production

1. **Rotate ALL compromised secrets immediately.**
   - Change MySQL database password on production server.
   - Rotate Shopee Partner credentials.
   - Revoke the GitHub PAT (`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`).
   - Change SSH passphrase on the deploy key.
   - Change the production user password used in E2E tests.
   - Regenerate Laravel APP_KEY (note: will invalidate all encrypted Shopee tokens — must re-link stores).
   - Purge secrets from git history with `git-filter-repo` or BFG Repo-Cleaner.
   - Add pre-commit hook for secret detection (gitleaks, talisman).
   - Move deployment secrets to CI/CD environment variables or a secrets manager.

2. **Publish and configure `config/cors.php`** with explicit production origins.

3. **Fix webhook debug mode bypass** — Always reject unverified webhooks.

4. **Fix store health null-token bug** — Add explicit null check in health computation.

### Should fix soon

5. **Change `dispatchSync()` to `dispatch()`** for cross-store sync jobs.

6. **Fix OAuth callback validation** — Replace `$request->validate()` with manual validation that redirects on error.

7. **Add transaction to unlink flow** — Wrap token and store deletes in `DB::transaction()`.

8. **Move CSP from report-only to enforced mode** or configure a reporting endpoint.

9. **Set Sanctum token prefix** (`SANCTUM_TOKEN_PREFIX=shp_`).

10. **Add soft deletes to ShopeeStores** to prevent permanent data loss on accidental unlinking.

11. **Stop storing full `raw_response` in orders** — Redact or don't store buyer PII.

12. **Add comprehensive PHPUnit tests** covering auth flows, OAuth callback, store ownership, authorization, unlinking, webhook, token refresh.

### Nice to improve

13. **Move HSTS to server-level** (.htaccess/nginx) so it applies to all responses, not just HTTPS requests.

14. **Add global API rate limiter** — catch-all throttle on the API group.

15. **Change frontend 401 handling** — Use React Router navigation instead of full page reload.

16. **Consider httpOnly cookie tokens** instead of localStorage for improved XSS resistance.

17. **Add email verification** workflow.

18. **Document single-session behavior** of the login token revocation policy.

19. **Add CI job for running PHPUnit tests** on push/PR.

20. **Add CI job for frontend build verification.**

---

## 7. Exact Patch Plan

### Fix 1: Publish and configure CORS

**File to edit:** Create `backend/config/cors.php`

**What to change:**
```php
<?php
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:5173')],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
```

**Why it matters:** Prevents arbitrary origins from accessing the API. Currently the framework default has wildcard `allowed_origins = ['*']` which is unnecessarily permissive.

**Suggested test:** Assert that `Access-Control-Allow-Origin` header matches the configured frontend URL.

---

### Fix 2: Fix webhook debug mode bypass

**File to edit:** `backend/app/Http/Controllers/Api/ShopeeWebhookController.php`

**What to change:** Remove the debug-mode bypass. Always return 401 on verification failure.

**Current code:**
```php
if (config('app.debug') && !$verified) {
    Log::warning(...);
} elseif (!$verified) {
    return response()->json(['message' => 'Invalid signature'], 401);
}
```

**Replace with:**
```php
if (!$verified) {
    Log::warning(...);
    return response()->json(['message' => 'Invalid signature'], 401);
}
```

**Why it matters:** Debug mode should NEVER bypass security checks in a production-facing webhook endpoint. This is a direct path to unauthorized stock manipulation if `APP_DEBUG=true` is deployed.

**Suggested test:** `test_webhook_rejects_invalid_signature` — POST to webhook with invalid signature, assert 401.

---

### Fix 3: Fix store health null-token bug

**File to edit:** `backend/app/Http/Controllers/Api/ShopeeAuthController.php`

**What to change:** Add null-check for token before computing health.

**Current code:**
```php
$health = 'healthy';
if (!$store->is_active) {
    $health = 'expired';
} elseif ($store->token) {
    if ($store->token->auth_expires_at->isPast()) {
        $health = 'expired';
    } elseif (now()->diffInDays($store->token->refresh_token_expires_at, false) < 7) {
        $health = 'expiring_soon';
    }
}
```

**Replace with:**
```php
$health = 'healthy';
if (!$store->is_active || !$store->token) {
    $health = 'expired';
} elseif ($store->token->auth_expires_at->isPast()) {
    $health = 'expired';
} elseif (now()->diffInDays($store->token->refresh_token_expires_at, false) < 7) {
    $health = 'expiring_soon';
}
```

**Why it matters:** Prevents a store with no token from incorrectly showing as healthy. Without this, users see a healthy store but all API calls fail.

**Suggested test:** Verify a store without a token returns health = 'expired'.

---

### Fix 4: Fix OAuth callback validation

**File to edit:** `backend/app/Http/Controllers/Api/ShopeeAuthController.php`

**What to change:** Replace `$request->validate()` with manual validation that redirects on error instead of returning JSON.

**Current code:**
```php
$validated = $request->validate([
    'code' => 'required|string',
    'shop_id' => 'required|integer',
    'state' => 'required|string|size:64',
]);
```

**Replace with:**
```php
if ($request->missing('code') || $request->missing('shop_id') || $request->missing('state') || strlen($request->string('state')) !== 64) {
    Log::warning('Invalid OAuth callback parameters', $request->only('shop_id', 'state'));
    return redirect("$frontendUrl/dashboard?error=oauth_failed");
}
$validated = [
    'code' => $request->string('code'),
    'shop_id' => $request->integer('shop_id'),
    'state' => $request->string('state'),
];
```

**Why it matters:** The callback is a redirect-based flow — the user's browser arrives from Shopee. Returning JSON 422 shows raw error text in the user's browser instead of a friendly redirect.

---

### Fix 5: Change dispatchSync to dispatch

**File to edit:** `backend/app/Services/ProductService.php`

**What to change:** Replace `dispatchSync()` with `dispatch()` around lines 179 and 233.

**Current code:**
```php
\App\Jobs\UpdateShopeeStockJob::dispatchSync($user, $variant->product_id, $payload, 'linked_sync');
```

**Replace with:**
```php
\App\Jobs\UpdateShopeeStockJob::dispatch($user, $variant->product_id, $payload, 'linked_sync');
```

**Why it matters:** Cross-store sync potentially calls the Shopee API for multiple stores. Running synchronously blocks the HTTP response. The job class already implements `ShouldQueue`, confirming the intent was async dispatch.

**Note:** The queue worker must be running. The deploy script configures cron for `queue:work --stop-when-empty` every minute, which should handle queued jobs.

---

### Fix 6: Add transaction to unlink flow

**File to edit:** `backend/app/Http/Controllers/Api/ShopeeAuthController.php`

**What to change:** Wrap unlink operations in a transaction.

**Current code:**
```php
$store->token()->delete();
$store->delete();
```

**Replace with:**
```php
DB::transaction(function () use ($store) {
    $store->token()->delete();
    $store->delete();
});
```

**Why it matters:** Ensures atomicity. If one delete succeeds and the other fails, data remains consistent.

---

### Fix 7: Stop storing raw order API response

**File to edit:** `backend/app/Console/Commands/SyncShopeeOrders.php`

**What to change:** Remove `raw_response` from stored order data, or redact PII fields before storing.

**Suggested change:** Remove `raw_response => $orderDetail` from the stored data, or sanitize:
```php
$sanitized = $orderDetail;
unset($sanitized['buyer_user_id'], $sanitized['buyer_username'], $sanitized['recipient_address']);
$orderData['raw_response'] = $sanitized;
```

**Why it matters:** The full Shopee API response includes buyer personal data (name, address, phone). Storing this in plaintext creates GDPR/data minimization risk. The application never uses this data.

---

### Fix 8: Set Sanctum token prefix

**Files to edit:** `backend/.env` and `deploy/.env.production`

**What to change:** Add `SANCTUM_TOKEN_PREFIX=shp_` to both env files.

**Why it matters:** Enables GitHub secret scanning to detect leaked Sanctum tokens. Without a prefix, tokens are raw hex strings that no automated scanner recognizes.

---

### Fix 9: Move Webhook cache dedup to before processing

**File to edit:** `backend/app/Http/Controllers/Api/ShopeeWebhookController.php`

**What to change:** Set the cache key immediately when a new event is detected, before processing begins.

**Current:** `Cache::put($cacheKey, true, now()->addHours(24))` is at the very end of the handler.

**Replace with:** Set it at the start of handling (after the dedup check passes), and handle the edge case where processing fails:
```php
// After dedup check passes and before processing:
Cache::put($cacheKey, true, now()->addHours(24));

// ... processing ...
```

**Why it matters:** Currently there's a window between when processing starts and when the cache is set. A duplicate webhook arriving during processing would not be deduplicated.

---

### Fix 10: Rotate all compromised secrets

This is an operational fix, not a code change:

1. Rotate MySQL database password on production server.
2. Rotate Shopee Partner ID and Partner Key.
3. Revoke the GitHub PAT (`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`).
4. Change SSH passphrase on the deploy key.
5. Change the production user password used in E2E tests.
6. Regenerate Laravel `APP_KEY` (note: will invalidate all encrypted Shopee tokens — stores must be re-linked).
7. Purge secrets from git history with `git-filter-repo`.
8. Add `.env`, `.env.production`, `deploy.config.json` to `.gitignore` (already in gitignore for some, but must clean history).
9. Configure deployment to use environment variables on the server rather than uploading `.env.production` files.
10. Add pre-commit hook (gitleaks/talisman) to prevent future secret commits.
11. Update CI secret scanning to run with `--verbose` and verify it catches known patterns.

**Why it matters:** All production credentials are currently in a publicly-facing git repository with multiple contributors/history. This is the highest-priority issue.

---

*Audit completed 2026-07-02. See related files in `Docs/` for project context.*
