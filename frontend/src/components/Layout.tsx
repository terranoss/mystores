import { useState, useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useStore } from '../context/StoreContext';
import { getDashboardStats } from '../api/client';
import StoreSwitcher from './StoreSwitcher';
import NotificationBell from './NotificationBell';
import { APP_VERSION } from '../version';

const getNavItems = () => {
  const baseNav = [
    { label: 'Dashboard', path: '/dashboard', icon: '◉' },
    { 
      label: 'Orders', 
      path: '/orders', 
      icon: '◈',
      children: [
        { label: 'Today Pick List', path: '/pick-list/today', icon: '☑' },
        { label: 'Pending Pick List', path: '/pick-list/pending', icon: '☐' },
      ]
    },
    { label: 'Stock', path: '/stock', icon: '◇' },
    { label: 'Promotions', path: '/promotions', icon: '✧' },
    { label: 'Team', path: '/team', icon: '◎' },
  ];

  // Settings hosts per-user actions (change password) plus owner-only platform credentials
  return [
    ...baseNav,
    { label: 'Settings', path: '/settings', icon: '⚙' },
    {
      label: 'Documentation',
      path: '/docs/auth',
      icon: '📖',
      children: [
        { label: 'Authentication', path: '/docs/auth', icon: '🔐' },
        { label: 'Menu Guide', path: '/docs/menu', icon: '☰' },
      ]
    },
  ];
};

