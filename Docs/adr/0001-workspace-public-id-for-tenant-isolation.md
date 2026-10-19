# ADR 0001: Workspace public UUID for cross-tenant push isolation

Date: 2026-10-19
Status: Accepted

## Context

MyStores is self-hosted. Many independent installations each run the same Laravel code against their own database, and each has its own `workspaces` rows numbered from 1. The mobile app ships one binary and points at whichever server the user connects to.

Adding OneSignal push meant binding a device to a tenant. Three facts collided:

- `workspaces` had only `id` (integer), `name`, `owner_user_id` — no globally unique identifier.
- A single OneSignal project is shared across every install, so the tenant key must be unique outside one database.
- The mobile app never holds a workspace id. `WorkspaceAccess::primaryWorkspace($user)` resolves it per request on the server.

Using the raw integer id would have meant two unrelated self-hosted installs both registering `workspace_id = 1`, and user A's pushes reaching user B's device. That is a cross-tenant data leak, not a cosmetic bug.

## Decision

Add a nullable, unique `public_id` (UUID v4) column to `workspaces`, backfilled by migration and assigned on create via a model `creating` hook. `POST /devices` resolves the Primary Workspace server-side and returns `workspace_public_id`, which the app sets as its OneSignal external id.

## Alternatives considered

**Per-install OneSignal app id** (`ONESIGNAL_APP_ID` in each install's `.env`, fetched by the app at connect time).
Rejected: pushes configuration burden onto every self-hosted operator and makes app startup depend on a fetch from the user's server before the SDK can init.

**Prefixing the integer id with the install URL**.
Rejected: a domain change breaks the external id, and the resulting key is awkward to target from the OneSignal dashboard. Adds nothing over a UUID.

**Raw integer workspace id, accepting the collision**.
Rejected outright — this is the leak described above.

**No external id, targeting by tag only**.
Rejected: tags are re-registered on every app launch and are easy to lose; external id + aliases is the supported persistent identity.

## Consequences

- One extra column, one backfill loop in the migration, one `creating` hook on the Workspace model.
- `public_id` is now available for any future external identifier use (share links, public URLs) without a second migration.
- If automated push dispatch is added later, it targets by `workspace_public_id` and never by integer id.
- The mobile app remains ignorant of which workspace it is in. `POST /devices` is the only place that answers the question, and it answers for the authenticated user only.

## Reversibility

Low cost to keep, moderate to remove. Removing it requires a second migration and a replacement identifier — but nothing in the codebase is coupled to the value beyond the OneSignal external id, so it can be left dormant indefinitely.
