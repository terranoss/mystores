/* ==========================================================================
   MyStore Demo — static replica app
   Mirrors frontend/src/components/Layout.tsx (shell) and the page components.
   No network calls, no backend. All interaction is local sample-data state.
   ========================================================================== */

import {
  DEMO_USER, DEMO_VERSION, STORES, SALES_TREND, SALES_BY_STORE, CANCEL_REASONS,
  CANCEL_REASON_LABELS, ORDERS, STOCK_ITEMS, PICK_ITEMS, PICK_COMMENTS, TEAM,
  NOTIFICATIONS, PROMOTIONS, storeById, lowStockCount, pendingOrders,
} from './demo-data.js';

/* ---------------------------------------------------------------- helpers */
const rm = (n) =>
  'RM ' + Number(n).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const dayLabel = (iso) =>
  new Date(iso + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
const weekday = (iso) => new Date(iso + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'short' });
const dom = (iso) => new Date(iso + 'T00:00:00').getDate();

function el(tag, attrs = {}, children = []) {
  const node = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) {
    if (v === null || v === undefined || v === false) continue;
    if (k === 'class') node.className = v;
    else if (k === 'html') node.innerHTML = v;
    else if (k === 'text') node.textContent = v;
    else if (k.startsWith('on')) node.addEventListener(k.slice(2).toLowerCase(), v);
    else if (k === 'style') node.setAttribute('style', v);
    else node.setAttribute(k, v);
  }
  // Flatten one level so nested .map() results can be passed inline without
  // wrapping them in a spread — appendChild cannot take an array.
  const flat = [];
  const push = (c) => {
    if (Array.isArray(c)) c.forEach(push);
    else if (c !== null && c !== undefined && c !== false) flat.push(c);
  };
  [].concat(children).forEach(push);
  for (const c of flat) {
    node.appendChild(typeof c === 'string' || typeof c === 'number' ? document.createTextNode(String(c)) : c);
  }
  return node;
}

const STATUS_STYLES = {
  UNPAID: ['bg-gray-50', 'text-gray-700', 'border-gray-200', 'bg-gray-500', 'Unpaid'],
  READY_TO_SHIP: ['bg-blue-50', 'text-blue-700', 'border-blue-200', 'bg-blue-500', 'Ready to Ship'],
  PROCESSED: ['bg-amber-50', 'text-amber-700', 'border-amber-200', 'bg-amber-500', 'Processed'],
  SHIPPED: ['bg-indigo-50', 'text-indigo-700', 'border-indigo-200', 'bg-indigo-500', 'Shipped'],
  TO_CONFIRM_RECEIVE: ['bg-teal-50', 'text-teal-700', 'border-teal-200', 'bg-teal-500', 'To Confirm'],
  COMPLETED: ['bg-emerald-50', 'text-emerald-700', 'border-emerald-200', 'bg-emerald-500', 'Completed'],
  IN_CANCEL: ['bg-orange-50', 'text-orange-700', 'border-orange-200', 'bg-orange-500', 'Cancelling'],
  CANCELLED: ['bg-red-50', 'text-red-700', 'border-red-200', 'bg-red-500', 'Cancelled'],
  TO_RETURN: ['bg-rose-50', 'text-rose-700', 'border-rose-200', 'bg-rose-500', 'To Return'],
};

function statusBadge(status) {
  const s = STATUS_STYLES[status] || ['bg-gray-50', 'text-gray-600', 'border-gray-200', 'bg-gray-400', status];
  return el('span', { class: `badge ${s[0]} ${s[1]} ${s[2]}` }, [
    el('span', { class: `d ${s[3]}` }),
    s[4],
  ]);
}

const platformBadge = (p) =>
  el('span', { class: `platform ${p}`, text: p === 'shopee' ? 'Shopee' : 'Lazada' });

const HEALTH = {
  active: ['Active', 'bg-emerald-50', 'text-emerald-700', 'border-emerald-200', 'bg-emerald-500'],
  expiring_soon: ['Expiring', 'bg-amber-50', 'text-amber-700', 'border-amber-200', 'bg-amber-500'],
  reauthorize_required: ['Reauthorize Required', 'bg-red-50', 'text-red-700', 'border-red-200', 'bg-red-500'],
  refresh_failed: ['Refresh Failed', 'bg-red-50', 'text-red-700', 'border-red-200', 'bg-red-500'],
  disconnected: ['Disconnected', 'bg-gray-50', 'text-gray-500', 'border-gray-200', 'bg-gray-400'],
  missing_token: ['Missing Token', 'bg-red-50', 'text-red-700', 'border-red-200', 'bg-red-500'],
};

function healthBadge(status) {
  const h = HEALTH[status] || [status, 'bg-gray-50', 'text-gray-600', 'border-gray-200', 'bg-gray-400'];
  return el('span', { class: `badge ${h[1]} ${h[2]} ${h[3]}` }, [
    el('span', { class: `d ${h[4]}` }),
    h[0],
  ]);
}

function tokenChip(label, value, tone) {
  const t =
    tone === 'warn' ? 'bg-amber-50 text-amber-700 border-amber-200'
      : tone === 'expired' ? 'bg-red-50 text-red-700 border-red-200'
        : 'bg-ink-50 text-ink-600 border-ink-200';
  return el('span', { class: `chip chip-sm ${t}`, text: `${label} ${value}` });
}

function vacationToggle(store) {
  const btn = el('button', {
    class: `vac ${store.vacation_mode ? 'on' : ''}`,
    role: 'switch',
    'aria-checked': String(store.vacation_mode),
  }, [
    el('span', { text: store.vacation_mode ? '🌴 Vacation ON' : 'Vacation Off' }),
    el('span', { class: 'track' }, [el('span', { class: 'knob' })]),
  ]);
  btn.addEventListener('click', () => {
    store.vacation_mode = !store.vacation_mode;
    btn.className = `vac ${store.vacation_mode ? 'on' : ''}`;
    btn.setAttribute('aria-checked', String(store.vacation_mode));
    btn.firstChild.textContent = store.vacation_mode ? '🌴 Vacation ON' : 'Vacation Off';
    toast(store.vacation_mode ? 'Vacation mode enabled for this store.' : 'Vacation mode disabled.');
  });
  return btn;
}

/* ------------------------------------------------------------ app state */
const state = {
  path: 'dashboard',
  marketplace: 'all',
  storeId: '',
  trendDays: 14,
  openSub: {},
  sidebarOpen: false,
  notifOpen: false,
  orderFilter: 'all',
  orderQuery: '',
  stockQuery: '',
  picked: {},
  toast: null,
};

