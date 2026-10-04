import { useState, useEffect, useRef, useMemo } from 'react';
import { PageShell } from '../components/PageShell';
import Spinner from '../components/Spinner';
import { useStore } from '../context/StoreContext';
import { useToast } from '../context/ToastContext';
import {
  getProductsGrouped,
  pullProducts,
  pushProducts,
  updateStockAndPrice,
  updateStockBySku,
  updatePriceBySku,
  type Product,
  type GroupedProductGroup,
} from '../api/client';

const PAGE_SIZE = 50;

type StockHealthFilter = '' | 'out' | 'low' | 'in';
type LinkStatusFilter = '' | 'linked' | 'unlinked' | 'multi_store' | 'variants';
type SyncStateFilter = '' | 'unsynced';
type SortPreset = 'name' | 'stock' | 'price' | 'synced';

const STOCK_HEALTH_CHIPS: { value: StockHealthFilter; label: string }[] = [
  { value: '', label: 'All' },
  { value: 'out', label: 'Out of stock' },
  { value: 'low', label: 'Low (≤5)' },
  { value: 'in', label: 'In stock' },
];

const LINK_STATUS_OPTIONS: { value: LinkStatusFilter; label: string }[] = [
  { value: '', label: 'All products' },
  { value: 'linked', label: 'Linked (has SKU)' },
  { value: 'unlinked', label: 'Unlinked (no SKU)' },
  { value: 'multi_store', label: 'Multi-store SKU' },
  { value: 'variants', label: 'Has variants' },
];

const SORT_PRESETS: {
  value: SortPreset;
  label: string;
  sortBy: 'sku' | 'item_name' | 'price' | 'stock' | 'stores' | 'synced_at';
  sortDir: 'asc' | 'desc';
  apiSortBy: string;
  apiSortDir: string;
}[] = [
  {
    value: 'name',
    label: 'Name A–Z',
    sortBy: 'item_name',
    sortDir: 'asc',
    apiSortBy: 'item_name',
    apiSortDir: 'asc',
  },
  {
    value: 'stock',
    label: 'Stock (lowest first)',
    sortBy: 'stock',
    sortDir: 'asc',
    apiSortBy: 'stock',
    apiSortDir: 'asc',
  },
  {
    value: 'price',
    label: 'Price (low to high)',
    sortBy: 'price',
    sortDir: 'asc',
    apiSortBy: 'price',
    apiSortDir: 'asc',
  },
  {
    value: 'synced',
    label: 'Recently synced',
    sortBy: 'synced_at',
    sortDir: 'desc',
    apiSortBy: 'synced_at',
    apiSortDir: 'desc',
  },
];

type StockRow =
  | {
      kind: 'sku';
      key: string;
      group: GroupedProductGroup;
      itemName: string;
      minPrice: number;
      maxPrice: number;
      minStock: number;
      maxStock: number;
      storeCount: number;
      sku: string;
      syncedAt: number;
      needsPush: boolean;
    }
  | {
      kind: 'product';
      key: string;
      product: Product;
      itemName: string;
      minPrice: number;
      maxPrice: number;
      minStock: number;
      maxStock: number;
      storeCount: number;
      sku: string;
      syncedAt: number;
      needsPush: boolean;
    };

function formatPriceDisplay(minPrice: number, maxPrice: number): string {
  if (minPrice === maxPrice) {
    return `$${minPrice.toFixed(2)}`;
  }
  return `$${minPrice.toFixed(2)}–$${maxPrice.toFixed(2)}`;
}

function formatStockDisplay(minStock: number, maxStock: number): string {
  if (minStock === maxStock) {
    return String(minStock);
  }
  return `${minStock}–${maxStock}`;
}

function buildStockRows(
  grouped: GroupedProductGroup[],
  ungrouped: Product[]
): StockRow[] {
  const skuRows: StockRow[] = grouped.map((group) => {
    const prices = group.products.map((p) => Number(p.price));
    const stocks = group.products.map((p) => Number(p.stock));
    const syncedAts = group.products.map((p) =>
      p.synced_at ? new Date(p.synced_at).getTime() : 0
    );
    const minPrice = prices.length ? Math.min(...prices) : 0;
    const maxPrice = prices.length ? Math.max(...prices) : 0;
    const minStock = stocks.length ? Math.min(...stocks) : 0;
    const maxStock = stocks.length ? Math.max(...stocks) : 0;
    const first = group.products[0];
    return {
      kind: 'sku',
      key: `sku:${group.sku}`,
      group,
      sku: group.sku,
      itemName: first?.item_name || `SKU ${group.sku}`,
      minPrice,
      maxPrice,
      minStock,
      maxStock,
      storeCount: group.products.length,
      syncedAt: syncedAts.length ? Math.max(...syncedAts) : 0,
      needsPush: group.products.some((p) => Boolean(p.needs_push)),
    };
  });
  const productRows: StockRow[] = ungrouped.map((product) => ({
    kind: 'product',
    key: `product:${product.id}`,
    product,
    sku: '',
    itemName: product.item_name || `Item #${product.item_id}`,
    minPrice: Number(product.price),
    maxPrice: Number(product.price),
    minStock: product.stock,
    maxStock: product.stock,
    storeCount: 1,
    syncedAt: product.synced_at ? new Date(product.synced_at).getTime() : 0,
    needsPush: Boolean(product.needs_push),
  }));
  return [...skuRows, ...productRows];
}

