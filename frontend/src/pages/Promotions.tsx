import { useCallback, useEffect, useMemo, useState, type FormEvent } from 'react';
import { PageShell } from '../components/PageShell';
import PromotionProductPicker, {
  type PromoItemSelection,
  type PromoSkuSelection,
} from '../components/PromotionProductPicker';
import Spinner from '../components/Spinner';
import { useStore } from '../context/StoreContext';
import { useToast } from '../context/ToastContext';
import {
  activateLazadaVoucher,
  addShopeeDiscountItems,
  createLazadaVoucher,
  createShopeeDiscount,
  createShopeeVoucher,
  deactivateLazadaVoucher,
  getPromotions,
  updateShopeeDiscount,
  updateShopeeVoucher,
  type MarketplaceType,
  type PromotionSummary,
  type PromotionType,
} from '../api/client';

type PlatformFilter = MarketplaceType | 'all';
type CreateKind = 'shopee_discount' | 'shopee_voucher' | 'lazada_voucher' | null;
type StatusFilter = 'active' | 'all' | 'finished';
type SortColumn = 'name' | 'platform' | 'type' | 'status' | 'start_at' | 'end_at' | 'store';

const PLATFORM_TABS: Array<{ label: string; value: PlatformFilter }> = [
  { label: 'All', value: 'all' },
  { label: 'Shopee', value: 'shopee' },
  { label: 'Lazada', value: 'lazada' },
];

const STATUS_TABS: Array<{ label: string; value: StatusFilter }> = [
  { label: 'Active / upcoming', value: 'active' },
  { label: 'All', value: 'all' },
  { label: 'Finished', value: 'finished' },
];

function isFinishedStatus(status: string): boolean {
  const s = status.toUpperCase();
  return s === 'FINISH' || s === 'EXPIRED' || s === 'expired';
}

function isoToMs(iso?: string | null): number {
  if (!iso) return 0;
  const ms = Date.parse(iso);
  return Number.isFinite(ms) ? ms : 0;
}

function hoursFromNowUnix(hours: number): number {
  return Math.floor(Date.now() / 1000) + Math.round(hours * 3600);
}

function apiErrorMessage(err: unknown): string {
  const ax = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
  const errors = ax.response?.data?.errors;
  if (errors) {
    const first = Object.values(errors)[0];
    if (Array.isArray(first) && first[0]) return first[0];
  }
  return ax.response?.data?.message || (err instanceof Error ? err.message : 'Request failed');
}

