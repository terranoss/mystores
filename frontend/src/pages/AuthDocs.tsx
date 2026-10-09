import { useState } from 'react';
import { PageShell } from '../components/PageShell';

export default function AuthDocs() {
  const [copiedKey, setCopiedKey] = useState<string | null>(null);

  // Dynamically derive the callback URLs from the active browser domain
  const origin = typeof window !== 'undefined' ? window.location.origin : 'https://your-domain.com';
  const shopeeCallback = `${origin}/api/shopee/callback`;
  const lazadaCallback = `${origin}/api/lazada/callback`;

  const copyToClipboard = (text: string, key: string) => {
    navigator.clipboard.writeText(text);
    setCopiedKey(key);
    setTimeout(() => setCopiedKey(null), 2000);
  };

  return (
    <PageShell>
      <div className="max-w-4xl mx-auto space-y-8 pb-16">
        {/* Header */}
        <div className="bg-white border border-ink-200 rounded-2xl p-6 sm:p-8 shadow-xs">
          <div className="flex items-center gap-2 text-xs font-semibold text-ink-500 uppercase tracking-wider mb-2">
            <span>Help Center</span>
            <span>/</span>
            <span>Store Connections</span>
          </div>
          <h1 className="text-2xl sm:text-3xl font-bold font-heading text-ink-950">
            Connecting Shopee & Lazada Stores
          </h1>
          <p className="mt-2 text-sm text-ink-600 leading-relaxed">
            Follow these step-by-step instructions to configure your marketplace partner accounts and connect your stores to this platform.
          </p>
        </div>

        {/* Shopee Guide */}
        <div className="bg-white border border-ink-200 rounded-2xl p-6 sm:p-8 shadow-xs space-y-6">
          <div className="flex items-center gap-3 border-b border-ink-100 pb-4">
            <span className="w-3 h-3 rounded-full bg-orange-500"></span>
            <h2 className="text-xl font-bold text-ink-950">Shopee Store Setup</h2>
          </div>

          <div className="space-y-6">
            {/* Step 1 */}
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <span className="w-6 h-6 rounded-full bg-orange-100 text-orange-800 text-xs font-bold flex items-center justify-center">
                  1
                </span>
                <h3 className="text-sm font-bold text-ink-900">
                  Register the Callback URL in Shopee Open Platform
                </h3>
              </div>
              <p className="text-xs text-ink-600 pl-8 leading-relaxed">
                Log into your <strong>Shopee Open Platform Console</strong>, navigate to <strong>App Management</strong>, and paste this exact URL into the <strong>Redirect URL</strong> field:
              </p>
              <div className="pl-8 pt-1">
                <div className="flex items-center gap-2 max-w-xl">
                  <input
                    type="text"
                    readOnly
                    value={shopeeCallback}
                    className="w-full bg-ink-50 border border-ink-200 rounded-lg px-3 py-2 text-xs font-mono text-ink-800 select-all"
                  />
                  <button
                    onClick={() => copyToClipboard(shopeeCallback, 'shopee')}
                    className="px-3 py-2 text-xs font-semibold bg-orange-600 hover:bg-orange-700 text-white rounded-lg transition shrink-0"
                  >
                    {copiedKey === 'shopee' ? 'Copied!' : 'Copy URL'}
                  </button>
                </div>
              </div>
            </div>

            {/* Step 2 */}
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <span className="w-6 h-6 rounded-full bg-orange-100 text-orange-800 text-xs font-bold flex items-center justify-center">
                  2
                </span>
                <h3 className="text-sm font-bold text-ink-900">
                  Save Credentials in System Settings
                </h3>
              </div>
              <p className="text-xs text-ink-600 pl-8 leading-relaxed">
                In this system, go to <strong>Settings → Shopee Credentials</strong>, enter your <strong>Partner ID</strong> and <strong>Partner Key</strong> from the Shopee console, and click <strong>Save</strong>.
              </p>
            </div>

            {/* Step 3 */}
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <span className="w-6 h-6 rounded-full bg-orange-100 text-orange-800 text-xs font-bold flex items-center justify-center">
                  3
                </span>
                <h3 className="text-sm font-bold text-ink-900">
                  Link Your Store
                </h3>
              </div>
              <p className="text-xs text-ink-600 pl-8 leading-relaxed">
                Go to the <strong>Dashboard</strong>, click <strong>[Link Store] → Shopee</strong>, log into your Shopee seller account, and click <strong>Authorize</strong>. Your store will connect automatically—no need to copy or paste any codes.
              </p>
            </div>
          </div>
        </div>

        {/* Lazada Guide */}
        <div className="bg-white border border-ink-200 rounded-2xl p-6 sm:p-8 shadow-xs space-y-6">
          <div className="flex items-center gap-3 border-b border-ink-100 pb-4">
            <span className="w-3 h-3 rounded-full bg-blue-600"></span>
            <h2 className="text-xl font-bold text-ink-950">Lazada Store Setup</h2>
          </div>

          <div className="space-y-6">
            {/* Step 1 */}
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <span className="w-6 h-6 rounded-full bg-blue-100 text-blue-800 text-xs font-bold flex items-center justify-center">
                  1
                </span>
                <h3 className="text-sm font-bold text-ink-900">
                  Register the Callback URL in Lazada Open Platform
                </h3>
              </div>
              <p className="text-xs text-ink-600 pl-8 leading-relaxed">
                Log into your <strong>Lazada Open Platform Console</strong>, go to <strong>App Overview / App Management</strong>, and paste this exact URL into the <strong>Callback URL</strong> field:
              </p>
              <div className="pl-8 pt-1">
                <div className="flex items-center gap-2 max-w-xl">
                  <input
                    type="text"
                    readOnly
                    value={lazadaCallback}
                    className="w-full bg-ink-50 border border-ink-200 rounded-lg px-3 py-2 text-xs font-mono text-ink-800 select-all"
                  />
                  <button
                    onClick={() => copyToClipboard(lazadaCallback, 'lazada')}
                    className="px-3 py-2 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition shrink-0"
                  >
                    {copiedKey === 'lazada' ? 'Copied!' : 'Copy URL'}
                  </button>
                </div>
              </div>
            </div>

            {/* Step 2 */}
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <span className="w-6 h-6 rounded-full bg-blue-100 text-blue-800 text-xs font-bold flex items-center justify-center">
                  2
                </span>
                <h3 className="text-sm font-bold text-ink-900">
                  Save Credentials in System Settings
                </h3>
              </div>
              <p className="text-xs text-ink-600 pl-8 leading-relaxed">
                In this system, go to <strong>Settings → Lazada Credentials</strong>, enter your <strong>App Key</strong> and <strong>App Secret</strong>, and click <strong>Save</strong>.
              </p>
            </div>

            {/* Step 3 */}
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <span className="w-6 h-6 rounded-full bg-blue-100 text-blue-800 text-xs font-bold flex items-center justify-center">
                  3
                </span>
                <h3 className="text-sm font-bold text-ink-900">
                  Link Your Store
                </h3>
              </div>
              <p className="text-xs text-ink-600 pl-8 leading-relaxed">
                Go to the <strong>Dashboard</strong>, click <strong>[Link Store] → Lazada</strong>, log into your Lazada seller account, and click <strong>Authorize</strong>. The store will connect automatically.
              </p>
            </div>
          </div>
        </div>

        {/* FAQ */}
        <div className="bg-white border border-ink-200 rounded-2xl p-6 sm:p-8 shadow-xs space-y-4">
          <h2 className="text-lg font-bold text-ink-950 border-b border-ink-100 pb-3">
            Frequently Asked Questions
          </h2>
          <div className="space-y-4 text-xs">
            <div>
              <p className="font-bold text-ink-900">Do I need to copy or enter any code after logging in?</p>
              <p className="text-ink-600 mt-0.5">
                No. After authorizing on Shopee or Lazada, your browser redirects directly back to this application, which connects the store automatically.
              </p>
            </div>
            <div>
              <p className="font-bold text-ink-900">What if I get a "Redirect URL Mismatch" error?</p>
              <p className="text-ink-600 mt-0.5">
                Ensure the URL registered in your marketplace developer console exactly matches the URL shown in Step 1 above, including <code className="bg-ink-50 px-1 py-0.5 rounded">https://</code>.
              </p>
            </div>
            <div>
              <p className="font-bold text-ink-900">How long does store authorization last?</p>
              <p className="text-ink-600 mt-0.5">
                Connections stay active automatically through background token refreshes. When a store reaches its annual authorization period (up to 365 days), simply click <strong>Reauthorize</strong> on the Dashboard.
              </p>
            </div>
          </div>
        </div>
      </div>
    </PageShell>
  );
}
