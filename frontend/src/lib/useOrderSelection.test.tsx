import { useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import {
  useOrderSelection,
  type SelectableOrder,
} from './useOrderSelection';

afterEach(cleanup);

const ORDERS: SelectableOrder[] = [
  { id: 1, canonical_status: 'ready_to_ship', store: { platform: 'shopee' } },
  { id: 2, canonical_status: 'pending', store: { platform: 'shopee' } },
  { id: 3, canonical_status: 'packed', store: { platform: 'lazada' } },
  { id: 4, canonical_status: 'pending', store: { platform: 'lazada' } },
];

function SelectionHarness({
  rows,
  resetKey = 'initial',
  max,
  onLimitReached,
}: {
  rows: SelectableOrder[];
  resetKey?: string;
  max?: number;
  onLimitReached?: (message: string, tone: 'error' | 'info') => void;
}) {
  const [currentRows, setCurrentRows] = useState(rows);
  const selection = useOrderSelection(currentRows, { resetKey, max, onLimitReached });

  return (
    <div>
      <output data-testid="selected">{selection.selectedIds.join(',')}</output>
      <output data-testid="count">{selection.selectedCount}</output>
      <output data-testid="all">{String(selection.allSelectableSelected)}</output>
      {currentRows.map((order) => (
        <button key={order.id} type="button" onClick={() => selection.toggleSelect(order)}>
          toggle-{order.id}
        </button>
      ))}
      <button type="button" onClick={selection.toggleSelectAllPrintable}>
        select-all
      </button>
      <button type="button" onClick={selection.clearSelection}>
        clear
      </button>
      <button
        type="button"
        onClick={() =>
          setCurrentRows((prev) =>
            prev.map((order) =>
              Number(order.id) === 1
                ? { ...order, canonical_status: 'pending' }
                : order,
            ),
          )
        }
      >
        demote-1
      </button>
    </div>
  );
}

describe('useOrderSelection', () => {
  it('selects only printable rows', () => {
    render(<SelectionHarness rows={ORDERS} />);

    fireEvent.click(screen.getByText('toggle-2'));
    expect(screen.getByTestId('count').textContent).toBe('0');

    fireEvent.click(screen.getByText('toggle-1'));
    expect(screen.getByTestId('selected').textContent).toBe('1');

    fireEvent.click(screen.getByText('toggle-4'));
    expect(screen.getByTestId('selected').textContent).toBe('1');
  });

  it('selects every printable row at once and clears on the second toggle', () => {
    render(<SelectionHarness rows={ORDERS} />);

    fireEvent.click(screen.getByText('select-all'));
    expect(screen.getByTestId('selected').textContent).toBe('1,3');
    expect(screen.getByTestId('all').textContent).toBe('true');

    fireEvent.click(screen.getByText('select-all'));
    expect(screen.getByTestId('count').textContent).toBe('0');
  });

  it('clears the selection explicitly', () => {
    render(<SelectionHarness rows={ORDERS} />);

    fireEvent.click(screen.getByText('toggle-1'));
    fireEvent.click(screen.getByText('toggle-3'));
    expect(screen.getByTestId('count').textContent).toBe('2');

    fireEvent.click(screen.getByText('clear'));
    expect(screen.getByTestId('count').textContent).toBe('0');
  });

  it('drops rows that stop being printable after a refresh', async () => {
    render(<SelectionHarness rows={ORDERS} />);

    fireEvent.click(screen.getByText('toggle-1'));
    expect(screen.getByTestId('selected').textContent).toBe('1');

    fireEvent.click(screen.getByText('demote-1'));

    await waitFor(() => {
      expect(screen.getByTestId('selected').textContent).toBe('');
    });
  });

  it('clears when the reset key changes', () => {
    const { rerender } = render(<SelectionHarness rows={ORDERS} resetKey="page-1" />);

    fireEvent.click(screen.getByText('toggle-1'));
    expect(screen.getByTestId('selected').textContent).toBe('1');

    rerender(<SelectionHarness rows={ORDERS} resetKey="page-2" />);
    expect(screen.getByTestId('selected').textContent).toBe('');
  });

  it('reports the bulk-print limit instead of exceeding it', () => {
    const onLimitReached = vi.fn();
    render(<SelectionHarness rows={ORDERS} max={1} onLimitReached={onLimitReached} />);

    fireEvent.click(screen.getByText('toggle-1'));
    fireEvent.click(screen.getByText('toggle-3'));

    expect(screen.getByTestId('selected').textContent).toBe('1');
    expect(onLimitReached).toHaveBeenCalledWith(
      'You can print at most 1 labels at once.',
      'error',
    );
  });
});
