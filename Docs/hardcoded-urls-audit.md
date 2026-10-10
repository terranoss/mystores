# Hardcoded URL Audit — Web + Flutter

**Date:** 2026-10-09
**Scope:** `frontend/` (React/Vite), `mobile/` (Flutter), `backend/config/`, `backend/app/Services/`
**Excluded:** `node_modules/`, `vendor/`, `dist/`, `build/`, `.dart_tool/`, lockfiles, generated assets

**Headline:** the production domain `mystores.melur.com` is hardcoded in **8 code locations** across
three layers (Flutter, frontend, backend config). One is a user-facing bug; the rest are
environment-variable defaults that fail silently and point non-prod deploys at production.

---

## 1. Critical — fix these first

### 1.1 `AuthDocs.tsx` shows production callbacks on every environment

`frontend/src/pages/AuthDocs.tsx:14-15`

```ts
const shopeeCallback = 'https://mystores.melur.com/api/shopee/callback';
const lazadaCallback = 'https://mystores.melur.com/api/lazada/callback';
```

These are rendered with a copy-to-clipboard button (lines 109, 112, 239, 242) and tell the user to
register that URL with Shopee and Lazada. On a staging deploy, the docs page still instructs users
to register the **production** callbacks. This is the only place in the frontend that hardcodes the
prod domain.

**Fix is straightforward** — the value is already available. `client.ts:1190,1193` defines
`WorkspaceCredentials.shopee_redirect_url` / `lazada_redirect_url`, returned by
`getWorkspaceCredentials()` (`client.ts:1198`). The page should read from that, falling back to a
neutral placeholder when unset. It currently bypasses an API that already exists.

### 1.2 Six duplicated prod-URL literals in backend config and services

| File | Line | Form |
|---|---|---|
| `backend/config/shopee.php` | 8 | `env('SHOPEE_REDIRECT_URL', <prod>)` |
| `backend/config/lazada.php` | 6 | `env('LAZADA_REDIRECT_URL', <prod>)` |
| `backend/app/Services/LazadaService.php` | 102 | `config('lazada.redirect_url', <prod>)` |
| `backend/app/Services/LazadaService.php` | 120 | `config('lazada.redirect_url', <prod>)` — duplicate |
| `backend/app/Services/WorkspaceConfig.php` | 64 | `config('lazada.redirect_url', <prod>)` |
| `frontend/src/pages/AuthDocs.tsx` | 14, 15 | bare literal (see 1.1) |

Every one of these resolves to production if the env var is unset. A developer running locally
without a `.env` override silently gets production OAuth callbacks — which fails confusingly at the
marketplace redirect step rather than at config load.

**Recommended fix:** make the PHP defaults `null` and add fail-fast validation (a `ServiceProvider`
boot check, or a `php artisan config:validate` command) that throws a clear error when
`SHOPEE_REDIRECT_URL` / `LAZADA_REDIRECT_URL` are missing. This is safe for local dev because
`deploy/.env.production` already sets both, and `backend/.env.example:85` documents the key.

**Note the in-repo precedent for the better pattern:** `ShopeeService.php` has **zero** hardcoded
URLs — it reads `config('shopee.redirect_url')` with no fallback. Following that pattern makes the
Lazada side consistent.

### 1.3 Live GitHub PAT in deploy config

`deploy/deploy.config.json:11`

```
"repo": "https://ghp_<REDACTED>@github.com/terranoss/mystores.git"
```

A personal access token is embedded directly in the remote URL. **This value is redacted in this
document deliberately.** The file is untracked by git, so it was never committed, but it sits in
plaintext on disk.

**Action: rotate the token.** Treat it as compromised regardless of git status, then move it to an
env var or SSH remote.

---

## 2. Worth fixing — environment coupling

### 2.1 Flutter API base URL has no env switching

`mobile/lib/core/api/api_endpoints.dart:5`

```dart
static const String baseUrl = 'https://mystores.melur.com/api';
```

A compile-time constant consumed by the single Dio instance at `api_client.dart:20` and logged at
`auth_provider.dart:70`. Every route in the same file is relative (`/login`, `/orders`, `/shopee/...`),
so this one line is the only thing pinning the app to production. There is no `--dart-define`, no
`.env`, and no dev/staging variant — **a debug build cannot point at a local backend.**

`grep` for `fromEnvironment` / `dotenv` across `lib/` returns no hits — the app has no runtime
configuration mechanism at all.

**Fix:** introduce an `API_BASE_URL` dart-define with the current URL as its `defaultValue`.

```dart
static const String baseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'https://mystores.melur.com/api',
);
```

This preserves current behavior for anyone who passes nothing, while unblocking `--dart-define`
for debug builds.

