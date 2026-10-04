import { useState, useEffect, useRef, type ReactNode } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useStore } from '../context/StoreContext';
import { PageShell } from '../components/PageShell';
import Spinner from '../components/Spinner';
import { useToast } from '../context/ToastContext';
import {
  getDashboardStats,
  getAuthUrl,
  getLazadaAuthUrl,
  getStores,
  syncOrders,
  syncLazadaOrders,
  reauthorizeStore,
  disconnectStore,
  unlinkStore,
  setStoreVacationMode,
  type ShopeeStore,
  type DashboardStats,
  type StoreOrderBreakdown,
  type SalesTrendDay,
  type SalesGrowth,
  type SalesTrendStoreDay,
  type TrendDays,
  type CancelReasonStat,
} from '../api/client';
import {
  accessRemainingHours,
  refreshRemainingDays,
  formatAccessRemaining,
  formatRefreshRemaining,
} from '../utils/tokenRemaining';

const TREND_PRESETS: { days: TrendDays; label: string }[] = [
  { days: 7, label: '7 days' },
  { days: 14, label: '14 days' },
  { days: 30, label: '30 days' },
];

function formatRm(amount: number): string {
  return `RM ${amount.toLocaleString('en-MY', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

function formatDayLabel(isoDate: string): string {
  const d = new Date(`${isoDate}T12:00:00`);
  return d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
}

function formatWeekday(isoDate: string): string {
  const d = new Date(`${isoDate}T12:00:00`);
  return d.toLocaleDateString('en-GB', { weekday: 'short' });
}

function formatDayOfMonth(isoDate: string): string {
  const d = new Date(`${isoDate}T12:00:00`);
  return String(d.getDate());
}

function GrowthBadge({
  salesPct,
  ordersDelta,
  label,
}: {
  salesPct: number | null | undefined;
  ordersDelta?: number;
  label: string;
}) {
  const hasPct = salesPct !== null && salesPct !== undefined;
  const up = hasPct ? salesPct > 0 : (ordersDelta ?? 0) > 0;
  const down = hasPct ? salesPct < 0 : (ordersDelta ?? 0) < 0;
  const tone = !hasPct && (ordersDelta === undefined || ordersDelta === 0)
    ? 'muted'
    : up
      ? 'up'
      : down
        ? 'down'
        : 'flat';
  const styles = {
    up: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    down: 'bg-red-50 text-red-700 border-red-200',
    flat: 'bg-ink-50 text-ink-600 border-ink-200',
    muted: 'bg-gray-50 text-gray-500 border-gray-200',
  };
  const pctText = hasPct
    ? `${salesPct > 0 ? '+' : ''}${salesPct.toFixed(1)}%`
    : '—';
  const orderText =
    ordersDelta === undefined
      ? null
      : `${ordersDelta > 0 ? '+' : ''}${ordersDelta} ord`;

  return (
    <span
      className={`inline-flex flex-wrap items-center gap-x-1.5 gap-y-0.5 px-2 py-0.5 rounded-full text-[11px] font-semibold border ${styles[tone]}`}
      title={label}
    >
      <span className="whitespace-nowrap">{label}</span>
      <span className="tabular-nums whitespace-nowrap">{pctText}</span>
      {orderText !== null && <span className="tabular-nums opacity-80 whitespace-nowrap">{orderText}</span>}
    </span>
  );
}
function CancelReasonsSection({
  reasons,
  total,
  trendDays,
  loading,
}: {
  reasons: CancelReasonStat[];
  total: number;
  trendDays: TrendDays;
  loading?: boolean;
}) {
  if (loading) {
    return (
      <div className="bg-white rounded-2xl border border-ink-100 shadow-sm p-6 animate-pulse">
        <div className="h-4 w-40 bg-ink-100 rounded mb-4" />
        <div className="space-y-3">
          <div className="h-8 w-full bg-ink-50 rounded" />
          <div className="h-8 w-3/4 bg-ink-50 rounded" />
        </div>
      </div>
    );
  }

  const maxCount = Math.max(...reasons.map((r) => r.count), 0);

  return (
    <div className="bg-white rounded-2xl border border-ink-100 shadow-sm p-6 space-y-4">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h3 className="font-heading text-lg font-semibold text-ink-950">
            Cancel reasons
          </h3>
          <p className="text-xs text-ink-500 mt-0.5">
            Last {trendDays} days
            {total > 0 ? ` · ${total} cancelled order${total === 1 ? '' : 's'}` : ''}
          </p>
        </div>
      </div>

      {total === 0 || reasons.length === 0 ? (
        <p className="text-sm text-ink-400 py-4">
          No cancelled orders in this period.
        </p>
      ) : (
        <ul className="space-y-3">
          {reasons.map((row) => {
            const share = total > 0 ? (row.count / total) * 100 : 0;
            const barPct = maxCount > 0 ? (row.count / maxCount) * 100 : 0;
            return (
              <li key={row.reason}>
                <div className="flex items-center justify-between gap-3 mb-1">
                  <span className="text-xs font-medium text-ink-800 truncate min-w-0">
                    {row.label}
                  </span>
                  <div className="shrink-0 text-right">
                    <span className="text-xs font-semibold text-ink-950 tabular-nums">
                      {row.count}
                    </span>
                    <span className="text-[10px] text-ink-400 tabular-nums ml-1.5">
                      {share.toFixed(0)}%
                    </span>
                  </div>
                </div>
                <div className="h-2 rounded-full bg-ink-50 overflow-hidden">
                  <div
                    className="h-full rounded-full bg-red-400/80 transition-all"
                    style={{ width: `${Math.max(barPct, row.count > 0 ? 4 : 0)}%` }}
                  />
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}

function HealthBadge({ status }: { status: ShopeeStore['connection_status'] }) {
  const styles: Record<string, string> = {
    active: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    expiring_soon: 'bg-amber-50 text-amber-700 border-amber-200',
    refresh_failed: 'bg-red-50 text-red-700 border-red-200',
    reauthorize_required: 'bg-red-50 text-red-700 border-red-200',
    disconnected: 'bg-gray-50 text-gray-500 border-gray-200',
    missing_token: 'bg-red-50 text-red-700 border-red-200',
  };
  const labels: Record<string, string> = {
    active: 'Active',
    expiring_soon: 'Expiring',
    refresh_failed: 'Refresh Failed',
    reauthorize_required: 'Reauthorize Required',
    disconnected: 'Disconnected',
    missing_token: 'Missing Token',
  };
  const dotStyles: Record<string, string> = {
    active: 'bg-emerald-500',
    expiring_soon: 'bg-amber-500',
    refresh_failed: 'bg-red-500',
    reauthorize_required: 'bg-red-500',
    disconnected: 'bg-gray-400',
    missing_token: 'bg-red-500',
  };
  return (
    <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border ${styles[status]}`}>
      <span className={`w-1.5 h-1.5 rounded-full ${dotStyles[status]}`} />
      {labels[status]}
    </span>
  );
}

