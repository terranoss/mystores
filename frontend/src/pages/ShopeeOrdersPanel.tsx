import { useState, useEffect, useRef, useMemo } from 'react';
import Spinner from '../components/Spinner';
import OrderDetailDrawer from '../components/OrderDetailDrawer';
import ArrangeShipmentModal from '../components/ArrangeShipmentModal';
import OrdersTable from '../components/orders/OrdersTable';
import type { OrdersTableRow } from '../components/orders/ordersTableTypes';
import PrintLabelButton from '../components/PrintLabelButton';
import {
  StatusBadge,
  STATUS_LABELS,
  canShip,
  canPrintLabel,
  formatCurrency,
  formatDate,
  getShipByInfo,
} from '../components/orderHelpers';
import { useStore } from '../context/StoreContext';
import { useToast } from '../context/ToastContext';
import {
  getOrders,
  syncOrders,
  downloadShippingLabelsBulk,
  type Order,
} from '../api/client';
import { mergeBulkShippingDocuments, openPdfBlob } from '../lib/shippingLabelPdf';

const BULK_PRINT_MAX = 15;

type UrgencyFilter = '' | 'overdue' | 'due_today' | 'due_48h';
type FulfillmentPreset = '' | 'to_arrange' | 'to_print' | 'in_transit' | 'problems';
type DatePreset = '' | 'today' | '7d' | '14d';
type SortPreset = 'newest' | 'ship_by' | 'total';

const FULFILLMENT_PRESETS: Record<
  Exclude<FulfillmentPreset, ''>,
  { label: string; statusIn: string[] }
> = {
  to_arrange: { label: 'To arrange', statusIn: ['READY_TO_SHIP'] },
  to_print: { label: 'To print', statusIn: ['PROCESSED'] },
  in_transit: { label: 'In transit', statusIn: ['SHIPPED', 'TO_CONFIRM_RECEIVE'] },
  problems: { label: 'Problems', statusIn: ['IN_CANCEL', 'TO_RETURN', 'CANCELLED'] },
};

const URGENCY_CHIPS: Array<{ value: UrgencyFilter; label: string }> = [
  { value: '', label: 'All' },
  { value: 'overdue', label: 'Overdue' },
  { value: 'due_today', label: 'Due today' },
  { value: 'due_48h', label: 'Due in 48h' },
];

