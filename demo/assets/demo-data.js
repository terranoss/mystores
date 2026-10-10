/* ==========================================================================
   MyStore Demo — sample data
   All values are fabricated. No live store, customer or order data appears here.
   Shape mirrors the exported interfaces from frontend/src/api/client.ts.
   ========================================================================== */

export const DEMO_USER = {
  id: 1,
  name: 'Aisyah Rahman',
  email: 'aisyah@demokit.my',
  workspace_role: 'owner',
  workspace_id: 1,
  is_workspace_owner: true,
};

export const DEMO_VERSION = '1.0.0';

/* ------------------------------------------------------------------ Stores */
export const STORES = [
  {
    id: 1,
    shop_name: 'Riverside Mini Market',
    shop_id: '228840551',
    platform: 'shopee',
    region: 'MY',
    product_count: 1842,
    linked_at: '2026-03-14',
    connection_status: 'active',
    access_token_expires_in_hours: 312,
    refresh_token_expires_in_days: 68,
    vacation_mode: false,
  },
  {
    id: 2,
    shop_name: 'Riverside Online Store',
    shop_id: '228840992',
    platform: 'shopee',
    region: 'MY',
    product_count: 1842,
    linked_at: '2026-03-14',
    connection_status: 'expiring_soon',
    access_token_expires_in_hours: 2,
    refresh_token_expires_in_days: 5,
    vacation_mode: false,
  },
  {
    id: 3,
    shop_name: 'Damansara Retail Point',
    shop_id: '339102774',
    platform: 'shopee',
    region: 'MY',
    product_count: 615,
    linked_at: '2026-06-02',
    connection_status: 'reauthorize_required',
    access_token_expires_in_hours: 0,
    refresh_token_expires_in_days: 0,
    vacation_mode: true,
  },
  {
    id: 4,
    shop_name: 'SS2 Convenience Store',
    shop_id: '551028834',
    platform: 'lazada',
    region: 'MY',
    product_count: 934,
    linked_at: '2026-08-21',
    connection_status: 'active',
    access_token_expires_in_hours: 540,
    refresh_token_expires_in_days: 74,
    vacation_mode: false,
  },
];

/* ------------------------------------------------------- 14-day sales trend */
export const SALES_TREND = {
  7: [
    { date: '2026-10-05', gmv: 3184.50, orders: 14 },
    { date: '2026-10-06', gmv: 2740.00, orders: 11 },
    { date: '2026-10-07', gmv: 3960.25, orders: 18 },
    { date: '2026-10-08', gmv: 5120.80, orders: 23 },
    { date: '2026-10-09', gmv: 4475.10, orders: 19 },
    { date: '2026-10-10', gmv: 6890.40, orders: 31 },
    { date: '2026-10-11', gmv: 8240.65, orders: 38 },
  ],
  14: [
    { date: '2026-09-28', gmv: 2410.30, orders: 10 },
    { date: '2026-09-29', gmv: 2980.75, orders: 13 },
    { date: '2026-09-30', gmv: 2655.40, orders: 12 },
    { date: '2026-10-01', gmv: 3410.90, orders: 15 },
    { date: '2026-10-02', gmv: 3890.20, orders: 17 },
    { date: '2026-10-03', gmv: 3120.60, orders: 14 },
    { date: '2026-10-04', gmv: 2760.15, orders: 12 },
    { date: '2026-10-05', gmv: 3184.50, orders: 14 },
    { date: '2026-10-06', gmv: 2740.00, orders: 11 },
    { date: '2026-10-07', gmv: 3960.25, orders: 18 },
    { date: '2026-10-08', gmv: 5120.80, orders: 23 },
    { date: '2026-10-09', gmv: 4475.10, orders: 19 },
    { date: '2026-10-10', gmv: 6890.40, orders: 31 },
    { date: '2026-10-11', gmv: 8240.65, orders: 38 },
  ],
  30: Array.from({ length: 30 }, (_, i) => {
    const d = new Date(2026, 8, 12 + i);
    const base = 2600 + ((i * 977) % 4200);
    return {
      date: isoDate(d),
      gmv: Number((base + (i % 7 === 0 ? 1800 : 0)).toFixed(2)),
      orders: Math.round(base / 240),
    };
  }),
};

