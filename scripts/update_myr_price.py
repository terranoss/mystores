"""Updates the Malaysian price on the stacklane.melur.com product record.

NOTE: scripts/data/stacklane_product_en.json is now the source of truth for
pricing and update_stacklane_details.py applies it. This script is retained as a
targeted nudge when only the price needs to move.

Usage:
    set STACKLANE_SSH_PASS (PowerShell: $env:STACKLANE_SSH_PASS = '...')
    python scripts/update_myr_price.py [amount_display] [toyyibpay_amount_cents]
"""

import pathlib
import sys

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))

import stacklane_ssh as ssh  # noqa: E402

AMOUNT_DISPLAY = sys.argv[1] if len(sys.argv) > 1 else "RM 199"
AMOUNT_CENTS = int(sys.argv[2]) if len(sys.argv) > 2 else 19900


def main():
    print("[1] Connecting to stacklane.melur.com via SSH...")
    client = ssh._client()
    print("  [OK] Connected.")

    print(f"\n[2] Updating Malaysian price to {AMOUNT_DISPLAY} ({AMOUNT_CENTS} cents)...")
    php_code = f'''php -r "
require 'vendor/autoload.php';
\\$app = require_once 'bootstrap/app.php';
\\$kernel = \\$app->make(Illuminate\\Contracts\\Console\\Kernel::class);
\\$kernel->bootstrap();

use App\\Models\\Product;
\\$prod = Product::where('code', 'mystores')->first();
if (\\$prod) {{
    \\$prod->update([
        'amount_display' => '{AMOUNT_DISPLAY}',
        'toyyibpay_amount_cents' => {AMOUNT_CENTS},
    ]);
    echo 'DB_UPDATED: amount_display=' . \\$prod->amount_display . ' toyyibpay_amount_cents=' . \\$prod->toyyibpay_amount_cents;
}} else {{
    echo 'NOT_FOUND';
}}
"'''

    status, out, err = ssh.run(client, f"{php_code} && php artisan cache:clear && php artisan view:clear")

    client.close()

    if status != 0:
        print("[FAIL] Price update did not complete.")
        sys.exit(status or 1)

    print("\n[3] Done!")


if __name__ == "__main__":
    main()