const NAV = [
  { label: 'Dashboard', key: 'dashboard', icon: '◉' },
  {
    label: 'Orders', key: 'orders', icon: '◈', children: [
      { label: 'Today Pick List', key: 'picklist-today', icon: '☑' },
      { label: 'Pending Pick List', key: 'picklist-pending', icon: '☐' },
    ],
  },
  { label: 'Stock', key: 'stock', icon: '◇' },
  { label: 'Promotions', key: 'promotions', icon: '✧' },
  { label: 'Team', key: 'team', icon: '◎' },
  { label: 'Settings', key: 'settings', icon: '⚙' },
  { label: 'Documentation', key: 'docs', icon: '📖', children: [
    { label: 'Authentication', key: 'docs-auth', icon: '🔐' },
    { label: 'Menu Guide', key: 'docs-menu', icon: '☰' },
  ] },
];

function visibleStores() {
  return STORES.filter((s) => state.marketplace === 'all' || s.platform === state.marketplace);
}

function filteredOrders() {
  return ORDERS.filter((o) => {
    if (state.marketplace !== 'all' && o.platform !== state.marketplace) return false;
    if (state.storeId && String(o.store_id) !== state.storeId) return false;
    if (state.orderFilter !== 'all' && o.status !== state.orderFilter) return false;
    if (state.orderQuery) {
      const q = state.orderQuery.toLowerCase();
      if (!o.order_sn.toLowerCase().includes(q) && !o.buyer_name.toLowerCase().includes(q)) return false;
    }
    return true;
  });
}

/* ------------------------------------------------------------------ toast */
function toast(msg) {
  state.toast = msg;
  render();
  clearTimeout(toast._t);
  toast._t = setTimeout(() => { state.toast = null; render(); }, 2600);
}

/* --------------------------------------------------------------- sidebar */
function buildSidebar() {
  const nav = el('nav', { class: 'sb-nav' });

  for (const item of NAV) {
    if (item.children) {
      const isOpen = !!state.openSub[item.key];
      const sub = el('div', { class: `subnav ${isOpen ? 'open' : ''}` },
        item.children.map((c) =>
          el('a', {
            class: `sub-item ${state.path === c.key ? 'active' : ''}`,
            href: '#',
            onclick: (e) => { e.preventDefault(); go(c.key); },
          }, [el('span', { text: c.icon }), c.label])
        )
      );
      nav.appendChild(el('div', {}, [
        el('button', {
          class: 'nav-parent',
          'aria-expanded': String(isOpen),
          onclick: () => { state.openSub[item.key] = !isOpen; render(); },
        }, [
          el('span', { class: 'glyph', text: item.icon }),
          item.label,
          el('span', { class: 'caret', text: '▸' }),
        ]),
        sub,
      ]));
    } else {
      const active = state.path === item.key;
      nav.appendChild(el('a', {
        class: `nav-item ${active ? 'active' : ''}`,
        href: '#',
        onclick: (e) => { e.preventDefault(); go(item.key); },
      }, [
        el('span', { class: 'glyph', text: item.icon }),
        item.label,
        item.key === 'orders'
          ? el('span', { class: 'badge', title: 'Pending orders needing attention', text: pendingOrders() > 99 ? '99+' : String(pendingOrders()) })
          : null,
      ]));
    }
  }

  return el('aside', { class: `sidebar ${state.sidebarOpen ? 'open' : ''}` }, [
    el('div', { class: 'sb-logo' }, [
      el('h1', { html: 'My<span>Stores</span>' }),
      el('p', { text: 'Multi-store dashboard' }),
    ]),
    nav,
    el('div', { class: 'sb-version', text: `v${DEMO_VERSION}` }),
    el('div', { class: 'sb-user' }, [
      el('div', { class: 'sb-user-inner' }, [
        el('div', { class: 'avatar', text: (DEMO_USER.name || '?').charAt(0).toUpperCase() }),
        el('div', {}, [
          el('div', { class: 'sb-user-name', text: DEMO_USER.name }),
          el('div', { class: 'sb-user-mail', text: DEMO_USER.email }),
          el('div', { class: 'sb-user-role', text: 'Owner' }),
        ]),
      ]),
    ]),
  ]);
}

/* --------------------------------------------------------------- topbar */
function buildTopbar() {
  const stores = visibleStores();
  const unread = NOTIFICATIONS.filter((n) => n.unread).length;

  const switcher = el('div', { class: 'store-switch' },
    ['all', 'shopee', 'lazada'].map((m) =>
      el('button', {
        class: state.marketplace === m ? 'active' : '',
        'data-m': m,
        text: m === 'all' ? 'All' : m === 'shopee' ? 'Shopee' : 'Lazada',
        onclick: () => {
          state.marketplace = m;
          state.storeId = '';
          render();
        },
      })
    )
  );

  const notifRows = NOTIFICATIONS.map((n) =>
    el('div', { class: `notif-row ${n.unread ? 'unread' : ''}` }, [
      el('span', { text: n.unread ? '●' : '○', style: n.unread ? 'color:#ca8554' : 'color:#d3cdb5' }),
      el('div', {}, [
        el('div', { style: 'font-weight:600;color:#2d211a', text: n.title }),
        el('div', { style: 'color:#958356;margin-top:2px', text: n.body }),
        el('div', { style: 'color:#a3956b;font-size:11px;margin-top:4px', text: n.at }),
      ]),
    ])
  );

  const bell = el('div', { class: 'bell-wrap' }, [
    el('button', {
      class: 'bell-btn',
      title: 'Notifications',
      onclick: (e) => { e.stopPropagation(); state.notifOpen = !state.notifOpen; render(); },
    }, [
      el('span', {
        html: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.158c0 .538-.214 1.055-.595 1.436L4 17h5m6 0a3 3 0 11-6 0m6 0H9"/></svg>',
      }),
      unread ? el('span', { class: 'bell-dot', text: unread > 9 ? '9+' : String(unread) }) : null,
    ]),
    state.notifOpen
      ? el('div', { class: 'notif-panel', onclick: (e) => e.stopPropagation() }, [
        el('div', { class: 'notif-head' }, [
          el('strong', { text: 'Notifications' }),
          unread ? el('span', { class: 'badge bg-red-50 text-red-700 border-red-200', text: `${unread} unread` }) : null,
        ]),
        el('div', { class: 'notif-list' }, notifRows),
      ])
      : null,
  ]);

  const select = stores.length > 1
    ? el('select', {
      class: 'store-select',
      onchange: (e) => { state.storeId = e.target.value; render(); },
    }, [
      el('option', { value: '', text: 'All Stores' }),
      ...stores.map((s) => el('option', { value: String(s.id), text: s.shop_name, selected: String(s.id) === state.storeId })),
    ])
    : null;

  return el('header', { class: 'topbar' }, [
    el('div', { class: 'topbar-inner' }, [
      el('div', {}, [
        el('button', {
          class: 'hamburger',
          'aria-label': 'Menu',
          onclick: () => { state.sidebarOpen = !state.sidebarOpen; render(); },
          html: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>',
        }),
      ]),
      el('div', { class: 'topbar-right' }, [switcher, select, bell,
        el('button', { class: 'link-btn', text: 'Logout', onclick: () => toast('Sign out is disabled in the demo.') }),
      ]),
    ]),
  ]);
}

