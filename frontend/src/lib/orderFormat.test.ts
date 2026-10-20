import { describe, expect, it } from 'vitest';
import {
  formatCurrency,
  formatDate,
  formatDateTime,
  getShipByInfo,
  itemsSummary,
} from './orderFormat';
import type { OrderItem } from '../api/client';

function localDateTime(daysFromToday: number): string {
  const d = new Date();
  d.setDate(d.getDate() + daysFromToday);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T12:00:00`;
}

function item(overrides: Partial<OrderItem> = {}): OrderItem {
  return {
    id: 1,
    item_id: 100,
    model_id: null,
    item_name: 'Widget',
    quantity: 2,
    price: 9.5,
    ...overrides,
  };
}

describe('formatCurrency', () => {
  it.each<[number, string | null | undefined, string]>([
    [12.5, 'MYR', 'RM12.50'],
    [3, 'SGD', 'S$3.00'],
    [4, 'USD', '$4.00'],
    [1234.5, null, '$1234.50'],
    [0, undefined, '$0.00'],
  ])('formats %s %s as %s', (amount, currency, expected) => {
    expect(formatCurrency(amount, currency)).toBe(expected);
  });
});

describe('formatDate', () => {
  it.each([null, undefined, ''])('falls back to an em dash for %s', (value) => {
    expect(formatDate(value)).toBe('—');
  });

  it('renders the local calendar date', () => {
    const result = formatDate('2026-01-15T09:30:00');
    expect(result).toContain('2026');
    expect(result).toContain('15');
  });
});

describe('formatDateTime', () => {
  it.each([null, undefined, ''])('falls back to an em dash for %s', (value) => {
    expect(formatDateTime(value)).toBe('—');
  });

  it('renders the local date and time', () => {
    const result = formatDateTime('2026-01-15T09:30:00');
    expect(result).toContain('2026');
    expect(result).toContain('15');
    expect(result).toContain('30');
  });
});

describe('itemsSummary', () => {
  it('falls back to an em dash without items', () => {
    expect(itemsSummary(undefined)).toBe('—');
    expect(itemsSummary([])).toBe('—');
  });

  it('summarises a single item', () => {
    expect(itemsSummary([item()])).toBe('Widget × 2');
  });

  it('falls back to the item id when the name is null', () => {
    expect(itemsSummary([item({ item_name: null, quantity: 1 })])).toBe('Item 100 × 1');
  });

  it('counts the remaining items', () => {
    expect(itemsSummary([item(), item({ id: 2 }), item({ id: 3 })])).toBe('Widget × 2 +2 more');
  });
});

describe('getShipByInfo', () => {
  it('is empty without a ship-by date', () => {
    expect(getShipByInfo({ ship_by_date: null, canonical_status: 'ready_to_ship' })).toEqual({
      label: '—',
      tone: 'none',
    });
  });

  it('ignores the epoch placeholder date', () => {
    expect(
      getShipByInfo({ ship_by_date: '1970-01-01T00:00:00', canonical_status: 'ready_to_ship' }),
    ).toEqual({ label: '—', tone: 'none' });
  });

  it('flags a due-today ship-by date for a status awaiting fulfilment', () => {
    const info = getShipByInfo({
      ship_by_date: localDateTime(0),
      canonical_status: 'ready_to_ship',
    });
    expect(info.tone).toBe('due_today');
    expect(info.label.startsWith('Due today · ')).toBe(true);
  });

  it('flags an overdue ship-by date for a packed order', () => {
    const info = getShipByInfo({ ship_by_date: localDateTime(-1), canonical_status: 'packed' });
    expect(info.tone).toBe('overdue');
    expect(info.label.startsWith('Overdue · ')).toBe(true);
  });

  it('leaves a future ship-by date normal for an unpaid order', () => {
    const info = getShipByInfo({ ship_by_date: localDateTime(2), canonical_status: 'unpaid' });
    expect(info.tone).toBe('normal');
    expect(info.label.startsWith('Overdue')).toBe(false);
    expect(info.label.startsWith('Due today')).toBe(false);
  });

  it('does not flag urgency for a shipped order', () => {
    const info = getShipByInfo({ ship_by_date: localDateTime(0), canonical_status: 'shipped' });
    expect(info.tone).toBe('normal');
  });

  it('does not flag urgency without a canonical status', () => {
    const info = getShipByInfo({ ship_by_date: localDateTime(-1), canonical_status: null });
    expect(info.tone).toBe('normal');
  });
});
