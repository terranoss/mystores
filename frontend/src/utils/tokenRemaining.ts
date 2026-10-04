/** Milliseconds helpers for Shopee token expiry display. */

export function accessRemainingHours(expiresAt: string | null | undefined): number | null {
  if (!expiresAt) return null;
  const ms = new Date(expiresAt).getTime() - Date.now();
  if (Number.isNaN(ms)) return null;
  if (ms <= 0) return -1;
  return Math.floor(ms / (1000 * 60 * 60));
}

export function refreshRemainingDays(expiresAt: string | null | undefined): number | null {
  if (!expiresAt) return null;
  const ms = new Date(expiresAt).getTime() - Date.now();
  if (Number.isNaN(ms)) return null;
  if (ms <= 0) return -1;
  return Math.floor(ms / (1000 * 60 * 60 * 24));
}

export function formatAccessRemaining(hours: number | null): string {
  if (hours === null) return '—';
  if (hours < 0) return 'Expired';
  return `${hours}h`;
}

export function formatRefreshRemaining(days: number | null): string {
  if (days === null) return '—';
  if (days < 0) return 'Expired';
  return `${days}d`;
}
