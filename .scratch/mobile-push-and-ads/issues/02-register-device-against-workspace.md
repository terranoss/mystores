# 02: Register a device against a workspace

**What to build:** An authenticated API client can bind a device to the workspace it belongs to and be told back which workspace that is, identified by a value that stays unique even when many independent MyStores installations are running the same code. Unbinding works, and no user can unbind somebody else's device. This is the backend half of push, and it stands on its own — it needs no mobile app to be exercised or verified.

**Blocked by:** None (can start immediately)

**Status:** resolved

> A draft implementation already exists uncommitted in the working tree. Treat it as a starting point to be reviewed against these criteria, not as satisfying them. The workspace identifier assignment is the fragile part: it must tolerate the column not existing yet, because a historical migration creates workspaces through that same model before the column is added.

- [x] Registering requires authentication.
- [x] A device identifier is required and length-bounded; absent or oversized input is rejected as a validation error rather than stored.
- [x] Registering returns the caller's workspace public identifier.
- [x] The device is stored against the caller's **Primary Workspace**, resolved server-side. The client never names a workspace.
- [x] A user belonging to several workspaces is bound to their own, not to one they were merely invited to.
- [x] Registering the same device identifier twice binds it to the latest caller's workspace and never creates a second record.
- [x] Every workspace has a unique public identifier, including workspaces created before this change existed.
- [x] A fresh install migrates cleanly — no migration failure caused by workspace creation.
- [x] Unregistering requires authentication.
- [x] A user cannot unregister another user's device; the attempt succeeds without removing the other device.
- [x] Deleting a workspace removes its device records.
- [x] Both endpoints carry the named write throttle used by the rest of the API.
- [x] Route declarations do not shadow each other.
