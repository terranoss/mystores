# Store Linking Feature — Fix Plan & Implementation Roadmap

**Date:** 2026-10-08  
**Scope:** Store linking (Shopee & Lazada), token encryption & lifecycle, health badges, scheduled & on-demand token refresh, reauth/disconnect/unlink, vacation mode, workspace credential isolation  
**Target Stack:** Laravel 12 / PHP 8.2 (Carbon 3.13) backend, React 19 / TS frontend, Flutter 3 / Dart mobile  
**Baseline Test Status:** 185 tests passing (41 store-linking tests pass). All 23 defects empirically verified against the active codebase.  
**Current Test Status:** 209 tests passing (185 baseline + 12 in Phase 1 + 12 in Phase 2).  
**Execution Sizing Rule:** Broken into phases targeted at **~400 lines of code per phase** (including implementation and tests) for safe, incremental, and reviewable execution.  
**Execution Policy:** Human approval required before moving between phases. Run verification test suite after every task.

---

## 1. Executive Summary & Phased Sizing Matrix

| Phase | Priority | Target Scope | Est. Impl. LOC | Est. Test LOC | Total Est. LOC | Status |
|---|---|---|:---:|:---:|:---:|---|
| **Phase 1** | P0 (Critical) | Core Token Lifecycle & Concurrency Mutex | ~130 | ~240 | **~370 LOC** | `[x] Complete (197/197 passing)` |
| **Phase 2** | P1 (High) | OAuth Security, Platform Isolation & State Migration | ~220 | ~190 | **~410 LOC** | `[ ] Ready for Execution (Next)` |
| **Phase 3** | P2 (Medium) | Shared Health Service, Notifications & API Hardening | ~280 | ~170 | **~450 LOC** | `[x] Complete (228/228 passing)` |
| **Phase 4** | P2 (Medium) | Web Dashboard State Consolidation & UI Hardening | ~140 | ~250 | **~390 LOC** | `[ ] Queued` |
| **Phase 5** | P3 (Low) | Mobile Parity, Action Sheets & Serialization Resiliency | ~230 | ~150 | **~380 LOC** | `[ ] Queued` |
| **Phase 6** | Quality | End-to-End Regression Suite & Cross-Platform Integrity | ~0 | ~410 | **~410 LOC** | `[ ] Queued` |
| **Total** | | **Comprehensive Store Linking Remediation** | **~1,000** | **~1,380** | **~2,380 LOC** | |

---

## 2. Architecture & Execution Dependency Graph

```mermaid
flowchart TD
    subgraph Phase 1: COMPLETE [x] - P0 Core Token Lifecycle & Concurrency
        T11["Task 1.1: Carbon Operand Sign Fix [x]"]
        T12["Task 1.2: Shopee Workspace Scoping [x]"]
        T13["Task 1.3: Lazada Concurrency Lock [x]"]
        T14["Task 1.4: Lazada Refresh Error Tracking [x]"]
        T15["Task 1.5: Refresh Command Platform Filter [x]"]
        T16["Task 1.6: Scheduler Overlap Guards [x]"]
    end

    subgraph Phase 2: COMPLETE [x] - P1 OAuth Security & Migration
        T21["Task 2.1: OAuth State Platform Migration [x]"]
        T22["Task 2.2: OAuth State Model & Controller Scoping [x]"]
        T23["Task 2.3: Lazada Reauth Mismatch Guard [x]"]
        T24["Task 2.4: Cross-Platform Store Route Guards [x]"]
        T25["Task 2.5: Guard Callback Expire_in & Dynamic Refresh Expiry [x]"]
        T26["Task 2.6: Workspace Cache Key Scoping & Secret Protection [x]"]
    end

    subgraph Phase 3: COMPLETE [x] - P2 Shared Domain Health & API Hardening
        T11 --> T31[Task 3.1: Extract Unified StoreHealthService [x]]
        T31 --> T32[Task 3.2: Refactor ShopeeAuthController Health [x]]
        T31 --> T33[Task 3.3: Wire NotificationGeneratorService & Scheduler [x]]
        T34[Task 3.4: Harden WorkspaceCredentialController [x]]
        T35[Task 3.5: Sanitize Vacation Response & Disconnect Cache Reset [x]]
        T36[Task 3.6: Deduplicate Token Retry Helper across Services [x]]
    end

    subgraph Phase 4: ~390 LOC - P2 Web Dashboard UI Hardening
        T31 --> T41[Task 4.1: Web Dashboard isOwner Fail-Safe]
        T42[Task 4.2: Consolidate Dashboard Stores with StoreContext]
        T43[Task 4.3: HealthBadge Unhide & Fallback Styles]
        T44[Task 4.4: Synchronize Disconnect/Unlink/Vacation State]
    end

    subgraph Phase 5: ~380 LOC - P3 Mobile Parity & Resiliency
        T51[Task 5.1: Safe shop_id Parsing in Dart Model]
        T52[Task 5.2: Align Mobile Health Badge Fallbacks]
        T53[Task 5.3: Store Action Bottom Sheet in Flutter]
        T54[Task 5.4: Connect Disconnect/Unlink/Vacation in StoreProvider]
    end

    subgraph Phase 6: ~410 LOC - Regression Gate & Test Suite
        T61[Task 6.1: Lazada Auth Controller Full Test Suite]
        T62[Task 6.2: Lazada Token Refresh & Concurrency Suite]
        T63[Task 6.3: Multi-Tenant Workspace Credential Isolation Suite]
        T64[Task 6.4: Full Suite Pass >200 Tests]
    end

    Phase 1 --> Phase 2
    Phase 1 --> Phase 3
    Phase 3 --> Phase 4
    Phase 4 --> Phase 5
    Phase 2 --> Phase 6
    Phase 3 --> Phase 6
    Phase 5 --> Phase 6
```

