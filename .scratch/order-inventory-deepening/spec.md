# Spec: Canonical order status and inventory deepening

Status: ready-for-agent

This spec covers the three Strong candidates from the architecture review: the
order-status vocabulary, the orphan order panels, and the ProductService split.
It is one change because the same order vocabulary feeds the API, the UI, and the
tests, and the inventory work shares the order/product boundary.

The delivery order is recorded under Further Notes. The open item from the
review — extracting a separate ProductCatalog — is out of scope; the call graph
showed one consumer per read method.

## Problem Statement

A store operator running both Shopee and Lazada cannot trust what the order
screens tell them.

Lazada orders in `ready_to_ship` do not appear in the urgent bucket. The urgency
filter lists Shopee's uppercase `READY_TO_SHIP` and `PROCESSED`, plus Lazada's
`pending` and `packed`, but omits Lazada's `ready_to_ship` — the one state that
means "hand this over now". The operator misses the deadline the bucket exists
to protect.

The same lifecycle state is labelled differently depending on which panel or
drawer renders it. Two helper modules encode the vocabulary independently, one
in uppercase and one in lowercase, with different membership. A status that
reads as actionable in one file reads as inert in the other. The dashboard and
the pick list use a third and fourth copy of the same sets, so their numbers do
not always describe the same orders.

Cancellation is worse: the set a service will accept disagrees with the set that
made the order actionable, and the UI's cancel predicate is a fifth copy. The
operator can be offered an action the platform will reject, or denied one it
would accept.

The audit trail is incomplete. Stock and price changes reach `inventory_logs`
through three different implementations: a private recorder in ProductService,
and two hand-rolled blocks in the stock and price jobs. Direct Shopee stock and
price edits write no row at all; Lazada edits write one. The job versions do not
agree on the dirty-flag restore. A failed push can leave a pending row with no
successor, and a successful queued push can produce a different row shape from a
successful direct push.

For the maintainer, the cost is concentrated in one place. ProductService is a
1,392-line module with fifteen public methods. It queries and sorts products,
builds marketplace payloads, pushes stock and price, orchestrates pulls, and
writes audit rows. Five call sites reach the Shopee SDK directly instead of
using the module that already owns token retry. Lazada product calls decrypt and
refresh tokens on their own, bypassing the refresh lock the order service uses.
Two order panels — 533 and 511 lines — have no importer and are never rendered,
while the live panel and the dashboard each carry a copy of the same sync
handler. There is no frontend test runner, so the logic that has already drifted
twice is verified only by type-checking and lint.

## Solution

Give order status one owner per runtime and make the API carry the result.

A backend `OrderStatus` module owns the raw-to-canonical mapping, the lifecycle
predicates (sync, fulfilment, terminal), the actionable ordering, and the
display labels. Every order payload gains an additive, nullable
`canonical_status` field computed from the raw value; the raw `order_status`
column is untouched and no migration or backfill is needed. The urgency filter,
dashboard cards, pick list, notification terminal check and order-action guards
all read the module instead of their own arrays.

A frontend `orderStatus` module owns the canonical type, the predicates the UI
mirrors, and the badge/dot/label presentation. It replaces both existing helper
modules; a companion `orderFormat` module absorbs the formatting helpers those
files also duplicated. The frontend consumes `canonical_status` and never maps
platform spellings itself.

The two orphan panels are deleted. Selection and sync behaviour moves into two
hooks shared by the live orders panel and the dashboard, and the sync endpoints
return a structured `failed` flag so the hook stops detecting failure by
searching message text.

ProductService is split at three seams. An `InventoryWriter` owns "apply the
local change and record it exactly once", with an explicit lifecycle
(`begin`/`succeed`/`fail`) that the queued stock job uses. A
`MarketplaceProductGateway` becomes the only product code that talks to either
marketplace's product API, with one adapter per platform; the Lazada adapter
refreshes the token before calling, and ProductService receives the gateway as a
dependency. The read methods stay in ProductService: their only consumer is the
product controller, so extracting them would move complexity rather than
concentrate it.

