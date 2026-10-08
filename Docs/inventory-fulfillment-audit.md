# Inventory, Products & Fulfillment — Remediation Plan & Phased Roadmap

**Date:** 2026-10-08  
**Scope:**
- Products: Cross-store stock & price editing, cross-store sync, SKU-based variant sync, needs_push lifecycle, inventory audit logging
- Orders & Fulfillment: Platform-aware cancellation, packing list PDF generation, bulk shipping documents
**Guiding Principle:** Non-over-engineered, strictly critical defects.  
**Execution Policy:** Human approval required before moving between phases. Run verification tests after every task.  
**Execution Sizing Rule:** Broken into phases targeted at **~200–300 lines of code per phase** (including implementation and tests) for safe, incremental, and reviewable execution.

---

## 1. Executive Summary & Phased Sizing Matrix

| Phase | Priority | Target Scope | Est. Impl. LOC | Est. Test LOC | Total Est. LOC | Status |
|---|---|---|:---:|:---:|:---:|---|
| **Phase 1** | P0 (Critical) | Cross-Platform Stock Sync & Packing Slip Real SKU Display | ~35 | ~120 | **~155 LOC** | `[ ] Pending Approval` |
| **Phase 2** | P1 (High) | Platform-Aware Order Cancellation Guard & Error Handling | ~25 | ~90 | **~115 LOC** | `[ ] Pending` |
| **Phase 3** | P1 (High) | Inventory Audit Trail for Direct & Local Staged Operations | ~70 | ~140 | **~210 LOC** | `[ ] Pending` |
| **Total** | | **Critical Inventory & Fulfillment Remediation** | **~130** | **~350** | **~480 LOC** | **0 of 3 phases complete (0%)** |

---

## 2. Architecture & Execution Dependency Graph

```mermaid
flowchart TD
    subgraph Phase 1: P0 - Cross-Platform Sync & Packing List Fix
        T11["Task 1.1: Remove Same-Platform Restriction in syncLinkedStores"]
        T12["Task 1.2: Display Real SKU in PackingListPdfService"]
        T13["Task 1.3: Cross-Platform Sync & Packing Slip Tests"]
        T11 --> T13
        T12 --> T13
    end

    subgraph Phase 2: P1 - Platform-Aware Order Cancellation
        T21["Task 2.1: Add Platform Guard to OrderService::cancelOrder"]
        T22["Task 2.2: Add Cancellation Tests for Shopee & Lazada"]
        T21 --> T22
    end

    subgraph Phase 3: P1 - Complete Inventory Audit Logging
        T31["Task 3.1: Log Inventory in applyLocalStockAndPrice & updateLazadaProduct"]
        T32["Task 3.2: Verify InventoryLog Entries Across Staged & Direct Updates"]
        T31 --> T32
    end

    Phase 1 --> Phase 2
    Phase 2 --> Phase 3
```

---

## Phase 1: Cross-Platform Stock Sync & Real SKU on Packing Slips (P0)

**Estimated Size:** ~155 LOC (Implementation: ~35 LOC, Tests: ~120 LOC)  
**Objective:** Restore cross-platform SKU synchronization between Shopee and Lazada stores, and fix packing slip display to output merchant SKUs instead of numeric IDs.