/* Per-store split, used by the "by store" panels */
export const SALES_BY_STORE = {
  7: {
    '2026-10-11': { 1: { gmv: 4210.15, orders: 19 }, 2: { gmv: 2410.30, orders: 11 }, 4: { gmv: 1620.20, orders: 8 } },
    '2026-10-10': { 1: { gmv: 3390.00, orders: 15 }, 2: { gmv: 2140.20, orders: 10 }, 4: { gmv: 1360.20, orders: 6 } },
    '2026-10-09': { 1: { gmv: 2210.40, orders: 10 }, 2: { gmv: 1480.30, orders: 6 }, 4: { gmv: 784.40, orders: 3 } },
    '2026-10-08': { 1: { gmv: 2610.00, orders: 12 }, 2: { gmv: 1690.50, orders: 7 }, 4: { gmv: 820.30, orders: 4 } },
    '2026-10-07': { 1: { gmv: 1980.60, orders: 9 }, 2: { gmv: 1300.15, orders: 6 }, 4: { gmv: 679.50, orders: 3 } },
    '2026-10-06': { 1: { gmv: 1410.00, orders: 6 }, 2: { gmv: 880.40, orders: 3 }, 4: { gmv: 449.60, orders: 2 } },
    '2026-10-05': { 1: { gmv: 1690.30, orders: 8 }, 2: { gmv: 1010.00, orders: 4 }, 4: { gmv: 484.20, orders: 2 } },
  },
  14: {},
  30: {},
};
SALES_BY_STORE[14] = SALES_BY_STORE[7];

/* ------------------------------------------------------- Cancel reasons */
export const CANCEL_REASONS = [
  { reason: 'OUT_OF_STOCK', count: 9 },
  { reason: 'CHANGE_OF_MIND', count: 6 },
  { reason: 'BUYER_NOT_REACHED', count: 4 },
  { reason: 'ORDER_NOT_PROCEEDING', count: 3 },
  { reason: 'DECLINED_OTHER_REASON', count: 2 },
  { reason: 'SELLER_CANCEL', count: 1 },
];

export const CANCEL_REASON_LABELS = {
  OUT_OF_STOCK: 'Out of stock',
  CHANGE_OF_MIND: 'Change of mind',
  BUYER_NOT_REACHED: 'Buyer not reached',
  ORDER_NOT_PROCEEDING: 'Order not proceeding',
  DECLINED_OTHER_REASON: 'Declined (other)',
  SELLER_CANCEL: 'Seller cancelled',
  CUSTOMER_CANCELLED: 'Customer cancelled',
  ORDER_CREATED_TOO_LONG_AGO: 'Created too long ago',
};

/* ----------------------------------------------------------------- Orders */
const NAMES = ['Nurul H.', 'Ahmad F.', 'Siti R.', 'Wei Ling', 'Rajesh K.', 'Syafiq H.', 'Priscilla T.', 'Daniel C.', 'Hafiz M.', 'Yee Wen L.'];
const CITIES = ['Kuala Lumpur', 'Petaling Jaya', 'Johor Bahru', 'Penang', 'Kota Kinabalu', 'Shah Alam', 'Malacca', 'Kuching'];

function pad(n, w = 10) {
  return String(n).padStart(w, '0');
}

/* Format as YYYY-MM-DD using LOCAL calendar parts.
   toISOString() shifts to UTC, which moves orders onto the previous day for
   viewers east of Greenwich and made "Orders Today" render as 0. */