The net effect is that a status is defined once per runtime, a mutation is
recorded once, and the marketplace boundary is crossed in one place.

## User Stories

1. As a store operator, I want Lazada orders in `ready_to_ship` to appear in the
   urgent bucket, so that I do not miss a handover deadline.
2. As a store operator, I want a mixed Shopee/Lazada order list to use one label
   for the same lifecycle state, so that I can scan it without translating
   spellings.
3. As a store operator, I want the same badge colour and label in the orders
   table and both detail drawers, so that an order does not look different
   depending on where I open it.
4. As a store operator, I want the dashboard's pending count and the pick list's
   open orders to mean the same set, so that the two screens reconcile.
5. As a store operator, I want cancelled orders excluded from GMV and counts
   regardless of how the marketplace spells cancellation, so that the totals
   stay correct.
6. As a store operator, I want an unrecognised marketplace status shown with its
   raw value and a neutral badge, so that I can still see and act on the order.
7. As a store operator, I want an unrecognised status never to be treated as
   urgent or terminal, so that it neither pollutes nor disappears from my
   queues.
8. As a store operator, I want the cancel action to appear only when the
   platform will accept it, so that I do not get avoidable errors.
9. As a store operator, I want a Lazada `ready_to_ship` order not to offer
   cancellation, so that the UI matches Lazada's rule.
10. As a store operator, I want ship and print-label actions to follow the same
    state semantics as the backend, so that buttons and API agree.
11. As a store operator, I want a status notification only when the lifecycle
    state really changes, so that a spelling change does not spam me.
12. As a store operator, I want completed, confirmed, cancelled, returned and
    failed orders to stop generating urgent notices, so that I am not chasing
    finished orders.
13. As a store operator, I want one orders panel instead of three, so that I
    cannot land on a stale implementation.
14. As a store operator, I want selection and bulk print to behave identically
    on the orders page and the dashboard, so that I do not have to relearn a
    second flow.
15. As a store operator, I want a sync failure reported from a real outcome
    rather than from message text, so that the toast is accurate.
16. As a store operator, I want every stock change recorded exactly once,
    whether it is a direct edit, a queued push, a linked-store sync or a
    platform pull, so that the audit trail is complete.
17. As a store operator, I want a failed push recorded with its error, so that I
    can see why stock did not change.
18. As a store operator, I want a successful push to close the pending row
    rather than create a second one, so that the audit trail is truthful.
19. As a store operator, I want price changes recorded with old and new values,
    so that I can audit pricing.
20. As a store operator, I want simple and variant products recorded
    consistently, so that neither kind is invisible.
21. As a store operator, I want a failed push to restore the needs-push marker,
    so that the item still shows as unsynchronised.
22. As a workspace member, I want audit rows to remain reachable only through
    products I can access, so that canonicalization does not widen tenancy.
23. As a developer, I want one backend module to own the raw-to-canonical
    mapping, so that I can change status semantics in one place.
24. As a developer, I want one frontend module to own canonical predicates and
    presentation, so that the two helper files cannot drift again.
25. As a developer, I want a table-driven test for every raw status either
    platform can persist, so that adding a status is a deliberate act.
26. As a developer, I want the backend to emit `canonical_status`, so that the
    frontend does not carry platform spellings.
27. As a developer, I want the API change to be additive, so that existing
    clients keep working.
28. As a developer, I want `needsSync` and `needsFulfillment` separated, so that
    an unpaid order can be resynced without becoming urgent.
29. As a developer, I want cancellation rules to stay with the platform that
    enforces them, so that a shared predicate does not lie about either
    marketplace.
30. As a developer, I want the marketplace product calls behind one gateway, so
    that product code cannot reach the SDK directly.