export default function Promotions() {
  const { stores, selectedStoreId, filteredStores } = useStore();
  const { showToast } = useToast();
  const [platform, setPlatform] = useState<PlatformFilter>('all');
  const [typeFilter, setTypeFilter] = useState<PromotionType | ''>('');
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('active');
  const [sortBy, setSortBy] = useState<SortColumn>('start_at');
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('desc');
  const [items, setItems] = useState<PromotionSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [createKind, setCreateKind] = useState<CreateKind>(null);

  const storeOptions = useMemo(() => {
    const list = filteredStores.length ? filteredStores : stores;
    if (platform === 'all') return list;
    return list.filter((s) => s.platform === platform);
  }, [filteredStores, stores, platform]);

  const visibleItems = useMemo(() => {
    const filtered = items.filter((row) => {
      if (statusFilter === 'all') return true;
      const finished = isFinishedStatus(row.status);
      return statusFilter === 'finished' ? finished : !finished;
    });
    const dir = sortDir === 'asc' ? 1 : -1;
    return [...filtered].sort((a, b) => {
      let cmp = 0;
      if (sortBy === 'name') {
        cmp = (a.name || '').localeCompare(b.name || '', undefined, { sensitivity: 'base' });
      } else if (sortBy === 'platform') {
        cmp = a.platform.localeCompare(b.platform);
      } else if (sortBy === 'type') {
        cmp = a.type.localeCompare(b.type);
      } else if (sortBy === 'status') {
        cmp = (a.status || '').localeCompare(b.status || '', undefined, { sensitivity: 'base' });
      } else if (sortBy === 'start_at') {
        cmp = isoToMs(a.start_at) - isoToMs(b.start_at);
      } else if (sortBy === 'end_at') {
        cmp = isoToMs(a.end_at) - isoToMs(b.end_at);
      } else if (sortBy === 'store') {
        const sa = a.store_name || String(a.store_id);
        const sb = b.store_name || String(b.store_id);
        cmp = sa.localeCompare(sb, undefined, { sensitivity: 'base' });
      }
      return cmp * dir;
    });
  }, [items, statusFilter, sortBy, sortDir]);

  function handleSort(column: SortColumn) {
    if (sortBy === column) {
      setSortDir((prev) => (prev === 'asc' ? 'desc' : 'asc'));
      return;
    }
    setSortBy(column);
    setSortDir(column === 'start_at' || column === 'end_at' ? 'desc' : 'asc');
  }

  const sortMark = (column: SortColumn) =>
    sortBy === column ? (sortDir === 'asc' ? ' ▲' : ' ▼') : '';

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const data = await getPromotions({
        platform,
        store_id: selectedStoreId || undefined,
        type: typeFilter || undefined,
      });
      setItems(data.filter((row) => row.remote_id != null || row.status === 'error'));
    } catch (err) {
      showToast(apiErrorMessage(err), 'error');
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, [platform, selectedStoreId, typeFilter, showToast]);

  useEffect(() => {
    void load();
  }, [load]);

  async function runAction(key: string, fn: () => Promise<void>) {
    setBusyKey(key);
    try {
      await fn();
      showToast('Done', 'success');
      await load();
    } catch (err) {
      showToast(apiErrorMessage(err), 'error');
    } finally {
      setBusyKey(null);
    }
  }

  return (
    <PageShell>
      <div className="space-y-6">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold text-ink-900">Promotions</h1>
            <p className="mt-1 text-sm text-ink-500">
              Live Shopee discounts/vouchers and Lazada seller vouchers from the marketplace.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              className="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm hover:bg-ink-50"
              onClick={() => void load()}
            >
              Refresh
            </button>
            {(platform === 'all' || platform === 'shopee') && (
              <>
                <button
                  type="button"
                  className="rounded-lg bg-ink-900 px-3 py-2 text-sm text-white hover:bg-ink-800"
                  onClick={() => setCreateKind('shopee_discount')}
                >
                  New Shopee discount
                </button>
                <button
                  type="button"
                  className="rounded-lg bg-ink-900 px-3 py-2 text-sm text-white hover:bg-ink-800"
                  onClick={() => setCreateKind('shopee_voucher')}
                >
                  New Shopee voucher
                </button>
              </>
            )}
            {(platform === 'all' || platform === 'lazada') && (
              <button
                type="button"
                className="rounded-lg bg-ink-900 px-3 py-2 text-sm text-white hover:bg-ink-800"
                onClick={() => setCreateKind('lazada_voucher')}
              >
                New Lazada voucher
              </button>
            )}
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          <div className="inline-flex rounded-lg border border-ink-200 bg-white p-1" role="tablist" aria-label="Platform">
            {PLATFORM_TABS.map((tab) => (
              <button
                key={tab.value}
                type="button"
                role="tab"
                aria-selected={platform === tab.value}
                className={`rounded-md px-3 py-1.5 text-sm ${
                  platform === tab.value ? 'bg-ink-900 text-white' : 'text-ink-600 hover:bg-ink-50'
                }`}
                onClick={() => setPlatform(tab.value)}
              >
                {tab.label}
              </button>
            ))}
          </div>
          <select
            className="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm"
            value={typeFilter}
            onChange={(e) => setTypeFilter(e.target.value as PromotionType | '')}
            aria-label="Promotion type"
          >
            <option value="">All types</option>
            <option value="discount">Discount</option>
            <option value="voucher">Voucher</option>
          </select>
          <div className="inline-flex rounded-lg border border-ink-200 bg-white p-1" role="tablist" aria-label="Status">
            {STATUS_TABS.map((tab) => (
              <button
                key={tab.value}
                type="button"
                role="tab"
                aria-selected={statusFilter === tab.value}
                className={`rounded-md px-3 py-1.5 text-sm ${
                  statusFilter === tab.value ? 'bg-ink-900 text-white' : 'text-ink-600 hover:bg-ink-50'
                }`}
                onClick={() => setStatusFilter(tab.value)}
              >
                {tab.label}
              </button>
            ))}
          </div>
        </div>

        {loading ? (
          <div className="flex justify-center py-16">
            <Spinner />
          </div>
        ) : visibleItems.length === 0 ? (
          <p className="rounded-xl border border-dashed border-ink-200 bg-white px-4 py-10 text-center text-sm text-ink-500">
            No promotions found for this filter.
          </p>
        ) : (
          <div className="overflow-x-auto rounded-xl border border-ink-200 bg-white">
            <table className="min-w-full text-left text-sm">
              <thead className="border-b border-ink-100 bg-ink-50 text-ink-500">
                <tr>
                  {(
                    [
                      { key: 'name', label: 'Name' },
                      { key: 'platform', label: 'Platform' },
                      { key: 'type', label: 'Type' },
                      { key: 'status', label: 'Status' },
                      { key: 'start_at', label: 'Start' },
                      { key: 'end_at', label: 'End' },
                      { key: 'store', label: 'Store' },
                    ] as Array<{ key: SortColumn; label: string }>
                  ).map((col) => (
                    <th key={col.key} className="px-4 py-3 font-medium">
                      <button
                        type="button"
                        className="inline-flex items-center gap-0.5 hover:text-ink-800"
                        onClick={() => handleSort(col.key)}
                      >
                        {col.label}
                        {sortMark(col.key)}
                      </button>
                    </th>
                  ))}
                  <th className="px-4 py-3 font-medium">Actions</th>
                </tr>
              </thead>
              <tbody>
                {visibleItems.map((row, idx) => {
                  const key = `${row.platform}-${row.type}-${row.remote_id ?? 'err'}-${idx}`;
                  const busy = busyKey === key;
                  return (
                    <tr key={key} className="border-b border-ink-50 last:border-0">
                      <td className="px-4 py-3 font-medium text-ink-900">
                        {row.name || (row.error ? 'Error' : '—')}
                        {row.error && (
                          <p className="mt-0.5 text-xs font-normal text-red-600">{row.error}</p>
                        )}
                      </td>
                      <td className="px-4 py-3 capitalize">{row.platform}</td>
                      <td className="px-4 py-3 capitalize">{row.type}</td>
                      <td className="px-4 py-3">{row.status || '—'}</td>
                      <td className="px-4 py-3 text-ink-600 whitespace-nowrap">
                        {row.start_at ? new Date(row.start_at).toLocaleString() : '—'}
                      </td>
                      <td className="px-4 py-3 text-ink-600 whitespace-nowrap">
                        {row.end_at ? new Date(row.end_at).toLocaleString() : '—'}
                      </td>
                      <td className="px-4 py-3">{row.store_name || `#${row.store_id}`}</td>
                      <td className="px-4 py-3">
                        {row.remote_id != null && row.status !== 'error' && (
                          <div className="flex flex-wrap gap-2">
                            {row.platform === 'shopee' && row.type === 'discount' && (
                              <button
                                type="button"
                                disabled={busy}
                                className="text-xs text-ink-700 underline disabled:opacity-50"
                                onClick={() =>
                                  void runAction(key, async () => {
                                    await updateShopeeDiscount(Number(row.remote_id), {
                                      store_id: row.store_id,
                                      action: 'end',
                                    });
                                  })
                                }
                              >
                                End
                              </button>
                            )}
                            {row.platform === 'shopee' && row.type === 'voucher' && (
                              row.can_mutate === false ? (
                                <span
                                  className="text-xs text-ink-400"
                                  title={row.mutate_blocked_reason || 'Platform/affiliate voucher cannot be ended'}
                                >
                                  Locked (affiliate)
                                </span>
                              ) : (
                                <button
                                  type="button"
                                  disabled={busy}
                                  className="text-xs text-ink-700 underline disabled:opacity-50"
                                  onClick={() =>
                                    void runAction(key, async () => {
                                      await updateShopeeVoucher(Number(row.remote_id), {
                                        store_id: row.store_id,
                                        action: 'end',
                                      });
                                    })
                                  }
                                >
                                  End
                                </button>
                              )
                            )}
                            {row.platform === 'lazada' && (
                              <>
                                <button
                                  type="button"
                                  disabled={busy}
                                  className="text-xs text-ink-700 underline disabled:opacity-50"
                                  onClick={() =>
                                    void runAction(key, async () => {
                                      await activateLazadaVoucher(row.remote_id!, {
                                        store_id: row.store_id,
                                      });
                                    })
                                  }
                                >
                                  Activate
                                </button>
                                <button
                                  type="button"
                                  disabled={busy}
                                  className="text-xs text-ink-700 underline disabled:opacity-50"
                                  onClick={() =>
                                    void runAction(key, async () => {
                                      await deactivateLazadaVoucher(row.remote_id!, {
                                        store_id: row.store_id,
                                      });
                                    })
                                  }
                                >
                                  Deactivate
                                </button>
                              </>
                            )}
                          </div>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {createKind && (
        <CreatePromotionModal
          kind={createKind}
          stores={storeOptions}
          defaultStoreId={selectedStoreId || storeOptions[0]?.id || null}
          onClose={() => setCreateKind(null)}
          onCreated={async () => {
            setCreateKind(null);
            showToast('Promotion created', 'success');
            await load();
          }}
        />
      )}
    </PageShell>
  );
}

function CreatePromotionModal({
  kind,
  stores,
  defaultStoreId,
  onClose,
  onCreated,
}: {
  kind: Exclude<CreateKind, null>;
  stores: Array<{ id: number; shop_name: string | null; platform: MarketplaceType }>;
  defaultStoreId: number | null;
  onClose: () => void;
  onCreated: () => Promise<void>;
}) {
  const { showToast } = useToast();
  const [submitting, setSubmitting] = useState(false);
  const shopeeStores = stores.filter((s) => s.platform === 'shopee');
  const lazadaStores = stores.filter((s) => s.platform === 'lazada');
  const [storeId, setStoreId] = useState<number>(() => {
    if (kind.startsWith('shopee')) {
      return shopeeStores.find((s) => s.id === defaultStoreId)?.id || shopeeStores[0]?.id || 0;
    }
    return lazadaStores.find((s) => s.id === defaultStoreId)?.id || lazadaStores[0]?.id || 0;
  });
  const [name, setName] = useState('');
  const [startHours, setStartHours] = useState(2);
  const [durationHours, setDurationHours] = useState(24);
  const [promoPrice, setPromoPrice] = useState('');
  const [selectedItems, setSelectedItems] = useState<PromoItemSelection[]>([]);
  const [selectedSkus, setSelectedSkus] = useState<PromoSkuSelection[]>([]);
  const [voucherScope, setVoucherScope] = useState<'shop' | 'product'>('shop');
  const [minBasket, setMinBasket] = useState('10');
  const [discountAmount, setDiscountAmount] = useState('5');
  const [usageQty, setUsageQty] = useState('100');
  const [criteria, setCriteria] = useState('30');
  const [issued, setIssued] = useState('100');
  const [limit, setLimit] = useState('1');
  const [apply, setApply] = useState('ENTIRE_SHOP');

  useEffect(() => {
    setSelectedItems([]);
    setSelectedSkus([]);
  }, [storeId]);

  async function submit(e: FormEvent) {
    e.preventDefault();
    if (!storeId) {
      showToast('Select a store', 'error');
      return;
    }
    if (kind === 'shopee_discount') {
      if (selectedItems.length === 0) {
        showToast('Select at least one product', 'error');
        return;
      }
      if (!promoPrice || Number(promoPrice) <= 0) {
        showToast('Enter a promo price', 'error');
        return;
      }
    }
    if (kind === 'shopee_voucher' && voucherScope === 'product' && selectedItems.length === 0) {
      showToast('Select at least one product for a product voucher', 'error');
      return;
    }
    if (kind === 'lazada_voucher' && apply === 'SPECIFIC_PRODUCTS' && selectedSkus.length === 0) {
      showToast('Select at least one product or SKU', 'error');
      return;
    }
    setSubmitting(true);
    try {
      const start = hoursFromNowUnix(startHours);
      const end = start + Math.round(durationHours * 3600);
      if (kind === 'shopee_discount') {
        const result = await createShopeeDiscount({
          store_id: storeId,
          discount_name: name || 'Discount',
          start_time: start,
          end_time: end,
        });
        if (!result.discount_id) {
          throw new Error('Discount created but no discount_id returned');
        }
        const price = Number(promoPrice);
        const itemList = selectedItems.map((item) => {
          if (item.hasVariants && item.variants.length > 0) {
            return {
              item_id: item.itemId,
              purchase_limit: 0,
              model_list: item.variants.map((v) => ({
                model_id: v.model_id,
                model_promotion_price: price,
              })),
            };
          }
          return {
            item_id: item.itemId,
            item_promotion_price: price,
            purchase_limit: 0,
          };
        });
        await addShopeeDiscountItems(result.discount_id, {
          store_id: storeId,
          item_list: itemList,
        });
      } else if (kind === 'shopee_voucher') {
        const payload: Parameters<typeof createShopeeVoucher>[0] = {
          store_id: storeId,
          voucher_name: name || 'Voucher',
          voucher_type: voucherScope === 'product' ? 2 : 1,
          reward_type: 1,
          discount_amount: Number(discountAmount),
          usage_quantity: Number(usageQty),
          min_basket_price: Number(minBasket),
          start_time: start,
          end_time: end,
        };
        if (voucherScope === 'product') {
          payload.item_id_list = selectedItems.map((i) => i.itemId);
        }
        await createShopeeVoucher(payload);
      } else {
        const startMs = start * 1000;
        const endMs = end * 1000;
        const skuIds = selectedSkus
          .map((s) => s.skuId)
          .filter((id): id is number | string => id != null && id !== '');
        const productIds = selectedSkus
          .filter((s) => s.skuId == null)
          .map((s) => s.productId);
        await createLazadaVoucher({
          store_id: storeId,
          voucher_type: 'COLLECTIBLE_VOUCHER',
          apply,
          display_area: 'REGULAR_CHANNEL',
          period_start_time: startMs,
          period_end_time: endMs,
          voucher_discount_type: 'MONEY_VALUE_OFF',
          criteria_over_money: criteria,
          offering_money_value_off: discountAmount,
          limit: Number(limit),
          issued: Number(issued),
          voucher_name: name || 'Voucher',
          ...(apply === 'SPECIFIC_PRODUCTS'
            ? {
                ...(skuIds.length ? { sku_ids: skuIds } : {}),
                ...(productIds.length ? { product_ids: productIds } : {}),
              }
            : {}),
        });
      }
      await onCreated();
    } catch (err) {
      showToast(apiErrorMessage(err), 'error');
    } finally {
      setSubmitting(false);
    }
  }

  const title =
    kind === 'shopee_discount'
      ? 'New Shopee discount'
      : kind === 'shopee_voucher'
        ? 'New Shopee voucher'
        : 'New Lazada voucher';
  const options = kind.startsWith('shopee') ? shopeeStores : lazadaStores;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <form
        onSubmit={(e) => void submit(e)}
        className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-5 shadow-xl"
      >
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-semibold text-ink-900">{title}</h2>
          <button type="button" className="text-ink-400 hover:text-ink-700" onClick={onClose}>
            Close
          </button>
        </div>
        <div className="space-y-3">
          <label className="block text-sm">
            <span className="mb-1 block text-ink-600">Store</span>
            <select
              className="w-full rounded-lg border border-ink-200 px-3 py-2"
              value={storeId}
              onChange={(e) => setStoreId(Number(e.target.value))}
              required
            >
              {options.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.shop_name || `Store #${s.id}`}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            <span className="mb-1 block text-ink-600">Name</span>
            <input
              className="w-full rounded-lg border border-ink-200 px-3 py-2"
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
            />
          </label>
          <div className="grid grid-cols-2 gap-3">
            <label className="block text-sm">
              <span className="mb-1 block text-ink-600">Starts in (hours)</span>
              <input
                type="number"
                min={kind === 'shopee_discount' ? 1 : 0}
                className="w-full rounded-lg border border-ink-200 px-3 py-2"
                value={startHours}
                onChange={(e) => setStartHours(Number(e.target.value))}
              />
            </label>
            <label className="block text-sm">
              <span className="mb-1 block text-ink-600">Duration (hours)</span>
              <input
                type="number"
                min={1}
                className="w-full rounded-lg border border-ink-200 px-3 py-2"
                value={durationHours}
                onChange={(e) => setDurationHours(Number(e.target.value))}
              />
            </label>
          </div>
          {kind === 'shopee_discount' && (
            <>
              <label className="block text-sm">
                <span className="mb-1 block text-ink-600">Promo price (all selected)</span>
                <input
                  className="w-full rounded-lg border border-ink-200 px-3 py-2"
                  value={promoPrice}
                  onChange={(e) => setPromoPrice(e.target.value)}
                  required
                  inputMode="decimal"
                />
              </label>
              <div className="block text-sm">
                <span className="mb-1 block text-ink-600">Products</span>
                <PromotionProductPicker
                  mode="item"
                  storeId={storeId}
                  selected={selectedItems}
                  onChange={setSelectedItems}
                  disabled={submitting}
                />
              </div>
            </>
          )}
          {kind === 'shopee_voucher' && (
            <>
              <label className="block text-sm">
                <span className="mb-1 block text-ink-600">Scope</span>
                <select
                  className="w-full rounded-lg border border-ink-200 px-3 py-2"
                  value={voucherScope}
                  onChange={(e) => setVoucherScope(e.target.value as 'shop' | 'product')}
                >
                  <option value="shop">Shop voucher</option>
                  <option value="product">Product voucher</option>
                </select>
              </label>
              <div className="grid grid-cols-3 gap-3">
                <label className="block text-sm">
                  <span className="mb-1 block text-ink-600">Min basket</span>
                  <input
                    className="w-full rounded-lg border border-ink-200 px-3 py-2"
                    value={minBasket}
                    onChange={(e) => setMinBasket(e.target.value)}
                  />
                </label>
                <label className="block text-sm">
                  <span className="mb-1 block text-ink-600">Discount $</span>
                  <input
                    className="w-full rounded-lg border border-ink-200 px-3 py-2"
                    value={discountAmount}
                    onChange={(e) => setDiscountAmount(e.target.value)}
                  />
                </label>
                <label className="block text-sm">
                  <span className="mb-1 block text-ink-600">Usage qty</span>
                  <input
                    className="w-full rounded-lg border border-ink-200 px-3 py-2"
                    value={usageQty}
                    onChange={(e) => setUsageQty(e.target.value)}
                  />
                </label>
              </div>
              {voucherScope === 'product' && (
                <div className="block text-sm">
                  <span className="mb-1 block text-ink-600">Products</span>
                  <PromotionProductPicker
                    mode="item"
                    storeId={storeId}
                    selected={selectedItems}
                    onChange={setSelectedItems}
                    disabled={submitting}
                  />
                </div>
              )}
            </>
          )}
          {kind === 'lazada_voucher' && (
            <>
              <label className="block text-sm">
                <span className="mb-1 block text-ink-600">Apply</span>
                <select
                  className="w-full rounded-lg border border-ink-200 px-3 py-2"
                  value={apply}
                  onChange={(e) => {
                    setApply(e.target.value);
                    if (e.target.value !== 'SPECIFIC_PRODUCTS') setSelectedSkus([]);
                  }}
                >
                  <option value="ENTIRE_SHOP">Entire shop</option>
                  <option value="SPECIFIC_PRODUCTS">Specific products</option>
                </select>
              </label>
              {apply === 'SPECIFIC_PRODUCTS' && (
                <div className="block text-sm">
                  <span className="mb-1 block text-ink-600">Products / SKUs</span>
                  <PromotionProductPicker
                    mode="sku"
                    storeId={storeId}
                    selected={selectedSkus}
                    onChange={setSelectedSkus}
                    disabled={submitting}
                  />
                </div>
              )}
              <div className="grid grid-cols-3 gap-3">
                <label className="block text-sm">
                  <span className="mb-1 block text-ink-600">Min spend</span>
                  <input
                    className="w-full rounded-lg border border-ink-200 px-3 py-2"
                    value={criteria}
                    onChange={(e) => setCriteria(e.target.value)}
                  />
                </label>
                <label className="block text-sm">
                  <span className="mb-1 block text-ink-600">Off $</span>
                  <input
                    className="w-full rounded-lg border border-ink-200 px-3 py-2"
                    value={discountAmount}
                    onChange={(e) => setDiscountAmount(e.target.value)}
                  />
                </label>
                <label className="block text-sm">
                  <span className="mb-1 block text-ink-600">Issued / limit</span>
                  <div className="flex gap-1">
                    <input
                      className="w-full rounded-lg border border-ink-200 px-2 py-2"
                      value={issued}
                      onChange={(e) => setIssued(e.target.value)}
                    />
                    <input
                      className="w-full rounded-lg border border-ink-200 px-2 py-2"
                      value={limit}
                      onChange={(e) => setLimit(e.target.value)}
                    />
                  </div>
                </label>
              </div>
            </>
          )}
        </div>
        <div className="mt-5 flex justify-end gap-2">
          <button type="button" className="rounded-lg px-3 py-2 text-sm text-ink-600" onClick={onClose}>
            Cancel
          </button>
          <button
            type="submit"
            disabled={submitting || !options.length}
            className="rounded-lg bg-ink-900 px-4 py-2 text-sm text-white disabled:opacity-50"
          >
            {submitting ? 'Creating…' : 'Create'}
          </button>
        </div>
      </form>
    </div>
  );
}
