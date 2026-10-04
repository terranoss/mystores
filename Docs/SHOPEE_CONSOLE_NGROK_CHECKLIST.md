# Shopee Partner Console — ngrok callback checklist (sandbox)

Do this **once** after you claim your free static domain and put it in `ngrok.domain`.

## Production stays as-is

Do **not** change the production app redirect:

```text
https://mystores.melur.com/api/shopee/callback
```

Deploy continues to use `deploy/.env.production` → no live setting changes.

## Sandbox app (local linking)

1. Open [open.shopee.com](https://open.shopee.com) → your **sandbox** partner app.
2. Find Redirect URL / Callback URL settings.
3. Add (replace with your hostname from `ngrok.domain`):

```text
https://optimal-rested-flamingo.ngrok-free.app/api/shopee/callback
```

4. Save.
5. Run `composer dev` + `composer dev:tunnel`, then Link Store from `http://localhost:5173`.

If Shopee only allows one redirect URL on the sandbox app, keep the ngrok URL for local linking; production linking uses the separate live app / live redirect on `mystores.melur.com`.

## Push Mechanism (Order Status)

Orders sync primarily via Push, with the 15-minute `shopee:sync-orders` cron as a backup reconcile.

1. In Shopee Open Platform Console → your App → **Push Mechanism** / **Set Push**.
2. Set the callback URL to (replace with your ngrok or production host):

```text
https://optimal-rested-flamingo.ngrok-free.app/api/shopee/webhook
```

3. Verify the URL, then subscribe at least to:
   - **Order Status Update (Code 3)** — required for real-time order updates
   - Item Promotion / Reserved Stock pushes (Codes **7** / **8**) if you use stock webhooks
4. In `backend/.env`, set `SHOPEE_WEBHOOK_URL` to the **exact** same URL you registered in the console (signature verification uses this string):

```text
SHOPEE_WEBHOOK_URL=https://optimal-rested-flamingo.ngrok-free.app/api/shopee/webhook
```

5. Ensure a queue worker is running (`php artisan queue:work` or `composer dev`) so `SyncShopeeOrderJob` can process Code 3 pushes.

**Out of scope for now:** Order TrackingNo (Code 4) and Shipping Document Status (Code 15) — enable those when fulfillment / waybill flows are built.

## Production (live website)

ngrok is only for localhost. On the live site, use the real domain — no tunnel needed.

1. Use the **live** Shopee app (MelurCom), not the sandbox app.
2. In its Push Mechanism settings, set the callback URL to:

```text
https://mystores.melur.com/api/shopee/webhook
```

3. Verify, then subscribe to **Order Status Update (Code 3)** (and stock Codes 7/8 if used).
4. In `deploy/.env.production`, set the matching value (already in `.env.production.example`):

```text
SHOPEE_WEBHOOK_URL=https://mystores.melur.com/api/shopee/webhook
```

5. Queue processing is already handled on the server: `deploy.py` installs a cron running `artisan queue:work --stop-when-empty` every minute, so Code 3 jobs process without a dedicated worker. `schedule:run` also runs the 15-minute `shopee:sync-orders` reconcile.

Redeploy (or update the server `.env`) after changing `SHOPEE_WEBHOOK_URL` so the new value is live.

## How pushes are processed (important)

- **Shopee pushes carry no `event_id`.** A real Code 3 body looks like `{"code":3,"shop_id":...,"timestamp":...,"data":{"ordersn":...,"status":...,"update_time":...}}`. The webhook classifies a request by `code`, not by `event_id`.
- **Verification pings** (missing `code` or `code: 0`, e.g. the console "Verify and Save" test) get an empty `200` with no signature check, because that test push is signed with a separate Test Push Partner Key.
- **Actionable codes** (`3`, `7`, `8`) require a valid signature (`Authorization` header = `hmac_sha256(SHOPEE_WEBHOOK_URL + "|" + rawBody, key)`) before any work. Prefer `SHOPEE_PUSH_PARTNER_KEY` (Push settings); `SHOPEE_PARTNER_KEY` is accepted as fallback.
- **Dedupe is body-hash based.** Since there is no `event_id`, the idempotency key is `body_<sha256(rawBody)>` claimed atomically with `Cache::add` (30-minute TTL). Identical retries collapse; a later genuine status change hashes differently and is processed.
- **Diagnosing a signature mismatch:** a rejected push logs `Shopee webhook signature verification failed` with non-secret details (`signing_url`, `url_source`, `body_length`, and 8-char prefixes of the received vs computed signatures). A mismatched `SHOPEE_WEBHOOK_URL` is the usual cause.
