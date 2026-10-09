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

  const origin = typeof window !== 'undefined' ? window.location.origin : 'https://<your-domain>';
  const shopeeCallback = `${origin}/api/shopee/callback`;
  const lazadaCallback = `${origin}/api/lazada/callback`;

  const scrollTo = (id: string) => {
    setActiveSection(id);
    const element = document.getElementById(id);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth' });
    }
  };

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

            <div className="mt-6 pt-4 border-t border-ink-100 text-xs text-ink-500">
              <p className="font-semibold text-ink-700 mb-1">Registered Endpoints</p>
              <div className="space-y-2 mt-2">
                <div>
                  <span className="text-[10px] uppercase font-bold text-ink-400">Shopee Callback</span>
                  <div className="flex items-center gap-1 mt-0.5">
                    <input
                      type="text"
                      readOnly
                      value={shopeeCallback}
                      className="w-full bg-ink-50 border border-ink-200 rounded px-1.5 py-0.5 text-[11px] font-mono text-ink-800"
                    />
                    <button
                      onClick={() => copyToClipboard(shopeeCallback, 'shopee-side')}
                      className="px-1.5 py-0.5 text-[11px] bg-ink-200 hover:bg-ink-300 rounded text-ink-800"
                    >
                      {copiedKey === 'shopee-side' ? '✓' : 'Copy'}
                    </button>
                  </div>
                </div>
                <div>
                  <span className="text-[10px] uppercase font-bold text-ink-400">Lazada Callback</span>
                  <div className="flex items-center gap-1 mt-0.5">
                    <input
                      type="text"
                      readOnly
                      value={lazadaCallback}
                      className="w-full bg-ink-50 border border-ink-200 rounded px-1.5 py-0.5 text-[11px] font-mono text-ink-800"
                    />
                    <button
                      onClick={() => copyToClipboard(lazadaCallback, 'lazada-side')}
                      className="px-1.5 py-0.5 text-[11px] bg-ink-200 hover:bg-ink-300 rounded text-ink-800"
                    >
                      {copiedKey === 'lazada-side' ? '✓' : 'Copy'}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </aside>

          {/* Main Documentation Body */}
          <article className="flex-1 min-w-0 bg-white border border-ink-200/80 rounded-xl p-6 sm:p-10 shadow-xs space-y-12 text-ink-800">
            {/* Title / Intro */}
            <header className="border-b border-ink-100 pb-6">
              <div className="flex items-center gap-2 text-xs font-semibold text-ink-500 uppercase tracking-wider mb-1">
                <span>Documentation</span>
                <span>/</span>
                <span>Integrations</span>
                <span>/</span>
                <span>Authentication</span>
              </div>
              <h1 className="text-3xl font-bold font-heading text-ink-950 tracking-tight">
                Marketplace OAuth 2.0 Integration
              </h1>
              <p className="mt-2 text-sm text-ink-600 leading-relaxed max-w-3xl">
                Technical specification for the authorization, token persistence, and background session maintenance across Shopee Open Platform and Lazada Open Platform.
              </p>
            </header>

            {/* Section 1: Overview */}
            <section id="overview" className="space-y-4">
              <h2 className="text-xl font-bold text-ink-950 border-b border-ink-100 pb-2">
                1. Architecture & Protocol
              </h2>
              <p className="text-sm leading-relaxed text-ink-700">
                Both marketplaces utilize the standard <strong>OAuth 2.0 Authorization Code Grant</strong> workflow. The authorization flow is completely automated—end users log in through their respective marketplace seller consoles and authorize access. <strong>The temporary Authorization Code is intercepted and exchanged by the server automatically without any manual copying or pasting.</strong>
              </p>

              {/* ASCII Protocol Diagram */}
              <div className="bg-ink-950 text-ink-100 rounded-lg p-4 font-mono text-xs overflow-x-auto leading-tight">
{`+-------------+             +--------------------+             +------------------+
| User Store  |             |      MyStores      |             |  Open Platform   |
|  (Browser)  |             |  (Backend Server)  |             | (Shopee / Lazada)|
+------+------+             +---------+----------+             +--------+---------+
       |                               |                                |
       | 1. Trigger "Link Store"       |                                |
       |------------------------------>|                                |
       |                               | 2. Generate signed auth URL    |
       |                               |    + 64-char CSRF state token  |
       | 3. Redirect to platform URL   |                                |
       |<------------------------------|                                |
       |                                                                |
       | 4. User logs in & clicks "Confirm / Authorize"                 |
       |--------------------------------------------------------------->|
       |                                                                |
       | 5. Platform redirects browser back with ?code=...&state=...    |
       |<---------------------------------------------------------------|
       |                                                                |
       | 6. Hits /api/{platform}/callback                               |
       |------------------------------>|                                |
       |                               | 7. Validate & consume state    |
       |                               | 8. Server-to-server POST:      |
       |                               |    Exchange code for tokens    |
       |                               |------------------------------->|
       |                               |                                |
       |                               | 9. Receive tokens & profile    |
       |                               |<-------------------------------|
       |                               | 10. Encrypt tokens (AES-256)   |
       | 11. Redirect /dashboard       |     and save store records     |
       |     (?linked=1)               |                                |
       |<------------------------------|                                |`}
              </div>
            </section>

            {/* Section 2: Shopee */}
            <section id="shopee-flow" className="space-y-4">
              <h2 className="text-xl font-bold text-ink-950 border-b border-ink-100 pb-2">
                2. Shopee Open Platform
              </h2>
              <p className="text-sm leading-relaxed text-ink-700">
                Shopee requires an HMAC-SHA256 signature when initiating the authorization request. Partner credentials are tied to the tenant workspace and used to generate the authorization link.
              </p>

              <h3 className="text-sm font-bold text-ink-900 mt-4">Required Partner Configurations</h3>
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                  <thead className="bg-ink-50 text-ink-700 font-semibold border-b border-ink-200">
                    <tr>
                      <th className="p-3">Field</th>
                      <th className="p-3">Description</th>
                      <th className="p-3">Where to configure</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-ink-100 text-ink-600">
                    <tr>
                      <td className="p-3 font-mono font-medium text-ink-900">Partner ID</td>
                      <td className="p-3">Unique numerical developer account ID</td>
                      <td className="p-3">MyStores Settings → Shopee Credentials</td>
                    </tr>
                    <tr>
                      <td className="p-3 font-mono font-medium text-ink-900">Partner Key</td>
                      <td className="p-3">Private HMAC secret key</td>
                      <td className="p-3">MyStores Settings → Shopee Credentials</td>
                    </tr>
                    <tr>
                      <td className="p-3 font-mono font-medium text-ink-900">Redirect URL</td>
                      <td className="p-3">
                        <code className="text-[11px] bg-ink-50 px-1 py-0.5 rounded">{shopeeCallback}</code>
                      </td>
                      <td className="p-3">Shopee Open Platform Console → App Management</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <h3 className="text-sm font-bold text-ink-900 mt-4">Authorization URL Construction</h3>
              <p className="text-xs text-ink-600">
                The signature is computed over the partner ID, path, and timestamp:
              </p>
              <pre className="bg-ink-900 text-ink-100 text-xs p-3 rounded-lg font-mono overflow-x-auto">
{`$path = '/api/v2/shop/auth_partner';
$baseString = $partnerId . $path . $timestamp;
$sign = hash_hmac('sha256', $baseString, $partnerKey);

$authUrl = "{$host}{$path}?partner_id={$partnerId}&timestamp={$timestamp}&sign={$sign}&redirect=" . urlencode($redirectUrl);`}
              </pre>

              <div id="shopee-tokens" className="pt-4">
                <h3 className="text-sm font-bold text-ink-900 mb-2">Token Lifecycles & Parameters</h3>
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                    <thead className="bg-ink-50 text-ink-700 font-semibold border-b border-ink-200">
                      <tr>
                        <th className="p-3">Parameter / Token</th>
                        <th className="p-3">Lifetime</th>
                        <th className="p-3">Behavior & Storage</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-ink-100 text-ink-600">
                      <tr>
                        <td className="p-3 font-mono font-medium text-ink-900">authorization_code</td>
                        <td className="p-3">~5–10 Minutes</td>
                        <td className="p-3">Single-use temporary ticket exchanged immediately on callback.</td>
                      </tr>
                      <tr>
                        <td className="p-3 font-mono font-medium text-ink-900">access_token</td>
                        <td className="p-3">4 Hours (14,400s)</td>
                        <td className="p-3">Used for all API requests. Stored with AES-256 encryption.</td>
                      </tr>
                      <tr>
                        <td className="p-3 font-mono font-medium text-ink-900">refresh_token</td>
                        <td className="p-3">30 Days</td>
                        <td className="p-3">Single-use rotating token used to generate a new token pair.</td>
                      </tr>
                      <tr>
                        <td className="p-3 font-mono font-medium text-ink-900">auth_expires_at</td>
                        <td className="p-3">365 Days</td>
                        <td className="p-3">Seller authorization limit. Requires owner to click Reauthorize.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </section>

            {/* Section 3: Lazada */}
            <section id="lazada-flow" className="space-y-4">
              <h2 className="text-xl font-bold text-ink-950 border-b border-ink-100 pb-2">
                3. Lazada Open Platform
              </h2>
              <p className="text-sm leading-relaxed text-ink-700">
                Lazada uses the OAuth 2.0 authorization code flow through Lazada's unified auth endpoint (<code className="text-xs bg-ink-50 px-1 py-0.5 rounded font-mono">auth.lazada.com/oauth/authorize</code>).
              </p>

              <h3 className="text-sm font-bold text-ink-900 mt-4">Required App Configurations</h3>
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                  <thead className="bg-ink-50 text-ink-700 font-semibold border-b border-ink-200">
                    <tr>
                      <th className="p-3">Field</th>
                      <th className="p-3">Description</th>
                      <th className="p-3">Where to configure</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-ink-100 text-ink-600">
                    <tr>
                      <td className="p-3 font-mono font-medium text-ink-900">App Key</td>
                      <td className="p-3">Application Client ID</td>
                      <td className="p-3">MyStores Settings → Lazada Credentials</td>
                    </tr>
                    <tr>
                      <td className="p-3 font-mono font-medium text-ink-900">App Secret</td>
                      <td className="p-3">Application Secret Key</td>
                      <td className="p-3">MyStores Settings → Lazada Credentials</td>
                    </tr>
                    <tr>
                      <td className="p-3 font-mono font-medium text-ink-900">Callback URL</td>
                      <td className="p-3">
                        <code className="text-[11px] bg-ink-50 px-1 py-0.5 rounded">{lazadaCallback}</code>
                      </td>
                      <td className="p-3">Lazada Open Platform Console → App Overview</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div id="lazada-tokens" className="pt-4">
                <h3 className="text-sm font-bold text-ink-900 mb-2">Token Lifecycles & Parameters</h3>
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs border border-ink-200 rounded-lg">
                    <thead className="bg-ink-50 text-ink-700 font-semibold border-b border-ink-200">
                      <tr>
                        <th className="p-3">Parameter / Token</th>
                        <th className="p-3">Lifetime</th>
                        <th className="p-3">Behavior & Storage</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-ink-100 text-ink-600">
                      <tr>
                        <td className="p-3 font-mono font-medium text-ink-900">code</td>
                        <td className="p-3">~5 Minutes</td>
                        <td className="p-3">Single-use authorization code exchanged on callback.</td>
                      </tr>
                      <tr>
                        <td className="p-3 font-mono font-medium text-ink-900">access_token</td>
                        <td className="p-3">~30 Days (2,592,000s)</td>
                        <td className="p-3">Stored with AES-256 encryption.</td>
                      </tr>
                      <tr>
                        <td className="p-3 font-mono font-medium text-ink-900">refresh_token</td>
                        <td className="p-3">~180 Days (15,552,000s)</td>
                        <td className="p-3">Used to rotate expired access tokens.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </section>

            {/* Section 4: Security */}
            <section id="security" className="space-y-4">
              <h2 className="text-xl font-bold text-ink-950 border-b border-ink-100 pb-2">
                4. Security & Replay Prevention
              </h2>
              <div className="space-y-3 text-sm text-ink-700">
                <div className="p-3.5 bg-ink-50 border border-ink-200 rounded-lg">
                  <h4 className="font-semibold text-ink-950 text-xs uppercase tracking-wide">CSRF & State Token Verification</h4>
                  <p className="text-xs text-ink-600 mt-1">
                    Every authorization URL contains a cryptographic 64-character random <code className="bg-white px-1 py-0.5 rounded font-mono border border-ink-200">state</code> token. Its SHA-256 hash is saved with a strict 10-minute expiry. During callback, the state is locked via a database transaction (<code className="bg-white px-1 py-0.5 rounded font-mono border border-ink-200">lockForUpdate()</code>) and marked consumed immediately to guarantee single-use execution.
                  </p>
                </div>
                <div className="p-3.5 bg-ink-50 border border-ink-200 rounded-lg">
                  <h4 className="font-semibold text-ink-950 text-xs uppercase tracking-wide">Encryption at Rest</h4>
                  <p className="text-xs text-ink-600 mt-1">
                    All <code className="bg-white px-1 py-0.5 rounded font-mono border border-ink-200">access_token</code> and <code className="bg-white px-1 py-0.5 rounded font-mono border border-ink-200">refresh_token</code> strings are encrypted using AES-256-CBC via Laravel’s <code className="bg-white px-1 py-0.5 rounded font-mono border border-ink-200">encrypt()</code> before storage. Tokens are never transmitted to frontend clients.
                  </p>
                </div>
                <div className="p-3.5 bg-ink-50 border border-ink-200 rounded-lg">
                  <h4 className="font-semibold text-ink-950 text-xs uppercase tracking-wide">Atomic Refresh Locks (Concurrency Guard)</h4>
                  <p className="text-xs text-ink-600 mt-1">
                    Because Shopee and Lazada refresh tokens are single-use, parallel background tasks attempting concurrent refreshes could burn the token. The backend acquires an atomic distributed cache lock per shop ID (<code className="bg-white px-1 py-0.5 rounded font-mono border border-ink-200">Cache::lock("shopee:token-refresh:shop_{'{id}'}", 30)</code>) before refreshing.
                  </p>
                </div>
              </div>
            </section>

            {/* Section 5: Troubleshooting & FAQ */}
            <section id="faq" className="space-y-4">
              <h2 className="text-xl font-bold text-ink-950 border-b border-ink-100 pb-2">
                5. Troubleshooting & FAQ
              </h2>
              <dl className="space-y-4 text-sm">
                <div>
                  <dt className="font-semibold text-ink-900">
                    Q: Does the seller have to copy the Authorization Code?
                  </dt>
                  <dd className="text-xs text-ink-600 mt-1 leading-relaxed">
                    No. The redirection from the marketplace directly contacts our callback URL, which extracts the code, completes the exchange, and returns the seller to the dashboard with an active store.
                  </dd>
                </div>
                <div>
                  <dt className="font-semibold text-ink-900">
                    Q: What causes "Redirect URL Mismatch" errors?
                  </dt>
                  <dd className="text-xs text-ink-600 mt-1 leading-relaxed">
                    The redirect URL registered in your Shopee or Lazada Open Platform console must match the backend callback route exact to the character, including protocol (<code className="bg-ink-50 px-1 py-0.5 rounded font-mono">https://</code>) and trailing paths.
                  </dd>
                </div>
                <div>
                  <dt className="font-semibold text-ink-900">
                    Q: How does reauthorization work?
                  </dt>
                  <dd className="text-xs text-ink-600 mt-1 leading-relaxed">
                    When a store reaches the end of its 365-day seller grant, clicking <strong>Reauthorize Store</strong> on the dashboard generates a state token linked to that specific store ID. When the callback is received, it confirms the returned <code className="bg-ink-50 px-1 py-0.5 rounded font-mono">shop_id</code> matches before updating the existing store record—avoiding duplicates and preserving all orders and mappings.
                  </dd>
                </div>
              </dl>
            </section>
          </article>
        </div>
      </div>
    </PageShell>
  );
}