/* -------------------------------------------------------------- Dashboard */
function kpiCard({ label, value, icon, action, growth, sm }) {
  const card = el('div', { class: 'kpi' }, [
    el('div', { class: 'kpi-top' }, [
      el('span', { class: 'kpi-icon', text: icon }),
      action || null,
    ]),
    el('div', { class: `kpi-value ${sm ? 'sm' : ''}`, text: value }),
    el('div', { class: 'kpi-label', text: label }),
    growth ? el('div', { style: 'margin-top:.5rem' }, [growth]) : null,
  ]);
  return card;
}

function growthBadge(label, dir, suffix) {
  const cls = dir === 'up' ? 'up' : dir === 'down' ? 'down' : 'flat';
  const sign = dir === 'up' ? '+' : dir === 'down' ? '−' : '';
  return el('span', { class: `growth ${cls}` }, [`${label} ${sign}${suffix}`]);
}

function storeBreakdown(rows) {
  if (!rows.length) return el('p', { class: 'breakdown-sub', text: 'None' });
  return el('ul', { style: 'list-style:none;margin:0;padding:0;display:grid;gap:.5rem' },
    rows.map((r) => el('li', { class: 'breakdown-row' }, [
      el('div', { class: 'breakdown-name' }, [
        el('span', { text: r.name }),
        platformBadge(r.platform),
      ]),
      el('div', { class: 'breakdown-right' }, [
        el('div', { class: 'breakdown-amt', text: r.amt }),
        el('div', { class: 'breakdown-sub', text: `${r.count} orders` }),
      ]),
    ]))
  );
}

function salesTrendCard() {
  const days = SALES_TREND[state.trendDays];
  const max = Math.max(...days.map((d) => d.gmv));
  const totalGmv = days.reduce((s, d) => s + d.gmv, 0);
  const totalOrders = days.reduce((s, d) => s + d.orders, 0);
  const compact = state.trendDays === 30;

  const bars = el('div', { class: 'bars', role: 'list' },
    days.map((d) => {
      const h = max ? Math.max((d.gmv / max) * 100, 8) : 2;
      return el('button', {
        class: 'bar-col',
        role: 'listitem',
        title: `${dayLabel(d.date)} — ${rm(d.gmv)} · ${d.orders} orders`,
        onclick: () => toast(`${dayLabel(d.date)}: ${rm(d.gmv)} from ${d.orders} orders`),
      }, [
        compact ? null : el('span', { class: 'bar-amt', text: Math.round(d.gmv).toLocaleString('en-MY') }),
        el('span', { class: 'bar', style: `height:${h}%` }),
        el('span', { class: 'bar-lbl', text: compact ? String(dom(d.date)) : weekday(d.date) }),
        compact ? null : el('span', { class: 'bar-ord', text: String(d.orders) }),
      ]);
    })
  );

  const byStore = STORES.filter((s) => state.marketplace === 'all' || s.platform === state.marketplace)
    .map((s) => ({
      name: s.shop_name,
      platform: s.platform,
      gmv: days.reduce((sum, d) => sum + ((SALES_BY_STORE[state.trendDays] || {})[d.date]?.[s.id]?.gmv || 0), 0),
      orders: days.reduce((sum, d) => sum + ((SALES_BY_STORE[state.trendDays] || {})[d.date]?.[s.id]?.orders || 0), 0),
    }))
    .filter((r) => r.gmv > 0)
    .sort((a, b) => b.gmv - a.gmv);

  const maxStore = Math.max(...byStore.map((r) => r.gmv), 1);

  return el('div', { class: 'card' }, [
    el('div', { class: 'trend-head' }, [
      el('div', {}, [
        el('div', { style: 'display:flex;align-items:center;gap:.75rem;flex-wrap:wrap' }, [
          el('h3', { class: 'card-h', text: `Sales (${state.trendDays} days)` }),
          el('div', { class: 'period-toggle' }, [7, 14, 30].map((n) =>
            el('button', {
              class: state.trendDays === n ? 'active' : '',
              text: `${n} days`,
              onclick: () => { state.trendDays = n; render(); },
            })
          )),
        ]),
        el('p', { class: 'page-sub', text: 'Non-cancelled · MYT · click a bar for store detail' }),
        el('div', { style: 'display:flex;gap:.5rem;margin-top:.5rem;flex-wrap:wrap' }, [
          growthBadge('vs yesterday', 'up', '18.4%'),
          growthBadge(`vs prior ${state.trendDays}d`, 'up', '6.2%'),
        ]),
      ]),
      el('div', { class: 'trend-stats' }, [
        el('div', { class: 'trend-stat' }, [
          el('div', { class: 'lbl', text: 'Sales' }),
          el('div', { class: 'val', text: rm(totalGmv) }),
        ]),
        el('div', { class: 'trend-stat' }, [
          el('div', { class: 'lbl', text: 'Orders' }),
          el('div', { class: 'val', text: String(totalOrders) }),
        ]),
      ]),
    ]),
    el('div', { style: 'margin-top:1.25rem' }, [bars]),
    el('div', { style: 'margin-top:1.25rem;border-top:1px solid var(--ink-100);padding-top:1rem' }, [
      el('div', { class: 'panel-label', text: 'By store (this period)' }),
      byStore.length
        ? el('div', { style: 'display:grid;gap:.75rem' }, byStore.map((r) => el('div', {}, [
          el('div', { class: 'breakdown-row', style: 'margin-bottom:.375rem' }, [
            el('div', { class: 'breakdown-name' }, [el('span', { text: r.name }), platformBadge(r.platform)]),
            el('div', { class: 'breakdown-right' }, [
              el('div', { class: 'breakdown-amt', text: rm(r.gmv) }),
              el('div', { class: 'breakdown-sub', text: `${r.orders} ord · ${Math.round((r.gmv / maxStore) * 100)}%` }),
            ]),
          ]),
          el('div', { class: 'hbar-track', style: 'height:6px' }, [
            el('div', { style: `width:${(r.gmv / maxStore) * 100}%;height:100%;background:var(--clay-400);border-radius:9999px` }),
          ]),
        ])))
        : el('p', { class: 'breakdown-sub', text: 'No sales in this window' }),
    ]),
  ]);
}

