import axios, { type AxiosResponse, type InternalAxiosRequestConfig } from 'axios';
import { base64ToUint8Array } from '../lib/shippingLabelPdf';
import {
  buildGetCacheKey,
  getCachedGet,
  invalidateApiGetCache,
  setCachedGet,
  getLocalSnapshot,
  setLocalSnapshot,
  clearLocalSnapshots,
} from './getCache';

declare module 'axios' {
  export interface AxiosRequestConfig {
    /** Skip the 60s in-memory GET cache for this request. */
    skipCache?: boolean;
    /** Internal: response was served from in-memory GET cache. */
    __fromCache?: boolean;
    _retryCount?: number;
  }
}

const api = axios.create({
  baseURL: '/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

function shouldUseGetCache(config: InternalAxiosRequestConfig): boolean {
  if (config.skipCache) return false;
  if ((config.method || 'get').toLowerCase() !== 'get') return false;
  if (config.responseType && config.responseType !== 'json') return false;
  const url = config.url || '';
  // Auth redirects and ephemeral URLs should always be fresh
  if (url.includes('/auth-url')) return false;
  return true;
}

function cacheKeyFromConfig(config: InternalAxiosRequestConfig): string {
  const base = (config.baseURL || '').replace(/\/$/, '');
  const path = config.url || '';
  const full = path.startsWith('http') ? path : `${base}${path.startsWith('/') ? '' : '/'}${path}`;
  return buildGetCacheKey(full.replace(/^\/api/, '') || path, config.params);
}

// Attach token to requests; serve cached GETs within 60s
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  if (shouldUseGetCache(config)) {
    const key = cacheKeyFromConfig(config);
    const hit = getCachedGet(key);
    if (hit) {
      config.__fromCache = true;
      config.adapter = async () =>
        ({
          data: hit.data,
          status: hit.status,
          statusText: hit.statusText,
          headers: hit.headers,
          config,
          request: {},
        }) as AxiosResponse;
    }
  }

  return config;
});

// Response interceptor: cache GETs, invalidate on mutations, handle 401 + retry
api.interceptors.response.use(
  (response) => {
    const method = (response.config.method || 'get').toLowerCase();
    if (
      method === 'get' &&
      shouldUseGetCache(response.config) &&
      !response.config.__fromCache
    ) {
      setCachedGet(cacheKeyFromConfig(response.config), {
        data: response.data,
        status: response.status,
        statusText: response.statusText,
        headers: response.headers as Record<string, string>,
      });
    } else if (method !== 'get' && method !== 'head' && method !== 'options') {
      // Mutations change server state — drop GET snapshots
      invalidateApiGetCache();
    }
    return response;
  },
  async (error) => {
    const config = error.config;

    // Handle 401 — clear session + cache
    if (error.response?.status === 401) {
      invalidateApiGetCache();
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      if (window.location.pathname !== '/login') {
        window.location.href = '/login';
      }
      return Promise.reject(error);
    }

    // Retry GET requests on 429 or 5xx (up to 3 times, exponential backoff)
    if (
      config &&
      config.method === 'get' &&
      !config._retryCount &&
      (error.response?.status === 429 || (error.response?.status ?? 0) >= 500)
    ) {
      config._retryCount = (config._retryCount || 0) + 1;
      if (config._retryCount <= 3) {
        const delay = Math.pow(2, config._retryCount) * 1000;
        await new Promise((resolve) => setTimeout(resolve, delay));
        return api(config);
      }
    }

    return Promise.reject(error);
  }
);

export default api;
export { invalidateApiGetCache, getLocalSnapshot, setLocalSnapshot, clearLocalSnapshots };

// ── Store API (Shopee + Lazada) ────────────────────────────

export type MarketplaceType = 'shopee' | 'lazada';

export interface ShopeeStore {
  id: number;
  shop_id: number;
  platform: MarketplaceType;
  shop_name: string | null;
  region: string | null;
  is_active: boolean;
  products_count?: number;
  connection_status: 'active' | 'expiring_soon' | 'refresh_failed' | 'reauthorize_required' | 'disconnected' | 'missing_token';
  token_health: 'healthy' | 'expiring_soon' | 'expired';
  requires_reauthorization: boolean;
  linked_at: string;
  last_refreshed_at?: string | null;
  last_refresh_attempt_at?: string | null;
  last_refresh_failed_at?: string | null;
  last_refresh_error?: string | null;
  refresh_failure_count?: number;
  access_token_expires_at?: string | null;
  refresh_token_expires_at?: string | null;
  vacation_mode?: boolean;
  vacation_mode_synced_at?: string | null;
}

