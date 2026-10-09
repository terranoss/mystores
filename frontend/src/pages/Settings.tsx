import { useState, useEffect, useCallback, type FormEvent } from 'react';
import { isAxiosError } from 'axios';
import { PageShell } from '../components/PageShell';
import Spinner from '../components/Spinner';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';
import {
  getWorkspace,
  getWorkspaceCredentials,
  updateWorkspaceCredentials,
  deleteWorkspaceCredentials,
  changePassword,
} from '../api/client';

/** Password input with show/hide toggle (same SVGs as Login.tsx). */
function PasswordField({
  id,
  label,
  value,
  onChange,
  placeholder,
  shown,
  onToggleShown,
  error,
  disabled,
}: {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  placeholder: string;
  shown: boolean;
  onToggleShown: () => void;
  error?: string[];
  disabled?: boolean;
}) {
  return (
    <div>
      <label htmlFor={id} className="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1.5">
        {label}
      </label>
      <div className="relative">
        <input
          id={id}
          type={shown ? 'text' : 'password'}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder}
          className="w-full max-w-md px-3 py-2 pr-11 border border-ink-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-clay-500"
          disabled={disabled}
        />
        <button
          type="button"
          onClick={onToggleShown}
          className="absolute inset-y-0 right-0 pr-3.5 flex items-center text-ink-400 hover:text-ink-600 transition focus:outline-none"
          tabIndex={-1}
          aria-label={shown ? 'Hide password' : 'Show password'}
        >
          {shown ? (
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.8}
                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"
              />
            </svg>
          ) : (
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.8}
                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
              />
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.8}
                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
              />
            </svg>
          )}
        </button>
      </div>
      {error && error.length > 0 && (
        <p className="mt-1.5 text-sm text-red-500 font-medium">{error[0]}</p>
      )}
    </div>
  );
}

