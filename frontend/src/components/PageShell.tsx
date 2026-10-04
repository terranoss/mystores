import { createContext, useContext, type ReactNode } from 'react';
import Layout from './Layout';

/** True when the app already wraps content in a single outer Layout (keep-alive shell). */
const AppShellLayoutContext = createContext(false);

export function AppShellLayoutProvider({ children }: { children: ReactNode }) {
  return (
    <AppShellLayoutContext.Provider value={true}>
      {children}
    </AppShellLayoutContext.Provider>
  );
}

export function useHasAppShellLayout(): boolean {
  return useContext(AppShellLayoutContext);
}

/**
 * Use in pages instead of Layout.
 * Skips a nested Layout when AppShell already provides one.
 */
export function PageShell({ children }: { children: ReactNode }) {
  const hasShell = useHasAppShellLayout();
  if (hasShell) {
    return <>{children}</>;
  }
  return <Layout>{children}</Layout>;
}
