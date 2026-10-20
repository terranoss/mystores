import { useCallback, useRef, useState } from 'react';
import {
  syncLazadaOrders,
  syncOrders,
  type OrderSyncResult,
} from '../api/client';
import { useToast } from '../context/ToastContext';

export type { OrderSyncResult, OrderSyncStoreOutcome } from '../api/client';

export type OrderSyncScope = 'all' | 'shopee' | 'lazada';

export interface UseOrderSyncOptions {
  /** Which platform tab the screen is showing; `all` syncs every linked platform. */
  scope: OrderSyncScope;
  /** Used only by the `all` scope, so a missing platform is not called. */
  hasShopeeStores: boolean;
  hasLazadaStores: boolean;
  /** The screen's refresh callback, invoked once per sync. */
  refresh: () => void | Promise<void>;
}

export interface OrderSyncController {
  syncing: boolean;
  sync: () => Promise<void>;
}

interface PlatformOutcome {
  messages: string[];
  failed: boolean;
}

function errorMessage(error: unknown): string {
  if (error !== null && typeof error === 'object') {
    const response = (error as { response?: { data?: { message?: unknown } } }).response;
    const responseMessage = response?.data?.message;
    if (typeof responseMessage === 'string' && responseMessage !== '') {
      return responseMessage;
    }

    const message = (error as { message?: unknown }).message;
    if (typeof message === 'string' && message !== '') {
      return message;
    }
  }

  return '';
}

async function runPlatform(
  label: string,
  call: () => Promise<OrderSyncResult>,
): Promise<PlatformOutcome> {
  try {
    const result = await call();
    return {
      messages: result.results.length ? result.results : [`${label}: sync completed.`],
      failed: result.failed,
    };
  } catch (error) {
    // A rejected call is a real failure. The message is only used for the toast
    // body; the failure decision comes from the exception or the `failed` flag.
    return {
      messages: [errorMessage(error) || `${label} sync failed.`],
      failed: true,
    };
  }
}

/**
 * The single manual-sync implementation for the orders panel and the dashboard.
 *
 * Calls only the platforms the screen asked for, reads the API's `failed` flag
 * (never the message text), refreshes the caller's data once, and shows exactly
 * one toast.
 */
export function useOrderSync({
  scope,
  hasShopeeStores,
  hasLazadaStores,
  refresh,
}: UseOrderSyncOptions): OrderSyncController {
  const { showToast } = useToast();
  const [syncing, setSyncing] = useState(false);
  const inFlight = useRef(false);

  const sync = useCallback(async () => {
    if (inFlight.current) return;
    inFlight.current = true;
    setSyncing(true);

    try {
      const runs: Array<Promise<PlatformOutcome>> = [];

      if (scope === 'shopee') {
        runs.push(runPlatform('Shopee', () => syncOrders('shopee')));
      } else if (scope === 'lazada') {
        runs.push(runPlatform('Lazada', () => syncLazadaOrders()));
      } else {
        if (hasShopeeStores) {
          runs.push(runPlatform('Shopee', () => syncOrders('shopee')));
        }
        if (hasLazadaStores) {
          runs.push(runPlatform('Lazada', () => syncLazadaOrders()));
        }
      }

      if (runs.length === 0) {
        showToast('No stores to sync. Link a store from the Dashboard.', 'info');
        return;
      }

      const outcomes = await Promise.all(runs);
      const messages = outcomes.flatMap((outcome) => outcome.messages);
      const failed = outcomes.some((outcome) => outcome.failed);

      try {
        await refresh();
      } catch {
        // The screen owns its own load-error state.
      }

      showToast(messages.join('\n'), failed ? 'error' : 'success');
    } finally {
      inFlight.current = false;
      setSyncing(false);
    }
  }, [hasLazadaStores, hasShopeeStores, refresh, scope, showToast]);

  return { syncing, sync };
}
