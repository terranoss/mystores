# Local Shopee Store Linking with ngrok

Use this only for **local development**. Production (`mystores.melur.com`) is configured separately via `deploy/.env.production` and is **never** overwritten by these steps.

## Which Shopee app?

| Environment | App | Partner ID | Redirect domain |
|-------------|-----|------------|-----------------|
| Local (`backend/.env`) | MelurShopeeAPILocal | `1152258` | `https://optimal-rested-flamingo.ngrok-free.app` |
| Live (`deploy/.env.production`) | MelurCom | `2018442` | `https://mystores.melur.com` |

Each app allows one redirect domain — that is why local and live use different apps. Deploy uploads production `.env` only, so live stays on MelurCom.

## Why ngrok?

Shopee redirects the seller browser to `SHOPEE_REDIRECT_URL` after authorize. That URL must be publicly reachable. A free **static** ngrok domain tunnels HTTPS to `localhost:8000` so the callback hits your local Laravel.

## One-time setup

### 1. ngrok (already installed if `ngrok version` works)

```bash
ngrok config add-authtoken <your-token-from-dashboard>
```

Claim **one free static domain**: [dashboard.ngrok.com/domains](https://dashboard.ngrok.com/domains)

### 2. Local domain file

```bash
cp ngrok.domain.example ngrok.domain
# Edit ngrok.domain — hostname only, e.g. my-app.ngrok-free.app
```

`ngrok.domain` and `ngrok.yml` are gitignored.

### 3. Shopee Partner Console (sandbox app)

In the **sandbox** app redirect settings, add:

```text
https://optimal-rested-flamingo.ngrok-free.app/api/shopee/callback
```

Do **not** remove `https://mystores.melur.com/api/shopee/callback` from the production app. Sandbox and live apps stay separate.

### 4. Run locally

One command (Laravel + queue + Vite + ngrok):

```bash
cd backend
composer dev:all
```

Or split:

```bash
# Terminal A — app stack only
cd backend && composer dev

# Terminal B — tunnel only
cd backend && composer dev:tunnel
```

Open `http://localhost:5173`, sign in, click **Link Store**, approve the sandbox shop.

## Deploy / production

No changes. `deploy/deploy.py` uploads `deploy/.env.production` as the server `.env`, which keeps:

```ini
SHOPEE_REDIRECT_URL=https://mystores.melur.com/api/shopee/callback
SHOPEE_SANDBOX=false
```

Local `backend/.env` ngrok values never ship with the repo or the deploy upload.
