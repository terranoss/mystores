import { useState } from 'react';
import { PageShell } from '../components/PageShell';

const OWNER_ONLY = (
  <span className="ml-1 align-middle text-[10px] font-bold uppercase tracking-wide text-amber-700 bg-amber-50 border border-amber-200 rounded px-1 py-0.5">
    Owner only
  </span>
);

function Section({
  id,
  title,
  children,
}: {
  id: string;
  title: string;
  children: React.ReactNode;
}) {
  return (
    <section id={id} className="space-y-4">
      <h2 className="text-xl font-bold text-ink-950 border-b border-ink-100 pb-2">
        {title}
      </h2>
      {children}
    </section>
  );
}

function Sub({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="pt-2 space-y-3">
      <h3 className="text-sm font-bold text-ink-900">{title}</h3>
      {children}
    </div>
  );
}

function P({ children }: { children: React.ReactNode }) {
  return <p className="text-sm leading-relaxed text-ink-700">{children}</p>;
}

function Callout({ children }: { children: React.ReactNode }) {
  return (
    <div className="p-3.5 bg-ink-50 border border-ink-200 rounded-lg">
      <p className="text-xs text-ink-600 leading-relaxed">{children}</p>
    </div>
  );
}

function Steps({ items }: { items: string[] }) {
  return (
    <ol className="space-y-1.5 text-sm text-ink-700 list-decimal pl-5 leading-relaxed">
      {items.map((item) => (
        <li key={item}>{item}</li>
      ))}
    </ol>
  );
}

const HEAD = 'bg-ink-50 text-ink-700 font-semibold border-b border-ink-200';
const CELL = 'p-3';

