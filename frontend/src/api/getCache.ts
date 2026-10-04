/**
 * In-memory GET response cache (60s TTL) so route remounts reuse recent API data.
 * Cleared on successful mutations and logout.
 */

const DEFAULT_TTL_MS = 60_000;

type CacheEntry = {
  expiresAt: number;
  data: unknown;
  status: number;
  statusText: string;
  headers: Record<string, string>;
};

const store = new Map<string, CacheEntry>();

function stableParams(params: unknown): string {
  if (params == null) return '';
  if (typeof params !== 'object') return String(params);
  const obj = params as Record<string, unknown>;
  const keys = Object.keys(obj).sort();
  return keys
    .map((k) => `${encodeURIComponent(k)}=${encodeURIComponent(String(obj[k] ?? ''))}`)
    .join('&');
}

export function buildGetCacheKey(url: string, params?: unknown): string {
  const q = stableParams(params);
  return q ? `${url}?${q}` : url;
}

export function getCachedGet(key: string): CacheEntry | null {
  const hit = store.get(key);
  if (!hit) return null;
  if (Date.now() > hit.expiresAt) {
    store.delete(key);
    return null;
  }
  return hit;
}

export function setCachedGet(
  key: string,
  payload: { data: unknown; status: number; statusText: string; headers: Record<string, string> },
  ttlMs: number = DEFAULT_TTL_MS
): void {
  store.set(key, {
    expiresAt: Date.now() + ttlMs,
    data: payload.data,
    status: payload.status,
    statusText: payload.statusText,
    headers: payload.headers,
  });
}

const SNAPSHOT_PREFIX = 'stores_swr_snap:';
const DEFAULT_SNAPSHOT_TTL_MS = 15 * 60_000; // 15 minutes

export function getLocalSnapshot<T>(key: string): T | null {
  try {
    const raw = sessionStorage.getItem(SNAPSHOT_PREFIX + key);
    if (!raw) return null;
    const parsed = JSON.parse(raw) as { expiresAt: number; data: T };
    if (!parsed || typeof parsed.expiresAt !== 'number') return null;
    if (Date.now() > parsed.expiresAt) {
      sessionStorage.removeItem(SNAPSHOT_PREFIX + key);
      return null;
    }
    return parsed.data;
  } catch {
    return null;
  }
}

export function setLocalSnapshot<T>(key: string, data: T, ttlMs: number = DEFAULT_SNAPSHOT_TTL_MS): void {
  try {
    sessionStorage.setItem(
      SNAPSHOT_PREFIX + key,
      JSON.stringify({
        expiresAt: Date.now() + ttlMs,
        data,
      })
    );
  } catch {
    // Ignore storage quota limits
  }
}

export function clearLocalSnapshots(prefix?: string): void {
  try {
    const fullPrefix = SNAPSHOT_PREFIX + (prefix || '');
    const toRemove: string[] = [];
    for (let i = 0; i < sessionStorage.length; i++) {
      const k = sessionStorage.key(i);
      if (k && k.startsWith(fullPrefix)) {
        toRemove.push(k);
      }
    }
    for (const k of toRemove) {
      sessionStorage.removeItem(k);
    }
  } catch {
    // Ignore
  }
}

export function invalidateApiGetCache(prefix?: string): void {
  if (!prefix) {
    store.clear();
    clearLocalSnapshots();
    return;
  }
  for (const key of store.keys()) {
    if (key.startsWith(prefix)) {
      store.delete(key);
    }
  }
  clearLocalSnapshots(prefix);
}

export const API_GET_CACHE_TTL_MS = DEFAULT_TTL_MS;
