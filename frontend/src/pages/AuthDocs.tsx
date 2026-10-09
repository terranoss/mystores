import { useState } from 'react';
import { PageShell } from '../components/PageShell';

export default function AuthDocs() {
  const [activeSection, setActiveSection] = useState<string>('overview');
  const [copiedKey, setCopiedKey] = useState<string | null>(null);

  const copyToClipboard = (text: string, key: string) => {
    navigator.clipboard.writeText(text);
    setCopiedKey(key);
    setTimeout(() => setCopiedKey(null), 2000);
  };

  const shopeeCallback = 'https://mystores.melur.com/api/shopee/callback';
  const lazadaCallback = 'https://mystores.melur.com/api/lazada/callback';

  const scrollTo = (id: string) => {
    setActiveSection(id);
    const element = document.getElementById(id);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth' });
    }
  };

  const flowSteps = [
    {
      actor: 'Store Owner',
      badgeColor: 'bg-emerald-100 text-emerald-800 border-emerald-200',
      title: '1. Initiate Connection',
      desc: 'Owner clicks [Link Store] on MyStores dashboard.',
    },
    {
      actor: 'MyStores Backend',
      badgeColor: 'bg-clay-100 text-clay-800 border-clay-200',
      title: '2. Generate Auth URL & State',
      desc: 'Backend generates HMAC signature and a unique 64-char CSRF state token (10-min TTL).',
    },
    {
      actor: 'Shopee / Lazada',
      badgeColor: 'bg-blue-100 text-blue-800 border-blue-200',
      title: '3. Seller Login & Consent',
      desc: 'User logs into official seller account and clicks "Confirm / Authorize".',
    },
    {
      actor: 'Browser Redirect',
      badgeColor: 'bg-amber-100 text-amber-800 border-amber-200',
      title: '4. Automatic Redirect with Code',
      desc: 'Platform redirects browser to /api/{platform}/callback with ?code=...&state=...',
    },
    {
      actor: 'MyStores Backend',
      badgeColor: 'bg-clay-100 text-clay-800 border-clay-200',
      title: '5. Instant Token Trade & Encryption',
      desc: 'Backend verifies state, calls platform API to exchange code for tokens, and encrypts with AES-256.',
    },
    {
      actor: 'Store Owner',
      badgeColor: 'bg-emerald-100 text-emerald-800 border-emerald-200',
      title: '6. Redirect to Dashboard',
      desc: 'Browser returns to /dashboard?linked=1 with store active and syncing ready.',
    },
  ];

  return (
    <PageShell>
      <div className="max-w-6xl mx-auto pb-16">
        <div className="flex flex-col lg:flex-row gap-8 items-start">
          {/* Left Table of Contents */}
          <aside className="w-full lg:w-60 shrink-0 lg:sticky lg:top-20 bg-white border border-ink-200 rounded-xl p-4 shadow-xs">
            <h2 className="text-[11px] font-bold text-ink-400 uppercase tracking-wider mb-2">
              Sections
            </h2>
            <nav className="space-y-1">
              {[
                { id: 'overview', label: '1. Architecture & Protocol' },
                { id: 'shopee-flow', label: '2. Shopee Open Platform' },
                { id: 'shopee-tokens', label: '   ↳ Tokens & Lifecycle' },
                { id: 'lazada-flow', label: '3. Lazada Open Platform' },
                { id: 'lazada-tokens', label: '   ↳ Tokens & Lifecycle' },
                { id: 'security', label: '4. Security & Replay Prevention' },
                { id: 'faq', label: '5. Troubleshooting & FAQ' },
              ].map((item) => (
                <button
                  key={item.id}
                  onClick={() => scrollTo(item.id)}
                  className={`w-full text-left px-2 py-1.5 rounded text-xs transition ${
                    activeSection === item.id
                      ? 'bg-ink-100 text-ink-950 font-semibold'
                      : 'text-ink-600 hover:text-ink-900 hover:bg-ink-50'
                  }`}
                >
                  {item.label}
                </button>
              ))}
            </nav>

            <div className="mt-6 pt-4 border-t border-ink-100 space-y-3">
              <span className="text-[10px] font-bold text-ink-400 uppercase tracking-wider block">
                Callback URLs
              </span>
              <div>
                <span className="text-[11px] font-semibold text-ink-700 block mb-1">Shopee</span>
                <div className="flex items-center gap-1">
                  <input
                    type="text"
                    readOnly
                    value={shopeeCallback}
                    className="w-full bg-ink-50 border border-ink-200 rounded px-2 py-1 text-[11px] font-mono text-ink-700 select-all"
                  />
                  <button
                    onClick={() => copyToClipboard(shopeeCallback, 'shopee-cb')}
                    className="px-2 py-1 text-xs bg-ink-100 hover:bg-ink-200 text-ink-800 rounded border border-ink-200 shrink-0"
                  >
                    {copiedKey === 'shopee-cb' ? 'Copied' : 'Copy'}
                  </button>
                </div>
              </div>

              <div>
                <span className="text-[11px] font-semibold text-ink-700 block mb-1">Lazada</span>
                <div className="flex items-center gap-1">
                  <input
                    type="text"
                    readOnly
                    value={lazadaCallback}
                    className="w-full bg-ink-50 border border-ink-200 rounded px-2 py-1 text-[11px] font-mono text-ink-700 select-all"
                  />
                  <button
                    onClick={() => copyToClipboard(lazadaCallback, 'lazada-cb')}
                    className="px-2 py-1 text-xs bg-ink-100 hover:bg-ink-200 text-ink-800 rounded border border-ink-200 shrink-0"
                  >
                    {copiedKey === 'lazada-cb' ? 'Copied' : 'Copy'}
                  </button>
                </div>
              </div>
            </div>
          </aside>

          {/* Main Content */}
          <main className="flex-1 min-w-0 bg-white border border-ink-200 rounded-xl p-6 sm:p-10 shadow-xs space-y-10 text-ink-800">
            {/* Header */}
            <div className="border-b border-ink-200 pb-5">
              <div className="flex items-center gap-2 text-xs font-semibold text-ink-400 uppercase tracking-wider mb-1.5">
                <span>Developer Guide</span>
                <span>/</span>
                <span>Marketplace OAuth 2.0</span>
              </div>
              <h1 className="text-2xl sm:text-3xl font-bold font-heading text-ink-950">
                Marketplace OAuth 2.0 Integration
              </h1>
              <p className="mt-2 text-sm text-ink-600 leading-relaxed">
                Technical reference for store authorization, token persistence, and automated background refresh across Shopee and Lazada.
              </p>
            </div>

            {/* 1. Architecture & Protocol */}
            <section id="overview" className="space-y-4">
              <h2 className="text-lg font-bold text-ink-950 border-b border-ink-100 pb-2">
                1. Architecture & Protocol
              </h2>
              <p className="text-sm leading-relaxed text-ink-700">
                Both marketplaces utilize the standard <strong>OAuth 2.0 Authorization Code Grant</strong> workflow. The authorization flow is completely automated—end users log in through their respective marketplace seller consoles and authorize access. <strong>The temporary Authorization Code is intercepted and exchanged by the server automatically without any manual copying or pasting.</strong>
              </p>

              {/* Clean Sequence Flow */}
              <div className="mt-4 border border-ink-200 rounded-xl overflow-hidden divide-y divide-ink-100">
                {flowSteps.map((step, idx) => (
                  <div key={idx} className="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white hover:bg-ink-50/50 transition">
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <span className={`text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border ${step.badgeColor}`}>
                          {step.actor}
                        </span>
                        <h3 className="text-sm font-semibold text-ink-900">{step.title}</h3>
                      </div>
                      <p className="text-xs text-ink-600 pl-1">{step.desc}</p>
                    </div>
                  </div>
                ))}
              </div>
            </section>

            {/* 2. Shopee Open Platform */}
            <section id="shopee-flow" className="space-y-4">
              <h2 className="text-lg font-bold text-ink-950 border-b border-ink-100 pb-2">
                2. Shopee Open Platform
              </h2>
              <p className="text-sm leading-relaxed text-ink-700">
                Shopee requires an HMAC-SHA256 signature when initiating the authorization request. Partner credentials are configured under Workspace Settings.
              </p>

              <h3 className="text-sm font-bold text-ink-900 mt-4">Required Partner Configurations</h3>
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                  <thead className="bg-ink-50 text-ink-700 font-semibold border-b border-ink-200">
                    <tr>
                      <th className="p-2.5">Field</th>
                      <th className="p-2.5">Description</th>
                      <th className="p-2.5">Config Location</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-ink-100 text-ink-600">
                    <tr>
                      <td className="p-2.5 font-mono font-medium text-ink-900">Partner ID</td>
                      <td className="p-2.5">Unique numerical developer account ID</td>
                      <td className="p-2.5">Settings → Shopee Credentials</td>
                    </tr>
                    <tr>
                      <td className="p-2.5 font-mono font-medium text-ink-900">Partner Key</td>
                      <td className="p-2.5">Private HMAC secret key</td>
                      <td className="p-2.5">Settings → Shopee Credentials</td>
                    </tr>
                    <tr>
                      <td className="p-2.5 font-mono font-medium text-ink-900">Redirect URL</td>
                      <td className="p-2.5">
                        <code className="text-[11px] bg-ink-50 px-1 py-0.5 rounded">{shopeeCallback}</code>
                      </td>
                      <td className="p-2.5">Shopee Console → App Management</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <h3 className="text-sm font-bold text-ink-900 mt-4">Signature Generation (Backend)</h3>
              <pre className="bg-ink-950 text-ink-100 text-xs p-3.5 rounded-lg font-mono overflow-x-auto leading-relaxed">
{`$path = '/api/v2/shop/auth_partner';
$baseString = $partnerId . $path . $timestamp;
$sign = hash_hmac('sha256', $baseString, $partnerKey);

$authUrl = "{$host}{$path}?partner_id={$partnerId}&timestamp={$timestamp}&sign={$sign}&redirect=" . urlencode($redirectUrl);`}
              </pre>

              <div id="shopee-tokens" className="pt-4">
                <h3 className="text-sm font-bold text-ink-900 mb-2">Tokens & Expiration Lifecycles</h3>
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                    <thead className="bg-ink-50 text-ink-700 font-semibold border-b border-ink-200">
                      <tr>
                        <th className="p-2.5">Parameter / Token</th>
                        <th className="p-2.5">Lifetime</th>
                        <th className="p-2.5">Handling</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-ink-100 text-ink-600">
                      <tr>
                        <td className="p-2.5 font-mono font-medium text-ink-900">code</td>
                        <td className="p-2.5">~5–10 Minutes</td>
                        <td className="p-2.5">Single-use temporary ticket exchanged immediately on callback.</td>
                      </tr>
                      <tr>
                        <td className="p-2.5 font-mono font-medium text-ink-900">access_token</td>
                        <td className="p-2.5">4 Hours (14,400s)</td>
                        <td className="p-2.5">Used for API requests. Encrypted at rest (AES-256).</td>
                      </tr>
                      <tr>
                        <td className="p-2.5 font-mono font-medium text-ink-900">refresh_token</td>
                        <td className="p-2.5">30 Days</td>
                        <td className="p-2.5">Single-use rotating token used to fetch a fresh token pair.</td>
                      </tr>
                      <tr>
                        <td className="p-2.5 font-mono font-medium text-ink-900">auth_expires_at</td>
                        <td className="p-2.5">365 Days</td>
                        <td className="p-2.5">Seller authorization period. Owner clicks Reauthorize to renew.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </section>

            {/* 3. Lazada Open Platform */}
            <section id="lazada-flow" className="space-y-4">
              <h2 className="text-lg font-bold text-ink-950 border-b border-ink-100 pb-2">
                3. Lazada Open Platform
              </h2>
              <p className="text-sm leading-relaxed text-ink-700">
                Lazada uses the OAuth 2.0 authorization code flow via Lazada's central auth gateway.
              </p>

              <h3 className="text-sm font-bold text-ink-900 mt-4">Required App Configurations</h3>
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                  <thead className="bg-ink-50 text-ink-700 font-semibold border-b border-ink-200">
                    <tr>
                      <th className="p-2.5">Field</th>
                      <th className="p-2.5">Description</th>
                      <th className="p-2.5">Config Location</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-ink-100 text-ink-600">
                    <tr>
                      <td className="p-2.5 font-mono font-medium text-ink-900">App Key</td>
                      <td className="p-2.5">Application Client ID</td>
                      <td className="p-2.5">Settings → Lazada Credentials</td>
                    </tr>
                    <tr>
                      <td className="p-2.5 font-mono font-medium text-ink-900">App Secret</td>
                      <td className="p-2.5">Application Secret Key</td>
                      <td className="p-2.5">Settings → Lazada Credentials</td>
                    </tr>
                    <tr>
                      <td className="p-2.5 font-mono font-medium text-ink-900">Callback URL</td>
                      <td className="p-2.5">
                        <code className="text-[11px] bg-ink-50 px-1 py-0.5 rounded">{lazadaCallback}</code>
                      </td>
                      <td className="p-2.5">Lazada Console → App Overview</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div id="lazada-tokens" className="pt-4">
                <h3 className="text-sm font-bold text-ink-900 mb-2">Tokens & Expiration Lifecycles</h3>
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                    <thead className="bg-ink-50 text-ink-700 font-semibold border-b border-ink-200">
                      <tr>
                        <th className="p-2.5">Parameter / Token</th>
                        <th className="p-2.5">Lifetime</th>
                        <th className="p-2.5">Handling</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-ink-100 text-ink-600">
                      <tr>
                        <td className="p-2.5 font-mono font-medium text-ink-900">code</td>
                        <td className="p-2.5">~5 Minutes</td>
                        <td className="p-2.5">Single-use authorization code exchanged on callback.</td>
                      </tr>
                      <tr>
                        <td className="p-2.5 font-mono font-medium text-ink-900">access_token</td>
                        <td className="p-2.5">~30 Days (2,592,000s)</td>
                        <td className="p-2.5">Stored with AES-256 encryption.</td>
                      </tr>
                      <tr>
                        <td className="p-2.5 font-mono font-medium text-ink-900">refresh_token</td>
                        <td className="p-2.5">~180 Days (15,552,000s)</td>
                        <td className="p-2.5">Rotates expired access tokens.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </section>

            {/* 4. Security */}
            <section id="security" className="space-y-4">
              <h2 className="text-lg font-bold text-ink-950 border-b border-ink-100 pb-2">
                4. Security & Replay Prevention
              </h2>
              <div className="space-y-3 text-sm text-ink-700">
                <div className="p-3.5 bg-ink-50 border border-ink-200 rounded-lg">
                  <h4 className="font-semibold text-ink-950 text-xs uppercase tracking-wide">CSRF & State Token Verification</h4>
                  <p className="text-xs text-ink-600 mt-1">
                    Every authorization URL contains a cryptographic 64-character random <code className="bg-white px-1 py-0.5 rounded font-mono border border-ink-200">state</code> token. Its SHA-256 hash is saved with a 10-minute expiry. During callback, the state is locked via a database transaction (<code className="bg-white px-1 py-0.5 rounded font-mono border border-ink-200">lockForUpdate()</code>) and marked consumed immediately to prevent replay attacks.
                  </p>
                </div>
                <div className="p-3.5 bg-ink-50 border border-ink-200 rounded-lg">
                  <h4 className="font-semibold text-ink-950 text-xs uppercase tracking-wide">Encryption at Rest</h4>
                  <p className="text-xs text-ink-600 mt-1">
                    All <code className="bg-white px-1 py-0.5 rounded font-mono border border-ink-200">access_token</code> and <code className="bg-white px-1 py-0.5 rounded font-mono border border-ink-200">refresh_token</code> strings are encrypted using AES-256-CBC via Laravel’s <code className="bg-white px-1 py-0.5 rounded font-mono border border-ink-200">encrypt()</code> before storage. Tokens are never exposed to browser clients.
                  </p>
                </div>
                <div className="p-3.5 bg-ink-50 border border-ink-200 rounded-lg">
                  <h4 className="font-semibold text-ink-950 text-xs uppercase tracking-wide">Atomic Refresh Locks (Concurrency Guard)</h4>
                  <p className="text-xs text-ink-600 mt-1">
                    Because refresh tokens are single-use, parallel background tasks attempting concurrent refreshes could burn the token. The backend acquires an atomic distributed cache lock per shop ID (<code className="bg-white px-1 py-0.5 rounded font-mono border border-ink-200">Cache::lock("shopee:token-refresh:shop_{'{id}'}", 30)</code>) before refreshing.
                  </p>
                </div>
              </div>
            </section>

            {/* 5. Troubleshooting & FAQ */}
            <section id="faq" className="space-y-4">
              <h2 className="text-lg font-bold text-ink-950 border-b border-ink-100 pb-2">
                5. Troubleshooting & FAQ
              </h2>
              <dl className="space-y-4 text-sm">
                <div>
                  <dt className="font-semibold text-ink-900">
                    Q: Does the seller have to copy the Authorization Code?
                  </dt>
                  <dd className="text-xs text-ink-600 mt-1 leading-relaxed">
                    No. The marketplace redirects directly back to our callback route, which automatically exchanges the code for tokens and redirects the user back to the dashboard with an active store.
                  </dd>
                </div>
                <div>
                  <dt className="font-semibold text-ink-900">
                    Q: What causes "Redirect URL Mismatch" errors?
                  </dt>
                  <dd className="text-xs text-ink-600 mt-1 leading-relaxed">
                    The redirect URL configured in your Shopee or Lazada Open Platform console must match the backend callback route exact to the character, including protocol (<code className="bg-ink-50 px-1 py-0.5 rounded font-mono">https://</code>) and trailing paths.
                  </dd>
                </div>
                <div>
                  <dt className="font-semibold text-ink-900">
                    Q: How does reauthorization work?
                  </dt>
                  <dd className="text-xs text-ink-600 mt-1 leading-relaxed">
                    When a store reaches the end of its 365-day seller grant, clicking <strong>Reauthorize Store</strong> generates a state token linked to that specific store ID. When the callback is received, it confirms the returned <code className="bg-ink-50 px-1 py-0.5 rounded font-mono">shop_id</code> matches before updating the existing store record—avoiding duplicates and preserving all orders and mappings.
                  </dd>
                </div>
              </dl>
            </section>
          </main>
        </div>
      </div>
    </PageShell>
  );
}