31. As a developer, I want the Lazada product path to refresh its token before
    calling, so that concurrent refreshes do not rotate each other's tokens.
32. As a developer, I want the inventory log lifecycle in one module, so that
    jobs and services share one definition.
33. As a developer, I want the never-dispatched price job deleted, so that the
    codebase does not imply a queue path that does not exist.
34. As a developer, I want a frontend test runner, so that pure status and hook
    logic is verified without a browser.
35. As a self-hoster, I want no database migration or backfill, so that
    upgrading does not rewrite order history.
36. As a self-hoster, I want the mobile app to ignore the new field until it is
    updated, so that an older build keeps working.
37. As a maintainer, I want the status vocabulary recorded in the glossary, so
    that future work uses the same words.
38. As a QA engineer, I want urgency covered for both platforms, so that the
    original Lazada bug cannot return.
39. As a QA engineer, I want the existing workspace-scoping tests to keep
    passing at the API boundary, so that the new derivation does not widen
    access.

## Implementation Decisions

### Vocabulary

The domain glossary now defines:

- **Order Status** — the marketplace's own spelling, stored in `order_status`:
  `READY_TO_SHIP`/`PROCESSED` for Shopee, `ready_to_ship`/`packed` for Lazada.
- **Canonical Status** — the platform-independent state derived from an Order
  Status, emitted as `canonical_status`. A raw value with no mapping yields
  `null`, never a guessed state.

The canonical set is:

`unpaid`, `pending`, `packed`, `ready_to_ship`, `shipped`, `delivered`,
`completed`, `cancelling`, `cancelled`, `returned`, `failed`.

`cancelling` was added during synthesis: Shopee persists `IN_CANCEL`, which is
neither fulfilment work nor terminal, and forcing it into either set would
recreate the bug this spec exists to remove.

### Mapping table

Mapping is case-insensitive and keyed by the raw value. Platform is accepted by
the module for future collisions but is not needed for today's table.

| Raw value | Canonical Status |
| --- | --- |
| `UNPAID` | `unpaid` |
| `PENDING` | `pending` |
| `PACKED` | `packed` |
| `READY_TO_SHIP` | `ready_to_ship` |
| `PROCESSED` | `ready_to_ship` |
| `SHIPPED` | `shipped` |
| `DELIVERED` | `delivered` |
| `TO_CONFIRM_RECEIVE` | `delivered` |
| `CONFIRMED` | `completed` |
| `COMPLETED` | `completed` |
| `IN_CANCEL` | `cancelling` |
| `CANCELLED`, `CANCELED` | `cancelled` |
| `TO_RETURN`, `RETURNED` | `returned` |
| `FAILED`, `FAILED_DELIVERY` | `failed` |
| `NULL`, empty, unrecognised | `null` |

`PROCESSED` and `READY_TO_SHIP` both mean "awaiting handover", so they share a
canonical value. Lazada's `confirmed` is treated as the post-delivery
confirmation, hence `completed`. `FAILED_DELIVERY` is normalised by the Lazada
service to `failed` today and remains so.

### Predicates

The module exposes four predicates over a raw status:

- `needsSync`: `unpaid`, `pending`, `packed`, `ready_to_ship`, `cancelling`.
  This is resync eligibility, not urgency; it deliberately includes `unpaid`.
- `needsFulfillment`: `pending`, `packed`, `ready_to_ship`. This drives the
  urgent bucket, the dashboard pending card and the pick list.
- `terminal`: `completed`, `cancelled`, `returned`, `failed`. `shipped` and
  `delivered` are not terminal; `cancelling` is not terminal.
- `statusChanged`: true when the canonical value changes. When both old and new
  are `null`, it falls back to comparing the raw strings.

