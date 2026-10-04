import { createContext, useContext, useEffect, useState, type ReactNode, type HTMLAttributes } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import ErrorBoundary from './ErrorBoundary';
import Dashboard from '../pages/Dashboard';
import Orders from '../pages/Orders';
import Stock from '../pages/Stock';
import PickList from '../pages/PickList';
import Promotions from '../pages/Promotions';
import Team from '../pages/Team';

/** Frozen path key for keep-alive slots so route hooks do not follow the active URL. */
export const KeepAlivePathContext = createContext<string | null>(null);

export function useKeepAlivePath(): string | null {
  return useContext(KeepAlivePathContext);
}

type HeavyEntry = {
  key: string;
  match: (pathname: string) => boolean;
  render: () => ReactNode;
};

const HEAVY: HeavyEntry[] = [
  { key: 'orders', match: (p) => p === '/orders', render: () => <Orders /> },
  { key: 'stock', match: (p) => p === '/stock', render: () => <Stock /> },
  { key: 'pick-today', match: (p) => p === '/pick-list/today', render: () => <PickList /> },
  { key: 'pick-pending', match: (p) => p === '/pick-list/pending', render: () => <PickList /> },
];

type LightEntry = {
  match: (pathname: string) => boolean;
  render: () => ReactNode;
};

const LIGHT: LightEntry[] = [
  { match: (p) => p === '/dashboard', render: () => <Dashboard /> },
  { match: (p) => p === '/promotions', render: () => <Promotions /> },
  { match: (p) => p === '/team', render: () => <Team /> },
];

function Slot({
  pathKey,
  active,
  children,
}: {
  pathKey: string;
  active: boolean;
  children: ReactNode;
}) {
  return (
    <div
      style={{ display: active ? 'block' : 'none' }}
      hidden={!active}
      aria-hidden={!active}
      {...(!active ? ({ inert: true } as HTMLAttributes<HTMLDivElement>) : {})}
      data-keepalive={pathKey}
    >
      <KeepAlivePathContext.Provider value={pathKey}>
        <ErrorBoundary>{children}</ErrorBoundary>
      </KeepAlivePathContext.Provider>
    </div>
  );
}

/** Heavy screens stay mounted after first visit; light screens unmount on leave. */
export default function KeepAlivePages() {
  const { pathname } = useLocation();
  const [visitedHeavy, setVisitedHeavy] = useState<Set<string>>(() => new Set());

  const heavyHit = HEAVY.find((e) => e.match(pathname));
  const lightHit = LIGHT.find((e) => e.match(pathname));

  useEffect(() => {
    if (!heavyHit) return;
    setVisitedHeavy((prev) => {
      if (prev.has(heavyHit.key)) return prev;
      const next = new Set(prev);
      next.add(heavyHit.key);
      return next;
    });
  }, [heavyHit]);

  if (!heavyHit && !lightHit) {
    return <Navigate to="/dashboard" replace />;
  }

  return (
    <>
      {HEAVY.filter((e) => visitedHeavy.has(e.key) || e.match(pathname)).map((e) => (
        <Slot key={e.key} pathKey={e.key} active={e.match(pathname)}>
          {e.render()}
        </Slot>
      ))}
      {lightHit ? <ErrorBoundary key={pathname}>{lightHit.render()}</ErrorBoundary> : null}
    </>
  );
}
