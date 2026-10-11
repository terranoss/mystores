#!/usr/bin/env python3
"""
Deploy the static MyStore demo to stacklane.melur.com.

Uploads demo/index.html and demo/assets/* into the Stacklane Laravel app's
public/ directory so it is served as plain static files at
https://stacklane.melur.com/demo/

The demo is a self-contained static replica with fabricated sample data.
It has no backend and makes no API calls.

Usage:
    set STACKLANE_SSH_PASS (PowerShell: $env:STACKLANE_SSH_PASS = '...')
    python scripts/deploy_demo.py
"""

import os
import sys
import posixpath
import paramiko
from scp import SCPClient

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import stacklane_ssh as ssh_helper  # noqa: E402

HOST = ssh_helper.HOST
USER = ssh_helper.USER
PORT = ssh_helper.PORT
KEY_PATH = str(ssh_helper.KEY_PATH)

REMOTE_PATH = ssh_helper.REMOTE_PATH
REMOTE_DEMO_DIR = f"{REMOTE_PATH}/public/demo"
REMOTE_ASSETS_DIR = f"{REMOTE_DEMO_DIR}/assets"

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
REPO_ROOT = os.path.abspath(os.path.join(SCRIPT_DIR, ".."))
DEMO_SRC = os.path.join(REPO_ROOT, "demo")

FILES = [
    ("index.html", "index.html"),
    (os.path.join("assets", "demo.css"), "assets/demo.css"),
    (os.path.join("assets", "demo.js"), "assets/demo.js"),
    (os.path.join("assets", "demo-data.js"), "assets/demo-data.js"),
]


def run_cmd(ssh, cmd):
    full_cmd = f"cd {REMOTE_PATH} && {cmd}"
    print(f">> {full_cmd}")
    _, stdout, stderr = ssh.exec_command(full_cmd)
    out = stdout.read().decode("utf-8", errors="replace").strip()
    err = stderr.read().decode("utf-8", errors="replace").strip()
    status = stdout.channel.recv_exit_status()
    if out:
        print(f"   OUT: {out}")
    if err:
        print(f"   ERR: {err}")
    return status, out, err


def main():
    missing = [rel for rel, _ in FILES if not os.path.isfile(os.path.join(DEMO_SRC, rel))]
    if missing:
        print(f"[FAIL] Missing demo source files: {missing}")
        sys.exit(1)

    print("[1] Connecting to stacklane.melur.com via SSH...")
    ssh_client = ssh_helper._client()
    print("  [OK] Connected.")

    print("\n[2] Creating remote demo directories...")
    run_cmd(ssh_client, f"mkdir -p {REMOTE_ASSETS_DIR}")

    print("\n[3] Uploading demo files...")
    with SCPClient(ssh_client.get_transport()) as scp:
        for rel, remote_rel in FILES:
            src = os.path.join(DEMO_SRC, rel)
            dst = posixpath.join(REMOTE_DEMO_DIR, remote_rel)
            scp.put(src, dst)
            size = os.path.getsize(src)
            print(f"  [OK] {remote_rel} ({size:,} bytes)")

    print("\n[4] Setting permissions...")
    run_cmd(ssh_client, f"find {REMOTE_DEMO_DIR} -type d -exec chmod 755 {{}} +")
    run_cmd(ssh_client, f"find {REMOTE_DEMO_DIR} -type f -exec chmod 644 {{}} +")

    print("\n[5] Verifying remote files...")
    status, out, _ = run_cmd(ssh_client, f"ls -la {REMOTE_DEMO_DIR} {REMOTE_ASSETS_DIR}")
    if status != 0:
        print("[FAIL] Could not list remote demo directory.")
        ssh_client.close()
        sys.exit(1)

    ssh_client.close()
    print("\n[OK] Demo deployed.")
    print(f"  URL: https://stacklane.melur.com/demo/")
    print("  Note: the product page link is source-controlled in the PinScout repo.")


if __name__ == "__main__":
    main()