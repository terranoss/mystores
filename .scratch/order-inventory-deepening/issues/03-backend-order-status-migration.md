# 03: Move backend order-status reads onto the module

**What to build:** Every backend read of a raw order status goes through the canonical status module, so the urgent bucket includes Lazada's ready_to_ship, the dashboard and pick list describe the same orders, status-change notifications compare lifecycle states, and order actions follow one vocabulary. Cancellation sets become one named set per platform, and the scattered status arrays and dead generator are removed.

**Blocked by:** 02

**Status:** ready-for-agent

- [ ] The urgency filter uses `needsFulfillment`; a Lazada order in ready_to_ship appears in the overdue, due-today and due-48h buckets.
- [ ] The dashboard pending card, pending pick list, cancelled exclusion and cancel-reason breakdown use the module.
- [ ] Pick-list photo cleanup's open set uses `needsFulfillment`.
- [ ] A status-change notification fires when the canonical status changes; a casing-only change fires none.
- [ ] Shipping-label, ship/retry and Lazada pack, AWB and ready-to-ship guards use the module; Lazada resync uses `needsSync`.
- [ ] Shopee's cancel set is READY_TO_SHIP and PROCESSED; Lazada's is pending, unpaid and packed; Lazada ready_to_ship is not cancelable.
- [ ] The dead urgent-order notification generator is deleted.
- [ ] No service or controller declares its own order status array.
- [ ] Existing order, dashboard, pick-list and notification tests pass.
- [ ] New tests assert urgency and cancellation for both platforms.