function isoDate(d) {
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${d.getFullYear()}-${m}-${day}`;
}

export const ORDER_STATUSES = ['UNPAID', 'READY_TO_SHIP', 'PROCESSED', 'SHIPPED', 'TO_CONFIRM_RECEIVE', 'COMPLETED', 'IN_CANCEL', 'CANCELLED', 'TO_RETURN'];

const ITEM_POOL = [
  ['Miyazaki Ghibli Vinyl Figure 8cm', 'SKU-MGV-08', 89.00],
  ['Rider Matcha Starter Kit', 'SKU-RM-KIT', 142.50],
  ['Nissin Cup Ramen Bundle x12', 'SKU-NSR-12', 64.20],
  ['Travel Organiser Bag Large', 'SKU-TOB-LG', 76.00],
  ['USB-C Fast Charger 65W', 'SKU-USB-65W', 118.90],
  ['Ceramic Pour-Over Set', 'SKU-CPS-01', 95.00],
  ['Wireless Earbuds Pro', 'SKU-WEP-PRO', 199.00],
  ['Bamboo Cutting Board Set', 'SKU-BCB-02', 54.75],
  ['Chef Knife 8 inch', 'SKU-CK-08', 89.90],
  ['Weighted Blanket 7kg', 'SKU-WB-7K', 168.00],
  ['Air Fryer 5.5L Digital', 'SKU-AFD-55', 249.00],
  ['Kids Learning Tablet', 'SKU-KLT-01', 179.50],
  ['Sports Water Bottle 1L', 'SKU-SWB-1L', 39.90],
  ['Cotton Bed Sheet Queen', 'SKU-CBS-QN', 129.00],
  ['Aroma Diffuser 300ml', 'SKU-AD-300', 58.40],
];

export const ORDERS = Array.from({ length: 64 }, (_, i) => {
  const platform = i % 5 === 0 ? 'lazada' : 'shopee';
  const storeId = platform === 'lazada' ? 4 : [1, 2, 3][i % 3];
  const status =
    i % 13 === 0 ? 'UNPAID' :
    i % 11 === 0 ? 'IN_CANCEL' :
    i % 9 === 0 ? 'TO_RETURN' :
    i % 7 === 0 ? 'CANCELLED' :
    i % 6 === 0 ? 'TO_CONFIRM_RECEIVE' :
    i % 5 === 0 ? 'COMPLETED' :
    i % 4 === 0 ? 'SHIPPED' :
    i % 3 === 0 ? 'PROCESSED' : 'READY_TO_SHIP';

  const itemCount = 1 + (i % 3);
  const items = Array.from({ length: itemCount }, (_, k) => {
    const [name, sku, price] = ITEM_POOL[(i + k * 3) % ITEM_POOL.length];
    return { name, sku, qty: 1 + ((i + k) % 3), price };
  });
  const total = items.reduce((s, it) => s + it.price * it.qty, 0);

  const d = new Date(2026, 9, 11 - Math.floor(i / 3));
  const shipBy = new Date(d);
  shipBy.setDate(shipBy.getDate() + 2 + (i % 4));

  return {
    id: i + 1,
    order_sn: `${platform === 'lazada' ? 'LZ' : 'SP'}${pad(2600000000 + i * 7919, 16)}`,
    platform,
    store_id: storeId,
    status,
    buyer_name: NAMES[i % NAMES.length],
    buyer_city: CITIES[i % CITIES.length],
    items,
    total: Number(total.toFixed(2)),
    ordered_at: isoDate(d),
    ship_by: isoDate(shipBy),
    tracking: status === 'SHIPPED' || status === 'TO_CONFIRM_RECEIVE' || status === 'COMPLETED'
      ? `${['SPX', 'JNT', 'POSLAJU', 'NINJA'][i % 4]}${pad(88001234 + i * 1337, 8)}MY`
      : null,
  };
});

/* ------------------------------------------------------------------ Stock */
export const STOCK_ITEMS = [
  { sku: 'SKU-MGV-08', name: 'Miyazaki Ghibli Vinyl Figure 8cm', model: 'MG-FIG-08', stock: 4, price: 89.00, category: 'Collectibles', brand: 'Ghibli' },
  { sku: 'SKU-RM-KIT', name: 'Rider Matcha Starter Kit', model: 'RM-START', stock: 0, price: 142.50, category: 'Kitchen', brand: 'Rider' },
  { sku: 'SKU-NSR-12', name: 'Nissin Cup Ramen Bundle x12', model: 'NSR-B12', stock: 132, price: 64.20, category: 'Grocery', brand: 'Nissin' },
  { sku: 'SKU-TOB-LG', name: 'Travel Organiser Bag Large', model: 'TOB-L', stock: 27, price: 76.00, category: 'Travel', brand: 'NoBrand' },
  { sku: 'SKU-USB-65W', name: 'USB-C Fast Charger 65W', model: 'UC65', stock: 8, price: 118.90, category: 'Electronics', brand: 'Voltix' },
  { sku: 'SKU-CPS-01', name: 'Ceramic Pour-Over Set', model: 'CPS', stock: 61, price: 95.00, category: 'Kitchen', brand: 'Kiln' },
  { sku: 'SKU-WEP-PRO', name: 'Wireless Earbuds Pro', model: 'WEP', stock: 3, price: 199.00, category: 'Electronics', brand: 'Voltix' },
  { sku: 'SKU-BCB-02', name: 'Bamboo Cutting Board Set', model: 'BCB2', stock: 44, price: 54.75, category: 'Kitchen', brand: 'Eco' },
  { sku: 'SKU-CK-08', name: 'Chef Knife 8 inch', model: 'CK8', stock: 19, price: 89.90, category: 'Kitchen', brand: 'Yoshita' },
  { sku: 'SKU-WB-7K', name: 'Weighted Blanket 7kg', model: 'WB7', stock: 0, price: 168.00, category: 'Home', brand: 'NoBrand' },
  { sku: 'SKU-AFD-55', name: 'Air Fryer 5.5L Digital', model: 'AFD55', stock: 23, price: 249.00, category: 'Appliances', brand: 'Heatwave' },
  { sku: 'SKU-KLT-01', name: 'Kids Learning Tablet', model: 'KLT', stock: 12, price: 179.50, category: 'Electronics', brand: 'BrightPath' },
  { sku: 'SKU-SWB-1L', name: 'Sports Water Bottle 1L', model: 'SWB1', stock: 210, price: 39.90, category: 'Sports', brand: 'HydroPeak' },
  { sku: 'SKU-CBS-QN', name: 'Cotton Bed Sheet Queen', model: 'CBSQ', stock: 6, price: 129.00, category: 'Home', brand: 'Lumera' },
  { sku: 'SKU-AD-300', name: 'Aroma Diffuser 300ml', model: 'AD300', stock: 88, price: 58.40, category: 'Home', brand: 'Aura' },
  { sku: 'SKU-SHLD-01', name: 'Tablet Holder Stand', model: 'THS1', stock: 2, price: 42.00, category: 'Electronics', brand: 'NoBrand' },
  { sku: 'SKU-MGSET-5', name: 'Magnetic Knife Strip Set', model: 'MGS5', stock: 15, price: 67.80, category: 'Kitchen', brand: 'Yoshita' },
  { sku: 'SKU-BTL-32', name: 'Insulated Bottle 32oz', model: 'IB32', stock: 74, price: 88.00, category: 'Sports', brand: 'HydroPeak' },
  { sku: 'SKU-LMP-DESK', name: 'Desk Lamp LED Dimming', model: 'DL01', stock: 0, price: 84.30, category: 'Home', brand: 'Lumina' },
  { sku: 'SKU-BRK-KIT', name: 'Baking Starter Kit 10pc', model: 'BSK10', stock: 37, price: 156.00, category: 'Kitchen', brand: 'Kiln' },
  { sku: 'SKU-PET-BOWL', name: 'Pet Bowl Non-Slip', model: 'PBN1', stock: 156, price: 26.90, category: 'Pets', brand: 'PawHome' },
  { sku: 'SKU-BLD-500', name: 'Protein Blend Chocolate 500g', model: 'PB500', stock: 93, price: 71.40, category: 'Health', brand: 'FitFuel' },
  { sku: 'SKU-SNK-SET', name: 'Snack Tumbler Set 4pc', model: 'STS4', stock: 5, price: 33.60, category: 'Kitchen', brand: 'NoBrand' },
  { sku: 'SKU-CBL-2M', name: 'Braided Lightning Cable 2m', model: 'BLC2', stock: 241, price: 22.90, category: 'Electronics', brand: 'Voltix' },
  { sku: 'SKU-RUG-S', name: 'Anti-Fatigue Floor Mat', model: 'AFM1', stock: 17, price: 112.00, category: 'Home', brand: 'Eco' },
  { sku: 'SKU-CUP-SET', name: 'Ceramic Mug Set 6pc', model: 'CMS6', stock: 41, price: 98.50, category: 'Kitchen', brand: 'Kiln' },
  { sku: 'SKU-BRD-S', name: 'Sourdough Starter Kit', model: 'SSK1', stock: 9, price: 145.00, category: 'Kitchen', brand: 'GrainCo' },
  { sku: 'SKU-SCL-DGT', name: 'Digital Kitchen Scale', model: 'DKS1', stock: 63, price: 47.20, category: 'Kitchen', brand: 'NoBrand' },
  { sku: 'SKU-BLK-GST', name: 'Black Granite Gas Stove', model: 'BGS1', stock: 11, price: 329.00, category: 'Appliances', brand: 'Heatwave' },
  { sku: 'SKU-MAT-YOG', name: 'Yoga Mat 6mm Non-Slip', model: 'YM6', stock: 68, price: 89.90, category: 'Sports', brand: 'FlowFit' },
  { sku: 'SKU-DUM-20', name: 'Adjustable Dumbbell 20kg', model: 'AD20', stock: 7, price: 415.00, category: 'Sports', brand: 'FlowFit' },
  { sku: 'SKU-BTL-GLS', name: 'Glass Lunch Box 3pc', model: 'GLB3', stock: 84, price: 37.60, category: 'Kitchen', brand: 'Kiln' },
  { sku: 'SKU-CHR-FLD', name: 'Folding Dining Chair', model: 'FDC1', stock: 26, price: 118.00, category: 'Furniture', brand: 'Homi' },
  { sku: 'SKU-SHOE-RK', name: 'Shoe Rack 5-Tier', model: 'SR5', stock: 13, price: 149.00, category: 'Furniture', brand: 'Homi' },
  { sku: 'SKU-LMP-NT', name: 'Night Light Motion Sensor', model: 'NLM1', stock: 118, price: 29.90, category: 'Home', brand: 'Lumina' },
  { sku: 'SKU-PCD-12', name: 'Packing Tape 12 rolls', model: 'PT12', stock: 302, price: 34.80, category: 'Packaging', brand: 'PackRight' },
  { sku: 'SKU-BUB-WRP', name: 'Bubble Wrap Roll 50m', model: 'BW50', stock: 45, price: 58.00, category: 'Packaging', brand: 'PackRight' },
  { sku: 'SKU-LBL-TH', name: 'Thermal Label 100x150mm', model: 'TL1015', stock: 780, price: 62.40, category: 'Packaging', brand: 'PackRight' },
];

/* --------------------------------------------------------------- Pick list */
export const PICK_ITEMS = [
  { sku: 'SKU-MGV-08', name: 'Miyazaki Ghibli Vinyl Figure 8cm', location: 'A-01-03', qty: 12 },
  { sku: 'SKU-USB-65W', name: 'USB-C Fast Charger 65W', location: 'C-02-01', qty: 18 },
  { sku: 'SKU-WEP-PRO', name: 'Wireless Earbuds Pro', location: 'C-02-04', qty: 9 },
  { sku: 'SKU-NSR-12', name: 'Nissin Cup Ramen Bundle x12', location: 'B-04-02', qty: 34 },
  { sku: 'SKU-CPS-01', name: 'Ceramic Pour-Over Set', location: 'A-03-01', qty: 7 },
  { sku: 'SKU-SWB-1L', name: 'Sports Water Bottle 1L', location: 'D-01-05', qty: 21 },
  { sku: 'SKU-CK-08', name: 'Chef Knife 8 inch', location: 'A-02-02', qty: 5 },
  { sku: 'SKU-AFD-55', name: 'Air Fryer 5.5L Digital', location: 'E-01-01', qty: 6 },
  { sku: 'SKU-CBS-QN', name: 'Cotton Bed Sheet Queen', location: 'F-02-03', qty: 4 },
  { sku: 'SKU-PET-BOWL', name: 'Pet Bowl Non-Slip', location: 'D-03-02', qty: 15 },
  { sku: 'SKU-PCD-12', name: 'Packing Tape 12 rolls', location: 'G-01-01', qty: 28 },
  { sku: 'SKU-LBL-TH', name: 'Thermal Label 100x150mm', location: 'G-01-04', qty: 40 },
];

export const PICK_COMMENTS = [
  { author: 'Warehouse Lead', text: 'Vinyl figures are fragile — please wrap individually before boxing.', at: '09:12' },
  { author: 'Aisyah Rahman', text: 'Air fryer cartons are oversized, pack two per box.', at: '09:48' },
];

/* --------------------------------------------------------------- Team */
export const TEAM = [
  { id: 1, name: 'Aisyah Rahman', email: 'aisyah@demokit.my', role: 'owner', activity: 'Updated Lazada credentials' },
  { id: 2, name: 'Nurul Huda', email: 'nurul@demokit.my', role: 'manager', activity: 'Printed 12 AWB labels' },
  { id: 3, name: 'Syafiq Hakim', email: 'syafiq@demokit.my', role: 'staff', activity: 'Completed today pick list' },
  { id: 4, name: 'Priscilla Tan', email: 'priscilla@demokit.my', role: 'staff', activity: 'Updated stock for 8 SKUs' },
  { id: 5, name: 'Daniel Chan', email: 'daniel@demokit.my', role: 'cashier', activity: 'Logged 3 POS sales' },
];

/* --------------------------------------------------------- Notifications */
export const NOTIFICATIONS = [
  { id: 1, type: 'store_reauth', title: 'Damansara Retail Point needs reauthorization', body: 'Shopee refresh token expired. Reconnect the store to resume syncing.', at: '10 min ago', unread: true },
  { id: 2, type: 'order_urgent', title: '3 orders past ship-by deadline', body: 'Riverside Online Store has orders awaiting shipment.', at: '35 min ago', unread: true },
  { id: 3, type: 'picklist_comment', title: 'New comment on Pick List', body: 'Warehouse Lead: "Vinyl figures are fragile — wrap individually."', at: '1 hr ago', unread: true },
  { id: 4, type: 'order_new', title: '8 new Shopee orders', body: 'Synced from Riverside Mini Market.', at: '2 hrs ago', unread: false },
  { id: 5, type: 'team_activity', title: 'Syafiq Hakim completed a pick list', body: '42 items picked and packed.', at: '3 hrs ago', unread: false },
];

/* ------------------------------------------------------------ Promotions */
export const PROMOTIONS = [
  { id: 1, kind: 'Shopee Discount', name: 'Flash Sale 10% — All Store', status: 'Running', used: 142, quota: 500, ends: '2026-10-20' },
  { id: 2, kind: 'Shopee Voucher', name: 'Free Shipping RM15 Off', status: 'Running', used: 88, quota: 300, ends: '2026-10-31' },
  { id: 3, kind: 'Shopee Discount', name: 'Clearance Bundle 20%', status: 'Expired', used: 500, quota: 500, ends: '2026-09-30' },
  { id: 4, kind: 'Lazada Voucher', name: 'New Buyer RM10', status: 'Scheduled', used: 0, quota: 1000, ends: '2026-11-15' },
];

/* ------------------------------------------------------- Derived helpers */
export function storeById(id) {
  return STORES.find((s) => s.id === id);
}

export function lowStockCount() {
  return STOCK_ITEMS.filter((i) => i.stock > 0 && i.stock <= 10).length + STOCK_ITEMS.filter((i) => i.stock === 0).length;
}

export const PENDING_STATUSES = ['UNPAID', 'READY_TO_SHIP', 'PROCESSED'];

export function pendingOrders() {
  return ORDERS.filter((o) => PENDING_STATUSES.includes(o.status)).length;
}