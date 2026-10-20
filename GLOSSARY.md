# MyStores

Multi-marketplace store management: a self-hosted Laravel API, a React admin SPA, and a Flutter companion app. The core organizing concept is the **Workspace**, and almost every write is scoped to it.

## Language

### Messaging

**Notification**:
A row in the `notifications` table scoped to a single user, rendered in the in-app inbox and counted as an unread badge. Delivery is pull-based — the app fetches it.
_Avoid_: push, message, alert

**Push Notification**:
A message delivered to a device by OneSignal — transport for a Notification, not a Notification itself. Carries the same `data` payload shape (`category`, `title`, `message`, `action_type`, …) and deep-links to the route that Notification would have opened. Any Push Notification originating from a domain event is backed by exactly one Notification row; see **Broadcast** for the exception.
_Avoid_: notification (use **Notification** for the inbox row)

**Broadcast**:
A Push Notification composed by hand in the OneSignal dashboard, sent outside any domain event. Has no backing Notification row and so appears in neither the inbox nor the unread count. The only kind of push the product sends today.
_Avoid_: campaign, announcement, blast

**Device Registration**:
A row binding an OneSignal player id to the workspace and user that owns the device. Deleted on logout. It exists so a workspace can be targeted for pushes and so a stale device never receives another tenant's messages.
_Avoid_: token, subscription, endpoint

### Tenancy

**Workspace**:
The tenant boundary. Owns stores, credentials, members, and notifications. Every scoped write resolves to exactly one Workspace.
_Avoid_: team, account, org, company

**Workspace Public ID**:
The UUID that identifies a Workspace to systems outside its own database — currently the OneSignal external id. Its whole purpose is to make a Workspace's integer id unambiguous when two self-hosted installs exist.
_Avoid_: public key, guid, slug

**Primary Workspace**:
The single Workspace a user is currently acting in, resolved server-side by `WorkspaceAccess::primaryWorkspace()`. The mobile app never names a workspace; the API resolves it for every request.
_Avoid_: active workspace, current workspace (client-side)

### Orders

**Order Status**:
The marketplace's own spelling of an order's lifecycle state — `READY_TO_SHIP`/`PROCESSED` for Shopee, `ready_to_ship`/`packed` for Lazada. Stored on the order row and never interpreted directly.
_Avoid_: order state, status code, raw status

**Canonical Status**:
The platform-independent lifecycle state derived from an **Order Status**: `unpaid`, `pending`, `packed`, `ready_to_ship`, `shipped`, `delivered`, `completed`, `cancelling`, `cancelled`, `returned`, `failed`. Owned by `OrderStatus` and emitted on order payloads as `canonical_status`; a raw status with no mapping yields `null` rather than a guessed state.
_Avoid_: normalized status, status category

### Advertising

**Interstitial**:
A full-screen AdMob unit shown on a deliberate user action, throttled to at most one per five minutes. Never shown on cold start.
_Avoid_: splash ad, popup

**Banner**:
A fixed-size AdMob unit pinned above the Dashboard's bottom navigation. Dashboard only.
_Avoid_: footer ad, sticky ad
