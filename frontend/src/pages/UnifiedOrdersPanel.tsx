import { useEffect, useMemo, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import Spinner from '../components/Spinner';
import OrderDetailDrawer from '../components/OrderDetailDrawer';
import ArrangeShipmentModal from '../components/ArrangeShipmentModal';
import PrintLabelButton from '../components/PrintLabelButton';
import OrdersTable from '../components/orders/OrdersTable';
import type { OrdersTableRow } from '../components/orders/ordersTableTypes';
import LazadaOrderDetailDrawer from '../components/lazada/LazadaOrderDetailDrawer';
import {
  StatusBadge,
  STATUS_LABELS,
  canShip,
  canPrintLabel,
  formatCurrency,
  formatDate,
  getShipByInfo,
} from '../components/orderHelpers';
import {
  LazadaStatusBadge,
  LAZADA_STATUS_LABELS,
  canLazadaPack,
  canLazadaPrintAwb,
  canLazadaReadyToShip,
  formatCurrency as formatLazadaCurrency,
  formatDate as formatLazadaDate,
} from '../components/lazada/lazadaOrderHelpers';
import { useStore } from '../context/StoreContext';
import { useToast } from '../context/ToastContext';
import {
  getOrders,
  syncOrders,
  syncLazadaOrders,
  packLazadaOrder,
  downloadLazadaAwb,
  downloadLazadaAwbsBulk,
  downloadShippingLabelsBulk,
  readyToShipLazadaOrder,
  type BulkShippingLabelResult,
  type MarketplaceType,
  type Order,
} from '../api/client';
import {
  mergeAwbAndPacking,
  mergeBulkShippingDocuments,
  openPdfBlob,
} from '../lib/shippingLabelPdf';

const BULK_PRINT_MAX = 15;
const ITEMS_PREVIEW = 2;

type PlatformFilter = MarketplaceType | 'all';
type UrgencyFilter = '' | 'overdue' | 'due_today' | 'due_48h';
type ShopeePreset = '' | 'unpaid' | 'to_arrange' | 'to_print' | 'in_transit' | 'completed' | 'problems';
type LazadaPreset =
  | ''
  | 'to_pack'
  | 'packed'
  | 'to_handover'
  | 'in_transit'
  | 'completed'
  | 'problems';
type AllPreset =
  | ''
  | 'unpaid'
  | 'to_fulfill'
  | 'to_print'
  | 'in_transit'
  | 'completed'
  | 'problems';
type DatePreset = '' | 'today' | '7d' | '14d' | 'custom';
type SortPreset = 'newest' | 'ship_by' | 'total';

const SHOPEE_PRESETS: Record<Exclude<ShopeePreset, ''>, { label: string; statusIn: string[] }> = {
  unpaid: { label: 'Unpaid', statusIn: ['UNPAID'] },
  to_arrange: { label: 'To arrange', statusIn: ['READY_TO_SHIP'] },
  to_print: { label: 'To print', statusIn: ['PROCESSED'] },
  in_transit: { label: 'In transit', statusIn: ['SHIPPED', 'TO_CONFIRM_RECEIVE'] },
  completed: { label: 'Completed', statusIn: ['COMPLETED'] },
  problems: { label: 'Problems', statusIn: ['IN_CANCEL', 'TO_RETURN', 'CANCELLED'] },
};

const LAZADA_PRESETS: Record<Exclude<LazadaPreset, ''>, { label: string; statusIn: string[] }> = {
  to_pack: { label: 'To pack', statusIn: ['pending'] },
  packed: { label: 'Packed / print', statusIn: ['packed'] },
  to_handover: { label: 'To hand over', statusIn: ['ready_to_ship'] },
  in_transit: { label: 'In transit', statusIn: ['shipped'] },
  completed: { label: 'Completed', statusIn: ['delivered', 'confirmed'] },
  problems: { label: 'Problems', statusIn: ['canceled', 'returned', 'failed'] },
};

const ALL_PRESETS: Record<Exclude<AllPreset, ''>, { label: string; statusIn: string[] }> = {
  unpaid: { label: 'Unpaid', statusIn: ['UNPAID', 'unpaid'] },
  to_fulfill: {
    label: 'To fulfill',
    statusIn: ['READY_TO_SHIP', 'PROCESSED', 'pending', 'packed', 'ready_to_ship'],
  },
  to_print: { label: 'To print', statusIn: ['PROCESSED', 'packed'] },
  in_transit: { label: 'In transit', statusIn: ['SHIPPED', 'TO_CONFIRM_RECEIVE', 'shipped'] },
  completed: { label: 'Completed', statusIn: ['COMPLETED', 'delivered', 'confirmed'] },
  problems: {
    label: 'Problems',
    statusIn: ['IN_CANCEL', 'TO_RETURN', 'CANCELLED', 'canceled', 'returned', 'failed'],
  },
};

const URGENCY_CHIPS: Array<{ value: UrgencyFilter; label: string }> = [
  { value: '', label: 'All' },
  { value: 'overdue', label: 'Overdue' },
  { value: 'due_today', label: 'Due today' },
  { value: 'due_48h', label: 'Due in 48h' },
];

const PLATFORM_TABS: Array<{ label: string; value: PlatformFilter }> = [
  { label: 'All', value: 'all' },
  { label: 'Shopee', value: 'shopee' },
  { label: 'Lazada', value: 'lazada' },
];

interface UnifiedTableRow extends OrdersTableRow {
  order: Order;
  busy: boolean;
  itemsExpanded: boolean;
  hiddenCount: number;
}

function localYmd(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function resolveDateRange(
  preset: DatePreset,
  customFrom = '',
  customTo = '',
): { from?: string; to?: string } {
  if (!preset) return {};
  if (preset === 'custom') {
    return {
      ...(customFrom ? { from: customFrom } : {}),
      ...(customTo ? { to: customTo } : {}),
    };
  }
  const start = new Date();
  start.setHours(0, 0, 0, 0);
  const to = localYmd(start);
  if (preset === 'today') return { from: to, to };
  const from = new Date(start);
  from.setDate(from.getDate() - (preset === '7d' ? 6 : 13));
  return { from: localYmd(from), to };
}

function orderPlatform(order: Order): MarketplaceType {
  return order.store?.platform === 'lazada' ? 'lazada' : 'shopee';
}

function isPrintable(order: Order): boolean {
  return orderPlatform(order) === 'lazada'
    ? canLazadaPrintAwb(order.order_status)
    : canPrintLabel(order.order_status);
}

export default function UnifiedOrdersPanel() {
  const { stores, selectedStoreId, selectedMarketplace, setSelectedMarketplace } = useStore();
  const platformFilter: PlatformFilter = selectedMarketplace;
  const { showToast } = useToast();
  const [searchParams, setSearchParams] = useSearchParams();
  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [syncing, setSyncing] = useState(false);
  const [statusFilter, setStatusFilter] = useState('');
  const [shopeePreset, setShopeePreset] = useState<ShopeePreset>('');
  const [lazadaPreset, setLazadaPreset] = useState<LazadaPreset>('');
  const [allPreset, setAllPreset] = useState<AllPreset>('');
  const [urgency, setUrgency] = useState<UrgencyFilter>('');
  const [datePreset, setDatePreset] = useState<DatePreset>('');
  const [customDateFrom, setCustomDateFrom] = useState('');
  const [customDateTo, setCustomDateTo] = useState('');
  const [sortPreset, setSortPreset] = useState<SortPreset>('newest');
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [detailOrder, setDetailOrder] = useState<Order | null>(null);
  const [arrangingOrder, setArrangingOrder] = useState<Order | null>(null);
  const [rowBusy, setRowBusy] = useState<number | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [bulkPrinting, setBulkPrinting] = useState(false);
  const [expandedItemIds, setExpandedItemIds] = useState<Set<number>>(() => new Set());
  const [showFulfillmentGuide, setShowFulfillmentGuide] = useState(() => {
    try {
      return localStorage.getItem('hide_fulfillment_guide') !== 'true';
    } catch {
      return true;
    }
  });
  const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const toggleFulfillmentGuide = () => {
    setShowFulfillmentGuide((prev) => {
      const next = !prev;
      try {
        if (next) localStorage.removeItem('hide_fulfillment_guide');
        else localStorage.setItem('hide_fulfillment_guide', 'true');
      } catch {}
      return next;
    });
  };

  const queuesEnabled = platformFilter !== 'all';
  const showStoreColumn = selectedStoreId === null;
  const hasShopeeStores = stores.some((s) => s.platform === 'shopee');
  const hasLazadaStores = stores.some((s) => s.platform === 'lazada');
  const customDateInvalid =
    datePreset === 'custom' &&
    customDateFrom !== '' &&
    customDateTo !== '' &&
    customDateFrom > customDateTo;
  const filtersActive =
    statusFilter !== '' ||
    shopeePreset !== '' ||
    lazadaPreset !== '' ||
    allPreset !== '' ||
    urgency !== '' ||
    datePreset !== '' ||
    sortPreset !== 'newest' ||
    searchInput.trim() !== '' ||
    search.trim() !== '';

  const printableOnPage = useMemo(
    () => orders.filter((o) => isPrintable(o)),
    [orders],
  );
  const printableIds = useMemo(
    () => printableOnPage.map((o) => Number(o.id)),
    [printableOnPage],
  );
  const selectableIds = useMemo(
    () => printableIds.slice(0, BULK_PRINT_MAX),
    [printableIds],
  );
  const allSelectableSelected =
    selectableIds.length > 0 && selectableIds.every((id) => selectedIds.includes(id));
  const selectedCount = selectedIds.length;

  const clearFilters = () => {
    setStatusFilter('');
    setShopeePreset('');
    setLazadaPreset('');
    setAllPreset('');
    setUrgency('');
    setDatePreset('');
    setCustomDateFrom('');
    setCustomDateTo('');
    setSortPreset('newest');
    setSearchInput('');
    setSearch('');
    setPage(1);
  };

  const fetchOrders = async () => {
    if (customDateInvalid) {
      setError('Custom date range is invalid: “from” must be on or before “to”.');
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const params: Parameters<typeof getOrders>[0] = { page };
      if (selectedStoreId !== null) params.store_id = selectedStoreId;
      if (platformFilter === 'shopee' || platformFilter === 'lazada') {
        params.platform = platformFilter;
      }
      if (statusFilter !== '') {
        params.status = statusFilter;
      } else if (platformFilter === 'shopee' && shopeePreset !== '') {
        params.status_in = SHOPEE_PRESETS[shopeePreset].statusIn;
      } else if (platformFilter === 'lazada' && lazadaPreset !== '') {
        params.status_in = LAZADA_PRESETS[lazadaPreset].statusIn;
      } else if (platformFilter === 'all' && allPreset !== '') {
        params.status_in = ALL_PRESETS[allPreset].statusIn;
      }
      if (search.trim() !== '') params.search = search.trim();
      if (urgency !== '') params.urgency = urgency;
      const range = resolveDateRange(datePreset, customDateFrom, customDateTo);
      if (range.from) params.ordered_from = range.from;
      if (range.to) params.ordered_to = range.to;
      if (sortPreset === 'ship_by') {
        params.sort_by = 'ship_by_date';
        params.sort_dir = 'asc';
      } else if (sortPreset === 'total') {
        params.sort_by = 'total_amount';
        params.sort_dir = 'desc';
      } else {
        params.sort_by = 'ordered_at';
        params.sort_dir = 'desc';
      }
      const res = await getOrders(params);
      setOrders(res.data ?? []);
      setLastPage(res.last_page ?? 1);
      setTotal(res.total ?? 0);
    } catch (e: any) {
      setError(e?.response?.data?.message || 'Failed to load orders.');
      setOrders([]);
      setLastPage(1);
      setTotal(0);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (searchTimer.current) clearTimeout(searchTimer.current);
    searchTimer.current = setTimeout(() => {
      setSearch(searchInput);
      setPage(1);
    }, 300);
    return () => {
      if (searchTimer.current) clearTimeout(searchTimer.current);
    };
  }, [searchInput]);

  useEffect(() => {
    setShopeePreset('');
    setLazadaPreset('');
    setAllPreset('');
    setUrgency('');
    setStatusFilter('');
    setPage(1);
  }, [platformFilter]);

  // Deep link from an urgent-order notification: /orders?urgency=due_today
  // (/dashboard -> NotificationBell). This page is a keep-alive slot, so it stays
  // mounted across visits and a lazy useState initializer would only ever run once.
  // Reading on every query-string change is what makes repeat clicks work.
  // Never clear when the param is absent - that would undo the filter we just set
  // once the param is stripped, and would stomp filters the user chose by hand.
  useEffect(() => {
    const value = searchParams.get('urgency');
    if (value === null) return;
    if (!URGENCY_CHIPS.some((chip) => chip.value === value)) return;
    setUrgency(value as UrgencyFilter);
    setPage(1);
    const next = new URLSearchParams(searchParams);
    next.delete('urgency');
    setSearchParams(next, { replace: true });
  }, [searchParams, setSearchParams]);

  useEffect(() => {
    setPage(1);
  }, [
    selectedStoreId,
    statusFilter,
    shopeePreset,
    lazadaPreset,
    allPreset,
    urgency,
    datePreset,
    customDateFrom,
    customDateTo,
    sortPreset,
  ]);

  useEffect(() => {
    setSelectedIds([]);
    setExpandedItemIds(new Set());
  }, [
    selectedStoreId,
    platformFilter,
    statusFilter,
    shopeePreset,
    lazadaPreset,
    allPreset,
    urgency,
    datePreset,
    customDateFrom,
    customDateTo,
    sortPreset,
    search,
    page,
  ]);

  useEffect(() => {
    fetchOrders();
  }, [
    selectedStoreId,
    platformFilter,
    statusFilter,
    shopeePreset,
    lazadaPreset,
    allPreset,
    urgency,
    datePreset,
    customDateFrom,
    customDateTo,
    sortPreset,
    search,
    page,
  ]);

  const toggleSelect = (order: Order) => {
    if (!isPrintable(order)) return;
    const id = Number(order.id);
    setSelectedIds((prev) => {
      if (prev.includes(id)) return prev.filter((x) => x !== id);
      if (prev.length >= BULK_PRINT_MAX) {
        showToast(`You can print at most ${BULK_PRINT_MAX} labels at once.`, 'error');
        return prev;
      }
      return [...prev, id];
    });
  };

  const toggleSelectAllPrintable = () => {
    if (allSelectableSelected) {
      setSelectedIds([]);
      return;
    }
    if (printableIds.length > BULK_PRINT_MAX) {
      showToast(`Selected first ${BULK_PRINT_MAX} printable orders on this page.`, 'info');
    }
    setSelectedIds([...selectableIds]);
  };

  const toggleItemsExpanded = (orderId: number) => {
    setExpandedItemIds((prev) => {
      const next = new Set(prev);
      if (next.has(orderId)) next.delete(orderId);
      else next.add(orderId);
      return next;
    });
  };

  const handleBulkPrint = async () => {
    if (selectedCount === 0) return;
    const ids = [...selectedIds];
    const orderById = new Map(orders.map((o) => [Number(o.id), o]));
    const shopeeIds: number[] = [];
    const lazadaIds: number[] = [];
    const unknownErrors: BulkShippingLabelResult['errors'] = [];
    for (const id of ids) {
      const order = orderById.get(id);
      if (!order) {
        unknownErrors.push({
          orderId: id,
          orderSn: null,
          message: `Order #${id} is no longer on this page.`,
        });
        continue;
      }
      if (orderPlatform(order) === 'lazada') lazadaIds.push(id);
      else shopeeIds.push(id);
    }
    setBulkPrinting(true);
    try {
      const emptyResult = (filename: string): BulkShippingLabelResult => ({
        documents: [],
        errors: [],
        filename,
      });
      const fetchSide = async (
        platformIds: number[],
        fetchFn: (orderIds: number[]) => Promise<BulkShippingLabelResult>,
        label: string,
        defaultFilename: string,
      ): Promise<BulkShippingLabelResult> => {
        if (platformIds.length === 0) return emptyResult(defaultFilename);
        try {
          return await fetchFn(platformIds);
        } catch (e: any) {
          const message =
            e?.response?.data?.message || e?.message || `${label} bulk print failed.`;
          return {
            documents: [],
            errors: platformIds.map((orderId) => {
              const order = orderById.get(orderId);
              return {
                orderId,
                orderSn: order?.order_sn ?? null,
                message,
              };
            }),
            filename: defaultFilename,
          };
        }
      };
      const [shopeeResult, lazadaResult] = await Promise.all([
        fetchSide(shopeeIds, downloadShippingLabelsBulk, 'Shopee', 'awb-packing-bulk.pdf'),
        fetchSide(lazadaIds, downloadLazadaAwbsBulk, 'Lazada', 'lazada-awb-packing-bulk.pdf'),
      ]);
      const allDocuments = [...shopeeResult.documents, ...lazadaResult.documents];
      const allErrors = [...unknownErrors, ...shopeeResult.errors, ...lazadaResult.errors];
      if (allDocuments.length === 0) {
        const detail = allErrors.map((e) => e.message).join('; ') || 'No labels generated.';
        showToast(detail, 'error');
        return;
      }
      const docsByOrderId = new Map(allDocuments.map((d) => [d.orderId, d]));
      const orderedDocs = ids
        .map((id) => docsByOrderId.get(id))
        .filter((d): d is (typeof allDocuments)[number] => !!d);
      const mergeDocs = orderedDocs.length > 0 ? orderedDocs : allDocuments;
      const merged = await mergeBulkShippingDocuments(
        mergeDocs.map((d) => ({
          awbPdf: d.awbPdf,
          packingListPdf: d.packingListPdf,
        })),
      );
      const mixed = shopeeIds.length > 0 && lazadaIds.length > 0;
      const filename = mixed
        ? 'awb-packing-bulk.pdf'
        : lazadaIds.length > 0
          ? lazadaResult.filename
          : shopeeResult.filename;
      openPdfBlob(merged, filename);
      if (allErrors.length > 0) {
        showToast(
          `Printed ${mergeDocs.length} label(s). ${allErrors.length} failed.`,
          'error',
        );
      } else {
        showToast(`Printed ${mergeDocs.length} label(s).`, 'success');
      }
    } catch (e: any) {
      showToast(e?.response?.data?.message || e?.message || 'Bulk print failed.', 'error');
    } finally {
      setBulkPrinting(false);
    }
  };

  const handleSync = async () => {
    setSyncing(true);
    try {
      if (platformFilter === 'shopee') {
        const results = await syncOrders('shopee');
        showToast(results.join('\n') || 'Shopee sync completed.', 'success');
      } else if (platformFilter === 'lazada') {
        const results = await syncLazadaOrders();
        showToast(results.join('\n') || 'Lazada sync completed.', 'success');
      } else {
        const messages: string[] = [];
        if (hasShopeeStores) {
          try {
            const results = await syncOrders('shopee');
            messages.push(...(results.length ? results : ['Shopee: sync completed.']));
          } catch {
            messages.push('Shopee sync failed.');
          }
        }
        if (hasLazadaStores) {
          try {
            const results = await syncLazadaOrders();
            messages.push(...(results.length ? results : ['Lazada: sync completed.']));
          } catch {
            messages.push('Lazada sync failed.');
          }
        }
        if (messages.length === 0) {
          showToast('No stores to sync. Link a store from the Dashboard.', 'info');
        } else {
          const failed = messages.some((m) => m.toLowerCase().includes('failed'));
          showToast(messages.join('\n'), failed ? 'error' : 'success');
        }
      }
      fetchOrders();
    } finally {
      setSyncing(false);
    }
  };

  const runLazadaRowAction = async (order: Order, action: 'pack' | 'awb' | 'rts') => {
    setRowBusy(order.id);
    try {
      if (action === 'pack') {
        await packLazadaOrder(order.id);
        showToast('Order packed.', 'success');
      } else if (action === 'awb') {
        const docs = await downloadLazadaAwb(order.id);
        const mergedBytes = await mergeAwbAndPacking(docs.awbPdf, docs.packingListPdf);
        openPdfBlob(mergedBytes, docs.filename || `lazada-awb-packing-${order.order_sn}.pdf`);
        showToast('AWB + Packing opened.', 'success');
      } else {
        await readyToShipLazadaOrder(order.id);
        showToast('Marked ready to ship.', 'success');
      }
      fetchOrders();
    } catch (e: any) {
      showToast(e?.response?.data?.message || e?.message || 'Action failed.', 'error');
    } finally {
      setRowBusy(null);
    }
  };

  const statusOptions =
    platformFilter === 'lazada'
      ? Object.entries(LAZADA_STATUS_LABELS).filter(([k]) => k !== 'cancelled')
      : platformFilter === 'shopee'
        ? Object.entries(STATUS_LABELS)
        : [];

  const tableRows: UnifiedTableRow[] = orders.map((order) => {
    const platform = orderPlatform(order);
    const printable = isPrintable(order);
    const shipBy = platform === 'shopee' ? getShipByInfo(order) : { label: '—', tone: 'none' as const };
    const items = order.items ?? [];
    const itemsExpanded = expandedItemIds.has(order.id);
    const fmtDate = platform === 'lazada' ? formatLazadaDate : formatDate;
    const fmtMoney = platform === 'lazada' ? formatLazadaCurrency : formatCurrency;
    return {
      id: order.id,
      order,
      platform,
      orderSn: order.order_sn,
      orderMeta:
        platform === 'shopee'
          ? order.paid_time
            ? `Paid ${fmtDate(order.paid_time)}`
            : order.synced_at
              ? `Synced ${fmtDate(order.synced_at)}`
              : '—'
          : order.synced_at
            ? `Synced ${fmtDate(order.synced_at)}`
            : '—',
      orderedAtText: order.ordered_at ? fmtDate(order.ordered_at) : '—',
      storeName: order.store?.shop_name || `Store #${order.store_id}`,
      buyerText: order.buyer_username || '—',
      shipByText: shipBy.label,
      shipByTone: shipBy.tone,
      totalText: fmtMoney(order.total_amount, order.currency),
      selectable: printable && !bulkPrinting,
      selected: selectedIds.includes(Number(order.id)),
      busy: rowBusy === order.id,
      itemsExpanded,
      hiddenCount: Math.max(0, items.length - ITEMS_PREVIEW),
    };
  });

  const syncLabel =
    platformFilter === 'shopee'
      ? 'Sync Shopee'
      : platformFilter === 'lazada'
        ? 'Sync Lazada'
        : 'Sync';

  return (
    <div className="w-full max-w-none space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div className="flex rounded-xl border border-ink-200 overflow-hidden text-sm w-fit">
          {PLATFORM_TABS.map((tab) => (
            <button
              key={tab.value}
              type="button"
              onClick={() => setSelectedMarketplace(tab.value)}
              className={`px-3 py-1.5 transition-colors ${
                platformFilter === tab.value
                  ? tab.value === 'lazada'
                    ? 'bg-blue-600 text-white font-medium'
                    : tab.value === 'shopee'
                      ? 'bg-red-600 text-white font-medium'
                      : 'bg-ink-950 text-white font-medium'
                  : 'bg-white text-ink-600 hover:bg-ink-50'
              }`}
            >
              {tab.label}
            </button>
          ))}
        </div>
        <div className="flex items-center gap-3 flex-wrap">
          <button
            onClick={handleBulkPrint}
            disabled={bulkPrinting || selectedCount === 0}
            title="Print AWB + packing list for selected orders"
            className="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl border border-indigo-200 text-indigo-700 hover:bg-indigo-50 disabled:opacity-50 transition"
          >
            {bulkPrinting && <Spinner />}
            {bulkPrinting
              ? 'Printing…'
              : selectedCount > 0
                ? `Print selected (${selectedCount})`
                : 'Print selected'}
          </button>
          <button
            onClick={handleSync}
            disabled={syncing}
            className={`inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-xl disabled:opacity-50 transition ${
              platformFilter === 'shopee'
                ? 'bg-red-600 hover:bg-red-700'
                : platformFilter === 'lazada'
                  ? 'bg-blue-600 hover:bg-blue-700'
                  : 'bg-ink-950 hover:bg-ink-800'
            }`}
          >
            {syncing && <Spinner />}
            {syncing ? 'Syncing…' : syncLabel}
          </button>
        </div>
      </div>

      <div className="bg-white p-4 rounded-2xl border border-ink-100 shadow-sm space-y-3">
        {platformFilter === 'all' ? (
          <div className="flex flex-wrap gap-2">
            {(Object.keys(ALL_PRESETS) as Array<Exclude<AllPreset, ''>>).map((key) => (
              <button
                key={key}
                type="button"
                onClick={() => {
                  setAllPreset((p) => (p === key ? '' : key));
                  setStatusFilter('');
                }}
                className={`px-3 py-1.5 rounded-full text-xs font-semibold border transition ${
                  allPreset === key
                    ? 'bg-ink-950 text-white border-ink-950'
                    : 'bg-white text-ink-600 border-ink-200 hover:bg-ink-50'
                }`}
              >
                {ALL_PRESETS[key].label}
              </button>
            ))}
          </div>
        ) : platformFilter === 'shopee' ? (
          <div className="flex flex-wrap gap-2">
            {(Object.keys(SHOPEE_PRESETS) as Array<Exclude<ShopeePreset, ''>>).map((key) => (
              <button
                key={key}
                type="button"
                onClick={() => {
                  setShopeePreset((p) => (p === key ? '' : key));
                  setStatusFilter('');
                }}
                className={`px-3 py-1.5 rounded-full text-xs font-semibold border transition ${
                  shopeePreset === key
                    ? 'bg-red-600 text-white border-red-600'
                    : 'bg-white text-ink-600 border-ink-200 hover:bg-ink-50'
                }`}
              >
                {SHOPEE_PRESETS[key].label}
              </button>
            ))}
          </div>
        ) : (
          <div className="flex flex-wrap gap-2">
            {(Object.keys(LAZADA_PRESETS) as Array<Exclude<LazadaPreset, ''>>).map((key) => (
              <button
                key={key}
                type="button"
                onClick={() => {
                  setLazadaPreset((p) => (p === key ? '' : key));
                  setStatusFilter('');
                }}
                className={`px-3 py-1.5 rounded-full text-xs font-semibold border transition ${
                  lazadaPreset === key
                    ? 'bg-blue-600 text-white border-blue-600'
                    : 'bg-white text-ink-600 border-ink-200 hover:bg-ink-50'
                }`}
              >
                {LAZADA_PRESETS[key].label}
              </button>
            ))}
          </div>
        )}

        <div className="flex flex-col sm:flex-row gap-3 flex-wrap items-center">
          <input
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            placeholder="Search order ID, buyer, item…"
            className="flex-1 min-w-[180px] px-3 py-2 rounded-xl border border-ink-200 text-sm"
          />
          {queuesEnabled && (
            <select
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value);
                setShopeePreset('');
                setLazadaPreset('');
                setAllPreset('');
              }}
              className="px-3 py-2 rounded-xl border border-ink-200 text-sm"
            >
              <option value="">All statuses</option>
              {statusOptions.map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          )}
          <div className="inline-flex items-center rounded-xl border border-ink-200 bg-white p-0.5">
            {URGENCY_CHIPS.map((chip) => (
              <button
                key={chip.value || 'all'}
                type="button"
                onClick={() => setUrgency(chip.value)}
                className={`px-2.5 py-1.5 text-xs font-semibold rounded-lg transition ${
                  urgency === chip.value
                    ? chip.value === 'overdue'
                      ? 'bg-red-600 text-white'
                      : chip.value === 'due_today'
                        ? 'bg-amber-500 text-white'
                        : 'bg-ink-950 text-white'
                    : 'text-ink-600 hover:bg-ink-50'
                }`}
              >
                {chip.label}
              </button>
            ))}
          </div>
          <select
            value={datePreset}
            onChange={(e) => {
              const next = e.target.value as DatePreset;
              setDatePreset(next);
              if (next !== 'custom') {
                setCustomDateFrom('');
                setCustomDateTo('');
              }
            }}
            className="px-3 py-2 rounded-xl border border-ink-200 text-sm"
          >
            <option value="">Any date</option>
            <option value="today">Today</option>
            <option value="7d">Last 7 days</option>
            <option value="14d">Last 14 days</option>
            <option value="custom">Custom</option>
          </select>
          {datePreset === 'custom' && (
            <div className="inline-flex items-center gap-2">
              <input
                type="date"
                value={customDateFrom}
                onChange={(e) => setCustomDateFrom(e.target.value)}
                className="px-3 py-2 rounded-xl border border-ink-200 text-sm"
                aria-label="Ordered from"
              />
              <span className="text-xs text-ink-400">to</span>
              <input
                type="date"
                value={customDateTo}
                onChange={(e) => setCustomDateTo(e.target.value)}
                className={`px-3 py-2 rounded-xl border text-sm ${
                  customDateInvalid ? 'border-red-400' : 'border-ink-200'
                }`}
                aria-label="Ordered to"
              />
            </div>
          )}
          <select
            value={sortPreset}
            onChange={(e) => setSortPreset(e.target.value as SortPreset)}
            className="px-3 py-2 rounded-xl border border-ink-200 text-sm"
          >
            <option value="newest">Newest</option>
            <option value="ship_by">Ship by (soonest)</option>
            <option value="total">Highest total</option>
          </select>
          {filtersActive && (
            <button
              type="button"
              onClick={clearFilters}
              className="px-3 py-2 text-xs font-semibold text-ink-600 border border-ink-200 rounded-xl hover:bg-ink-50"
            >
              Clear filters
            </button>
          )}
        </div>
        {filtersActive && (
          <p className="text-xs text-ink-400">
            {[
              allPreset ? `Queue: ${ALL_PRESETS[allPreset].label}` : null,
              shopeePreset ? `Queue: ${SHOPEE_PRESETS[shopeePreset].label}` : null,
              lazadaPreset ? `Queue: ${LAZADA_PRESETS[lazadaPreset].label}` : null,
              statusFilter
                ? `Status: ${
                    statusOptions.find(([v]) => v === statusFilter)?.[1] || statusFilter
                  }`
                : null,
              urgency
                ? `Urgency: ${URGENCY_CHIPS.find((c) => c.value === urgency)?.label || urgency}`
                : null,
              datePreset === 'custom'
                ? `Date: ${customDateFrom || '…'} → ${customDateTo || '…'}`
                : datePreset
                  ? `Date: ${
                      datePreset === 'today'
                        ? 'Today'
                        : datePreset === '7d'
                          ? 'Last 7 days'
                          : 'Last 14 days'
                    }`
                  : null,
              sortPreset !== 'newest'
                ? `Sort: ${sortPreset === 'ship_by' ? 'Ship by' : 'Highest total'}`
                : null,
              search.trim() ? `Search: “${search.trim()}”` : null,
            ]
              .filter(Boolean)
              .join(' · ')}
          </p>
        )}
        {customDateInvalid && (
          <p className="text-xs text-red-600">“From” date must be on or before “to” date.</p>
        )}
      </div>

      {error && (
        <div className="p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-center justify-between gap-3">
          <span>{error}</span>
          <button
            type="button"
            onClick={fetchOrders}
            className="shrink-0 px-3 py-1.5 text-xs font-semibold bg-ink-950 text-white rounded-lg"
          >
            Retry
          </button>
        </div>
      )}

      {showFulfillmentGuide ? (
        <div className="rounded-2xl border border-blue-100 bg-blue-50/70 p-4 text-xs sm:text-sm text-blue-950 shadow-2xs">
          <div className="flex items-start justify-between gap-3">
            <div className="flex items-start gap-3">
              <span className="text-base sm:text-lg leading-none mt-0.5 select-none" aria-hidden="true">ℹ️</span>
              <div className="space-y-1.5">
                <p className="font-semibold text-blue-900">
                  Fulfillment &amp; Printing Guide
                </p>
                {platformFilter === 'shopee' ? (
                  <p className="text-blue-800 leading-relaxed text-xs sm:text-sm">
                    <strong>Shopee:</strong> Orders in <span className="font-semibold text-blue-900">Ready to Ship</span> require courier arrangement first. Click <span className="font-semibold text-blue-900">Arrange Shipment</span> to assign a courier and tracking code. Once arranged, status changes to <span className="font-semibold text-amber-800">Processed</span> and the AWB + packing list becomes selectable and printable.
                  </p>
                ) : platformFilter === 'lazada' ? (
                  <p className="text-blue-800 leading-relaxed text-xs sm:text-sm">
                    <strong>Lazada:</strong> Click <span className="font-semibold text-ink-950">Pack</span> to generate courier packages and AWBs. Once packed, orders in <span className="font-semibold text-amber-800">Packed</span> or <span className="font-semibold text-indigo-800">Ready to ship</span> already have their AWB generated and can be selected for printing anytime.
                  </p>
                ) : (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-2 text-blue-800 leading-relaxed pt-0.5 text-xs sm:text-sm">
                    <div className="p-2.5 rounded-xl bg-white/70 border border-blue-100/80">
                      <span className="font-semibold text-red-700 block mb-0.5">Shopee Orders:</span>
                      <span className="font-semibold text-blue-900">Ready to Ship</span> requires assigning a courier first. Click <span className="font-semibold">Arrange Shipment</span> → status moves to <span className="font-semibold text-amber-800">Processed</span> → AWB becomes printable.
                    </div>
                    <div className="p-2.5 rounded-xl bg-white/70 border border-blue-100/80">
                      <span className="font-semibold text-blue-700 block mb-0.5">Lazada Orders:</span>
                      Click <span className="font-semibold">Pack</span> to assign packages and generate the AWB. Orders in <span className="font-semibold text-amber-800">Packed</span> and <span className="font-semibold text-indigo-800">Ready to ship</span> already have their AWB generated and are ready to print.
                    </div>
                  </div>
                )}
              </div>
            </div>
            <button
              type="button"
              onClick={toggleFulfillmentGuide}
              className="text-xs text-blue-600 hover:text-blue-800 font-medium whitespace-nowrap px-2 py-1 rounded-md hover:bg-blue-100/60 transition cursor-pointer"
            >
              Hide
            </button>
          </div>
        </div>
      ) : (
        <div className="flex justify-end">
          <button
            type="button"
            onClick={toggleFulfillmentGuide}
            className="inline-flex items-center gap-1.5 text-xs text-ink-500 hover:text-blue-600 font-medium py-1 px-2.5 rounded-lg border border-ink-200 bg-white hover:border-blue-200 transition shadow-2xs cursor-pointer"
          >
            <span>ℹ️</span>
            <span>Show Fulfillment &amp; Printing Guide</span>
          </button>
        </div>
      )}

      <OrdersTable
        title="Orders"
        loading={loading}
        loadingText="Loading orders…"
        emptyText={
          stores.length === 0
            ? 'No stores linked yet. Link one from the Dashboard.'
            : 'No orders found for the current filters.'
        }
        rows={tableRows}
        columns={{ platform: true, store: showStoreColumn, buyer: true, shipBy: true }}
        allSelectableSelected={allSelectableSelected}
        disableSelectAll={selectableIds.length === 0 || bulkPrinting}
        onToggleSelectAll={toggleSelectAllPrintable}
        onOpenDetail={(row) => setDetailOrder(row.order)}
        onToggleSelected={(row) => toggleSelect(row.order)}
        getSelectTitle={(row) => {
          if (!isPrintable(row.order)) {
            const platform = orderPlatform(row.order);
            if (platform === 'shopee') {
              if (row.order.order_status === 'READY_TO_SHIP') {
                return "Shopee: Click 'Arrange Shipment' first to assign a courier before printing";
              }
              return 'Shopee: Only PROCESSED or SHIPPED orders can be printed';
            }
            if (row.order.order_status?.toLowerCase() === 'pending') {
              return "Lazada: Click 'Pack' first to generate the AWB before printing";
            }
            return 'Lazada: Only packed, ready to ship, or shipped orders can be printed';
          }
          return 'Select for bulk print';
        }}
        renderItems={(row) => {
          const items = row.order.items ?? [];
          if (items.length === 0) return <span className="text-sm text-ink-400">—</span>;
          if (orderPlatform(row.order) === 'shopee') {
            return (
              <ul className="text-sm text-ink-700 space-y-1">
                {items.map((item) => (
                  <li key={item.id} className="break-words leading-snug">
                    {item.item_name || `Item ${item.item_id}`}
                    <span className="text-ink-400"> × {item.quantity}</span>
                  </li>
                ))}
              </ul>
            );
          }
          const visibleItems = row.itemsExpanded ? items : items.slice(0, ITEMS_PREVIEW);
          return (
            <div>
              <ul className="text-sm text-ink-700 space-y-1">
                {visibleItems.map((item) => (
                  <li key={item.id} className="break-words leading-snug">
                    {item.item_name || `Item ${item.item_id}`}
                    <span className="text-ink-400"> × {item.quantity}</span>
                  </li>
                ))}
              </ul>
              {row.hiddenCount > 0 && (
                <button
                  type="button"
                  className="mt-1 text-xs font-semibold text-blue-600 hover:text-blue-700"
                  onClick={() => toggleItemsExpanded(row.order.id)}
                >
                  {row.itemsExpanded ? 'Show less' : `Show ${row.hiddenCount} more`}
                </button>
              )}
            </div>
          );
        }}
        renderStatus={(row) =>
          orderPlatform(row.order) === 'lazada' ? (
            <LazadaStatusBadge status={row.order.order_status} />
          ) : (
            <StatusBadge status={row.order.order_status} />
          )
        }
        renderActions={(row) => {
          const platform = orderPlatform(row.order);
          if (platform === 'lazada') {
            return (
              <div className="flex flex-wrap gap-1.5 justify-end">
                {canLazadaPack(row.order.order_status) && (
                  <button
                    disabled={row.busy || bulkPrinting}
                    onClick={() => runLazadaRowAction(row.order, 'pack')}
                    className="px-2 py-1 text-xs font-semibold bg-ink-950 text-white rounded-lg disabled:opacity-50"
                  >
                    Pack
                  </button>
                )}
                {canLazadaPrintAwb(row.order.order_status) && (
                  <button
                    disabled={row.busy || bulkPrinting}
                    onClick={() => runLazadaRowAction(row.order, 'awb')}
                    title="Print AWB + packing list"
                    className="px-2 py-1 text-xs font-semibold border border-ink-200 rounded-lg disabled:opacity-50 hover:bg-ink-50"
                  >
                    AWB + Packing
                  </button>
                )}
                {canLazadaReadyToShip(row.order.order_status) && (
                  <button
                    disabled={row.busy || bulkPrinting}
                    onClick={() => runLazadaRowAction(row.order, 'rts')}
                    className="px-2 py-1 text-xs font-semibold bg-blue-600 text-white rounded-lg disabled:opacity-50"
                  >
                    RTS
                  </button>
                )}
              </div>
            );
          }
          return (
            <div className="flex flex-col items-end gap-1.5">
              {canShip(row.order.order_status) && (
                <button
                  onClick={() => setArrangingOrder(row.order)}
                  className="px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition"
                >
                  Arrange Shipment
                </button>
              )}
              {canPrintLabel(row.order.order_status) && <PrintLabelButton order={row.order} />}
              {!canShip(row.order.order_status) &&
                !canPrintLabel(row.order.order_status) && (
                  <span className="text-xs text-ink-300">—</span>
                )}
            </div>
          );
        }}
        pagination={{
          page,
          lastPage,
          total,
          onPrev: () => setPage((p) => Math.max(1, p - 1)),
          onNext: () => setPage((p) => Math.min(lastPage, p + 1)),
        }}
      />

      {detailOrder && orderPlatform(detailOrder) === 'shopee' && (
        <OrderDetailDrawer
          orderId={detailOrder.id}
          onClose={() => setDetailOrder(null)}
          onShipped={() => fetchOrders()}
        />
      )}

      {detailOrder && orderPlatform(detailOrder) === 'lazada' && (
        <LazadaOrderDetailDrawer
          orderId={detailOrder.id}
          onClose={() => setDetailOrder(null)}
          onUpdated={fetchOrders}
        />
      )}

      {arrangingOrder && (
        <ArrangeShipmentModal
          order={arrangingOrder}
          onClose={() => setArrangingOrder(null)}
          onShipped={() => {
            setArrangingOrder(null);
            fetchOrders();
          }}
        />
      )}
    </div>
  );
}
