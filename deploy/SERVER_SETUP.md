# Server Setup Guide — mystores.melur.com

Do these steps once in cPanel before first deploy:

## 1. Create Subdomain

- In cPanel → **Subdomains**
- Subdomain: `stores`
- Domain: `melur.com`
- Document root: `/home/melurcom/mystores.melur.com`

## 2. PHP Version

- In cPanel → **MultiPHP Manager**
- **IMPORTANT**: Set `mystores.melur.com` to **PHP 8.2** or newer (currently on 8.1, project requires ^8.2)
  - In cPanel → MultiPHP Manager → select mystores.melur.com → choose ea-php82

## 3. SSH Access

- In cPanel → **SSH Access**
- Add your public SSH key
- Test: `ssh melurcom@103.10.78.60 -p 55000`

## 4. Composer

- In cPanel → **Setup Composer**
- Or verify it's available via SSH: `composer --version`

## 5. Database

Create a dedicated production database and user in cPanel. Keep credentials only in the ignored `deploy/.env.production` file:
- Database: `<production-database>`
- User: `<production-database-user>`
- Password: `<production-database-password>`

## 6. SSL

- In cPanel → **SSL/TLS Status**
- Run AutoSSL for `mystores.melur.com`
