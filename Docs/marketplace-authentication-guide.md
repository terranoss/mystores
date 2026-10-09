# Marketplace Store Connection Guide (Shopee & Lazada)

This guide explains how to connect **Shopee** and **Lazada** stores to the platform.

---

## 1. Shopee Store Setup

### Step 1: Register the Callback URL
1. Log into your **Shopee Open Platform Console**.
2. Go to **App Management**.
3. Under **Redirect URL**, enter:
   ```text
   https://<your-domain>/api/shopee/callback
   ```
   *(Replace `<your-domain>` with the active domain of your deployment).*

### Step 2: Save Credentials in System Settings
1. In this platform, navigate to **Settings → Shopee Credentials**.
2. Enter your **Partner ID** and **Partner Key**.
3. Click **Save Changes**.

### Step 3: Link Your Store
1. Go to the **Dashboard** and click **Link Store → Shopee**.
2. Log into your Shopee seller account and click **Confirm / Authorize**.
3. The store connects automatically without requiring manual code entry.

---

## 2. Lazada Store Setup

### Step 1: Register the Callback URL
1. Log into your **Lazada Open Platform Console**.
2. Go to **App Overview / App Management**.
3. Under **Callback URL**, enter:
   ```text
   https://<your-domain>/api/lazada/callback
   ```
   *(Replace `<your-domain>` with the active domain of your deployment).*

### Step 2: Save Credentials in System Settings
1. In this platform, navigate to **Settings → Lazada Credentials**.
2. Enter your **App Key** and **App Secret**.
3. Click **Save Changes**.

### Step 3: Link Your Store
1. Go to the **Dashboard** and click **Link Store → Lazada**.
2. Log into your Lazada seller account and click **Authorize**.
3. The store connects automatically.

---

## 3. Frequently Asked Questions

### Does the user ever have to manually enter an authorization code?
**No.** Both platforms automatically redirect to the callback route, which exchanges the code and links the store seamlessly in the background.

### What causes a "Redirect URL Mismatch" error?
The Redirect/Callback URL configured in the developer console must match the deployed site's URL exact to the character, including `https://` and `/api/<platform>/callback`.

### How often do stores need to be reauthorized?
Background token maintenance handles daily API communication automatically. Shopee authorizations remain active for up to 365 days; when expired, clicking **Reauthorize Store** in the dashboard renews the grant without losing any orders or product mappings.
