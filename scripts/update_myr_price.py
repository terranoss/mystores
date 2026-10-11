import paramiko

HOST = "103.10.78.60"
USER = "melurcom"
PORT = 55000
KEY_PATH = r"D:\GitHub\PinScout\docs\melurcom"
REMOTE_PATH = "/home/melurcom/stacklane.melur.com/web"

def main():
    print("[1] Connecting to stacklane.melur.com via SSH...")
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    key = paramiko.RSAKey.from_private_key_file(KEY_PATH, password="Wanderer*1974")
    ssh.connect(HOST, port=PORT, username=USER, pkey=key, timeout=20)
    print("  [OK] Connected successfully.")

    print("\n[2] Updating Malaysian price to RM 199 (19900 cents) in database...")
    php_code = """php -r "
require 'vendor/autoload.php';
\\$app = require_once 'bootstrap/app.php';
\\$kernel = \\$app->make(Illuminate\\Contracts\\Console\\Kernel::class);
\\$kernel->bootstrap();

use App\\Models\\Product;
\\$prod = Product::where('code', 'mystores')->first();
if (\\$prod) {
    \\$prod->update([
        'amount_display' => 'RM 199',
        'toyyibpay_amount_cents' => 19900,
    ]);
    echo 'DB_UPDATED: amount_display=' . \\$prod->amount_display . ' toyyibpay_amount_cents=' . \\$prod->toyyibpay_amount_cents;
} else {
    echo 'NOT_FOUND';
}
"
"""
    full_cmd = f"cd {REMOTE_PATH} && {php_code} && php artisan cache:clear && php artisan view:clear"
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
