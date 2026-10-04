import { useEffect, useMemo, useRef, useState } from 'react';
import Spinner from '../components/Spinner';
import OrdersTable from '../components/orders/OrdersTable';
import type { OrdersTableRow } from '../components/orders/ordersTableTypes';
import LazadaOrderDetailDrawer from '../components/lazada/LazadaOrderDetailDrawer';
import {
  LazadaStatusBadge,
  LAZADA_STATUS_LABELS,
  canLazadaPack,
  canLazadaPrintAwb,
  canLazadaReadyToShip,
  formatCurrency,
  formatDate,
} from '../components/lazada/lazadaOrderHelpers';
import { useStore } from '../context/StoreContext';
import { useToast } from '../context/ToastContext';
import {
  getOrders,
  syncLazadaOrders,
  packLazadaOrder,
  downloadLazadaAwb,
  downloadLazadaAwbsBulk,
  readyToShipLazadaOrder,
  type Order,
} from '../api/client';
import {
  mergeAwbAndPacking,
  mergeBulkShippingDocuments,
  openPdfBlob,
} from '../lib/shippingLabelPdf';

const BULK_PRINT_MAX = 15;
const ITEMS_PREVIEW = 2;

interface LazadaTableRow extends OrdersTableRow {
  order: Order;
  busy: boolean;
  itemsExpanded: boolean;
  hiddenCount: number;
}

type FulfillmentPreset = '' | 'to_pack' | 'packed' | 'to_handover' | 'in_transit' | 'completed' | 'problems';
type DatePreset = '' | 'today' | '7d' | '14d';
type SortPreset = 'newest' | 'total';

const FULFILLMENT_PRESETS: Record<
  Exclude<FulfillmentPreset, ''>,
  { label: string; statusIn: string[] }
> = {
  to_pack: { label: 'To pack', statusIn: ['pending'] },
  packed: { label: 'Packed / print', statusIn: ['packed'] },
  to_handover: { label: 'To hand over', statusIn: ['ready_to_ship'] },
  in_transit: { label: 'In transit', statusIn: ['shipped'] },
  completed: { label: 'Completed', statusIn: ['delivered', 'confirmed'] },
  problems: { label: 'Problems', statusIn: ['canceled', 'returned', 'failed'] },
};

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