**Doc drift:** `mobile/README.md:31` says to point the app at your backend in
`lib/core/api/api_client.dart`. The constant actually lives in `api_endpoints.dart`. The README
path is stale.

### 2.2 Lazada host map ignores the sandbox flag

`backend/app/Services/LazadaService.php:22-28` hardcodes eight regional hosts
(`api.lazada.sg`, `.com.my`, `.co.th`, `.com.ph`, `.co.id`, `.vn`). `LAZADA_SANDBOX` exists
(`config/lazada.php:7`) but **no sandbox variant is consulted.** Setting `LAZADA_SANDBOX=true` with
sandbox credentials still sends production traffic to production Lazada hosts — which will fail auth
against the wrong key rather than revealing that the flag is inert.

The regional host map itself is reasonable to hardcode — those are third-party endpoints, not
project config. What's missing is a test-host branch.

### 2.3 Stock Laravel welcome page still deployed

`backend/resources/views/welcome.blade.php` is the untouched Laravel default, pulling
`fonts.bunny.net` (lines 10, 11) and linking laravel.com/laracasts/cloud.laravel.com. Harmless, but
it should be deleted or replaced so the default page isn't publicly reachable on the prod domain.

---

## 3. Cleanup — dead or misleading config

### 3.1 reCAPTCHA is configured but never used

Present in config, absent from all logic:

- `frontend/.env:1` and `frontend/.env.example:1` — `VITE_RECAPTCHA_SITE_KEY`
- `backend/.env.example:88-91` — `RECAPTCHA_ENABLED`, `RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY`

A search for `recaptcha` / `VITE_RECAPTCHA_SITE_KEY` across `frontend/src`, `index.html`, and
`vite.config.ts` returns **zero** hits. No script tag, no `grecaptcha.render()` call, and no server
-side verification endpoint.

Two problems: dead config, and `.env.example` is tracked by git while shipping a **real-looking**
site key rather than a placeholder. Either wire reCAPTCHA up or strip the keys from all three files
and substitute obvious placeholders in the example.

### 3.2 `frontend/.env` is not gitignored

`frontend/.gitignore` lists only `*.local`, so `.env` isn't covered. The file is currently untracked
(git confirms), so nothing leaked — but it's one `git add` away from being committed. Add an explicit
`.env` entry.

### 3.3 Stale env entries

- `backend/.env:86` — `ERP_API_BASE_URL=https://pos.melur.com`. Migration
  `2026_10_04_000001_drop_pos_inventories_table.php:21` records that the integration was removed.
- ~~`mobile/README.md:35-38` claims the Plant ID screen is inert without a key.~~ Resolved — the Plant ID
  feature and its README section have been removed.

---

## 4. Reference — hardcoded URLs that are appropriate

These are third-party endpoints or benign links. Listed so future audits don't re-flag them.

### 4.1 Third-party services

| Location | URL | Purpose |
|---|---|---|
| `frontend/index.html:8` | `https://fonts.googleapis.com` | preconnect |
| `frontend/index.html:9` | `https://fonts.gstatic.com` | preconnect (crossorigin) |
| `frontend/index.html:10` | `https://fonts.googleapis.com/css2?family=DM+Sans...&family=Poppins...` | Google Fonts |
| `backend/.../LazadaService.php:22-28` | `https://api.lazada.{sg,com.my,co.th,com.ph,com.id,vn}/rest` | Regional API bases |
| `backend/.../LazadaService.php:191,213,259` | `https://auth.lazada.com/...` | Lazada OAuth authorize/token |

Google Fonts is the **only** third-party runtime dependency in the frontend.

> Previously listed here were the Pl@ntNet endpoints (`plantnet_service.dart:18`,
> `plant_id_screen.dart:201`, `result_card.dart:21`). Those files were deleted along with the Plant ID
> feature, so the entries no longer apply.

### 4.2 Committed third-party API key

**Resolved — no key is committed anywhere in the repo.** This finding concerned
`mobile/lib/features/plant_id/services/plantnet_service.dart`, which shipped a borrowed PlantNet key
(`2b10JOrURQieOwcU9sxG5YAe`) as a hardcoded fallback. The entire Plant ID feature was removed, taking
the key with it.

If a similar third-party credential is ever reintroduced, do not commit it — read it from
`String.fromEnvironment` with no `defaultValue`.

### 4.3 Safe localhost defaults

All `env()`-overridable, all dev-only: `config/cors.php:20` (`http://localhost:5173`),
`config/sanctum.php:23`, `config/app.php:55-56`, `config/filesystems.php:44`, `config/mail.php:44,49`,
`config/cache.php:68`, `config/queue.php:49,60`, `config/database.php:50,70,90,105,158,171`.