A fifth helper, `mostActionable`, replaces the Lazada-only priority table for
deriving one status from multiple item statuses. Its order is:
`unpaid`, `pending`, `packed`, `ready_to_ship`, `shipped`, `delivered`,
`completed`, `cancelling`, `returned`, `failed`, `cancelled`, then `null`.
The existing rule that an empty status preserves the previous value stays in the
Lazada service.

### API contract

Every order payload gains `canonical_status`, a nullable string. It is computed
from the raw status by the Order model as an appended attribute. The computation
does not query the store: the mapping is case-insensitive and needs no platform
today, and a platform hint is used only when the relation is already loaded. The
raw `order_status` remains present and unchanged.

No migration, no backfill, no column addition. No endpoint is removed or
renamed. The field is additive, so the existing React build and the mobile app
ignore it until they are updated.

The order sync endpoints gain two additive fields:

- `failed`: boolean, true if any store failed.
- `stores`: array of `{store_id, platform, ok, message}`.

The existing `results` array of message strings stays for compatibility. The
frontend hook reads `failed`; it does not infer failure from text.

### Backend call sites

The following move onto `OrderStatus`:

- The urgency filter uses `needsFulfillment`. This is the fix for Lazada
  `ready_to_ship`.
- The dashboard pending card and pending pick list use `needsFulfillment`; the
  cancelled exclusion and cancel-reason breakdown use `terminal` /
  `cancelled`.
- Pick-list photo cleanup's open set uses `needsFulfillment`.
- The order status-change notification compares canonical values.
- Shipping-label and ship/retry gates use canonical values.
- Lazada resync uses `needsSync`; Lazada pack, AWB and ready-to-ship guards use
  canonical values.
- The dead urgent-order notification generator is deleted. It has no caller and
  would otherwise carry a second terminal definition.

The dashboard, pick list and services stop declaring their own status arrays.

### Cancellation

Cancellation eligibility remains owned by the platform service, because the
platforms genuinely differ and AGENTS.md places platform logic there. Each
service gets one named set instead of an inline array:

- Shopee: `READY_TO_SHIP`, `PROCESSED`.
- Lazada: `pending`, `unpaid`, `packed`.

Lazada `ready_to_ship` remains not cancelable. The frontend mirrors these sets
for button visibility only; the backend remains the authority. The shared
`OrderStatus` module does not expose a global `cancelable` predicate.

### Notifications

A status-change notification fires when the canonical status changes, with the
raw-string fallback described above. A tail of casing or spelling differences
does not produce a second notification. Terminal states stop generating urgent
notices.

### Frontend modules

Two modules replace the two helper files:

- `orderStatus`: the canonical type, `needsFulfillment`/`terminal` predicates,
  platform-aware cancel and ship/print capabilities for presentation, and the
  badge tone, dot and label.
- `orderFormat`: currency, date, date-time, item summary and ship-by
  presentation helpers, which both old files duplicated.

The old helper modules are deleted, and every consumer imports from the new
modules. A `null` canonical status renders a neutral badge and the raw status
text.

The frontend trusts `canonical_status`; it does not re-map raw platform
spellings. The capability predicates are presentation mirrors, not authority.

### Hooks and sync

`useOrderSelection` and `useOrderSync` are extracted. The unified orders panel
and the dashboard both consume them; the duplicated sync handler and the
duplicated selection helpers are removed. `useOrderSync` calls the platform sync
endpoint, reads the `failed` flag, refreshes through a caller-supplied callback,
and produces one toast.

### InventoryWriter

One module owns the "apply locally and record it" concern:

- `apply` performs the local product/variant update and writes exactly one
  completed log row.
- `begin` writes one pending row for a queued push and returns its id.
- `succeed` closes that row.
- `fail` records the error and optionally restores the needs-push marker.

The invariant is exactly one row per stock or price mutation, across every path:
direct push, queued stock job, linked-store sync, webhook, and Lazada update.
Direct pushes currently write no row and will start writing one. The stock job
keeps its dirty-flag restore; the price job did not have it, and the writer makes
the behaviour explicit rather than implicit.

