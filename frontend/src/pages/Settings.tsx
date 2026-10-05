import { useState, useEffect, useCallback, type FormEvent } from 'react';
import { PageShell } from '../components/PageShell';
import Spinner from '../components/Spinner';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';
import {
  getWorkspace,
  getWorkspaceCredentials,
  updateWorkspaceCredentials,
  deleteWorkspaceCredentials,
} from '../api/client';

export default function Settings() {
  const { user } = useAuth();
  const [isOwner, setIsOwner] = useState(false);
  const [loading, setLoading] = useState(true);
  const [shopeeCredentials, setShopeeCredentials] = useState({
    partnerId: '',
    partnerKey: '',
    pushPartnerKey: '',
    hasCredentials: false,
  });
  const [lazadaCredentials, setLazadaCredentials] = useState({
    appKey: '',
    appSecret: '',
    hasCredentials: false,
  });
  const [shopeeLoading, setShopeeLoading] = useState(false);
  const [lazadaLoading, setLazadaLoading] = useState(false);
  const { showToast } = useToast();

  const loadSettings = useCallback(async () => {
    try {
      setLoading(true);
      const workspaceRes = await getWorkspace();
      setIsOwner(workspaceRes.workspace.is_owner || user?.is_workspace_owner || user?.workspace_role === 'owner');

      const credentials = await getWorkspaceCredentials();

      setShopeeCredentials({
        partnerId: credentials?.partner_id || '',
        partnerKey: credentials?.partner_key ? '[SET]' : '',
        pushPartnerKey: credentials?.push_partner_key ? '[SET]' : '',
        hasCredentials: !!credentials?.id,
      });

      setLazadaCredentials({
        appKey: credentials?.app_key || '',
        appSecret: credentials?.app_secret ? '[SET]' : '',
        hasCredentials: !!credentials?.id,
      });
    } catch (error) {
      console.error('Failed to load settings:', error);
      showToast('Failed to load workspace settings', 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast, user]);

  useEffect(() => {
    void loadSettings();
  }, [loadSettings]);

  const handleShopeeSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setShopeeLoading(true);
    try {
      const formData = new FormData(e.currentTarget);
      const partnerId = formData.get('partnerId')?.toString().trim();
      const partnerKey = formData.get('partnerKey')?.toString().trim();
      const pushPartnerKey = formData.get('pushPartnerKey')?.toString().trim();

      await updateWorkspaceCredentials({
        partner_id: partnerId || undefined,
        partner_key: partnerKey || undefined,
        push_partner_key: pushPartnerKey || undefined,
      });

      showToast('Shopee credentials updated successfully', 'success');
      await loadSettings();
    } catch (error) {
      console.error('Failed to update Shopee credentials:', error);
      showToast('Failed to update Shopee credentials', 'error');
    } finally {
      setShopeeLoading(false);
    }
  };

  const handleLazadaSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setLazadaLoading(true);
    try {
      const formData = new FormData(e.currentTarget);
      const appKey = formData.get('appKey')?.toString().trim();
      const appSecret = formData.get('appSecret')?.toString().trim();

      await updateWorkspaceCredentials({
        app_key: appKey || undefined,
        app_secret: appSecret || undefined,
      });

      showToast('Lazada credentials updated successfully', 'success');
      await loadSettings();
    } catch (error) {
      console.error('Failed to update Lazada credentials:', error);
      showToast('Failed to update Lazada credentials', 'error');
    } finally {
      setLazadaLoading(false);
    }
  };

  const handleClearCredentials = async (platform: string) => {
    if (!window.confirm(`Are you sure you want to remove ${platform} credentials? This may affect store syncing.`)) {
      return;
    }

    try {
      await deleteWorkspaceCredentials();
      showToast(`${platform} credentials removed`, 'success');
      await loadSettings();
    } catch (error) {
      console.error(`Failed to clear ${platform} credentials:`, error);
      showToast(`Failed to clear ${platform} credentials`, 'error');
    }
  };

  if (loading) {
    return (
      <PageShell>
        <div className="flex justify-center items-center py-20">
          <Spinner />
        </div>
      </PageShell>
    );
  }

  if (!isOwner) {
    return (
      <PageShell>
        <div className="p-6">
          <div className="bg-amber-500/10 border border-amber-500/20 text-amber-600 rounded-xl p-4 text-sm font-medium">
            Only the workspace owner can view and edit platform credentials.
          </div>
        </div>
      </PageShell>
    );
  }

  return (
    <PageShell>
      <div className="p-6 max-w-4xl mx-auto space-y-8">
        <div>
          <h1 className="text-2xl font-bold text-ink-900 tracking-tight">Workspace Credentials</h1>
          <p className="text-sm text-ink-500 mt-1">
            Configure platform API credentials for your workspace. These credentials are used by team members to connect and sync stores.
          </p>
        </div>

        <div className="space-y-6">
          {/* Shopee */}
          <div className="bg-white border border-ink-200 rounded-2xl p-6 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <h2 className="text-lg font-bold text-ink-900">Shopee Partner Credentials</h2>
                <p className="text-xs text-ink-400">Partner ID, Partner Key, and Push Notification credentials</p>
              </div>
              {shopeeCredentials.hasCredentials && (
                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                  Configured
                </span>
              )}
            </div>

            <form onSubmit={handleShopeeSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1.5">
                  Partner ID
                </label>
                <input
                  type="text"
                  name="partnerId"
                  value={shopeeCredentials.partnerId}
                  onChange={(e) => setShopeeCredentials((prev) => ({ ...prev, partnerId: e.target.value }))}
                  placeholder="Enter Shopee Partner ID"
                  className="w-full max-w-md px-3 py-2 border border-ink-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-clay-500"
                  disabled={shopeeLoading}
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1.5">
                  Partner Key
                </label>
                <input
                  type="password"
                  name="partnerKey"
                  value={shopeeCredentials.partnerKey}
                  onChange={(e) => setShopeeCredentials((prev) => ({ ...prev, partnerKey: e.target.value }))}
                  placeholder={shopeeCredentials.hasCredentials ? '•••••••• (configured)' : 'Enter Shopee Partner Key'}
                  className="w-full max-w-md px-3 py-2 border border-ink-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-clay-500"
                  disabled={shopeeLoading}
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1.5">
                  Push Partner Key
                </label>
                <input
                  type="password"
                  name="pushPartnerKey"
                  value={shopeeCredentials.pushPartnerKey}
                  onChange={(e) => setShopeeCredentials((prev) => ({ ...prev, pushPartnerKey: e.target.value }))}
                  placeholder={shopeeCredentials.hasCredentials ? '•••••••• (configured)' : 'Enter Push Partner Key'}
                  className="w-full max-w-md px-3 py-2 border border-ink-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-clay-500"
                  disabled={shopeeLoading}
                />
              </div>

              <div className="flex items-center gap-3 pt-2">
                <button
                  type="submit"
                  disabled={shopeeLoading}
                  className="px-4 py-2 bg-clay-600 hover:bg-clay-700 text-white rounded-xl text-sm font-semibold transition-colors disabled:opacity-50"
                >
                  {shopeeLoading ? 'Saving...' : 'Save Shopee Credentials'}
                </button>

                {shopeeCredentials.hasCredentials && (
                  <button
                    type="button"
                    onClick={() => handleClearCredentials('Shopee')}
                    className="px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl text-sm font-semibold transition-colors"
                  >
                    Remove
                  </button>
                )}
              </div>
            </form>
          </div>

          {/* Lazada */}
          <div className="bg-white border border-ink-200 rounded-2xl p-6 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <h2 className="text-lg font-bold text-ink-900">Lazada App Credentials</h2>
                <p className="text-xs text-ink-400">Open Platform App Key & Secret</p>
              </div>
              {lazadaCredentials.hasCredentials && (
                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                  Configured
                </span>
              )}
            </div>

            <form onSubmit={handleLazadaSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1.5">
                  App Key
                </label>
                <input
                  type="text"
                  name="appKey"
                  value={lazadaCredentials.appKey}
                  onChange={(e) => setLazadaCredentials((prev) => ({ ...prev, appKey: e.target.value }))}
                  placeholder="Enter Lazada App Key"
                  className="w-full max-w-md px-3 py-2 border border-ink-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-clay-500"
                  disabled={lazadaLoading}
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1.5">
                  App Secret
                </label>
                <input
                  type="password"
                  name="appSecret"
                  value={lazadaCredentials.appSecret}
                  onChange={(e) => setLazadaCredentials((prev) => ({ ...prev, appSecret: e.target.value }))}
                  placeholder={lazadaCredentials.hasCredentials ? '•••••••• (configured)' : 'Enter Lazada App Secret'}
                  className="w-full max-w-md px-3 py-2 border border-ink-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-clay-500"
                  disabled={lazadaLoading}
                />
              </div>

              <div className="flex items-center gap-3 pt-2">
                <button
                  type="submit"
                  disabled={lazadaLoading}
                  className="px-4 py-2 bg-clay-600 hover:bg-clay-700 text-white rounded-xl text-sm font-semibold transition-colors disabled:opacity-50"
                >
                  {lazadaLoading ? 'Saving...' : 'Save Lazada Credentials'}
                </button>

                {lazadaCredentials.hasCredentials && (
                  <button
                    type="button"
                    onClick={() => handleClearCredentials('Lazada')}
                    className="px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl text-sm font-semibold transition-colors"
                  >
                    Remove
                  </button>
                )}
              </div>
            </form>
          </div>
        </div>
      </div>
    </PageShell>
  );
}