function chipClass(tone: 'ok' | 'warn' | 'expired' | 'muted'): string {
  const map = {
    ok: 'bg-ink-50 text-ink-600 border-ink-200',
    warn: 'bg-amber-50 text-amber-700 border-amber-200',
    expired: 'bg-red-50 text-red-700 border-red-200',
    muted: 'bg-gray-50 text-gray-400 border-gray-200',
  };
  return map[tone];
}

function TokenExpiryChips({
  accessExpiresAt,
  refreshExpiresAt,
}: {
  accessExpiresAt?: string | null;
  refreshExpiresAt?: string | null;
}) {
  const accessHours = accessRemainingHours(accessExpiresAt);
  const refreshDays = refreshRemainingDays(refreshExpiresAt);
  const accessTone: 'ok' | 'warn' | 'expired' | 'muted' =
    accessHours === null ? 'muted' : accessHours < 0 ? 'expired' : accessHours < 1 ? 'warn' : 'ok';
  const refreshTone: 'ok' | 'warn' | 'expired' | 'muted' =
    refreshDays === null ? 'muted' : refreshDays < 0 ? 'expired' : refreshDays < 7 ? 'warn' : 'ok';
  return (
    <div className="flex items-center gap-1.5">
      <span className={`inline-flex items-center px-2 py-1 rounded-full text-[11px] font-medium border ${chipClass(accessTone)}`}>
        Access {formatAccessRemaining(accessHours)}
      </span>
      <span className={`inline-flex items-center px-2 py-1 rounded-full text-[11px] font-medium border ${chipClass(refreshTone)}`}>
        Refresh {formatRefreshRemaining(refreshDays)}
      </span>
    </div>
  );
}

function PlatformBadge({ platform }: { platform: ShopeeStore['platform'] | string }) {
  if (platform === 'lazada') {
    return (
      <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-600 text-white border border-blue-600">
        Lazada
      </span>
    );
  }
  return (
    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-600 text-white border border-red-600">
      Shopee
    </span>
  );
}

function VacationModeToggle({
  store,
  isOwner,
  onToggle,
  isToggling,
}: {
  store: ShopeeStore;
  isOwner: boolean;
  onToggle: (store: ShopeeStore) => void;
  isToggling: boolean;
}) {
  if (store.platform !== 'shopee') {
    return null;
  }

  const isConnected = store.connection_status === 'active' || store.connection_status === 'expiring_soon';
  const isVacation = Boolean(store.vacation_mode);
  const disabled = !isOwner || !isConnected || isToggling;

  const tooltip = !isConnected
    ? 'Connect store to change vacation mode'
    : !isOwner
      ? 'Only workspace owner can change vacation mode'
      : isVacation
        ? 'Vacation mode is active (buyers cannot place new orders). Click to turn off.'
        : 'Turn on vacation mode to stop receiving new orders.';

  return (
    <div className="inline-flex items-center" title={tooltip}>
      <button
        type="button"
        role="switch"
        aria-checked={isVacation}
        disabled={disabled}
        onClick={() => onToggle(store)}
        className={`relative inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border transition-all select-none ${
          isVacation
            ? 'bg-amber-50 text-amber-900 border-amber-300 hover:bg-amber-100'
            : 'bg-ink-50 text-ink-600 border-ink-200 hover:bg-ink-100'
        } ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer active:scale-[0.97]'}`}
      >
        <span>{isVacation ? '🌴 Vacation ON' : 'Vacation Off'}</span>
        <span
          className={`inline-flex items-center w-7 h-4 rounded-full transition-colors relative ${
            isVacation ? 'bg-amber-500' : 'bg-ink-300'
          }`}
        >
          {isToggling ? (
            <span className="w-2.5 h-2.5 mx-auto border-2 border-white border-t-transparent rounded-full animate-spin" />
          ) : (
            <span
              className={`inline-block w-3 h-3 bg-white rounded-full shadow-sm transform transition-transform ${
                isVacation ? 'translate-x-3.5' : 'translate-x-0.5'
              }`}
            />
          )}
        </span>
      </button>
    </div>
  );
}

