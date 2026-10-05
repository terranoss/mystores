# Plan: Workspace Credentials Management (Owner-Only Settings)

## Goal
Allow the **workspace owner** to configure Shopee/Lazada partner credentials in the dashboard (Settings page), replacing `.env` config. Team members cannot view/manage credentials.

---

## Architecture Decisions (Confirmed)

| Decision | Choice | Rationale |
|----------|--------|-----------|
| **Scope** | Per Workspace | Matches existing Workspace model; owner sets once, all members use |
| **Apps** | Single per Platform | One Shopee Partner ID/Key + One Lazada App Key/Secret per workspace |
| **UI** | Settings Page (Owner Only) | New sidebar item, visible only to `workspace_role === 'owner'` |
| **Fallback** | No `.env` fallback | DB only; credentials must be configured in dashboard |

---

## Data Model

### New Migration: `workspace_credentials` table
```php
// migration: create_workspace_credentials_table
Schema::create('workspace_credentials', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
    $table->string('platform'); // 'shopee' | 'lazada'
    
    // Shopee fields
    $table->string('partner_id')->nullable();
    $table->text('partner_key')->nullable(); // encrypted
    $table->text('push_partner_key')->nullable(); // encrypted
    
    // Lazada fields
    $table->string('app_key')->nullable();
    $table->text('app_secret')->nullable(); // encrypted
    
    // Common
    $table->string('redirect_url')->nullable();
    $table->boolean('is_active')->default(true);
    
    $table->unique(['workspace_id', 'platform']);
    $table->timestamps();
});
```

### Model: `WorkspaceCredential`
```php
// app/Models/WorkspaceCredential.php
protected $fillable = ['workspace_id', 'platform', 'partner_id', 'partner_key', 'push_partner_key', 'app_key', 'app_secret', 'redirect_url', 'is_active'];

protected $casts = [
    'partner_key' => 'encrypted',
    'push_partner_key' => 'encrypted',
    'app_secret' => 'encrypted',
];
```

---

## Backend Changes

### 1. Migration & Model
- Create migration & model as above
- Add relationship to `Workspace` model: `hasMany(WorkspaceCredential::class)`

### 2. Config Resolution Service
```php
// app/Services/WorkspaceConfig.php
class WorkspaceConfig {
    public function getShopeeConfig(int $workspaceId): array { ... }
    public function getLazadaConfig(int $workspaceId): array { ... }
    // Returns array with keys matching config/shopee.php, config/lazada.php
}
```

### 3. Update ShopeeService & LazadaService
- Inject `WorkspaceConfig` 
- Replace `config('shopee.*')` calls with `$this->workspaceConfig->getShopeeConfig($workspaceId)`
- Services need workspace context — pass `$store->workspace_id` from controllers

### 4. API Controller: `WorkspaceCredentialController`
```
GET    /api/workspace/credentials        # List (owner only)
PUT    /api/workspace/credentials        # Upsert (owner only) 
DELETE /api/workspace/credentials/{platform} # Delete (owner only)
```
- Uses `WorkspaceAccess::isOwnerOfWorkspace()` authorization
- Validates required fields per platform
- Encrypts secrets before storage

### 5. Update Existing Controllers
- `ShopeeAuthController::authUrl()` → pass workspace_id to service
- `ShopeeAuthController::stores()` → pass workspace_id
- `LazadaAuthController` → same
- All Shopee/Lazada API calls must resolve credentials from workspace

### 6. Remove `.env` Dependencies
- Delete `SHOPEE_PARTNER_ID`, `SHOPEE_PARTNER_KEY`, `LAZADA_APP_KEY`, `LAZADA_APP_SECRET` from `.env.example`
- Update `config/shopee.php`, `config/lazada.php` to return empty defaults (workspace config takes precedence)

---

## Frontend Changes

### 1. API Client (`frontend/src/api/client.ts`)
```typescript
export interface WorkspaceCredential {
  platform: 'shopee' | 'lazada';
  partner_id?: string;
  partner_key?: string; // masked in response
  push_partner_key?: string;
  app_key?: string;
  app_secret?: string;
  redirect_url?: string;
  is_active: boolean;
}

export async function getWorkspaceCredentials(): Promise<WorkspaceCredential[]>
export async function updateWorkspaceCredential(platform: 'shopee'|'lazada', data: Partial<WorkspaceCredential>): Promise<WorkspaceCredential>
export async function deleteWorkspaceCredential(platform: 'shopee'|'lazada'): Promise<void>
```