The dead `UpdateShopeePriceJob` is deleted: no code dispatches it, and price
pushes are synchronous today. ProductService stops exposing
`applyLocalStockAndPrice` publicly and stops owning a private recorder.

The `inventory_logs` table has no read path today. Exposing it is out of scope;
this spec only makes the writes consistent.

### MarketplaceProductGateway

One gateway is the only product code that talks to either marketplace product
API:

- `pushStock`, `pushPrice`
- `fetchItemList`, `fetchItemDetails`, `fetchModels`

It has two adapters, Shopee and Lazada, which preserve today's payload shapes,
response extraction and error handling. The Shopee adapter composes the existing
token-retry path. The Lazada adapter refreshes the token before calling, using
the same locked refresh the order service uses, so concurrent product calls
cannot rotate each other's refresh token.

ProductService receives the gateway as a constructor dependency. The five raw
SDK call sites and the direct Lazada token decryption disappear. Pagination and
sync orchestration stay in ProductService; the gateway owns one page/one call.

The three product read methods stay where they are. Each has exactly one
consumer, so a separate read model would move code without adding leverage.

### Delivery order

1. Delete the orphan panels.
2. Backend `OrderStatus` and the `canonical_status` field.
3. Frontend `orderStatus` and `orderFormat`.
4. Selection and sync hooks plus the structured sync result.
5. `InventoryWriter` and deletion of the dead price job.
6. `MarketplaceProductGateway`.

Steps 1, 2 and 5 are independent and can start immediately. Step 3 depends on 2;
step 4 depends on 3; step 6 depends on 5.

## Testing Decisions

A good test asserts externally observable behaviour: the JSON an endpoint
returns, the rows a mutation leaves behind, the message a hook produces. It does
not assert private methods, internal call order, or the shape of an
implementation detail.

### Seam 1: the HTTP API (existing)

This is the highest seam and the one at which tenancy is enforced. It covers:

- `canonical_status` is present on order list and detail payloads, matches the
  mapping table, and is `null` for an unmapped raw value.
- A Lazada order in `ready_to_ship` appears in the overdue, due-today and
  due-48h urgency buckets.
- The dashboard pending card, pending pick list and cancelled exclusions agree
  with `needsFulfillment` and `terminal`.
- A status change from `READY_TO_SHIP` to `SHIPPED` produces one notification;
  a casing-only change produces none.
- A direct stock update leaves exactly one success row; a queued push leaves a
  pending row that becomes success or failed.
- Existing workspace-scoping tests still pass.

Prior art: `OrderListTest`, `OrderCancelTest`, `DashboardTest`,
`LazadaOrderManagementTest`, `PickListCompletionTest`, `InventoryAuditLogTest`,
`NeedsPushStockTest`.

### Seam 2: the pure backend modules (new)

One PHPUnit unit seam covers `OrderStatus` and `InventoryWriter` directly. This
seam exists because a two-platform mapping table and the failure transition are
not efficiently reachable through HTTP fixtures, and failure needs a forced
marketplace error.

Covered:

- Every raw value in the mapping table, both spellings of cancelled, and the
  `null` fallback.
- `needsSync` includes `unpaid` and excludes `shipped`; `needsFulfillment`
  excludes `unpaid`; `terminal` excludes `shipped`, `delivered` and
  `cancelling`.
- `mostActionable` picks the expected raw value from mixed item statuses.
- `begin` → `succeed` leaves one row with status success.
- `begin` → `fail` leaves one row with the error message.
- `fail` restores the needs-push marker only when asked.
- `apply` writes exactly one row for a simple product and one per variant.

Prior art: `InventoryAuditLogTest` for field assertions and the table-driven
style already used in `LazadaOrderManagementTest`.

### Seam 3: frontend pure modules and hooks (new)