function cancelReasonsCard() {
  const max = Math.max(...CANCEL_REASONS.map((c) => c.count));
  const total = CANCEL_REASONS.reduce((s, c) => s + c.count, 0);
  return el('div', { class: 'card' }, [
    el('h3', { class: 'card-h', text: 'Cancel reasons' }),
    el('p', { class: 'page-sub', text: `Last ${state.trendDays} days · ${total} cancelled orders` }),
    el('div', { style: 'display:grid;gap:.875rem;margin-top:1rem' },
      CANCEL_REASONS.map((c) => el('div', {}, [
        el('div', { class: 'hbar-row', style: 'margin-bottom:.375rem' }, [
          el('span', { class: 'name', text: CANCEL_REASON_LABELS[c.reason] || c.reason }),
          el('span', { class: 'cnt', text: String(c.count) }),
          el('span', { class: 'pct', text: `${Math.round((c.count / total) * 100)}%` }),
        ]),
        el('div', { class: 'hbar-track' }, [
          el('div', { class: 'hbar-fill', style: `width:${Math.max((c.count / max) * 100, 4)}%` }),
        ]),
      ]))
    ),
  ]);
}

function linkedStoresCard() {
  const stores = visibleStores();
  return el('div', { class: 'card card-flush' }, [
    el('div', {
      style: 'padding:1.25rem 1.5rem;border-bottom:1px solid var(--ink-100);display:flex;align-items:center;justify-content:space-between;gap:1rem',
    }, [
      el('h3', { class: 'card-h', text: 'Linked Stores' }),
      el('div', { style: 'display:flex;gap:.5rem' }, [
        el('button', { class: 'btn btn-sm btn-shopee', text: '+ Shopee', onclick: () => toast('Linking a store is disabled in the demo.') }),
        el('button', { class: 'btn btn-sm', style: 'background:#f97316', text: '+ Lazada', onclick: () => toast('Linking a store is disabled in the demo.') }),
      ]),
    ]),
    ...stores.map((s) => el('div', { class: 'store-row' }, [
      el('div', { style: 'display:flex;align-items:center;gap:.875rem;min-width:0' }, [
        el('div', { class: `region-tile ${s.platform}`, text: s.region }),
        el('div', { style: 'min-width:0' }, [
          el('div', { style: 'display:flex;align-items:center;gap:.5rem' }, [
            el('span', { style: 'font-weight:600;color:var(--ink-900)', text: s.shop_name }),
            platformBadge(s.platform),
          ]),
          el('div', { class: 'store-meta', text: `ID: ${s.shop_id} · MY · ${s.product_count.toLocaleString('en-MY')} products · Linked ${s.linked_at}` }),
        ]),
      ]),
      el('div', { class: 'store-right' }, [
        tokenChip('Access', s.access_token_expires_in_hours ? `${s.access_token_expires_in_hours}h` : 'expired',
          s.access_token_expires_in_hours === 0 ? 'expired' : s.access_token_expires_in_hours < 24 ? 'warn' : 'ok'),
        tokenChip('Refresh', s.refresh_token_expires_in_days ? `${s.refresh_token_expires_in_days}d` : 'expired',
          s.refresh_token_expires_in_days === 0 ? 'expired' : s.refresh_token_expires_in_days < 7 ? 'warn' : 'ok'),
        healthBadge(s.connection_status),
        s.platform === 'shopee' ? vacationToggle(s) : null,
        s.connection_status === 'reauthorize_required'
          ? el('button', { class: 'btn btn-sm', style: 'color:#b45309;background:#fffbeb;border:1px solid #fde68a', text: 'Reauthorize', onclick: () => toast('Reauthorization is disabled in the demo.') })
          : null,
      ]),
    ])),
  ]);
}

function pageDashboard() {
  const stores = visibleStores();
  const pool = ORDERS.filter((o) =>
    (state.marketplace === 'all' || o.platform === state.marketplace) &&
    (!state.storeId || String(o.store_id) === state.storeId)
  );
  const todayOrders = pool.filter((o) => o.ordered_at === '2026-10-11');
  const todaySales = todayOrders.reduce((s, o) => s + o.total, 0);
  const pending = pool.filter((o) => ['UNPAID', 'READY_TO_SHIP', 'PROCESSED'].includes(o.status));

  const breakdownFor = (orders) =>
    stores.map((s) => ({
      name: s.shop_name,
      platform: s.platform,
      count: orders.filter((o) => o.store_id === s.id).length,
      amt: rm(orders.filter((o) => o.store_id === s.id).reduce((t, o) => t + o.total, 0)),
    })).filter((r) => r.count > 0);

  const kpis = el('div', { class: 'kpi-grid' }, [
    kpiCard({ label: 'Linked Stores', value: String(stores.length), icon: '◉' }),
    kpiCard({
      label: 'Orders Today', value: String(todayOrders.length), icon: '◈',
      action: el('button', { class: 'btn btn-sm btn-clay', text: '📋 Pick List', onclick: () => go('picklist-today') }),
    }),
    kpiCard({
      label: 'Sales Today', value: rm(todaySales), sm: true, icon: '◈',
      growth: growthBadge('vs yday', 'up', '18.4%'),
    }),
    kpiCard({
      label: 'Pending Orders', value: String(pending.length), icon: '◉',
      action: el('button', { class: 'btn btn-sm btn-clay', text: '📋 Pick List', onclick: () => go('picklist-pending') }),
    }),
    kpiCard({ label: 'Low Stock Items', value: String(lowStockCount()), icon: '◇' }),
  ]);

  return el('div', { class: 'page', style: 'display:grid;gap:2rem' }, [
    el('div', { class: 'page-head' }, [
      el('div', {}, [
        el('h2', { text: 'Dashboard' }),
        el('p', { class: 'page-sub', text: `Welcome back, ${DEMO_USER.name}` }),
      ]),
      el('button', {
        class: `btn ${state.marketplace === 'shopee' ? 'btn-shopee' : state.marketplace === 'lazada' ? 'btn-lazada' : ''}`,
        text: state.marketplace === 'all' ? 'Sync' : `Sync ${state.marketplace === 'shopee' ? 'Shopee' : 'Lazada'}`,
        onclick: () => toast('Sync is disabled in the demo — no API is connected.'),
      }),
    ]),
    kpis,
    salesTrendCard(),
    cancelReasonsCard(),
    lowStockCount() > 0
      ? el('div', { class: 'alert' }, [
        el('span', { class: 'ico', text: '◇' }),
        el('div', {}, [
          el('strong', { text: `${lowStockCount()} product(s) running low on stock` }),
          el('p', {}, [el('a', { href: '#', style: 'text-decoration:underline;font-weight:500', text: 'Go to Stock & Price', onclick: (e) => { e.preventDefault(); go('stock'); } }), ' to review and reorder.']),
        ]),
      ])
      : null,
    linkedStoresCard(),
  ]);
}

