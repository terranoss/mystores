import os
import sys
import json
import getpass
import shlex
import subprocess
from datetime import datetime
import paramiko
from scp import SCPClient
import requests


def require_safe_value(value, name, pattern):
    import re
    if not value or not re.fullmatch(pattern, value):
        raise ValueError(f"Unsafe or missing {name} in deployment configuration")
    return value


def print_step(step, desc):
    print(f"\n==================================================")
    print(f" STEP {step}: {desc}")
    print(f"==================================================")


def run_remote_command(ssh, command, cwd):
    full_command = f"cd {shlex.quote(cwd)} && {command}"
    print(f"Executing: {full_command}")
    stdin, stdout, stderr = ssh.exec_command(full_command)
    exit_status = stdout.channel.recv_exit_status()
    out = stdout.read().decode('utf-8', errors='replace').strip()
    err = stderr.read().decode('utf-8', errors='replace').strip()
    if out:
        print(f"Output:\n{out}")
    if err:
        print(f"Error Output:\n{err}", file=sys.stderr)
    return exit_status, out, err


def remote_git_sync(ssh, deploy_path, branch):
    checkout_cmd = f"git checkout {shlex.quote(branch)}"
    pull_cmd = f"git pull --ff-only origin {shlex.quote(branch)}"
    status, _, _ = run_remote_command(ssh, f"git fetch origin && {checkout_cmd} && {pull_cmd}", deploy_path)
    if status == 0:
        return
    timestamp = datetime.now().strftime("%Y%m%d-%H%M%S")
    stash_msg = f"pre-deploy-backup-{timestamp}"
    print("[-] Git pull failed. Preserving local server changes and resetting to origin branch...")
    run_remote_command(ssh, f"git stash push --include-untracked -m {shlex.quote(stash_msg)} || true", deploy_path)
    status, _, _ = run_remote_command(ssh, f"git fetch origin && git reset --hard {shlex.quote(f'origin/{branch}')} && git clean -fd", deploy_path)
    if status != 0:
        print("[-] Git sync fallback failed!")
        sys.exit(1)


