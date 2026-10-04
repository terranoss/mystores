import type { ReactNode } from 'react';

export interface OrdersTableRow {
  id: number;
  orderSn: string;
  platform: string;
  orderMeta?: string | null;
  orderedAtText?: string | null;
  storeName?: string | null;
  buyerText?: string | null;
  shipByText?: string | null;
  shipByTone?: 'normal' | 'due_today' | 'overdue' | 'none';
  totalText: string;
  selectable?: boolean;
  selected?: boolean;
}

export interface OrdersTableColumnVisibility {
  platform?: boolean;
  store?: boolean;
  buyer?: boolean;
  shipBy?: boolean;
}

export interface OrdersTablePagination {
  page: number;
  lastPage: number;
  total: number;
  onPrev: () => void;
  onNext: () => void;
}

export interface OrdersTableProps<TRow extends OrdersTableRow> {
  title: string;
  loadingText: string;
  emptyText: string;
  rows: TRow[];
  loading?: boolean;
  columns?: OrdersTableColumnVisibility;
  bulkToolbar?: ReactNode;
  pagination?: OrdersTablePagination;
  allSelectableSelected?: boolean;
  disableSelectAll?: boolean;
  onToggleSelectAll?: () => void;
  onOpenDetail?: (row: TRow) => void;
  renderItems: (row: TRow) => ReactNode;
  renderStatus: (row: TRow) => ReactNode;
  renderActions: (row: TRow) => ReactNode;
  onToggleSelected?: (row: TRow) => void;
  getSelectTitle?: (row: TRow) => string | undefined;
}