function StockBadge({ stock }: { stock: number }) {
  const color =
    stock > 10
      ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
      : stock > 3
        ? 'bg-amber-50 text-amber-700 border-amber-200'
        : 'bg-red-50 text-red-700 border-red-200';
  return (
    <span
      className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border ${color}`}
    >
      <span
        className={`w-1.5 h-1.5 rounded-full ${
          stock > 10
            ? 'bg-emerald-500'
            : stock > 3
              ? 'bg-amber-500'
              : 'bg-red-500'
        }`}
      />
      {stock}
    </span>
  );
}

// ── Edit Modal ──────────────────────────────────────────────

interface EditModalProps {
  product: Product;
  onClose: () => void;
  onSaved: (updatedProduct: Product) => void;
}

function EditModal({ product, onClose, onSaved }: EditModalProps) {
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const { showToast } = useToast();

  // Form state for simple products
  const [price, setPrice] = useState(product.price);
  const [stock, setStock] = useState(product.stock);

  // Form state for variant products
  const [variantPrices, setVariantPrices] = useState<Record<number, number>>(
    () =>
      Object.fromEntries(
        product.variants.map((v) => [v.model_id, v.price])
      )
  );
  const [variantStocks, setVariantStocks] = useState<Record<number, number>>(
    () =>
      Object.fromEntries(
        product.variants.map((v) => [v.model_id, v.stock])
      )
  );

  const handleSaveStock = async () => {
    setSaving(true);
    setError(null);
    try {
      let updatedProduct: Product;
      if (product.has_variants) {
        const stockList = product.variants.map((v) => ({
          model_id: v.model_id,
          seller_stock: [{ stock: variantStocks[v.model_id] ?? v.stock }],
        }));
        const priceList = product.variants.map((v) => ({
          model_id: v.model_id,
          original_price: variantPrices[v.model_id] ?? v.price,
        }));
        updatedProduct = await updateStockAndPrice(product.id, stockList, priceList, 'local');
      } else {
        updatedProduct = await updateStockAndPrice(
          product.id,
          [{ seller_stock: [{ stock }] }],
          [{ original_price: price }],
          'local',
        );
      }
      showToast('Saved locally. Use Push selected to sync to the marketplace.', 'success');
      onSaved(updatedProduct);
      onClose();
    } catch (e: any) {
      const msg = e?.response?.data?.message || e?.message || 'Failed to save.';
      setError(msg);
      showToast(msg, 'error');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 animate-fade-in">
      <div
        className="fixed inset-0 bg-ink-950/40 backdrop-blur-sm"
        onClick={onClose}
      />
      <div className="relative bg-white rounded-2xl shadow-xl border border-ink-100 w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div className="px-6 py-5 border-b border-ink-100 flex items-center justify-between">
          <h3 className="font-heading text-lg font-semibold text-ink-950">
            Edit — {product.item_name || `Item #${product.item_id}`}
          </h3>
          <button
            onClick={onClose}
            className="text-ink-400 hover:text-ink-700 transition text-xl leading-none"
          >
            ✕
          </button>
        </div>

        <div className="p-6 space-y-6">
          {error && (
            <div className="p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">
              {error}
            </div>
          )}

          {product.has_variants ? (
            /* ── Variant mode ── */
            <div className="space-y-4">
              <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-400 font-mono">
                <span>SKU: {product.item_sku || 'None'}</span>
                <span>Item ID: {product.item_id}</span>
              </div>
              <p className="text-sm text-ink-500 font-medium">
                This product has variants. Edit each below.
              </p>
              {product.variants.map((v) => (
                <div
                  key={v.model_id}
                  className="p-4 rounded-xl bg-ink-50 border border-ink-100 space-y-3"
                >
                  <div className="flex justify-between items-center">
                    <p className="font-medium text-ink-900 text-sm">
                      {v.name || `Variant #${v.model_id}`}
                    </p>
                    <span className="text-xs text-ink-400 font-mono">
                      SKU: {v.model_sku || 'None'}
                    </span>
                  </div>
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="block text-xs font-medium text-ink-500 mb-1">
                        Price
                      </label>
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={variantPrices[v.model_id] ?? v.price}
                        onChange={(e) =>
                          setVariantPrices((prev) => ({
                            ...prev,
                            [v.model_id]: parseFloat(e.target.value) || 0,
                          }))
                        }
                        className="w-full px-3 py-2 rounded-xl border border-ink-200 text-sm focus:outline-none focus:ring-2 focus:ring-clay-400/40 focus:border-clay-400"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-ink-500 mb-1">
                        Stock
                      </label>
                      <input
                        type="number"
                        min="0"
                        value={variantStocks[v.model_id] ?? v.stock}
                        onChange={(e) =>
                          setVariantStocks((prev) => ({
                            ...prev,
                            [v.model_id]: parseInt(e.target.value) || 0,
                          }))
                        }
                        className="w-full px-3 py-2 rounded-xl border border-ink-200 text-sm focus:outline-none focus:ring-2 focus:ring-clay-400/40 focus:border-clay-400"
                      />
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            /* ── Simple product mode ── */
            <div className="space-y-4">
              <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-400 font-mono">
                <span>SKU: {product.item_sku || 'None'}</span>
                <span>Item ID: {product.item_id}</span>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-ink-700 mb-1.5">
                    Price
                  </label>
                  <input
                    type="number"
                    step="0.01"
                    min="0"
                    value={price}
                    onChange={(e) => setPrice(parseFloat(e.target.value) || 0)}
                    className="w-full px-3 py-2.5 rounded-xl border border-ink-200 text-sm focus:outline-none focus:ring-2 focus:ring-clay-400/40 focus:border-clay-400"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-ink-700 mb-1.5">
                    Stock
                  </label>
                  <input
                    type="number"
                    min="0"
                    value={stock}
                    onChange={(e) => setStock(parseInt(e.target.value) || 0)}
                    className="w-full px-3 py-2.5 rounded-xl border border-ink-200 text-sm focus:outline-none focus:ring-2 focus:ring-clay-400/40 focus:border-clay-400"
                  />
                </div>
              </div>
            </div>
          )}

          <div className="flex gap-3 pt-2">
            <button
              onClick={onClose}
              disabled={saving}
              className="flex-1 px-4 py-2.5 text-sm font-semibold rounded-xl border border-ink-200 text-ink-700 hover:bg-ink-50 transition disabled:opacity-50"
            >
              Cancel
            </button>
            <button
              onClick={handleSaveStock}
              disabled={saving}
              className="flex-1 px-4 py-2.5 text-sm font-semibold bg-ink-950 text-white rounded-xl hover:bg-ink-800 disabled:opacity-50 transition active:scale-[0.97]"
            >
              {saving ? 'Saving…' : 'Save'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

// ── Consolidated Edit Modal (cross-store SKU group) ───────────

interface ConsolidatedEditModalProps {
  group: GroupedProductGroup;
  stores: { id: number; shop_name: string | null }[];
  onClose: () => void;
  onSaved: () => void;
}

function ConsolidatedEditModal({ group, stores, onClose, onSaved }: ConsolidatedEditModalProps) {
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const { showToast } = useToast();

  // Per-listing state (product.id) — same store can have multiple SKU-linked listings
  const [prices, setPrices] = useState<Record<number, number>>(() =>
    Object.fromEntries(group.products.map((p) => [p.id, Number(p.price)]))
  );
  const [stocks, setStocks] = useState<Record<number, number>>(() =>
    Object.fromEntries(group.products.map((p) => [p.id, p.stock]))
  );

  const [syncStock, setSyncStock] = useState(false);
  const [sharedStock, setSharedStock] = useState(group.products[0]?.stock ?? 0);

  const [syncPrice, setSyncPrice] = useState(false);
  const [sharedPrice, setSharedPrice] = useState(Number(group.products[0]?.price ?? 0));

  const handleSave = async () => {
    setSaving(true);
    setError(null);
    try {
      if (syncStock || syncPrice) {
        if (syncStock) {
          await updateStockBySku(group.sku, sharedStock, 'local');
        } else {
          for (const product of group.products) {
            const newStock = stocks[product.id];
            const stockChanged = typeof newStock === 'number' && newStock !== product.stock;
            if (stockChanged) {
              await updateStockAndPrice(product.id, [{ seller_stock: [{ stock: newStock }] }], undefined, 'local');
            }
          }
        }

        if (syncPrice) {
          await updatePriceBySku(group.sku, sharedPrice, 'local');
        } else {
          for (const product of group.products) {
            const newPrice = prices[product.id];
            if (typeof newPrice === 'number' && newPrice !== Number(product.price)) {
              await updateStockAndPrice(product.id, undefined, [{ original_price: newPrice }], 'local');
            }
          }
        }

        const parts: string[] = [];
        if (syncStock) parts.push('stock');
        if (syncPrice) parts.push('price');
        showToast(
          `Saved ${parts.join(' & ')} locally for ${group.products.length} listing(s). Push selected to sync.`,
          'success',
        );
      } else {
        for (const product of group.products) {
          const newPrice = prices[product.id];
          const newStock = stocks[product.id];
          const stockChanged = typeof newStock === 'number' && newStock !== product.stock;
          const priceChanged = typeof newPrice === 'number' && newPrice !== Number(product.price);

          if (stockChanged || priceChanged) {
            await updateStockAndPrice(
              product.id,
              stockChanged ? [{ seller_stock: [{ stock: newStock }] }] : undefined,
              priceChanged ? [{ original_price: newPrice }] : undefined,
              'local',
            );
          }
        }
        showToast('Saved locally. Use Push selected to sync to the marketplace.', 'success');
      }

      onSaved();
      onClose();
    } catch (e: any) {
      const msg = e?.response?.data?.message || e?.message || 'Failed to save.';
      setError(msg);
      showToast(msg, 'error');
    } finally {
      setSaving(false);
    }
  };

  const storeMap = Object.fromEntries(stores.map((s) => [s.id, s]));

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 animate-fade-in">
      <div className="fixed inset-0 bg-ink-950/40 backdrop-blur-sm" onClick={onClose} />
      <div className="relative bg-white rounded-2xl shadow-xl border border-ink-100 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div className="px-6 py-5 border-b border-ink-100 flex items-center justify-between gap-3">
          <h3 className="font-heading text-lg font-semibold text-ink-950">
            Edit — SKU: {group.sku}
          </h3>
          <span className="text-xs text-ink-400 font-medium shrink-0">
            {group.products.length} listing{group.products.length !== 1 ? 's' : ''}
          </span>
          <button onClick={onClose} className="text-ink-400 hover:text-ink-700 transition text-xl leading-none">✕</button>
        </div>

        <div className="p-6 space-y-6">
          {error && (
            <div className="p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">{error}</div>
          )}

          <label className="flex items-center gap-2 text-sm font-medium text-ink-700 cursor-pointer">
            <input
              type="checkbox"
              checked={syncStock}
              onChange={(e) => setSyncStock(e.target.checked)}
              className="rounded border-ink-300"
            />
            Set all listings with this SKU to the same stock
          </label>

          {syncStock && (
            <div className="p-4 rounded-xl bg-ink-50 border border-ink-100">
              <label className="block text-xs font-medium text-ink-500 mb-1">Stock (all listings)</label>
              <input
                type="number"
                min="0"
                value={sharedStock}
                onChange={(e) => setSharedStock(parseInt(e.target.value) || 0)}
                className="w-full max-w-xs px-3 py-2.5 rounded-xl border border-ink-200 text-sm focus:outline-none focus:ring-2 focus:ring-clay-400/40 focus:border-clay-400"
              />
            </div>
          )}

          <label className="flex items-center gap-2 text-sm font-medium text-ink-700 cursor-pointer">
            <input
              type="checkbox"
              checked={syncPrice}
              onChange={(e) => setSyncPrice(e.target.checked)}
              className="rounded border-ink-300"
            />
            Set all listings with this SKU to the same price
          </label>

          {syncPrice && (
            <div className="p-4 rounded-xl bg-ink-50 border border-ink-100 space-y-2">
              <label className="block text-xs font-medium text-ink-500 mb-1">Price (all listings)</label>
              <input
                type="number"
                step="0.01"
                min="0"
                value={sharedPrice}
                onChange={(e) => setSharedPrice(parseFloat(e.target.value) || 0)}
                className="w-full max-w-xs px-3 py-2.5 rounded-xl border border-ink-200 text-sm focus:outline-none focus:ring-2 focus:ring-clay-400/40 focus:border-clay-400"
              />
              <p className="text-xs text-ink-400">
                Applies to every listing sharing this SKU, including different products on the same store.
              </p>
            </div>
          )}

          {group.products.map((product) => {
            const store = storeMap[product.store_id];
            return (
              <div key={product.id} className="p-4 rounded-xl bg-ink-50 border border-ink-100 space-y-3">
                <div className="space-y-1">
                  <div className="flex justify-between items-start gap-3">
                    <p className="font-medium text-ink-900 text-sm">
                      {store?.shop_name || `Store #${product.store_id}`}
                    </p>
                    <span className="text-xs text-ink-400 text-right">{product.item_name}</span>
                  </div>
                  <p className="text-xs text-ink-400 font-mono">Item ID: {product.item_id}</p>
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-medium text-ink-500 mb-1">Price</label>
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      value={syncPrice ? sharedPrice : (prices[product.id] ?? Number(product.price))}
                      onChange={(e) =>
                        setPrices((prev) => ({ ...prev, [product.id]: parseFloat(e.target.value) || 0 }))
                      }
                      className="w-full px-3 py-2 rounded-xl border border-ink-200 text-sm focus:outline-none focus:ring-2 focus:ring-clay-400/40 focus:border-clay-400"
                      disabled={saving || syncPrice}
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-ink-500 mb-1">Stock</label>
                    <input
                      type="number"
                      min="0"
                      value={syncStock ? sharedStock : (stocks[product.id] ?? product.stock)}
                      onChange={(e) =>
                        setStocks((prev) => ({ ...prev, [product.id]: parseInt(e.target.value) || 0 }))
                      }
                      className="w-full px-3 py-2 rounded-xl border border-ink-200 text-sm focus:outline-none focus:ring-2 focus:ring-clay-400/40 focus:border-clay-400"
                      disabled={saving || syncStock}
                    />
                  </div>
                </div>
              </div>
            );
          })}

          <div className="flex gap-3 pt-2">
            <button onClick={onClose} disabled={saving}
              className="flex-1 px-4 py-2.5 text-sm font-semibold rounded-xl border border-ink-200 text-ink-700 hover:bg-ink-50 transition disabled:opacity-50">
              Cancel
            </button>
            <button onClick={handleSave} disabled={saving}
              className="flex-1 px-4 py-2.5 text-sm font-semibold bg-ink-950 text-white rounded-xl hover:bg-ink-800 disabled:opacity-50 transition active:scale-[0.97]">
              {saving ? 'Saving…' : `Save × ${group.products.length}`}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

// ── Main Stock Page ─────────────────────────────────────────

export default function Stock() {
  const { stores, selectedStoreId } = useStore();
  const { showToast } = useToast();

  const [rows, setRows] = useState<StockRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [syncing, setSyncing] = useState(false);
  const [pushing, setPushing] = useState(false);
  const [selectedKeys, setSelectedKeys] = useState<Set<string>>(new Set());
  const [editingProduct, setEditingProduct] = useState<Product | null>(null);
  const [editingGroup, setEditingGroup] = useState<GroupedProductGroup | null>(null);

  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const [stockHealth, setStockHealth] = useState<StockHealthFilter>('');
  const [linkStatus, setLinkStatus] = useState<LinkStatusFilter>('');
  const [syncState, setSyncState] = useState<SyncStateFilter>('');
  const [sortPreset, setSortPreset] = useState<SortPreset>('name');
  const [sortBy, setSortBy] = useState<
    'sku' | 'item_name' | 'price' | 'stock' | 'stores' | 'synced_at'
  >('item_name');
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc');
  const [page, setPage] = useState(1);

  useEffect(() => {
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current);
    }
    searchTimeoutRef.current = setTimeout(() => {
      setDebouncedSearch(search);
      setPage(1);
    }, 300);

    return () => {
      if (searchTimeoutRef.current) {
        clearTimeout(searchTimeoutRef.current);
      }
    };
  }, [search]);

  const fetchProducts = async () => {
    setLoading(true);
    setError(null);
    try {
      const preset = SORT_PRESETS.find((p) => p.value === sortPreset) ?? SORT_PRESETS[0];
      const params: {
        store_id?: number;
        search?: string;
        stock_health?: string;
        link_status?: string;
        sync_state?: string;
        sort_by?: string;
        sort_dir?: string;
      } = {
        sort_by: preset.apiSortBy,
        sort_dir: preset.apiSortDir,
      };
      if (selectedStoreId !== null) params.store_id = selectedStoreId;
      if (debouncedSearch) params.search = debouncedSearch;
      if (stockHealth !== '') params.stock_health = stockHealth;
      if (linkStatus !== '') params.link_status = linkStatus;
      if (syncState !== '') params.sync_state = syncState;

      const res = await getProductsGrouped(params);
      setRows(buildStockRows(res.grouped ?? [], res.ungrouped ?? []));
    } catch (e: any) {
      setError(e?.response?.data?.message || 'Failed to load products.');
      setRows([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProducts();
    setSelectedKeys(new Set());
  }, [selectedStoreId, debouncedSearch, stockHealth, linkStatus, syncState, sortPreset]);

  useEffect(() => {
    setPage(1);
  }, [selectedStoreId, debouncedSearch, stockHealth, linkStatus, syncState, sortPreset]);

  const productIdsForRow = (row: StockRow): number[] => {
    if (row.kind === 'sku') {
      return row.group.products.map((p) => p.id);
    }
    return [row.product.id];
  };

  const selectedProductIds = useMemo(() => {
    const ids = new Set<number>();
    for (const row of rows) {
      if (selectedKeys.has(row.key)) {
        productIdsForRow(row).forEach((id) => ids.add(id));
      }
    }
    return Array.from(ids);
  }, [rows, selectedKeys]);

  const toggleRow = (key: string) => {
    setSelectedKeys((prev) => {
      const next = new Set(prev);
      if (next.has(key)) {
        next.delete(key);
      } else {
        next.add(key);
      }
      return next;
    });
  };

  const handlePull = async (selectedOnly: boolean) => {
    if (selectedOnly && selectedProductIds.length === 0) {
      showToast('Select at least one product to pull.', 'error');
      return;
    }
    setSyncing(true);
    showToast(
      selectedOnly
        ? `Queuing pull for ${selectedProductIds.length} selected product(s)…`
        : selectedStoreId
          ? 'Queuing pull for this store…'
          : 'Queuing staggered pull for all stores…',
      'info'
    );
    try {
      const params: { store_id?: number; product_ids?: number[] } = {};
      if (selectedOnly) {
        params.product_ids = selectedProductIds;
      } else if (selectedStoreId !== null) {
        params.store_id = selectedStoreId;
      }
      const res = await pullProducts(params);
      const count = res.queued?.length ?? 0;
      showToast(
        count > 0
          ? `Pull queued for ${count} store job(s). Refresh in a few minutes.`
          : 'Nothing queued — check store links on Dashboard.',
        count > 0 ? 'success' : 'error'
      );
    } catch {
      showToast('Failed to queue pull. Check store connections on Dashboard.', 'error');
    } finally {
      setSyncing(false);
    }
  };

  const handlePushSelected = async () => {
    if (selectedProductIds.length === 0) {
      showToast('Select at least one product to push stock.', 'error');
      return;
    }
    setPushing(true);
    showToast(`Queuing push for ${selectedProductIds.length} product(s)…`, 'info');
    try {
      const res = await pushProducts(selectedProductIds);
      const count = res.queued?.length ?? 0;
      showToast(
        count > 0
          ? `Push queued for ${count} product(s).`
          : 'Nothing queued.',
        count > 0 ? 'success' : 'error'
      );
      if (count > 0) {
        await fetchProducts();
      }
    } catch {
      showToast('Failed to push selected products.', 'error');
    } finally {
      setPushing(false);
    }
  };

  const sortedRows = useMemo(() => {
    const next = [...rows];
    const dir = sortDir === 'asc' ? 1 : -1;
    next.sort((a, b) => {
      let cmp = 0;
      if (sortBy === 'sku') {
        cmp = a.sku.localeCompare(b.sku, undefined, { sensitivity: 'base' });
        if (cmp === 0) {
          cmp = a.itemName.localeCompare(b.itemName, undefined, { sensitivity: 'base' });
        }
      } else if (sortBy === 'item_name') {
        cmp = a.itemName.localeCompare(b.itemName, undefined, { sensitivity: 'base' });
      } else if (sortBy === 'price') {
        cmp = a.minPrice - b.minPrice;
      } else if (sortBy === 'stock') {
        cmp = a.minStock - b.minStock;
      } else if (sortBy === 'stores') {
        cmp = a.storeCount - b.storeCount;
      } else if (sortBy === 'synced_at') {
        cmp = a.syncedAt - b.syncedAt;
      }
      return cmp * dir;
    });
    return next;
  }, [rows, sortBy, sortDir]);

  const lastPage = Math.max(1, Math.ceil(sortedRows.length / PAGE_SIZE));
  const pageRows = useMemo(() => {
    const safePage = Math.min(page, lastPage);
    const start = (safePage - 1) * PAGE_SIZE;
    return sortedRows.slice(start, start + PAGE_SIZE);
  }, [sortedRows, page, lastPage]);

  const togglePageSelection = () => {
    const pageKeys = pageRows.map((r) => r.key);
    const allSelected = pageKeys.length > 0 && pageKeys.every((k) => selectedKeys.has(k));
    setSelectedKeys((prev) => {
      const next = new Set(prev);
      if (allSelected) {
        pageKeys.forEach((k) => next.delete(k));
      } else {
        pageKeys.forEach((k) => next.add(k));
      }
      return next;
    });
  };
  useEffect(() => {
    if (page > lastPage) {
      setPage(lastPage);
    }
  }, [page, lastPage]);

  const handleSort = (column: typeof sortBy) => {
    if (sortBy === column) {
      setSortDir((prev) => (prev === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortBy(column);
      setSortDir(column === 'synced_at' ? 'desc' : 'asc');
    }
    if (column === 'item_name') setSortPreset('name');
    else if (column === 'stock') setSortPreset('stock');
    else if (column === 'price') setSortPreset('price');
    else if (column === 'synced_at') setSortPreset('synced');
    setPage(1);
  };

  const handleEditRow = (row: StockRow) => {
    if (row.kind === 'sku') {
      if (row.group.products.length > 1) {
        setEditingGroup(row.group);
        return;
      }
      const only = row.group.products[0];
      if (only) {
        setEditingProduct(only);
      }
      return;
    }
    setEditingProduct(row.product);
  };

  const handleSaved = (_updatedProduct: Product) => {
    void fetchProducts();
  };

  const sortMark = (column: typeof sortBy) =>
    sortBy === column ? (sortDir === 'asc' ? ' ▲' : ' ▼') : '';

  return (
    <PageShell>
      <div className="max-w-6xl space-y-8 animate-fade-in">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h2 className="font-heading text-2xl font-bold text-ink-950">
              Stock & Price
            </h2>
            <p className="text-ink-400 text-sm mt-1">
              Pull marketplace data or push local stock changes. Edits save locally (*); use Push selected to sync.
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2 justify-end">
            <button
              onClick={() => handlePull(true)}
              disabled={syncing || pushing || selectedProductIds.length === 0}
              className="inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-semibold border border-ink-200 text-ink-800 rounded-xl hover:bg-ink-50 disabled:opacity-50 transition active:scale-[0.97]"
            >
              Pull selected
            </button>
            <button
              onClick={handlePushSelected}
              disabled={pushing || syncing || selectedProductIds.length === 0}
              className="inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-semibold border border-ink-200 text-ink-800 rounded-xl hover:bg-ink-50 disabled:opacity-50 transition active:scale-[0.97]"
            >
              {pushing && <Spinner />}
              {pushing ? 'Pushing…' : 'Push selected'}
            </button>
            <button
              onClick={() => handlePull(false)}
              disabled={syncing || pushing}
              className="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold bg-ink-950 text-white rounded-xl hover:bg-ink-800 disabled:opacity-50 transition active:scale-[0.97]"
            >
              {syncing && <Spinner />}
              {syncing
                ? 'Queuing…'
                : selectedStoreId
                  ? 'Pull this store'
                  : 'Pull all stores'}
            </button>
          </div>
        </div>

        <div className="flex flex-col gap-3 bg-white p-4 rounded-2xl border border-ink-100 shadow-sm">
          <div className="flex flex-col sm:flex-row sm:items-center gap-3 flex-wrap">
            <input
              type="text"
              placeholder="Name, SKU, or item ID…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full max-w-md px-4 py-2.5 rounded-xl border border-ink-200 text-sm focus:outline-none focus:ring-2 focus:ring-clay-400/40 focus:border-clay-400"
            />
            <div className="inline-flex items-center rounded-xl border border-ink-200 bg-white p-0.5 flex-wrap">
              {STOCK_HEALTH_CHIPS.map((chip) => (
                <button
                  key={chip.value || 'all'}
                  type="button"
                  onClick={() => setStockHealth(chip.value)}
                  className={`px-2.5 py-1.5 text-xs font-semibold rounded-lg transition ${
                    stockHealth === chip.value
                      ? chip.value === 'out'
                        ? 'bg-red-600 text-white'
                        : chip.value === 'low'
                          ? 'bg-amber-500 text-white'
                          : 'bg-ink-950 text-white'
                      : 'text-ink-600 hover:bg-ink-50'
                  }`}
                >
                  {chip.label}
                </button>
              ))}
            </div>
            <button
              type="button"
              onClick={() => setSyncState((prev) => (prev === 'unsynced' ? '' : 'unsynced'))}
              className={`px-3 py-2.5 rounded-xl border text-xs font-semibold transition ${
                syncState === 'unsynced'
                  ? 'bg-orange-500 text-white border-orange-500'
                  : 'bg-white text-ink-600 border-ink-200 hover:bg-ink-50'
              }`}
              title="Show only listings edited locally and not yet pushed"
            >
              Unsynced only
            </button>
            <select
              value={linkStatus}
              onChange={(e) => setLinkStatus(e.target.value as LinkStatusFilter)}
              className="px-3 py-2.5 rounded-xl border border-ink-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-clay-400/40 focus:border-clay-400"
              title="Catalog view"
            >
              {LINK_STATUS_OPTIONS.map((o) => (
                <option key={o.value || 'all'} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
            <select
              value={sortPreset}
              onChange={(e) => {
                const next = e.target.value as SortPreset;
                setSortPreset(next);
                const preset = SORT_PRESETS.find((p) => p.value === next);
                if (preset) {
                  setSortBy(preset.sortBy);
                  setSortDir(preset.sortDir);
                }
              }}
              className="px-3 py-2.5 rounded-xl border border-ink-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-clay-400/40 focus:border-clay-400"
              title="Sort"
            >
              {SORT_PRESETS.map((p) => (
                <option key={p.value} value={p.value}>
                  {p.label}
                </option>
              ))}
            </select>
            {(debouncedSearch || stockHealth || linkStatus || syncState) && (
              <button
                onClick={() => {
                  setSearch('');
                  setStockHealth('');
                  setLinkStatus('');
                  setSyncState('');
                }}
                className="text-xs font-semibold text-clay-600 hover:text-clay-800"
              >
                Clear filters
              </button>
            )}
          </div>
        </div>

        {loading ? (
          <div className="bg-white rounded-2xl border border-ink-100 shadow-sm overflow-hidden">
            <div className="divide-y divide-ink-50">
              {[1, 2, 3, 4].map((i) => (
                <div key={i} className="px-6 py-5 animate-pulse">
                  <div className="flex items-center gap-4">
                    <div className="w-10 h-10 rounded-xl bg-ink-100" />
                    <div className="flex-1 space-y-2">
                      <div className="h-4 bg-ink-100 rounded w-48" />
                      <div className="h-3 bg-ink-50 rounded w-32" />
                    </div>
                    <div className="h-6 bg-ink-100 rounded-full w-16" />
                  </div>
                </div>
              ))}
            </div>
          </div>
        ) : error ? (
          <div className="bg-white rounded-2xl border border-ink-100 shadow-sm p-12 text-center">
            <div className="w-14 h-14 rounded-2xl bg-red-50 flex items-center justify-center mx-auto mb-4">
              <span className="text-2xl text-red-500">!</span>
            </div>
            <h4 className="font-heading text-base font-semibold text-ink-950 mb-1">
              Failed to load products
            </h4>
            <p className="text-sm text-ink-400 max-w-sm mx-auto mb-6">
              {error}
            </p>
            <button
              onClick={fetchProducts}
              className="px-5 py-2.5 text-sm font-semibold bg-ink-950 text-white rounded-xl hover:bg-ink-800 transition active:scale-[0.97]"
            >
              Retry
            </button>
          </div>
        ) : sortedRows.length === 0 ? (
          <div className="bg-white rounded-2xl border border-ink-100 shadow-sm p-12 text-center">
            <div className="w-14 h-14 rounded-2xl bg-clay-100 flex items-center justify-center mx-auto mb-4">
              <span className="text-2xl text-clay-500">◇</span>
            </div>
            <h4 className="font-heading text-base font-semibold text-ink-950 mb-1">
              No products found
            </h4>
            <p className="text-sm text-ink-400 max-w-sm mx-auto mb-6">
              Try adjusting your search, or pull products from a linked marketplace.
            </p>
            <button
              onClick={() => handlePull(false)}
              disabled={syncing}
              className="inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm font-semibold bg-ink-950 text-white rounded-xl hover:bg-ink-800 disabled:opacity-50 transition active:scale-[0.97]"
            >
              {syncing && <Spinner />}
              {syncing ? 'Queuing…' : selectedStoreId ? 'Pull this store' : 'Pull all stores'}
            </button>
          </div>
        ) : (
          <div className="space-y-4">
            <div className="bg-white rounded-2xl border border-ink-100 shadow-sm overflow-hidden">
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead>
                    <tr className="border-b border-ink-100 bg-ink-50/50">
                      <th className="px-4 py-4 w-10">
                        <input
                          type="checkbox"
                          aria-label="Select page"
                          checked={
                            pageRows.length > 0 &&
                            pageRows.every((r) => selectedKeys.has(r.key))
                          }
                          onChange={togglePageSelection}
                          className="rounded border-ink-300"
                        />
                      </th>
                      <th
                        onClick={() => handleSort('sku')}
                        className="text-left px-6 py-4 text-xs font-semibold text-ink-500 uppercase tracking-wider cursor-pointer hover:bg-ink-100 transition select-none"
                      >
                        SKU{sortMark('sku')}
                      </th>
                      <th
                        onClick={() => handleSort('stores')}
                        className="text-left px-6 py-4 text-xs font-semibold text-ink-500 uppercase tracking-wider cursor-pointer hover:bg-ink-100 transition select-none hidden sm:table-cell"
                      >
                        Stores{sortMark('stores')}
                      </th>
                      <th
                        onClick={() => handleSort('price')}
                        className="text-left px-6 py-4 text-xs font-semibold text-ink-500 uppercase tracking-wider cursor-pointer hover:bg-ink-100 transition select-none"
                      >
                        Price{sortMark('price')}
                      </th>
                      <th
                        onClick={() => handleSort('stock')}
                        className="text-left px-6 py-4 text-xs font-semibold text-ink-500 uppercase tracking-wider cursor-pointer hover:bg-ink-100 transition select-none"
                      >
                        Stock{sortMark('stock')}
                      </th>
                      <th className="text-right px-6 py-4" />
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-ink-50">
                    {pageRows.map((row) => (
                      <tr key={row.key} className="hover:bg-ink-50/50 transition">
                        <td className="px-4 py-4">
                          <input
                            type="checkbox"
                            aria-label={`Select ${row.itemName}`}
                            checked={selectedKeys.has(row.key)}
                            onChange={() => toggleRow(row.key)}
                            className="rounded border-ink-300"
                          />
                        </td>
                        <td className="px-6 py-4">
                          {row.kind === 'sku' ? (
                            <>
                              <p className="font-mono font-semibold text-ink-900 text-sm">
                                {row.sku}
                                {row.needsPush && (
                                  <span className="text-orange-600" title="Edited locally — not yet pushed">
                                    {' '}*
                                  </span>
                                )}
                              </p>
                              <p className="text-xs text-ink-400 mt-0.5">
                                {row.itemName}
                              </p>
                            </>
                          ) : (
                            <>
                              <p className="font-medium text-ink-900 text-sm">
                                {row.itemName}
                                {row.needsPush && (
                                  <span className="text-orange-600" title="Edited locally — not yet pushed">
                                    {' '}*
                                  </span>
                                )}
                              </p>
                              <p className="text-xs text-ink-400 mt-0.5">
                                No SKU · ID {row.product.item_id}
                              </p>
                            </>
                          )}
                        </td>
                        <td className="px-6 py-4 hidden sm:table-cell">
                          <span className="text-xs font-medium text-ink-500">
                            {row.storeCount} store{row.storeCount !== 1 ? 's' : ''}
                          </span>
                        </td>
                        <td className="px-6 py-4">
                          <span className="font-semibold text-ink-900 text-sm font-mono">
                            {formatPriceDisplay(row.minPrice, row.maxPrice)}
                          </span>
                        </td>
                        <td className="px-6 py-4">
                          {row.minStock === row.maxStock ? (
                            <StockBadge stock={row.minStock} />
                          ) : (
                            <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border bg-ink-50 text-ink-700 border-ink-200 font-mono">
                              {formatStockDisplay(row.minStock, row.maxStock)}
                            </span>
                          )}
                        </td>
                        <td className="px-6 py-4 text-right">
                          <button
                            onClick={() => handleEditRow(row)}
                            className="px-3 py-1.5 text-xs font-semibold rounded-lg border border-ink-200 text-ink-700 hover:bg-ink-100 transition"
                          >
                            Edit
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="px-6 py-3 bg-ink-50/30 border-t border-ink-50 flex items-center justify-between text-xs text-ink-400">
                <span>
                  Showing {pageRows.length} of {sortedRows.length} SKUs / products
                </span>
                <span>
                  {selectedStoreId !== null
                    ? (stores.find((s) => s.id === selectedStoreId)?.shop_name
                      || `Store #${selectedStoreId}`)
                    : 'all stores'}
                </span>
              </div>
            </div>

            {lastPage > 1 && (
              <div className="flex items-center justify-between bg-white px-4 py-3 rounded-2xl border border-ink-100 shadow-sm">
                <button
                  disabled={page <= 1}
                  onClick={() => setPage((prev) => prev - 1)}
                  className="px-3 py-1.5 text-xs font-semibold rounded-lg border border-ink-200 text-ink-700 hover:bg-ink-50 disabled:opacity-50 transition"
                >
                  Previous
                </button>
                <span className="text-xs font-medium text-ink-500">
                  Page {Math.min(page, lastPage)} of {lastPage}
                </span>
                <button
                  disabled={page >= lastPage}
                  onClick={() => setPage((prev) => prev + 1)}
                  className="px-3 py-1.5 text-xs font-semibold rounded-lg border border-ink-200 text-ink-700 hover:bg-ink-50 disabled:opacity-50 transition"
                >
                  Next
                </button>
              </div>
            )}
          </div>
        )}

        {editingProduct && (
          <EditModal
            product={editingProduct}
            onClose={() => setEditingProduct(null)}
            onSaved={handleSaved}
          />
        )}

        {editingGroup && (
          <ConsolidatedEditModal
            group={editingGroup}
            stores={stores}
            onClose={() => setEditingGroup(null)}
            onSaved={() => fetchProducts()}
          />
        )}
      </div>
    </PageShell>
  );
}