export default function Layout({ children }: { children: React.ReactNode }) {
  const { user, logout } = useAuth();
  const { stores } = useStore();
  const location = useLocation();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [pendingCount, setPendingCount] = useState(0);
  const [openSubmenus, setOpenSubmenus] = useState<Set<string>>(new Set());
  const navItems = getNavItems();

  // Poll for pending order count every 60s
  useEffect(() => {
    const fetchPending = async () => {
      try {
        const data = await getDashboardStats();
        setPendingCount(data.stats.pending_orders_count);
      } catch {
        // silently ignore
      }
    };
    fetchPending();
    const interval = setInterval(fetchPending, 60000);
    return () => clearInterval(interval);
  }, []);

  return (
    <div className="flex min-h-screen bg-ink-50">
      {/* Mobile overlay */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 bg-ink-950/40 z-20 lg:hidden print:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={`fixed inset-y-0 left-0 z-30 w-64 bg-ink-950 transform transition-transform duration-300 ease-out lg:relative lg:translate-x-0 print:hidden ${
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="flex flex-col h-full">
          {/* Logo */}
          <div className="px-6 pt-8 pb-6 border-b border-ink-800">
            <h1 className="font-heading text-xl font-bold text-clay-400 tracking-tight">
              My<span className="text-white">Stores</span>
            </h1>
            <p className="text-xs text-ink-400 mt-1 font-medium tracking-wide uppercase">
              Multi-store dashboard
            </p>
          </div>

          {/* Nav */}
          <nav className="flex-1 px-3 py-6 space-y-1">
            {navItems.map((item) => {
              if (item.children) {
                // Parent menu with submenu
                const isParentActive = item.children.some(
                  (child) => location.pathname === child.path
                );
                const isOpen = openSubmenus.has(item.path) || isParentActive;
                
                const toggleSubmenu = (e: React.MouseEvent) => {
                  e.preventDefault();
                  setOpenSubmenus(prev => {
                    const next = new Set(prev);
                    if (next.has(item.path)) {
                      next.delete(item.path);
                    } else {
                      next.add(item.path);
                    }
                    return next;
                  });
                };
                
                return (
                  <div key={item.path} className="space-y-1">
                    <button
                      onClick={toggleSubmenu}
                      className={`flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 w-full text-left ${
                        isParentActive || isOpen
                          ? 'bg-clay-500/15 text-clay-400 shadow-inner'
                          : 'text-ink-300 hover:text-white hover:bg-ink-800/50'
                      }`}
                    >
                      <span className="text-lg">{item.icon}</span>
                      {item.label}
                      {item.label === 'Orders' && pendingCount > 0 && (
                        <span
                          title="Pending orders needing attention: Unpaid, Ready to ship, and Processed"
                          className="ml-auto bg-red-500 text-white text-xs font-bold rounded-full min-w-[20px] h-5 flex items-center justify-center px-1.5"
                        >
                          {pendingCount > 99 ? '99+' : pendingCount}
                        </span>
                      )}
                      <span className={`ml-auto transition-transform duration-200 ${isOpen ? 'rotate-90' : ''}`}>
                        ▸
                      </span>
                    </button>
                    {isOpen && (
                      <div className="pl-10 space-y-0.5 animate-slideDown">
                        {item.children.map((child) => {
                          const isActive = location.pathname === child.path;
                          return (
                            <Link
                              key={child.path}
                              to={child.path}
                              onClick={() => setSidebarOpen(false)}
                              className={`flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition-all duration-200 ${
                                isActive
                                  ? 'bg-clay-500/10 text-clay-400'
                                  : 'text-ink-400 hover:text-white hover:bg-ink-800/50'
                              }`}
                            >
                              <span className="text-base">{child.icon}</span>
                              {child.label}
                            </Link>
                          );
                        })}
                      </div>
                    )}
                  </div>
                );
              }
              
              // Regular menu item
              const isActive =
                location.pathname === item.path ||
                (item.path.startsWith('/pick-list/') && location.pathname === item.path);
              return (
                <Link
                  key={item.path}
                  to={item.path}
                  onClick={() => setSidebarOpen(false)}
                  className={`flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 ${
                    isActive
                      ? 'bg-clay-500/15 text-clay-400 shadow-inner'
                      : 'text-ink-300 hover:text-white hover:bg-ink-800/50'
                  }`}
                >
                  <span className="text-lg">{item.icon}</span>
                  {item.label}
                  {item.label === 'Orders' && pendingCount > 0 && (
                    <span
                      title="Pending orders needing attention: Unpaid, Ready to ship, and Processed"
                      className="ml-auto bg-red-500 text-white text-xs font-bold rounded-full min-w-[20px] h-5 flex items-center justify-center px-1.5"
                    >
                      {pendingCount > 99 ? '99+' : pendingCount}
                    </span>
                  )}
                </Link>
              );
            })}
            
            {/* Version below menu */}
            <div className="pt-2 px-4">
              <span className="text-[11px] text-ink-500 font-medium tracking-wide">
                v{APP_VERSION}
              </span>
            </div>
          </nav>

          {/* User info at bottom */}
          <div className="px-3 py-4 border-t border-ink-800">
            <div className="flex items-center gap-3 px-4 py-3 rounded-xl bg-ink-900/50">
              <div className="w-8 h-8 rounded-full bg-clay-600 flex items-center justify-center text-white text-xs font-bold">
                {user?.name?.charAt(0)?.toUpperCase() || '?'}
              </div>
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-white truncate">{user?.name}</p>
                <p className="text-xs text-ink-400 truncate">{user?.email}</p>
                {user?.workspace_role && (
                  <p className="text-[10px] uppercase tracking-wide text-clay-400 mt-0.5 font-semibold">
                    {user.workspace_role}
                  </p>
                )}
              </div>
            </div>
          </div>
        </div>
      </aside>

      {/* Main */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Top bar */}
        <header className="sticky top-0 z-10 bg-ink-50/80 backdrop-blur-md border-b border-ink-200/60 print:hidden">
          <div className="flex items-center justify-between px-4 sm:px-8 h-16">
            <button
              onClick={() => setSidebarOpen(true)}
              className="lg:hidden p-2 -ml-2 text-ink-700 hover:text-ink-900 transition"
              aria-label="Open sidebar"
            >
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>

            <div className="hidden lg:block" />

            <div className="flex items-center gap-3">
              {stores.length > 0 && <StoreSwitcher />}
              <NotificationBell />
              <button
                onClick={logout}
                className="text-sm font-medium text-ink-500 hover:text-ink-800 transition px-4 py-2 rounded-lg hover:bg-ink-100"
              >
                Logout
              </button>
            </div>
          </div>
        </header>

        {/* Page content */}
        <main className="flex-1 px-4 sm:px-8 py-8 print:p-0 print:m-0">
          {children}
        </main>
      </div>
    </div>
  );
}