`config/cors.php:31` sets `supports_credentials => false`, correct given Bearer-token auth.
**No wildcard origins** and no `allowed_origins_patterns` — good.

### 4.4 Documentation and infrastructure (non-code)

`README.md` (many lines), `Docs/marketplace-authentication-guide.md:58,79,107,126`,
`deploy/.env.production:6,7,35,36,41`, `deploy/.env.production.example`, `deploy/SERVER_SETUP.md`,
`deploy/process_queue.py:11` (SSH `cd` path), `deploy/deploy.config.json.example:15`,
`deploy.ps1:4`, `.env.test.example:6`, and
`.kilo/plans/1791155046087-remove-sandbox-production-local.md`.

These are config and docs, not application code. Correct to hardcode a production domain in a
production deploy manifest; just be aware that `.kilo/plans/1791155046087-*.md` documents a
deliberate decision to test **only** against production, which is consistent with §1.2 being a
low-urgency item if you genuinely only ever deploy once.

### 4.5 Correctly handled — no action needed

| Location | Detail |
|---|---|
| `frontend/src/api/client.ts:24` | `baseURL: '/api'` — relative. Prod is same-origin, so no host needed. |
| `frontend/vite.config.ts:10-13` | Dev proxy `/api` → `localhost:8000`. Correct, dev-only. |
| `backend/app/Services/ShopeeService.php` | Zero hardcoded URLs; derives `/api/v2/...` paths. Best pattern in the repo. |
| Marketplace credentials | All from `env()`. `Settings.tsx:132` masks `partner_key` as `[SET]` — keys never reach the browser. |
| OAuth auth URLs | Fetched at runtime via `/shopee/auth-url`, `/lazada/auth-url` (`store_provider.dart:22`, `dashboard_screen.dart:70-74`). |
| Flutter assets | No `assets:`/`fonts:` in `pubspec.yaml`; no fonts or images fetched from URLs. |
| `authenticated_network_image.dart` | Takes relative `path`, resolves through authenticated Dio. |
| Deep links / URL schemes | None. Android-only (no `ios/`); `AndroidManifest.xml` has only launcher + widget receivers, no `<data>` scheme. |
| SDK project IDs | No Firebase, Sentry, analytics, ads, or maps. No `google-services.json`. |
| WebSockets | No `ws://` / `wss://` anywhere. |
| `index.html` meta | No `og:` tags, no canonical, no analytics. Favicon is local. |

---

## 5. Prioritized action list

| # | Action | Files | Priority |
|---|---|---|---|
| 1 | Rotate leaked GitHub PAT; move to env/SSH | `deploy/deploy.config.json` | **Immediate** |
| 2 | Read callbacks from `getWorkspaceCredentials()` | `frontend/src/pages/AuthDocs.tsx` | **High** |
| 3 | Null the PHP prod defaults + fail-fast validation | `config/shopee.php`, `config/lazada.php`, `LazadaService.php`, `WorkspaceConfig.php` | **High** |
| 4 | Add `API_BASE_URL` dart-define | `mobile/lib/core/api/api_endpoints.dart` | Medium |
| 5 | Add sandbox hosts to Lazada map, or drop `LAZADA_SANDBOX` | `LazadaService.php:22-28` | Medium |
| 6 | Strip or wire up reCAPTCHA; placeholder the example key | `frontend/.env*`, `backend/.env.example` | Medium |
| 7 | Add `.env` to `frontend/.gitignore` | `frontend/.gitignore` | Low |
| 8 | ~~Move PlantNet key to required dart-define~~ — resolved, Plant ID feature deleted | `plantnet_service.dart` | ~~Low~~ Done |
| 9 | Remove `ERP_API_BASE_URL` | `backend/.env:86` | Low |
| 10 | Delete stock `welcome.blade.php` | `backend/resources/views/` | Low |
| 11 | ~~Fix stale README paths / inert-screen claim~~ — done; Plant ID docs removed | `mobile/README.md` | ~~Low~~ Done |

---

## 6. Re-audit commands

```powershell
# production domain, code only (exclude deploy manifests and docs)
Select-String -Path backend\config\*.php,backend\app\Services\*.php,frontend\src\**\*.ts*,mobile\lib\**\*.dart `
  -Pattern 'mystores\.melur\.com'

# all absolute URLs in Flutter source
Select-String -Path mobile\lib\**\*.dart -Pattern 'https?://'

# all absolute URLs in frontend source
Select-String -Path frontend\src\**\*.ts*,frontend\index.html -Pattern 'https?://'

# catch committed secrets
git ls-files | Select-String -Pattern '\.env$'
```