/* ----------------------------------------------------------------- Orders */
function pageOrders() {
  const rows = filteredOrders();
  const perPage = 10;
  const pages = Math.max(1, Math.ceil(rows.length / perPage));

  const filters = el('div', { class: 'table-bar' }, [
    el('div', { class: 'seg' }, ['all', 'UNPAID', 'READY_TO_SHIP', 'PROCESSED', 'SHIPPED', 'COMPLETED'].map((s) =>
      el('button', {
        class: state.orderFilter === s ? 'active' : '',
        text: s === 'all' ? 'All' : STATUS_STYLES[s][4],
        onclick: () => { state.orderFilter = s; render(); },
      })
    )),
    el('input', {
      type: 'search', placeholder: 'Search order SN or buyer…', value: state.orderQuery,
      oninput: (e) => { state.orderQuery = e.target.value; render(); },
    }),
    el('span', { style: 'margin-left:auto;font-size:.75rem;color:var(--ink-400)', text: `${rows.length} orders` }),
  ]);

  const body = rows.slice(0, perPage).map((o) => {
    const s = storeById(o.store_id);
    return el('tr', { class: 'rowlink', onclick: () => toast(`Order ${o.order_sn} detail is disabled in the demo.`) }, [
      el('td', {}, [el('input', { type: 'checkbox', class: 'checkbox', onclick: (e) => e.stopPropagation() })]),
      el('td', {}, [platformBadge(o.platform)]),
      el('td', {}, [
        el('div', { class: 'mono', style: 'font-size:.75rem;color:var(--ink-800)', text: o.order_sn }),
        el('div', { style: 'font-size:11px;color:var(--ink-400);margin-top:2px', text: `${o.items.length} item(s) · ${s ? s.shop_name : ''}` }),
      ]),
      el('td', { style: 'white-space:nowrap;color:var(--ink-600)', text: dayLabel(o.ordered_at) }),
      el('td', { class: 'num mono', text: rm(o.total) }),
      el('td', {}, [statusBadge(o.status)]),
      el('td', { style: 'white-space:nowrap;color:var(--ink-700)', text: dayLabel(o.ship_by) }),
      el('td', { class: 'num' }, [
        o.status === 'SHIPPED' || o.status === 'TO_CONFIRM_RECEIVE' || o.status === 'COMPLETED'
          ? el('span', { class: 'mono', style: 'font-size:.75rem', text: o.tracking })
          : el('button', { class: 'btn btn-sm btn-ghost', text: 'Arrange', onclick: (e) => { e.stopPropagation(); toast('Shipping arrangement is disabled in the demo.'); } }),
      ]),
    ]);
  });

  return el('div', { class: 'page', style: 'display:grid;gap:1.5rem' }, [
    el('div', { class: 'page-head' }, [
      el('div', {}, [
        el('h2', { text: 'Orders' }),
        el('p', { class: 'page-sub', text: 'Unified Shopee and Lazada order queue' }),
      ]),
    ]),
    el('div', { class: 'table-wrap' }, [
      filters,
      el('div', { class: 'table-scroll' }, [
        el('table', {}, [
          el('thead', {}, [el('tr', {}, [
            el('th', { style: 'width:2.5rem' }),
            el('th', { text: 'Platform' }),
            el('th', { text: 'Order' }),
            el('th', { text: 'Ordered' }),
            el('th', { class: 'num', text: 'Total' }),
            el('th', { text: 'Status' }),
            el('th', { text: 'Ship by' }),
            el('th', { class: 'num', text: 'Shipping' }),
          ])]),
          el('tbody', {}, body.length ? body : el('tr', {}, [el('td', { colspan: 8 }, [el('div', { class: 'empty', text: 'No orders match these filters.' })])])),
        ]),
      ]),
      el('div', { class: 'pager' }, [
        el('span', { text: `Page 1 of ${pages} · ${rows.length} orders` }),
        el('div', { class: 'pager-btns' }, [
          el('button', { class: 'pager-btn', text: 'Prev', disabled: true }),
          el('button', { class: 'pager-btn', text: 'Next', disabled: true }),
        ]),
      ]),
    ]),
  ]);
}

/* ------------------------------------------------------------------ Stock */
function pageStock() {
  const q = state.stockQuery.toLowerCase();
  const rows = STOCK_ITEMS.filter((i) =>
    !q || i.sku.toLowerCase().includes(q) || i.name.toLowerCase().includes(q) || i.model.toLowerCase().includes(q)
  );
  const out = rows.filter((r) => r.stock === 0).length;
  const low = rows.filter((r) => r.stock > 0 && r.stock <= 10).length;

  return el('div', { class: 'page', style: 'display:grid;gap:1.5rem' }, [
    el('div', { class: 'page-head' }, [
      el('div', {}, [
        el('h2', { text: 'Stock & Price' }),
        el('p', { class: 'page-sub', text: `${rows.length} SKUs · ${out} out of stock · ${low} low` }),
      ]),
      el('div', { style: 'display:flex;gap:.5rem' }, [
        el('button', { class: 'btn btn-sm btn-ghost', text: 'Pull from stores', onclick: () => toast('Stock pull is disabled in the demo.') }),
        el('button', { class: 'btn btn-sm btn-clay', text: 'Push to stores', onclick: () => toast('Stock push is disabled in the demo.') }),
      ]),
    ]),
    el('div', { class: 'table-wrap' }, [
      el('div', { class: 'table-bar' }, [
        el('input', {
          type: 'search', placeholder: 'Search SKU, name or model…', value: state.stockQuery,
          oninput: (e) => { state.stockQuery = e.target.value; render(); },
        }),
        el('div', { style: 'display:flex;gap:.5rem;margin-left:auto;flex-wrap:wrap' }, [
          el('span', { class: 'chip chip-sm', text: `All ${rows.length}` }),
          el('span', { class: 'chip chip-sm', style: 'border-color:#fecaca;background:#fef2f2;color:#b91c1c', text: `Out ${out}` }),
          el('span', { class: 'chip chip-sm', style: 'border-color:#fde68a;background:#fffbeb;color:#b45309', text: `Low ${low}` }),
        ]),
      ]),
      el('div', { class: 'table-scroll' }, [
        el('table', {}, [
          el('thead', {}, [el('tr', {}, [
            el('th', { text: 'SKU' }),
            el('th', { text: 'Product' }),
            el('th', { text: 'Category' }),
            el('th', { class: 'num', text: 'Stock' }),
            el('th', { class: 'num', text: 'Price' }),
            el('th', { class: 'num', text: 'Value' }),
          ])]),
          el('tbody', {}, rows.map((i) => el('tr', {}, [
            el('td', { class: 'mono', style: 'font-size:.75rem', text: i.sku }),
            el('td', {}, [
              el('div', { style: 'font-weight:500', text: i.name }),
              el('div', { style: 'font-size:11px;color:var(--ink-400)', text: `${i.model} · ${i.brand}` }),
            ]),
            el('td', { style: 'color:var(--ink-600)', text: i.category }),
            el('td', { class: 'num' }, [
              i.stock === 0
                ? el('span', { class: 'badge bg-red-50 text-red-700 border-red-200', text: 'Out' })
                : i.stock <= 10
                  ? el('span', { class: 'badge bg-amber-50 text-amber-700 border-amber-200', text: String(i.stock) })
                  : el('span', { style: 'font-variant-numeric:tabular-nums', text: String(i.stock) }),
            ]),
            el('td', { class: 'num mono', text: rm(i.price) }),
            el('td', { class: 'num mono', style: 'color:var(--ink-500)', text: rm(i.stock * i.price) }),
          ]))),
        ]),
      ]),
    ]),
  ]);
}

