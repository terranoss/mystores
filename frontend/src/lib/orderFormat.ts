import type { OrderItem } from '../api/client';
import { needsFulfillment } from './orderStatus';

/**
 * The single owner of order presentation formatting. Both platform helper
 * modules (and the table formatter they shared) duplicated these; Order Status
 * presentation lives in `orderStatus` instead.
 */

export function formatCurrency(amount: number, currency?: string | null): string {
  const symbol = currency === 'MYR' ? 'RM' : currency === 'SGD' ? 'S$' : '$';
  return `${symbol}${Number(amount).toFixed(2)}`;
}

export function formatDate(value?: string | null): string {
  if (!value) return '—';
  return new Date(value).toLocaleDateString();
}

export function formatDateTime(value?: string | null): string {
  if (!value) return '—';
  return new Date(value).toLocaleString();
}

export function itemsSummary(items: OrderItem[] | undefined): string {
  if (!items || items.length === 0) return '—';
  const first = items[0];
  const name = first.item_name || `Item ${first.item_id}`;
  const base = `${name} × ${first.quantity}`;
  if (items.length === 1) return base;
  return `${base} +${items.length - 1} more`;
}

export type ShipByTone = 'normal' | 'due_today' | 'overdue' | 'none';

interface ShipByOrder {
  ship_by_date?: string | null;
  canonical_status?: string | null;
}

/**
 * Ship-by presentation. Only statuses that still need fulfilment work are
 * flagged as due today or overdue; finished and in-transit orders show the date.
 */
export function getShipByInfo(order: ShipByOrder): { label: string; tone: ShipByTone } {
  if (!order.ship_by_date) {
    return { label: '—', tone: 'none' };
  }

  const shipBy = new Date(order.ship_by_date);
  if (Number.isNaN(shipBy.getTime()) || shipBy.getFullYear() < 1971) {
    return { label: '—', tone: 'none' };
  }

  const label = shipBy.toLocaleDateString();
  if (!needsFulfillment(order.canonical_status)) {
    return { label, tone: 'normal' };
  }

  const now = new Date();
  const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const startOfShipDay = new Date(
    shipBy.getFullYear(),
    shipBy.getMonth(),
    shipBy.getDate(),
  );

  if (startOfShipDay < startOfToday) {
    return { label: `Overdue · ${label}`, tone: 'overdue' };
  }
  if (startOfShipDay.getTime() === startOfToday.getTime()) {
    return { label: `Due today · ${label}`, tone: 'due_today' };
  }
  return { label, tone: 'normal' };
}
