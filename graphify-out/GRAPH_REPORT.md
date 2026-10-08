# Graph Report - mystores  (2026-10-08)

## Corpus Check
- 298 files · ~145,819 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 46 file(s) not represented in the graph (top: (none) 22, .xml 12, .example 5)

## Summary
- 3286 nodes · 6841 edges · 178 communities (105 shown, 73 thin omitted)
- Extraction: 98% EXTRACTED · 2% INFERRED · 0% AMBIGUOUS · INFERRED: 115 edges (avg confidence: 0.92)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `9ad856a0`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- UnifiedOrdersPanel.tsx
- Order
- dashboard.dart
- client.ts
- User
- ShopeeToken
- Illuminate\Database\Eloquent\Relations\BelongsTo
- LazadaService
- pick_list_screen.dart
- api_endpoints.dart
- order.dart
- team_screen.dart
- DashboardController.php
- LogisticsService
- Illuminate\Http\Request
- ProductService.php
- workspace.dart
- package:flutter/material.dart
- TestCase
- dashboard_screen.dart
- react
- PickListCommentTest
- Illuminate\Bus\Queueable
- Stock.tsx
- Promotions.tsx
- Illuminate\Foundation\Http\FormRequest
- StoreHealthService
- product.dart
- KeepAlivePages.tsx
- stock_screen.dart
- Dashboard.tsx
- LazadaPromotionService
- Product
- dashboard_provider.dart
- orders_screen.dart
- plant_id_screen.dart
- plantnet_service.dart
- WorkspaceAccess
- ShopeeService
- ShopeeTokenRefreshTest.php
- app.dart
- order_provider.dart
- PickListCompletion
- product_provider.dart
- store.dart
- app_theme.dart
- ShopeePromotionService
- login_screen.dart
- Illuminate\Database\Schema\Blueprint
- UpdateShopeeStockJob
- WorkspaceConfig
- PickListPhotoCompressor
- ShopeeTokenRefreshTest
- frontend/package.json
- auth_provider.dart
- EcomPHP\Shopee\Client
- ShopeeStore
- compilerOptions
- notification.dart
- Illuminate\Console\Command
- ShopeeResponseHelper
- compilerOptions
- deploy.py
- PickListComment
- Workspace
- .ensureFreshToken
- authProvider
- ProductController
- PromotionController.php
- SyncShopeeOrderJob
- AppServiceProvider.php
- LazadaOrderService
- devDependencies
- auth_storage.dart
- notification_provider.dart
- SecurityHeaders.php
- LazadaProductUpdateTest.php
- 2026_10_08_000001_add_platform_to_shopee_oauth_states_table.php
- 2026_08_11_000001_add_category_brand_to_pos_inventories.php
- plant_identification_result.dart
- LazadaOrderService.php
- backend/package.json
- Plan: Workspace Credentials Management (Owner-Only Settings)
- _
- 90-Day Buyer Data Retention Policy Implementation Plan
- api_log.dart
- MyStoresWidgetProvider.kt
- StoreNotActiveException
- workspace_activity.dart
- composer.json
- scripts
- home_screen_widget.dart
- ../../shared/theme/app_theme.dart
- PurgeOldBuyerData.php
- RefreshShopeeTokens.php
- require
- require-dev
- BulkShippingLabelTest
- LazadaBulkAwbTest
- app_shell_scope.dart
- config
- Illuminate\Support\Str
- LazadaAuthController
- devDependencies
- dependencies
- scripts
- MyStores — Mobile App
- API Overview
- 2026_08_23_000001_add_stock_note_to_pos_inventories.php
- ShopeeOrderQuantityTest
- Features
- vite.config.js
- api.php
- psr-4
- 0001_01_01_000001_create_cache_table.php
- 2026_06_22_000008_add_indexes_to_products_and_variants.php
- 2026_07_26_000001_add_orders_synced_at_to_shopee_stores.php
- 2026_07_27_000001_add_ordered_at_to_orders_table.php
- 2026_07_28_000003_add_platform_to_shopee_stores.php
- 2026_07_28_200000_add_needs_push_to_products_table.php
- 2026_08_22_000001_add_photo_path_to_pos_inventories.php
- 2026_08_22_000002_add_stock_update_tracking_to_pos_inventories.php
- 2026_08_22_000003_add_sku_to_order_items_table.php
- 2026_09_17_000001_add_vacation_mode_to_shopee_stores_table.php
- DatabaseSeeder.php
- scripts
- 2026_06_21_161355_create_personal_access_tokens_table.php
- Illuminate\Support\Facades\Schema
- 2026_06_21_163338_create_shopee_tokens_table.php
- 2026_06_22_000001_create_products_table.php
- 2026_06_22_000002_create_product_variants_table.php
- 2026_06_22_000003_create_orders_table.php
- 2026_06_22_000004_create_order_items_table.php
- 2026_06_22_000005_create_shopee_oauth_states_table.php
- Illuminate\Database\Migrations\Migration
- 2026_07_30_000001_create_notifications_table.php
- WorkspaceCredential
- MyStores
- .log
- 2026_10_05_030346_create_workspace_credentials_table.php
- ExampleTest
- autoload-dev
- extra
- FlutterActivity
- tsconfig.json
- WorkspaceNotificationService
- Plan: Remove Sandbox Mode - Test on Production Server
- Getting Started (Local Development)
- WorkspaceInvite
- WorkspaceNotificationService.php
- 2026_06_22_000006_add_skus_to_products_and_variants.php
- Server Setup Guide — mystores.melur.com
- 🛡️ Security
- Configuration Reference
- MyStores — Backend
- Tech Stack
- Deployment
- 2026_06_22_000007_create_inventory_logs_table.php
- 2026_08_10_000001_create_pos_inventories_table.php
- AGENTS.md

