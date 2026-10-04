export function formatOrderCurrency(amount: number, currency?: string | null): string {
  const symbol = currency === 'MYR' ? 'RM' : currency === 'SGD' ? 'S$' : '$';
  return `${symbol}${Number(amount).toFixed(2)}`;
}

export function formatOrderDate(value?: string | null): string {
  if (!value) return '—';
  return new Date(value).toLocaleDateString();
}

export function fallbackText(value?: string | null): string {
  if (!value) return '—';
  const trimmed = value.trim();
  return trimmed === '' ? '—' : trimmed;
}