/* -------------------------------------------------------------- Pick List */
function pagePickList(kind) {
  const total = PICK_ITEMS.reduce((s, i) => s + i.qty, 0);
  const pickedCount = Object.values(state.picked).filter(Boolean).length;
  const progress = Math.round((pickedCount / PICK_ITEMS.length) * 100);

  return el('div', { class: 'page', style: 'display:grid;gap:1.5rem' }, [
    el('div', { class: 'page-head' }, [
      el('div', {}, [
        el('h2', { text: kind === 'today' ? 'Today Pick List' : 'Pending Pick List' }),
        el('p', { class: 'page-sub', text: 'Lumped totals for shelf picking · ' + (kind === 'today' ? 'Orders ready to ship today' : 'Orders awaiting action') }),
      ]),
      el('button', {
        class: 'btn btn-ghost',
        text: '🖨 Print',
        onclick: () => toast('Printing is disabled in the demo.'),
      }),
    ]),
    el('div', { class: 'card' }, [
      el('div', { style: 'display:flex;justify-content:space-between;margin-bottom:.5rem' }, [
        el('span', { style: 'font-size:.875rem;font-weight:600', text: `${pickedCount} of ${PICK_ITEMS.length} lines picked` }),
        el('span', { style: 'font-size:.875rem;color:var(--ink-500)', text: `${total} units total` }),
      ]),
      el('div', { class: 'progress' }, [el('div', { style: `width:${progress}%` })]),
    ]),
    el('div', { class: 'table-wrap' }, [
      el('div', { class: 'table-scroll' }, [
        el('table', {}, [
          el('thead', {}, [el('tr', {}, [
            el('th', { style: 'width:2.5rem' }),
            el('th', { text: 'Location' }),
            el('th', { text: 'SKU' }),
            el('th', { text: 'Product' }),
            el('th', { class: 'num', text: 'Pick Qty' }),
            el('th', { text: 'Picked' }),
          ])]),
          el('tbody', {}, PICK_ITEMS.map((i) => el('tr', {}, [
            el('td', {}, [el('input', {
              type: 'checkbox', class: 'checkbox', checked: !!state.picked[i.sku],
              onchange: (e) => { state.picked[i.sku] = e.target.checked; render(); },
            })]),
            el('td', { class: 'mono', style: 'font-size:.75rem;color:var(--ink-600)', text: i.location }),
            el('td', { class: 'mono', style: 'font-size:.75rem', text: i.sku }),
            el('td', { text: i.name }),
            el('td', { class: 'num', style: 'font-weight:600', text: String(i.qty) }),
            el('td', {}, [state.picked[i.sku] ? el('span', { class: 'badge bg-emerald-50 text-emerald-700 border-emerald-200', text: '✓ Done' }) : el('span', { style: 'color:var(--ink-300);font-size:.75rem', text: 'Pending' })]),
          ]))),
        ]),
      ]),
    ]),
    el('div', { class: 'card' }, [
      el('h3', { class: 'card-h', text: 'Comments' }),
      ...PICK_COMMENTS.map((c) => el('div', { style: 'padding:.75rem 0;border-bottom:1px solid var(--ink-50)' }, [
        el('div', { style: 'font-size:.75rem;font-weight:600;color:var(--ink-800)', text: `${c.author} · ${c.at}` }),
        el('div', { style: 'font-size:.875rem;color:var(--ink-600);margin-top:.25rem', text: c.text }),
      ])),
      el('div', { style: 'display:flex;gap:.5rem;margin-top:1rem' }, [
        el('input', { type: 'text', placeholder: 'Add a note for the warehouse…', style: 'flex:1' }),
        el('button', { class: 'btn', text: 'Post', onclick: () => toast('Comments are disabled in the demo.') }),
      ]),
    ]),
  ]);
}

/* ------------------------------------------------------------- Promotions */
function pagePromotions() {
  const tone = { Running: 'bg-emerald-50 text-emerald-700 border-emerald-200', Expired: 'bg-gray-50 text-gray-600 border-gray-200', Scheduled: 'bg-blue-50 text-blue-700 border-blue-200' };
  return el('div', { class: 'page', style: 'display:grid;gap:1.5rem' }, [
    el('div', { class: 'page-head' }, [
      el('div', {}, [
        el('h2', { text: 'Promotions' }),
        el('p', { class: 'page-sub', text: 'Shopee discounts and vouchers, Lazada vouchers' }),
      ]),
      el('div', { style: 'display:flex;gap:.5rem;flex-wrap:wrap' }, [
        el('button', { class: 'btn btn-sm btn-shopee', text: 'New Shopee discount', onclick: () => toast('Creating promotions is disabled in the demo.') }),
        el('button', { class: 'btn btn-sm', style: 'background:#f97316', text: 'New Lazada voucher', onclick: () => toast('Creating promotions is disabled in the demo.') }),
      ]),
    ]),
    el('div', { class: 'table-wrap' }, [
      el('div', { class: 'table-scroll' }, [
        el('table', {}, [
          el('thead', {}, [el('tr', {}, [
            el('th', { text: 'Type' }), el('th', { text: 'Name' }),
            el('th', { text: 'Status' }), el('th', { class: 'num', text: 'Usage' }),
            el('th', { text: 'Ends' }),
          ])]),
          el('tbody', {}, PROMOTIONS.map((p) => el('tr', {}, [
            el('td', { style: 'color:var(--ink-600);white-space:nowrap', text: p.kind }),
            el('td', { style: 'font-weight:500', text: p.name }),
            el('td', {}, [el('span', { class: `badge ${tone[p.status]}`, text: p.status })]),
            el('td', { class: 'num tnum', text: `${p.used} / ${p.quota}` }),
            el('td', { style: 'color:var(--ink-600);white-space:nowrap', text: p.ends }),
          ]))),
        ]),
      ]),
    ]),
  ]);
}