def main():
    script_dir = os.path.dirname(__file__)
    repo_root = os.path.abspath(os.path.join(script_dir, ".."))
    frontend_path = os.path.join(repo_root, "frontend")
    config_path = os.path.join(script_dir, "deploy.config.json")
    env_prod_path = os.path.join(script_dir, ".env.production")

    if not os.path.exists(config_path):
        print(f"[-] Configuration file not found at: {config_path}")
        print("[-] Please copy deploy.config.json.example to deploy.config.json and fill in your details.")
        sys.exit(1)

    if not os.path.exists(env_prod_path):
        print(f"[-] Production .env file not found at: {env_prod_path}")
        print("[-] Please copy .env.production.example to .env.production and fill in your details.")
        sys.exit(1)

    # Production runs on Linux. Normalize Windows CRLF before both uploading
    # and sourcing the file remotely.
    with open(env_prod_path, "rb") as env_file:
        env_content = env_file.read()
    normalized_env_content = env_content.replace(b"\r\n", b"\n").replace(b"\r", b"\n")
    if normalized_env_content != env_content:
        with open(env_prod_path, "wb") as env_file:
            env_file.write(normalized_env_content)
        print("[+] Normalized .env.production line endings to LF.")

    with open(config_path, "r") as f:
        config = json.load(f)

    ssh_cfg = config.get("ssh", {})
    git_cfg = config.get("github", {})
    srv_cfg = config.get("server", {})

    host = ssh_cfg.get("host")
    username = ssh_cfg.get("username")
    port = ssh_cfg.get("port", 22)
    use_password = ssh_cfg.get("use_password", False)
    key_path = ssh_cfg.get("key_path")

    deploy_path = srv_cfg.get("deploy_path")
    php_bin = srv_cfg.get("php_binary", "php")
    composer_bin = srv_cfg.get("composer_binary", "composer")
    backend_path = os.path.join(deploy_path, "backend").replace("\\", "/")

    require_safe_value(host, "SSH host", r"[A-Za-z0-9.-]+")
    require_safe_value(username, "SSH username", r"[A-Za-z0-9._-]+")
    require_safe_value(deploy_path, "deployment path", r"/[A-Za-z0-9._/-]+")
    require_safe_value(git_cfg.get("branch", "main"), "Git branch", r"[A-Za-z0-9._/-]+")
    require_safe_value(php_bin, "PHP binary", r"[A-Za-z0-9._/-]+")
    require_safe_value(composer_bin, "Composer binary", r"[A-Za-z0-9._/-]+")

    print_step(0, "Building Frontend")
    npm_command = "npm.cmd" if os.name == "nt" else "npm"
    try:
        build = subprocess.run(
            [npm_command, "run", "build"],
            cwd=frontend_path,
            check=False,
        )
    except FileNotFoundError:
        print("[-] npm was not found. Install Node.js and ensure npm is in PATH.")
        sys.exit(1)
    if build.returncode != 0:
        print("[-] Frontend build failed; deployment aborted.")
        sys.exit(build.returncode)
    print("[+] Frontend build completed successfully.")

    print_step(1, "Connecting to Server via SSH")
    ssh = paramiko.SSHClient()
    ssh.load_system_host_keys()
    ssh.set_missing_host_key_policy(paramiko.RejectPolicy())

    try:
        if use_password:
            password = ssh_cfg.get("password_or_passphrase")
            if not password:
                password = getpass.getpass(f"Enter SSH Password for {username}@{host}: ")
            ssh.connect(host, port=port, username=username, password=password)
        else:
            passphrase = ssh_cfg.get("password_or_passphrase")
            if not passphrase:
                is_encrypted = False
                if os.path.exists(key_path):
                    with open(key_path, "r") as kf:
                        first_lines = "".join([kf.readline() for _ in range(5)])
                        if "ENCRYPTED" in first_lines:
                            is_encrypted = True
                if is_encrypted:
                    passphrase = getpass.getpass(f"Enter Passphrase for Encrypted Private Key '{key_path}': ")
            pkey = paramiko.RSAKey.from_private_key_file(key_path, password=passphrase if passphrase else None)
            ssh.connect(host, port=port, username=username, pkey=pkey)
        print("[+] SSH Connection Successful!")
    except Exception as e:
        print(f"[-] SSH Connection Failed: {e}")
        sys.exit(1)

    ssh.exec_command(f"mkdir -p {shlex.quote(deploy_path)}")

    print_step(2, "Backing Up Production Database")
    backup_dir = deploy_path.rstrip("/") + "-backups"
    backup_timestamp = datetime.now().strftime("%Y%m%d-%H%M%S")
    backup_file = f"{backup_dir}/database-{backup_timestamp}.sql.gz"
    check_status, _, _ = run_remote_command(ssh, "test -f .env", backend_path)
    if check_status == 0:
        backup_command = (
            f"set -o pipefail && mkdir -p {shlex.quote(backup_dir)} && "
            "sed -i 's/\r$//' .env && "
            "set -a && . ./.env && set +a && umask 077 && "
            "mysqldump --single-transaction --quick --lock-tables=false --no-tablespaces "
            "--host=\"$DB_HOST\" --port=\"$DB_PORT\" --user=\"$DB_USERNAME\" "
            f"--password=\"$DB_PASSWORD\" \"$DB_DATABASE\" | gzip > {shlex.quote(backup_file)} && "
            f"test -s {shlex.quote(backup_file)}"
        )
        status, _, _ = run_remote_command(ssh, backup_command, backend_path)
        if status != 0:
            print("[-] Database backup failed; deployment aborted.")
            sys.exit(1)
        print(f"[+] Database backup created: {backup_file}")
    else:
        print("[*] No existing .env found on server. Skipping pre-deploy backup.")

    print_step(3, "Synchronizing Files via Git")
    is_first_deploy = config.get("first_deploy", True)

    if is_first_deploy:
        repo_url = git_cfg.get("repo")
        branch = git_cfg.get("branch", "main")
        print(f"[*] First deploy: initializing Git repository in {deploy_path}...")
        run_remote_command(ssh, "rm -rf .git", deploy_path)
        git_init_cmds = [
            "git init",
            f"git remote add origin {shlex.quote(repo_url)}",
            "git fetch origin",
            f"git checkout -f {shlex.quote(branch)}"
        ]
        for cmd in git_init_cmds:
            status, _, _ = run_remote_command(ssh, cmd, deploy_path)
            if status != 0:
                print(f"[-] Command '{cmd}' failed! Aborting Git initialization.")
                sys.exit(1)
        config["first_deploy"] = False
        with open(config_path, "w") as f:
            json.dump(config, f, indent=2)
        print("[+] Updated deploy.config.json -> first_deploy: false")
    else:
        branch = git_cfg.get("branch", "main")
        print(f"[*] Updating existing deployment: pulling branch {branch}...")
        remote_git_sync(ssh, deploy_path, branch)

    print_step(4, "Installing Composer Dependencies")
    composer_cmd = f"{php_bin} {composer_bin} install --no-dev --optimize-autoloader --no-scripts"
    status, _, _ = run_remote_command(ssh, composer_cmd, backend_path)
    if status != 0:
        print("[*] Retrying with global composer command...")
        status, _, _ = run_remote_command(ssh, "composer install --no-dev --optimize-autoloader --no-scripts", backend_path)
    if status != 0:
        print("[-] Composer installation failed due to an incompatible or unavailable environment.")
        sys.exit(1)

    print_step(5, "Uploading Environment & Frontend Build")
    try:
        with SCPClient(ssh.get_transport()) as scp:
            # Upload .env
            print(f"[*] Uploading .env.production as .env...")
            scp.put(env_prod_path, remote_path=f"{backend_path}/.env")

            # Upload compiled frontend assets to backend/public/
            local_build_path = os.path.join(repo_root, "frontend", "dist")
            if os.path.exists(local_build_path):
                print(f"[*] Uploading frontend build from {local_build_path}...")
                scp.put(local_build_path, remote_path=f"{backend_path}/public/tmp-build", recursive=True)
                # Move files from tmp-build to public root, then remove tmp dir
                run_remote_command(ssh, f"cp -r {backend_path}/public/tmp-build/* {backend_path}/public/ && rm -rf {backend_path}/public/tmp-build", "/tmp")
                print("[+] Frontend assets uploaded successfully.")
            else:
                print("[-] Warning: frontend/dist not found. Run 'npm run build' first!")
    except Exception as e:
        print(f"[-] SCP upload failed: {e}")
        sys.exit(1)

    print_step(6, "Running Laravel Commands")
    # Only generate key if not already set (preserves existing tokens/sessions)
    run_remote_command(ssh, f"grep -q '^APP_KEY=[^ ]' .env || {php_bin} artisan key:generate --force", backend_path)

    commands = [
        f"{php_bin} artisan package:discover",
        f"{php_bin} artisan migrate --force",
        f"{php_bin} artisan config:cache",
        f"{php_bin} artisan route:cache",
        f"{php_bin} artisan storage:link"
    ]
    for cmd in commands:
        status, _, _ = run_remote_command(ssh, cmd, backend_path)
        if status != 0:
            if "storage:link" in cmd:
                print("[*] storage:link failed (likely already exists), continuing...")
                continue
            print(f"[-] Command failed: {cmd}")
            sys.exit(1)

    print_step(7, "Configuring Entrypoint for cPanel")
    print("[*] Creating root .htaccess rewrite to backend/public/...")
    htaccess_content = """<IfModule mod_rewrite.c>
    RewriteEngine On

    # Enforce HTTPS
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    <IfModule mod_headers.c>
        Header always set X-Content-Type-Options "nosniff"
        Header always set X-Frame-Options "DENY"
        Header always set Referrer-Policy "strict-origin-when-cross-origin"
        Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
        Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
        Header always set Content-Security-Policy-Report-Only "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'"
    </IfModule>

    # Rewrite all requests to the backend public directory
    RewriteRule ^(.*)$ backend/public/$1 [L]
</IfModule>
"""
    htaccess_remote_path = os.path.join(deploy_path, ".htaccess").replace("\\", "/")
    cpanel_handler = ""
    sftp = ssh.open_sftp()
    
    # Try to read existing .htaccess to preserve cPanel PHP handler block
    try:
        with sftp.file(htaccess_remote_path, "r") as f:
            existing_content = f.read()
            if isinstance(existing_content, bytes):
                existing_content = existing_content.decode('utf-8', errors='ignore')
            import re
            match = re.search(r"(# php -- BEGIN cPanel-generated handler.*# php -- END cPanel-generated handler)", existing_content, re.DOTALL)
            if match:
                cpanel_handler = "\n" + match.group(1) + "\n"
                print("[+] Preserved cPanel-generated PHP handler block from existing .htaccess.")
    except Exception:
        pass

    with sftp.file(htaccess_remote_path, "w") as f:
        f.write(htaccess_content + cpanel_handler)
    sftp.close()

    print_step(8, "Configuring Cron Jobs")
    clean_deploy_path = deploy_path.rstrip('/')
    cron_jobs = [
        f"* * * * * {php_bin} -d disable_functions=\"\" {clean_deploy_path}/backend/artisan schedule:run >> {clean_deploy_path}/backend/storage/logs/scheduler.log 2>&1",
        f"* * * * * {php_bin} {clean_deploy_path}/backend/artisan queue:work --stop-when-empty --timeout=600 >> /dev/null 2>&1"
    ]
    
    status, existing_cron, _ = run_remote_command(ssh, "crontab -l", "/tmp")
    if status != 0:
        existing_cron = ""
        
    cron_lines = [line.strip() for line in existing_cron.splitlines() if line.strip()]
    
    # Filter out any older entries for this project's schedule:run or queue:work to avoid duplicates
    filtered_cron_lines = []
    for line in cron_lines:
        is_this_project_schedule = ("backend/artisan schedule:run" in line) and (clean_deploy_path in line or deploy_path in line)
        is_this_project_queue = ("backend/artisan queue:work" in line) and (clean_deploy_path in line or deploy_path in line)
        if not is_this_project_schedule and not is_this_project_queue:
            filtered_cron_lines.append(line)

    updated = False
    if len(filtered_cron_lines) != len(cron_lines):
        updated = True

    for job in cron_jobs:
        if job not in filtered_cron_lines:
            filtered_cron_lines.append(job)
            updated = True
            
    if updated:
        cron_content = "\n".join(filtered_cron_lines) + "\n"
        sftp = ssh.open_sftp()
        temp_cron_path = f"{clean_deploy_path}/cron.tmp"
        try:
            with sftp.file(temp_cron_path, "w") as f:
                f.write(cron_content)
            run_remote_command(ssh, f"crontab {temp_cron_path} && rm -f {temp_cron_path}", "/tmp")
            print("[+] Cron jobs configured successfully!")
        except Exception as e:
            print(f"[-] Failed to update cron jobs: {e}")
        finally:
            sftp.close()
    else:
        print("[*] Cron jobs are already configured.")

    print_step(9, "Verifying Deployment")
    app_url = None
    with open(env_prod_path, "r") as f:
        for line in f:
            if line.startswith("APP_URL="):
                app_url = line.split("=", 1)[1].strip()
                break

    if app_url:
        print(f"[*] Testing connection to: {app_url}")
        try:
            resp = requests.get(app_url, timeout=10)
            if resp.status_code == 200:
                print(f"[+] Success! Server returned HTTP 200.")
            else:
                print(f"[-] Warning: Server returned HTTP status {resp.status_code}")
        except Exception as e:
            print(f"[-] Could not verify app url: {e}")
    else:
        print("[-] APP_URL not found in .env.production, skipping verification.")

    ssh.close()
    print("\n[+] DEPLOYMENT COMPLETED SUCCESSFULLY!")


if __name__ == "__main__":
    main()
