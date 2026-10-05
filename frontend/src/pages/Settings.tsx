import React, { useState, useCallback } from 'react';
import { useWorkspace } from '../contexts/workspaceContext';
import {
  getWorkspaceCredentials,
  updateWorkspaceCredentials,
  deleteWorkspaceCredentials,
} from '../api/client';
import { useToast } from '../contexts/toastContext';

export default function Settings() {
  const { user } = useWorkspace();
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
      // Check if user is owner by fetching workspace info
      // Note: We'll rely on the backend to enforce ownership, but we can check role from context
      // For now, we'll determine ownership by checking if user_id matches workspace owner
      // This requires updating the workspace context or making an additional API call
      // Let's check the user's role from the workspace context (assuming it's available)
      // If not available in context, we'll need to fetch it
      
      // For now, let's fetch workspace to check ownership
      const workspaceRes = await api.getWorkspace();
      setIsOwner(workspaceRes.workspace.is_owner);

      const credentialsRes = await getWorkspaceCredentials();
      const credentials = credentialsRes;

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
      showToast('Error', 'Failed to load settings', 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast, user]);

  const handleShopeeSubmit = async (e) => {
    e.preventDefault();
    setShopeeLoading(true);
    try {
      const formData = new FormData(e.target);
      const data = Object.fromEntries(formData.entries());
      
      await updateWorkspaceCredentials({
        partner_id: data.partnerId || undefined,
        partner_key: data.partnerKey || undefined,
        push_partner_key: data.pushPartnerKey || undefined,
      });

      showToast('Success', 'Shopee credentials updated', 'success');
      loadSettings(); // Reload to refresh state
    } catch (error) {
      console.error('Failed to update Shopee credentials:', error);
      showToast('Error', 'Failed to update Shopee credentials', 'error');
    } finally {
      setShopeeLoading(false);
    }
  };

  const handleLazadaSubmit = async (e) => {
    e.preventDefault();
    setLazadaLoading(true);
    try {
      const formData = new FormData(e.target);
      const data = Object.fromEntries(formData.entries());
      
      await updateWorkspaceCredentials({
        app_key: data.appKey || undefined,
        app_secret: data.appSecret || undefined,
      });

      showToast('Success', 'Lazada credentials updated', 'success');
      loadSettings(); // Reload to refresh state
    } catch (error) {
      console.error('Failed to update Lazada credentials:', error);
      showToast('Error', 'Failed to update Lazada credentials', 'error');
    } finally {
      setLazadaLoading(false);
    }
  };

  const handleClearCredentials = async (platform) => {
    if (!window.confirm(`Are you sure you want to remove ${platform} credentials? This will disconnect all stores.`)) {
      return;
    }

    try {
      await deleteWorkspaceCredentials();
      showToast('Success', `${platform} credentials removed`, 'success');
      loadSettings(); // Reload to refresh state
    } catch (error) {
      console.error(`Failed to clear ${platform} credentials:`, error);
      showToast('Error', `Failed to clear ${platform} credentials`, 'error');
    }
  };

  if (loading) {
    return <div className="p-6">Loading settings...</div>;
  }

  if (!isOwner) {
    return (
      <div className="p-6">
        <h1 className="text-2xl font-bold mb-4">Settings</h1>
        <div className="alert alert-warning">
          Only the workspace owner can view and edit credentials.
        </div>
      </div>
    );
  }

  return (
    <div className="p-6">
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold">Workspace Credentials</h1>
          <p className="text-sm text-ink-500">
            Configure platform API credentials for your workspace. These credentials
            are used by all team members to connect stores.
          </p>
        </div>

        <div className="space-y-4">
          <div className="border-base-200 rounded-lg p-4">
            <h2 className="font-bold mb-3">Shopee Partner Credentials</h2>
            <form onSubmit={handleShopeeSubmit} className="space-y-3">
              <div className="space-y-2">
                <label className="label">
                  <span className="label-text">Partner ID</span>
                </label>
                <input
                  type="text"
                  name="partnerId"
                  value={shopeeCredentials.partnerId}
                  onChange={(e) => setShopeeCredentials(prev => ({ ...prev, partnerId: e.target.value }))}
                  placeholder="Enter your Shopee Partner ID"
                  className="input input-bordered w-full max-w-xs"
                  disabled={shopeeLoading}
                />
              </div>

              <div className="space-y-2">
                <label className="label">
                  <span className="label-text">Partner Key</span>
                </label>
                <input
                  type="password"
                  name="partnerKey"
                  value={shopeeCredentials.hasCredentials ? '' : ''}
                  onChange={(e) => setShopeeCredentials(prev => ({ ...prev, partnerKey: e.target.value }))}
                  placeholder={shopeeCredentials.hasCredentials ? '[hidden]' : 'Enter your Shopee Partner Key'}
                  className="input input-bordered w-full max-w-xs"
                  disabled={shopeeLoading}
                />
                {shopeeCredentials.hasCredentials && (
                  <button
                    type="button"
                    onClick={(e) => {
                      e.preventDefault();
                      setShopeeCredentials(prev => ({ ...prev, partnerKey: '' }));
                    }}
                    className="btn btn-sm btn-ghost"
                  >
                    Change
                  </button>
                )}
              </div>

              <div className="space-y-2">
                <label className="label">
                  <span className="label-text">Push Partner Key</span>
                </label>
                <input
                  type="password"
                  name="pushPartnerKey"
                  value={shopeeCredentials.hasCredentials ? '' : ''}
                  onChange={(e) => setShopeeCredentials(prev => ({ ...prev, pushPartnerKey: e.target.value }))}
                  placeholder={shopeeCredentials.hasCredentials ? '[hidden]' : 'Enter your Shopee Push Partner Key'}
                  className="input input-bordered w-full max-w-xs"
                  disabled={shopeeLoading}
                />
                {shopeeCredentials.hasCredentials && (
                  <button
                    type="button"
                    onClick={(e) => {
                      e.preventDefault();
                      setShopeeCredentials(prev => ({ ...prev, pushPartnerKey: '' }));
                    }}
                    className="btn btn-sm btn-ghost"
                  >
                    Change
                  </button>
                )}
              </div>

              {shopeeCredentials.hasCredentials ? (
                <button
                  type="button"
                  onClick={(e) => {
                    e.preventDefault();
                    handleClearCredentials('Shopee');
                  }}
                  className="btn btn-sm btn-error"
                >
                  Remove Shopee Credentials
                </button>
              ) : (
                <button
                  type="submit"
                  disabled={shopeeLoading}
                  className="btn btn-primary"
                >
                  {shopeeLoading ? 'Saving...' : 'Save Shopee Credentials'}
                </button>
              )}
            </form>
          </div>

          <div className="border-base-200 rounded-lg p-4">
            <h2 className="font-bold mb-3">Lazada App Credentials</h2>
            <form onSubmit={handleLazadaSubmit} className="space-y-3">
              <div className="space-y-2">
                <label className="label">
                  <span className="label-text">App Key</span>
                </label>
                <input
                  type="text"
                  name="appKey"
                  value={lazadaCredentials.appKey}
                  onChange={(e) => setLazadaCredentials(prev => ({ ...prev, appKey: e.target.value }))}
                  placeholder="Enter your Lazada App Key"
                  className="input input-bordered w-full max-w-xs"
                  disabled={lazadaLoading}
                />
              </div>

              <div className="space-y-2">
                <label className="label">
                  <span className="label-text">App Secret</span>
                </label>
                <input
                  type="password"
                  name="appSecret"
                  value={lazadaCredentials.hasCredentials ? '' : ''}
                  onChange={(e) => setLazadaCredentials(prev => ({ ...prev, appSecret: e.target.value }))}
                  placeholder={lazadaCredentials.hasCredentials ? '[hidden]' : 'Enter your Lazada App Secret'}
                  className="input input-bordered w-full max-w-xs"
                  disabled={lazadaLoading}
                />
                {lazadaCredentials.hasCredentials && (
                  <button
                    type="button"
                    onClick={(e) => {
                      e.preventDefault();
                      setLazadaCredentials(prev => ({ ...prev, appSecret: '' }));
                    }}
                    className="btn btn-sm btn-ghost"
                  >
                    Change
                  </button>
                )}
              </div>

              {lazadaCredentials.hasCredentials ? (
                <button
                  type="button"
                  onClick={(e) => {
                    e.preventDefault();
                    handleClearCredentials('Lazada');
                  }}
                  className="btn btn-sm btn-error"
                >
                  Remove Lazada Credentials
                </button>
              ) : (
                <button
                  type="submit"
                  disabled={lazadaLoading}
                  className="btn btn-primary"
                >
                  {lazadaLoading ? 'Saving...' : 'Save Lazada Credentials'}
                </button>
              )}
            </form>
          </div>
        </div>
      </div>
    </div>
  );
}