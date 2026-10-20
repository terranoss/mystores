import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import type { OrderSyncScope, OrderSyncResult } from './useOrderSync';

const mocks = vi.hoisted(() => ({
  syncOrders: vi.fn(),
  syncLazadaOrders: vi.fn(),
  showToast: vi.fn(),
}));

vi.mock('../api/client', () => ({
  syncOrders: mocks.syncOrders,
  syncLazadaOrders: mocks.syncLazadaOrders,
}));

vi.mock('../context/ToastContext', () => ({
  useToast: () => ({ showToast: mocks.showToast }),
}));

import { useOrderSync } from './useOrderSync';

afterEach(cleanup);

beforeEach(() => {
  vi.clearAllMocks();
  mocks.syncOrders.mockReset();
  mocks.syncLazadaOrders.mockReset();
});

function SyncHarness({
  scope = 'shopee',
  hasShopeeStores = true,
  hasLazadaStores = false,
  refresh,
}: {
  scope?: OrderSyncScope;
  hasShopeeStores?: boolean;
  hasLazadaStores?: boolean;
  refresh: () => void | Promise<void>;
}) {
  const { syncing, sync } = useOrderSync({
    scope,
    hasShopeeStores,
    hasLazadaStores,
    refresh,
  });

  return (
    <button type="button" onClick={() => void sync()} disabled={syncing}>
      {syncing ? 'syncing' : 'sync'}
    </button>
  );
}

function okResult(results: string[] = ['Store #1: synced']): OrderSyncResult {
  return {
    results,
    stores: [{ store_id: 1, platform: 'shopee', ok: true, message: 'synced' }],
    failed: false,
  };
}

describe('useOrderSync', () => {
  it('refreshes once and shows one success toast on a successful sync', async () => {
    mocks.syncOrders.mockResolvedValue(okResult());
    const refresh = vi.fn();

    render(<SyncHarness refresh={refresh} />);
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => expect(mocks.showToast).toHaveBeenCalledTimes(1));

    expect(mocks.syncOrders).toHaveBeenCalledWith('shopee');
    expect(mocks.syncLazadaOrders).not.toHaveBeenCalled();
    expect(refresh).toHaveBeenCalledTimes(1);
    expect(mocks.showToast).toHaveBeenCalledWith('Store #1: synced', 'success');
  });

  it('reports failure from the failed flag even when the messages look successful', async () => {
    mocks.syncOrders.mockResolvedValue({
      ...okResult(['Store #1: synced']),
      failed: true,
    });
    const refresh = vi.fn();

    render(<SyncHarness refresh={refresh} />);
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => expect(mocks.showToast).toHaveBeenCalledTimes(1));

    expect(refresh).toHaveBeenCalledTimes(1);
    expect(mocks.showToast).toHaveBeenCalledWith('Store #1: synced', 'error');
  });

  it('does not infer failure from the word "failed" in a message', async () => {
    mocks.syncOrders.mockResolvedValue(
      okResult(['Store #1: failed - transient, then recovered']),
    );
    const refresh = vi.fn();

    render(<SyncHarness refresh={refresh} />);
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => expect(mocks.showToast).toHaveBeenCalledTimes(1));

    expect(mocks.showToast).toHaveBeenCalledWith(
      'Store #1: failed - transient, then recovered',
      'success',
    );
  });

  it('treats a rejected platform call as a failure and still refreshes', async () => {
    mocks.syncOrders.mockRejectedValue(new Error('Network down'));
    const refresh = vi.fn();

    render(<SyncHarness refresh={refresh} />);
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => expect(mocks.showToast).toHaveBeenCalledTimes(1));

    expect(refresh).toHaveBeenCalledTimes(1);
    expect(mocks.showToast).toHaveBeenCalledWith('Network down', 'error');
  });

  it('syncs only lazada for a lazada scope', async () => {
    mocks.syncLazadaOrders.mockResolvedValue({
      results: ['Store #2: synced'],
      stores: [{ store_id: 2, platform: 'lazada', ok: true, message: 'synced' }],
      failed: false,
    });
    const refresh = vi.fn();

    render(<SyncHarness scope="lazada" hasShopeeStores={false} hasLazadaStores refresh={refresh} />);
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => expect(mocks.showToast).toHaveBeenCalledTimes(1));

    expect(mocks.syncOrders).not.toHaveBeenCalled();
    expect(mocks.syncLazadaOrders).toHaveBeenCalledTimes(1);
    expect(refresh).toHaveBeenCalledTimes(1);
    expect(mocks.showToast).toHaveBeenCalledWith('Store #2: synced', 'success');
  });

  it('syncs both platforms for the all scope and reports one combined toast', async () => {
    mocks.syncOrders.mockResolvedValue(okResult(['Store #1: synced']));
    mocks.syncLazadaOrders.mockResolvedValue({
      results: ['Store #2: synced'],
      stores: [{ store_id: 2, platform: 'lazada', ok: true, message: 'synced' }],
      failed: true,
    });
    const refresh = vi.fn();

    render(
      <SyncHarness
        scope="all"
        hasShopeeStores
        hasLazadaStores
        refresh={refresh}
      />,
    );
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => expect(mocks.showToast).toHaveBeenCalledTimes(1));

    expect(mocks.syncOrders).toHaveBeenCalledTimes(1);
    expect(mocks.syncLazadaOrders).toHaveBeenCalledTimes(1);
    expect(refresh).toHaveBeenCalledTimes(1);
    expect(mocks.showToast).toHaveBeenCalledWith(
      'Store #1: synced\nStore #2: synced',
      'error',
    );
  });
});
