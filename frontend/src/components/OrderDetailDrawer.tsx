import { useEffect, useState } from 'react';
import Spinner from './Spinner';
import ArrangeShipmentModal from './ArrangeShipmentModal';
import PrintLabelButton from './PrintLabelButton';
import { canPrintLabel, canShip } from '../lib/orderStatus';
import { formatCurrency, formatDateTime, getShipByInfo } from '../lib/orderFormat';
import OrderStatusBadge from '../lib/OrderStatusBadge';
import { getOrder, type Order } from '../api/client';

interface OrderDetailDrawerProps {
  orderId: number;
  onClose: () => void;
  onShipped?: (order: Order) => void;
}

export default function OrderDetailDrawer({
  orderId,
  onClose,
  onShipped,
}: OrderDetailDrawerProps) {
  const [order, setOrder] = useState<Order | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [arrangingShipment, setArrangingShipment] = useState(false);

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

  const shipBy = order ? getShipByInfo(order) : null;

  return (
    <div className="fixed inset-0 z-50 flex justify-end">
      <div
        className="fixed inset-0 bg-ink-950/40 backdrop-blur-sm"
        onClick={onClose}
      />
      <aside className="relative h-full w-full max-w-lg bg-white shadow-2xl border-l border-ink-100 flex flex-col animate-in slide-in-from-right">
        <div className="px-6 py-5 border-b border-ink-100 flex items-start justify-between gap-3">
          <div className="min-w-0">
            <p className="text-xs font-semibold uppercase tracking-wider text-ink-400">
              Order detail
            </p>
            <h3 className="font-heading text-lg font-semibold text-ink-950 font-mono truncate mt-1">
              {order?.order_sn || '…'}
            </h3>
            {order && (
              <div className="mt-2 flex flex-wrap items-center gap-2">
                <OrderStatusBadge
                  status={order.order_status}
                  canonical={order.canonical_status}
                />
                {order.store?.shop_name && (
                  <span className="text-xs text-ink-500">
                    {order.store.shop_name}
                  </span>
                )}
              </div>
            )}
          </div>
          <button
            onClick={onClose}
            className="text-ink-400 hover:text-ink-700 transition text-xl leading-none shrink-0"
            aria-label="Close"
          >
            ✕
          </button>
        </div>

        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          {loading ? (
            <div className="flex items-center justify-center py-16 gap-2 text-ink-400 text-sm">
              <Spinner /> Loading order…
            </div>
          ) : error ? (
            <div className="p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">
              {error}
            </div>
          ) : order ? (
            <>
              <section className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-xs font-semibold uppercase tracking-wider text-ink-400">
                    Total
                  </p>
                  <p className="mt-1 font-mono font-semibold text-ink-950">
                    {formatCurrency(order.total_amount, order.currency)}
                  </p>
                </div>
                <div>
                  <p className="text-xs font-semibold uppercase tracking-wider text-ink-400">
                    Order date
                  </p>
                  <p className="mt-1 text-sm text-ink-700">
                    {formatDateTime(order.ordered_at)}
                  </p>
                </div>
                <div>
                  <p className="text-xs font-semibold uppercase tracking-wider text-ink-400">
                    Paid
                  </p>
                  <p className="mt-1 text-sm text-ink-700">
                    {formatDateTime(order.paid_time)}
                  </p>
                </div>
                <div>
                  <p className="text-xs font-semibold uppercase tracking-wider text-ink-400">
                    Ship by
                  </p>
                  <p
                    className={`mt-1 text-sm font-medium ${
                      shipBy?.tone === 'overdue'
                        ? 'text-red-600'
                        : shipBy?.tone === 'due_today'
                          ? 'text-amber-600'
                          : 'text-ink-700'
                    }`}
                  >
                    {shipBy?.label || '—'}
                  </p>
                </div>
              </section>

              <section>
                <p className="text-xs font-semibold uppercase tracking-wider text-ink-400 mb-2">
                  Buyer
                </p>
                <p className="text-sm text-ink-900">
                  {order.buyer_username || '—'}
                </p>
                {order.shipping_address && (
                  <p className="mt-2 text-sm text-ink-500 leading-relaxed">
                    {order.shipping_address}
                  </p>
                )}
              </section>

              <section>
                <p className="text-xs font-semibold uppercase tracking-wider text-ink-400 mb-3">
                  Items
                </p>
                {order.items?.length ? (
                  <div className="rounded-xl border border-ink-100 overflow-hidden">
                    <table className="w-full text-sm">
                      <thead>
                        <tr className="bg-ink-50/80 border-b border-ink-100">
                          <th className="text-left px-3 py-2 text-xs font-semibold text-ink-500">
                            Item
                          </th>
                          <th className="text-right px-3 py-2 text-xs font-semibold text-ink-500">
                            Qty
                          </th>
                          <th className="text-right px-3 py-2 text-xs font-semibold text-ink-500">
                            Price
                          </th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-ink-50">
                        {order.items.map((item) => (
                          <tr key={item.id}>
                            <td className="px-3 py-2.5 text-ink-900">
                              {item.item_name || `Item ${item.item_id}`}
                            </td>
                            <td className="px-3 py-2.5 text-right text-ink-700">
                              {item.quantity}
                            </td>
                            <td className="px-3 py-2.5 text-right font-mono text-ink-900">
                              {formatCurrency(item.price, order.currency)}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ) : (
                  <p className="text-sm text-ink-400">No line items.</p>
                )}
              </section>

              {order.cancel_reason && (
                <section>
                  <p className="text-xs font-semibold uppercase tracking-wider text-ink-400 mb-1">
                    Cancel reason
                  </p>
                  <p className="text-sm text-ink-700">{order.cancel_reason}</p>
                </section>
              )}

              <p className="text-xs text-ink-400">
                Last synced {formatDateTime(order.synced_at)}
              </p>
            </>
          ) : null}
        </div>

        <div className="px-6 py-4 border-t border-ink-100 flex flex-col gap-3 shrink-0">
          <div className="flex flex-wrap gap-2">
            <button
              onClick={onClose}
              className="flex-1 min-w-[7rem] px-4 py-2.5 text-sm font-semibold rounded-xl border border-ink-200 text-ink-700 hover:bg-ink-50 transition"
            >
              Close
            </button>
            {order && canShip(order.canonical_status) && (
              <button
                onClick={() => setArrangingShipment(true)}
                className="flex-1 min-w-[10rem] px-4 py-2.5 text-sm font-semibold rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition active:scale-[0.97]"
              >
                🚚 Arrange Shipment
              </button>
            )}
            {order && canPrintLabel(order.canonical_status) && (
              <PrintLabelButton order={order} />
            )}
          </div>
          <p className="text-xs text-ink-400">
            Cancel only on the official Shopee/Lazada seller website.
          </p>
        </div>

        {order && arrangingShipment && (
          <ArrangeShipmentModal
            order={order}
            onClose={() => setArrangingShipment(false)}
            onShipped={(updatedOrder) => {
              setOrder(updatedOrder);
              setArrangingShipment(false);
              onShipped?.(updatedOrder);
            }}
          />
        )}
      </aside>
    </div>
  );
}
