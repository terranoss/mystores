"""Shared SSH connection helper for the stacklane.melur.com deploy scripts.

Centralises connection settings so that credentials and host-key policy live in
exactly one place. Previously every script hardcoded the passphrase inline and
used AutoAddPolicy(), which silently trusts any host on first contact.

Environment
-----------
STACKLANE_SSH_PASS   Private-key passphrase. Required for encrypted keys.
STACKLANE_SSH_HOST   Override the default host (useful for staging).
STACKLANE_APP_PATH   Override the remote Laravel root.
"""

import os
import pathlib
import sys

import paramiko

HOST = os.environ.get("STACKLANE_SSH_HOST", "103.10.78.60")
USER = "melurcom"
PORT = 55000
KEY_PATH = pathlib.Path(r"D:\GitHub\PinScout\docs\melurcom")
REMOTE_PATH = os.environ.get("STACKLANE_APP_PATH", "/home/melurcom/stacklane.melur.com/web")
KNOWN_HOSTS = pathlib.Path(__file__).resolve().parent / ".known_hosts"

# Fixed so `ssh-keyscan` output and paramiko agree on the hash format.
KNOWN_HOSTS_FORMAT = "[%s]:%s %s"

_HOST_FINGERPRINT_HINT = (
    "Host key for {host}:{port} is not trusted yet.\n"
    "  Verify the fingerprint with the server admin, then run:\n"
    "      python scripts/stacklane_ssh.py --trust\n"
    "  Or, for a throwaway dev check only, set STACKLANE_INSECURE_HOST_KEY=1."
)


class ConnectionError_(RuntimeError):
    """Raised when the deploy scripts cannot establish a usable connection."""


def trust_host() -> None:
    """Record the current host key so future runs can verify it."""
    transport = paramiko.Transport((HOST, PORT))
    try:
        transport.connect()
        key = transport.get_remote_server_key()
    finally:
        transport.close()

    KNOWN_HOSTS.parent.mkdir(parents=True, exist_ok=True)
    entry = KNOWN_HOSTS_FORMAT % (HOST, PORT, f"{key.get_name()} {key.get_base64()}")

    existing = KNOWN_HOSTS.read_text(encoding="utf-8") if KNOWN_HOSTS.exists() else ""
    if entry not in existing:
        with KNOWN_HOSTS.open("a", encoding="utf-8") as handle:
            handle.write(entry + "\n")

    print(f"Trusted host key written to {KNOWN_HOSTS}")
    print(f"  {key.get_name()} SHA256:{paramiko.util.b64.b64encode(key.get_fingerprint()).decode().rstrip('=')}")


def _client() -> paramiko.SSHClient:
    passphrase = os.environ.get("STACKLANE_SSH_PASS")

    if not passphrase:
        raise ConnectionError_(
            "STACKLANE_SSH_PASS is not set.\n"
            "  PowerShell:  $env:STACKLANE_SSH_PASS = '<passphrase>'\n"
            "  Or persist:  setx STACKLANE_SSH_PASS '<passphrase>'"
        )

    if not KEY_PATH.is_file():
        raise ConnectionError_(f"SSH key not found at {KEY_PATH}")

    client = paramiko.SSHClient()
    client.load_system_host_keys()

    if KNOWN_HOSTS.exists():
        client.load_host_keys(str(KNOWN_HOSTS))

    if os.environ.get("STACKLANE_INSECURE_HOST_KEY") == "1":
        # Explicit opt-in only. Accepts an unverified host key.
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    else:
        client.set_missing_host_key_policy(paramiko.RejectPolicy())

    try:
        key = paramiko.RSAKey.from_private_key_file(str(KEY_PATH), password=passphrase)
        client.connect(HOST, port=PORT, username=USER, pkey=key, timeout=20)
    except paramiko.SSHException as exc:
        raise ConnectionError_(str(exc)) from exc

    return client


def run(client: paramiko.SSHClient, command: str, cwd: str = REMOTE_PATH):
    """Run a command inside the remote Laravel root. Returns (status, stdout, stderr)."""
    full = f"cd {cwd} && {command}"
    print(f">> {full}")

    _stdin, stdout, stderr = client.exec_command(full)
    out = stdout.read().decode("utf-8", errors="replace").strip()
    err = stderr.read().decode("utf-8", errors="replace").strip()
    status = stdout.channel.recv_exit_status()

    if out:
        print(out)
    if err:
        print(f"ERR: {err}", file=sys.stderr)

    return status, out, err


def upload(client: paramiko.SSHClient, local: pathlib.Path, remote: str) -> None:
    """Copy a local file to the remote host over SFTP."""
    sftp = client.open_sftp()
    try:
        sftp.put(str(local), remote)
    finally:
        sftp.close()


def download(client: paramiko.SSHClient, remote: str, local: pathlib.Path) -> None:
    """Copy a file off the remote host over SFTP."""
    local.parent.mkdir(parents=True, exist_ok=True)
    sftp = client.open_sftp()
    try:
        sftp.get(remote, str(local))
    finally:
        sftp.close()


if __name__ == "__main__":
    if "--trust" in sys.argv:
        trust_host()
    else:
        print(__doc__)
        sys.exit(1)