/* ------------------------------------------------------------------- Team */
function pageTeam() {
  const roleTone = { owner: 'bg-clay-100 text-clay-700 border-clay-200', manager: 'bg-blue-50 text-blue-700 border-blue-200', staff: 'bg-emerald-50 text-emerald-700 border-emerald-200', cashier: 'bg-gray-50 text-gray-600 border-gray-200' };

  // Intentionally a non-scannable placeholder — the real Store QR is never exposed.
  const qr = el('div', {
    style: 'width:8rem;height:8rem;border-radius:.75rem;background:repeating-linear-gradient(45deg,#2d211a,#2d211a 6px,#e8e5d9 6px,#e8e5d9 12px);display:flex;align-items:center;justify-content:center',
  }, [el('span', { style: 'background:#fff;padding:.35rem .5rem;border-radius:.375rem;font-size:10px;font-weight:600;color:#958356', text: 'DEMO QR' })]);

  return el('div', { class: 'page-narrow', style: 'display:grid;gap:1.5rem' }, [
    el('h2', { text: 'Team' }),
    el('div', { class: 'card' }, [
      el('h3', { class: 'card-h', text: 'Workspace' }),
      el('p', { class: 'page-sub', text: 'Riverside Retail Group · Owner-managed workspace · 5 members' }),
    ]),
    el('div', { class: 'card', style: 'background:linear-gradient(135deg,#0c4a6e,#1e293b);border-color:#0ea5e9' }, [
      el('div', { style: 'display:flex;gap:1.5rem;align-items:center;flex-wrap:wrap' }, [
        el('div', { style: 'flex:1;min-width:14rem' }, [
          el('span', { class: 'badge', style: 'background:rgba(14,165,233,.2);color:#7dd3fc;border-color:rgba(14,165,233,.3)', text: 'Mobile Companion Included' }),
          el('h3', { class: 'card-h', style: 'color:#fff;margin-top:.75rem;font-size:1.25rem', text: 'Official MyStores Mobile App' }),
          el('p', { style: 'font-size:.875rem;color:#cbd5e1;margin:.5rem 0 0;line-height:1.6', text: 'Warehouse staff and cashiers download the companion app free from Google Play, then scan the Store QR code to connect to this server instantly.' }),
        ]),
        qr,
      ]),
      el('p', { style: 'font-size:.75rem;color:#94a3b8;margin:1rem 0 0', text: 'Demo placeholder — this QR is not scannable and is not connected to any store.' }),
    ]),
    el('div', { class: 'card card-flush' }, [
      el('div', { style: 'padding:1.25rem 1.5rem;border-bottom:1px solid var(--ink-100)' }, [
        el('h3', { class: 'card-h', text: 'Members' }),
      ]),
      ...TEAM.map((m) => el('div', { class: 'store-row' }, [
        el('div', { style: 'display:flex;align-items:center;gap:.875rem;min-width:0' }, [
          el('div', { class: 'avatar', text: m.name.charAt(0) }),
          el('div', {}, [
            el('div', { style: 'font-weight:600;color:var(--ink-900)', text: m.name }),
            el('div', { class: 'store-meta', text: m.email }),
          ]),
        ]),
        el('div', { class: 'store-right' }, [
          el('span', { class: `badge ${roleTone[m.role]}`, text: m.role }),
          el('span', { style: 'font-size:.75rem;color:var(--ink-400)', text: m.activity }),
          m.role !== 'owner' ? el('button', { class: 'btn btn-sm btn-ghost', text: 'Remove', onclick: () => toast('Team changes are disabled in the demo.') }) : null,
        ]),
      ])),
    ]),
  ]);
}

/* --------------------------------------------------------------- Settings */
function pageSettings() {
  return el('div', { class: 'page-narrow', style: 'display:grid;gap:1.5rem' }, [
    el('h1', { text: 'Settings' }),
    el('div', { class: 'card' }, [
      el('h3', { class: 'card-h', text: 'Change Password' }),
      el('div', { style: 'display:grid;gap:.875rem;margin-top:1rem;max-width:22rem' }, [
        el('input', { type: 'password', placeholder: 'Current password' }),
        el('input', { type: 'password', placeholder: 'New password' }),
        el('input', { type: 'password', placeholder: 'Confirm new password' }),
        el('button', { class: 'btn', style: 'justify-self:start', text: 'Update password', onclick: () => toast('Settings are disabled in the demo.') }),
      ]),
    ]),
    el('div', { class: 'card' }, [
      el('h3', { class: 'card-h', text: 'Workspace Credentials' }),
      el('p', { class: 'page-sub', text: 'Owner-only. Used to authorise Shopee and Lazada Open Platform API calls.' }),
      el('div', { style: 'display:grid;gap:1rem;margin-top:1.25rem' }, [
        el('div', {}, [el('label', { style: 'font-size:.75rem;font-weight:600;color:var(--ink-600);display:block;margin-bottom:.375rem', text: 'Shopee Partner ID' }), el('input', { type: 'text', value: '••••••••1234', style: 'width:100%;max-width:24rem' })]),
        el('div', {}, [el('label', { style: 'font-size:.75rem;font-weight:600;color:var(--ink-600);display:block;margin-bottom:.375rem', text: 'Shopee Partner Key' }), el('input', { type: 'password', value: 'demo-placeholder-key', style: 'width:100%;max-width:24rem' })]),
        el('div', {}, [el('label', { style: 'font-size:.75rem;font-weight:600;color:var(--ink-600);display:block;margin-bottom:.375rem', text: 'Lazada App Key' }), el('input', { type: 'text', value: 'demo-placeholder-app-key', style: 'width:100%;max-width:24rem' })]),
        el('div', {}, [el('label', { style: 'font-size:.75rem;font-weight:600;color:var(--ink-600);display:block;margin-bottom:.375rem', text: 'Lazada App Secret' }), el('input', { type: 'password', value: 'demo-placeholder-secret', style: 'width:100%;max-width:24rem' })]),
        el('button', { class: 'btn', style: 'justify-self:start', text: 'Save credentials', onclick: () => toast('Settings are disabled in the demo.') }),
      ]),
      el('p', { style: 'font-size:.75rem;color:var(--ink-400);margin-top:1rem', text: 'All values shown here are dummy placeholders.' }),
    ]),
  ]);
}

