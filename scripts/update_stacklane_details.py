"""Updates the MyStore product record on stacklane.melur.com.

This script used to also overwrite resources/views/storefront/product.blade.php
on the server with a 464-line Blade payload embedded inline. That mechanism is
gone: Blade views are now source-controlled in the PinScout repository and ship
via git. Running this script no longer touches any view file.

What it does now:
  1. Reads scripts/data/stacklane_product.json (copy for every locale).
  2. Upserts the base products row from the fallback-locale copy + pricing.
  3. Upserts product_translations rows, once that table exists.
  4. Clears the compiled view and application caches.

Usage:
  set STACKLANE_SSH_PASS (PowerShell: $env:STACKLANE_SSH_PASS = '...')
  python scripts/update_stacklane_details.py --dry-run
  python scripts/update_stacklane_details.py
"""

import argparse
import json
import pathlib
import shutil
import sys
import tempfile

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))

import stacklane_ssh as ssh  # noqa: E402

DATA_FILE = pathlib.Path(__file__).resolve().parent / "data" / "stacklane_product_en.json"
APPLY_PHP = pathlib.Path(__file__).resolve().parent / "stacklane_apply_product.php"

REMOTE_JSON = "/tmp/stacklane_product_payload.json"
REMOTE_PHP = "/tmp/stacklane_apply_product.php"


def load_payload():
    if not DATA_FILE.is_file():
        sys.exit(f"Missing data file: {DATA_FILE}")

    payload = json.loads(DATA_FILE.read_text(encoding="utf-8"))
    locales = sorted(payload.get("translations", {}))

    print(f"Data file : {DATA_FILE}")
    print(f"Product   : {payload.get('code')}")
    print(f"Locales   : {', '.join(locales) or '(none)'}")
    print(f"Pricing   : {payload.get('pricing', {})}")
    return payload


def run_dry_run(payload) -> None:
    print("\n--- DRY RUN: no changes will be made ---")
    print(f"Would upload payload to {ssh.REMOTE_PATH and REMOTE_JSON}")
    print(f"Would upload runner  to {REMOTE_PHP}")
    print(f"Would run: php {REMOTE_PHP} {REMOTE_JSON}")
    print("Would clear caches: php artisan view:clear && php artisan cache:clear")

    fallback = payload.get("translations", {}).get("en", {})
    print("\nBase products row (English fallback) would be set to:")
    for field in ("name", "tagline", "description", "price_label"):
        if field in fallback:
            print(f"  {field}: {fallback[field][:70]}")

    for field, value in payload.get("pricing", {}).items():
        print(f"  {field}: {value}")

    print("\nproduct_translations rows:")
    for locale, copy in payload.get("translations", {}).items():
        print(f"  [{locale}] name={copy.get('name')} tagline={str(copy.get('tagline'))[:60]}")

    print("\nNOTE: product.blade.php is NOT touched. It ships from git.")


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dry-run", action="store_true", help="Print the planned changes and exit.")
    args = parser.parse_args()

    payload = load_payload()

    if args.dry_run:
        run_dry_run(payload)
        return

    if not APPLY_PHP.is_file():
        sys.exit(f"Missing PHP runner: {APPLY_PHP}")

    print("\n[1] Connecting to stacklane.melur.com via SSH...")
    client = ssh._client()
    print("  [OK] Connected.")

    tmp_dir = pathlib.Path(tempfile.mkdtemp(prefix="stacklane-"))
    local_json = tmp_dir / "payload.json"
    local_json.write_text(json.dumps(payload, ensure_ascii=False), encoding="utf-8")

    try:
        print(f"\n[2] Uploading payload and runner to {ssh.HOST}...")
        ssh.upload(client, local_json, REMOTE_JSON)
        ssh.upload(client, APPLY_PHP, REMOTE_PHP)
        print("  [OK] Uploaded.")

        print("\n[3] Applying product record...")
        status, out, err = ssh.run(
            client,
            f"STACKLANE_APP_PATH={ssh.REMOTE_PATH} php {REMOTE_PHP} {REMOTE_JSON}",
        )

        if status != 0:
            print("\nApply failed; aborting before cache clear.", file=sys.stderr)
            ssh.run(client, f"rm -f {REMOTE_JSON} {REMOTE_PHP}")
            client.close()
            sys.exit(status or 1)

        print("\n[4] Clearing caches on server...")
        ssh.run(client, "php artisan view:clear && php artisan cache:clear")

        print("\n[5] Cleaning up remote temp files...")
        ssh.run(client, f"rm -f {REMOTE_JSON} {REMOTE_PHP}")

        client.close()
        print("\nDone. Views were not modified.")
    finally:
        shutil.rmtree(tmp_dir, ignore_errors=True)


if __name__ == "__main__":
    main()