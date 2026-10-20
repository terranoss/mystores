# MyStores — Agent Notes

Multi-marketplace (Shopee + Lazada) store management: Laravel 12 API, React 19 SPA, Flutter companion app.
`README.md` is the detailed reference (API tables, env vars, schema, cron), but it lags the code — trust `deploy/deploy.py`, `composer.json`, `phpunit.xml`, and `routes/` when they disagree.

## Layout

- `backend/` — Laravel 12 (PHP ^8.2), REST API only (`routes/api.php`), Sanctum bearer tokens, DB queue, scheduler in `routes/console.php`. First-run setup is the installer: `/install` + `/api/install/*` (`InstallController`).
- `frontend/` — React 19 + TS + Vite 8 + Tailwind 4 SPA. `src/api/client.ts` is the single Axios entrypoint (401 auto-logout + GET cache interceptors live there). `src/pages/InstallWizard.tsx` is the installer UI.
- `mobile/` — Flutter app hitting the same API. API base URL is user-supplied at login (`api_client.dart`). Push (OneSignal) and AdMob ads are build-flag configured, see Gotchas.
- `deploy/` — Python/Paramiko cPanel deploy. `scripts/` — release builder + Stacklane/Stripe/distribution automation. `demo/` — static interactive replica, not the app.
- `Docs/` — audits, distribution plans, and `Docs/agents/*` workflow docs (read-only reference). `.opencode/` + `.agents/` are agent config/skills, not application code.

## Commands

```bash
# root package.json proxies into frontend/
npm run build          # tsc -b && vite build -> frontend/dist
npm run dev

# backend
cd backend
composer dev           # serve + queue:listen + ../frontend vite (no scheduler)
php artisan test       # feature + unit suites, in-memory SQLite, no network
composer test          # config:clear then artisan test
php artisan test --filter=ShopeeOAuth
php artisan serve
php artisan queue:work --tries=1 --timeout=0
php artisan schedule:work   # local scheduler; composer dev does NOT start it

# frontend lint/typecheck: no separate typecheck script — `npm run build` runs `tsc -b`
cd frontend && npm run lint
```

Playwright specs at repo root (`e2e-stock-flow.spec.ts`, `verify-stock.spec.ts`, `verify-cross-stock.spec.ts`) read `TEST_BASE_URL` (default `http://localhost:5173`) and `TEST_USER_*`. They perform real stock edits, and `verify-cross-stock.spec.ts` SSHes to production via `deploy/process_queue.py` to drain the queue — only run against a dedicated test account when explicitly asked.

## Gotchas that will bite you

