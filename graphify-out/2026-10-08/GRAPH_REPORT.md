# Graph Report - mystores  (2026-10-08)

## Corpus Check
- 308 files · ~145,819 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 45 file(s) not represented in the graph (top: (none) 21, .xml 12, .example 5)

## Summary
- 3076 nodes · 6556 edges · 163 communities (90 shown, 73 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 33 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Components Pages Lazada
- Orderservice Feature Dashboardtest
- Dashboard Dashboardstats Picklistcompletioninfo
- Api Client Getcache
- Feature Lazadaordermanagementtest Promotiontest
- Feature Shopeeoauthtest Lazadaauthcontrollertest
- Workspacecredentialtest Workspace Workspaceinvite
- Lazadaservice Lazadatokenrefreshtest Feature
- Pick List Screen
- Api Endpoints Apiendpoints
- Order Orderstore Address
- Team Screen Shared
- Dashboardcontroller Api Carbon
- Shippinglabeltest Feature Arrangeshipmenttest
- Api Promotioncontroller Ordercontroller
- Productservice Productvariant Orderitem
- Workspace Activity Action
- Widgets Shared Status
- Feature Needspushstocktest Productgroupedfiltertest
- Dashboard Screen Providers
- Components Context Api
- Workspaceaccess Feature Picklistcommenttest
- Notifications Workspacenotificationservice Ordernewnotification
- Pages Stock Api
- Promotions Promotionproductpicker Pages
- Requests Authcontroller Api
- Storehealthservicetest Feature Storehealthnotificationtest
- Product Store Bool
- Components Errorboundary Api
- Stock Screen Product
- Dashboard Pages Api
- Lazadapromotionservice Feature Promotiontest
- Productservice Storenotactiveexception Lazadaservice
- Providers Provider Dashboard
- Orders Screen Order
- Plant Screens Screen
- Api Plant Plantnet
- Api Lazadaauthcontroller Workspacecontroller
- Shopeeservice Productservice Construct
- Feature Shopeetokenrefreshtest Shopeeservice
- Screen Package Dashboard
- Order Providers Provider
- Picklistphotocleanupservice Picklistcompletioncontroller Api
- Product Providers Provider
- Store Marketplacetype Accesstokenexpiresat
- Theme Shared Accent
- Shopeepromotionservice Accessibleshopeestores Adddiscountitems
- Screen Notifications Auth
- Database Migrations 2026
- Jobs Syncshopeestorejob Illuminate
- Shopeewebhookcontroller Workspaceconfig Api
- Picklistphotocompressor Api Shopeeauthcontroller
- Shopeetokenrefreshtest Feature Token
- Ref Package Eslint
- Providers Auth Provider
- Shopeepromotionservice Throwable Ecomphp
- Shared Widgets Authenticated
- Tsconfig Compileroptions Allowimportingtsextensions
- Notification Actiontype Appnotification
- Console Commands Synclazadaorders
- Shopeeresponsehelper Productservice Helpers
- Tsconfig Node Compileroptions
- Deploy Remote Datetime
- Picklistcommentcontroller Api Picklistcomment
- Shopeestore Workspace User
- Lazadaorderservice Cancelorder Downloadawbpdf
- Team Screen Route
- Productcontroller Api Routes
- Api Promotioncontroller Illuminate
- Shopeeorderpushtest Feature Order
- Appserviceprovider Providers Illuminate
- Lazadaorderservice Deriveorderstatus Extractpackagesfromitems
- Package Devdependencies Eslint
- Auth Storage Flutter
- Notification Providers Provider
- Workspacecredentialcontroller Api Securityheaders
- Lazadaproductupdatetest Feature Lazada
- Database Migrations 2026
- Database Migrations 2026
- Plant Identification Result
- Lazadaorderservice
- Package Ref Json
- Securityhardeningtest Feature Oauth
- Api Error Bool
- Picklistcompletiontest Feature Photo
- Api Log Static
- Mystoreswidgetprovider Android Main
- Bootstrap Illuminate Foundation
- Composer Description Keywords
- Composer Scripts Post
- Home Widget Widgets
- Plant Widgets Organ
- Purgeoldbuyerdata Console Commands
- Refreshshopeetokens Console Commands
- Composer Require Laravel
- Composer Require Dev
- Bulkshippinglabeltest Feature Bulk
- Lazadabulkawbtest Feature Awb
- Shared Widgets Shell
- Productauthorizationexception Exceptions Construct
- Composer Config Allow
- Userfactory Database Factories
- Database Migrations 2026
- Package Devdependencies Vite
- Package Dependencies React
- Package Scripts Name
- Refreshlazadatokens Console Commands
- Database Migrations 0001
- Database Migrations 2026
- Shopeeorderquantitytest Feature Quantity
- Workspacenotificationtest Feature New
- Vite Ref Config
- Shared Widgets Authenticated
- Composer Psr Autoload
- Database Migrations 0001
- Database Migrations 2026
- Database Migrations 2026
- Database Migrations 2026
- Database Migrations 2026
- Database Migrations 2026
- Database Migrations 2026
- Database Migrations 2026
- Database Migrations 2026
- Database Migrations 2026
- Database Databaseseeder Seeders
- Package Scripts Build
- Database Migrations 2026
- Database Migrations 2026
- Database Migrations 2026
- Database Migrations 2026
- Database Migrations 2026
- Database Migrations 2026
- Database Migrations 2026
- Database Migrations 2026
- Database Migrations 2026
- Database Migrations 2026
- Database Migrations 2026
- Database Migrations 2026
- Database Migrations 2026
- Database Migrations 2026
- Exampletest Unit True
- Composer Autoload Dev
- Composer Extra Laravel
- Mainactivity Android Main
- Tsconfig Files References

## God Nodes (most connected - your core abstractions)
1. `User` - 297 edges
2. `ShopeeStore` - 238 edges
3. `Order` - 107 edges
4. `TestCase` - 72 edges
5. `LazadaService` - 69 edges
6. `WorkspaceAccess` - 65 edges
7. `Product` - 59 edges
8. `ShopeeService` - 57 edges
9. `ShopeeToken` - 52 edges
10. `ProductService` - 50 edges

## Surprising Connections (you probably didn't know these)
- `{closure#3}()` --references--> `StoreNotActiveException`  [EXTRACTED]
  backend/bootstrap/app.php → backend/app/Exceptions/StoreNotActiveException.php
- `{closure#15}()` --references--> `Order`  [EXTRACTED]
  backend/app/Http/Controllers/Api/DashboardController.php → backend/app/Models/Order.php
- `{closure#1}()` --calls--> `LazadaPromotionService`  [EXTRACTED]
  backend/app/Http/Controllers/Api/PromotionController.php → backend/app/Services/LazadaPromotionService.php
- `{closure#1}()` --calls--> `ShopeeOAuthState`  [EXTRACTED]
  backend/app/Http/Controllers/Api/ShopeeAuthController.php → backend/app/Models/ShopeeOAuthState.php
- `{closure#4}()` --references--> `ShopeeStore`  [EXTRACTED]
  backend/app/Http/Controllers/Api/ShopeeAuthController.php → backend/app/Models/ShopeeStore.php

## Import Cycles
- None detected.

## Communities (163 total, 73 thin omitted)

### Community 0 - "Components Pages Lazada"
Cohesion: 0.06
Nodes (90): BulkShippingLabelResult, downloadLazadaAwb(), downloadLazadaAwbsBulk(), downloadShippingLabel(), downloadShippingLabelsBulk(), getOrder(), getOrders(), Order (+82 more)

### Community 1 - "Orderservice Feature Dashboardtest"
Cohesion: 0.05
Nodes (14): Order, OrderItem, {closure#1}(), {closure#5}(), {closure#8}(), {closure#9}(), OrderService, {closure#1}() (+6 more)

### Community 2 - "Dashboard Dashboardstats Picklistcompletioninfo"
Cohesion: 0.03
Nodes (68): aggregatedItems, body, cancelReasons, CancelReasonStat, cancelReasonsTotal, commentCount, comments, completion (+60 more)

### Community 3 - "Api Client Getcache"
Cohesion: 0.05
Nodes (54): acceptWorkspaceInvite(), AppNotification, axios, AxiosRequestConfig, cacheKeyFromConfig(), DashboardData, DashboardWorkspace, deleteNotification() (+46 more)

### Community 4 - "Feature Lazadaordermanagementtest Promotiontest"
Cohesion: 0.06
Nodes (6): User, AuthenticationTest, ChangePasswordTest, LazadaOrderManagementTest, PromotionTest, WorkspaceActivityLogTest

### Community 5 - "Feature Shopeeoauthtest Lazadaauthcontrollertest"
Cohesion: 0.06
Nodes (8): {closure#1}(), {closure#2}(), ShopeeOAuthState, ShopeeStore, ShopeeToken, LazadaAuthControllerTest, ShopeeOAuthTest, ShopeeVacationModeTest

### Community 6 - "Workspacecredentialtest Workspace Workspaceinvite"
Cohesion: 0.06
Nodes (6): InventoryLog, PickListCommentRead, WorkspaceActivityLog, WorkspaceCredential, WorkspaceInvite, WorkspaceCredentialTest

### Community 8 - "Pick List Screen"
Cohesion: 0.04
Nodes (51): PickList, PickListItem, dashboardProvider, build, _buildBody, _busy, _busyItemKey, checked (+43 more)

### Community 9 - "Api Endpoints Apiendpoints"
Cohesion: 0.04
Nodes (52): ApiEndpoints, baseUrl, dashboard, deleteNotification, lazadaAuthUrl, lazadaDisconnect, lazadaOrderCancel, lazadaOrderPack (+44 more)

### Community 10 - "Order Orderstore Address"
Cohesion: 0.04
Nodes (51): address, addressId, buyerUsername, cancelReason, city, currency, currentPage, data (+43 more)

### Community 11 - "Team Screen Shared"
Cohesion: 0.05
Nodes (41): _SalesChart, _StatCard, _StoreCard, _TrendDayToggle, _FilterChipRow, _InfoRow, _OrderDetailSheet, _OrderTile (+33 more)

### Community 12 - "Dashboardcontroller Api Carbon"
Cohesion: 0.07
Nodes (12): {closure#1}(), {closure#11}(), {closure#15}(), {closure#24}(), {closure#26}(), {closure#27}(), {closure#3}(), {closure#4}() (+4 more)

### Community 13 - "Shippinglabeltest Feature Arrangeshipmenttest"
Cohesion: 0.07
Nodes (4): LogisticsService, PackingListPdfService, ArrangeShipmentTest, ShippingLabelTest

### Community 14 - "Api Promotioncontroller Ordercontroller"
Cohesion: 0.11
Nodes (5): LazadaOrderController, NotificationController, OrderController, PromotionController, ShopeeAuthController

### Community 15 - "Productservice Productvariant Orderitem"
Cohesion: 0.05
Nodes (16): ProductVariant, {closure#1}(), {closure#20}(), {closure#23}(), {closure#24}(), {closure#27}(), {closure#28}(), {closure#29}() (+8 more)

### Community 16 - "Workspace Activity Action"
Cohesion: 0.04
Nodes (43): action, 0, action, createdAt, email, fromJson, id, meta (+35 more)

### Community 17 - "Widgets Shared Status"
Cohesion: 0.05
Nodes (25): SpeciesResult, build, _buildBadge, _getConfidenceColor, index, _launchSearch, result, ResultCard (+17 more)

### Community 18 - "Feature Needspushstocktest Productgroupedfiltertest"
Cohesion: 0.08
Nodes (7): DebugTest, ExampleTest, LazadaSellerNameTest, NeedsPushStockTest, ProductGroupedFilterTest, PurgeBuyerDataTest, TestCase

### Community 19 - "Dashboard Screen Providers"
Cohesion: 0.05
Nodes (36): notificationProvider, storeProvider, bgColor, build, _buildCancelReasonsSection, _buildContent, _buildSalesTrendSection, _buildStatCards (+28 more)

### Community 20 - "Components Context Api"
Cohesion: 0.11
Nodes (31): api, getDashboardStats(), getStores(), MarketplaceType, ShopeeStore, App(), AppShell(), getNavItems() (+23 more)

### Community 21 - "Workspaceaccess Feature Picklistcommenttest"
Cohesion: 0.09
Nodes (8): Workspace, WorkspaceMember, {closure#1}(), {closure#4}(), WorkspaceAccess, up(), PickListCommentTest, WorkspaceAccessTest

### Community 22 - "Notifications Workspacenotificationservice Ordernewnotification"
Cohesion: 0.06
Nodes (7): OrderNewNotification, OrderStatusChangedNotification, OrderUrgentNotification, PickListCommentNotification, StoreReauthNotification, TeamActivityNotification, WorkspaceNotificationService

### Community 23 - "Pages Stock Api"
Cohesion: 0.08
Nodes (37): changePassword(), deleteWorkspaceCredentials(), getProductsGrouped(), getWorkspaceCredentials(), GroupedProductGroup, Product, pullProducts(), pushProducts() (+29 more)

### Community 24 - "Promotions Promotionproductpicker Pages"
Cohesion: 0.08
Nodes (38): activateLazadaVoucher(), addShopeeDiscountItems(), createLazadaVoucher(), createShopeeDiscount(), createShopeeVoucher(), deactivateLazadaVoucher(), getProducts(), getPromotions() (+30 more)

### Community 25 - "Requests Authcontroller Api"
Cohesion: 0.07
Nodes (8): AuthController, ChangePasswordRequest, LoginRequest, RegisterRequest, UpdatePriceRequest, UpdateStockPriceRequest, UpdateStockRequest, UserResource

### Community 26 - "Storehealthservicetest Feature Storehealthnotificationtest"
Cohesion: 0.10
Nodes (5): NotificationGeneratorService, StoreHealthService, {closure#2}(), StoreHealthNotificationTest, StoreHealthServiceTest

### Community 27 - "Product Store Bool"
Cohesion: 0.05
Nodes (36): categoryId, copyWith, currentPage, data, fromJson, grouped, GroupedProductGroup, GroupedResponse (+28 more)

### Community 28 - "Components Errorboundary Api"
Cohesion: 0.09
Nodes (28): completePickListItem(), deletePickListComment(), fetchPickListPhotoObjectUrl(), getPendingPickList(), getTodayPickList(), markPickListCommentsRead(), PickListKind, postPickListComment() (+20 more)

### Community 29 - "Stock Screen Product"
Cohesion: 0.07
Nodes (31): productProvider, _buildSimpleFields, _buildVariantRow, createState, _debounce, dispose, _EditStockSheet, _EditStockSheetState (+23 more)

### Community 30 - "Dashboard Pages Api"
Cohesion: 0.11
Nodes (34): CancelReasonStat, DashboardStats, disconnectStore(), getAuthUrl(), getLazadaAuthUrl(), reauthorizeStore(), SalesGrowth, SalesTrendDay (+26 more)

### Community 32 - "Productservice Storenotactiveexception Lazadaservice"
Cohesion: 0.16
Nodes (4): StoreNotActiveException, Product, {closure#10}(), ProductService

### Community 33 - "Providers Provider Dashboard"
Cohesion: 0.08
Nodes (17): completePickListItem, DashboardNotifier, deletePickListComment, fetchDashboard, fetchPendingPickList, fetchTodayPickList, markPickListCommentsRead, postPickListComment (+9 more)

### Community 34 - "Orders Screen Order"
Cohesion: 0.08
Nodes (25): orderProvider, build, _cancelReasonLabels, createState, dispose, _formatCancelReason, items, label (+17 more)

### Community 35 - "Plant Screens Screen"
Cohesion: 0.09
Nodes (21): build, _buildIdentifyButton, _buildImageSection, _buildSourceOption, createState, _errorMessage, _identifyPlant, _isLoading (+13 more)

### Community 36 - "Api Plant Plantnet"
Cohesion: 0.08
Nodes (17): apiClient, createDio, dio, endsWith, _handlingUnauthorized, _isAuthEndpoint, _apiKey, _baseUrl (+9 more)

### Community 37 - "Api Lazadaauthcontroller Workspacecontroller"
Cohesion: 0.10
Nodes (4): LazadaAuthController, WorkspaceController, Controller, WorkspaceActivityLogger

### Community 39 - "Feature Shopeetokenrefreshtest Shopeeservice"
Cohesion: 0.08
Nodes (4): {closure#2}(), {closure#3}(), {closure#4}(), auth()

### Community 40 - "Screen Package Dashboard"
Cohesion: 0.07
Nodes (14): _AppDrawer, _AppShell, child, createState, _currentIndex, _go, initState, _label (+6 more)

### Community 41 - "Order Providers Provider"
Cohesion: 0.08
Nodes (23): cancelOrder, copyWith, currentPage, error, fetchOrders, filters, getShippingParameters, hasMore (+15 more)

### Community 42 - "Picklistphotocleanupservice Picklistcompletioncontroller Api"
Cohesion: 0.13
Nodes (3): PickListCompletionController, PickListCompletion, PickListPhotoCleanupService

### Community 43 - "Product Providers Provider"
Cohesion: 0.08
Nodes (23): copyWith, currentPage, error, fetchGrouped, fetchProducts, filters, grouped, groupedData (+15 more)

### Community 44 - "Store Marketplacetype Accesstokenexpiresat"
Cohesion: 0.08
Nodes (24): accessTokenExpiresAt, ConnectionStatus, connectionStatusFromString, fromJson, id, isActive, lastRefreshedAt, lastRefreshError (+16 more)

### Community 45 - "Theme Shared Accent"
Cohesion: 0.08
Nodes (24): accent, accentLight, AppColors, background, border, buildAppTheme, divider, error (+16 more)

### Community 47 - "Screen Notifications Auth"
Cohesion: 0.09
Nodes (13): createState, dispose, _emailController, _formKey, LoginScreen, _LoginScreenState, _obscurePassword, _passwordController (+5 more)

### Community 48 - "Database Migrations 2026"
Cohesion: 0.14
Nodes (15): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#1}() (+7 more)

### Community 49 - "Jobs Syncshopeestorejob Illuminate"
Cohesion: 0.21
Nodes (4): SyncShopeeProducts, SyncShopeeStoreJob, UpdateShopeePriceJob, UpdateShopeeStockJob

### Community 51 - "Picklistphotocompressor Api Shopeeauthcontroller"
Cohesion: 0.12
Nodes (4): {closure#1}(), {closure#4}(), PickListPhotoCompressor, PickListPhotoCompressorTest

### Community 53 - "Ref Package Eslint"
Cohesion: 0.11
Nodes (20): axios, tailwindcss, @tailwindcss/vite, vite, name, private, type, version (+12 more)

### Community 54 - "Providers Auth Provider"
Cohesion: 0.11
Nodes (17): AppUser, AuthNotifier, AuthState, clearError, copyWith, error, errorDetail, forceLocalLogout (+9 more)

### Community 55 - "Shopeepromotionservice Throwable Ecomphp"
Cohesion: 0.19
Nodes (16): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}() (+8 more)

### Community 56 - "Shared Widgets Authenticated"
Cohesion: 0.11
Nodes (11): borderRadius, build, createState, didUpdateWidget, fit, _future, height, initState (+3 more)

### Community 57 - "Tsconfig Compileroptions Allowimportingtsextensions"
Cohesion: 0.11
Nodes (18): compilerOptions, allowImportingTsExtensions, erasableSyntaxOnly, jsx, lib, module, moduleDetection, moduleResolution (+10 more)

### Community 58 - "Notification Actiontype Appnotification"
Cohesion: 0.11
Nodes (18): actionType, AppNotification, AppNotificationData, category, createdAt, data, fromJson, id (+10 more)

### Community 59 - "Console Commands Synclazadaorders"
Cohesion: 0.14
Nodes (5): BackfillOrderOrderedAt, BackfillShopeeOrderQuantities, CleanupPickListPhotos, SyncLazadaOrders, SyncShopeeOrders

### Community 60 - "Shopeeresponsehelper Productservice Helpers"
Cohesion: 0.15
Nodes (4): ShopeeResponseHelper, {closure#39}(), {closure#40}(), {closure#45}()

### Community 61 - "Tsconfig Node Compileroptions"
Cohesion: 0.11
Nodes (17): compilerOptions, allowImportingTsExtensions, erasableSyntaxOnly, lib, module, moduleDetection, moduleResolution, noEmit (+9 more)

### Community 62 - "Deploy Remote Datetime"
Cohesion: 0.24
Nodes (5): main(), print_step(), remote_git_sync(), require_safe_value(), run_remote_command()

### Community 63 - "Picklistcommentcontroller Api Picklistcomment"
Cohesion: 0.16
Nodes (5): {closure#12}(), {closure#13}(), {closure#1}(), PickListCommentController, PickListComment

### Community 66 - "Team Screen Route"
Cohesion: 0.15
Nodes (9): build, MyStoresApp, authProvider, build, _submit, build, build, TeamScreen (+1 more)

### Community 70 - "Appserviceprovider Providers Illuminate"
Cohesion: 0.14
Nodes (7): AppServiceProvider, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}()

### Community 72 - "Package Devdependencies Eslint"
Cohesion: 0.13
Nodes (15): devDependencies, eslint, @eslint/js, eslint-plugin-react-hooks, eslint-plugin-react-refresh, globals, tailwindcss, @tailwindcss/vite (+7 more)

### Community 73 - "Auth Storage Flutter"
Cohesion: 0.13
Nodes (11): AuthStorage, clearAll, deleteToken, deleteUser, getToken, getUser, saveToken, saveUser (+3 more)

### Community 74 - "Notification Providers Provider"
Cohesion: 0.14
Nodes (12): copyWith, deleteNotification, error, fetchNotifications, fetchUnreadCount, isLoading, markAllAsRead, markAsRead (+4 more)

### Community 77 - "Database Migrations 2026"
Cohesion: 0.15
Nodes (3): {closure#1}(), {closure#1}(), {closure#2}()

### Community 78 - "Database Migrations 2026"
Cohesion: 0.15
Nodes (3): {closure#1}(), {closure#1}(), {closure#2}()

### Community 79 - "Plant Identification Result"
Cohesion: 0.15
Nodes (11): bestMatch, commonNames, family, fromJson, genus, PlantIdentificationResult, predictedOrgans, remainingRequests (+3 more)

### Community 81 - "Package Ref Json"
Cohesion: 0.17
Nodes (11): axios, tailwindcss, @tailwindcss/vite, vite, private, $schema, scripts, build (+3 more)

### Community 83 - "Api Error Bool"
Cohesion: 0.17
Nodes (10): _, apiErrorMessage, ApiResult, data, error, fail, isFail, isOk (+2 more)

### Community 85 - "Api Log Static"
Cohesion: 0.18
Nodes (6): add, ApiLog, clear, dump, _entries, _maxEntries

### Community 87 - "Bootstrap Illuminate Foundation"
Cohesion: 0.24
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 89 - "Composer Description Keywords"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 90 - "Composer Scripts Post"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 91 - "Home Widget Widgets"
Cohesion: 0.22
Nodes (5): androidName, clear, HomeScreenWidget, qualifiedAndroidName, syncFromDashboard

### Community 92 - "Plant Widgets Organ"
Cohesion: 0.22
Nodes (5): build, onOrganChanged, _organs, OrganSelector, selectedOrgan

### Community 95 - "Composer Require Laravel"
Cohesion: 0.25
Nodes (7): require, dompdf/dompdf, ecomphp/shopee-php, laravel/framework, laravel/sanctum, laravel/tinker, php

### Community 96 - "Composer Require Dev"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 99 - "Shared Widgets Shell"
Cohesion: 0.25
Nodes (5): AppShellScope, of, openDrawer, scaffoldKey, updateShouldNotify

### Community 100 - "Productauthorizationexception Exceptions Construct"
Cohesion: 0.33
Nodes (3): ProductAuthorizationException, {closure#4}(), PlantNetException

### Community 101 - "Composer Config Allow"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 103 - "Database Migrations 2026"
Cohesion: 0.29
Nodes (4): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}()

### Community 104 - "Package Devdependencies Vite"
Cohesion: 0.29
Nodes (7): devDependencies, axios, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite, vite

### Community 106 - "Package Dependencies React"
Cohesion: 0.29
Nodes (7): dependencies, axios, pdf-lib, react, react-dom, react-router-dom, @tailwindcss/oxide-win32-x64-msvc

### Community 107 - "Package Scripts Name"
Cohesion: 0.29
Nodes (6): name, private, scripts, build, deploy, dev

### Community 109 - "Database Migrations 0001"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 110 - "Database Migrations 2026"
Cohesion: 0.40
Nodes (4): backfillFromActivityLogs(), {closure#1}(), {closure#2}(), up()

### Community 114 - "Shared Widgets Authenticated"
Cohesion: 0.40
Nodes (4): _DrawerAppVersion, _DrawerAppVersionState, AuthenticatedNetworkImage, _AuthenticatedNetworkImageState

### Community 115 - "Composer Psr Autoload"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 129 - "Package Scripts Build"
Cohesion: 0.40
Nodes (5): scripts, build, dev, lint, preview

### Community 146 - "Composer Autoload Dev"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 147 - "Composer Extra Laravel"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

## Knowledge Gaps
- **860 isolated node(s):** `$schema`, `name`, `type`, `description`, `keywords` (+855 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 1353 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **73 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `ShopeeStore` connect `Feature Shopeeoauthtest Lazadaauthcontrollertest` to `Orderservice Feature Dashboardtest`, `Feature Lazadaordermanagementtest Promotiontest`, `Workspacecredentialtest Workspace Workspaceinvite`, `Lazadaservice Lazadatokenrefreshtest Feature`, `Dashboardcontroller Api Carbon`, `Shippinglabeltest Feature Arrangeshipmenttest`, `Api Promotioncontroller Ordercontroller`, `Productservice Productvariant Orderitem`, `Feature Needspushstocktest Productgroupedfiltertest`, `Workspaceaccess Feature Picklistcommenttest`, `Notifications Workspacenotificationservice Ordernewnotification`, `Storehealthservicetest Feature Storehealthnotificationtest`, `Lazadapromotionservice Feature Promotiontest`, `Productservice Storenotactiveexception Lazadaservice`, `Api Lazadaauthcontroller Workspacecontroller`, `Shopeeservice Productservice Construct`, `Feature Shopeetokenrefreshtest Shopeeservice`, `Picklistphotocleanupservice Picklistcompletioncontroller Api`, `Shopeepromotionservice Accessibleshopeestores Adddiscountitems`, `Jobs Syncshopeestorejob Illuminate`, `Shopeewebhookcontroller Workspaceconfig Api`, `Picklistphotocompressor Api Shopeeauthcontroller`, `Shopeetokenrefreshtest Feature Token`, `Shopeepromotionservice Throwable Ecomphp`, `Console Commands Synclazadaorders`, `Shopeeresponsehelper Productservice Helpers`, `Shopeestore Workspace User`, `Lazadaorderservice Cancelorder Downloadawbpdf`, `Api Promotioncontroller Illuminate`, `Shopeeorderpushtest Feature Order`, `Lazadaorderservice Deriveorderstatus Extractpackagesfromitems`, `Lazadaproductupdatetest Feature Lazada`, `Lazadaorderservice`, `Securityhardeningtest Feature Oauth`, `Picklistcompletiontest Feature Photo`, `Feature Arrangeshipmenttest Logisticsservice`, `Bulkshippinglabeltest Feature Bulk`, `Lazadabulkawbtest Feature Awb`, `Refreshlazadatokens Console Commands`, `Shopeeorderquantitytest Feature Quantity`, `Workspacenotificationtest Feature New`?**
  _High betweenness centrality (0.178) - this node is a cross-community bridge._
- **What connects `$schema`, `name`, `type` to the rest of the system?**
  _860 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Components Pages Lazada` be split into smaller, more focused modules?**
  _Cohesion score 0.06013048845000882 - nodes in this community are weakly interconnected._
- **Why does `User` connect `Feature Lazadaordermanagementtest Promotiontest` to `Database Databaseseeder Seeders`, `Orderservice Feature Dashboardtest`, `Feature Shopeeoauthtest Lazadaauthcontrollertest`, `Workspacecredentialtest Workspace Workspaceinvite`, `Lazadaservice Lazadatokenrefreshtest Feature`, `Shippinglabeltest Feature Arrangeshipmenttest`, `Productservice Productvariant Orderitem`, `Feature Needspushstocktest Productgroupedfiltertest`, `Workspaceaccess Feature Picklistcommenttest`, `Notifications Workspacenotificationservice Ordernewnotification`, `Requests Authcontroller Api`, `Storehealthservicetest Feature Storehealthnotificationtest`, `Lazadapromotionservice Feature Promotiontest`, `Productservice Storenotactiveexception Lazadaservice`, `Feature Shopeetokenrefreshtest Shopeeservice`, `Shopeepromotionservice Accessibleshopeestores Adddiscountitems`, `Database Migrations 2026`, `Jobs Syncshopeestorejob Illuminate`, `Shopeewebhookcontroller Workspaceconfig Api`, `Shopeetokenrefreshtest Feature Token`, `Shopeepromotionservice Throwable Ecomphp`, `Shopeestore Workspace User`, `Lazadaorderservice Cancelorder Downloadawbpdf`, `Api Promotioncontroller Illuminate`, `Shopeeorderpushtest Feature Order`, `Lazadaorderservice Deriveorderstatus Extractpackagesfromitems`, `Lazadaproductupdatetest Feature Lazada`, `Lazadaorderservice`, `Securityhardeningtest Feature Oauth`, `Picklistcompletiontest Feature Photo`, `Feature Arrangeshipmenttest Logisticsservice`, `Bulkshippinglabeltest Feature Bulk`, `Lazadabulkawbtest Feature Awb`, `Userfactory Database Factories`, `Shopeeorderquantitytest Feature Quantity`, `Workspacenotificationtest Feature New`?**
  _High betweenness centrality (0.126) - this node is a cross-community bridge._
- **Should `Orderservice Feature Dashboardtest` be split into smaller, more focused modules?**
  _Cohesion score 0.05146242132543503 - nodes in this community are weakly interconnected._
- **Why does `WorkspaceAccess` connect `Workspaceaccess Feature Picklistcommenttest` to `Orderservice Feature Dashboardtest`, `Lazadaservice Lazadatokenrefreshtest Feature`, `Dashboardcontroller Api Carbon`, `Api Promotioncontroller Ordercontroller`, `Feature Needspushstocktest Productgroupedfiltertest`, `Notifications Workspacenotificationservice Ordernewnotification`, `Requests Authcontroller Api`, `Lazadapromotionservice Feature Promotiontest`, `Productservice Storenotactiveexception Lazadaservice`, `Api Lazadaauthcontroller Workspacecontroller`, `Shopeeservice Productservice Construct`, `Feature Shopeetokenrefreshtest Shopeeservice`, `Picklistphotocleanupservice Picklistcompletioncontroller Api`, `Shopeepromotionservice Accessibleshopeestores Adddiscountitems`, `Shopeewebhookcontroller Workspaceconfig Api`, `Picklistphotocompressor Api Shopeeauthcontroller`, `Picklistcommentcontroller Api Picklistcomment`, `Shopeestore Workspace User`, `Lazadaorderservice Deriveorderstatus Extractpackagesfromitems`, `Workspacecredentialcontroller Api Securityheaders`?**
  _High betweenness centrality (0.074) - this node is a cross-community bridge._
- **Should `Dashboard Dashboardstats Picklistcompletioninfo` be split into smaller, more focused modules?**
  _Cohesion score 0.0273972602739726 - nodes in this community are weakly interconnected._