Vitest with jsdom and Testing Library is added. It covers the `orderStatus`
predicates and presentation, the `orderFormat` helpers that were previously
duplicated, and the two hooks through a small component harness.

Covered:

- Canonical predicates match the backend table for both platforms.
- A `null` canonical status renders the neutral badge and the raw text.
- Badge tone and label are identical for Shopee `PROCESSED` and Lazada
  `ready_to_ship`.
- Cancel visibility matches the platform sets.
- Selection includes only printable rows and clears correctly.
- Sync reports failure from `failed`, not from a message containing "failed".
- Sync refreshes once on success and once on failure.

There is no frontend test runner today, so this establishes the first one. The
tests stay hand-written and table-driven, consistent with the backend style.

### No seam: orphan deletion

Deleting the two panels needs no test. They have no importer, and the build and
lint prove the application still compiles. The existing orders page remains the
behavioural check.

### Not covered by automated tests

The visual appearance of a badge is not asserted beyond its tone and label.
Marketplace payload shapes are covered by the existing feature tests that fake
the client; no test makes a real Shopee or Lazada call. The audit log's read
surface does not exist, so it is not tested.

## Out of Scope

- **ProductCatalog extraction.** The three read methods have one consumer each,
  so a separate module would be a move, not a deepening.
- **The order pipeline and platform adapters** (candidate 4). `OrderService` and
  `LazadaOrderService` remain twin services; only their status vocabulary is
  shared.
- **The API client split** (candidate 5). `client.ts` remains one module.
- **The dashboard metrics query** (candidate 6). The dashboard controller keeps
  its query structure; only its status predicates move.
- **The workspace store resolver** (candidate 7).
- **Notification routing through the workspace service** (candidate 8). The dead
  urgent generator is deleted, but store-health routing is untouched.
- **Exposing `inventory_logs`.** No API or UI read path is added.
- **Database migration or backfill.** The raw column and historical rows are
  untouched.
- **Changing platform cancellation rules.** The current per-platform membership
  is preserved; only its location becomes single.
- **A UI redesign.** Badge tones and labels are consolidated, not redesigned.
- **Mobile app changes.** The field is additive; the app ignores it until a
  later change.
- **New routes or throttles.** No write endpoint is added.
- **Real marketplace calls in tests.** The suite stays in-memory and offline.

## Further Notes

- **Fact-sheet findings.** Shopee's status is a raw pass-through and can be
  `NULL`; Lazada's normaliser also passes unknown values through. That is why
  `canonical_status` is nullable and why unmapped values are logged rather than
  coerced.
- **`UpdateShopeePriceJob` is dead.** No dispatch site exists in the app. It is
  deleted rather than consolidated.
- **`inventory_logs` is write-only.** No application code reads it today; only
  tests do. The writer still earns its keep by making the writes consistent.
- **`cancelling` is a synthesis addition.** The earlier ten-state set had no
  home for Shopee `IN_CANCEL`; forcing it into `pending` would make a
  cancellation request urgent, and forcing it into `cancelled` would hide an
  order that may still be open.
- **`PROCESSED` maps to `ready_to_ship`.** The two names describe the same
  handover state, and treating them differently is the root of the Lazada bug.
- **No new N+1.** `canonical_status` is derived from the raw string alone; the
  platform hint is used only when the store relation is already loaded.
- **Tests run in memory.** Backend tests use SQLite and
  `QUEUE_CONNECTION=sync`, so queued jobs execute inline; assert their side
  effects rather than deferred execution.
- **Frontend build is the type check.** `npm run build` runs `tsc -b`; `lint`
  and the new `test` script run separately.
- **Glossary is updated.** `GLOSSARY.md` now defines Order Status and Canonical
  Status, including the nullable fallback and `cancelling`.
- **Graphify should be refreshed** after the code changes land.
- **Rate limiters are named.** No endpoint changes, so no throttle changes.
- **Route ordering is untouched.** No literal route is added under a wildcard.