// ── Shopee ──────────────────────────────────────────────────

export async function getAuthUrl(): Promise<string> {
  const res = await api.get('/shopee/auth-url');
  return res.data.url;
}

export async function getStores(): Promise<ShopeeStore[]> {
  const res = await api.get('/shopee/stores');
  return res.data.stores;
}

export async function setStoreVacationMode(id: number, vacationMode: boolean): Promise<{ message: string; vacation_mode: boolean }> {
  const res = await api.post(`/shopee/stores/${id}/vacation-mode`, { vacation_mode: vacationMode });
  invalidateApiGetCache('/shopee/stores');
  return res.data;
}

export async function getStoreVacationMode(id: number): Promise<{ vacation_mode: boolean; vacation_mode_synced_at?: string | null }> {
  const res = await api.get(`/shopee/stores/${id}/vacation-mode`, { skipCache: true });
  return res.data;
}

export async function unlinkStore(id: number, platform: MarketplaceType = 'shopee'): Promise<void> {
  const endpoint = platform === 'lazada' ? `/lazada/stores/${id}` : `/shopee/stores/${id}`;
  await api.delete(endpoint, { data: { confirmation: 'DELETE STORE DATA' } });
}

export async function reauthorizeStore(id: number, platform: MarketplaceType = 'shopee'): Promise<string> {
  const endpoint = platform === 'lazada'
    ? `/lazada/stores/${id}/reauthorize`
    : `/shopee/stores/${id}/reauthorize`;
  const res = await api.post(endpoint);
  return res.data.url;
}

export async function disconnectStore(id: number, platform: MarketplaceType = 'shopee'): Promise<void> {
  const endpoint = platform === 'lazada'
    ? `/lazada/stores/${id}/disconnect`
    : `/shopee/stores/${id}/disconnect`;
  await api.post(endpoint);
}

// ── Lazada ──────────────────────────────────────────────────

export async function getLazadaAuthUrl(): Promise<string> {
  const res = await api.get('/lazada/auth-url');
  return res.data.url;
}


// ── Products / Stock & Price API ────────────────────────────

export interface ProductVariant {
  id: number;
  model_id: number;
  name: string | null;
  model_sku: string | null;
  price: number;
  stock: number;
}