## God Nodes (most connected - your core abstractions)
1. `User` - 297 edges
2. `ShopeeStore` - 238 edges
3. `Order` - 107 edges
4. `TestCase` - 72 edges
5. `LazadaService` - 71 edges
6. `WorkspaceAccess` - 66 edges
7. `ShopeeService` - 61 edges
8. `Product` - 59 edges
9. `ShopeeToken` - 53 edges
10. `ProductService` - 52 edges

## Surprising Connections (you probably didn't know these)
- `5. Update Existing Controllers` --references--> `LazadaAuthController`  [INFERRED]
  .kilo/plans/1791155046087-workspace-credentials.md → backend/app/Http/Controllers/Api/LazadaAuthController.php
- `Core Features` --references--> `UpdateShopeeStockJob`  [INFERRED]
  README.md → backend/app/Jobs/UpdateShopeeStockJob.php
- `🔄 Cross-Store Sync` --references--> `UpdateShopeeStockJob`  [INFERRED]
  README.md → backend/app/Jobs/UpdateShopeeStockJob.php
- `Running Queue Workers` --references--> `UpdateShopeeStockJob`  [INFERRED]
  README.md → backend/app/Jobs/UpdateShopeeStockJob.php
- `[x] Task 1.5: Add platform filter to `shopee:refresh-tokens`` --references--> `ShopeeToken`  [INFERRED]
  Docs/store-linking-audit.md → backend/app/Models/ShopeeToken.php

## Import Cycles
- None detected.

## Communities (178 total, 73 thin omitted)

### Community 0 - "UnifiedOrdersPanel.tsx"
Cohesion: 0.06
Nodes (90): downloadLazadaAwb(), downloadLazadaAwbsBulk(), downloadShippingLabel(), downloadShippingLabelsBulk(), getOrder(), getOrders(), Order, packLazadaOrder() (+82 more)

