import type { Order, OrderItem } from '../api/client';
import { formatOrderCurrency, formatOrderDate } from './orders/ordersTableFormatters';

export const STATUS_STYLES: Record<string, string> = {
  UNPAID: 'bg-gray-50 text-gray-700 border-gray-200',
  READY_TO_SHIP: 'bg-blue-50 text-blue-700 border-blue-200',
  PROCESSED: 'bg-amber-50 text-amber-700 border-amber-200',
  SHIPPED: 'bg-indigo-50 text-indigo-700 border-indigo-200',
  TO_CONFIRM_RECEIVE: 'bg-teal-50 text-teal-700 border-teal-200',
  COMPLETED: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  IN_CANCEL: 'bg-orange-50 text-orange-700 border-orange-200',
  CANCELLED: 'bg-red-50 text-red-700 border-red-200',
  TO_RETURN: 'bg-rose-50 text-rose-700 border-rose-200',
};

export const STATUS_DOTS: Record<string, string> = {
  UNPAID: 'bg-gray-500',
  READY_TO_SHIP: 'bg-blue-500',
  PROCESSED: 'bg-amber-500',
  SHIPPED: 'bg-indigo-500',
  TO_CONFIRM_RECEIVE: 'bg-teal-500',
  COMPLETED: 'bg-emerald-500',
  IN_CANCEL: 'bg-orange-500',
  CANCELLED: 'bg-red-500',
  TO_RETURN: 'bg-rose-500',
};

export const STATUS_LABELS: Record<string, string> = {
  UNPAID: 'Unpaid',
  READY_TO_SHIP: 'Ready to Ship',
  PROCESSED: 'Processed',
  SHIPPED: 'Shipped',
  TO_CONFIRM_RECEIVE: 'To Confirm',
  COMPLETED: 'Completed',
  IN_CANCEL: 'Cancelling',
  CANCELLED: 'Cancelled',
  TO_RETURN: 'To Return',
};

export function StatusBadge({ status }: { status: string }) {
  const style = STATUS_STYLES[status] || 'bg-gray-50 text-gray-700 border-gray-200';
  const dot = STATUS_DOTS[status] || 'bg-gray-500';
  const label = STATUS_LABELS[status] || status;
  return (
    <span
      className={`inline-flex max-w-full items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border whitespace-normal text-left leading-snug ${style}`}
    >
      <span className={`w-1.5 h-1.5 rounded-full ${dot}`} />
      {label}
    </span>
  );
}

export function canCancel(status: string): boolean {
  return ['READY_TO_SHIP', 'PROCESSED'].includes(status);
}

export function canShip(status: string): boolean {
  return status === 'READY_TO_SHIP';
}

export function canPrintLabel(status: string): boolean {
  return ['PROCESSED', 'SHIPPED'].includes(status);
}


export function formatCurrency(amount: number, currency?: string | null): string {
  return formatOrderCurrency(amount, currency);
}

export function formatDate(value?: string | null): string {
  return formatOrderDate(value);
}

export function formatDateTime(value?: string | null): string {
  if (!value) return '—';
  return new Date(value).toLocaleString();
}

export type ShipByTone = 'normal' | 'due_today' | 'overdue' | 'none';

export function getShipByInfo(
  order: Pick<Order, 'ship_by_date' | 'order_status'>
): { label: string; tone: ShipByTone } {
  if (!order.ship_by_date) {
    return { label: '—', tone: 'none' };
  }

  const shipBy = new Date(order.ship_by_date);
  if (Number.isNaN(shipBy.getTime()) || shipBy.getFullYear() < 1971) {
    return { label: '—', tone: 'none' };
  }

  const label = shipBy.toLocaleDateString();
  const actionable = ['READY_TO_SHIP', 'PROCESSED'].includes(order.order_status);
  if (!actionable) {
    return { label, tone: 'normal' };
  }

  const now = new Date();
  const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const startOfShipDay = new Date(
    shipBy.getFullYear(),
    shipBy.getMonth(),
    shipBy.getDate()
  );

  if (startOfShipDay < startOfToday) {
    return { label: `Overdue · ${label}`, tone: 'overdue' };
  }
  if (startOfShipDay.getTime() === startOfToday.getTime()) {
    return { label: `Due today · ${label}`, tone: 'due_today' };
  }
  return { label, tone: 'normal' };
}

export function itemsSummary(items: OrderItem[] | undefined): string {
  if (!items || items.length === 0) return '—';
  const first = items[0];
  const name = first.item_name || `Item ${first.item_id}`;
  const base = `${name} × ${first.quantity}`;
  if (items.length === 1) return base;
  return `${base} +${items.length - 1} more`;
}