function localYmd(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function resolveDateRange(preset: DatePreset): { from?: string; to?: string } {
  if (!preset) return {};
  const start = new Date();
  start.setHours(0, 0, 0, 0);
  const to = localYmd(start);
  if (preset === 'today') return { from: to, to };
  const from = new Date(start);
  from.setDate(from.getDate() - (preset === '7d' ? 6 : 13));
  return { from: localYmd(from), to };
}

interface ShopeeTableRow extends OrdersTableRow {
  order: Order;
}

export default function ShopeeOrdersPanel() {
  const { selectedStoreId } = useStore();
  const { showToast } = useToast();
  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [syncing, setSyncing] = useState(false);
  const [statusFilter, setStatusFilter] = useState('');
  const [preset, setPreset] = useState<FulfillmentPreset>('');
  const [urgency, setUrgency] = useState<UrgencyFilter>('');
  const [datePreset, setDatePreset] = useState<DatePreset>('');
  const [sortPreset, setSortPreset] = useState<SortPreset>('newest');
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [detailOrderId, setDetailOrderId] = useState<number | null>(null);
  const [arrangingOrder, setArrangingOrder] = useState<Order | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [bulkPrinting, setBulkPrinting] = useState(false);
  const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const showStoreColumn = selectedStoreId === null;
  const printableOrders = useMemo(
    () => orders.filter((o) => canPrintLabel(o.order_status)),
    [orders],
  );
  const printableIds = useMemo(
    () => printableOrders.map((o) => Number(o.id)),
    [printableOrders],
  );
  const selectableIds = useMemo(
    () => printableIds.slice(0, BULK_PRINT_MAX),
    [printableIds],
  );
  const allSelectableSelected =
    selectableIds.length > 0 && selectableIds.every((id) => selectedIds.includes(id));
  const selectedCount = selectedIds.length;

  const fetchOrders = async () => {
    setLoading(true);
    setError(null);
    try {
      const params: Parameters<typeof getOrders>[0] = { page };
      if (selectedStoreId !== null) params.store_id = selectedStoreId;
      params.platform = 'shopee';
      if (statusFilter !== '') {
        params.status = statusFilter;
      } else if (preset !== '') {
        params.status_in = FULFILLMENT_PRESETS[preset].statusIn;
      }
      if (search.trim() !== '') params.search = search.trim();
      if (urgency !== '') params.urgency = urgency;
      const range = resolveDateRange(datePreset);
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
    setPage(1);
  }, [selectedStoreId, statusFilter, preset, urgency, datePreset, sortPreset]);

  useEffect(() => {
    setSelectedIds([]);
  }, [selectedStoreId, statusFilter, preset, urgency, datePreset, sortPreset, search, page]);

  useEffect(() => {
    fetchOrders();
  }, [selectedStoreId, statusFilter, preset, urgency, datePreset, sortPreset, search, page]);

  const toggleSelect = (orderId: number, printable: boolean) => {
    if (!printable) return;
    const id = Number(orderId);
    setSelectedIds((prev) => {
      if (prev.includes(id)) {
        return prev.filter((x) => x !== id);
      }
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

  const handleBulkPrint = async () => {
    if (selectedCount === 0) return;
    const ids = [...selectedIds];
    setBulkPrinting(true);
    try {
      const result = await downloadShippingLabelsBulk(ids);
      if (result.documents.length === 0) {
        const detail = result.errors.map((e) => e.message).join('; ') || 'No labels generated.';
        showToast(detail, 'error');
        return;
      }
      const merged = await mergeBulkShippingDocuments(
        result.documents.map((d) => ({
          awbPdf: d.awbPdf,
          packingListPdf: d.packingListPdf,
        })),
      );
      openPdfBlob(merged, result.filename);
      if (result.errors.length > 0) {
        showToast(
          `Printed ${result.documents.length} label(s). ${result.errors.length} failed.`,
          'error',
        );
      } else {
        showToast(`Printed ${result.documents.length} label(s).`, 'success');
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
      const results = await syncOrders('shopee');
      showToast(results.join('\n') || 'Sync completed!', 'success');
      fetchOrders();
    } catch {
      showToast('Sync failed.', 'error');
    } finally {
      setSyncing(false);
    }
  };

  const STATUS_OPTIONS = [
    { value: '', label: 'All Statuses' },
    ...Object.entries(STATUS_LABELS).map(([value, label]) => ({
      value,
      label,
    })),
  ];

  const tableRows: ShopeeTableRow[] = orders.map((order) => {
    const shipBy = getShipByInfo(order);
    const printable = canPrintLabel(order.order_status);
    return {
      id: order.id,
      order,
      orderSn: order.order_sn,
      platform: 'shopee',
      orderMeta: order.paid_time
        ? `Paid ${formatDate(order.paid_time)}`
        : order.synced_at
          ? `Synced ${formatDate(order.synced_at)}`
          : '—',
      orderedAtText: order.ordered_at ? formatDate(order.ordered_at) : '—',
      storeName: order.store?.shop_name || '—',
      buyerText: order.buyer_username || '—',
      shipByText: shipBy.label,
      shipByTone: shipBy.tone,
      totalText: formatCurrency(order.total_amount, order.currency),
      selectable: printable && !bulkPrinting,
      selected: selectedIds.includes(Number(order.id)),
    };
  });

  return (
      <div className="w-full max-w-none space-y-6">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <p className="text-ink-400 text-sm">
            Shopee orders across your linked Shopee stores.
          </p>
          <div className="flex items-center gap-3 flex-wrap">
            <input
              type="search"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Order SN, buyer, or item…"
              className="px-3 py-2 rounded-xl border border-ink-200 text-sm bg-white w-44 sm:w-56 focus:outline-none focus:ring-2 focus:ring-clay-400/40"
            />
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
              value={preset}
              onChange={(e) => {
                setPreset(e.target.value as FulfillmentPreset);
                setStatusFilter('');
              }}
              className="px-3 py-2 rounded-xl border border-ink-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-clay-400/40"
              title="Fulfillment queue"
            >
              <option value="">All queues</option>
              {Object.entries(FULFILLMENT_PRESETS).map(([value, meta]) => (
                <option key={value} value={value}>
                  {meta.label}
                </option>
              ))}
            </select>

            <select
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value);
                if (e.target.value !== '') setPreset('');
              }}
              className="px-3 py-2 rounded-xl border border-ink-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-clay-400/40"
              title="Exact status"
            >
              {STATUS_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.value === '' ? 'All statuses' : o.label}
                </option>
              ))}
            </select>

            <select
              value={datePreset}
              onChange={(e) => setDatePreset(e.target.value as DatePreset)}
              className="px-3 py-2 rounded-xl border border-ink-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-clay-400/40"
              title="Order date"
            >
              <option value="">Any date</option>
              <option value="today">Ordered today</option>
              <option value="7d">Last 7 days</option>
              <option value="14d">Last 14 days</option>
            </select>

            <select
              value={sortPreset}
              onChange={(e) => setSortPreset(e.target.value as SortPreset)}
              className="px-3 py-2 rounded-xl border border-ink-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-clay-400/40"
              title="Sort"
            >
              <option value="newest">Newest ordered</option>
              <option value="ship_by">Ship by (soonest)</option>
              <option value="total">Highest total</option>
            </select>

            <button
              onClick={handleBulkPrint}
              disabled={bulkPrinting || selectedCount === 0}
              title="Print AWB + Packing for selected orders"
              className="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl border border-indigo-200 text-indigo-700 hover:bg-indigo-50 disabled:opacity-50 transition active:scale-[0.97]"
            >
              {bulkPrinting && <Spinner />}
              {bulkPrinting
                ? 'Generating…'
                : selectedCount > 0
                  ? `Print selected (${selectedCount})`
                  : 'Print selected'}
            </button>

            <button
              onClick={handleSync}
              disabled={syncing}
              className="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold bg-ink-950 text-white rounded-xl hover:bg-ink-800 disabled:opacity-50 transition active:scale-[0.97]"
            >
              {syncing && <Spinner />}
              {syncing ? 'Syncing…' : 'Sync Now'}
            </button>
          </div>
        </div>

        {loading ? (
          <div className="bg-white rounded-2xl border border-ink-100 shadow-sm overflow-hidden">
            <div className="divide-y divide-ink-50">
              {[1, 2, 3, 4].map((i) => (
                <div key={i} className="px-6 py-5 animate-pulse">
                  <div className="flex items-center gap-4">
                    <div className="flex-1 space-y-2">
                      <div className="h-4 bg-ink-100 rounded w-48" />
                      <div className="h-3 bg-ink-50 rounded w-32" />
                    </div>
                    <div className="h-6 bg-ink-100 rounded-full w-24" />
                    <div className="h-6 bg-ink-100 rounded w-16" />
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
              Failed to load orders
            </h4>
            <p className="text-sm text-ink-400 max-w-sm mx-auto mb-6">
              {error}
            </p>
            <button
              onClick={fetchOrders}
              className="px-5 py-2.5 text-sm font-semibold bg-ink-950 text-white rounded-xl hover:bg-ink-800 transition active:scale-[0.97]"
            >
              Retry
            </button>
          </div>
        ) : orders.length === 0 ? (
          <div className="bg-white rounded-2xl border border-ink-100 shadow-sm p-12 text-center">
            <div className="w-14 h-14 rounded-2xl bg-clay-100 flex items-center justify-center mx-auto mb-4">
              <span className="text-2xl text-clay-500">◈</span>
            </div>
            <h4 className="font-heading text-base font-semibold text-ink-950 mb-1">
              No orders found
            </h4>
            <p className="text-sm text-ink-400 max-w-sm mx-auto mb-6">
              {search || statusFilter || preset || urgency || datePreset
                ? 'Try a different search or filter.'
                : 'Sync your Shopee stores to pull in orders and manage them from here.'}
            </p>
            {!search && !statusFilter && !preset && !urgency && !datePreset && (
              <button
                onClick={handleSync}
                disabled={syncing}
                className="inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm font-semibold bg-ink-950 text-white rounded-xl hover:bg-ink-800 disabled:opacity-50 transition active:scale-[0.97]"
              >
                {syncing && <Spinner />}
                {syncing ? 'Syncing…' : 'Sync Now'}
              </button>
            )}
          </div>
        ) : (
          <OrdersTable
            title="Shopee orders"
            loadingText="Loading orders…"
            emptyText="No orders found for the current filters."
            rows={tableRows}
            columns={{ store: showStoreColumn, buyer: true, shipBy: true }}
            allSelectableSelected={allSelectableSelected}
            disableSelectAll={selectableIds.length === 0 || bulkPrinting}
            onToggleSelectAll={toggleSelectAllPrintable}
            onOpenDetail={(row) => setDetailOrderId(row.order.id)}
            onToggleSelected={(row) => toggleSelect(Number(row.order.id), canPrintLabel(row.order.order_status))}
            getSelectTitle={(row) =>
              canPrintLabel(row.order.order_status)
                ? 'Select for bulk print'
                : 'Only PROCESSED or SHIPPED orders can be printed'
            }
            renderItems={(row) =>
              !row.order.items || row.order.items.length === 0 ? (
                <span className="text-sm text-ink-400">—</span>
              ) : (
                <ul className="text-sm text-ink-700 space-y-1">
                  {row.order.items.map((item) => (
                    <li key={item.id} className="break-words leading-snug">
                      {item.item_name || `Item ${item.item_id}`}
                      <span className="text-ink-400"> × {item.quantity}</span>
                    </li>
                  ))}
                </ul>
              )
            }
            renderStatus={(row) => <StatusBadge status={row.order.order_status} />}
            renderActions={(row) => (
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
                  !canPrintLabel(row.order.order_status) && <span className="text-xs text-ink-300">—</span>}
              </div>
            )}
            pagination={{
              page,
              lastPage,
              total,
              onPrev: () => setPage((p) => Math.max(1, p - 1)),
              onNext: () => setPage((p) => Math.min(lastPage, p + 1)),
            }}
          />
        )}

        {detailOrderId !== null && (
          <OrderDetailDrawer
            orderId={detailOrderId}
            onClose={() => setDetailOrderId(null)}
            onShipped={() => fetchOrders()}
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