function StoreBreakdownPanel({ breakdown }: { breakdown: StoreOrderBreakdown[] }) {
  if (breakdown.length === 0) {
    return <p className="text-xs text-ink-400 px-1 py-0.5">None</p>;
  }
  const showGmv = breakdown.some((row) => row.gmv !== undefined);
  return (
    <ul className="space-y-2">
      {breakdown.map((row) => (
        <li key={row.store_id} className="flex items-center justify-between gap-3">
          <div className="min-w-0 flex items-center gap-2">
            <span className="text-xs font-medium text-ink-800 truncate">
              {row.shop_name || `Store #${row.store_id}`}
            </span>
            <PlatformBadge platform={row.platform} />
          </div>
          <div className="text-right shrink-0">
            {showGmv ? (
              <>
                <p className="text-xs font-semibold text-ink-950 tabular-nums">{formatRm(row.gmv ?? 0)}</p>
                <p className="text-[10px] text-ink-400 tabular-nums">{row.count} order{row.count === 1 ? '' : 's'}</p>
              </>
            ) : (
              <span className="text-xs font-semibold text-ink-950 tabular-nums">{row.count}</span>
            )}
          </div>
        </li>
      ))}
    </ul>
  );
}

function SalesTrendSection({
  trend,
  growth,
  trendDays,
  onTrendDaysChange,
  loading,
}: {
  trend: SalesTrendDay[];
  growth?: SalesGrowth | null;
  trendDays: TrendDays;
  onTrendDaysChange: (days: TrendDays) => void;
  loading?: boolean;
}) {
  const [selectedDate, setSelectedDate] = useState<string | null>(null);
  const [hoveredDate, setHoveredDate] = useState<string | null>(null);
  const compactLabels = trendDays >= 30;

  useEffect(() => {
    if (trend.length === 0) {
      setSelectedDate(null);
      return;
    }
    setSelectedDate((prev) =>
      prev && trend.some((d) => d.date === prev) ? prev : trend[trend.length - 1].date
    );
  }, [trend]);

  if (loading) {
    return (
      <div className="bg-white rounded-2xl border border-ink-100 shadow-sm p-6 animate-pulse">
        <div className="h-4 w-32 bg-ink-100 rounded mb-4" />
        <div className="h-40 w-full bg-ink-50 rounded" />
      </div>
    );
  }

  const totalSales = trend.reduce((sum, d) => sum + d.gmv, 0);
  const totalOrders = trend.reduce((sum, d) => sum + d.orders, 0);
  const maxSales = Math.max(...trend.map((d) => d.gmv), 0);
  const selected = trend.find((d) => d.date === selectedDate) ?? null;
  const hovered = trend.find((d) => d.date === hoveredDate) ?? null;
  const tipDay = hovered ?? selected;

  const storeSummary = (() => {
    const map = new Map<
      number,
      { store_id: number; shop_name: string | null; platform: string; orders: number; gmv: number }
    >();
    for (const day of trend) {
      for (const s of day.by_store ?? []) {
        const prev = map.get(s.store_id);
        if (prev) {
          prev.orders += s.orders;
          prev.gmv += s.gmv;
        } else {
          map.set(s.store_id, {
            store_id: s.store_id,
            shop_name: s.shop_name,
            platform: s.platform,
            orders: s.orders,
            gmv: s.gmv,
          });
        }
      }
    }
    return [...map.values()].sort((a, b) => b.gmv - a.gmv);
  })();
  const maxStoreSales = Math.max(...storeSummary.map((s) => s.gmv), 0);

  return (
    <div className="bg-white rounded-2xl border border-ink-100 shadow-sm p-6 space-y-5">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <div className="flex flex-wrap items-center gap-3">
            <h3 className="font-heading text-lg font-semibold text-ink-950">
              Sales ({trendDays} days)
            </h3>
            <div
              className="inline-flex rounded-lg border border-ink-200 bg-ink-50 p-0.5"
              role="group"
              aria-label="Sales period"
            >
              {TREND_PRESETS.map((preset) => {
                const active = preset.days === trendDays;
                return (
                  <button
                    key={preset.days}
                    type="button"
                    onClick={() => onTrendDaysChange(preset.days)}
                    className={`px-2.5 py-1 text-[11px] font-semibold rounded-md transition ${
                      active
                        ? 'bg-white text-ink-950 shadow-sm'
                        : 'text-ink-500 hover:text-ink-800'
                    }`}
                    aria-pressed={active}
                  >
                    {preset.label}
                  </button>
                );
              })}
            </div>
          </div>
          <p className="text-xs text-ink-400 mt-0.5">Non-cancelled · MYT · hover for details, click a day for stores</p>
          {growth && (
            <div className="flex flex-wrap gap-2 mt-2">
              <GrowthBadge
                label="vs yesterday"
                salesPct={growth.today_vs_yesterday.sales_pct}
                ordersDelta={growth.today_vs_yesterday.orders_delta}
              />
              <GrowthBadge
                label={`vs prior ${trendDays}d`}
                salesPct={growth.period_vs_prior.sales_pct}
                ordersDelta={growth.period_vs_prior.orders_delta}
              />
            </div>
          )}
        </div>
        <div className="flex items-end gap-6">
          <div className="text-right">
            <p className="text-[10px] uppercase tracking-wide text-ink-400 font-semibold">Sales</p>
            <p className="text-lg font-bold font-heading text-ink-950 tabular-nums">{formatRm(totalSales)}</p>
          </div>
          <div className="text-right">
            <p className="text-[10px] uppercase tracking-wide text-ink-400 font-semibold">Orders</p>
            <p className="text-lg font-bold font-heading text-ink-950 tabular-nums">{totalOrders}</p>
          </div>
        </div>
      </div>

      <div className="relative">
        {tipDay && (
          <div className="mb-3 rounded-xl border border-ink-100 bg-ink-50/80 px-3 py-2 text-sm flex flex-wrap items-center justify-between gap-2">
            <span className="font-medium text-ink-800">{formatDayLabel(tipDay.date)}</span>
            <span className="tabular-nums text-ink-950 font-semibold">
              {formatRm(tipDay.gmv)}
              <span className="text-ink-400 font-medium ml-2">
                {tipDay.orders} order{tipDay.orders === 1 ? '' : 's'}
              </span>
            </span>
          </div>
        )}

        <div
          className={`flex items-end justify-between h-40 px-1 ${compactLabels ? 'gap-0.5' : 'gap-2'}`}
          role="list"
          aria-label={`Daily sales for the last ${trendDays} days`}
        >
          {trend.map((day) => {
            const ratio = maxSales > 0 ? day.gmv / maxSales : 0;
            const heightPct = day.orders > 0 || day.gmv > 0 ? Math.max(ratio * 100, 8) : 2;
            const isSelected = day.date === selectedDate;
            const isHovered = day.date === hoveredDate;
            return (
              <button
                key={day.date}
                type="button"
                role="listitem"
                className="flex-1 flex flex-col items-center justify-end h-full gap-1.5 group focus:outline-none min-w-0"
                onMouseEnter={() => setHoveredDate(day.date)}
                onMouseLeave={() => setHoveredDate(null)}
                onFocus={() => setHoveredDate(day.date)}
                onBlur={() => setHoveredDate(null)}
                onClick={() => setSelectedDate(day.date)}
                aria-pressed={isSelected}
                aria-label={`${formatDayLabel(day.date)}: ${formatRm(day.gmv)}, ${day.orders} orders`}
              >
                {!compactLabels && (
                  <span className="text-[10px] tabular-nums text-ink-500 font-medium h-4">
                    {day.gmv > 0 ? Math.round(day.gmv) : ''}
                  </span>
                )}
                <div className="w-full flex-1 flex items-end justify-center">
                  <div
                    className={`w-full ${compactLabels ? 'max-w-[18px]' : 'max-w-[40px]'} rounded-t-md transition-colors ${
                      isSelected || isHovered ? 'bg-clay-500' : 'bg-clay-400/70 group-hover:bg-clay-500'
                    }`}
                    style={{ height: `${heightPct}%` }}
                  />
                </div>
                <span
                  className={`text-[10px] font-semibold ${
                    isSelected ? 'text-ink-950' : 'text-ink-400'
                  }`}
                >
                  {compactLabels ? formatDayOfMonth(day.date) : formatWeekday(day.date)}
                </span>
                {!compactLabels && (
                  <span className="text-[10px] tabular-nums text-ink-400">{day.orders}</span>
                )}
              </button>
            );
          })}
        </div>
      </div>

      {selected && (
        <div className="rounded-xl border border-ink-100 p-3">
          <p className="text-[10px] uppercase tracking-wide text-ink-400 font-semibold mb-2">
            {formatDayLabel(selected.date)} · by store
          </p>
          {(selected.by_store ?? []).length === 0 ? (
            <p className="text-xs text-ink-400">No sales this day</p>
          ) : (
            <ul className="space-y-2">
              {(selected.by_store as SalesTrendStoreDay[]).map((row) => (
                <li key={row.store_id} className="flex items-center justify-between gap-3">
                  <div className="min-w-0 flex items-center gap-2">
                    <span className="text-xs font-medium text-ink-800 truncate">
                      {row.shop_name || `Store #${row.store_id}`}
                    </span>
                    <PlatformBadge platform={row.platform} />
                  </div>
                  <div className="text-right shrink-0">
                    <p className="text-xs font-semibold text-ink-950 tabular-nums">{formatRm(row.gmv)}</p>
                    <p className="text-[10px] text-ink-400 tabular-nums">
                      {row.orders} order{row.orders === 1 ? '' : 's'}
                    </p>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}

      <div>
        <p className="text-[10px] uppercase tracking-wide text-ink-400 font-semibold mb-2">
          By store (this period)
        </p>
        {storeSummary.length === 0 ? (
          <p className="text-xs text-ink-400">No sales in this window</p>
        ) : (
          <ul className="space-y-3">
            {storeSummary.map((row) => {
              const share = totalSales > 0 ? (row.gmv / totalSales) * 100 : 0;
              const barPct = maxStoreSales > 0 ? (row.gmv / maxStoreSales) * 100 : 0;
              return (
                <li key={row.store_id}>
                  <div className="flex items-center justify-between gap-3 mb-1">
                    <div className="min-w-0 flex items-center gap-2">
                      <span className="text-xs font-medium text-ink-800 truncate">
                        {row.shop_name || `Store #${row.store_id}`}
                      </span>
                      <PlatformBadge platform={row.platform} />
                    </div>
                    <div className="text-right shrink-0">
                      <p className="text-xs font-semibold text-ink-950 tabular-nums">{formatRm(row.gmv)}</p>
                      <p className="text-[10px] text-ink-400 tabular-nums">
                        {row.orders} ord · {share.toFixed(0)}%
                      </p>
                    </div>
                  </div>
                  <div className="h-1.5 rounded-full bg-ink-50 overflow-hidden">
                    <div className="h-full rounded-full bg-clay-400" style={{ width: `${barPct}%` }} />
                  </div>
                </li>
              );
            })}
          </ul>
        )}
      </div>
    </div>
  );
}

function StatCard({
  label,
  value,
  icon,
  loading,
  breakdown,
  growthBadge,
  actionButton,
}: {
  label: string;
  value: string | number;
  icon: string;
  loading?: boolean;
  breakdown?: StoreOrderBreakdown[];
  growthBadge?: ReactNode;
  actionButton?: ReactNode;
}) {
  const [pinnedOpen, setPinnedOpen] = useState(false);
  const [hovered, setHovered] = useState(false);
  const cardRef = useRef<HTMLDivElement>(null);
  const interactive = breakdown !== undefined;
  const open = interactive && (pinnedOpen || hovered);

  useEffect(() => {
    if (!pinnedOpen) return;
    const onPointerDown = (e: MouseEvent | TouchEvent) => {
      if (cardRef.current && !cardRef.current.contains(e.target as Node)) {
        setPinnedOpen(false);
      }
    };
    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setPinnedOpen(false);
    };
    document.addEventListener('mousedown', onPointerDown);
    document.addEventListener('touchstart', onPointerDown);
    document.addEventListener('keydown', onKeyDown);
    return () => {
      document.removeEventListener('mousedown', onPointerDown);
      document.removeEventListener('touchstart', onPointerDown);
      document.removeEventListener('keydown', onKeyDown);
    };
  }, [pinnedOpen]);

  if (loading) {
    return (
      <div className="bg-white rounded-2xl border border-ink-100 p-6 shadow-sm animate-pulse">
        <div className="flex items-center justify-between mb-3">
          <div className="w-8 h-8 rounded-xl bg-ink-100" />
        </div>
        <div className="h-8 w-16 bg-ink-100 rounded mb-2" />
        <div className="h-4 w-24 bg-ink-50 rounded" />
      </div>
    );
  }

  return (
    <div
      ref={cardRef}
      className={`bg-white rounded-2xl border border-ink-100 p-6 shadow-sm relative ${
        interactive ? 'cursor-pointer hover:border-ink-200' : ''
      }`}
      onMouseEnter={() => interactive && setHovered(true)}
      onMouseLeave={() => setHovered(false)}
      onClick={() => interactive && setPinnedOpen((v) => !v)}
      onKeyDown={(e) => {
        if (!interactive) return;
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          setPinnedOpen((v) => !v);
        }
      }}
      role={interactive ? 'button' : undefined}
      tabIndex={interactive ? 0 : undefined}
      aria-expanded={interactive ? open : undefined}
      aria-label={interactive ? `${label}: ${value}. Show store breakdown.` : undefined}
    >
      <div className="flex items-center justify-between mb-3 gap-2">
        <span className="text-2xl text-clay-400">{icon}</span>
        {actionButton && <div onClick={(e) => e.stopPropagation()}>{actionButton}</div>}
      </div>
      <p className={`${typeof value === 'string' && value.length > 8 ? 'text-2xl' : 'text-3xl'} font-bold font-heading text-ink-950 tabular-nums break-all`}>
        {value}
      </p>
      <p className="text-sm text-ink-400 mt-1 font-medium">{label}</p>
      {growthBadge && <div className="mt-2" onClick={(e) => e.stopPropagation()}>{growthBadge}</div>}
      {open && (
        <div
          className="absolute left-0 right-0 top-full z-20 mt-2 rounded-xl border border-ink-100 bg-white p-3 shadow-lg"
          onClick={(e) => e.stopPropagation()}
        >
          <p className="text-[10px] uppercase tracking-wide text-ink-400 font-semibold mb-2">By store</p>
          <StoreBreakdownPanel breakdown={breakdown ?? []} />
        </div>
      )}
    </div>
  );
}

export default function Dashboard() {
  const navigate = useNavigate();
  const { user } = useAuth();
  const { showToast } = useToast();
  const { selectedStoreId, selectedMarketplace } = useStore();
  const [stores, setStores] = useState<ShopeeStore[]>([]);
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [isOwner, setIsOwner] = useState(true);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [linking, setLinking] = useState(false);
  const [linkingLazada, setLinkingLazada] = useState(false);
  const [syncing, setSyncing] = useState(false);
  const [togglingVacationId, setTogglingVacationId] = useState<number | null>(null);
  const [trendDays, setTrendDays] = useState<TrendDays>(7);
  const trendDaysRef = useRef(trendDays);
  trendDaysRef.current = trendDays;

  const fetchData = async (days: TrendDays = trendDaysRef.current) => {
    setLoading(true);
    setError(null);
    try {
      const [statsData, storesData] = await Promise.all([
        getDashboardStats({
          trendDays: days,
          marketplace: selectedMarketplace,
          storeId: selectedStoreId,
        }),
        getStores(),
      ]);
      setStats(statsData.stats);
      setStores(storesData);
      setIsOwner(
        statsData.workspace?.role === 'owner' ||
          user?.is_workspace_owner === true ||
          statsData.workspace?.role == null
      );
    } catch (e: any) {
      setError(e?.response?.data?.message || 'Failed to load dashboard data.');
    } finally {
      setLoading(false);
    }
  };

  const handleTrendDaysChange = (days: TrendDays) => {
    if (days === trendDays) return;
    setTrendDays(days);
    fetchData(days);
  };

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('linked') === '1') window.history.replaceState({}, '', '/dashboard');
    if (params.get('error')) {
      const errorCode = params.get('error');
      const messages: Record<string, string> = {
        oauth_failed: 'Failed to link store.',
        reauthorize_shop_mismatch: 'Reauthorization failed: the returned store ID did not match the intended store. Please try again.',
      };
      showToast(messages[errorCode!] || 'Failed to link store: ' + errorCode, 'error');
      window.history.replaceState({}, '', '/dashboard');
    }
  }, []);

  useEffect(() => {
    fetchData(trendDays);
  }, [selectedMarketplace, selectedStoreId]);

  const handleLinkStore = async () => {
    setLinking(true);
    try {
      const url = await getAuthUrl();
      window.location.href = url;
    } catch {
      showToast('Failed to generate auth URL.', 'error');
      setLinking(false);
    }
  };

  const handleLinkLazadaStore = async () => {
    setLinkingLazada(true);
    try {
      const url = await getLazadaAuthUrl();
      window.location.href = url;
    } catch {
      showToast('Failed to generate Lazada auth URL.', 'error');
      setLinkingLazada(false);
    }
  };

  const handleReauthorize = async (store: ShopeeStore) => {
    setLinking(true);
    try {
      const url = await reauthorizeStore(store.id, store.platform);
      window.location.href = url;
    } catch {
      showToast('Failed to generate reauthorization URL.', 'error');
      setLinking(false);
    }
  };

  const handleDisconnect = async (store: ShopeeStore) => {
    if (!confirm(`Disconnect "${store.shop_name || 'Shop #' + store.shop_id}"?\n\nSyncing will stop, but products, orders, and history will be kept. You can reconnect later.`)) return;
    try {
      await disconnectStore(store.id, store.platform);
      fetchData();
    } catch {
      showToast('Failed to disconnect store.', 'error');
    }
  };

  const handleUnlink = async (store: ShopeeStore) => {
    const phrase = window.prompt(
      `Permanently delete "${store.shop_name || 'Shop #' + store.shop_id}" and all related data?\n\nType DELETE STORE DATA to confirm:`
    );
    if (phrase === null) return;
    if (phrase !== 'DELETE STORE DATA') {
      showToast('Unlink cancelled — confirmation phrase did not match.', 'error');
      return;
    }
    try {
      await unlinkStore(store.id, store.platform);
      showToast('Store unlinked permanently.', 'success');
      fetchData();
    } catch {
      showToast('Failed to unlink store.', 'error');
    }
  };

  const handleToggleVacationMode = async (store: ShopeeStore) => {
    const isCurrentlyOn = Boolean(store.vacation_mode);
    const shopLabel = store.shop_name || `Shop #${store.shop_id}`;
    const nextState = !isCurrentlyOn;

    const message = nextState
      ? `Enable Vacation Mode for "${shopLabel}"?\n\nBuyers will not be able to place new orders while Vacation Mode is active. Existing orders must still be fulfilled within deadline.`
      : `Disable Vacation Mode for "${shopLabel}"?\n\nNote: Shopee enforces a ~3-hour cooldown before Vacation Mode can be re-enabled.`;

    if (!window.confirm(message)) {
      return;
    }

    setTogglingVacationId(store.id);
    try {
      const res = await setStoreVacationMode(store.id, nextState);
      showToast(res.message || `Vacation mode ${nextState ? 'enabled' : 'disabled'}.`, 'success');
      setStores((prev) =>
        prev.map((s) =>
          s.id === store.id
            ? { ...s, vacation_mode: nextState, vacation_mode_synced_at: new Date().toISOString() }
            : s
        )
      );
    } catch (err: unknown) {
      const resp = (err as { response?: { data?: { message?: string; vacation_mode?: boolean } } })?.response?.data;
      const errorMsg =
        resp?.message ||
        (err as Error)?.message ||
        'Failed to update vacation mode.';
      showToast(errorMsg, 'error');
      if (resp?.vacation_mode !== undefined) {
        setStores((prev) =>
          prev.map((s) => (s.id === store.id ? { ...s, vacation_mode: resp.vacation_mode } : s))
        );
      }
    } finally {
      setTogglingVacationId(null);
    }
  };

  // Filter stores according to top header controls
  const activeStores = stores.filter((s) => {
    if (selectedMarketplace !== 'all' && s.platform !== selectedMarketplace) return false;
    if (selectedStoreId !== null && s.id !== selectedStoreId) return false;
    return true;
  });

  const filterBreakdown = (list?: StoreOrderBreakdown[]) => {
    if (!list) return [];
    return list.filter((item) => {
      if (selectedMarketplace !== 'all' && item.platform !== selectedMarketplace) return false;
      if (selectedStoreId !== null && item.store_id !== selectedStoreId) return false;
      return true;
    });
  };

  const calcOrdersToday = () => {
    if (selectedMarketplace === 'all' && selectedStoreId === null) return stats?.orders_today ?? 0;
    return filterBreakdown(stats?.orders_today_by_store).reduce((sum, item) => sum + item.count, 0);
  };

  const calcGmvToday = () => {
    if (selectedMarketplace === 'all' && selectedStoreId === null) return stats?.gmv_today ?? 0;
    return filterBreakdown(stats?.gmv_today_by_store).reduce((sum, item) => sum + (item.gmv ?? 0), 0);
  };

  const calcPendingOrders = () => {
    if (selectedMarketplace === 'all' && selectedStoreId === null) return stats?.pending_orders_count ?? 0;
    return filterBreakdown(stats?.pending_orders_by_store).reduce((sum, item) => sum + item.count, 0);
  };

  const hasShopeeStores = stores.some((s) => s.platform === 'shopee');
  const hasLazadaStores = stores.some((s) => s.platform === 'lazada');

  const handleSync = async () => {
    setSyncing(true);
    try {
      if (selectedMarketplace === 'shopee') {
        const results = await syncOrders('shopee');
        showToast(results.join('\n') || 'Shopee sync completed.', 'success');
      } else if (selectedMarketplace === 'lazada') {
        const results = await syncLazadaOrders();
        showToast(results.join('\n') || 'Lazada sync completed.', 'success');
      } else {
        const messages: string[] = [];
        if (hasShopeeStores) {
          try {
            const results = await syncOrders('shopee');
            messages.push(...(results.length ? results : ['Shopee: sync completed.']));
          } catch {
            messages.push('Shopee sync failed.');
          }
        }
        if (hasLazadaStores) {
          try {
            const results = await syncLazadaOrders();
            messages.push(...(results.length ? results : ['Lazada: sync completed.']));
          } catch {
            messages.push('Lazada sync failed.');
          }
        }
        if (messages.length === 0) {
          showToast('No stores to sync. Link a store from the Dashboard.', 'info');
        } else {
          const failed = messages.some((m) => m.toLowerCase().includes('failed'));
          showToast(messages.join('\n'), failed ? 'error' : 'success');
        }
      }
      fetchData();
    } finally {
      setSyncing(false);
    }
  };

  const syncLabel =
    selectedMarketplace === 'shopee'
      ? 'Sync Shopee'
      : selectedMarketplace === 'lazada'
        ? 'Sync Lazada'
        : 'Sync';

  const cards: Array<{
    label: string;
    value: string | number;
    icon: string;
    breakdown?: StoreOrderBreakdown[];
    growthBadge?: ReactNode;
    actionButton?: ReactNode;
  }> = [
    { label: 'Linked Stores', value: activeStores.length, icon: '◉' },
    {
      label: 'Orders Today',
      value: calcOrdersToday(),
      icon: '◈',
      breakdown: filterBreakdown(stats?.orders_today_by_store),
      actionButton: (
        <button
          type="button"
          onClick={() => navigate('/pick-list/today')}
          title="Open today's pick list"
          className="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold bg-clay-500 hover:bg-clay-600 text-white rounded-lg transition shadow-sm active:scale-95"
        >
          <span>📋 Pick List</span>
        </button>
      ),
    },
    {
      label: 'Sales Today',
      value: formatRm(calcGmvToday()),
      icon: '◈',
      breakdown: filterBreakdown(stats?.gmv_today_by_store),
      growthBadge: stats?.sales_growth ? (
        <GrowthBadge
          label="vs yday"
          salesPct={stats.sales_growth.today_vs_yesterday.sales_pct}
          ordersDelta={stats.sales_growth.today_vs_yesterday.orders_delta}
        />
      ) : undefined,
    },
    {
      label: 'Pending Orders',
      value: calcPendingOrders(),
      icon: '◉',
      breakdown: filterBreakdown(stats?.pending_orders_by_store),
      actionButton: (
        <button
          type="button"
          onClick={() => navigate('/pick-list/pending')}
          title="Open pending pick list"
          className="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold bg-clay-500 hover:bg-clay-600 text-white rounded-lg transition shadow-sm active:scale-95"
        >
          <span>📋 Pick List</span>
        </button>
      ),
    },
    { label: 'Low Stock Items', value: stats?.low_stock_items ?? 0, icon: '◇' },
  ];

  return (
    <PageShell>
      <div className="max-w-5xl space-y-8">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h2 className="font-heading text-2xl font-bold text-ink-950">Dashboard</h2>
            <p className="text-ink-400 text-sm mt-1">Welcome back, {user?.name}</p>
          </div>
          <div className="flex items-center gap-3">
            <button
              onClick={handleSync}
              disabled={syncing}
              className={`inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-xl disabled:opacity-50 transition ${
                selectedMarketplace === 'shopee'
                  ? 'bg-red-600 hover:bg-red-700'
                  : selectedMarketplace === 'lazada'
                    ? 'bg-blue-600 hover:bg-blue-700'
                    : 'bg-ink-950 hover:bg-ink-800'
              }`}
            >
              {syncing && <Spinner />}
              {syncing ? 'Syncing…' : syncLabel}
            </button>
          </div>
        </div>

        {error ? (
          /* Error state */
          <div className="bg-white rounded-2xl border border-ink-100 shadow-sm p-12 text-center">
            <div className="w-14 h-14 rounded-2xl bg-red-50 flex items-center justify-center mx-auto mb-4">
              <span className="text-2xl text-red-500">!</span>
            </div>
            <h4 className="font-heading text-base font-semibold text-ink-950 mb-1">Failed to load dashboard</h4>
            <p className="text-sm text-ink-400 max-w-sm mx-auto mb-6">{error}</p>
            <button
              onClick={() => fetchData()}
              className="px-5 py-2.5 text-sm font-semibold bg-ink-950 text-white rounded-xl hover:bg-ink-800 transition active:scale-[0.97]"
            >
              Retry
            </button>
          </div>
        ) : (
          <>
            <div className="space-y-8">
              {/* Stats */}
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 overflow-visible">
                {cards.map((stat) => (
                  <StatCard key={stat.label} {...stat} loading={loading} />
                ))}
              </div>

              <SalesTrendSection
                trend={stats?.sales_trend ?? []}
                growth={stats?.sales_growth}
                trendDays={trendDays}
                onTrendDaysChange={handleTrendDaysChange}
                loading={loading}
              />

              <CancelReasonsSection
                reasons={stats?.cancel_reasons ?? []}
                total={stats?.cancel_reasons_total ?? 0}
                trendDays={trendDays}
                loading={loading}
              />
            </div>
          </>
        )}

        {/* Low Stock Alerts */}
        {!loading && !error && stats && stats.low_stock_items > 0 && (
          <div className="bg-amber-50 border border-amber-200 rounded-2xl p-5 flex items-center gap-4">
            <span className="text-xl text-amber-600">◇</span>
            <div>
              <p className="text-sm font-semibold text-amber-900">
                {stats.low_stock_items} product{stats.low_stock_items !== 1 ? 's' : ''} running low on stock
              </p>
              <p className="text-xs text-amber-700 mt-0.5">
                <a href="/stock" className="underline font-medium">Go to Stock & Price</a> to review and reorder.
              </p>
            </div>
          </div>
        )}

        {/* Stores section */}
        <div className="bg-white rounded-2xl border border-ink-100 shadow-sm overflow-hidden">
          <div className="px-6 py-5 border-b border-ink-100 flex items-center justify-between">
            <h3 className="font-heading text-lg font-semibold text-ink-950">Linked Stores</h3>
            {isOwner && (
              <div className="flex items-center gap-2">
                <button
                  onClick={handleLinkStore}
                  disabled={linking || linkingLazada}
                  className="px-4 py-2 text-sm font-semibold bg-red-600 text-white rounded-xl hover:bg-red-700 disabled:opacity-50 transition-all active:scale-[0.97]"
                >
                  {linking ? 'Redirecting…' : '+ Shopee'}
                </button>
                <button
                  onClick={handleLinkLazadaStore}
                  disabled={linking || linkingLazada}
                  className="px-4 py-2 text-sm font-semibold bg-orange-500 text-white rounded-xl hover:bg-orange-600 disabled:opacity-50 transition-all active:scale-[0.97]"
                >
                  {linkingLazada ? 'Redirecting…' : '+ Lazada'}
                </button>
              </div>
            )}
          </div>

          {loading ? (
            /* Loading skeleton for store list */
            <div className="divide-y divide-ink-50">
              {[1, 2].map((i) => (
                <div key={i} className="px-6 py-4 animate-pulse flex items-center gap-4">
                  <div className="w-10 h-10 rounded-xl bg-ink-100" />
                  <div className="flex-1 space-y-2">
                    <div className="h-4 bg-ink-100 rounded w-36" />
                    <div className="h-3 bg-ink-50 rounded w-24" />
                  </div>
                  <div className="h-6 w-16 bg-ink-100 rounded-full" />
                </div>
              ))}
            </div>
          ) : stores.length === 0 ? (
            <div className="p-12 text-center">
              <div className="w-14 h-14 rounded-2xl bg-clay-100 flex items-center justify-center mx-auto mb-4">
                <span className="text-2xl text-clay-500">◉</span>
              </div>
              <h4 className="font-heading text-base font-semibold text-ink-950 mb-1">No stores linked yet</h4>
              <p className="text-sm text-ink-400 max-w-sm mx-auto mb-6">
                {isOwner
                  ? 'Connect your first store to start managing orders, tracking stock, and monitoring performance.'
                  : 'No stores in this workspace yet. Ask the workspace owner to link a store.'}
              </p>
              {isOwner && (
                <div className="flex items-center justify-center gap-3">
                  <button
                    onClick={handleLinkStore}
                    disabled={linking || linkingLazada}
                    className="px-5 py-2.5 text-sm font-semibold bg-red-600 text-white rounded-xl hover:bg-red-700 disabled:opacity-50 transition-all active:scale-[0.97]"
                  >
                    {linking ? 'Redirecting…' : 'Link Shopee Store'}
                  </button>
                  <button
                    onClick={handleLinkLazadaStore}
                    disabled={linking || linkingLazada}
                    className="px-5 py-2.5 text-sm font-semibold bg-orange-500 text-white rounded-xl hover:bg-orange-600 disabled:opacity-50 transition-all active:scale-[0.97]"
                  >
                    {linkingLazada ? 'Redirecting…' : 'Link Lazada Store'}
                  </button>
                </div>
              )}
            </div>
          ) : (
            <div className="divide-y divide-ink-50">
              {stores.map((store) => (
                <div key={store.id} className="px-6 py-4 flex items-center justify-between hover:bg-ink-50/50 transition">
                  <div className="flex items-center gap-4">
                    <div className={`w-10 h-10 rounded-xl flex items-center justify-center font-heading font-bold text-sm ${
                      store.platform === 'lazada' ? 'bg-orange-100 text-orange-600' : 'bg-clay-100 text-clay-600'
                    }`}>
                      {store.region?.toUpperCase() || '?'}
                    </div>
                    <div>
                      <div className="flex items-center gap-2">
                        <p className="font-semibold text-ink-900 text-sm">{store.shop_name || `Shop #${store.shop_id}`}</p>
                        <PlatformBadge platform={store.platform ?? 'shopee'} />
                      </div>
                      <p className="text-xs text-ink-400 mt-0.5">
                        ID: {store.shop_id}
                        {store.region && ` · ${store.region.toUpperCase()}`}
                        {' · '}{(store.products_count ?? 0).toLocaleString()} product{(store.products_count ?? 0) === 1 ? '' : 's'}
                        {' · '}Linked {new Date(store.linked_at).toLocaleDateString()}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center gap-2 flex-wrap justify-end">
                    <TokenExpiryChips
                      accessExpiresAt={store.access_token_expires_at}
                      refreshExpiresAt={store.refresh_token_expires_at}
                    />
                    {store.connection_status !== 'expiring_soon' && (
                      <HealthBadge status={store.connection_status} />
                    )}
                    <VacationModeToggle
                      store={store}
                      isOwner={isOwner}
                      onToggle={handleToggleVacationMode}
                      isToggling={togglingVacationId === store.id}
                    />
                    {isOwner && ['active', 'expiring_soon', 'refresh_failed', 'reauthorize_required'].includes(store.connection_status) && (
                      <button
                        onClick={() => handleDisconnect(store)}
                        className="px-3 py-1.5 text-xs font-semibold text-red-600 bg-red-50 border border-red-200 rounded-xl hover:bg-red-100 transition active:scale-[0.97]"
                      >
                        Disconnect
                      </button>
                    )}
                    {/* Needs reauthorization: show Reauthorize */}
                    {isOwner && store.requires_reauthorization && (
                      <button
                        onClick={() => handleReauthorize(store)}
                        disabled={linking}
                        className="px-3 py-1.5 text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-xl hover:bg-amber-100 disabled:opacity-50 transition active:scale-[0.97]"
                      >
                        Reauthorize
                      </button>
                    )}
                    {/* Disconnected or missing token: show Reconnect */}
                    {isOwner && (store.connection_status === 'disconnected' || store.connection_status === 'missing_token') && (
                      <button
                        onClick={() => store.platform === 'lazada' ? handleLinkLazadaStore() : handleLinkStore()}
                        disabled={linking || linkingLazada}
                        className="px-3 py-1.5 text-xs font-semibold text-ink-700 bg-ink-50 border border-ink-200 rounded-xl hover:bg-ink-100 disabled:opacity-50 transition active:scale-[0.97]"
                      >
                        Reconnect
                      </button>
                    )}
                    {isOwner && (
                    <button
                      onClick={() => handleUnlink(store)}
                      className="px-3 py-1.5 text-xs font-semibold text-red-700 bg-white border border-red-300 rounded-xl hover:bg-red-50 transition active:scale-[0.97]"
                    >
                      Unlink
                    </button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </PageShell>
  );
}
