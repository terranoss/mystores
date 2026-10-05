# Plan: Remove Sandbox Mode - Test on Production Server

## Goal
Configure the application to use **production API credentials and endpoints** (no sandbox) and test directly on the production server `mystores.melur.com`.

## Current State
- `backend/.env` has `SHOPEE_SANDBOX=true`, `LAZADA_SANDBOX=false`
- Production credentials exist in `deploy/.env.production` (already deployed)
- Production server runs at `https://mystores.melur.com` with production callbacks already registered

## Required Changes

### 1. Update `backend/.env` with Production Values (for local reference / deploy sync)
Copy production credentials from `deploy/.env.production`:

```ini
# Shopee - Production
SHOPEE_PARTNER_ID=2018442
SHOPEE_PARTNER_KEY=shpk594d4743614f784d6e664c4e544f544448745346515a5353485971567a64
SHOPEE_PUSH_PARTNER_KEY=7059656d6c47636343414b6a6c6378636350477956586f496a486f4d446e734b
SHOPEE_REDIRECT_URL=https://mystores.melur.com/api/shopee/callback
SHOPEE_WEBHOOK_URL=https://mystores.melur.com/api/shopee/webhook
SHOPEE_SANDBOX=false

# Lazada - Production
LAZADA_APP_KEY=132539
LAZADA_APP_SECRET=lXxT6v55pTmmQokh3ChIZ1XPD3s8fO8N
LAZADA_REDIRECT_URL=https://mystores.melur.com/api/lazada/callback
LAZADA_SANDBOX=false
```

### 2. Update Config Defaults
Change config file defaults to `false` so production is the default:

- `config/shopee.php`: `'sandbox' => env('SHOPEE_SANDBOX', false)`
- `config/lazada.php`: `'sandbox' => env('LAZADA_SANDBOX', false)`

### 3. Test Workflow: On Production Server
Since callbacks point to `mystores.melur.com`, testing must happen on the production server:

```bash
# SSH to production server
ssh user@server

# Navigate to app directory
cd /path/to/mystores/backend

# Run scheduler/queue workers if needed
php artisan schedule:work
php artisan queue:work

# Access via browser: https://mystores.melur.com
# - Login
# - Click "Link Store" → OAuth works (callbacks hit same server)
# - Test stock updates, order sync, vacation mode
```

## Validation Steps
1. Update local `.env` and config defaults with production values
2. Deploy to production (or verify already deployed): `cd deploy && python deploy.py`
3. Access `https://mystores.melur.com` in browser
4. Login → Dashboard → Click "+ Shopee" / "+ Lazada"
5. Complete OAuth flow → store appears in dashboard with "Active" status
6. Test stock update, order sync, vacation mode toggle
7. Verify API calls hit production endpoints (network tab: `partner.shopeemobile.com`, not sandbox)

## Risks
- **Real data mutation**: All actions affect LIVE seller accounts
- **Rate limits**: Production API limits apply
- **No isolation**: Changes visible to real customers/sellers immediately

## Files to Modify
- `backend/.env` — primary change (for deploy sync reference)
- `config/shopee.php` — default change
- `config/lazada.php` — default change