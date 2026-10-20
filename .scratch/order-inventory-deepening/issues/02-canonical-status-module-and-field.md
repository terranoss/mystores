# 02: Add the canonical status module and the canonical_status field

**What to build:** One module owns the raw-to-canonical order status mapping, the lifecycle predicates and the actionable ordering, and every order payload gains an additive, nullable `canonical_status` derived from the raw value. The raw status column is untouched and no migration is added. No caller is migrated in this ticket, so nothing can break.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] The module maps every raw value in the spec's mapping table case-insensitively; an empty, null or unrecognised value maps to null.
- [ ] `needsSync` includes unpaid and excludes shipped.
- [ ] `needsFulfillment` includes pending, packed and ready_to_ship, and excludes unpaid.
- [ ] `terminal` includes completed, cancelled, returned and failed; shipped, delivered and cancelling are not terminal.
- [ ] The actionable ordering returns the expected value from mixed item statuses and null when there are none.
- [ ] Status-change detection is true when the canonical value changes, and falls back to comparing raw strings when both are null.
- [ ] `canonical_status` appears on order list and order detail payloads and is null when the raw value is unmapped.
- [ ] Deriving the field adds no database query.
- [ ] The raw status column is unchanged, with no migration or backfill.
- [ ] Table-driven unit tests cover every mapping row and every predicate.