### Task 1.1: Remove Same-Platform Restriction in `syncLinkedStores`
- **Target File:** [`backend/app/Services/ProductService.php`](file:///d:/GitHub/mystores/backend/app/Services/ProductService.php#L619-L650)
- **Problem Statement:**  
  In `ProductService::syncLinkedStores()`, both variant queries and simple product queries filter by `$q->where('platform', $originPlatform)`. This completely prevents stock updates on a Shopee store from syncing to a Lazada store sharing the identical SKU (and vice versa).
- **Code Changes:**
  ```php
  // ProductService.php:617-624
  $matchingVariants = ProductVariant::where('model_sku', $sku)
      ->where('product_id', '!=', $originProductId)
      ->whereHas('product.store', function ($q) use ($user) {
          $this->applyWorkspaceStoreScope($q, $user, true);
      })
      ->with('product.store')
      ->get();

  // ProductService.php:643-650
  $matchingProducts = Product::where('item_sku', $sku)
      ->where('has_variants', false)
      ->where('id', '!=', $originProductId)
      ->whereHas('store', function ($q) use ($user) {
          $this->applyWorkspaceStoreScope($q, $user, true);
      })
      ->with('store')
      ->get();
  ```
  *(Note: The subsequent dispatch loops already correctly check `$platform === 'lazada'` vs Shopee).*

### Task 1.2: Display Real Merchant SKU in `PackingListPdfService`
- **Target File:** [`backend/app/Services/PackingListPdfService.php:56`](file:///d:/GitHub/mystores/backend/app/Services/PackingListPdfService.php#L56)
- **Problem Statement:**  
  The packing slip renders `$sku = e($item->model_id ? (string) $item->model_id : (string) $item->item_id);`. Packers see raw numeric marketplace IDs instead of merchant SKUs, preventing warehouse item picking.
- **Code Changes:**
  ```php
  // PackingListPdfService.php:56
  $sku = e($item->sku ?: ($item->model_id ? (string) $item->model_id : (string) $item->item_id));
  ```

### Task 1.3: Verification Tests
- **Target Files:**
  - `backend/tests/Feature/NeedsPushStockTest.php`
  - `backend/tests/Feature/ShippingLabelTest.php`
- **Test Coverage:**
  1. Test that `syncLinkedStores` for a SKU present on both a Shopee store and a Lazada store dispatches updates to both platforms.
  2. Test that `PackingListPdfService::generate` produces an HTML/PDF containing the item's merchant SKU when available on `OrderItem`.
- **Command:** `php artisan test --filter=NeedsPushStockTest` and `php artisan test --filter=ShippingLabelTest`

---

## Phase 2: Platform-Aware Order Cancellation Guard (P1)

**Estimated Size:** ~115 LOC (Implementation: ~25 LOC, Tests: ~90 LOC)  
**Objective:** Prevent crashes and invalid SDK calls when canceling orders in unified multi-platform environments.

### Task 2.1: Add Platform Guard in `OrderService::cancelOrder`
- **Target File:** [`backend/app/Services/OrderService.php:160-175`](file:///d:/GitHub/mystores/backend/app/Services/OrderService.php#L160-L175)
- **Problem Statement:**  
  Calling `/api/orders/{id}/cancel` on a Lazada order invokes `ShopeeService::forStore()`, crashing with invalid authentication or mismatched API endpoints.
- **Code Changes:**
  ```php
  // OrderService.php
  if (($store->platform ?? 'shopee') === 'lazada') {
      abort(400, 'Lazada orders must be cancelled via the Lazada cancellation endpoint.');
  }
  ```

### Task 2.2: Add Feature Tests for Order Cancellation
- **Target File:** `backend/tests/Feature/OrderCancelTest.php`
- **Test Coverage:**
  1. Attempting to cancel a Lazada order via `/api/orders/{id}/cancel` returns HTTP 400 with a clear message.
  2. Canceling a Shopee order via `/api/orders/{id}/cancel` continues to work as expected.
- **Command:** `php artisan test --filter=OrderCancelTest`

---

## Phase 3: Complete Inventory Audit Trail (P1)

**Estimated Size:** ~210 LOC (Implementation: ~70 LOC, Tests: ~140 LOC)  
**Objective:** Ensure all stock and price changes (local staged edits, direct synchronous updates, and Lazada marketplace updates) are recorded in `inventory_logs`.

### Task 3.1: Record Audit Entries in `applyLocalStockAndPrice` and `updateLazadaProduct`
- **Target File:** [`backend/app/Services/ProductService.php`](file:///d:/GitHub/mystores/backend/app/Services/ProductService.php)
- **Problem Statement:**  
  Currently only `UpdateShopeeStockJob` and `UpdateShopeePriceJob` insert into `inventory_logs`. Local staged stock/price changes (via "Save local" / `mode=local`) and Lazada updates leave no trace in `inventory_logs`.
- **Code Changes:**
  - Create a private helper `recordInventoryLog($product, $variantId, $sku, $oldStock, $newStock, $oldPrice, $newPrice, $source, $status, $error = null)`.
  - In `applyLocalStockAndPrice`: write `InventoryLog` records with `source = 'local_staged'` and `status = 'success'`.
  - In `updateLazadaProduct`: write `InventoryLog` records with `source = 'user'` and `status = 'success'`.

### Task 3.2: Verification Tests
- **Target File:** `backend/tests/Feature/InventoryAuditLogTest.php`
- **Test Coverage:**
  1. Test that calling `updateStockAndPrice` with `mode=local` creates an `InventoryLog` with `source='local_staged'`.
  2. Test that updating a Lazada product creates an `InventoryLog` with `status='success'`.
- **Command:** `php artisan test --filter=InventoryAuditLogTest`

---

## 3. Human Approval Gate

In accordance with our user global rules:
- **Never use auto-proceed.**
- Waiting for explicit user approval before moving from Planning to Execution.