export interface Product {
  id: number;
  store_id: number;
  item_id: number;
  item_name: string | null;
  item_sku: string | null;
  category_id: number | null;
  price: number;
  stock: number;
  has_variants: boolean;
  synced_at: string | null;
  needs_push?: boolean;
  variants: ProductVariant[];
  store?: {
    id: number;
    platform: MarketplaceType;
    shop_name: string | null;
  };
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

export interface GroupedProductGroup {
  sku: string;
  products: Product[];
  total_stock: number;
}

export interface GroupedResponse {
  grouped: GroupedProductGroup[];
  ungrouped: Product[];
  total: number;
}

export async function getProducts(params?: {
  store_id?: number;
  search?: string;
  sort_by?: string;
  sort_dir?: string;
  page?: number;
}): Promise<PaginatedResponse<Product>> {
  const res = await api.get('/products', { params });
  return res.data;
}

export async function getProduct(id: number): Promise<Product> {
  const res = await api.get(`/products/${id}`);
  return res.data.product;
}

export async function updateStock(
  id: number,
  stockList: { model_id?: number; seller_stock: { stock: number }[] }[]
): Promise<Product> {
  const res = await api.put(`/products/${id}/stock`, { stock_list: stockList });
  return res.data.product;
}

export async function updatePrice(
  id: number,
  priceList: { model_id?: number; original_price: number }[]
): Promise<Product> {
  const res = await api.put(`/products/${id}/price`, { price_list: priceList });
  return res.data.product;
}

export async function updateStockAndPrice(
  id: number,
  stockList?: { model_id?: number; seller_stock: { stock: number }[] }[],
  priceList?: { model_id?: number; original_price: number }[],
  mode: 'local' | 'push' = 'push',
): Promise<Product> {
  const res = await api.put(`/products/${id}/stock-price`, {
    stock_list: stockList,
    price_list: priceList,
    mode,
  });
  return res.data.product;
}

export async function syncProducts(params?: {
  store_id?: number;
  product_ids?: number[];
}): Promise<{ message: string; status: string; queued: Array<Record<string, unknown>> }> {
  const res = await api.post('/products/pull', params ?? {});
  return res.data;
}

export async function pullProducts(params?: {
  store_id?: number;
  product_ids?: number[];
}): Promise<{ message: string; status: string; queued: Array<Record<string, unknown>> }> {
  return syncProducts(params);
}

export async function pushProducts(productIds: number[]): Promise<{
  message: string;
  status: string;
  queued: Array<Record<string, unknown>>;
}> {
  const res = await api.post('/products/push', { product_ids: productIds });
  return res.data;
}

export async function getProductsGrouped(params?: {
  store_id?: number;
  search?: string;
  stock_health?: string;
  link_status?: string;
  sync_state?: string;
  sort_by?: string;
  sort_dir?: string;
}): Promise<GroupedResponse> {
  const res = await api.get('/products/grouped', { params });
  return res.data;
}

export async function updateStockBySku(
  sku: string,
  stock: number,
  mode: 'local' | 'push' = 'push',
): Promise<{ message: string; results: string[] }> {
  const res = await api.put('/products/stock-by-sku', { sku, stock, mode });
  return res.data;
}

export async function updatePriceBySku(
  sku: string,
  price: number,
  mode: 'local' | 'push' = 'push',
): Promise<{ message: string; results: string[] }> {
  const res = await api.put('/products/price-by-sku', { sku, price, mode });
  return res.data;
}

// ── Orders API ────────────────────────────────────────────────

export interface OrderItem {
  id: number;
  item_id: number;
  model_id: number | null;
  item_name: string | null;
  quantity: number;
  price: number;
}

export interface OrderStore {
  id: number;
  shop_name: string | null;
  shop_id: number;
  region: string | null;
  platform?: MarketplaceType;
}

export interface Order {
  id: number;
  store_id: number;
  order_sn: string;
  order_status: string;
  total_amount: number;
  currency: string | null;
  buyer_username: string | null;
  shipping_address: string | null;
  ordered_at: string | null;
  paid_time: string | null;
  ship_by_date: string | null;
  cancel_reason: string | null;
  synced_at: string | null;
  items: OrderItem[];
  store?: OrderStore | null;
}

export interface OrdersPage {
  data: Order[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

export async function getOrders(params?: {
  store_id?: number;
  status?: string;
  status_in?: string[];
  search?: string;
  urgency?: 'overdue' | 'due_today' | 'due_48h' | '';
  ordered_from?: string;
  ordered_to?: string;
  page?: number;
  sort_by?: string;
  sort_dir?: 'asc' | 'desc';
  platform?: MarketplaceType;
}): Promise<OrdersPage> {
  const res = await api.get('/orders', { params });
  return res.data;
}

export async function getOrder(id: number): Promise<Order> {
  const res = await api.get(`/orders/${id}`);
  return res.data.order;
}

export async function cancelOrder(
  id: number,
  reason: string
): Promise<void> {
  await api.post(`/orders/${id}/cancel`, { cancel_reason: reason });
}

export async function syncOrders(platform: MarketplaceType = 'shopee'): Promise<string[]> {
  const res = await api.post('/orders/sync', { platform });
  return res.data.results;
}

export async function syncLazadaOrders(): Promise<string[]> {
  const res = await api.post('/lazada/orders/sync');
  return res.data.results;
}

export async function cancelLazadaOrder(
  id: number,
  reasonId: string,
  reasonDetail?: string
): Promise<Order> {
  const res = await api.post(`/lazada/orders/${id}/cancel`, {
    reason_id: reasonId,
    reason_detail: reasonDetail,
  });
  return res.data.order;
}

export async function packLazadaOrder(
  id: number,
  opts?: { shipping_allocate_type?: string; shipment_provider_code?: string }
): Promise<Order> {
  const res = await api.post(`/lazada/orders/${id}/pack`, opts ?? {});
  return res.data.order;
}

export async function readyToShipLazadaOrder(id: number): Promise<Order> {
  const res = await api.post(`/lazada/orders/${id}/ready-to-ship`);
  return res.data.order;
}

export async function downloadLazadaAwb(id: number): Promise<{
  awbPdf: Uint8Array;
  packingListPdf: Uint8Array;
  filename: string;
}> {
  const res = await api.get(`/lazada/orders/${id}/awb`, { timeout: 60000 });
  const data = res.data as {
    awb_pdf: string;
    packing_list_pdf: string;
    filename?: string;
  };
  return {
    awbPdf: base64ToUint8Array(data.awb_pdf),
    packingListPdf: base64ToUint8Array(data.packing_list_pdf),
    filename: data.filename || `lazada-awb-packing-${id}.pdf`,
  };
}

export async function downloadLazadaAwbsBulk(
  orderIds: number[],
): Promise<BulkShippingLabelResult> {
  const res = await api.post(
    '/lazada/orders/awb-bulk',
    { order_ids: orderIds },
    { timeout: 180000 },
  );
  const data = res.data as {
    documents: Array<{
      order_id: number;
      order_sn: string;
      awb_pdf: string;
      packing_list_pdf: string;
    }>;
    errors: Array<{
      order_id: number;
      order_sn: string | null;
      message: string;
    }>;
    filename: string;
  };
  return {
    documents: (data.documents ?? []).map((doc) => ({
      orderId: doc.order_id,
      orderSn: doc.order_sn,
      awbPdf: base64ToUint8Array(doc.awb_pdf),
      packingListPdf: base64ToUint8Array(doc.packing_list_pdf),
    })),
    errors: (data.errors ?? []).map((err) => ({
      orderId: err.order_id,
      orderSn: err.order_sn,
      message: err.message,
    })),
    filename: data.filename || 'lazada-awb-packing-bulk.pdf',
  };
}

// ── Logistics / Fulfillment API ──────────────────────────────

export interface ShippingTimeSlot {
  date: string;
  time_text: string;
  pickup_time_id?: string;
  drop_off_time_id?: string;
}

export interface PickupAddress {
  address_id: number;
  address: string;
  city: string;
  state: string;
  country?: string;
  region?: string;
  zipcode: string;
  default_address?: boolean;
  address_flag?: string[];
  time_slot_list: ShippingTimeSlot[] | null;
}

export interface DropoffBranch {
  branch_id: number;
  branch_name: string;
  address: string;
  country?: string;
  region?: string;
  time_slot_list?: ShippingTimeSlot[] | null;
}

export interface ShippingParameterResponse {
  info_needed?: {
    pickup?: string[];
    dropoff?: string[];
    non_integrated?: string[];
  };
  pickup?: { address_list?: PickupAddress[] } | null;
  dropoff?: { branch_list?: DropoffBranch[] } | null;
}

export async function getShippingParameters(
  orderId: number
): Promise<ShippingParameterResponse> {
  const res = await api.get(`/orders/${orderId}/shipping-parameters`);
  return res.data.params;
}

export async function shipOrder(
  orderId: number,
  params: {
    pickup?: { address_id?: number; pickup_time_id?: string };
    dropoff?: { branch_id?: number; drop_off_time_id?: string };
    non_integrated?: Record<string, unknown>;
  }
): Promise<Order> {
  const res = await api.post(`/orders/${orderId}/ship`, params);
  return res.data.order;
}

/**
 * Fetch Air Waybill + Packing List PDFs (base64) for client-side merge.
 * Note: May take 5–12 seconds while Shopee generates the document.
 */
export interface ShippingLabelDocuments {
  awbPdf: Uint8Array;
  packingListPdf: Uint8Array;
  filename: string;
}

export interface BulkShippingLabelResult {
  documents: Array<{
    orderId: number;
    orderSn: string;
    awbPdf: Uint8Array;
    packingListPdf: Uint8Array;
  }>;
  errors: Array<{
    orderId: number;
    orderSn: string | null;
    message: string;
  }>;
  filename: string;
}

export async function downloadShippingLabel(orderId: number): Promise<ShippingLabelDocuments> {
  const res = await api.get(`/orders/${orderId}/shipping-label`, {
    timeout: 30000,
  });
  const data = res.data as {
    awb_pdf: string;
    packing_list_pdf: string;
    filename: string;
  };
  return {
    awbPdf: base64ToUint8Array(data.awb_pdf),
    packingListPdf: base64ToUint8Array(data.packing_list_pdf),
    filename: data.filename || `awb-packing-${orderId}.pdf`,
  };
}

export async function downloadShippingLabelsBulk(
  orderIds: number[],
): Promise<BulkShippingLabelResult> {
  const res = await api.post(
    '/orders/shipping-labels',
    { order_ids: orderIds },
    { timeout: 180000 },
  );
  const data = res.data as {
    documents: Array<{
      order_id: number;
      order_sn: string;
      awb_pdf: string;
      packing_list_pdf: string;
    }>;
    errors: Array<{
      order_id: number;
      order_sn: string | null;
      message: string;
    }>;
    filename: string;
  };
  return {
    documents: (data.documents ?? []).map((doc) => ({
      orderId: doc.order_id,
      orderSn: doc.order_sn,
      awbPdf: base64ToUint8Array(doc.awb_pdf),
      packingListPdf: base64ToUint8Array(doc.packing_list_pdf),
    })),
    errors: (data.errors ?? []).map((err) => ({
      orderId: err.order_id,
      orderSn: err.order_sn,
      message: err.message,
    })),
    filename: data.filename || 'awb-packing-bulk.pdf',
  };
}

// ── Dashboard API ────────────────────────────────────────────

export interface StoreOrderBreakdown {
  store_id: number;
  shop_name: string | null;
  platform: 'shopee' | 'lazada' | string;
  count: number;
  gmv?: number;
}

export interface SalesTrendStoreDay {
  store_id: number;
  shop_name: string | null;
  platform: 'shopee' | 'lazada' | string;
  orders: number;
  gmv: number;
}

export interface SalesTrendDay {
  date: string;
  orders: number;
  gmv: number;
  by_store?: SalesTrendStoreDay[];
}

export interface SalesGrowthPeriod {
  sales_pct: number | null;
  orders_delta: number;
  sales_today?: number;
  sales_yesterday?: number;
  orders_today?: number;
  orders_yesterday?: number;
  sales_period?: number;
  sales_prior?: number;
  orders_period?: number;
  orders_prior?: number;
}

export interface SalesGrowth {
  today_vs_yesterday: SalesGrowthPeriod;
  period_vs_prior: SalesGrowthPeriod;
}

export type TrendDays = 7 | 14 | 30;

export interface CancelReasonStat {
  reason: string;
  label: string;
  count: number;
}

export interface DashboardStats {
  stores_count: number;
  orders_today: number;
  orders_today_by_store?: StoreOrderBreakdown[];
  gmv_today?: number;
  gmv_today_by_store?: StoreOrderBreakdown[];
  pending_orders_count: number;
  pending_orders_by_store?: StoreOrderBreakdown[];
  trend_days?: TrendDays;
  sales_trend?: SalesTrendDay[];
  sales_growth?: SalesGrowth;
  cancel_reasons?: CancelReasonStat[];
  cancel_reasons_total?: number;
  low_stock_items: number;
}

export interface DashboardWorkspace {
  id: number;
  name: string;
  role: string | null;
}

export interface DashboardData {
  user: { id: number; name: string; email: string };
  stats: DashboardStats;
  workspace?: DashboardWorkspace | null;
}

export async function getDashboardStats(options?: {
  trendDays?: TrendDays;
  marketplace?: MarketplaceType | 'all';
  storeId?: number | null;
}): Promise<DashboardData> {
  const params: Record<string, any> = {};
  if (options?.trendDays) params.trend_days = options.trendDays;
  if (options?.marketplace && options.marketplace !== 'all') params.marketplace = options.marketplace;
  if (options?.storeId) params.store_id = options.storeId;

  const res = await api.get('/dashboard', { params });
  if (!res.data || typeof res.data !== 'object' || !('stats' in res.data) || !res.data.stats) {
    throw new Error('Unexpected dashboard response.');
  }
  return res.data as DashboardData;
}

// ── Workspace / Team API ──────────────────────────────────

export interface WorkspaceMemberRow {
  id: number;
  user_id: number;
  role: string;
  name: string | null;
  email: string | null;
}

export interface WorkspaceInviteRow {
  id: number;
  email: string;
  role: string;
  expires_at: string;
  created_at: string;
}

export interface WorkspaceResponse {
  workspace: {
    id: number;
    name: string;
    role: string | null;
    is_owner: boolean;
  };
  members: WorkspaceMemberRow[];
  pending_invites: WorkspaceInviteRow[];
}

export async function getWorkspace(): Promise<WorkspaceResponse> {
  const res = await api.get('/workspace');
  return res.data;
}

export async function addWorkspaceMember(payload: {
  name: string;
  email: string;
  password: string;
  role?: 'owner' | 'member';
}): Promise<{
  message: string;
  member: WorkspaceMemberRow;
}> {
  const res = await api.post('/workspace/members', payload);
  return res.data;
}

export async function inviteWorkspaceMember(email: string): Promise<{
  message: string;
  invite: { id: number; email: string; token: string; expires_at: string };
}> {
  const res = await api.post('/workspace/invites', { email });
  return res.data;
}

export async function acceptWorkspaceInvite(token: string): Promise<{ message: string }> {
  const res = await api.post('/workspace/invites/accept', { token });
  return res.data;
}

export async function removeWorkspaceMember(memberId: number): Promise<void> {
  await api.delete(`/workspace/members/${memberId}`);
}

export interface WorkspaceActivityEntry {
  id: number;
  workspace_id: number;
  user_id: number;
  action: string;
  summary: string;
  subject_type: string | null;
  subject_id: number | null;
  meta: Record<string, unknown> | null;
  ip: string | null;
  created_at: string;
  user?: { id: number; name: string; email: string } | null;
}

export interface WorkspaceActivityResponse {
  data: WorkspaceActivityEntry[];
  current_page: number;
  last_page: number;
  total: number;
}

export async function getWorkspaceActivity(page = 1): Promise<WorkspaceActivityResponse> {
  const res = await api.get('/workspace/activity', { params: { page } });
  return res.data;
}

export type PickListKind = 'today' | 'pending';

export interface PickListCompletionInfo {
  id: number;
  picked_by: { id: number; name: string };
  picked_at: string | null;
  photo_url: string;
}

export interface PickListComment {
  id: number;
  body: string;
  created_at: string | null;
  user: { id: number; name: string };
}

export interface TodayPickListItem {
  item_key: string;
  item_id: number | string | null;
  model_id: number | string | null;
  item_name: string;
  quantity: number;
  store_names: string[];
  platforms: string[];
  completion?: PickListCompletionInfo | null;
  comments?: PickListComment[];
  comment_count?: number;
  has_unread_comments?: boolean;
  unread_comment_count?: number;
}

export interface TodayPickListOrder {
  id: number;
  order_sn: string;
  order_status: string;
  buyer_username: string | null;
  platform: string;
  shop_name: string;
  ordered_at: string | null;
  items: Array<{
    item_name: string;
    item_id: number | string | null;
    model_id: number | string | null;
    quantity: number;
    price: number;
  }>;
}

export interface TodayPickListResponse {
  date: string;
  generated_at: string;
  total_orders: number;
  total_skus: number;
  total_units: number;
  aggregated_items: TodayPickListItem[];
  orders: TodayPickListOrder[];
}

export async function getTodayPickList(): Promise<TodayPickListResponse> {
  const res = await api.get('/dashboard/today-picklist');
  return res.data;
}

export async function getPendingPickList(): Promise<TodayPickListResponse> {
  const res = await api.get('/dashboard/pending-picklist');
  return res.data;
}

export async function completePickListItem(params: {
  kind: PickListKind;
  item_key: string;
  item_id?: string | number | null;
  model_id?: string | number | null;
  item_name: string;
  photo: File;
}): Promise<PickListCompletionInfo> {
  const form = new FormData();
  form.append('kind', params.kind);
  form.append('item_key', params.item_key);
  form.append('item_name', params.item_name);
  if (params.item_id != null && params.item_id !== '') {
    form.append('item_id', String(params.item_id));
  }
  if (params.model_id != null && params.model_id !== '') {
    form.append('model_id', String(params.model_id));
  }
  form.append('photo', params.photo);
  const res = await api.post('/dashboard/picklist-completions', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return res.data;
}

export async function uncompletePickListItem(params: {
  kind: PickListKind;
  item_key: string;
}): Promise<void> {
  await api.delete('/dashboard/picklist-completions', { data: params });
}

export async function postPickListComment(params: {
  kind: PickListKind;
  item_key: string;
  item_id?: string | number | null;
  model_id?: string | number | null;
  item_name: string;
  body: string;
}): Promise<PickListComment> {
  const res = await api.post('/dashboard/picklist-comments', {
    kind: params.kind,
    item_key: params.item_key,
    item_id: params.item_id != null ? String(params.item_id) : null,
    model_id: params.model_id != null ? String(params.model_id) : null,
    item_name: params.item_name,
    body: params.body,
  });
  return res.data;
}

export async function deletePickListComment(id: number): Promise<void> {
  await api.delete(`/dashboard/picklist-comments/${id}`);
}

export async function markPickListCommentsRead(params: {
  kind: PickListKind;
  item_key: string;
}): Promise<void> {
  await api.post('/dashboard/picklist-comments/read', params);
}

/** Fetch pick-list photo as an object URL (caller must revoke). */
export async function fetchPickListPhotoObjectUrl(completionId: number): Promise<string> {
  const res = await api.get(`/dashboard/picklist-photos/${completionId}`, {
    responseType: 'blob',
  });
  return URL.createObjectURL(res.data);
}

// ── Notifications API ────────────────────────────────────────

export interface AppNotification {
  id: string;
  type: string;
  data: {
    category:
      | 'store_reauth'
      | 'order_urgent'
      | 'order_new'
      | 'order_status'
      | 'team_activity'
      | 'picklist_comment';
    title: string;
    message: string;
    store_id?: number;
    order_id?: number;
    order_sn?: string;
    platform?: string;
    action_type?: string;
    kind?: string;
  };
  read_at: string | null;
  created_at: string;
}

export async function getNotifications(unreadOnly = false, page = 1): Promise<{
  unread_count: number;
  notifications: {
    data: AppNotification[];
    current_page: number;
    last_page: number;
    total: number;
  };
}> {
  const res = await api.get('/notifications', {
    params: { unread_only: unreadOnly ? 1 : 0, page },
  });
  return res.data;
}

export async function getUnreadNotificationCount(): Promise<number> {
  const res = await api.get('/notifications/unread-count');
  return res.data.unread_count ?? 0;
}

export async function markNotificationAsRead(id: string): Promise<number> {
  const res = await api.post(`/notifications/${id}/read`);
  return res.data.unread_count ?? 0;
}

export async function markAllNotificationsAsRead(): Promise<void> {
  await api.post('/notifications/read-all');
}

export async function deleteNotification(id: string): Promise<number> {
  const res = await api.delete(`/notifications/${id}`);
  return res.data.unread_count ?? 0;
}

// ── Promotions API ─────────────────────────────────────────

export type PromotionType = 'discount' | 'voucher';

export interface PromotionSummary {
  platform: MarketplaceType;
  store_id: number;
  store_name?: string | null;
  type: PromotionType;
  remote_id: number | string | null;
  name?: string | null;
  status: string;
  start_at?: string | null;
  end_at?: string | null;
  error?: string;
  raw?: Record<string, unknown>;
  is_admin?: boolean;
  can_mutate?: boolean;
  mutate_blocked_reason?: string | null;
}

export async function getPromotions(params?: {
  platform?: MarketplaceType | 'all';
  store_id?: number;
  type?: PromotionType;
  status?: string;
}): Promise<PromotionSummary[]> {
  const res = await api.get('/promotions', { params });
  return res.data.data ?? [];
}

export async function getPromotion(
  platform: MarketplaceType,
  type: PromotionType,
  remoteId: string | number,
  storeId: number,
  voucherType?: string,
): Promise<PromotionSummary> {
  const res = await api.get(`/promotions/${platform}/${type}/${remoteId}`, {
    params: { store_id: storeId, voucher_type: voucherType },
  });
  return res.data.promotion;
}

export async function createShopeeDiscount(payload: {
  store_id: number;
  discount_name: string;
  start_time: number;
  end_time: number;
}): Promise<{ discount_id?: number; raw?: unknown }> {
  const res = await api.post('/shopee/promotions/discounts', payload);
  return res.data.result;
}

export async function addShopeeDiscountItems(
  discountId: number,
  payload: { store_id: number; item_list: Array<Record<string, unknown>> },
): Promise<unknown> {
  const res = await api.post(`/shopee/promotions/discounts/${discountId}/items`, payload);
  return res.data.result;
}

export async function updateShopeeDiscount(
  discountId: number,
  payload: Record<string, unknown> & { store_id: number; action?: 'update' | 'end' | 'delete' },
): Promise<unknown> {
  const res = await api.put(`/shopee/promotions/discounts/${discountId}`, payload);
  return res.data.result;
}

export async function createShopeeVoucher(payload: Record<string, unknown> & {
  store_id: number;
  voucher_name: string;
  voucher_type: number;
  reward_type: number;
  usage_quantity: number;
  min_basket_price: number;
  start_time: number;
  end_time: number;
}): Promise<{ voucher_id?: number; raw?: unknown }> {
  const res = await api.post('/shopee/promotions/vouchers', payload);
  return res.data.result;
}

export async function updateShopeeVoucher(
  voucherId: number,
  payload: Record<string, unknown> & { store_id: number; action?: 'update' | 'end' | 'delete' },
): Promise<unknown> {
  const res = await api.put(`/shopee/promotions/vouchers/${voucherId}`, payload);
  return res.data.result;
}

export async function createLazadaVoucher(payload: Record<string, unknown> & {
  store_id: number;
  voucher_type: string;
  apply: string;
  display_area: string;
  period_start_time: number;
  period_end_time: number;
  voucher_discount_type: string;
  criteria_over_money: string | number;
  limit: number;
  issued: number;
}): Promise<{ id?: number | string; raw?: unknown }> {
  const res = await api.post('/lazada/promotions/vouchers', payload);
  return res.data.result;
}

export async function updateLazadaVoucher(
  id: string | number,
  payload: Record<string, unknown> & { store_id: number },
): Promise<unknown> {
  const res = await api.put(`/lazada/promotions/vouchers/${id}`, payload);
  return res.data.result;
}

export async function activateLazadaVoucher(
  id: string | number,
  payload: { store_id: number; voucher_type?: string },
): Promise<unknown> {
  const res = await api.post(`/lazada/promotions/vouchers/${id}/activate`, payload);
  return res.data.result;
}

export async function deactivateLazadaVoucher(
  id: string | number,
  payload: { store_id: number; voucher_type?: string },
): Promise<unknown> {
  const res = await api.post(`/lazada/promotions/vouchers/${id}/deactivate`, payload);
  return res.data.result;
}

export async function mutateLazadaVoucherSkus(
  id: string | number,
  payload: {
    store_id: number;
    action: 'add' | 'remove';
    sku_ids: Array<number | string>;
    voucher_type?: string;
  },
): Promise<unknown> {
  const res = await api.post(`/lazada/promotions/vouchers/${id}/skus`, payload);
  return res.data.result;
}

// ── Workspace Credentials API ──────────────────────────────────

export interface WorkspaceCredentials {
  id: number;
  workspace_id: number;
  partner_id?: string | null;
  partner_key?: string | null;
  push_partner_key?: string | null;
  shopee_redirect_url?: string | null;
  app_key?: string | null;
  app_secret?: string | null;
  lazada_redirect_url?: string | null;
  created_at: string;
  updated_at: string;
}

export async function getWorkspaceCredentials(): Promise<WorkspaceCredentials | null> {
  const res = await api.get('/workspace/credentials');
  // API returns null if no credentials exist
  return res.data ?? null;
}

export async function updateWorkspaceCredentials(data: Partial<Omit<WorkspaceCredentials, 'id' | 'workspace_id' | 'created_at' | 'updated_at'>>): Promise<WorkspaceCredentials> {
  const res = await api.put('/workspace/credentials', data);
  return res.data;
}

export async function deleteWorkspaceCredentials(): Promise<void> {
  await api.delete('/workspace/credentials');
}

// ── Account ────────────────────────────────────────────────────────

export async function changePassword(data: {
  current_password: string;
  password: string;
  password_confirmation: string;
}): Promise<{ message: string }> {
  const res = await api.post('/change-password', data);
  return res.data;
}
