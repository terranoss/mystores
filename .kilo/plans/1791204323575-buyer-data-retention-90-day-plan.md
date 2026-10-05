# 90-Day Buyer Data Retention Policy Implementation Plan

## Goal
Implement automated purging of buyer PII (Personally Identifiable Information) from orders older than 90 days across **both Shopee and Lazada** platforms to comply with Shopee's data retention requirement.

## Current State Analysis

### Data Stored (Both Platforms Use Same `Order` Model)
| Field | Shopee Source | Lazada Source | Contains PII |
|-------|---------------|---------------|--------------|
| `buyer_username` | `buyer_username` / `buyer_user_name` | `customer_first_name` + `customer_last_name` | ✅ Name |
| `shipping_address` | `recipient_address` (full: name, address, city, state, country, zip, **phone**) | `address_shipping` (full: name, address lines, city, post_code, country, **phone**) | ✅ Address, Phone |
| `raw_response` (JSON) | Full Shopee `getOrderDetail` response | Full Lazada `getOrder` + `getOrderItems` response | ✅ All of above + Email, detailed address |

### Data Usage Points (Read Access)
1. **Order Listing API** (`OrderService::getOrders`) - searchable by `buyer_username`, returns `shipping_address`
2. **Order Detail API** (`OrderService::getOrder`) - returns full PII
3. **Dashboard** (`DashboardController`) - returns `buyer_username` in order breakdown
4. **Packing List PDF** (`PackingListPdfService`) - renders `buyer_username`
5. **Notifications** - may include buyer info (need to verify)

## Implementation Plan

### Phase 1: Core Purge Command
Create `app/Console/Commands/PurgeOldBuyerData.php`

**Strategy: Anonymize (not delete) to preserve order analytics**
- Nullify `buyer_username` and `shipping_address` for orders where `created_at > 90 days`
- Optionally sanitize `raw_response` to keep non-PII fields (order totals, items, status) while removing PII
- Run daily via scheduler

**Command Options:**
```php
--days=90              // Retention period (default 90)
--dry-run              // Preview without changes
--platform=shopee|lazada|all  // Filter by platform (default all)
--batch-size=100       // Chunk size for memory efficiency
```

**Anonymization Logic:**
```php
// For raw_response, keep only non-PII fields:
$allowedRawFields = [
    'order_sn', 'order_status', 'total_amount', 'currency',
    'ordered_at', 'paid_time', 'ship_by_date', 'cancel_reason',
    'item_list', 'items', 'packages', 'pack_response', 'rts_response'
];
```

### Phase 2: Scheduler Integration
Add to `routes/console.php`:
```php
Schedule::command('orders:purge-buyer-data')->dailyAt('02:00');
```

### Phase 3: Audit Logging
Log every purge operation for compliance evidence:
```php
Log::info('Buyer data purged per 90-day retention policy', [
    'orders_affected' => $count,
    'cutoff_date' => $cutoff->toDateString(),
    'platform' => $platform,
    'dry_run' => $dryRun,
]);
```

### Phase 4: Tests
Create `tests/Feature/PurgeBuyerDataTest.php` covering:
- Dry-run mode shows correct count
- Actual purge anonymizes fields correctly
- `raw_response` sanitization preserves non-PII data
- Platform filtering works
- Orders < 90 days untouched
- Orders exactly at 90 days boundary handled correctly

### Phase 5: Documentation
Update `Docs/SHOPEE_CONSOLE_CHECKLIST.md` with retention policy note.

## Design Decisions to Confirm

### 1. Anonymize vs Hard Delete
**Recommendation: Anonymize (nullify PII fields, keep order record)**
- Preserves order analytics, revenue tracking, inventory history
- Shopee requirement: "Data must be disposed of... irrecoverable" - nullifying in DB satisfies this
- Hard delete would break foreign keys (order_items, pick_list_photos, notifications)

### 2. raw_response Handling
**Recommendation: Selective sanitization**
- Remove PII keys (`buyer_username`, `buyer_user_name`, `recipient_address`, `shipping_address`, `address_shipping`, `customer_first_name`, `customer_last_name`, `phone`, `email`)
- Keep operational fields (`order_sn`, `order_status`, `item_list`, `total_amount`, etc.)
- This maintains debuggability while removing PII

### 3. Platform Scope
**Recommendation: Apply to ALL platforms (Shopee + Lazada)**
- Same `Order` model used for both
- Consistent privacy posture
- Lazada likely has similar requirements

### 4. Timing Basis
**Recommendation: Use `created_at` (local DB record creation)**
- `ordered_at` may be null for old records
- `synced_at` updates on every sync
- `created_at` is immutable and represents when we first stored the data

### 5. Related Data Cleanup
**PickListPhotoCleanupService** already handles photo cleanup when orders complete - no change needed.
**Notifications** - Need to check if they store buyer PII separately.

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Accidentally purging recent orders | Low | High | Dry-run default, boundary tests, logging |
| Breaking API consumers expecting PII | Medium | Medium | Version API or document breaking change |
| raw_response sanitization missing PII fields | Medium | High | Comprehensive field list, test with real API samples |
| Performance on large datasets | Low | Medium | Batch processing, indexed `created_at` |

## Rollout Plan

1. **Deploy command** (hidden behind feature flag or dry-run only)
2. **Run dry-run** in production for 1 week, monitor logs
3. **Enable actual purge** after verification
4. **Monitor** first few runs for anomalies

## Open Questions

1. **Notifications**: Do `OrderNewNotification`, `OrderStatusChangedNotification`, `OrderUrgentNotification` store buyer PII in database (notifications table)? If yes, need separate cleanup.

2. **Frontend**: Does frontend cache buyer data locally? If so, purge won't affect cached data until refresh.

3. **Backups**: Database backups older than 90 days still contain PII. Need separate backup retention policy.

4. **Legal**: Is 90 days from `ordered_at` (purchase date) or `created_at` (our storage date)? Shopee says "stored for more than 90 days" - implies storage date.

---

## Task List for Implementation Agent

- [ ] Create `PurgeOldBuyerData` command with dry-run, platform filter, batch options
- [ ] Implement selective `raw_response` sanitization helper
- [ ] Add command to scheduler (`dailyAt('02:00')`)
- [ ] Add audit logging for each purge run
- [ ] Write comprehensive feature tests
- [ ] Verify notifications table doesn't store separate PII copies
- [ ] Update SHOPEE_CONSOLE_CHECKLIST.md with retention policy note
- [ ] Run dry-run in staging, verify output