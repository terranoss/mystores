import paramiko, json, sys
with open('deploy.config.json') as f:
    config = json.load(f)
ssh = paramiko.SSHClient()
ssh.load_system_host_keys()
ssh.set_missing_host_key_policy(paramiko.RejectPolicy())
key = paramiko.RSAKey.from_private_key_file(config['ssh']['key_path'], password=config['ssh']['password_or_passphrase'])
ssh.connect(config['ssh']['host'], port=config['ssh']['port'], username=config['ssh']['username'], pkey=key)
jobs = 0
for _ in range(12):
    stdin, stdout, stderr = ssh.exec_command('cd /home/melurcom/mystores.melur.com/backend && /opt/alt/php83/usr/bin/php artisan queue:work --once --timeout=60 2>&1 | tail -1')
    out = stdout.read().decode().strip()
    if 'DONE' in out:
        jobs += 1
    else:
        break
ssh.close()
print(jobs, end='')
