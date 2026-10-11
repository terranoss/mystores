"""Points the Stacklane product at the live Stripe price.

Usage:
    set STACKLANE_SSH_PASS (PowerShell: $env:STACKLANE_SSH_PASS = '...')
    python scripts/update_stripe_price_live.py
"""

import pathlib
import sys

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))

import stacklane_ssh as ssh_helper  # noqa: E402

HOST = ssh_helper.HOST
USER = ssh_helper.USER
PORT = ssh_helper.PORT
KEY_PATH = str(ssh_helper.KEY_PATH)
REMOTE_PATH = ssh_helper.REMOTE_PATH
PRICE_ID = "price_1UPA4mEdddTGsMgJo5Z4TwsL"

def main():
    print("[1] Connecting to stacklane.melur.com via SSH...")
    ssh = ssh_helper._client()
    print("  [OK] Connected successfully.")

    print(f"\n[2] Updating stripe_price_id in Stacklane database to: {PRICE_ID}...")
    php_code = f"""php -r "
require 'vendor/autoload.php';
\\$app = require_once 'bootstrap/app.php';
\\$kernel = \\$app->make(Illuminate\\Contracts\\Console\\Kernel::class);
\\$kernel->bootstrap();

use App\\Models\\Product;
\\$prod = Product::where('code', 'mystores')->first();
if (\\$prod) {{
    \\$prod->update(['stripe_price_id' => '{PRICE_ID}']);
    echo 'DB_UPDATED: ID=' . \\$prod->id . ' STRIPE_PRICE=' . \\$prod->stripe_price_id;
}} else {{
    echo 'NOT_FOUND';
}}
"
"""
    full_cmd = f"cd {REMOTE_PATH} && {php_code} && php artisan cache:clear"
    stdin, stdout, stderr = ssh.exec_command(full_cmd)
    out = stdout.read().decode('utf-8', errors='replace').strip()
    err = stderr.read().decode('utf-8', errors='replace').strip()
    print(f"OUTPUT: {out}")
    if err:
        print(f"ERR: {err}")

    ssh.close()
    print("\n[3] Done!")

if __name__ == "__main__":
    main()
