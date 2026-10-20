/**
 * The single owner of canonical order-status vocabulary in the frontend.
 *
 * Raw marketplace spellings ("Order Status") are mapped to a platform-independent
 * "Canonical Status" by the backend and arrive on `Order.canonical_status`. The UI
 * never re-maps platform spellings: it consumes the canonical value, or renders the
 * raw text neutrally when the backend could not map it.
 *
 * The capability helpers below are presentation mirrors of the platform services.
 * The backend remains the authority; they only decide whether a button is offered.
 */

export const CANONICAL_STATUSES = [
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
] as const;

export type CanonicalStatus = (typeof CANONICAL_STATUSES)[number];

export function isCanonicalStatus(value: unknown): value is CanonicalStatus {
  return CANONICAL_STATUSES.includes(value as CanonicalStatus);
}

/** Statuses that still need seller fulfilment work. Drives urgency presentation. */
const NEEDS_FULFILLMENT: readonly CanonicalStatus[] = ['pending', 'packed', 'ready_to_ship'];

/** Finished statuses. Shipped, delivered and cancelling are not terminal. */
const TERMINAL: readonly CanonicalStatus[] = ['completed', 'cancelled', 'returned', 'failed'];

/**
 * Predicates take a plain string because callers hand them `Order.canonical_status`.
 * A value outside the canonical set (a newer backend spelling, or the null the
 * backend emits for an unmapped raw status) is neither fulfilment work nor terminal.
 */
export function needsFulfillment(status?: string | null): boolean {
  return status != null && isCanonicalStatus(status) && NEEDS_FULFILLMENT.includes(status);
}

export function terminal(status?: string | null): boolean {
  return status != null && isCanonicalStatus(status) && TERMINAL.includes(status);
}

export interface StatusPresentation {
  label: string;
  tone: string;
  dot: string;
}

const PRESENTATION: Record<CanonicalStatus, StatusPresentation> = {
  unpaid: {
    label: 'Unpaid',
    tone: 'bg-gray-50 text-gray-700 border-gray-200',
    dot: 'bg-gray-500',
  },
  pending: {
    label: 'Pending',
    tone: 'bg-blue-50 text-blue-700 border-blue-200',
    dot: 'bg-blue-500',
  },
  packed: {
    label: 'Packed',
    tone: 'bg-amber-50 text-amber-700 border-amber-200',
    dot: 'bg-amber-500',
  },
  ready_to_ship: {
    label: 'Ready to Ship',
    tone: 'bg-indigo-50 text-indigo-700 border-indigo-200',
    dot: 'bg-indigo-500',
  },
  shipped: {
    label: 'Shipped',
    tone: 'bg-violet-50 text-violet-700 border-violet-200',
    dot: 'bg-violet-500',
  },
  delivered: {
    label: 'Delivered',
    tone: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    dot: 'bg-emerald-500',
  },
  completed: {
    label: 'Completed',
    tone: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    dot: 'bg-emerald-500',
  },
  cancelling: {
    label: 'Cancelling',
    tone: 'bg-orange-50 text-orange-700 border-orange-200',
    dot: 'bg-orange-500',
  },
  cancelled: {
    label: 'Cancelled',
    tone: 'bg-red-50 text-red-700 border-red-200',
    dot: 'bg-red-500',
  },
  returned: {
    label: 'Returned',
    tone: 'bg-rose-50 text-rose-700 border-rose-200',
    dot: 'bg-rose-500',
  },
  failed: {
    label: 'Failed',
    tone: 'bg-orange-50 text-orange-700 border-orange-200',
    dot: 'bg-orange-500',
  },
};

/** Used when the backend could not map the raw status. */
export const NEUTRAL_PRESENTATION: StatusPresentation = {
  label: '',
  tone: 'bg-gray-50 text-gray-700 border-gray-200',
  dot: 'bg-gray-500',
};

export function statusPresentation(status: CanonicalStatus): StatusPresentation {
  return PRESENTATION[status];
}

// ── Shopee capability mirrors ─────────────────────────────────

export function canCancel(status?: string | null): boolean {
  return status === 'ready_to_ship';
}

export function canShip(status?: string | null): boolean {
  return status === 'ready_to_ship';
}

export function canPrintLabel(status?: string | null): boolean {
  return status === 'ready_to_ship' || status === 'shipped';
}

// ── Lazada capability mirrors ─────────────────────────────────

export function canLazadaCancel(status?: string | null): boolean {
  return status === 'unpaid' || status === 'pending' || status === 'packed';
}

export function canLazadaPack(status?: string | null): boolean {
  return status === 'pending';
}

export function canLazadaPrintAwb(status?: string | null): boolean {
  return status === 'packed' || status === 'ready_to_ship' || status === 'shipped';
}

export function canLazadaReadyToShip(status?: string | null): boolean {
  return status === 'packed';
}

// ── Lazada cancellation reasons ───────────────────────────────

export const LAZADA_CANCEL_REASONS = [
  { value: 'Out of stock', label: 'Out of stock' },
  { value: 'Wrong price', label: 'Wrong price' },
  { value: 'Seller out of stock', label: 'Seller out of stock' },
  { value: 'Unable to deliver', label: 'Unable to deliver' },
];
