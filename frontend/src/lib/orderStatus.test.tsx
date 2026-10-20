import { afterEach, describe, expect, it } from 'vitest';
import { cleanup, render } from '@testing-library/react';
import {
  canCancel,
  canLazadaCancel,
  canLazadaPack,
  canLazadaPrintAwb,
  canLazadaReadyToShip,
  canPrintLabel,
  canShip,
  needsFulfillment,
  terminal,
  type CanonicalStatus,
} from './orderStatus';
import OrderStatusBadge from './OrderStatusBadge';

afterEach(cleanup);

const ALL_CANONICAL: CanonicalStatus[] = [
  'unpaid',
  'pending',
  'packed',
  'ready_to_ship',
  'shipped',
  'delivered',
  'completed',
  'cancelling',
  'cancelled',
  'returned',
  'failed',
];

const FULFILLMENT_CASES: Array<[CanonicalStatus | null | undefined, boolean]> = [
  ['unpaid', false],
  ['pending', true],
  ['packed', true],
  ['ready_to_ship', true],
  ['shipped', false],
  ['delivered', false],
  ['completed', false],
  ['cancelling', false],
  ['cancelled', false],
  ['returned', false],
  ['failed', false],
  [null, false],
  [undefined, false],
];

const TERMINAL_CASES: Array<[CanonicalStatus | null | undefined, boolean]> = [
  ['unpaid', false],
  ['pending', false],
  ['packed', false],
  ['ready_to_ship', false],
  ['shipped', false],
  ['delivered', false],
  ['completed', true],
  ['cancelling', false],
  ['cancelled', true],
  ['returned', true],
  ['failed', true],
  [null, false],
  [undefined, false],
];

describe('canonical predicates', () => {
  it.each(FULFILLMENT_CASES)(
    'needsFulfillment(%s) is %s',
    (status, expected) => {
      expect(needsFulfillment(status)).toBe(expected);
    },
  );

  it.each(TERMINAL_CASES)('terminal(%s) is %s', (status, expected) => {
    expect(terminal(status)).toBe(expected);
  });
});

describe('capability mirrors', () => {
  const CAPABILITY_CASES: Array<{
    name: string;
    fn: (status: CanonicalStatus | null | undefined) => boolean;
    trueFor: CanonicalStatus[];
  }> = [
    { name: 'canCancel', fn: canCancel, trueFor: ['ready_to_ship'] },
    { name: 'canShip', fn: canShip, trueFor: ['ready_to_ship'] },
    { name: 'canPrintLabel', fn: canPrintLabel, trueFor: ['ready_to_ship', 'shipped'] },
    {
      name: 'canLazadaCancel',
      fn: canLazadaCancel,
      trueFor: ['unpaid', 'pending', 'packed'],
    },
    { name: 'canLazadaPack', fn: canLazadaPack, trueFor: ['pending'] },
    {
      name: 'canLazadaPrintAwb',
      fn: canLazadaPrintAwb,
      trueFor: ['packed', 'ready_to_ship', 'shipped'],
    },
    { name: 'canLazadaReadyToShip', fn: canLazadaReadyToShip, trueFor: ['packed'] },
  ];

  it.each(CAPABILITY_CASES)('$name matches the platform set', ({ fn, trueFor }) => {
    const actual = ALL_CANONICAL.filter((status) => fn(status));
    expect(actual).toEqual(trueFor);
  });

  it.each(CAPABILITY_CASES)('$name is false without a canonical status', ({ fn }) => {
    expect(fn(null)).toBe(false);
    expect(fn(undefined)).toBe(false);
  });
});

describe('OrderStatusBadge', () => {
  const LABEL_CASES: Array<[CanonicalStatus, string, string]> = [
    ['unpaid', 'Unpaid', 'bg-gray-50'],
    ['pending', 'Pending', 'bg-blue-50'],
    ['packed', 'Packed', 'bg-amber-50'],
    ['ready_to_ship', 'Ready to Ship', 'bg-indigo-50'],
    ['shipped', 'Shipped', 'bg-violet-50'],
    ['delivered', 'Delivered', 'bg-emerald-50'],
    ['completed', 'Completed', 'bg-emerald-50'],
    ['cancelling', 'Cancelling', 'bg-orange-50'],
    ['cancelled', 'Cancelled', 'bg-red-50'],
    ['returned', 'Returned', 'bg-rose-50'],
    ['failed', 'Failed', 'bg-orange-50'],
  ];

  it.each(LABEL_CASES)(
    'renders %s as %s with the %s tone',
    (canonical, label, tone) => {
      const { container } = render(
        <OrderStatusBadge status="RAW_PLATFORM_SPELLING" canonical={canonical} />,
      );
      expect(container.textContent).toBe(label);
      expect(container.firstElementChild?.className).toContain(tone);
    },
  );

  it('renders Shopee PROCESSED and Lazada ready_to_ship identically', () => {
    const shopee = render(<OrderStatusBadge status="PROCESSED" canonical="ready_to_ship" />);
    const lazada = render(<OrderStatusBadge status="ready_to_ship" canonical="ready_to_ship" />);

    expect(shopee.container.firstElementChild?.className).toBe(
      lazada.container.firstElementChild?.className,
    );
    expect(shopee.container.textContent).toBe('Ready to Ship');
    expect(lazada.container.textContent).toBe('Ready to Ship');
  });

  it('renders a neutral badge with the raw text when there is no canonical status', () => {
    const { container } = render(<OrderStatusBadge status="SOMETHING_NEW" canonical={null} />);

    expect(container.textContent).toBe('SOMETHING_NEW');
    expect(container.firstElementChild?.className).toContain('bg-gray-50');
  });

  it('renders a neutral badge with the raw text when canonical_status is absent', () => {
    const { container } = render(<OrderStatusBadge status="weird_value" />);

    expect(container.textContent).toBe('weird_value');
    expect(container.firstElementChild?.className).toContain('bg-gray-50');
  });
});