### Community 1 - "Order"
Cohesion: 0.06
Nodes (13): Order, OrderItem, {closure#1}(), {closure#5}(), {closure#8}(), {closure#9}(), OrderService, {closure#1}() (+5 more)

### Community 2 - "dashboard.dart"
Cohesion: 0.03
Nodes (68): aggregatedItems, body, cancelReasons, CancelReasonStat, cancelReasonsTotal, commentCount, comments, completion (+60 more)

### Community 3 - "client.ts"
Cohesion: 0.04
Nodes (62): api, AppNotification, axios, AxiosRequestConfig, BulkShippingLabelResult, cacheKeyFromConfig(), completePickListItem(), DashboardData (+54 more)

### Community 4 - "User"
Cohesion: 0.07
Nodes (5): User, AuthenticationTest, ChangePasswordTest, LazadaOrderManagementTest, PickListCompletionTest

### Community 5 - "ShopeeToken"
Cohesion: 0.05
Nodes (7): {closure#1}(), {closure#2}(), ShopeeOAuthState, ShopeeToken, LazadaAuthControllerTest, SecurityHardeningTest, ShopeeOAuthTest

### Community 6 - "Illuminate\Database\Eloquent\Relations\BelongsTo"
Cohesion: 0.09
Nodes (4): InventoryLog, PickListCommentRead, {closure#1}(), WorkspaceActivityLog

### Community 7 - "LazadaService"
Cohesion: 0.07
Nodes (5): LazadaService, getOrderDetail(), LazadaSellerNameTest, LazadaTokenRefreshTest, Data Stored (Both Platforms Use Same `Order` Model)

### Community 8 - "pick_list_screen.dart"
Cohesion: 0.04
Nodes (50): PickList, PickListItem, dashboardProvider, build, _buildBody, _busy, _busyItemKey, checked (+42 more)

### Community 9 - "api_endpoints.dart"
Cohesion: 0.04
Nodes (52): ApiEndpoints, baseUrl, dashboard, deleteNotification, lazadaAuthUrl, lazadaDisconnect, lazadaOrderCancel, lazadaOrderPack (+44 more)

### Community 10 - "order.dart"
Cohesion: 0.04
Nodes (51): address, addressId, buyerUsername, cancelReason, city, currency, currentPage, data (+43 more)

### Community 11 - "team_screen.dart"
Cohesion: 0.04
Nodes (42): _AppShell, _SalesChart, _StatCard, _StoreCard, _TrendDayToggle, _FilterChipRow, _InfoRow, _OrderDetailSheet (+34 more)

### Community 12 - "DashboardController.php"
Cohesion: 0.07
Nodes (12): {closure#1}(), {closure#11}(), {closure#15}(), {closure#24}(), {closure#26}(), {closure#27}(), {closure#3}(), {closure#4}() (+4 more)

### Community 13 - "LogisticsService"
Cohesion: 0.06
Nodes (6): LogisticsService, PackingListPdfService, ArrangeShipmentTest, ShippingLabelTest, Current State Analysis, Data Usage Points (Read Access)

### Community 14 - "Illuminate\Http\Request"
Cohesion: 0.09
Nodes (5): LazadaOrderController, NotificationController, OrderController, PromotionController, ShopeeAuthController

### Community 15 - "ProductService.php"
Cohesion: 0.05
Nodes (16): ProductVariant, {closure#1}(), {closure#20}(), {closure#23}(), {closure#24}(), {closure#27}(), {closure#28}(), {closure#29}() (+8 more)

### Community 16 - "workspace.dart"
Cohesion: 0.07
Nodes (29): action, ActivityEntry, ActivityUser, createdAt, currentPage, data, email, expiresAt (+21 more)

### Community 17 - "package:flutter/material.dart"
Cohesion: 0.05
Nodes (25): SpeciesResult, build, _buildBadge, _getConfidenceColor, index, _launchSearch, result, ResultCard (+17 more)

### Community 18 - "TestCase"
Cohesion: 0.07
Nodes (9): WorkspaceMember, DebugTest, ExampleTest, ProductGroupedFilterTest, PurgeBuyerDataTest, WorkspaceAccessTest, WorkspaceActivityLogTest, WorkspaceNotificationTest (+1 more)

### Community 19 - "dashboard_screen.dart"
Cohesion: 0.05
Nodes (35): notificationProvider, storeProvider, bgColor, build, _buildCancelReasonsSection, _buildContent, _buildSalesTrendSection, _buildStatCards (+27 more)

### Community 20 - "react"
Cohesion: 0.08
Nodes (35): getDashboardStats(), getStores(), MarketplaceType, ShopeeStore, App(), AppShell(), ErrorBoundary, Props (+27 more)

### Community 22 - "Illuminate\Bus\Queueable"
Cohesion: 0.11
Nodes (5): OrderStatusChangedNotification, OrderUrgentNotification, PickListCommentNotification, StoreReauthNotification, TeamActivityNotification

### Community 23 - "Stock.tsx"
Cohesion: 0.10
Nodes (31): getProductsGrouped(), GroupedProductGroup, Product, pullProducts(), pushProducts(), syncProducts(), updatePriceBySku(), updateStockAndPrice() (+23 more)

### Community 24 - "Promotions.tsx"
Cohesion: 0.08
Nodes (38): activateLazadaVoucher(), addShopeeDiscountItems(), createLazadaVoucher(), createShopeeDiscount(), createShopeeVoucher(), deactivateLazadaVoucher(), getProducts(), getPromotions() (+30 more)

### Community 25 - "Illuminate\Foundation\Http\FormRequest"
Cohesion: 0.07
Nodes (8): AuthController, ChangePasswordRequest, LoginRequest, RegisterRequest, UpdatePriceRequest, UpdateStockPriceRequest, UpdateStockRequest, UserResource

### Community 26 - "StoreHealthService"
Cohesion: 0.05
Nodes (25): NotificationGeneratorService, StoreHealthService, {closure#2}(), StoreHealthNotificationTest, StoreHealthServiceTest, 1. Executive Summary & Phased Sizing Matrix, 2. Architecture & Execution Dependency Graph, 7. Rollback & Disaster Recovery Strategy (+17 more)

### Community 27 - "product.dart"
Cohesion: 0.05
Nodes (36): categoryId, copyWith, currentPage, data, fromJson, grouped, GroupedProductGroup, GroupedResponse (+28 more)

### Community 28 - "KeepAlivePages.tsx"
Cohesion: 0.11
Nodes (24): acceptWorkspaceInvite(), changePassword(), deleteWorkspaceCredentials(), getWorkspace(), getWorkspaceActivity(), getWorkspaceCredentials(), inviteWorkspaceMember(), removeWorkspaceMember() (+16 more)

### Community 29 - "stock_screen.dart"
Cohesion: 0.07
Nodes (31): productProvider, build, _buildSimpleFields, _buildVariantRow, createState, _debounce, dispose, _EditStockSheet (+23 more)

### Community 30 - "Dashboard.tsx"
Cohesion: 0.10
Nodes (35): Phase 4 Verification Result (2026-10-08), Phase 4: Web Dashboard State Consolidation & UI Hardening (P2), [x] Task 4.2: HealthBadge unhide & fallback status styles, [x] Task 4.3: Synchronize store actions with topbar `StoreContext`, CancelReasonStat, DashboardStats, getAuthUrl(), getLazadaAuthUrl() (+27 more)

### Community 32 - "Product"
Cohesion: 0.19
Nodes (3): Product, {closure#10}(), ProductService

### Community 33 - "dashboard_provider.dart"
Cohesion: 0.09
Nodes (16): completePickListItem, DashboardNotifier, deletePickListComment, fetchDashboard, fetchPendingPickList, fetchTodayPickList, markPickListCommentsRead, postPickListComment (+8 more)

### Community 34 - "orders_screen.dart"
Cohesion: 0.08
Nodes (23): orderProvider, build, _cancelReasonLabels, createState, dispose, _formatCancelReason, items, label (+15 more)

### Community 35 - "plant_id_screen.dart"
Cohesion: 0.09
Nodes (21): build, _buildIdentifyButton, _buildImageSection, _buildSourceOption, createState, _errorMessage, _identifyPlant, _isLoading (+13 more)

### Community 36 - "plantnet_service.dart"
Cohesion: 0.08
Nodes (17): apiClient, createDio, dio, endsWith, _handlingUnauthorized, _isAuthEndpoint, _apiKey, _baseUrl (+9 more)

### Community 37 - "WorkspaceAccess"
Cohesion: 0.11
Nodes (8): PickListCommentController, PickListCompletionController, {closure#1}(), {closure#4}(), Controller, WorkspaceAccess, WorkspaceActivityLogger, Workspace Isolation

### Community 38 - "ShopeeService"
Cohesion: 0.09
Nodes (3): {closure#1}(), {closure#2}(), ShopeeService

### Community 40 - "app.dart"
Cohesion: 0.04
Nodes (28): _AppDrawer, child, createState, _currentIndex, _DrawerAppVersion, _DrawerAppVersionState, _go, initState (+20 more)

### Community 41 - "order_provider.dart"
Cohesion: 0.08
Nodes (23): cancelOrder, copyWith, currentPage, error, fetchOrders, filters, getShippingParameters, hasMore (+15 more)

### Community 43 - "product_provider.dart"
Cohesion: 0.08
Nodes (23): copyWith, currentPage, error, fetchGrouped, fetchProducts, filters, grouped, groupedData (+15 more)

### Community 44 - "store.dart"
Cohesion: 0.08
Nodes (24): accessTokenExpiresAt, ConnectionStatus, connectionStatusFromString, fromJson, id, isActive, lastRefreshedAt, lastRefreshError (+16 more)

### Community 45 - "app_theme.dart"
Cohesion: 0.08
Nodes (24): accent, accentLight, AppColors, background, border, buildAppTheme, divider, error (+16 more)

### Community 47 - "login_screen.dart"
Cohesion: 0.08
Nodes (16): createState, dispose, _emailController, _formKey, LoginScreen, _LoginScreenState, _obscurePassword, _passwordController (+8 more)

### Community 48 - "Illuminate\Database\Schema\Blueprint"
Cohesion: 0.11
Nodes (18): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}() (+10 more)

### Community 49 - "UpdateShopeeStockJob"
Cohesion: 0.27
Nodes (3): SyncShopeeStoreJob, UpdateShopeePriceJob, UpdateShopeeStockJob

### Community 50 - "WorkspaceConfig"
Cohesion: 0.10
Nodes (10): WorkspaceCredentialController, WorkspaceConfig, [x] Task 3.4: Harden `WorkspaceCredentialController`, 1. Migration & Model, 2. Config Resolution Service, 3. Update ShopeeService & LazadaService, 4. API Controller: `WorkspaceCredentialController`, 5. Update Existing Controllers (+2 more)

### Community 53 - "frontend/package.json"
Cohesion: 0.11
Nodes (20): axios, tailwindcss, @tailwindcss/vite, vite, name, private, type, version (+12 more)

### Community 54 - "auth_provider.dart"
Cohesion: 0.11
Nodes (17): AppUser, AuthNotifier, AuthState, clearError, copyWith, error, errorDetail, forceLocalLogout (+9 more)

### Community 55 - "EcomPHP\Shopee\Client"
Cohesion: 0.13
Nodes (18): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}() (+10 more)

### Community 56 - "ShopeeStore"
Cohesion: 0.08
Nodes (5): SyncShopeeOrders, ShopeeStore, DashboardTest, PromotionTest, ShopeeVacationModeTest

### Community 57 - "compilerOptions"
Cohesion: 0.11
Nodes (18): compilerOptions, allowImportingTsExtensions, erasableSyntaxOnly, jsx, lib, module, moduleDetection, moduleResolution (+10 more)

### Community 58 - "notification.dart"
Cohesion: 0.11
Nodes (18): actionType, AppNotification, AppNotificationData, category, createdAt, data, fromJson, id (+10 more)

### Community 59 - "Illuminate\Console\Command"
Cohesion: 0.11
Nodes (6): BackfillOrderOrderedAt, BackfillShopeeOrderQuantities, CleanupPickListPhotos, RefreshLazadaTokens, SyncLazadaOrders, SyncShopeeProducts

### Community 60 - "ShopeeResponseHelper"
Cohesion: 0.17
Nodes (3): ShopeeResponseHelper, {closure#39}(), {closure#40}()

### Community 61 - "compilerOptions"
Cohesion: 0.11
Nodes (17): compilerOptions, allowImportingTsExtensions, erasableSyntaxOnly, lib, module, moduleDetection, moduleResolution, noEmit (+9 more)

### Community 62 - "deploy.py"
Cohesion: 0.16
Nodes (10): main(), print_step(), remote_git_sync(), require_safe_value(), run_remote_command(), Goal, Notes, Plan: Create New Repository from Current Branch (+2 more)

### Community 63 - "PickListComment"
Cohesion: 0.20
Nodes (4): {closure#12}(), {closure#13}(), {closure#1}(), PickListComment

### Community 64 - "Workspace"
Cohesion: 0.09
Nodes (6): Workspace, {closure#1}(), {closure#4}(), up(), Database Schema, Key Relationships

### Community 66 - "authProvider"
Cohesion: 0.13
Nodes (11): build, MyStoresApp, authProvider, build, _submit, didChangeAppLifecycleState, build, _showItemSheet (+3 more)

### Community 70 - "AppServiceProvider.php"
Cohesion: 0.14
Nodes (7): AppServiceProvider, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}()

### Community 72 - "devDependencies"
Cohesion: 0.13
Nodes (15): devDependencies, eslint, @eslint/js, eslint-plugin-react-hooks, eslint-plugin-react-refresh, globals, tailwindcss, @tailwindcss/vite (+7 more)

### Community 73 - "auth_storage.dart"
Cohesion: 0.13
Nodes (11): AuthStorage, clearAll, deleteToken, deleteUser, getToken, getUser, saveToken, saveUser (+3 more)

### Community 74 - "notification_provider.dart"
Cohesion: 0.14
Nodes (12): copyWith, deleteNotification, error, fetchNotifications, fetchUnreadCount, isLoading, markAllAsRead, markAsRead (+4 more)

### Community 76 - "LazadaProductUpdateTest.php"
Cohesion: 0.13
Nodes (3): {closure#4}(), LazadaProductUpdateTest, NeedsPushStockTest

### Community 79 - "plant_identification_result.dart"
Cohesion: 0.15
Nodes (11): bestMatch, commonNames, family, fromJson, genus, PlantIdentificationResult, predictedOrgans, remainingRequests (+3 more)

### Community 81 - "backend/package.json"
Cohesion: 0.17
Nodes (11): axios, tailwindcss, @tailwindcss/vite, vite, private, $schema, scripts, build (+3 more)

### Community 82 - "Plan: Workspace Credentials Management (Owner-Only Settings)"
Cohesion: 0.09
Nodes (21): 1. API Client (`frontend/src/api/client.ts`), 2. Settings Page (`frontend/src/pages/Settings.tsx`), 3. Layout Sidebar (`frontend/src/components/Layout.tsx`), 4. AuthContext / User Type, Architecture Decisions (Confirmed), Backend, Controller Pattern, Data Model (+13 more)

### Community 83 - "_"
Cohesion: 0.17
Nodes (10): _, apiErrorMessage, ApiResult, data, error, fail, isFail, isOk (+2 more)

### Community 84 - "90-Day Buyer Data Retention Policy Implementation Plan"
Cohesion: 0.11
Nodes (17): 1. Anonymize vs Hard Delete, 2. raw_response Handling, 3. Platform Scope, 4. Timing Basis, 5. Related Data Cleanup, 90-Day Buyer Data Retention Policy Implementation Plan, Design Decisions to Confirm, Goal (+9 more)

### Community 85 - "api_log.dart"
Cohesion: 0.18
Nodes (6): add, ApiLog, clear, dump, _entries, _maxEntries

### Community 87 - "StoreNotActiveException"
Cohesion: 0.11
Nodes (9): ProductAuthorizationException, StoreNotActiveException, {closure#45}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), [x] Task 3.6: Deduplicate `callWithTokenRetry` helper across services (+1 more)

### Community 88 - "workspace_activity.dart"
Cohesion: 0.12
Nodes (14): 0, action, createdAt, email, fromJson, id, meta, name (+6 more)

### Community 89 - "composer.json"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 90 - "scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 91 - "home_screen_widget.dart"
Cohesion: 0.22
Nodes (5): androidName, clear, HomeScreenWidget, qualifiedAndroidName, syncFromDashboard

### Community 92 - "../../shared/theme/app_theme.dart"
Cohesion: 0.22
Nodes (5): build, onOrganChanged, _organs, OrganSelector, selectedOrgan

### Community 93 - "PurgeOldBuyerData.php"
Cohesion: 0.28
Nodes (3): {closure#3}(), PurgeOldBuyerData, Task List for Implementation Agent

### Community 94 - "RefreshShopeeTokens.php"
Cohesion: 0.12
Nodes (9): RefreshShopeeTokens, Phase 1: Core Token Lifecycle & Concurrency Engine (P0), Phase 1 Verification Result (2026-10-08), [x] Task 1.1: Fix Carbon operand sign for store health badge, [x] Task 1.2: Scope Shopee token refresh to store workspace, [x] Task 1.3: Concurrency lock for Lazada single-use token refresh, [x] Task 1.4: Track Lazada refresh failures and extend refresh token expiry, [x] Task 1.5: Add platform filter to `shopee:refresh-tokens` (+1 more)

### Community 95 - "require"
Cohesion: 0.25
Nodes (7): require, dompdf/dompdf, ecomphp/shopee-php, laravel/framework, laravel/sanctum, laravel/tinker, php

### Community 96 - "require-dev"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 99 - "app_shell_scope.dart"
Cohesion: 0.25
Nodes (5): AppShellScope, of, openDrawer, scaffoldKey, updateShouldNotify

### Community 101 - "config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 103 - "LazadaAuthController"
Cohesion: 0.14
Nodes (9): LazadaAuthController, Phase 2: OAuth Security, Platform Isolation & State Migration (P1), Phase 2 Verification Result (2026-10-08), [x] Task 2.1: Database migration: Add `platform` column to `shopee_oauth_states`, [x] Task 2.2: OAuth state model & controller platform scoping, [x] Task 2.3: Seller_id mismatch validation guard in Lazada reauth callback, [x] Task 2.4: Cross-platform store route guards, [x] Task 2.5: Guard callback expire_in & retain Shopee refresh expiry (+1 more)

### Community 104 - "devDependencies"
Cohesion: 0.29
Nodes (7): devDependencies, axios, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite, vite

### Community 106 - "dependencies"
Cohesion: 0.29
Nodes (7): dependencies, axios, pdf-lib, react, react-dom, react-router-dom, @tailwindcss/oxide-win32-x64-msvc

### Community 107 - "scripts"
Cohesion: 0.29
Nodes (6): name, private, scripts, build, deploy, dev

### Community 108 - "MyStores — Mobile App"
Cohesion: 0.14
Nodes (10): API, Commands, MyStores — Frontend, Structure, Build flags, Features, Getting Started, Layout (+2 more)

### Community 109 - "API Overview"
Cohesion: 0.15
Nodes (13): API Overview, Authentication, Dashboard, Fulfillment, Lazada Orders, Lazada Store Linking, Notifications, Orders (+5 more)

### Community 110 - "2026_08_23_000001_add_stock_note_to_pos_inventories.php"
Cohesion: 0.22
Nodes (4): backfillFromActivityLogs(), {closure#1}(), {closure#2}(), up()

### Community 112 - "Features"
Cohesion: 0.15
Nodes (13): 🔐 Authentication, 🔄 Cross-Store Sync, 📊 Dashboard, Features, 🚚 Fulfillment & Shipping, 📱 Mobile App (Flutter), 🏪 Multi-Marketplace Store Management, 📋 Order Management (+5 more)

### Community 115 - "psr-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 129 - "scripts"
Cohesion: 0.40
Nodes (5): scripts, build, dev, lint, preview

### Community 131 - "Illuminate\Support\Facades\Schema"
Cohesion: 0.15
Nodes (3): {closure#1}(), {closure#1}(), {closure#1}()

### Community 141 - "MyStores"
Cohesion: 0.17
Nodes (12): Architecture, Core Features, Development Conventions, E2E Tests (Playwright), Key Design Decisions, License, MyStores, Overview (+4 more)

### Community 146 - "autoload-dev"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 147 - "extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 164 - "Plan: Remove Sandbox Mode - Test on Production Server"
Cohesion: 0.18
Nodes (10): 1. Update `backend/.env` with Production Values (for local reference / deploy sync), 2. Update Config Defaults, 3. Test Workflow: On Production Server, Current State, Files to Modify, Goal, Plan: Remove Sandbox Mode - Test on Production Server, Required Changes (+2 more)

### Community 165 - "Getting Started (Local Development)"
Cohesion: 0.22
Nodes (9): 1. Clone & Install Backend, 2. Configure `.env`, 3. Install Frontend & Start Dev, 4. Start Backend Dev Server, 5. Mobile App (optional), 6. Run Scheduled Tasks Locally, 7. Run Tests, Getting Started (Local Development) (+1 more)

### Community 168 - "2026_06_22_000006_add_skus_to_products_and_variants.php"
Cohesion: 0.29
Nodes (4): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}()

### Community 169 - "Server Setup Guide — mystores.melur.com"
Cohesion: 0.29
Nodes (7): 1. Create Subdomain, 2. PHP Version, 3. SSH Access, 4. Composer, 5. Database, 6. SSL, Server Setup Guide — mystores.melur.com

### Community 170 - "🛡️ Security"
Cohesion: 0.33
Nodes (6): API Security, Authentication & Authorization, HTTP Security Headers, OAuth Flow Security, Operational Security, 🛡️ Security

### Community 171 - "Configuration Reference"
Cohesion: 0.33
Nodes (6): Backend `.env`, `config/cors.php`, `config/lazada.php`, `config/shopee.php`, Configuration Reference, Mobile build flags

### Community 172 - "MyStores — Backend"
Cohesion: 0.40
Nodes (4): Commands, MyStores — Backend, Notes, Structure

### Community 173 - "Tech Stack"
Cohesion: 0.40
Nodes (5): Backend (`backend/`), Deploy (`deploy/`), Frontend (`frontend/`), Mobile (`mobile/`), Tech Stack

### Community 174 - "Deployment"
Cohesion: 0.40
Nodes (5): Deploy, Deployment, Prerequisites (one-time), Running Queue Workers, What the Deploy Script Does

## Knowledge Gaps
- **984 isolated node(s):** `$schema`, `name`, `type`, `description`, `keywords` (+979 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 1481 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **73 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `ShopeeStore` connect `ShopeeStore` to `Order`, `User`, `ShopeeToken`, `Illuminate\Database\Eloquent\Relations\BelongsTo`, `LazadaService`, `DashboardController.php`, `LogisticsService`, `Illuminate\Http\Request`, `.log`, `ProductService.php`, `TestCase`, `PickListCommentTest`, `Illuminate\Bus\Queueable`, `StoreHealthService`, `LazadaPromotionService`, `Product`, `WorkspaceNotificationService`, `WorkspaceAccess`, `ShopeeService`, `WorkspaceNotificationService.php`, `ShopeeTokenRefreshTest.php`, `PickListCompletion`, `ShopeePromotionService`, `UpdateShopeeStockJob`, `ShopeeTokenRefreshTest`, `EcomPHP\Shopee\Client`, `Illuminate\Console\Command`, `ShopeeResponseHelper`, `Workspace`, `.ensureFreshToken`, `PromotionController.php`, `SyncShopeeOrderJob`, `LazadaOrderService`, `LazadaProductUpdateTest.php`, `LazadaOrderService.php`, `BulkShippingLabelTest`, `LazadaBulkAwbTest`, `PromotionTest.php`, `LazadaAuthController`, `ShopeeOrderQuantityTest`?**
  _High betweenness centrality (0.369) - this node is a cross-community bridge._
- **What connects `$schema`, `name`, `type` to the rest of the system?**
  _984 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `UnifiedOrdersPanel.tsx` be split into smaller, more focused modules?**
  _Cohesion score 0.06065949567977429 - nodes in this community are weakly interconnected._
- **Why does `disconnectStore()` connect `StoreHealthService` to `client.ts`, `Dashboard.tsx`?**
  _High betweenness centrality (0.199) - this node is a cross-community bridge._
- **Should `Order` be split into smaller, more focused modules?**
  _Cohesion score 0.05913461538461538 - nodes in this community are weakly interconnected._
- **Should `dashboard.dart` be split into smaller, more focused modules?**
  _Cohesion score 0.0273972602739726 - nodes in this community are weakly interconnected._
- **Should `client.ts` be split into smaller, more focused modules?**
  _Cohesion score 0.042624042624042624 - nodes in this community are weakly interconnected._