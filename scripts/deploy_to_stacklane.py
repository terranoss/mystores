"""Uploads the MyStore release ZIP and upserts the Stacklane product row.

Usage:
    set STACKLANE_SSH_PASS (PowerShell: $env:STACKLANE_SSH_PASS = '...')
    python scripts/deploy_to_stacklane.py
"""

import os
import sys
import pathlib
import paramiko
from scp import SCPClient

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))

import stacklane_ssh as ssh_helper  # noqa: E402

HOST = ssh_helper.HOST
USER = ssh_helper.USER
PORT = ssh_helper.PORT
KEY_PATH = str(ssh_helper.KEY_PATH)
ZIP_PATH = r"D:\GitHub\mystores\dist_release\MyStore-v1.0.0-Codebase.zip"
REMOTE_PATH = ssh_helper.REMOTE_PATH

def run_cmd(ssh, cmd):
    full_cmd = f"cd {REMOTE_PATH} && {cmd}"
    print(f">> Executing: {full_cmd}")
    stdin, stdout, stderr = ssh.exec_command(full_cmd)
    out = stdout.read().decode('utf-8', errors='replace').strip()
    err = stderr.read().decode('utf-8', errors='replace').strip()
    status = stdout.channel.recv_exit_status()
    if out:
        print(f"OUT: {out}")
    if err:
        print(f"ERR: {err}")
    return status, out, err

def main():
    print("[1] Connecting to stacklane.melur.com via SSH...")
    ssh = ssh_helper._client()
    print("  [OK] Connected successfully.")

    # 1. Ensure download directory exists
    print("\n[2] Ensuring remote downloads directory exists...")
    run_cmd(ssh, "mkdir -p public/downloads")

    # 2. Upload zip
    print(f"\n[3] Uploading {ZIP_PATH} to remote server...")
    with SCPClient(ssh.get_transport()) as scp:
        scp.put(ZIP_PATH, f"{REMOTE_PATH}/public/downloads/MyStore-v1.0.0-Codebase.zip")
    print("  [OK] Upload completed.")

    # 3. Add mystores prefix to LicenseKeyGenerator.php on server if not present
    print("\n[4] Checking LicenseKeyGenerator on server...")
    check_prefix_php = '''php -r "
\$file = 'app/Services/LicenseKeyGenerator.php';
\$content = file_get_contents(\$file);
if (!str_contains(\$content, \\"'mystores'\\")) {
    \$content = str_replace(
        \\"'pinscout-agency' => 'PSCA',\\",
        \\"'pinscout-agency' => 'PSCA',\\\\n        'mystores'        => 'MYS',\\",
        \$content
    );
    file_put_contents(\$file, \$content);
    echo 'PREFIX_ADDED';
} else {
    echo 'PREFIX_EXISTS';
}
"'''
    status, out, _ = run_cmd(ssh, check_prefix_php)

    # 4. Insert or update product in database via artisan tinker / PHP
    print("\n[5] Upserting 'mystores' product in Stacklane database...")
    upsert_product_php = '''php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$kernel = \$app->make(Illuminate\\\Contracts\\\Console\\\Kernel::class);
\$kernel->bootstrap();

use App\\\Models\\\Product;
\$prod = Product::updateOrCreate(
    ['code' => 'mystores'],
    [
        'slug' => 'mystores',
        'name' => 'MyStore',
        'tagline' => 'Self-Hosted Multi-Outlet POS & Retail Management Platform. Lifetime license — no subscriptions.',
        'description' => 'MyStore is a full-featured, self-hosted point-of-sale and multi-outlet retail management platform built with Laravel 12 and React 19. It comes with a free companion mobile app on Google Play (com.melur.mystores) that staff can connect to instantly using the Store Connection QR code. Features multi-outlet inventory, order management, staff role permissions, receipt printing, and a 5-minute self-hosted web installer.',
        'price_label' => 'Perpetual License (1 Domain)',
        'amount_display' => 'RM 220',
        'amount_display_usd' => '$50',
        'stripe_price_id' => 'price_mystores_usd50',
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

echo 'PRODUCT_SAVED: ID=' . \$prod->id . ' SLUG=' . \$prod->slug . ' NAME=' . \$prod->name;
"'''
    status, out, err = run_cmd(ssh, upsert_product_php)

    # 5. Clear application caches
    print("\n[6] Clearing route and application cache...")
    run_cmd(ssh, "php artisan route:clear && php artisan cache:clear")

    ssh.close()
    print("\n[7] Done!")

if __name__ == "__main__":
    main()
