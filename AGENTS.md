# MyStores — Agent Notes

Multi-marketplace (Shopee + Lazada) store management: Laravel 12 API, React 19 SPA, Flutter companion app.
`README.md` is the detailed reference (API tables, env vars, schema, cron). This file is only the stuff that is easy to get wrong.

## Layout

- `backend/` — Laravel 12 (PHP ^8.2), REST API only (`routes/api.php`), Sanctum bearer tokens, DB queue, scheduler in `routes/console.php`.
- `frontend/` — React 19 + TS + Vite 8 + Tailwind 4 SPA. `src/api/client.ts` is the single Axios entrypoint (401 auto-logout interceptor lives there).
- `mobile/` — Flutter app hitting the same API. API base URL is user-supplied at login (`api_client.dart`).
- `deploy/` — Python/Paramiko cPanel deploy. `scripts/` — release builder + Stacklane/Stripe/distribution automation. `demo/` — static interactive replica, not the app.
- `Docs/` — audits and distribution plans (read-only reference).

## Commands

```bash
# root package.json just proxies into frontend/
npm run build          # tsc -b && vite build  -> frontend/dist
npm run dev

# backend
cd backend
composer dev           # serve + queue:listen + ../frontend vite, concurrently
php artisan test       # 38 feature files, in-memory SQLite, no network needed
composer test          # config:clear then artisan test
php artisan test --filter=ShopeeOAuth
php artisan serve && php artisan queue:work --tries=1 --timeout=0

# frontend lint/typecheck: there is no separate typecheck script — `npm run build` runs `tsc -b`
cd frontend && npm run lint
```

Playwright specs live at repo root (`e2e-stock-flow.spec.ts`, `verify-stock.spec.ts`, `verify-cross-stock.spec.ts`). They default to the **live** production URL and perform real stock edits — only run when explicitly asked, with a dedicated test account.

## Gotchas that will bite you

- **Install lock.** `App\Http\Middleware\CheckInstallation` (global) returns 503 / redirects to `/install` for every request unless `backend/storage/installed` exists. It is bypassed when `APP_ENV=testing`. For local dev: `touch backend/storage/installed`.
- **Registration is disabled.** The `/api/register` route is commented out in `routes/api.php` (returns 405). Accounts come from the installer or `artisan tinker`.
- **`shopee_stores` / `ShopeeStore` hold both platforms.** Shopee and Lazada rows share the table; discriminate on the `platform` column (`products.platform` too). Don't assume "Shopee" in a class/table name means Shopee-only.
- **Rate limiters are named, not numeric.** `login`, `register`, `change-password`, `oauth`, `sync` (5/min), `mutation` (60/min) are defined in `AppServiceProvider`. New write endpoints must carry `throttle:mutation` (or `throttle:sync`).
- **Async work is fire-and-forget.** Use `dispatch()`, never `dispatchSync()` — HTTP responses must not block on Shopee/Lazada calls. Cross-store SKU sync must not chain into itself.
- **Workspace scoping is not optional.** Tenant data goes through the `Workspace*` services; raw model queries leak other workspaces' data and are covered by `SecurityHardeningTest` / `WorkspaceAccessTest`.
- **`phpunit.xml` sets `QUEUE_CONNECTION=sync`.** Queued jobs execute inline during tests — assert side effects, or use `Queue::fake()`, rather than expecting deferred execution.
- **Prod queue/scheduler only run via cron** (`schedule:run` + `queue:work --stop-when-empty`, every minute, installed by `deploy.py`). Nothing async happens locally or in prod without it.
- **Deploy is not just an upload.** `python deploy/deploy.py` (or `npm run deploy` / `deploy.ps1`) builds the frontend itself, aborts if the MySQL backup fails, then pulls, migrates, caches config/routes, and installs crontab entries. Needs gitignored `deploy/deploy.config.json` + `deploy/.env.production` (copy from the `.example` files).
- **Frontend `dist` is served from `backend/public/`** in production; `vite build` output is what deploy copies.
- **CORS allows only `FRONTEND_URL`**, and Vite dev proxies `/api` to `localhost:8000`. A new frontend origin needs an env change, not a code change.
- **`frontend/src/version.ts` (`APP_VERSION`) must be kept in sync with `frontend/package.json` version** — the release builder and installer surface it to users.
- **CI is effectively off.** `.github/workflows/security.yml` is `workflow_dispatch`-only. Run `php artisan test` and `npm run lint` locally before claiming a change works.
- **`scripts/` contains live commerce automation** (`update_stripe_price_live.py`, `update_stacklane_details.py`, `stacklane_apply_product.php`, `deploy_to_stacklane.py`). Never run these without an explicit instruction; they mutate production Stripe/Stacklane state.

## Conventions

- API JSON is `snake_case`; TypeScript interfaces are `camelCase` — the conversion happens at the client boundary.
- Platform-specific logic belongs in `ShopeeService` / `LazadaService` (+ `*PromotionService`, `LazadaOrderService`); shared flows go through `ProductService` / `OrderService`.
- Marketplace OAuth tokens are encrypted at rest; never log or return them.
- Route ordering matters in `routes/api.php`: literal paths such as `GET /products/grouped` are declared above `GET /products/{product}` on purpose — a new literal segment under a wildcard route will be swallowed by route-model binding and 404.
- Tests use `RefreshDatabase` + in-memory SQLite, make no external API calls, and need no credentials.

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