- **Install lock.** `App\Http\Middleware\CheckInstallation` (global) returns 503 for `api/*` / redirects other requests to `/install` unless `backend/storage/installed` exists. It is bypassed for `/up`, `/install*`, `/api/install*`, and `APP_ENV=testing`. For local dev create the empty file (PowerShell: `New-Item -ItemType File -Force backend/storage/installed`).
- **Registration is disabled.** `/api/register` is commented out in `routes/api.php` (405). Accounts are created by the installer (admin user + workspace) or `artisan tinker`.
- **`shopee_stores` / `ShopeeStore` hold both platforms.** Shopee and Lazada rows share the table; discriminate on `platform` (`products.platform` too). Don't assume "Shopee" in a class/table name means Shopee-only.
- **Rate limiters are named, not numeric.** In `AppServiceProvider`: `login` (10/min per email + 20/min per IP), `change-password` 5/min, `oauth` 20/min, `sync` 5/min, `mutation` 60/min. New write endpoints must carry `throttle:mutation` (or `throttle:sync`); installer endpoints are the numeric exception.
- **Async work is fire-and-forget.** Use `dispatch()`, never `dispatchSync()` — HTTP responses must not block on Shopee/Lazada calls. Cross-store SKU sync must not chain into itself.
- **Workspace scoping is not optional.** Tenant data goes through `Workspace*` services (`WorkspaceAccess`, `WorkspaceActivityLogger`, `WorkspaceNotificationService`, `WorkspaceConfig`); raw model queries leak other workspaces' data and are covered by `SecurityHardeningTest` / `WorkspaceAccessTest`.
- **`phpunit.xml` sets `QUEUE_CONNECTION=sync`.** Queued jobs execute inline during tests — assert side effects, or use `Queue::fake()`, rather than expecting deferred execution.
- **Prod queue/scheduler only run via cron** (`schedule:run` + `queue:work --stop-when-empty`, every minute, installed by `deploy.py`). Locally `composer dev` runs a queue listener but no scheduler; use `php artisan schedule:work`.
- **Deploy does not build the frontend.** `python deploy/deploy.py` (`npm run deploy` / `deploy.ps1`) uploads the existing `frontend/dist` and only warns if it is missing, so run `npm run build` first. It aborts if the MySQL backup fails, runs migrate/cache commands, touches `backend/storage/installed`, and installs crontab entries. Needs gitignored `deploy/deploy.config.json` + `deploy/.env.production` (copy from the `.example` files).
- **Frontend `dist` is served from `backend/public/`** in production; `vite build` output is what deploy copies.
- **CORS allows only `FRONTEND_URL`**, and Vite dev proxies `/api` to `localhost:8000`. A new frontend origin needs an env change, not a code change.
- **`frontend/src/version.ts` (`APP_VERSION`) must stay in sync with `frontend/package.json` version** — it is shown in the sidebar footer (`Layout.tsx`).
- **CI is effectively off.** `.github/workflows/security.yml` is `workflow_dispatch`-only. Run `php artisan test` and `npm run lint` locally before claiming a change works.
- **Mobile build flags, not `.env`.** OneSignal requires `--dart-define=ONESIGNAL_APP_ID=...`; ads stay on Google test IDs unless `--dart-define=LIVE_ADS=true` plus `ADMOB_BANNER_AD_UNIT_ID` / `ADMOB_INTERSTITIAL_AD_UNIT_ID`. Device push tokens register through `POST /api/devices`.
- **`scripts/` contains live commerce automation** (`update_stripe_price_live.py`, `update_stacklane_details.py`, `stacklane_apply_product.php`, `deploy_to_stacklane.py`, `update_myr_price.py`). Never run these without an explicit instruction; they mutate production Stripe/Stacklane state. `npm run package` opens an interactive Tkinter GUI — don't run it unattended.

## Conventions

- **No automatic case conversion.** API JSON and TS interfaces in `frontend/src/api/client.ts` are `snake_case`; helper params use camelCase but are explicitly mapped (`storeId` -> `store_id`). Dart models use camelCase and map snake_case JSON by hand.
- Platform-specific logic belongs in `ShopeeService` / `LazadaService` (+ `*PromotionService`, `LazadaOrderService`); shared flows go through `ProductService` / `OrderService`.
- Marketplace OAuth tokens are encrypted at rest; never log or return them.
- Route ordering matters in `routes/api.php`: literal paths such as `GET /products/grouped` are declared above `GET /products/{product}` on purpose — a new literal segment under a wildcard route will be swallowed by route-model binding and 404.
- Most tests use `RefreshDatabase` + in-memory SQLite, make no external API calls, and need no credentials.

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

When the user types `/graphify`, use the installed graphify skill or instructions before doing anything else.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- Dirty graphify-out/ files are expected after hooks or incremental updates; dirty graph files are not a reason to skip graphify. Only skip graphify if the task is about stale or incorrect graph output, or the user explicitly says not to use it.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).

## Agent skills

### Issue tracker

Issues live as markdown files under `.scratch/<feature>/`, not in GitHub Issues. See `Docs/agents/issue-tracker.md`.

### Triage labels

Default vocabulary: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `Docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `GLOSSARY.md` and `docs/adr/` at the repo root. See `Docs/agents/domain.md`.