/* ------------------------------------------------------------------- Docs */
function pageDocsAuth() {
  const steps = [
    ['Request API access', 'Apply for developer or partner app access on the Shopee Open Platform and Lazada Open Platform consoles. Approval provides the Partner ID, Partner Key, App Key and App Secret.'],
    ['Store the credentials', 'As workspace owner, enter those keys under Settings > Workspace Credentials. They are encrypted at rest on your own server.'],
    ['Authorise the store', 'Follow the OAuth link for the platform. MyStore exchanges the code for access and refresh tokens and stores them against the store record.'],
  ];
  return el('div', { class: 'wide', style: 'display:grid;gap:2rem;grid-template-columns:1fr' }, [
    el('h1', { text: 'Marketplace OAuth 2.0 Integration' }),
    el('div', { class: 'prose' }, [
      el('p', { text: 'MyStore connects to marketplaces through official Open Platform APIs using your own authorised developer credentials — not third-party scrapers.' }),
      ...steps.map(([t, d], i) => el('div', { class: 'card', style: 'margin-bottom:1rem' }, [
        el('div', { style: 'display:flex;gap:1rem;align-items:flex-start' }, [
          el('span', { style: 'font-family:var(--font-heading);font-size:1.5rem;font-weight:700;color:var(--clay-500)', text: String(i + 1).padStart(2, '0') }),
          el('div', {}, [el('h3', { style: 'margin:0', text: t }), el('p', { text: d })]),
        ]),
      ])),
      el('h3', { text: 'Callback URLs' }),
      el('p', {}, ['Register ', el('code', { text: '/api/shopee/callback' }), ' and ', el('code', { text: '/api/lazada/callback' }), ' on your domain. Tokens are refreshed proactively by the background scheduler so stores stay connected without manual re-linking.']),
    ]),
  ]);
}

function pageDocsMenu() {
  const items = [
    ['Dashboard', 'KPI tiles for linked stores, orders and sales today, pending orders and low stock. A sales trend bar chart and cancel-reason breakdown sit below, followed by the linked store health list.'],
    ['Orders', 'One unified queue across Shopee and Lazada. Filter by platform, status and date; sort by newest, ship-by or total. Bulk-select rows to print AWB labels and packing slips as PDF.'],
    ['Today / Pending Pick List', 'Lumped warehouse totals grouped by SKU rather than by order, so staff pick each item once regardless of how many orders contain it. Add per-line comments for the warehouse.'],
    ['Stock & Price', 'Searchable SKU table with inline editing of stock levels and prices, stock-health filters, and pull/push buttons to sync quantities to connected marketplaces.'],
    ['Promotions', 'Create and monitor Shopee discounts and vouchers plus Lazada vouchers, with usage quota and expiry tracking.'],
    ['Team', 'Manage staff accounts and roles, and share the Store Connection QR code that links the Google Play companion app to your server.'],
    ['Settings', 'Change password, and — for the workspace owner only — the Shopee and Lazada API credentials.'],
  ];
  return el('div', { class: 'wide', style: 'display:grid;gap:2rem' }, [
    el('h1', { text: 'Using Every Menu Item' }),
    el('div', { style: 'display:grid;gap:1rem;grid-template-columns:1fr' }, items.map(([t, d]) =>
      el('div', { class: 'card' }, [
        el('h3', { style: 'margin:0 0 .5rem', text: t }),
        el('p', { style: 'margin:0', text: d }),
      ])
    )),
  ]);
}

/* ---------------------------------------------------------------- routing */
const PAGES = {
  dashboard: pageDashboard,
  orders: pageOrders,
  stock: pageStock,
  'picklist-today': () => pagePickList('today'),
  'picklist-pending': () => pagePickList('pending'),
  promotions: pagePromotions,
  team: pageTeam,
  settings: pageSettings,
  'docs-auth': pageDocsAuth,
  'docs-menu': pageDocsMenu,
};

function go(key) {
  state.path = key;
  state.sidebarOpen = false;
  render();
}

/* ----------------------------------------------------------------- render */
function render() {
  const root = document.getElementById('root');
  root.innerHTML = '';

  const app = el('div', { class: 'app' }, [
    buildSidebar(),
    el('div', { class: 'main-col' }, [
      buildTopbar(),
      el('main', {}, [(PAGES[state.path] || pageDashboard)()]),
    ]),
  ]);

  const overlay = el('div', {
    class: 'sidebar-overlay',
    style: state.sidebarOpen ? 'display:block' : '',
    onclick: () => { state.sidebarOpen = false; render(); },
  });

  const demoBar = el('div', { class: 'demo-bar' }, [
    el('span', { class: 'dot' }),
    'Interactive sample data — this demo is not connected to a live store',
    el('a', {
      href: 'https://stacklane.melur.com/apps/mystores',
      target: '_blank',
      rel: 'noopener',
      style: 'color:#e0be9b;text-decoration:underline',
      text: 'Buy MyStore',
    }),
  ]);

  root.appendChild(el('div', { class: 'demo-app' }, [demoBar, app, overlay]));
  if (state.toast) root.appendChild(el('div', { class: 'toast', text: state.toast }));
}

document.addEventListener('click', () => {
  if (state.notifOpen) { state.notifOpen = false; render(); }
});

/* Test hooks — used only by demo/_smoke.mjs to exercise every page and
   interaction headlessly. Inert in production; the demo has no side effects. */
window.__demoGo = go;
window.__demoSetMarketplace = (m) => { state.marketplace = m; state.storeId = ''; render(); };
window.__demoSetTrend = (n) => { state.trendDays = n; render(); };
window.__demoSetOrderFilter = (s) => { state.orderFilter = s; render(); };
window.__demoSetStockQuery = (q) => { state.stockQuery = q; render(); };
window.__demoReset = () => {
  state.marketplace = 'all'; state.storeId = ''; state.trendDays = 14;
  state.orderFilter = 'all'; state.orderQuery = ''; state.stockQuery = '';
  render();
};

render();