### 2. Settings Page (`frontend/src/pages/Settings.tsx`)
- **Owner only**: Check `user.workspace_role === 'owner'`
- Two sections: **Shopee Credentials**, **Lazada Credentials**
- Form fields per platform:
  - **Shopee**: Partner ID, Partner Key, Push Partner Key (optional), Redirect URL
  - **Lazada**: App Key, App Secret, Redirect URL
- Show/hide secret fields (password inputs with toggle)
- Save button → calls `updateWorkspaceCredential`
- Status indicator: "Configured" / "Not configured"
- Delete button (owner confirmation)

### 3. Layout Sidebar (`frontend/src/components/Layout.tsx`)
- Add `Settings` to `NAV` array with conditional rendering:
  ```tsx
  {user?.workspace_role === 'owner' && { label: 'Settings', path: '/settings', icon: '⚙' }}
  ```

### 4. AuthContext / User Type
- Ensure `user.workspace_role` is available (already in `AuthContext` from `/api/me`)

---

## Integration Points

### ShopeeService Changes
```php
// Before:
$partnerId = config('shopee.partner_id');

// After:
$partnerId = $this->workspaceConfig->getShopeeConfig($workspaceId)['partner_id'] ?? config('shopee.partner_id');
```

All methods that need credentials:
- `authUrl()` → needs partner_id, partner_key, redirect_url
- `callback()` → needs partner_id, partner_key, redirect_url
- `reauthorize()` → same
- `forStore()` → needs workspace_id from store

### Controller Pattern
```php
public function authUrl(Request $request): JsonResponse {
    $user = $request->user();
    $workspace = $this->workspaceAccess->ensurePersonalWorkspace($user);
    $config = $this->workspaceConfig->getShopeeConfig($workspace->id);
    
    if (!$config['partner_id'] || !$config['partner_key']) {
        return response()->json(['message' => 'Shopee credentials not configured. Go to Settings.'], 400);
    }
    // ... use $config values
}
```

---

## Migration Path

1. **Deploy migration** → creates `workspace_credentials` table
2. **Owner logs in** → sees "Settings" in sidebar
3. **Owner enters credentials** → saved encrypted in DB
4. **All API calls** now use DB credentials
5. **Remove `.env` credentials** after verification

---

## Validation Checklist

- [ ] Owner can access `/settings` page
- [ ] Non-owner cannot see Settings in sidebar
- [ ] Owner can save Shopee credentials → encrypted in DB
- [ ] Owner can save Lazada credentials → encrypted in DB
- [ ] "Link Shopee Store" works with DB credentials
- [ ] "Link Lazada Store" works with DB credentials
- [ ] Team member can use linked stores (orders, stock, etc.)
- [ ] Team member CANNOT see Settings page
- [ ] Deleting credentials disables linking (shows error)
- [ ] Existing linked stores still work (tokens stored separately)
- [ ] No `.env` credentials used after migration

---

## Files to Create/Modify

### Backend
- `database/migrations/xxxx_create_workspace_credentials_table.php` (new)
- `app/Models/WorkspaceCredential.php` (new)
- `app/Services/WorkspaceConfig.php` (new)
- `app/Http/Controllers/Api/WorkspaceCredentialController.php` (new)
- `app/Http/Controllers/Api/ShopeeAuthController.php` (modify)
- `app/Http/Controllers/Api/LazadaAuthController.php` (modify)
- `app/Services/ShopeeService.php` (modify)
- `app/Services/LazadaService.php` (modify)
- `app/Models/Workspace.php` (add relationship)
- `routes/api.php` (add credentials routes)

### Frontend
- `frontend/src/pages/Settings.tsx` (new)
- `frontend/src/components/Layout.tsx` (add Settings to NAV)
- `frontend/src/api/client.ts` (add credential API functions)
- `frontend/src/context/AuthContext.tsx` (ensure workspace_role available)

---

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Breaking existing stores during migration | Tokens stored in `shopee_tokens` table (unchanged); only *new* OAuth flows use DB credentials |
| Encryption key rotation | Use Laravel's `encrypt()` — tied to `APP_KEY` (unchanged) |
| Owner loses access | Owner always has `isOwnerOfWorkspace` = true; cannot be removed |
| Partial credentials | Validate required fields per platform; return 400 with clear message |

---

## Out of Scope
- Multiple credential sets per platform
- Per-user credentials
- Credential rotation UI (manual re-entry)
- Audit log for credential changes (can add later)