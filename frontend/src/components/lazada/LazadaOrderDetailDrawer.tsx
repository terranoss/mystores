import { useEffect, useState } from 'react';
import Spinner from '../Spinner';
import {
  LazadaStatusBadge,
  canLazadaPack,
  canLazadaPrintAwb,
  canLazadaReadyToShip,
  formatCurrency,
  formatDateTime,
} from './lazadaOrderHelpers';
import {
  getOrder,
  packLazadaOrder,
  downloadLazadaAwb,
  readyToShipLazadaOrder,
  type Order,
} from '../../api/client';
import { mergeAwbAndPacking, openPdfBlob } from '../../lib/shippingLabelPdf';
import { useToast } from '../../context/ToastContext';

interface Props {
  orderId: number;
  onClose: () => void;
  onUpdated?: () => void;
}

export default function LazadaOrderDetailDrawer({
  orderId,
  onClose,
  onUpdated,
}: Props) {
  const { showToast } = useToast();
  const [order, setOrder] = useState<Order | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);

  const reload = async () => {
    const data = await getOrder(orderId);
    setOrder(data);
  };

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);
    getOrder(orderId)
      .then((data) => {
        if (!cancelled) setOrder(data);
      })
      .catch((e: any) => {
        if (!cancelled) {
          setError(e?.response?.data?.message || 'Failed to load order.');
          setOrder(null);
        }
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [orderId]);

  const runAction = async (key: string, fn: () => Promise<void>) => {
    setBusy(key);
    setError(null);
    try {
      await fn();
      await reload();
      onUpdated?.();
    } catch (e: any) {
      const msg = e?.response?.data?.message || e?.message || 'Action failed.';
      setError(msg);
      showToast(msg, 'error');
    } finally {
      setBusy(null);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex justify-end">
      <div className="fixed inset-0 bg-ink-950/40 backdrop-blur-sm" onClick={onClose} />
      <aside className="relative h-full w-full max-w-lg bg-white shadow-2xl border-l border-ink-100 flex flex-col">
        <div className="px-6 py-5 border-b border-ink-100 flex items-start justify-between gap-3">
          <div className="min-w-0">
            <p className="text-xs font-semibold uppercase tracking-wider text-ink-400">
              Lazada order
            </p>
            <h3 className="font-heading text-lg font-semibold text-ink-950 font-mono truncate mt-1">
              {order?.order_sn || '…'}
            </h3>
            {order && (
              <div className="mt-2 flex flex-wrap items-center gap-2">
                <LazadaStatusBadge status={order.order_status} />
                {order.store?.shop_name && (
                  <span className="text-xs text-ink-500">{order.store.shop_name}</span>
                )}
              </div>
            )}
          </div>
          <button onClick={onClose} className="text-ink-400 hover:text-ink-700 text-xl leading-none" aria-label="Close">
            ✕
          </button>
        </div>

        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          {loading ? (
            <div className="flex items-center justify-center py-16 gap-2 text-ink-400 text-sm">
              <Spinner /> Loading order…
            </div>
          ) : error && !order ? (
            <div className="p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">{error}</div>
          ) : order ? (
            <>
              {error && (
                <div className="p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">{error}</div>
              )}
              <section className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-xs font-semibold uppercase tracking-wider text-ink-400">Total</p>
                  <p className="mt-1 font-mono font-semibold text-ink-950">
                    {formatCurrency(order.total_amount, order.currency)}
                  </p>
                </div>
                <div>
                  <p className="text-xs font-semibold uppercase tracking-wider text-ink-400">Order date</p>
                  <p className="mt-1 text-sm text-ink-700">{formatDateTime(order.ordered_at)}</p>
                </div>
                <div>
                  <p className="text-xs font-semibold uppercase tracking-wider text-ink-400">Buyer</p>
                  <p className="mt-1 text-sm text-ink-700">{order.buyer_username || '—'}</p>
                </div>
              </section>

              {order.shipping_address && (
                <section>
                  <p className="text-xs font-semibold uppercase tracking-wider text-ink-400 mb-2">Shipping address</p>
                  <p className="text-sm text-ink-700 whitespace-pre-wrap">{order.shipping_address}</p>
                </section>
              )}

              <section>
                <p className="text-xs font-semibold uppercase tracking-wider text-ink-400 mb-2">Items</p>
                <ul className="space-y-2">
                  {order.items.map((item) => (
                    <li key={item.id} className="rounded-xl border border-ink-100 bg-ink-50/50 px-3 py-2">
                      <p className="text-sm font-medium text-ink-900">{item.item_name || `Item #${item.item_id}`}</p>
                      <p className="text-xs text-ink-500 mt-0.5 font-mono">
                        Qty {item.quantity} · {formatCurrency(item.price, order.currency)}
                        {item.model_id != null ? ` · Item ID ${item.model_id}` : ''}
                      </p>
                    </li>
                  ))}
                </ul>
              </section>

              <section className="space-y-2">
                <p className="text-xs font-semibold uppercase tracking-wider text-ink-400">Fulfillment</p>
                <p className="text-xs text-ink-500">Lazada flow: Pack → Print AWB → Ready to ship</p>
                <div className="flex flex-col gap-2">
                  {canLazadaPack(order.order_status) && (
                    <button
                      disabled={!!busy}
                      onClick={() =>
                        runAction('pack', async () => {
                          await packLazadaOrder(order.id);
                          showToast('Order packed.', 'success');
                        })
                      }
                      className="px-4 py-2.5 text-sm font-semibold bg-ink-950 text-white rounded-xl hover:bg-ink-800 disabled:opacity-50"
                    >
                      {busy === 'pack' ? 'Packing…' : 'Pack order'}
                    </button>
                  )}
                  {canLazadaPrintAwb(order.order_status) && (
                    <button
                      disabled={!!busy}
                      onClick={() =>
                        runAction('awb', async () => {
                          const docs = await downloadLazadaAwb(order.id);
                          const mergedBytes = await mergeAwbAndPacking(docs.awbPdf, docs.packingListPdf);
                          openPdfBlob(mergedBytes, docs.filename || `lazada-awb-packing-${order.order_sn}.pdf`);
                          showToast('AWB + Packing opened.', 'success');
                        })
                      }
                      title="Print AWB + packing list"
                      className="px-4 py-2.5 text-sm font-semibold border border-ink-200 text-ink-800 rounded-xl hover:bg-ink-50 disabled:opacity-50"
                    >
                      {busy === 'awb' ? 'Loading…' : 'Print AWB + Packing'}
                    </button>
                  )}
                  {canLazadaReadyToShip(order.order_status) && (
                    <button
                      disabled={!!busy}
                      onClick={() =>
                        runAction('rts', async () => {
                          await readyToShipLazadaOrder(order.id);
                          showToast('Marked ready to ship.', 'success');
                        })
                      }
                      className="px-4 py-2.5 text-sm font-semibold bg-orange-500 text-white rounded-xl hover:bg-orange-600 disabled:opacity-50"
                    >
                      {busy === 'rts' ? 'Updating…' : 'Ready to ship'}
                    </button>
                  )}
                </div>
                <p className="text-xs text-ink-400 mt-3">
                  Cancel only on the official Shopee/Lazada seller website.
                </p>
              </section>
            </>
          ) : null}
        </div>
      </aside>
    </div>
  );
}