export default function Settings() {
  const { user } = useAuth();
  const [isOwner, setIsOwner] = useState(false);
  const [loading, setLoading] = useState(true);
  const [shopeeCredentials, setShopeeCredentials] = useState({
    partnerId: '',
    partnerKey: '',
    pushPartnerKey: '',
    redirectUrl: '',
    hasCredentials: false,
  });
  const [lazadaCredentials, setLazadaCredentials] = useState({
    appKey: '',
    appSecret: '',
    redirectUrl: '',
    hasCredentials: false,
  });
  const [shopeeLoading, setShopeeLoading] = useState(false);
  const [lazadaLoading, setLazadaLoading] = useState(false);
  const [passwordLoading, setPasswordLoading] = useState(false);
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [showCurrentPassword, setShowCurrentPassword] = useState(false);
  const [showNewPassword, setShowNewPassword] = useState(false);
  const [showConfirmation, setShowConfirmation] = useState(false);
  const [passwordErrors, setPasswordErrors] = useState<Record<string, string[]>>({});
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
        redirectUrl: credentials?.shopee_redirect_url || '',
        hasCredentials: !!credentials?.id,
      });

      setLazadaCredentials({
        appKey: credentials?.app_key || '',
        appSecret: credentials?.app_secret ? '[SET]' : '',
        redirectUrl: credentials?.lazada_redirect_url || '',
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
      const redirectUrl = formData.get('shopeeRedirectUrl')?.toString().trim();

      await updateWorkspaceCredentials({
        partner_id: partnerId || undefined,
        partner_key: partnerKey || undefined,
        push_partner_key: pushPartnerKey || undefined,
        shopee_redirect_url: redirectUrl || undefined,
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
      const redirectUrl = formData.get('lazadaRedirectUrl')?.toString().trim();

      await updateWorkspaceCredentials({
        app_key: appKey || undefined,
        app_secret: appSecret || undefined,
        lazada_redirect_url: redirectUrl || undefined,
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

  const handlePasswordSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setPasswordErrors({});

    const errs: Record<string, string[]> = {};
    if (!currentPassword) {
      errs.current_password = ['Current password is required.'];
    }
    if (newPassword.length < 8) {
      errs.password = ['New password must be at least 8 characters.'];
    } else if (newPassword === currentPassword) {
      errs.password = ['New password must be different from the current password.'];
    }
    if (newPassword !== passwordConfirmation) {
      errs.password_confirmation = ['Password confirmation does not match.'];
    }
    if (Object.keys(errs).length > 0) {
      setPasswordErrors(errs);
      return;
    }

    setPasswordLoading(true);
    try {
      await changePassword({
        current_password: currentPassword,
        password: newPassword,
        password_confirmation: passwordConfirmation,
      });
      showToast('Password updated successfully', 'success');
      setCurrentPassword('');
      setNewPassword('');
      setPasswordConfirmation('');
    } catch (error) {
      if (isAxiosError(error) && error.response?.status === 422 && error.response.data?.errors) {
        setPasswordErrors(error.response.data.errors as Record<string, string[]>);
      } else if (isAxiosError(error) && error.response?.status === 429) {
        showToast('Too many attempts. Please wait a minute and try again.', 'error');
      } else {
        console.error('Failed to update password:', error);
        showToast('Failed to update password', 'error');
      }
    } finally {
      setPasswordLoading(false);
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

  return (
    <PageShell>
      <div className="p-6 max-w-4xl mx-auto space-y-8">
        <div>
          <h1 className="text-2xl font-bold text-ink-900 tracking-tight">Settings</h1>
          <p className="text-sm text-ink-500 mt-1">
            Manage your login password and workspace platform credentials.
          </p>
        </div>

        {/* Change password — available to every user */}
        <div className="bg-white border border-ink-200 rounded-2xl p-6 shadow-sm">
          <div className="mb-4">
            <h2 className="text-lg font-bold text-ink-900">Change Password</h2>
            <p className="text-xs text-ink-400">
              Update your login password. Other signed-in sessions will be logged out.
            </p>
          </div>

          <form onSubmit={handlePasswordSubmit} className="space-y-4">
            <PasswordField
              id="currentPassword"
              label="Current Password"
              value={currentPassword}
              onChange={setCurrentPassword}
              placeholder="••••••••"
              shown={showCurrentPassword}
              onToggleShown={() => setShowCurrentPassword((v) => !v)}
              error={passwordErrors.current_password}
              disabled={passwordLoading}
            />
            <PasswordField
              id="newPassword"
              label="New Password"
              value={newPassword}
              onChange={setNewPassword}
              placeholder="At least 8 characters"
              shown={showNewPassword}
              onToggleShown={() => setShowNewPassword((v) => !v)}
              error={passwordErrors.password}
              disabled={passwordLoading}
            />
            <PasswordField
              id="passwordConfirmation"
              label="Confirm New Password"
              value={passwordConfirmation}
              onChange={setPasswordConfirmation}
              placeholder="Repeat your new password"
              shown={showConfirmation}
              onToggleShown={() => setShowConfirmation((v) => !v)}
              error={passwordErrors.password_confirmation}
              disabled={passwordLoading}
            />

            <div className="pt-2">
              <button
                type="submit"
                disabled={passwordLoading}
                className="px-4 py-2 bg-clay-600 hover:bg-clay-700 text-white rounded-xl text-sm font-semibold transition-colors disabled:opacity-50"
              >
                {passwordLoading ? 'Updating...' : 'Update Password'}
              </button>
            </div>
          </form>
        </div>

        {isOwner && (
        <div>
          <h1 className="text-2xl font-bold text-ink-900 tracking-tight">Workspace Credentials</h1>
          <p className="text-sm text-ink-500 mt-1">
            Configure platform API credentials for your workspace. These credentials are used by team members to connect and sync stores.
          </p>
        </div>
        )}

        {isOwner && (
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

              <div>
                <label className="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1.5">
                  Redirect URL
                </label>
                <input
                  type="url"
                  name="shopeeRedirectUrl"
                  value={shopeeCredentials.redirectUrl}
                  onChange={(e) => setShopeeCredentials((prev) => ({ ...prev, redirectUrl: e.target.value }))}
                  placeholder="https://your-domain.com/api/shopee/callback"
                  className="w-full max-w-md px-3 py-2 border border-ink-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-clay-500"
                  disabled={shopeeLoading}
                />
                <p className="text-xs text-ink-500 mt-1 max-w-md">
                  Must exactly match the <strong>Redirect URL Domain</strong> registered in your Shopee Open Platform Console. Default endpoint is <code className="bg-ink-100 px-1 py-0.5 rounded text-xs">/api/shopee/callback</code>.
                </p>
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

              <div>
                <label className="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1.5">
                  Redirect URL
                </label>
                <input
                  type="url"
                  name="lazadaRedirectUrl"
                  value={lazadaCredentials.redirectUrl}
                  onChange={(e) => setLazadaCredentials((prev) => ({ ...prev, redirectUrl: e.target.value }))}
                  placeholder="https://your-domain.com/api/lazada/callback"
                  className="w-full max-w-md px-3 py-2 border border-ink-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-clay-500"
                  disabled={lazadaLoading}
                />
                <p className="text-xs text-ink-500 mt-1 max-w-md">
                  Must match the Callback URL registered in your Lazada Open Platform Console. Default endpoint is <code className="bg-ink-100 px-1 py-0.5 rounded text-xs">/api/lazada/callback</code>.
                </p>
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
        )}
        {!isOwner && (
          <div className="bg-amber-500/10 border border-amber-500/20 text-amber-600 rounded-xl p-4 text-sm font-medium">
            Only the workspace owner can view and edit platform credentials.
          </div>
        )}
      </div>
    </PageShell>
  );
}