export default function MenuGuide() {
  const [activeSection, setActiveSection] = useState<string>('getting-started');

  const scrollTo = (id: string) => {
    setActiveSection(id);
    const element = document.getElementById(id);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth' });
    }
  };

  const sections = [
    { id: 'getting-started', label: '1. Getting Started' },
    { id: 'sidebar', label: '2. Sidebar Menu' },
    { id: 'topbar', label: '3. Top Bar' },
    { id: 'dashboard', label: '4. Dashboard' },
    { id: 'orders', label: '5. Orders' },
    { id: 'stock', label: '6. Stock' },
    { id: 'promotions', label: '7. Promotions' },
    { id: 'team', label: '8. Team' },
    { id: 'settings', label: '9. Settings' },
    { id: 'pick-list', label: '10. Pick List' },
    { id: 'limits', label: '11. Limits & Common Questions' },
  ];

  return (
    <PageShell>
      <div className="max-w-7xl mx-auto">
        <div className="flex flex-col lg:flex-row gap-8 items-start">
          {/* Sticky Table of Contents (Desktop) */}
          <aside className="w-full lg:w-64 shrink-0 lg:sticky lg:top-20 bg-white border border-ink-200/80 rounded-xl p-4 text-sm shadow-xs">
            <h2 className="text-xs font-bold text-ink-400 uppercase tracking-wider mb-3">
              On this page
            </h2>
            <nav className="space-y-1">
              {sections.map((item) => (
                <button
                  key={item.id}
                  onClick={() => scrollTo(item.id)}
                  className={`w-full text-left px-2.5 py-1.5 rounded-lg text-xs font-medium transition ${
                    activeSection === item.id
                      ? 'bg-ink-100 text-ink-950 font-semibold'
                      : 'text-ink-600 hover:text-ink-900 hover:bg-ink-50'
                  }`}
                >
                  {item.label}
                </button>
              ))}
            </nav>
          </aside>

          {/* Main Documentation Body */}
          <article className="flex-1 min-w-0 bg-white border border-ink-200/80 rounded-xl p-6 sm:p-10 shadow-xs space-y-12 text-ink-800">
            <header className="border-b border-ink-100 pb-6">
              <div className="flex items-center gap-2 text-xs font-semibold text-ink-500 uppercase tracking-wider mb-1">
                <span>Documentation</span>
                <span>/</span>
                <span>Menu Guide</span>
              </div>
              <h1 className="text-3xl font-bold font-heading text-ink-950 tracking-tight">
                Using Every Menu Item
              </h1>
              <p className="mt-2 text-sm text-ink-600 leading-relaxed max-w-3xl">
                A plain-language walkthrough of every button, menu, and filter in the MyStores web app — what each one does, and when you should reach for it.
              </p>
            </header>

            <Section id="getting-started" title="1. Getting Started">
              <P>
                The MyStores web app has two main navigation areas. The{' '}
                <strong>sidebar</strong> down the left side holds your main working areas. The{' '}
                <strong>top bar</strong> across the top holds the store filter, your alerts, and
                the sign-out button.
              </P>
              <P>
                Your role in the workspace is shown at the very bottom of the sidebar, underneath
                your name and email. Every signed-in user sees the same menu, but a few actions are
                restricted to the workspace owner. Those are marked{' '}
                <strong>Owner only</strong> in this guide.
              </P>
              <Sub title="On smaller screens">
                <P>
                  When the browser window is narrow, the sidebar slides off-screen. Tap the{' '}
                  <strong>☰</strong> button at the top left to open it, and tap the darkened area
                  to close it. Everything else works the same way.
                </P>
              </Sub>
              <Sub title="Two documents">
                <P>
                  This guide covers the everyday menus. If you are setting up a shop connection or
                  troubleshooting a store that has dropped, see the{' '}
                  <strong>Authentication</strong> page under Documentation instead — that one covers
                  how linking a Shopee or Lazada store works behind the scenes.
                </P>
              </Sub>
            </Section>

            <Section id="sidebar" title="2. Sidebar Menu">
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                  <thead className={HEAD}>
                    <tr>
                      <th className={CELL}>Menu item</th>
                      <th className={CELL}>What it does</th>
                      <th className={CELL}>When to use it</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-ink-100 text-ink-600">
                    <tr>
                      <td className={`${CELL} font-medium text-ink-900`}>◉ Dashboard</td>
                      <td className={CELL}>
                        Your overview: today&rsquo;s sales, order counts, stock warnings, and the
                        list of stores you have linked.
                      </td>
                      <td className={CELL}>Start of every session. First place to look.</td>
                    </tr>
                    <tr>
                      <td className={`${CELL} font-medium text-ink-900`}>◈ Orders</td>
                      <td className={CELL}>
                        A menu heading only. It expands to reveal two pick-list pages — it does not
                        open an order list.
                      </td>
                      <td className={CELL}>See the note below before you rely on it.</td>
                    </tr>
                    <tr>
                      <td className={`${CELL} font-medium text-ink-900`}>
                        <div className="pl-4">☑ Today Pick List</div>
                      </td>
                      <td className={CELL}>
                        Everything you need to pick for orders shipping today, totalled by item so
                        you walk the shelves once.
                      </td>
                      <td className={CELL}>Morning of pick and pack.</td>
                    </tr>
                    <tr>
                      <td className={`${CELL} font-medium text-ink-900`}>
                        <div className="pl-4">☐ Pending Pick List</div>
                      </td>
                      <td className={CELL}>
                        The same view, but for orders still waiting on payment or not yet ready to
                        ship.
                      </td>
                      <td className={CELL}>Working through the backlog.</td>
                    </tr>
                    <tr>
                      <td className={`${CELL} font-medium text-ink-900`}>◇ Stock</td>
                      <td className={CELL}>
                        Product quantities and prices across every linked store, with tools to pull
                        them down and push edits back up.
                      </td>
                      <td className={CELL}>Correcting stock levels or prices.</td>
                    </tr>
                    <tr>
                      <td className={`${CELL} font-medium text-ink-900`}>✧ Promotions</td>
                      <td className={CELL}>
                        Discounts and vouchers for Shopee and Lazada, and where to create or end
                        them.
                      </td>
                      <td className={CELL}>Planning a sale or ending a promotion early.</td>
                    </tr>
                    <tr>
                      <td className={`${CELL} font-medium text-ink-900`}>◎ Team</td>
                      <td className={CELL}>
                        Who is in your workspace, recent activity, and invitations for new
                        teammates.
                      </td>
                      <td className={CELL}>Adding a colleague or checking who did what.</td>
                    </tr>
                    <tr>
                      <td className={`${CELL} font-medium text-ink-900`}>⚙ Settings</td>
                      <td className={CELL}>Change your password, and manage the shop connection
                        credentials.</td>
                      <td className={CELL}>Password changes and platform setup.</td>
                    </tr>
                    <tr>
                      <td className={`${CELL} font-medium text-ink-900`}>📖 Documentation</td>
                      <td className={CELL}>
                        A heading that expands to reveal <strong>Authentication</strong> (this
                        guide&rsquo;s neighbour) and <strong>Menu Guide</strong> (this page).
                      </td>
                      <td className={CELL}>Looking something up.</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <Callout>
                <strong>Orders does not open an order list.</strong> Clicking{' '}
                <strong>◈ Orders</strong> only expands and collapses its two pick-list entries. To
                reach the order list itself, use the <strong>📋 Pick List</strong> shortcuts on the
                Dashboard, or click through from a notification in the top bar.
              </Callout>

              <Sub title="The red number on Orders">
                <P>
                  A red badge next to <strong>◈ Orders</strong> shows how many orders need your
                  attention right now — unpaid, ready to ship, and already processed. It updates
                  about once a minute. It is a count of work waiting for you, not a count of
                  problems.
                </P>
              </Sub>

              <Sub title="Bottom of the sidebar">
                <P>
                  Below the menu you will find your name, email, and your workspace role. Below
                  that is the application version number.
                </P>
              </Sub>
            </Section>

            <Section id="topbar" title="3. Top Bar">
              <Sub title="Store switcher">
                <P>
                  The tabs and dropdown at the top left of the bar filter everything on the page to
                  one platform or one store. This is the fastest way to answer &ldquo;what happened
                  in <em>this</em> shop?&rdquo;
                </P>
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                    <thead className={HEAD}>
                      <tr>
                        <th className={CELL}>Item</th>
                        <th className={CELL}>What it does</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-ink-100 text-ink-600">
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>All</td>
                        <td className={CELL}>Shows every store from both platforms at once.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Shopee</td>
                        <td className={CELL}>Limits the page to your Shopee stores.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Lazada</td>
                        <td className={CELL}>Limits the page to your Lazada stores.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>All Stores</td>
                        <td className={CELL}>
                          The dropdown beside the tabs. Picks one specific store. It only appears
                          once you have more than one store to choose from.
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <Callout>
                  Switching platform also resets the store dropdown back to{' '}
                  <strong>All Stores</strong>, because the previous store may not belong to the
                  platform you just picked.
                </Callout>
              </Sub>

              <Sub title="Notifications">
                <P>
                  The bell icon shows how many unread notifications you have. The number updates
                  roughly every 30 seconds. Click the bell to open the list.
                </P>
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                    <thead className={HEAD}>
                      <tr>
                        <th className={CELL}>Item</th>
                        <th className={CELL}>What it does</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-ink-100 text-ink-600">
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Mark all as read</td>
                        <td className={CELL}>Clears the unread badge across the whole list.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Mark read</td>
                        <td className={CELL}>Marks that single notification as read.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Dismiss</td>
                        <td className={CELL}>Deletes that notification from the list.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Click the row</td>
                        <td className={CELL}>Takes you to the page the notification is about.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <Callout>
                  Clicking an urgent-order notification opens the Orders page, but the{' '}
                  <strong>Overdue</strong>, <strong>Due today</strong>, and{' '}
                  <strong>Due in 48h</strong> filter is <em>not</em> applied for you. Tap the chip
                  you want on the Orders page to apply it yourself.
                </Callout>
              </Sub>

              <Sub title="Logout">
                <P>
                  <strong>Logout</strong> at the far right of the bar signs you out immediately.
                  There is no confirmation step, so use it freely.
                </P>
              </Sub>

              <Callout>
                <strong>There is no profile menu.</strong> Your name, email, and role are displayed
                in the sidebar but cannot be edited there. To change a password, go to{' '}
                <strong>⚙ Settings</strong>.
              </Callout>
            </Section>

            <Section id="dashboard" title="4. Dashboard">
              <Sub title="The five cards across the top">
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                    <thead className={HEAD}>
                      <tr>
                        <th className={CELL}>Card</th>
                        <th className={CELL}>What it shows</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-ink-100 text-ink-600">
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Linked Stores</td>
                        <td className={CELL}>How many stores are connected and healthy.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Orders Today</td>
                        <td className={CELL}>
                          Today&rsquo;s order count.{' '}
                          <strong>Hover or click it</strong> for a per-store breakdown. The{' '}
                          <strong>📋 Pick List</strong> button on it opens the Today Pick List.
                        </td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Sales Today</td>
                        <td className={CELL}>
                          Today&rsquo;s revenue, with a small badge comparing it to yesterday.
                        </td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Pending Orders</td>
                        <td className={CELL}>
                          Orders still waiting on something. Its{' '}
                          <strong>📋 Pick List</strong> button opens the Pending Pick List.
                        </td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Low Stock Items</td>
                        <td className={CELL}>
                          Count of products running low. Only appears when there is something to
                          warn about.
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </Sub>

              <Sub title="Sales trend">
                <P>
                  On the Sales panel, <strong>7 days</strong>, <strong>14 days</strong>, and{' '}
                  <strong>30 days</strong> switch how far back the chart reaches. This is a display
                  setting — it does not change any data.
                </P>
              </Sub>

              <Sub title="Sync">
                <P>
                  <strong>Sync</strong> at the top of the page pulls fresh orders from the
                  marketplaces. It changes to <strong>Sync Shopee</strong> or{' '}
                  <strong>Sync Lazada</strong> once you narrow the store switcher to one platform,
                  and shows <strong>Syncing…</strong> while it runs. Syncing does not change your
                  stock or prices.
                </P>
              </Sub>

              <Sub title="Store health badges">
                <P>
                  Each linked store carries a badge showing whether it is working. Here is what
                  each one means.
                </P>
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                    <thead className={HEAD}>
                      <tr>
                        <th className={CELL}>Badge</th>
                        <th className={CELL}>Meaning</th>
                        <th className={CELL}>What to do</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-ink-100 text-ink-600">
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Active</td>
                        <td className={CELL}>Working normally.</td>
                        <td className={CELL}>Nothing.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Expiring</td>
                        <td className={CELL}>
                          The marketplace permission is close to expiring.
                        </td>
                        <td className={CELL}>
                          Use <strong>Reauthorize</strong> before it runs out.
                        </td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Refresh Failed</td>
                        <td className={CELL}>
                          The app could not renew its own access to the store.
                        </td>
                        <td className={CELL}>Use <strong>Reauthorize</strong>.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Reauthorize Required</td>
                        <td className={CELL}>
                          The marketplace has revoked or expired your permission and MyStores
                          cannot get it back on its own.
                        </td>
                        <td className={CELL}>
                          Use <strong>Reauthorize</strong>. Orders stop syncing until you do.
                        </td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Disconnected</td>
                        <td className={CELL}>You disconnected this store yourself.</td>
                        <td className={CELL}>Use <strong>Reconnect</strong> to restore it.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Missing Token</td>
                        <td className={CELL}>
                          MyStores no longer holds valid access for this store.
                        </td>
                        <td className={CELL}>Use <strong>Reconnect</strong>.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </Sub>

              <Sub title="Actions on a store card">
                <P>
                  The buttons under a store change depending on its badge. Some appear only for the
                  workspace owner.
                </P>
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                    <thead className={HEAD}>
                      <tr>
                        <th className={CELL}>Action</th>
                        <th className={CELL}>What it does</th>
                        <th className={CELL}>When it appears</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-ink-100 text-ink-600">
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>
                          Reauthorize{OWNER_ONLY}
                        </td>
                        <td className={CELL}>
                          Sends you to the marketplace to approve the connection again, then
                          returns you here. Your orders and history are kept.
                        </td>
                        <td className={CELL}>Badge says reauthorization is needed.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>
                          Reconnect{OWNER_ONLY}
                        </td>
                        <td className={CELL}>
                          Puts a disconnected store back into your list.
                        </td>
                        <td className={CELL}>Badge says disconnected or missing token.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>
                          Disconnect{OWNER_ONLY}
                        </td>
                        <td className={CELL}>
                          Temporarily pauses the connection. The store stays listed and can be
                          reconnected later.
                        </td>
                        <td className={CELL}>Any healthy store.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Unlink{OWNER_ONLY}</td>
                        <td className={CELL}>
                          Permanently removes the store and everything synced with it.
                        </td>
                        <td className={CELL}>Any store. Confirmation required.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>
                          Vacation mode{OWNER_ONLY}
                        </td>
                        <td className={CELL}>
                          Switches a Shopee store into holiday mode so it stops selling while you
                          are away.
                        </td>
                        <td className={CELL}>Shopee stores only.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div className="space-y-2">
                  <p className="text-sm font-semibold text-ink-900">Unlinking a store</p>
                  <Steps
                    items={[
                      'Open ⚙ Settings first if you want a record of the shop ID — it cannot be recovered afterwards.',
                      'On the Dashboard, find the store card and choose Unlink.',
                      'Read the warning carefully. This removes synced orders and product data too.',
                      'Type DELETE STORE DATA exactly as shown, then confirm.',
                    ]}
                  />
                </div>

                <div className="space-y-2">
                  <p className="text-sm font-semibold text-ink-900">Linking a new store</p>
                  <Steps
                    items={[
                      'On the Dashboard, choose + Shopee or + Lazada.',
                      'Sign in to the marketplace seller account when prompted.',
                      'Approve the access request.',
                      'You are returned to the Dashboard automatically and the new store appears with an Active badge.',
                    ]}
                  />
                </div>
              </Sub>
            </Section>

            <Section id="orders" title="5. Orders">
              <Sub title="Platform tabs">
                <P>
                  <strong>All</strong>, <strong>Shopee</strong>, and <strong>Lazada</strong> at
                  the top of the page filter the list. These follow the store switcher in the top
                  bar, so changing one changes the other.
                </P>
              </Sub>

              <Sub title="Queue chips">
                <P>
                  The coloured chips below the tabs are your work queues. They are exclusive — pick
                  one at a time, or use <strong>All</strong> to see everything. The available queues
                  depend on which platform tab you are on, because the two marketplaces use
                  different stage names.
                </P>
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                    <thead className={HEAD}>
                      <tr>
                        <th className={CELL}>On the All tab</th>
                        <th className={CELL}>On the Shopee tab</th>
                        <th className={CELL}>On the Lazada tab</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-ink-100 text-ink-600">
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Unpaid</td>
                        <td className={`${CELL} font-medium text-ink-900`}>Unpaid</td>
                        <td className={CELL}>—</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>To fulfill</td>
                        <td className={`${CELL} font-medium text-ink-900`}>To arrange</td>
                        <td className={`${CELL} font-medium text-ink-900`}>To pack</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>To print</td>
                        <td className={`${CELL} font-medium text-ink-900`}>To print</td>
                        <td className={`${CELL} font-medium text-ink-900`}>Packed / print</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>In transit</td>
                        <td className={`${CELL} font-medium text-ink-900`}>In transit</td>
                        <td className={`${CELL} font-medium text-ink-900`}>To hand over</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Completed</td>
                        <td className={`${CELL} font-medium text-ink-900`}>Completed</td>
                        <td className={`${CELL} font-medium text-ink-900`}>Completed</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Problems</td>
                        <td className={`${CELL} font-medium text-ink-900`}>Problems</td>
                        <td className={`${CELL} font-medium text-ink-900`}>Problems</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <Callout>
                  If you were wondering where the order list went: <strong>To fulfill</strong>,{' '}
                  <strong>To arrange</strong>, and <strong>To pack</strong> are all different names
                  for the same stage.
                </Callout>
              </Sub>

              <Sub title="Filters">
                <P>
                  The filter row narrows the list further. Filters combine — set as many as you
                  need.
                </P>
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                    <thead className={HEAD}>
                      <tr>
                        <th className={CELL}>Filter</th>
                        <th className={CELL}>What it does</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-ink-100 text-ink-600">
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Search</td>
                        <td className={CELL}>
                          Matches order ID, buyer name, or an item name. Results update as you
                          type.
                        </td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Status</td>
                        <td className={CELL}>
                          Narrows to one exact order status. Only offered when a single platform is
                          selected, because the two platforms name their statuses differently.
                        </td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Urgency</td>
                        <td className={CELL}>
                          All, Overdue, Due today, or Due in 48h — measured against the ship-by
                          deadline.
                        </td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Date</td>
                        <td className={CELL}>
                          Any date, Today, Last 7 days, Last 14 days, or Custom. Choosing{' '}
                          <strong>Custom</strong> reveals two date boxes.
                        </td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Sort</td>
                        <td className={CELL}>
                          Newest first, Ship by soonest, or Highest total.
                        </td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Clear filters</td>
                        <td className={CELL}>
                          Resets everything at once. Only appears once a filter is active.
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <Callout>
                  The grey summary line under the filters spells out exactly what is currently
                  applied — <strong>Queue</strong>, <strong>Status</strong>,{' '}
                  <strong>Urgency</strong>, <strong>Date</strong>, <strong>Sort</strong>, and{' '}
                  <strong>Search</strong>. Read it first when the list looks wrong.
                </Callout>
              </Sub>

              <Sub title="Printing labels">
                <P>
                  Tick the checkbox beside an order, then use <strong>Print selected</strong>. To
                  print a whole page at once, use the checkbox in the header row.
                </P>
                <Callout>
                  You can print <strong>15 labels at a time</strong>. Selecting more than 15 shows
                  a message instead of printing. Print in batches.
                </Callout>
              </Sub>

              <Sub title="Fulfillment & Printing Guide">
                <P>
                  A collapsible panel above the table explains the correct sequence for each
                  platform. Use <strong>Hide</strong> to dismiss it — MyStores remembers your
                  choice, and the panel returns via <strong>Show Fulfillment &amp; Printing
                  Guide</strong> whenever you need it again.
                </P>
              </Sub>

              <Sub title="Row actions">
                <P>
                  Which buttons appear on an order depends entirely on its status, and on whether
                  it is a Shopee or Lazada order.
                </P>
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                    <thead className={HEAD}>
                      <tr>
                        <th className={CELL}>Button</th>
                        <th className={CELL}>Platform</th>
                        <th className={CELL}>Appears when</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-ink-100 text-ink-600">
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Show n more</td>
                        <td className={CELL}>Both</td>
                        <td className={CELL}>The order has more than two items.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Arrange Shipment</td>
                        <td className={CELL}>Shopee</td>
                        <td className={CELL}>The order is ready to ship.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>🖨 Print AWB + Packing</td>
                        <td className={CELL}>Shopee</td>
                        <td className={CELL}>
                          The order has already been processed or shipped. Arrange Shipment first.
                        </td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Pack</td>
                        <td className={CELL}>Lazada</td>
                        <td className={CELL}>The order is waiting to be packed.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>AWB + Packing</td>
                        <td className={CELL}>Lazada</td>
                        <td className={CELL}>
                          The order has been packed or is ready to ship.
                        </td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>RTS</td>
                        <td className={CELL}>Lazada</td>
                        <td className={CELL}>
                          Packed, and now ready to hand over to the courier.
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <Callout>
                  On Shopee, <strong>Arrange Shipment</strong> must come before printing — you
                  cannot print a label for an order that has not been given a shipping method.
                </Callout>
              </Sub>

              <Sub title="Order details">
                <P>
                  Click anywhere on a row to open the detail panel: buyer, dates, deadline, totals,
                  items, and any cancellation reason. From there you can also open{' '}
                  <strong>Arrange Shipment</strong> and print the label.
                </P>
                <Callout>
                  MyStores cannot cancel an order for you. Cancel on the official Shopee or Lazada
                  seller website.
                </Callout>
              </Sub>

              <Sub title="Arrange Shipment">
                <Steps
                  items={[
                    'On a Shopee order that is ready to ship, choose Arrange Shipment.',
                    'Pick how the parcel will move: 📦 Courier Pickup, 🏬 Drop Off at Branch, or ✏️ Self-Arrange (Non-integrated). Drop Off is suggested by default.',
                    'Courier Pickup — choose a pickup address and a pickup time slot.',
                    'Drop Off — choose the branch you will deliver to.',
                    'Self-Arrange — nothing further is needed; MyStores simply records that you are handling shipping yourself.',
                    'Choose Cancel to back out, or Confirm Shipment to apply it.',
                  ]}
                />
              </Sub>
            </Section>

            <Section id="stock" title="6. Stock">
              <Sub title="Syncing stock and prices">
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                    <thead className={HEAD}>
                      <tr>
                        <th className={CELL}>Button</th>
                        <th className={CELL}>What it does</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-ink-100 text-ink-600">
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Pull selected</td>
                        <td className={CELL}>
                          Fetches current quantities and prices for the stores you ticked, and
                          replaces what is shown.
                        </td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Push selected</td>
                        <td className={CELL}>
                          Sends your local edits up to the marketplaces. Anything you changed but
                          did not push is waiting for this.
                        </td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Pull this store</td>
                        <td className={CELL}>Pulls one store. Use when a single store is selected.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Pull all stores</td>
                        <td className={CELL}>Pulls every linked store in one go.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </Sub>

              <Sub title="Filters and sorting">
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                    <thead className={HEAD}>
                      <tr>
                        <th className={CELL}>Control</th>
                        <th className={CELL}>Options</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-ink-100 text-ink-600">
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Search</td>
                        <td className={CELL}>Matches name, SKU, or item ID.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Stock chips</td>
                        <td className={CELL}>
                          All, Out of stock, Low (≤5), In stock.
                        </td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Unsynced only</td>
                        <td className={CELL}>
                          Shows only products you have edited locally and not yet pushed.
                        </td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Catalog view</td>
                        <td className={CELL}>
                          All products, Linked (has SKU), Unlinked (no SKU), Multi-store SKU, Has
                          variants.
                        </td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Sort</td>
                        <td className={CELL}>
                          Name A–Z, Stock (lowest first), Price (low to high), Recently synced.
                        </td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Clear filters</td>
                        <td className={CELL}>Resets the search, chips, and dropdowns.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <P>
                  The <strong>SKU</strong>, <strong>Stores</strong>, <strong>Price</strong>, and{' '}
                  <strong>Stock</strong> column headings are also clickable — tap one to sort by it,
                  and tap again to reverse the order.
                </P>
              </Sub>

              <Sub title="The * marker">
                <P>
                  A <strong>*</strong> after a SKU or product name means you changed it in MyStores
                  and have not pushed the change to the marketplace yet. The two sides disagree until
                  you do.
                </P>
              </Sub>

              <Sub title="Editing a product">
                <Steps
                  items={[
                    'Choose Edit on a product row.',
                    'For a plain product, change the stock or the price.',
                    'For a product with variants, edit each variant separately.',
                    'If several listings share one SKU, tick the box to apply your value to all of them, then save.',
                    'Choose Cancel to discard, or Save to apply.',
                    'The change is local only until you use Push selected.',
                  ]}
                />
              </Sub>
            </Section>

            <Section id="promotions" title="7. Promotions">
              <Sub title="Filters">
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                    <thead className={HEAD}>
                      <tr>
                        <th className={CELL}>Control</th>
                        <th className={CELL}>Options</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-ink-100 text-ink-600">
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Platform tabs</td>
                        <td className={CELL}>All, Shopee, Lazada.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Promotion type</td>
                        <td className={CELL}>All types, Discount, Voucher.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Status</td>
                        <td className={CELL}>
                          Active / upcoming (the default), All, Finished.
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <P>
                  The <strong>Name</strong>, <strong>Platform</strong>, <strong>Start</strong>, and{' '}
                  <strong>End</strong> column headings sort the list.
                </P>
              </Sub>

              <Sub title="Actions on a promotion">
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                    <thead className={HEAD}>
                      <tr>
                        <th className={CELL}>Action</th>
                        <th className={CELL}>When it appears</th>
                        <th className={CELL}>What it does</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-ink-100 text-ink-600">
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>End</td>
                        <td className={CELL}>Shopee discounts and most Shopee vouchers.</td>
                        <td className={CELL}>Stops the promotion early.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Activate</td>
                        <td className={CELL}>A Lazada voucher that is switched off.</td>
                        <td className={CELL}>Turns the voucher back on.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Deactivate</td>
                        <td className={CELL}>A Lazada voucher that is currently on.</td>
                        <td className={CELL}>Turns the voucher off without deleting it.</td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Locked (affiliate)</td>
                        <td className={CELL}>
                          A voucher MyStores is not allowed to change.
                        </td>
                        <td className={CELL}>
                          Nothing — this is a status, not a button. Hover it for the reason.
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </Sub>

              <Sub title="Creating a promotion">
                <P>
                  The three <strong>New…</strong> buttons at the top of the page only appear for the
                  platform you have selected.
                </P>
                <Steps
                  items={[
                    'Choose New Shopee discount, New Shopee voucher, or New Lazada voucher.',
                    'Pick the store it applies to, and give it a name you will recognise.',
                    'Set when it starts (hours from now) and how long it runs.',
                    'For a Shopee discount, set the promo price. Choose a scope: shop voucher or product voucher.',
                    'Shop vouchers also take a minimum basket, a discount amount, and a usage limit.',
                    'Lazada vouchers choose Entire shop or Specific products, plus a minimum spend, an amount off, and how many are issued.',
                    'Choose Create. Cancelling before this point changes nothing.',
                  ]}
                />
                <Callout>
                  The product picker inside the modal will report &ldquo;No products found for this
                  store&rdquo; if you have not pulled that store&rsquo;s stock yet. Close the modal,
                  use <strong>Pull this store</strong> on the Stock page, then try again.
                </Callout>
              </Sub>
            </Section>

            <Section id="team" title="8. Team">
              <Sub title="Reading the workspace card">
                <P>
                  The card at the top names your workspace and states your role. Members can see
                  everything on this page; a few actions are owner-only.
                </P>
              </Sub>

              <Sub title="Members">
                <P>
                  Each member row shows a role badge. Owners have{' '}
                  <strong>Remove</strong> beside each member, and you cannot remove the owner.
                </P>
              </Sub>

              <Sub title="Inviting a teammate">
                <Steps
                  items={[
                    'Choose Invite member.',
                    'Enter their email address and choose Create invite.',
                    'A 64-character invite token appears. Copy it and send it to your teammate — this is the only time it is shown.',
                    'Your teammate signs in, opens Team, pastes the token under Accept an invite, and chooses Accept invite.',
                    'Pending invites are listed with their expiry date. An invite is also shown here until it is used.',
                  ]}
                />
                <Callout>
                  Pending invites can be cancelled from the same list.
                </Callout>
              </Sub>

              <Sub title="Activity">
                <P>
                  The activity card logs recent workspace actions.{' '}
                  <strong>Refresh</strong> updates it, and{' '}
                  <strong>Previous</strong> / <strong>Next</strong> page through it. Owners see all
                  workspace activity; members see only their own actions.
                </P>
              </Sub>
            </Section>

            <Section id="settings" title="9. Settings">
              <Sub title="Change Password">
                <P>
                  Available to every user. Each field has an eye button that reveals what you have
                  typed. Fill in all three boxes and choose{' '}
                  <strong>Update Password</strong>.
                </P>
              </Sub>

              <Sub title="Platform credentials">
                <Callout>
                  Everything below this line is <strong>owner only</strong>. Other members see a
                  notice saying only the workspace owner can view and edit platform credentials.
                </Callout>
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                    <thead className={HEAD}>
                      <tr>
                        <th className={CELL}>Section</th>
                        <th className={CELL}>Fields</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-ink-100 text-ink-600">
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>
                          Shopee Partner Credentials
                        </td>
                        <td className={CELL}>
                          Partner ID, Partner Key, Push Partner Key, Redirect URL.
                        </td>
                      </tr>
                      <tr>
                        <td className={`${CELL} font-medium text-ink-900`}>Lazada App Credentials</td>
                        <td className={CELL}>App Key, App Secret, Redirect URL.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <P>
                  A <strong>Configured</strong> badge means values are already saved. Use{' '}
                  <strong>Save…</strong> to store changes, and <strong>Remove</strong> to clear
                  them — removing them stops new stores from being linked.
                </P>
                <Callout>
                  The Redirect URL here must match the one registered in the marketplace console
                  exactly. A mismatch is the most common cause of linking failures. The{' '}
                  <strong>Authentication</strong> page under Documentation explains each field in
                  detail.
                </Callout>
              </Sub>
            </Section>

            <Section id="pick-list" title="10. Pick List">
              <P>
                Both pick-list pages work the same way. <strong>☑ Today Pick List</strong> covers
                orders shipping today; <strong>☐ Pending Pick List</strong> covers everything else
                still waiting.
              </P>

              <Sub title="The two sections">
                <Steps
                  items={[
                    '1. Warehouse Pick List — items totalled across all orders, so you walk each shelf once. Use this on the floor.',
                    '2. Order Packing Details — the same items split back out per order, so you can sort parcels for the right customer. Use this at the packing bench.',
                  ]}
                />
              </Sub>

              <Sub title="Picking an item">
                <Steps
                  items={[
                    'Tap the checkbox beside an item to open it.',
                    'Choose Take / upload photo & pick, then photograph the item or choose an existing photo. This is your proof of pick.',
                    'Tap Send to close and tick the item off.',
                    'To undo, reopen the item and choose Unpick.',
                    'Choose Replace photo if the first photo was unclear.',
                  ]}
                />
              </Sub>

              <Sub title="Comments">
                <P>
                  The <strong>💬</strong> button on each item opens a comment thread for colleagues
                  working the same list. Type in the box and choose <strong>Send</strong>. A dot on
                  the button means there are comments you have not opened yet. You can delete your
                  own comments; other people&rsquo;s you cannot.
                </P>
              </Sub>

              <Sub title="Printing">
                <P>
                  <strong>Refresh</strong> reloads the list. <strong>Print / Save as PDF</strong>{' '}
                  produces a clean print layout with the sidebar and top bar removed, and can be
                  saved as a PDF for the packing bench.
                </P>
              </Sub>
            </Section>

            <Section id="limits" title="11. Limits & Common Questions">
              <dl className="space-y-4 text-sm">
                <div>
                  <dt className="font-semibold text-ink-900">Q: How do I cancel an order?</dt>
                  <dd className="text-xs text-ink-600 mt-1 leading-relaxed">
                    Not from MyStores. Cancel on the official Shopee or Lazada seller website. The
                    order detail panel says the same thing.
                  </dd>
                </div>
                <div>
                  <dt className="font-semibold text-ink-900">Q: Why does printing stop at 15?</dt>
                  <dd className="text-xs text-ink-600 mt-1 leading-relaxed">
                    15 is the maximum per batch, to keep the browser from freezing. Select fewer
                    orders and print again.
                  </dd>
                </div>
                <div>
                  <dt className="font-semibold text-ink-900">Q: How do I add a new person to the workspace?</dt>
                  <dd className="text-xs text-ink-600 mt-1 leading-relaxed">
                    There is no self-service sign-up. The owner creates an invite on the Team page
                    and shares the token with them.
                  </dd>
                </div>
                <div>
                  <dt className="font-semibold text-ink-900">
                    Q: Can I edit my name or email address?
                  </dt>
                  <dd className="text-xs text-ink-600 mt-1 leading-relaxed">
                    No. Only your password can be changed from MyStores, under Settings.
                  </dd>
                </div>
                <div>
                  <dt className="font-semibold text-ink-900">
                    Q: My stock edits are not showing on the marketplace.
                  </dt>
                  <dd className="text-xs text-ink-600 mt-1 leading-relaxed">
                    Saving on the Stock page only changes MyStores. Use <strong>Push selected</strong>{' '}
                    to send the changes up. Anything unsent shows a <strong>*</strong>.
                  </dd>
                </div>
                <div>
                  <dt className="font-semibold text-ink-900">
                    Q: Are Promotions and Settings available in the mobile app?
                  </dt>
                  <dd className="text-xs text-ink-600 mt-1 leading-relaxed">
                    No. Those two areas are web-only. The mobile app covers Dashboard, Orders,
                    Stock, Pick Lists, Team, and Plant ID.
                  </dd>
                </div>
                <div>
                  <dt className="font-semibold text-ink-900">Q: A menu button is missing.</dt>
                  <dd className="text-xs text-ink-600 mt-1 leading-relaxed">
                    Most actions appear only when the related status allows them — row buttons on
                    Orders and Promotions work this way. Owner-only actions are hidden from
                    members. If something is genuinely absent, check your role at the bottom of the
                    sidebar.
                  </dd>
                </div>
              </dl>

              <Callout>
                This guide reflects the menu as currently labelled in the web app. Button names are
                fixed text, so if the interface is reworded, this page needs the same update.
              </Callout>
            </Section>
          </article>
        </div>
      </div>
    </PageShell>
  );
}