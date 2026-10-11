<?php

/**
 * Upserts the Stacklane storefront product record and its localized copy.
 *
 * Uploaded to the server by scripts/update_stacklane_details.py, which is what
 * actually executes it. Kept as a standalone file rather than a `php -r` string
 * so that quotes, dollar signs and ampersands in marketing copy cannot break
 * shell interpolation.
 *
 * Usage (from any directory):
 *   php stacklane_apply_product.php /path/to/stacklane_product.json
 *
 * Input JSON shape:
 *   {
 *     "code": "mystores",
 *     "translations": { "en": {...}, "ms": {...} },
 *     "pricing": { "amount_display": "RM 199", ... }
 *   }
 */

use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$appPath = getenv('STACKLANE_APP_PATH') ?: __DIR__;

require $appPath.'/vendor/autoload.php';
$app = require_once $appPath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$jsonPath = $argv[1] ?? null;

if (! $jsonPath || ! is_file($jsonPath)) {
    fwrite(STDERR, "USAGE_ERROR: pass a path to the product JSON file\n");
    exit(2);
}

$payload = json_decode((string) file_get_contents($jsonPath), true);

if (! is_array($payload) || empty($payload['code'])) {
    fwrite(STDERR, "INVALID_PAYLOAD: expected JSON with a 'code' key\n");
    exit(2);
}

$code = (string) $payload['code'];
$translations = is_array($payload['translations'] ?? null) ? $payload['translations'] : [];
$pricing = is_array($payload['pricing'] ?? null) ? $payload['pricing'] : [];

// The base products row doubles as the English fallback, so its copy always
// comes from the fallback locale rather than drifting independently.
$fallback = (string) config('app.fallback_locale', 'en');
$fallbackCopy = $translations[$fallback] ?? ($translations[array_key_first($translations)] ?? []);

$product = Product::query()->where('code', $code)->first();

if (! $product) {
    fwrite(STDERR, "NOT_FOUND: no product with code '{$code}'\n");
    exit(3);
}

$baseFields = [];

foreach (['name', 'tagline', 'description', 'price_label'] as $field) {
    if (array_key_exists($field, $fallbackCopy)) {
        $baseFields[$field] = (string) $fallbackCopy[$field];
    }
}

foreach (['amount_display', 'amount_display_usd'] as $field) {
    if (array_key_exists($field, $pricing)) {
        $baseFields[$field] = (string) $pricing[$field];
    }
}

if (array_key_exists('toyyibpay_amount_cents', $pricing)) {
    $baseFields['toyyibpay_amount_cents'] = (int) $pricing['toyyibpay_amount_cents'];
}

$baseChanged = [];

foreach ($baseFields as $field => $value) {
    if ((string) $product->{$field} !== $value) {
        $baseChanged[$field] = ['from' => $product->{$field}, 'to' => $value];
    }
}

if ($baseChanged) {
    $product->fill($baseFields)->save();
}

// Translations land in product_translations once the localization migration has
// run. Before that exists the base row above is already correct, so we skip
// rather than fail the deploy.
$translationsSupported = Schema::hasTable('product_translations');
$translationChanges = [];

if ($translationsSupported) {
    foreach ($translations as $locale => $copy) {
        $values = [];

        foreach (['name', 'tagline', 'description', 'price_label', 'changelog'] as $field) {
            if (array_key_exists($field, $copy)) {
                $values[$field] = (string) $copy[$field];
            }
        }

        if ($values === []) {
            continue;
        }

        $existing = DB::table('product_translations')
            ->where('product_id', $product->id)
            ->where('locale', $locale)
            ->first();

        if ($existing) {
            $changed = [];

            foreach ($values as $field => $value) {
                if ((string) $existing->{$field} !== $value) {
                    $changed[$field] = ['from' => $existing->{$field}, 'to' => $value];
                }
            }

            if ($changed) {
                DB::table('product_translations')
                    ->where('product_id', $product->id)
                    ->where('locale', $locale)
                    ->update($values);

                $translationChanges[$locale] = $changed;
            }

            continue;
        }

        DB::table('product_translations')->insert($values + [
            'product_id' => $product->id,
            'locale' => $locale,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $translationChanges[$locale] = ['created' => true];
    }
}

echo json_encode([
    'status' => 'ok',
    'product' => ['id' => $product->id, 'code' => $product->code],
    'base_changed' => $baseChanged,
    'translations_supported' => $translationsSupported,
    'translations_changed' => $translationChanges,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";