---

## Phase 1: Core Token Lifecycle & Concurrency Engine (P0)
**Estimated Size:** ~370 LOC (Implementation: ~130 LOC, Tests: ~240 LOC)  
**Objective:** Eliminate token classification failure, single-use refresh race conditions, and cross-platform token pollution.

### [x] Task 1.1: Fix Carbon operand sign for store health badge
- **Ref:** Finding #1 (CRITICAL)
- **Target File:** [`backend/app/Http/Controllers/Api/ShopeeAuthController.php:556`](file:///d:/GitHub/mystores/backend/app/Http/Controllers/Api/ShopeeAuthController.php#L556)
- **Estimated LOC:** ~15 LOC impl, ~35 LOC test
- **Problem Statement:**  
  Carbon 3.13 `diffInSeconds($date)` is signed. Calling `$token->access_token_expires_at->diffInSeconds(now())` on a valid future token returns a negative number (e.g., `-10800`). Because `-10800 < 3600`, every healthy token is classified as `expiring_soon`, leaving `connection_status = 'active'` unreachable.
- **Code Changes:**
  ```php
  // ShopeeAuthController.php:556
  if ($token->access_token_expires_at->isPast()
      || now()->diffInSeconds($token->access_token_expires_at) < 3600) {
      return [
          'connection_status' => 'expiring_soon',
          'token_health' => 'expiring_soon',
          'requires_reauthorization' => false,
      ];
  }
  ```
- **Verification:** `php artisan test --filter=ShopeeOAuthTest` (assert `connection_status === 'active'` for tokens expiring in > 1 hour).

---

### [x] Task 1.2: Scope Shopee token refresh to store workspace
- **Ref:** Finding #2 (CRITICAL)
- **Target File:** [`backend/app/Services/ShopeeService.php:240-246`](file:///d:/GitHub/mystores/backend/app/Services/ShopeeService.php#L240-L246)
- **Estimated LOC:** ~15 LOC impl, ~45 LOC test
- **Problem Statement:**  
  `refreshStoreToken()` calls `$this->auth()`, which runs `configureClientForCurrentWorkspace()`. In CLI/cron contexts (`RefreshShopeeTokens`), `getCurrentWorkspaceId()` returns `null`, falling back to root `.env`. In multi-tenant environments, it uses the caller's session instead of `$store->workspace_id`.
- **Code Changes:**
  ```php
  // ShopeeService.php:240-246
  $refreshToken = decrypt($token->refresh_token);
  $newTokens = $this->withWorkspace($store->workspace_id, function () use ($refreshToken, $store) {
      return $this->auth()->refreshNewToken(
          $refreshToken,
          $store->shop_id
      );
  });
  ```
- **Verification:** Feature test verifying client configuration loads `$store->workspace_id` credentials during console refresh.

---

### [x] Task 1.3: Concurrency lock for Lazada single-use token refresh
- **Ref:** Finding #3 (CRITICAL)
- **Target File:** [`backend/app/Services/LazadaOrderService.php:556-584`](file:///d:/GitHub/mystores/backend/app/Services/LazadaOrderService.php#L556-L584)
- **Estimated LOC:** ~30 LOC impl, ~60 LOC test
- **Problem Statement:**  
  Lazada rotates refresh tokens on every refresh, invalidating the previous one immediately. `ensureFreshToken()` lacks mutex locking, allowing parallel requests to race and permanently burn the token.
- **Code Changes:**
  ```php
  public function ensureFreshToken(ShopeeStore $store): void
  {
      $lock = Cache::lock("lazada:token-refresh:store_{$store->id}", 30);

      $lock->block(15, function () use ($store) {
          $store->unsetRelation('token');
          $store->load('token');
          $token = $store->token;

          if (!$token) return;
          if ($token->access_token_expires_at && $token->access_token_expires_at->gt(now()->addMinutes(30))) {
              return;
          }
          if ($token->refresh_token_expires_at && $token->refresh_token_expires_at->isPast()) {
              throw new \RuntimeException("Store #{$store->id} Lazada refresh token expired. Please reconnect.");
          }

          $refreshToken = decrypt($token->refresh_token);
          // Token refresh logic...
      });
  }
  ```
- **Verification:** Unit test simulating 2 parallel refresh requests asserting the second waits and exits early without calling upstream API.

---

### [x] Task 1.4: Track Lazada refresh failures and extend refresh token expiry
- **Ref:** Finding #4 (CRITICAL)
- **Target File:** [`backend/app/Services/LazadaOrderService.php:571-584`](file:///d:/GitHub/mystores/backend/app/Services/LazadaOrderService.php#L571-L584)
- **Estimated LOC:** ~40 LOC impl, ~50 LOC test
- **Problem Statement:**  
  `refresh_token_expires_at` is never updated on successful refresh (causing disconnect after original 180 days). Errors are not caught, so `refresh_failure_count` and `last_refresh_failed_at` remain null.
- **Code Changes:**
  ```php
  $token->update(['last_refresh_attempt_at' => now()]);

  try {
      $data = $this->runWithWorkspace($store->workspace_id, fn () => $this->lazada->refreshToken($refreshToken));
      $refreshExpiresIn = (int) ($data['refresh_expires_in'] ?? 15552000);
      $token->update([
          'access_token' => encrypt($data['access_token']),
          'refresh_token' => encrypt($data['refresh_token'] ?? $refreshToken),
          'access_token_expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 2592000)),
          'refresh_token_expires_at' => now()->addSeconds($refreshExpiresIn),
          'auth_expires_at' => now()->addSeconds($refreshExpiresIn),
          'last_refreshed_at' => now(),
          'last_refresh_failed_at' => null,
          'last_refresh_error' => null,
          'refresh_failure_count' => 0,
      ]);
  } catch (\Throwable $e) {
      $token->forceFill([
          'last_refresh_failed_at' => now(),
          'last_refresh_error' => $e->getMessage(),
          'refresh_failure_count' => ($token->refresh_failure_count ?? 0) + 1,
      ])->save();
      throw $e;
  }
  ```
- **Verification:** Test verifying failure increments failure counter and success extends `refresh_token_expires_at`.

---

### [x] Task 1.5: Add platform filter to `shopee:refresh-tokens`
- **Ref:** Finding #5 (CRITICAL)
- **Target File:** [`backend/app/Console/Commands/RefreshShopeeTokens.php:16-25`](file:///d:/GitHub/mystores/backend/app/Console/Commands/RefreshShopeeTokens.php#L16-L25)
- **Estimated LOC:** ~15 LOC impl, ~40 LOC test
- **Problem Statement:**  
  Query queries all `ShopeeToken` without checking `platform === 'shopee'`. Inactive or expiring Lazada stores are picked up and fed to Shopee's SDK.
- **Code Changes:**
  ```php
  $expiringTokens = ShopeeToken::whereHas('store', fn ($q) => $q->where('platform', 'shopee'))
      ->where(function ($q) {
          $q->where(function ($sub) {
              $sub->where('access_token_expires_at', '<=', now()->addHour())
                  ->where('auth_expires_at', '>', now());
          })->orWhere(function ($sub) {
              $sub->whereHas('store', fn ($sq) => $sq->where('is_active', false))
                  ->where('auth_expires_at', '>', now());
          });
      })->get();
  ```
- **Verification:** Command test verifying non-Shopee stores are completely excluded.

---

### [x] Task 1.6: Prevent scheduler overlaps in background jobs
- **Ref:** Finding #6 (CRITICAL)
- **Target File:** [`backend/routes/console.php:12-26`](file:///d:/GitHub/mystores/backend/routes/console.php#L12-L26)
- **Estimated LOC:** ~15 LOC impl, ~10 LOC test
- **Problem Statement:**  
  Scheduled sync jobs run without mutex protection, risking overlapping runs on long synchronization cycles.
- **Code Changes:**
  ```php
  Schedule::command('shopee:refresh-tokens')->everyFifteenMinutes()->withoutOverlapping();
  Schedule::command('lazada:refresh-tokens')->hourly()->withoutOverlapping();
  Schedule::command('shopee:sync-products')->everyThirtyMinutes()->withoutOverlapping();
  Schedule::command('shopee:sync-orders')->everyFifteenMinutes()->withoutOverlapping();
  Schedule::command('lazada:sync-orders')->everyFifteenMinutes()->withoutOverlapping();
  ```
- **Verification:** Verify `php artisan schedule:list` registers mutex lock parameters.

### Phase 1 Verification Result (2026-10-08)

| Task | Test Coverage | Result |
|---|---|:---:|
| 1.1 | `ShopeeOAuthTest::test_store_listing_reports_active_for_healthy_token`, `..._reports_expiring_soon_for_token_inside_hour` | Pass |
| 1.2 | `ShopeeTokenRefreshTest::test_refresh_store_token_uses_store_workspace_credentials` | Pass |
| 1.3 | `LazadaTokenRefreshTest::test_second_concurrent_refresh_does_not_burn_the_rotated_token`, `..._reloads_token_relation_after_refresh`, `..._http_call_sets_timeouts_below_the_refresh_lock_ttl` | Pass |
| 1.4 | `LazadaTokenRefreshTest::test_ensure_fresh_token_extends_refresh_and_auth_expiry`, `..._records_failure_count_on_api_error`, `..._rejects_expired_refresh_token` | Pass |
| 1.5 | `ShopeeTokenRefreshTest::test_refresh_command_ignores_lazada_stores`, `..._refreshes_expiring_shopee_store` | Pass |
| 1.6 | `ShopeeTokenRefreshTest::test_scheduled_sync_commands_use_overlap_mutex` | Pass |

**Full backend suite:** `197 passed (825 assertions)` — 185 baseline + 12 new. No regressions.

**Implementation notes / deviations from plan:**
- Task 1.1 also guards a null `access_token_expires_at` to avoid a fatal on malformed token rows.
- Task 1.2 wraps the SDK call in a new private `ShopeeService::refreshViaApi()` helper that falls back to the ambient workspace when `$store->workspace_id` is null.
- Task 1.4 accepts both `refresh_expires_in` and `refresh_token_expires_in` from the upstream payload, defaulting to 180 days (15,552,000s).
- Task 1.6 additionally guards `picklist:cleanup-photos`, which shares the same hourly overlapping-run risk.
- Task 1.3 follow-up: the refresh mutex TTL is 120s (`LazadaOrderService::TOKEN_REFRESH_LOCK_SECONDS`), comfortably above the worst-case upstream round trip. `LazadaService::refreshToken` now pins `connectTimeout(10)` / `timeout(30)` explicitly so the bound does not depend on framework defaults, and a test asserts the lock TTL exceeds connect + request timeout.

---

## Phase 2: OAuth Security, Platform Isolation & State Migration (P1)
**Estimated Size:** ~410 LOC (Implementation: ~220 LOC, Tests: ~190 LOC)  
**Objective:** Enforce cryptographic OAuth state platform scoping, prevent credential spoofing, and isolate tenant secrets.

### [x] Task 2.1: Database migration: Add `platform` column to `shopee_oauth_states`
- **Ref:** Finding #12 (HIGH)
- **Target File:** `backend/database/migrations/2026_10_08_000001_add_platform_to_shopee_oauth_states_table.php` (New)
- **Estimated LOC:** ~35 LOC migration, ~15 LOC rollback
- **Code Changes:**
  ```php
  Schema::table('shopee_oauth_states', function (Blueprint $table) {
      $table->string('platform', 32)->default('shopee')->after('store_id')->index();
  });
  ```
- **Verification:** `php artisan migrate` and verify column exists in SQLite / MySQL schema.

---

### [x] Task 2.2: OAuth state model & controller platform scoping
- **Ref:** Finding #12 (HIGH)
- **Target Files:**
  - [`backend/app/Models/ShopeeOAuthState.php`](file:///d:/GitHub/mystores/backend/app/Models/ShopeeOAuthState.php)
  - [`backend/app/Http/Controllers/Api/ShopeeAuthController.php:85, 110`](file:///d:/GitHub/mystores/backend/app/Http/Controllers/Api/ShopeeAuthController.php#L85)
  - [`backend/app/Http/Controllers/Api/LazadaAuthController.php:65, 105`](file:///d:/GitHub/mystores/backend/app/Http/Controllers/Api/LazadaAuthController.php#L65)
- **Estimated LOC:** ~45 LOC impl, ~50 LOC test
- **Code Changes:**
  - Add `'platform'` to `$fillable` in `ShopeeOAuthState.php`.
  - In `ShopeeAuthController`: store `'platform' => 'shopee'` on create; query `where('platform', 'shopee')` on callback.
  - In `LazadaAuthController`: store `'platform' => 'lazada'` on create; query `where('platform', 'lazada')` on callback.
- **Verification:** Test asserting Lazada state passed to Shopee callback returns 400 invalid state error.

---

### [x] Task 2.3: Seller_id mismatch validation guard in Lazada reauth callback
- **Ref:** Finding #7 (HIGH)
- **Target File:** [`backend/app/Http/Controllers/Api/LazadaAuthController.php:131-146`](file:///d:/GitHub/mystores/backend/app/Http/Controllers/Api/LazadaAuthController.php#L131-L146)
- **Estimated LOC:** ~25 LOC impl, ~45 LOC test
- **Problem Statement:**  
  Reauthorizing store A with account B replaces store A's credentials with account B's tokens while retaining `shop_id` = A.
- **Code Changes:**
  ```php
  if ($isReauthorization) {
      $intendedStore = ShopeeStore::findOrFail($oauthState->store_id);
      if ((string) $intendedStore->shop_id !== (string) $sellerId) {
          Log::warning('Lazada reauthorization seller_id mismatch', [
              'intended_shop_id' => $intendedStore->shop_id,
              'returned_seller_id' => $sellerId,
          ]);
          return redirect()->away(
              config('app.frontend_url', 'http://localhost:5173') . '/dashboard?error=reauthorize_shop_mismatch'
          );
      }
  }
  ```
- **Verification:** Feature test simulating mismatched reauth seller ID redirects to error URL.

---

### [x] Task 2.4: Cross-platform store route guards
- **Ref:** Finding #8 (HIGH)
- **Target Files:**
  - [`backend/app/Http/Controllers/Api/ShopeeAuthController.php:306, 348, 376`](file:///d:/GitHub/mystores/backend/app/Http/Controllers/Api/ShopeeAuthController.php#L306)
  - [`backend/app/Http/Controllers/Api/LazadaAuthController.php:200, 230, 260`](file:///d:/GitHub/mystores/backend/app/Http/Controllers/Api/LazadaAuthController.php#L200)
- **Estimated LOC:** ~40 LOC impl, ~40 LOC test
- **Code Changes:**
  ```php
  // ShopeeAuthController
  if (($store->platform ?? 'shopee') !== 'shopee') {
      return response()->json(['message' => 'This store is not a Shopee store.'], 400);
  }

  // LazadaAuthController
  if ($store->platform !== 'lazada') {
      return response()->json(['message' => 'This store is not a Lazada store.'], 400);
  }
  ```
- **Verification:** Test verifying cross-platform reauth/disconnect requests return HTTP 400.

---

### [x] Task 2.5: Guard callback expire_in & retain Shopee refresh expiry
- **Ref:** Findings #9 & #11 (HIGH)
- **Target Files:**
  - [`backend/app/Http/Controllers/Api/ShopeeAuthController.php:148`](file:///d:/GitHub/mystores/backend/app/Http/Controllers/Api/ShopeeAuthController.php#L148)
  - [`backend/app/Services/ShopeeService.php:251`](file:///d:/GitHub/mystores/backend/app/Services/ShopeeService.php#L251)
- **Estimated LOC:** ~30 LOC impl, ~30 LOC test
- **Code Changes:**
  ```php
  // ShopeeAuthController.php:148
  if (empty($tokens['access_token']) || empty($tokens['refresh_token'])) {
      throw new \Exception('Incomplete token response from Shopee API.');
  }
  $accessTokenExpiresAt = now()->addSeconds((int) ($tokens['expire_in'] ?? 14400));

  // ShopeeService.php:251
  'refresh_token_expires_at' => !empty($newTokens['refresh_token_expire_in'])
      ? now()->addSeconds((int) $newTokens['refresh_token_expire_in'])
      : now()->addDays(30),
  ```
- **Verification:** Unit test asserting sandbox fallback to 14,400s and dynamic upstream refresh retention.

---

### [x] Task 2.6: Workspace cache key scoping & tenant secret protection
- **Ref:** Finding #10 (HIGH)
- **Target Files:**
  - [`backend/app/Services/ShopeeService.php:420-435`](file:///d:/GitHub/mystores/backend/app/Services/ShopeeService.php#L420-L435)
  - [`backend/app/Services/WorkspaceConfig.php:31-70`](file:///d:/GitHub/mystores/backend/app/Services/WorkspaceConfig.php#L31-L70)
- **Estimated LOC:** ~45 LOC impl, ~45 LOC test
- **Code Changes:**
  - Change cache key to `"shopee:token:ws_{$store->workspace_id}:shop_{$store->shop_id}"`.
  - In `WorkspaceConfig`: avoid caching raw decrypted plaintext secrets in global cache or encrypt cached payloads (`encrypt(serialize($result))`).
- **Verification:** Unit test verifying cache key format and checking cache payload structure.

### Phase 2 Verification Result (2026-10-08)

| Task | Test Coverage | Result |
|---|---|:---:|
| 2.1 | Migration `2026_10_08_000001_add_platform_to_shopee_oauth_states_table` tested in SQLite test runs & artisan migrate | Pass |
| 2.2 | `ShopeeOAuthTest::test_shopee_callback_rejects_lazada_oauth_state`, `LazadaAuthControllerTest::test_callback_rejects_shopee_oauth_state` | Pass |
| 2.3 | `LazadaAuthControllerTest::test_reauthorization_callback_aborts_on_seller_id_mismatch` | Pass |
| 2.4 | `ShopeeOAuthTest::test_shopee_endpoints_reject_lazada_store`, `LazadaAuthControllerTest::test_lazada_endpoints_reject_shopee_store` | Pass |
| 2.5 | `ShopeeOAuthTest::test_shopee_callback_guards_expire_in_with_fallback`, `ShopeeTokenRefreshTest::test_refresh_store_token_uses_store_workspace_credentials` | Pass |
| 2.6 | `ShopeeTokenRefreshTest::test_token_cache_scoped_by_workspace`, `ShopeeTokenRefreshTest::test_workspace_config_cache_is_encrypted_at_rest` | Pass |

**Full backend suite:** `209 passed (866 assertions)` — 197 prior + 12 new tests in `LazadaAuthControllerTest` and `ShopeeOAuthTest`. No regressions.
**Frontend build:** `npm run build` succeeds cleanly with 0 TypeScript/Vite errors.
**Mobile analyze:** `flutter analyze` passes cleanly with `No issues found!`.

**Implementation notes / deviations from plan:**
- Task 2.6: In `ShopeeService::clearStoreCache()`, cleared both the workspace-scoped key (`shopee:token:ws_{id}:shop_{id}`) and the legacy key (`shopee:token:shop_{id}`) to prevent stale cache entries during migration.
- Task 2.6: In `WorkspaceConfig::getCredential()`, cached configs are encrypted via Laravel's `encrypt(serialize(...))` and decrypted on cache hit so tenant API keys and app secrets are never stored plaintext in cache backends (Redis/file).
- Task 2.4: `ShopeeAuthController` endpoints for `reauthorize`, `disconnect`, and `unlink` now return `400 Bad Request` with an explanatory error message when invoked on a store whose platform is not `'shopee'`.
- Task 2.3: `LazadaAuthController::callback()` now redirects to `/dashboard?error=reauthorize_shop_mismatch` if the returned `seller_id` doesn't match the existing store's `shop_id`.

---

## Phase 3: Shared Domain Health Service, Notifications & API Hardening (P2)
**Estimated Size:** ~420 LOC (Implementation: ~280 LOC, Tests: ~140 LOC)  
**Objective:** Unify health computation across backend callers, wire dead notification pipelines, and harden credential APIs.

### [x] Task 3.1: Extract unified `StoreHealthService`
- **Ref:** Finding #13 (MEDIUM)
- **Target File:** [`backend/app/Services/StoreHealthService.php`](file:///d:/GitHub/mystores/backend/app/Services/StoreHealthService.php) (New)
- **Status:** Complete — `tests/Feature/StoreHealthServiceTest.php` (11 tests)
- **Code Changes:**
  `computeHealth(ShopeeStore $store): array` returns `['connection_status', 'token_health', 'requires_reauthorization']`.
  Thresholds are exposed as constants (`ACCESS_TOKEN_WARNING_SECONDS`, `REFRESH_TOKEN_WARNING_DAYS`,
  `REFRESH_FAILURE_THRESHOLD`). Helpers `requiresReauthorization()` and `isOperational()` were added for
  callers that only need the boolean.
- **Verification:** `php artisan test --filter=StoreHealthServiceTest` — 11 passing.

---

### [x] Task 3.2: Refactor `ShopeeAuthController` to use `StoreHealthService`
- **Ref:** Finding #13 (MEDIUM)
- **Target File:** [`backend/app/Http/Controllers/Api/ShopeeAuthController.php`](file:///d:/GitHub/mystores/backend/app/Http/Controllers/Api/ShopeeAuthController.php)
- **Status:** Complete
- **Code Changes:**
  `StoreHealthService` injected via constructor; the private 65-line `computeStoreHealth()` is deleted and
  `GET /api/shopee/stores` delegates to `computeHealth()`. The response shape is unchanged.
- **Verification:** `ShopeeVacationModeTest::test_store_listing_reports_health_from_shared_service`.

---

### [x] Task 3.3: Wire `NotificationGeneratorService` & scheduler
- **Ref:** Finding #13 (MEDIUM)
- **Target Files:**
  - [`backend/app/Services/NotificationGeneratorService.php`](file:///d:/GitHub/mystores/backend/app/Services/NotificationGeneratorService.php)
  - [`backend/routes/console.php`](file:///d:/GitHub/mystores/backend/routes/console.php)
- **Status:** Complete — `tests/Feature/StoreHealthNotificationTest.php` (5 tests)
- **Code Changes:**
  The stub `getStoreConnectionStatus()` (which hardcoded `'active'` and therefore never fired a notification)
  is deleted. `NotificationGeneratorService` now injects `StoreHealthService`, eager-loads `token`, and reads
  `computeHealth($store)['connection_status']`. The pipeline is scheduled:
  ```php
  Schedule::call(fn () => app(NotificationGeneratorService::class)->generateStoreHealthNotifications())
      ->name('store-health-notifications')
      ->dailyAt('08:00')
      ->withoutOverlapping();
  ```
- **Verification:** Notification dispatched for expiring / failing / token-less stores, deduplicated while unread,
  silent for healthy stores, and confirmed registered in the scheduler.

---

### [x] Task 3.4: Harden `WorkspaceCredentialController`
- **Ref:** Finding #16 (MEDIUM)
- **Target File:** [`backend/app/Http/Controllers/Api/WorkspaceCredentialController.php`](file:///d:/GitHub/mystores/backend/app/Http/Controllers/Api/WorkspaceCredentialController.php)
- **Status:** Complete — `tests/Feature/WorkspaceCredentialTest.php` (8 tests)
- **Code Changes:**
  1. `show()` now requires `roleInWorkspace(...) === 'owner'` → 403, matching `update()` / `destroy()`.
  2. Workspace resolution honours an explicit `X-Workspace-Id` header or `workspace_id` input, validated
     against the caller's memberships (non-member → 404, no cross-tenant leakage); otherwise it falls back to
     `primaryWorkspace()` / the personal workspace.
  3. Secrets are clearable: an explicit `""` or `null` now nulls the stored value instead of being ignored.
     The `[SET]` mask returned by `show()` is treated as "unchanged" so re-submitting the loaded form cannot
     overwrite a real secret with the placeholder.
- **Verification:** `WorkspaceCredentialTest` covers 403 for members, masked read, clearing via `""` and `null`,
  `[SET]` round-trip safety, workspace scoping, and cross-tenant rejection.

---

### [x] Task 3.5: Sanitize vacation response & disconnect cache reset
- **Ref:** Findings #17 & #18 (MEDIUM)
- **Target File:** [`backend/app/Http/Controllers/Api/ShopeeAuthController.php`](file:///d:/GitHub/mystores/backend/app/Http/Controllers/Api/ShopeeAuthController.php)
- **Status:** Complete
- **Code Changes:**
  - `getVacationMode()` no longer returns the raw upstream Shopee payload, which could carry seller metadata
    outside this endpoint's contract.
  - `disconnect()` resets `vacation_mode = false`, `vacation_mode_synced_at = null`, and calls
    `$this->shopee->clearStoreCache($store)` so a reconnect cannot resurrect a stale cached access token.
- **Verification:** `ShopeeVacationModeTest::test_vacation_mode_response_does_not_leak_raw_upstream_payload`
  and `..._disconnect_resets_vacation_state_and_clears_cached_token`.

---

### [x] Task 3.6: Deduplicate `callWithTokenRetry` helper across services
- **Ref:** Findings #19 & #20 (MEDIUM)
- **Target Files:**
  - [`backend/app/Services/ProductService.php`](file:///d:/GitHub/mystores/backend/app/Services/ProductService.php)
  - [`backend/app/Services/ShopeePromotionService.php`](file:///d:/GitHub/mystores/backend/app/Services/ShopeePromotionService.php)
  - [`backend/app/Services/ShopeeService.php`](file:///d:/GitHub/mystores/backend/app/Services/ShopeeService.php)
- **Status:** Complete
- **Code Changes:**
  The two duplicated `callWithTokenRetry()` / `isTokenError()` pairs (~70 lines) are deleted.
  `ShopeeService::callWithTokenRetry()` gained an optional `callable(string): \Throwable $failureFactory`
  parameter so callers keep throwing their own domain exception (`StoreNotActiveException`) while the
  refresh-and-retry state machine lives in one place. `ShopeeService::isTokenError()` is now public and is
  reused by `ProductService`'s pagination error handling.
- **Verification:** `ShopeeTokenRefreshTest` — delegation is asserted for both services, and the real
  retry/disconnect behaviour is exercised end-to-end against `ShopeeService`.

### Phase 3 Verification Result (2026-10-08)

| Task | Test Coverage | Result |
|---|---|:---:|
| 3.1 | `StoreHealthServiceTest` (11 cases: missing token, active, disconnected, access expiry, null expiry, refresh window, auth expiry, failure count, threshold) | Pass |
| 3.2 | `ShopeeVacationModeTest::test_store_listing_reports_health_from_shared_service` | Pass |
| 3.3 | `StoreHealthNotificationTest` (dispatch, dedup while unread, healthy silence, reauth reason, schedule registration) | Pass |
| 3.4 | `WorkspaceCredentialTest` (8 cases incl. member 403, clearing, `[SET]` round-trip, tenant scoping) | Pass |
| 3.5 | `ShopeeVacationModeTest::..._does_not_leak_raw_upstream_payload`, `..._resets_vacation_state_and_clears_cached_token` | Pass |
| 3.6 | `ShopeeTokenRefreshTest::test_promotion_service_delegates_token_retry_to_shopee_service`, `..._product_service_delegates_...`, `..._product_service_wraps_unrecoverable_token_failure_in_domain_exception` | Pass |

**Full backend suite:** `228 passed (915 assertions)` — 198 baseline + 30 new. No regressions.

**Implementation notes / deviations from plan:**
- The two `call_with_token_retry_*` tests in `ShopeeTokenRefreshTest` were rewritten. They previously mocked
  `ShopeeService` and asserted the copy-pasted retry loop; they now exercise the single canonical
  implementation, which is what actually ships.
- Task 3.4's "active workspace context" is implemented as an opt-in `X-Workspace-Id` header / `workspace_id`
  input validated against `WorkspaceMember`, since the codebase has no ambient active-workspace concept yet.
  No frontend change was required: `Settings.tsx` already gates the credential forms behind `isOwner`.
- Task 3.5 targeted only the Shopee controller. `LazadaAuthController::disconnect()` has no vacation mode and
  no Shopee token cache, so no equivalent change applies.

---

## Phase 4: Web Dashboard State Consolidation & UI Hardening (P2)
**Estimated Size:** ~390 LOC (Frontend Impl: ~140 LOC, Tests: ~250 LOC)  
**Objective:** Eliminate fail-open UI states, unify store state management with `StoreContext`, and show proper health badges.

### [ ] Task 4.1: Web Dashboard `isOwner` fail-safe
- **Ref:** Finding #14 (MEDIUM)
- **Target File:** [`frontend/src/pages/Dashboard.tsx:732, 757-761`](file:///d:/GitHub/mystores/frontend/src/pages/Dashboard.tsx#L732)
- **Estimated LOC:** ~20 LOC impl, ~40 LOC test
- **Problem Statement:**  
  `isOwner` initializes to `true` and treats `statsData.workspace?.role == null` as `true`. Members flash or retain owner controls.
- **Code Changes:**
  ```tsx
  const [isOwner, setIsOwner] = useState(false);
  // ...
  setIsOwner(
    statsData.workspace?.role === 'owner' ||
    user?.is_workspace_owner === true
  );
  ```
- **Verification:** Unit test asserting non-owner role does not render sensitive action buttons during loading or rendered state.

---

### [ ] Task 4.2: Consolidate Dashboard stores with `StoreContext`
- **Ref:** Finding #15 (MEDIUM)
- **Target File:** [`frontend/src/pages/Dashboard.tsx:730, 753`](file:///d:/GitHub/mystores/frontend/src/pages/Dashboard.tsx#L730)
- **Estimated LOC:** ~40 LOC impl, ~60 LOC test
- **Problem Statement:**  
  Dashboard maintains disconnected local store state, causing top navigation switcher to drift when stores are updated.
- **Code Changes:**
  Use `stores` and `refetchStores` from `useStore()` hook directly in `Dashboard.tsx`.
- **Verification:** Verify that mutating store in dashboard updates topbar store dropdown immediately.

---

### [ ] Task 4.3: HealthBadge unhide & fallback status styles
- **Ref:** Finding #21 (LOW)
- **Target File:** [`frontend/src/pages/Dashboard.tsx:185-215, 1214`](file:///d:/GitHub/mystores/frontend/src/pages/Dashboard.tsx#L185)
- **Estimated LOC:** ~35 LOC impl, ~50 LOC test
- **Code Changes:**
  - Remove `{store.connection_status !== 'expiring_soon' && ...}` suppression on line 1214.
  - Provide fallback in `HealthBadge` for undefined/future statuses:
    ```tsx
    const style = styles[status] || 'bg-gray-50 text-gray-500 border-gray-200';
    const label = labels[status] || status;
    ```
- **Verification:** Test asserting badge renders for all 6 connection statuses including `active` and `expiring_soon`.

---

### [ ] Task 4.4: Synchronize disconnect/unlink/vacation state handlers
- **Ref:** Findings #15 & #18 (MEDIUM)
- **Target File:** [`frontend/src/pages/Dashboard.tsx:1020-1090`](file:///d:/GitHub/mystores/frontend/src/pages/Dashboard.tsx#L1020)
- **Estimated LOC:** ~45 LOC impl, ~100 LOC test
- **Code Changes:**
  Ensure `handleDisconnect()`, `handleUnlink()`, and `handleToggleVacationMode()` call `refetchStores()` and show clear feedback toasts.
- **Verification:** Integration tests for disconnect and vacation toggles in `Dashboard.test.tsx`.

---

## Phase 5: Mobile Parity, Action Sheets & Serialization Resiliency (P3)
**Estimated Size:** ~380 LOC (Dart Impl: ~230 LOC, Tests: ~150 LOC)  
**Objective:** Prevent mobile serialization crashes, add store management actions to Flutter, and harmonize badges.

### [ ] Task 5.1: Safe shop_id parsing in Dart model
- **Ref:** Finding #23 (LOW)
- **Target File:** [`mobile/lib/core/models/store.dart:94-96`](file:///d:/GitHub/mystores/mobile/lib/core/models/store.dart#L94-L96)
- **Estimated LOC:** ~20 LOC impl, ~40 LOC test
- **Problem Statement:**  
  Direct cast `json['shop_id'] as int` crashes when values are returned as string or BigInt.
- **Code Changes:**
  ```dart
  id: json['id'] is int ? json['id'] as int : int.tryParse(json['id'].toString()) ?? 0,
  shopId: json['shop_id'] is int ? json['shop_id'] as int : int.tryParse(json['shop_id'].toString()) ?? 0,
  ```
- **Verification:** `flutter test test/models/store_test.dart` parsing numbers, strings, and missing IDs.

---

### [ ] Task 5.2: Align mobile health badge fallbacks & style
- **Ref:** Finding #21 (LOW)
- **Target File:** [`mobile/lib/features/dashboard/dashboard_screen.dart:779-820`](file:///d:/GitHub/mystores/mobile/lib/features/dashboard/dashboard_screen.dart#L779)
- **Estimated LOC:** ~40 LOC impl, ~30 LOC test
- **Code Changes:**
  Harmonize color tokens and labels with web (active = Emerald green, expiring = Amber, reauth = Rose red, disconnected = Slate gray).
- **Verification:** Widget test verifying health badge renders accurately for all enum values.

---

### [ ] Task 5.3: Store action bottom sheet in Flutter
- **Ref:** Finding #22 (LOW)
- **Target File:** `mobile/lib/features/dashboard/widgets/store_action_sheet.dart` (New)
- **Estimated LOC:** ~110 LOC impl, ~45 LOC test
- **Code Changes:**
  Create bottom modal sheet providing:
  - Re-link store button
  - Vacation mode toggle switch
  - Disconnect store with confirmation dialog
  - Permanent unlink store with safety prompt
- **Verification:** Widget test verifying modal triggers each action properly.

---

### [ ] Task 5.4: Connect mobile UI to `StoreProvider` actions
- **Ref:** Finding #22 (LOW)
- **Target Files:**
  - `mobile/lib/features/dashboard/dashboard_screen.dart`
  - `mobile/lib/core/providers/store_provider.dart`
- **Estimated LOC:** ~60 LOC impl, ~35 LOC test
- **Code Changes:**
  Hook store card trailing menu to `StoreProvider.disconnectStore()`, `unlinkStore()`, and vacation mode update.
- **Verification:** Integration test verifying tapping disconnect invokes provider and updates list.

---

## Phase 6: End-to-End Regression Suite & Cross-Platform Integrity (Quality)
**Estimated Size:** ~410 LOC (All Automated Tests & Verification)  
**Objective:** Close all 11 test gaps, guarantee multi-tenant security, and verify concurrency safety under load.

### [ ] Task 6.1: Lazada Auth Controller Full Test Suite
- **Ref:** Test Gap 2
- **Target File:** `backend/tests/Feature/LazadaAuthControllerTest.php` (New)
- **Estimated LOC:** ~150 LOC test
- **Test Cases:**
  - `test_redirect_generates_valid_oauth_state_with_lazada_platform`
  - `test_callback_exchanges_code_for_tokens_and_creates_store`
  - `test_callback_rejects_reauth_with_mismatched_seller_id`
  - `test_callback_rejects_state_from_different_platform`
  - `test_disconnect_deactivates_lazada_store_and_removes_token`

---

### [ ] Task 6.2: Lazada Token Refresh & Concurrency Suite (Expansion)
- **Ref:** Test Gaps 3 & 4
- **Target File:** `backend/tests/Feature/LazadaTokenRefreshTest.php`
- **Phase 1 Progress:** Seeded with 4 passing tests (mutex concurrency lock, expiry extension, failure counter, expired refresh token rejection).
- **Remaining Scope (~50 LOC):**
  - `test_scheduled_lazada_refresh_skips_valid_tokens`
  - `test_scheduled_lazada_refresh_processes_expiring_tokens`

---

### [ ] Task 6.3: Multi-tenant credential isolation & notification tests
- **Ref:** Test Gaps 5, 8, 9, 10
- **Target Files:**
  - `backend/tests/Feature/WorkspaceCredentialTest.php` (New)
  - `backend/tests/Feature/StoreHealthNotificationTest.php` (New)
  - `backend/tests/Feature/ShopeeVacationModeTest.php`
- **Phase 1 Progress:** Platform filter (`test_refresh_command_ignores_lazada_stores`), workspace scoping (`test_refresh_store_token_uses_store_workspace_credentials`), and scheduler overlap mutex tests were completed.
- **Remaining Scope (~100 LOC):**
  - `test_non_owner_forbidden_from_viewing_credentials`
  - `test_owner_can_clear_credentials`
  - `test_store_health_notification_dispatches_when_expiring`
  - `test_lazy_vacation_sync_on_active_healthy_store`

---

### [ ] Task 6.4: Cross-platform full regression gate
- **Estimated LOC:** Automated validation run across all suites
- **Execution Recipe:**
  ```bash
  # 1. Backend tests
  cd backend && php artisan test
  # 2. Web frontend build & test
  cd ../frontend && npm run build && npm test -- --run
  # 3. Mobile static analysis & tests
  cd ../mobile && flutter analyze && flutter test
  ```
- **Acceptance Criteria:** Total passing backend tests >200 (up from baseline 185). Zero TypeScript compiler errors. Zero Flutter analysis issues.

---

## 7. Rollback & Disaster Recovery Strategy

1. **Database Migration Rollback:**
   ```bash
   cd backend
   php artisan migrate:rollback --step=1
   ```
2. **Cache Invalidation:**
   If cache keys need flushing after deployment:
   ```bash
   cd backend
   php artisan cache:clear
   ```
3. **Rollback Safety Guarantee:**
   All database schema changes are additive (`platform` column with `'shopee'` default). Reverting code will not break existing tables or access tokens.