export default function LazadaOrdersPanel() {
  const { stores, selectedStoreId } = useStore();
  const lazadaStores = useMemo(
    () => stores.filter((s) => s.platform === 'lazada'),
    [stores]
  );
  const { showToast } = useToast();
  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [syncing, setSyncing] = useState(false);
  const [statusFilter, setStatusFilter] = useState('');
  const [preset, setPreset] = useState<FulfillmentPreset>('');
  const [datePreset, setDatePreset] = useState<DatePreset>('');
  const [sortPreset, setSortPreset] = useState<SortPreset>('newest');
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [detailOrderId, setDetailOrderId] = useState<number | null>(null);
  const [rowBusy, setRowBusy] = useState<number | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [bulkPrinting, setBulkPrinting] = useState(false);
  const [expandedItemIds, setExpandedItemIds] = useState<Set<number>>(() => new Set());
  const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const showStoreColumn = selectedStoreId === null;

  const printableOrders = useMemo(
    () => orders.filter((o) => canLazadaPrintAwb(o.order_status)),
    [orders]
  );
  const printableIds = useMemo(
    () => printableOrders.map((o) => Number(o.id)),
    [printableOrders]
  );
  const selectableIds = useMemo(
    () => printableIds.slice(0, BULK_PRINT_MAX),
    [printableIds]
  );
  const allSelectableSelected =
    selectableIds.length > 0 && selectableIds.every((id) => selectedIds.includes(id));
  const selectedCount = selectedIds.length;

  const fetchOrders = async () => {
    setLoading(true);
    setError(null);
    try {
      const params: Parameters<typeof getOrders>[0] = { page, platform: 'lazada' };
      if (selectedStoreId !== null) params.store_id = selectedStoreId;
      if (statusFilter !== '') params.status = statusFilter;
      else if (preset !== '') params.status_in = FULFILLMENT_PRESETS[preset].statusIn;
      if (search.trim() !== '') params.search = search.trim();
      const range = resolveDateRange(datePreset);
      if (range.from) params.ordered_from = range.from;
      if (range.to) params.ordered_to = range.to;
      if (sortPreset === 'total') {
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
      setError(e?.response?.data?.message || 'Failed to load Lazada orders.');
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
  }, [selectedStoreId, statusFilter, preset, datePreset, sortPreset]);

  useEffect(() => {
    setSelectedIds([]);
    setExpandedItemIds(new Set());
  }, [selectedStoreId, statusFilter, preset, datePreset, sortPreset, search, page]);

  useEffect(() => {
    fetchOrders();
  }, [selectedStoreId, statusFilter, preset, datePreset, sortPreset, search, page]);

  const toggleSelect = (orderId: number, printable: boolean) => {
    if (!printable) return;
    setSelectedIds((prev) => {
      if (prev.includes(orderId)) return prev.filter((id) => id !== orderId);
      if (prev.length >= BULK_PRINT_MAX) {
        showToast(`You can print at most ${BULK_PRINT_MAX} labels at once.`, 'error');
        return prev;
      }
      return [...prev, orderId];
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
    setBulkPrinting(true);
    try {
      const result = await downloadLazadaAwbsBulk(ids);
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
      const results = await syncLazadaOrders();
      showToast(results.join('\n') || 'Lazada sync completed.', 'success');
      fetchOrders();
    } catch {
      showToast('Lazada sync failed.', 'error');
    } finally {
      setSyncing(false);
    }
  };

  const runRowAction = async (order: Order, action: 'pack' | 'awb' | 'rts') => {
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

  const tableRows: LazadaTableRow[] = orders.map((order) => {
    const printable = canLazadaPrintAwb(order.order_status);
    const items = order.items ?? [];
    const itemsExpanded = expandedItemIds.has(order.id);
    return {
      id: order.id,
      order,
      platform: 'lazada',
      orderSn: order.order_sn,
      orderMeta: order.synced_at ? `Synced ${formatDate(order.synced_at)}` : '—',
      orderedAtText: formatDate(order.ordered_at),
      storeName: order.store?.shop_name || `Store #${order.store_id}`,
      buyerText: order.buyer_username || '—',
      shipByText: '—',
      shipByTone: 'none',
      totalText: formatCurrency(order.total_amount, order.currency),
      selectable: printable && !bulkPrinting,
      selected: selectedIds.includes(Number(order.id)),
      busy: rowBusy === order.id,
      itemsExpanded,
      hiddenCount: Math.max(0, items.length - ITEMS_PREVIEW),
    };
  });

  return (
    <div className="w-full max-w-none space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <p className="text-ink-400 text-sm">
          Lazada orders — pack, print AWB, then ready to ship.
        </p>
        <div className="flex items-center gap-3 flex-wrap">
          <button
            onClick={handleBulkPrint}
            disabled={bulkPrinting || selectedCount === 0}
            title="Print AWB + packing list for selected orders"
            className="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl border border-orange-200 text-orange-700 hover:bg-orange-50 disabled:opacity-50 transition"
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
            disabled={syncing || lazadaStores.length === 0}
            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold bg-orange-500 text-white rounded-xl hover:bg-orange-600 disabled:opacity-50"
          >
            {syncing && <Spinner />}
            {syncing ? 'Syncing…' : 'Sync Lazada'}
          </button>
        </div>
      </div>

      <div className="bg-white p-4 rounded-2xl border border-ink-100 shadow-sm space-y-3">
        <div className="flex flex-wrap gap-2">
          {(Object.keys(FULFILLMENT_PRESETS) as Array<Exclude<FulfillmentPreset, ''>>).map((key) => (
            <button
              key={key}
              onClick={() => {
                setPreset((p) => (p === key ? '' : key));
                setStatusFilter('');
              }}
              className={`px-3 py-1.5 rounded-full text-xs font-semibold border transition ${
                preset === key
                  ? 'bg-orange-500 text-white border-orange-500'
                  : 'bg-white text-ink-600 border-ink-200 hover:bg-ink-50'
              }`}
            >
              {FULFILLMENT_PRESETS[key].label}
            </button>
          ))}
        </div>
        <div className="flex flex-col sm:flex-row gap-3 flex-wrap">
          <input
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            placeholder="Search order ID, buyer, item…"
            className="flex-1 min-w-[180px] px-3 py-2 rounded-xl border border-ink-200 text-sm"
          />
          <select
            value={statusFilter}
            onChange={(e) => {
              setStatusFilter(e.target.value);
              setPreset('');
            }}
            className="px-3 py-2 rounded-xl border border-ink-200 text-sm"
          >
            <option value="">All statuses</option>
            {Object.entries(LAZADA_STATUS_LABELS)
              .filter(([k]) => k !== 'cancelled')
              .map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
          </select>
          <select
            value={datePreset}
            onChange={(e) => setDatePreset(e.target.value as DatePreset)}
            className="px-3 py-2 rounded-xl border border-ink-200 text-sm"
          >
            <option value="">Any date</option>
            <option value="today">Today</option>
            <option value="7d">Last 7 days</option>
            <option value="14d">Last 14 days</option>
          </select>
          <select
            value={sortPreset}
            onChange={(e) => setSortPreset(e.target.value as SortPreset)}
            className="px-3 py-2 rounded-xl border border-ink-200 text-sm"
          >
            <option value="newest">Newest</option>
            <option value="total">Highest total</option>
          </select>
        </div>
      </div>

      {error && (
        <div className="p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">{error}</div>
      )}

      <div className="bg-white rounded-2xl border border-ink-100 shadow-sm overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center py-20 gap-2 text-ink-400 text-sm">
            <Spinner /> Loading Lazada orders…
          </div>
        ) : orders.length === 0 ? (
          <div className="p-12 text-center text-sm text-ink-400">
            {lazadaStores.length === 0
              ? 'No Lazada stores linked yet. Link one from the Dashboard.'
              : 'No Lazada orders found. Try Sync Lazada.'}
          </div>
        ) : (
          <OrdersTable
            title="Lazada orders"
            loadingText="Loading Lazada orders…"
            emptyText="No Lazada orders found. Try Sync Lazada."
            rows={tableRows}
            columns={{ store: showStoreColumn }}
            allSelectableSelected={allSelectableSelected}
            disableSelectAll={selectableIds.length === 0 || bulkPrinting}
            onToggleSelectAll={toggleSelectAllPrintable}
            onOpenDetail={(row) => setDetailOrderId(row.order.id)}
            onToggleSelected={(row) => toggleSelect(Number(row.order.id), canLazadaPrintAwb(row.order.order_status))}
            getSelectTitle={(row) =>
              canLazadaPrintAwb(row.order.order_status)
                ? 'Select for bulk print'
                : 'Only packed, ready to ship, or shipped orders can be printed'
            }
            renderItems={(row) => {
              const items = row.order.items ?? [];
              const visibleItems = row.itemsExpanded ? items : items.slice(0, ITEMS_PREVIEW);
              return items.length === 0 ? (
                <span className="text-sm text-ink-400">—</span>
              ) : (
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
                      className="mt-1 text-xs font-semibold text-orange-600 hover:text-orange-700"
                      onClick={() => toggleItemsExpanded(row.order.id)}
                    >
                      {row.itemsExpanded ? 'Show less' : `Show ${row.hiddenCount} more`}
                    </button>
                  )}
                </div>
              );
            }}
            renderStatus={(row) => <LazadaStatusBadge status={row.order.order_status} />}
            renderActions={(row) => (
              <div className="flex flex-wrap gap-1.5 justify-end">
                {canLazadaPack(row.order.order_status) && (
                  <button
                    disabled={row.busy || bulkPrinting}
                    onClick={() => runRowAction(row.order, 'pack')}
                    className="px-2 py-1 text-xs font-semibold bg-ink-950 text-white rounded-lg disabled:opacity-50"
                  >
                    Pack
                  </button>
                )}
                {canLazadaPrintAwb(row.order.order_status) && (
                  <button
                    disabled={row.busy || bulkPrinting}
                    onClick={() => runRowAction(row.order, 'awb')}
                    title="Print AWB + packing list"
                    className="px-2 py-1 text-xs font-semibold border border-ink-200 rounded-lg disabled:opacity-50 hover:bg-ink-50"
                  >
                    AWB + Packing
                  </button>
                )}
                {canLazadaReadyToShip(row.order.order_status) && (
                  <button
                    disabled={row.busy || bulkPrinting}
                    onClick={() => runRowAction(row.order, 'rts')}
                    className="px-2 py-1 text-xs font-semibold bg-orange-500 text-white rounded-lg disabled:opacity-50"
                  >
                    RTS
                  </button>
                )}

              </div>
            )}
            pagination={{
              page,
              lastPage,
              total,
              onPrev: () => setPage((p) => Math.max(1, p - 1)),
              onNext: () => setPage((p) => p + 1),
            }}
          />
        )}
      </div>

      {detailOrderId !== null && (
        <LazadaOrderDetailDrawer
          orderId={detailOrderId}
          onClose={() => setDetailOrderId(null)}
          onUpdated={fetchOrders}
        />
      )}
    </div>
  );
}
