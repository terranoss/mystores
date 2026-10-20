import { useCallback, useMemo, useState } from 'react';
import { canLazadaPrintAwb, canPrintLabel } from './orderStatus';

/**
 * The single bulk-print limit. Kept here so the two screens cannot disagree.
 */
export const ORDER_BULK_PRINT_MAX = 15;

export type SelectablePlatform = 'shopee' | 'lazada';

/**
 * The minimum an order must expose to be selectable. Selection only needs the
 * platform and the canonical status; `Order` satisfies this structurally.
 */
export interface SelectableOrder {
  id: number | string;
  canonical_status?: string | null;
  store?: { platform?: string | null } | null;
}

export function selectableOrderPlatform(order: SelectableOrder): SelectablePlatform {
  return order.store?.platform === 'lazada' ? 'lazada' : 'shopee';
}

/**
 * Printable rows are the only selectable rows. This mirrors the platform print
 * capability helpers in `orderStatus`; the backend remains the authority.
 */
export function isOrderPrintable(order: SelectableOrder): boolean {
  return selectableOrderPlatform(order) === 'lazada'
    ? canLazadaPrintAwb(order.canonical_status)
    : canPrintLabel(order.canonical_status);
}

export interface UseOrderSelectionOptions {
  /** Identifies the current page/filter set. Changing it clears the selection. */
  resetKey?: string;
  /** Defaults to ORDER_BULK_PRINT_MAX. */
  max?: number;
  /** Called when a selection would exceed `max`. The screen owns the toast. */
  onLimitReached?: (message: string, tone: 'error' | 'info') => void;
}

export interface OrderSelection {
  selectedIds: number[];
  selectedCount: number;
  printableIds: number[];
  selectableIds: number[];
  allSelectableSelected: boolean;
  isSelected: (order: SelectableOrder) => boolean;
  toggleSelect: (order: SelectableOrder) => void;
  toggleSelectAllPrintable: () => void;
  clearSelection: () => void;
}

interface SelectionState {
  resetKey: string;
  ids: number[];
}

/**
 * One selection implementation for every order screen.
 *
 * - Only printable rows can be selected.
 * - A refresh that makes a selected row unprintable (or removes it) prunes it,
 *   because the exposed selection is derived from the current printable set.
 * - A filter/page change (`resetKey`) makes the previous selection invisible and
 *   the next toggle starts from empty.
 */
export function useOrderSelection(
  orders: SelectableOrder[],
  options: UseOrderSelectionOptions = {},
): OrderSelection {
  const { resetKey = '', max = ORDER_BULK_PRINT_MAX, onLimitReached } = options;
  const [state, setState] = useState<SelectionState>(() => ({ resetKey, ids: [] }));

  const printableIds = useMemo(
    () =>
      orders
        .filter((order) => isOrderPrintable(order))
        .map((order) => Number(order.id)),
    [orders],
  );

  const printableSet = useMemo(() => new Set(printableIds), [printableIds]);

  const selectableIds = useMemo(
    () => printableIds.slice(0, max),
    [printableIds, max],
  );

  const selectedIds = useMemo(
    () =>
      state.resetKey === resetKey
        ? state.ids.filter((id) => printableSet.has(id))
        : [],
    [printableSet, resetKey, state],
  );

  const allSelectableSelected =
    selectableIds.length > 0 && selectableIds.every((id) => selectedIds.includes(id));

  const toggleSelect = useCallback(
    (order: SelectableOrder) => {
      if (!isOrderPrintable(order)) return;

      const id = Number(order.id);
      if (selectedIds.includes(id)) {
        setState({ resetKey, ids: selectedIds.filter((value) => value !== id) });
        return;
      }
      if (selectedIds.length >= max) {
        onLimitReached?.(`You can print at most ${max} labels at once.`, 'error');
        return;
      }
      setState({ resetKey, ids: [...selectedIds, id] });
    },
    [max, onLimitReached, resetKey, selectedIds],
  );

  const toggleSelectAllPrintable = useCallback(() => {
    if (allSelectableSelected) {
      setState({ resetKey, ids: [] });
      return;
    }
    if (printableIds.length > max) {
      onLimitReached?.(`Selected first ${max} printable orders on this page.`, 'info');
    }
    setState({ resetKey, ids: [...selectableIds] });
  }, [
    allSelectableSelected,
    max,
    onLimitReached,
    printableIds.length,
    resetKey,
    selectableIds,
  ]);

  const clearSelection = useCallback(() => {
    setState({ resetKey, ids: [] });
  }, [resetKey]);

  const isSelected = useCallback(
    (order: SelectableOrder) => selectedIds.includes(Number(order.id)),
    [selectedIds],
  );

  return {
    selectedIds,
    selectedCount: selectedIds.length,
    printableIds,
    selectableIds,
    allSelectableSelected,
    isSelected,
    toggleSelect,
    toggleSelectAllPrintable,
    clearSelection,
  };
}
