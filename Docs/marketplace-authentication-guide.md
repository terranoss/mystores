# Marketplace Authentication & Authorization Guide (Shopee & Lazada)

This document provides a comprehensive guide to how **MyStores** connects to and maintains authentication for **Shopee Open Platform** and **Lazada Open Platform** stores via OAuth 2.0.

---

## 1. Architecture Overview

Both Shopee and Lazada use standard OAuth 2.0 authorization code flows with short-to-medium-lived access tokens and refresh tokens.

```text
+----------------+          +-------------------+          +--------------------+
|  Store Owner   |          |     MyStores      |          | Market Platform    |
|  (Browser)     |          | (Frontend/Backend)|          | (Shopee / Lazada)  |
+----------------+          +-------------------+          +--------------------+
       |                              |                              |
       | 1. Click "Link Store"        |                              |
       |----------------------------->|                              |
       |                              | 2. Generate signed auth URL  |
       |                              |    + CSRF state hash         |
       | 3. Redirect to Platform Auth |                              |
       |<-----------------------------|                              |
       |                                                             |
       | 4. User logs into Seller Account & confirms authorization   |
       |------------------------------------------------------------>|
       |                                                             |
       | 5. Platform redirects browser back with ?code=...&state=... |
       |------------------------------------------------------------>|
       |                              |                              |
       | 6. Browser hits callback     |                              |
       |----------------------------->|                              |
       |                              | 7. Validate state (anti-CSRF)|
       |                              | 8. Server-to-server POST:    |
       |                              |    Exchange code for tokens  |
       |                              |----------------------------->|
       |                              |                              |
       |                              | 9. Receive Access & Refresh  |
       |                              |<-----------------------------|
       |                              | 10. Encrypt tokens (AES-256) |
       |                              |     & save store profile     |
       | 11. Redirect to /dashboard   |                              |
       |<-----------------------------|                              |
       |     (?linked=1)              |                              |
```

---

## 2. Shopee Open Platform Authentication

### 2.1 Configuration & Prerequisites

In the Shopee Open Platform Console:
* **App Type**: Custom App / In-house
* **Partner ID**: Found in App Management console.
* **Partner Key**: Found in App Management console.
* **Redirect URL**: Must be registered in the Shopee Console:
  ```text
  https://mystores.melur.com/api/shopee/callback
  ```
  *(Or your custom environment URL)*

In MyStores:
* Workspace owners configure `Partner ID`, `Partner Key`, and optional `Redirect URL` under **Settings > Shopee Credentials**.

### 2.2 The Authorization Process

1. **Step 1: Auth URL Generation (`GET /api/shopee/auth-url`)**:
   - The backend creates a 64-character random string (`state`) and stores its SHA-256 hash in `shopee_oauth_states` with a 10-minute expiry.
   - Computes HMAC-SHA256 signature using `partner_id`, path (`/api/v2/shop/auth_partner`), `timestamp`, and `partner_key`.
   - Generates the authorization link:
     ```text
     https://partner.shopeemobile.com/api/v2/shop/auth_partner?partner_id={id}&timestamp={ts}&sign={sign}&redirect={redirect_url}?state={state}
     ```
2. **Step 2: Seller Login & Grant**:
   - The user is redirected to Shopee's official login screen and clicks **Confirm / Authorize**.
3. **Step 3: Callback & Automatic Token Exchange (`GET /api/shopee/callback`)**:
   - Shopee redirects the user's browser back to:
     ```text
     https://mystores.melur.com/api/shopee/callback?code={code}&shop_id={shop_id}&state={state}
     ```
   - The backend validates the `state` in an atomic database transaction to prevent replay attacks and marks it consumed.
   - The backend immediately calls Shopee's API (`/api/v2/auth/token/get`) with the code and `shop_id`.
4. **Step 4: Persistence & Encryption**:
   - Tokens received:
     - `access_token` (valid for **4 hours**)
     - `refresh_token` (valid for **30 days**)
     - `auth_expires_at` (valid for **365 days**; seller authorization limit)
   - Stored in the `shopee_tokens` table with **AES-256 encryption**.
   - User is redirected back to the MyStores dashboard (`/dashboard?linked=1`).

