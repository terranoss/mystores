import { formatOrderCurrency, formatOrderDate } from '../orders/ordersTableFormatters';

export const LAZADA_STATUS_STYLES: Record<string, string> = {
  unpaid: 'bg-gray-50 text-gray-700 border-gray-200',
  pending: 'bg-blue-50 text-blue-700 border-blue-200',
  confirmed: 'bg-emerald-50 text-emerald-800 border-emerald-300',
  packed: 'bg-amber-50 text-amber-700 border-amber-200',
  ready_to_ship: 'bg-indigo-50 text-indigo-700 border-indigo-200',
  shipped: 'bg-violet-50 text-violet-700 border-violet-200',
  delivered: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  canceled: 'bg-red-50 text-red-700 border-red-200',
  cancelled: 'bg-red-50 text-red-700 border-red-200',
  returned: 'bg-rose-50 text-rose-700 border-rose-200',
  failed: 'bg-orange-50 text-orange-700 border-orange-200',
};

export const LAZADA_STATUS_DOTS: Record<string, string> = {
  unpaid: 'bg-gray-500',
  pending: 'bg-blue-500',
  confirmed: 'bg-emerald-600',
  packed: 'bg-amber-500',
  ready_to_ship: 'bg-indigo-500',
  shipped: 'bg-violet-500',
  delivered: 'bg-emerald-500',
  canceled: 'bg-red-500',
  cancelled: 'bg-red-500',
  returned: 'bg-rose-500',
  failed: 'bg-orange-500',
};

export const LAZADA_STATUS_LABELS: Record<string, string> = {
  unpaid: 'Unpaid',
  pending: 'To pack',
  confirmed: 'Confirmed',
  packed: 'Packed',
  ready_to_ship: 'Ready to ship',
  shipped: 'Shipped',
  delivered: 'Delivered',
  canceled: 'Cancelled',
  cancelled: 'Cancelled',
  returned: 'Returned',
  failed: 'Failed',
};

export const LAZADA_CANCEL_REASONS = [
  { value: 'Out of stock', label: 'Out of stock' },
  { value: 'Wrong price', label: 'Wrong price' },
  { value: 'Seller out of stock', label: 'Seller out of stock' },
  { value: 'Unable to deliver', label: 'Unable to deliver' },
];

export function LazadaStatusBadge({ status }: { status: string }) {
  const key = status.toLowerCase();
  const style = LAZADA_STATUS_STYLES[key] || 'bg-gray-50 text-gray-700 border-gray-200';
  const dot = LAZADA_STATUS_DOTS[key] || 'bg-gray-500';
  const label = LAZADA_STATUS_LABELS[key] || status;
  return (
    <span
      className={`inline-flex max-w-full items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border whitespace-normal text-left leading-snug ${style}`}
    >
      <span className={`w-1.5 h-1.5 rounded-full ${dot}`} />
      {label}
    </span>
  );
}

export function canLazadaCancel(status: string): boolean {
  return ['pending', 'unpaid', 'packed'].includes(status.toLowerCase());
}

export function canLazadaPack(status: string): boolean {
  return status.toLowerCase() === 'pending';
}

export function canLazadaPrintAwb(status: string): boolean {
  return ['packed', 'ready_to_ship', 'shipped'].includes(status.toLowerCase());
}

export function canLazadaReadyToShip(status: string): boolean {
  return status.toLowerCase() === 'packed';
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
