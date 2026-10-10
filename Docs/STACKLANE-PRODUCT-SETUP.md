# MyStore Product Setup on Stacklane (stacklane.melur.com)

This document provides the exact commands and code to register **MyStore** on `stacklane.melur.com` at **USD 50** per perpetual license.

---

## 1. Product Configuration Parameters

- **Product Name**: `MyStore`
- **Product Slug / Code**: `mystores` *(Must match `LicenseService::PRODUCT_CODE = 'mystores'`)*
- **Price**: `$50 USD` (or RM 220 for MY IP addresses)
- **Max Activations**: `1` (1 domain/server per license key)
- **License Key Prefix**: `MYS` -> Generates `MYS-XXXX-XXXX-XXXX`
- **Deliverable ZIP**: `dist_release/MyStore-v1.0.0-Codebase.zip` (1.12 MB)
- **Companion Mobile App**: `https://play.google.com/store/apps/details?id=com.melur.mystores`

---

## 2. Updates on Stacklane Codebase (`d:\GitHub\PinScout\web`)

### A. Add Key Prefix in `app/Services/LicenseKeyGenerator.php`

In `app/Services/LicenseKeyGenerator.php`, add `'mystores' => 'MYS'`:

```php
private static array $prefixes = [
    'pinscout'        => 'PSCT',
    'pinscout-agency' => 'PSCA',
    'mystores'        => 'MYS',
];
```

### B. Add Product Entry in `database/seeders/ProductSeeder.php`

Add the following block to `ProductSeeder.php`:

```php
Product::updateOrCreate(
    ['code' => 'mystores'],
    [
        'slug' => 'mystores',
        'name' => 'MyStore',
        'tagline' => 'Self-Hosted Multi-Outlet POS & Retail Management Platform. Lifetime license — no subscriptions.',
        'description' => 'MyStore is a full-featured, self-hosted point-of-sale and multi-outlet retail management platform built with Laravel 12 and React 19. It comes with a free companion mobile app on Google Play (com.melur.mystores) that staff can connect to instantly using the Store Connection QR code. Features multi-outlet inventory, order management, staff role permissions, receipt printing, and a 5-minute self-hosted web installer.',
        'price_label' => 'Perpetual License (1 Domain)',
        'amount_display' => 'RM 220',
        'amount_display_usd' => '$50',
        'stripe_price_id' => env('STRIPE_PRICE_ID_MYSTORES', 'price_mystores_usd50'),
        'toyyibpay_amount_cents' => 22000,
        'max_activations' => 1,
        'download_url' => 'https://stacklane.melur.com/downloads/MyStore-v1.0.0-Codebase.zip',
        'latest_version' => '1.0.0',
        'min_supported_version' => '1.0.0',
        'changelog' => 'Initial release of MyStore with multi-outlet POS, inventory tracking, web installer, and Google Play companion app support.',
        'is_published' => true,
        'sort_order' => 3,
    ]
);
```

---

## 3. Direct SQL Insert (Quick Setup on Live Database)

If you have database access on `stacklane.melur.com`, you can insert the product directly:

```sql
INSERT INTO products (
    code,
    slug,
    name,
    tagline,
    description,
    price_label,
    amount_display,
    amount_display_usd,
    stripe_price_id,
    toyyibpay_amount_cents,
    max_activations,
    download_url,
    latest_version,
    min_supported_version,
    changelog,
    is_published,
    sort_order,
    created_at,
    updated_at
) VALUES (
    'mystores',
    'mystores',
    'MyStore',
    'Self-Hosted Multi-Outlet POS & Retail Management Platform. Lifetime license — no subscriptions.',
    'MyStore is a full-featured, self-hosted point-of-sale and multi-outlet retail management platform built with Laravel 12 and React 19. It comes with a free companion mobile app on Google Play (com.melur.mystores) that staff can connect to instantly using the Store Connection QR code. Features multi-outlet inventory, order management, staff role permissions, receipt printing, and a 5-minute self-hosted web installer.',
    'Perpetual License (1 Domain)',
    'RM 220',
    '$50',
    'price_mystores_usd50',
    22000,
    1,
    'https://stacklane.melur.com/downloads/MyStore-v1.0.0-Codebase.zip',
    '1.0.0',
    '1.0.0',
    'Initial release of MyStore with multi-outlet POS, inventory tracking, web installer, and Google Play companion app support.',
    1,
    3,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    tagline = VALUES(tagline),
    amount_display_usd = VALUES(amount_display_usd),
    max_activations = VALUES(max_activations),
    download_url = VALUES(download_url),
    is_published = 1;
```

---

## 4. Deploying the Deliverable File

1. Upload `dist_release/MyStore-v1.0.0-Codebase.zip` to the server:
   ```bash
   scp -P 55000 dist_release/MyStore-v1.0.0-Codebase.zip melurcom@103.10.78.60:/home/melurcom/stacklane.melur.com/web/public/downloads/
   ```
2. Run database migration / seeder on `stacklane.melur.com`:
   ```bash
   php artisan db:seed --class=ProductSeeder
   ```
3. The product is immediately live at:
   - **Product Page**: `https://stacklane.melur.com/apps/mystores`
   - **Checkout**: `https://stacklane.melur.com/buy?product=mystores`