### 2.3 Token Refresh & Rotation

- **On-Demand (JIT)**: Whenever an API call is made (`ShopeeService::forStore()`), if the token expires within 30 minutes, it automatically executes a token refresh with an atomic Redis/Cache lock (`Cache::lock("shopee:token-refresh:shop_{shop_id}", 30)`).
- **Scheduled Cron**: `php artisan shopee:refresh-tokens` runs hourly to rotate any tokens expiring within the next 60 minutes.

---

## 3. Lazada Open Platform Authentication

### 3.1 Configuration & Prerequisites

In the Lazada Open Platform Console:
* **App Key**: In App Overview.
* **App Secret**: In App Overview.
* **Callback URL**: Must be registered in the Lazada Console:
  ```text
  https://mystores.melur.com/api/lazada/callback
  ```

In MyStores:
* Configured under **Settings > Lazada Credentials** (or default environment config).

### 3.2 The Authorization Process

1. **Step 1: Auth URL Generation (`GET /api/lazada/auth-url`)**:
   - The backend creates a 64-character random string (`state`) and saves the hash in `shopee_oauth_states` with `platform = 'lazada'`.
   - Constructs Lazada's authorization URL:
     ```text
     https://auth.lazada.com/oauth/authorize?response_type=code&force_auth=true&redirect_uri={redirect_url}&client_id={app_key}&state={state}
     ```
2. **Step 2: Seller Login & Grant**:
   - The seller logs in to Lazada Seller Center and approves the authorization.
3. **Step 3: Callback & Automatic Token Exchange (`GET /api/lazada/callback`)**:
   - Lazada redirects back to:
     ```text
     https://mystores.melur.com/api/lazada/callback?code={code}&state={state}
     ```
   - Backend consumes the `state` transactionally.
   - Exchanges the code using Lazada's SDK (`/auth/token/create`).
   - Retrieves the store's profile and seller information (`/seller/get`).
4. **Step 4: Persistence & Encryption**:
   - Tokens received:
     - `access_token` (valid for **~30 days**)
     - `refresh_token` (valid for **~180 days**)
   - Tokens are stored encrypted in the same `shopee_tokens` table linked to the store record (`platform = 'lazada'`).
   - Browser is redirected to `/dashboard?linked=1`.

---

## 4. Key Differences Summary

| Feature | Shopee Open Platform | Lazada Open Platform |
| :--- | :--- | :--- |
| **User Interaction** | Fully automatic (No copy-pasting required) | Fully automatic (No copy-pasting required) |
| **Callback URL** | `/api/shopee/callback` | `/api/lazada/callback` |
| **Access Token Lifetime** | 4 Hours (14,400s) | ~30 Days (2,592,000s) |
| **Refresh Token Lifetime**| 30 Days | ~180 Days |
| **Reauthorization Period**| 1 Year (365 days max) | When refresh token expires (~180 days) |
| **Token Encryption** | AES-256 (`encrypt()`) | AES-256 (`encrypt()`) |
| **Concurrency Safeguard**| Atomic Lock per Shop ID | Atomic Lock per Shop ID |

---

## 5. Troubleshooting & FAQ

### Q: Does the user ever have to copy-paste the Authorization Code?
**No.** The browser handles the redirect to `/api/shopee/callback` or `/api/lazada/callback` directly. The backend exchanges the code in milliseconds and redirects the user back to the dashboard with a success message.

### Q: What causes "Redirect URL Mismatch"?
The URL configured in your platform developer console (Shopee or Lazada) must match the URL sent by the app character-for-character, including `https://` and `/api/shopee/callback`.

### Q: What happens if a token fails to refresh?
The store status transitions to inactive or requires reauthorization. A notification will appear in the dashboard prompting the owner to click **Reauthorize**, which initiates a seamless re-link for that exact shop ID.
