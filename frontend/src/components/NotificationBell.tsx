import React, { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  getNotifications,
  getUnreadNotificationCount,
  markNotificationAsRead,
  markAllNotificationsAsRead,
  deleteNotification,
  type AppNotification,
} from '../api/client';

export default function NotificationBell() {
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);
  const [unreadCount, setUnreadCount] = useState(0);
  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const [loading, setLoading] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  // Poll for unread count every 30s
  useEffect(() => {
    const fetchCount = async () => {
      try {
        const count = await getUnreadNotificationCount();
        setUnreadCount(count);
      } catch (_) {}
    };
    fetchCount();
    const interval = setInterval(fetchCount, 30000);
    return () => clearInterval(interval);
  }, []);

  // Close dropdown when clicking outside
  useEffect(() => {
    function handleClickOutside(e: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const toggleOpen = async () => {
    const nextOpen = !open;
    setOpen(nextOpen);
    if (nextOpen) {
      setLoading(true);
      try {
        const data = await getNotifications(false, 1);
        setNotifications(data.notifications.data);
        setUnreadCount(data.unread_count);
      } catch (_) {
      } finally {
        setLoading(false);
      }
    }
  };

  const handleMarkAsRead = async (id: string, e: React.MouseEvent) => {
    e.stopPropagation();
    try {
      const count = await markNotificationAsRead(id);
      setUnreadCount(count);
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n))
      );
    } catch (_) {}
  };

  const handleMarkAllRead = async () => {
    try {
      await markAllNotificationsAsRead();
      setUnreadCount(0);
      setNotifications((prev) =>
        prev.map((n) => ({ ...n, read_at: new Date().toISOString() }))
      );
    } catch (_) {}
  };

  const handleDelete = async (id: string, e: React.MouseEvent) => {
    e.stopPropagation();
    try {
      const count = await deleteNotification(id);
      setUnreadCount(count);
      setNotifications((prev) => prev.filter((n) => n.id !== id));
    } catch (_) {}
  };

  const handleItemClick = (n: AppNotification) => {
    if (!n.read_at) {
      markNotificationAsRead(n.id);
      setUnreadCount((c) => Math.max(0, c - 1));
    }
    setOpen(false);

    if (n.data.category === 'order_urgent' || n.data.category === 'order_new' || n.data.category === 'order_status') {
      navigate(n.data.category === 'order_urgent' ? '/orders?urgency=due_today' : '/orders');
    } else if (n.data.category === 'picklist_comment') {
      navigate(n.data.kind === 'pending' ? '/pick-list/pending' : '/pick-list/today');
    } else if (n.data.category === 'team_activity') {
      navigate('/team');
    } else if (n.data.category === 'store_reauth') {
      navigate('/dashboard');
    }
  };

  const getCategoryIcon = (category: string) => {
    switch (category) {
      case 'store_reauth':
        return <span className="text-amber-500">⚠️</span>;
      case 'order_urgent':
      case 'order_status':
        return <span className="text-red-500">📦</span>;
      case 'order_new':
        return <span className="text-green-600">🛍️</span>;
      case 'picklist_comment':
        return <span className="text-teal-600">💬</span>;
      case 'team_activity':
        return <span className="text-blue-500">👥</span>;
      default:
        return <span>🔔</span>;
    }
  };

  return (
    <div className="relative" ref={dropdownRef}>
      <button
        type="button"
        onClick={toggleOpen}
        className="relative p-2 rounded-xl text-ink-300 hover:text-white hover:bg-ink-900 transition-colors focus:outline-none"
        title="Notifications"
      >
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
          />
        </svg>
        {unreadCount > 0 && (
          <span className="absolute top-1 right-1 flex h-4 min-w-[16px] px-1 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white leading-none">
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-2xl shadow-xl border border-ink-100 z-50 overflow-hidden">
          <div className="p-3.5 px-4 bg-ink-50 border-b border-ink-100 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <h3 className="font-heading font-semibold text-sm text-ink-950">Notifications</h3>
              {unreadCount > 0 && (
                <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-700">
                  {unreadCount} unread
                </span>
              )}
            </div>
            {unreadCount > 0 && (
              <button
                type="button"
                onClick={handleMarkAllRead}
                className="text-xs font-medium text-clay-600 hover:text-clay-700 transition-colors"
              >
                Mark all as read
              </button>
            )}
          </div>

          <div className="max-h-80 overflow-y-auto divide-y divide-ink-50">
            {loading ? (
              <div className="p-8 text-center text-xs text-ink-400">Loading notifications...</div>
            ) : notifications.length === 0 ? (
              <div className="p-8 text-center text-xs text-ink-400">No notifications yet.</div>
            ) : (
              notifications.map((n) => (
                <div
                  key={n.id}
                  onClick={() => handleItemClick(n)}
                  className={`p-3.5 px-4 flex items-start gap-3 cursor-pointer hover:bg-ink-50/70 transition-colors ${
                    !n.read_at ? 'bg-amber-50/30' : ''
                  }`}
                >
                  <div className="mt-0.5 shrink-0 text-base">{getCategoryIcon(n.data.category)}</div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between gap-2">
                      <p className={`text-xs ${!n.read_at ? 'font-bold text-ink-950' : 'font-medium text-ink-800'}`}>
                        {n.data.title}
                      </p>
                      {!n.read_at && <span className="w-2 h-2 rounded-full bg-clay-500 shrink-0" />}
                    </div>
                    <p className="text-[11px] text-ink-500 mt-0.5 line-clamp-2 leading-relaxed">
                      {n.data.message}
                    </p>
                    <div className="flex items-center justify-between mt-1.5 text-[10px] text-ink-400">
                      <span>{new Date(n.created_at).toLocaleDateString()}</span>
                      <div className="flex items-center gap-2">
                        {!n.read_at && (
                          <button
                            type="button"
                            onClick={(e) => handleMarkAsRead(n.id, e)}
                            className="hover:text-ink-700 font-medium"
                          >
                            Mark read
                          </button>
                        )}
                        <button
                          type="button"
                          onClick={(e) => handleDelete(n.id, e)}
                          className="hover:text-red-600 font-medium"
                        >
                          Dismiss
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
      )}
    </div>
  );
}
