import { useEffect, useState } from 'react';
import { PageShell } from '../components/PageShell';
import Spinner from '../components/Spinner';
import { useToast } from '../context/ToastContext';
import {
  addWorkspaceMember,
  getWorkspace,
  getWorkspaceActivity,
  removeWorkspaceMember,
  type WorkspaceActivityEntry,
  type WorkspaceMemberRow,
  type WorkspaceResponse,
} from '../api/client';

import { QRCodeSVG } from 'qrcode.react';

export default function Team() {
  const { showToast } = useToast();
  const [data, setData] = useState<WorkspaceResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [newMemberName, setNewMemberName] = useState('');
  const [newMemberEmail, setNewMemberEmail] = useState('');
  const [newMemberPassword, setNewMemberPassword] = useState('');
  const [newMemberRole, setNewMemberRole] = useState<'owner' | 'member'>('member');
  const [saving, setSaving] = useState(false);
  const [activity, setActivity] = useState<WorkspaceActivityEntry[]>([]);
  const [activityPage, setActivityPage] = useState(1);
  const [activityLastPage, setActivityLastPage] = useState(1);
  const [activityLoading, setActivityLoading] = useState(false);

  const loadActivity = async (page = 1) => {
    setActivityLoading(true);
    try {
      const res = await getWorkspaceActivity(page);
      setActivity(res.data ?? []);
      setActivityPage(res.current_page ?? 1);
      setActivityLastPage(res.last_page ?? 1);
    } catch {
      setActivity([]);
    } finally {
      setActivityLoading(false);
    }
  };

  const load = async () => {
    setLoading(true);
    try {
      const res = await getWorkspace();
      setData(res);
      await loadActivity(1);
    } catch {
      showToast('Failed to load team.', 'error');
      setData(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void load();
  }, []);

  const handleAddMember = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newMemberEmail.trim() || !newMemberPassword.trim() || !newMemberName.trim()) {
      showToast('Please fill in Name, Email and Password.', 'error');
      return;
    }
    setSaving(true);
    try {
      await addWorkspaceMember({
        name: newMemberName.trim(),
        email: newMemberEmail.trim(),
        password: newMemberPassword.trim(),
        role: newMemberRole,
      });
      setNewMemberName('');
      setNewMemberEmail('');
      setNewMemberPassword('');
      setNewMemberRole('member');
      showToast('Team member added successfully!', 'success');
      await load();
    } catch (err: any) {
      showToast(err?.response?.data?.message || 'Failed to add member.', 'error');
    } finally {
      setSaving(false);
    }
  };

  const handleRemove = async (member: WorkspaceMemberRow) => {
    if (!confirm(`Remove ${member.email || member.name} from the workspace?`)) return;
    try {
      await removeWorkspaceMember(member.id);
      showToast('Member removed.', 'success');
      await load();
    } catch (err: any) {
      showToast(err?.response?.data?.message || 'Remove failed.', 'error');
    }
  };

  const isOwner = data?.workspace.is_owner === true;

  return (
    <PageShell>
      <div className="max-w-3xl space-y-8 animate-fade-in">
        <div>
          <h2 className="font-heading text-2xl font-bold text-ink-950">Team</h2>
          <p className="text-ink-400 text-sm mt-1">
            Share stores, orders, and stock with teammates. Owners manage invites and store linking.
          </p>
        </div>

        {loading ? (
          <div className="bg-white rounded-2xl border border-ink-100 p-12 flex justify-center">
            <Spinner />
          </div>
        ) : !data ? (
          <div className="bg-white rounded-2xl border border-ink-100 p-8 text-sm text-ink-500">
            Unable to load workspace.
          </div>
        ) : (
          <>
            <div className="bg-white rounded-2xl border border-ink-100 shadow-sm p-6 space-y-2">
              <p className="text-xs font-semibold uppercase tracking-wide text-ink-400">Workspace</p>
              <p className="font-heading text-lg font-semibold text-ink-950">{data.workspace.name}</p>
              <p className="text-sm text-ink-500">
                Your role:{' '}
                <span className="font-semibold text-ink-800 capitalize">
                  {data.workspace.role || 'unknown'}
                </span>
              </p>
            </div>

            {/* Mobile App Companion Setup Card */}
            <div className="bg-gradient-to-br from-ink-950 to-ink-900 rounded-2xl p-6 text-white shadow-lg space-y-5">
              <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                  <div className="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-clay-500/20 border border-clay-400/30 text-clay-300 text-xs font-medium mb-2">
                    <span className="w-1.5 h-1.5 rounded-full bg-clay-400 animate-pulse" />
                    Google Play Companion App
                  </div>
                  <h3 className="font-heading text-lg font-bold text-white">
                    Connect Staff & Mobile Devices
                  </h3>
                  <p className="text-sm text-ink-300 max-w-lg mt-1">
                    Staff members can download the official <strong>MyStore</strong> app from Google Play, scan this Store QR code to connect directly to this server, and log in with their email.
                  </p>
                </div>
                <div className="bg-white p-3 rounded-xl shadow-md shrink-0 flex flex-col items-center">
                  <QRCodeSVG
                    value={window.location.origin}
                    size={110}
                    level="M"
                    includeMargin={false}
                  />
                  <span className="text-[10px] font-semibold text-ink-600 mt-1 uppercase tracking-wider">
                    Scan Store QR
                  </span>
                </div>
              </div>

              <div className="pt-4 border-t border-ink-800/80 flex flex-wrap items-center justify-between gap-3 text-xs text-ink-300">
                <div className="flex items-center gap-2">
                  <span className="text-ink-400">Store Domain:</span>
                  <code className="px-2 py-0.5 rounded bg-ink-800 text-clay-300 font-mono text-xs">
                    {window.location.origin}
                  </code>
                </div>
                <div className="flex items-center gap-2">
                  <a
                    href="https://play.google.com/store/apps/details?id=com.melur.mystores"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-clay-300 hover:text-white underline font-medium"
                  >
                    1. Install on Play Store
                  </a>
                  <span>→</span>
                  <span>2. Scan QR</span>
                  <span>→</span>
                  <span>3. Log in</span>
                </div>
              </div>
            </div>

            <div className="bg-white rounded-2xl border border-ink-100 shadow-sm overflow-hidden">
              <div className="px-6 py-4 border-b border-ink-100">
                <h3 className="font-heading text-base font-semibold text-ink-950">Members</h3>
              </div>
              <ul className="divide-y divide-ink-50">
                {data.members.map((m) => (
                  <li key={m.id} className="px-6 py-4 flex items-center justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold text-ink-900">{m.name || 'User'}</p>
                      <p className="text-xs text-ink-400">{m.email}</p>
                    </div>
                    <div className="flex items-center gap-2">
                      <span className="text-xs font-semibold uppercase tracking-wide text-ink-500 px-2 py-1 rounded-lg bg-ink-50 border border-ink-100">
                        {m.role}
                      </span>
                      {isOwner && m.role !== 'owner' && (
                        <button
                          type="button"
                          onClick={() => handleRemove(m)}
                          className="text-xs font-semibold text-red-600 hover:text-red-800"
                        >
                          Remove
                        </button>
                      )}
                    </div>
                  </li>
                ))}
              </ul>
            </div>

            <div className="bg-white rounded-2xl border border-ink-100 shadow-sm overflow-hidden">
              <div className="px-6 py-4 border-b border-ink-100 flex items-center justify-between gap-3">
                <div>
                  <h3 className="font-heading text-base font-semibold text-ink-950">Activity</h3>
                  <p className="text-xs text-ink-400 mt-0.5">
                    {isOwner
                      ? 'All workspace actions from owners and members.'
                      : 'Your recent actions in this workspace.'}
                  </p>
                </div>
                <button
                  type="button"
                  onClick={() => loadActivity(activityPage)}
                  disabled={activityLoading}
                  className="text-xs font-semibold text-clay-600 hover:text-clay-800 disabled:opacity-50"
                >
                  Refresh
                </button>
              </div>
              {activityLoading && activity.length === 0 ? (
                <div className="p-8 flex justify-center">
                  <Spinner />
                </div>
              ) : activity.length === 0 ? (
                <div className="p-8 text-sm text-ink-400 text-center">No activity yet</div>
              ) : (
                <ul className="divide-y divide-ink-50">
                  {activity.map((entry) => (
                    <li key={entry.id} className="px-6 py-4 space-y-1">
                      <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <p className="text-sm font-medium text-ink-900">{entry.summary}</p>
                        <time className="text-xs text-ink-400 shrink-0">
                          {new Date(entry.created_at).toLocaleString()}
                        </time>
                      </div>
                      <p className="text-xs text-ink-500">
                        {entry.user?.name || 'User'}
                        {entry.user?.email ? ` · ${entry.user.email}` : ''}
                      </p>
                    </li>
                  ))}
                </ul>
              )}
              {activityLastPage > 1 && (
                <div className="px-6 py-3 border-t border-ink-100 flex items-center justify-between">
                  <button
                    type="button"
                    disabled={activityPage <= 1 || activityLoading}
                    onClick={() => loadActivity(activityPage - 1)}
                    className="text-xs font-semibold text-ink-600 hover:text-ink-900 disabled:opacity-40"
                  >
                    Previous
                  </button>
                  <span className="text-xs text-ink-400">
                    Page {activityPage} of {activityLastPage}
                  </span>
                  <button
                    type="button"
                    disabled={activityPage >= activityLastPage || activityLoading}
                    onClick={() => loadActivity(activityPage + 1)}
                    className="text-xs font-semibold text-ink-600 hover:text-ink-900 disabled:opacity-40"
                  >
                    Next
                  </button>
                </div>
              )}
            </div>

            {isOwner && (
              <div className="bg-white rounded-2xl border border-ink-100 shadow-sm p-6 space-y-5">
                <div>
                  <h3 className="font-heading text-base font-semibold text-ink-950">Add Team Member</h3>
                  <p className="text-xs text-ink-400 mt-0.5">
                    Create a direct login for staff or cashiers. They can log in immediately on the web or mobile app.
                  </p>
                </div>

                <form onSubmit={handleAddMember} className="space-y-4">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-semibold text-ink-700 mb-1">
                        Full Name
                      </label>
                      <input
                        type="text"
                        required
                        value={newMemberName}
                        onChange={(e) => setNewMemberName(e.target.value)}
                        placeholder="e.g. Ahmad Cashier"
                        className="w-full px-4 py-2.5 rounded-xl border border-ink-200 text-sm focus:outline-none focus:ring-2 focus:ring-clay-400/40"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-ink-700 mb-1">
                        Email Address
                      </label>
                      <input
                        type="email"
                        required
                        value={newMemberEmail}
                        onChange={(e) => setNewMemberEmail(e.target.value)}
                        placeholder="ahmad@store.com"
                        className="w-full px-4 py-2.5 rounded-xl border border-ink-200 text-sm focus:outline-none focus:ring-2 focus:ring-clay-400/40"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-semibold text-ink-700 mb-1">
                        Password
                      </label>
                      <input
                        type="password"
                        required
                        minLength={6}
                        value={newMemberPassword}
                        onChange={(e) => setNewMemberPassword(e.target.value)}
                        placeholder="Minimum 6 characters"
                        className="w-full px-4 py-2.5 rounded-xl border border-ink-200 text-sm focus:outline-none focus:ring-2 focus:ring-clay-400/40"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-ink-700 mb-1">
                        Role
                      </label>
                      <select
                        value={newMemberRole}
                        onChange={(e) => setNewMemberRole(e.target.value as 'owner' | 'member')}
                        className="w-full px-4 py-2.5 rounded-xl border border-ink-200 text-sm focus:outline-none focus:ring-2 focus:ring-clay-400/40 bg-white"
                      >
                        <option value="member">Staff / Member (Orders, Pick List, Stock)</option>
                        <option value="owner">Owner (Full Admin Access)</option>
                      </select>
                    </div>
                  </div>

                  <div className="pt-2 flex justify-end">
                    <button
                      type="submit"
                      disabled={saving}
                      className="px-5 py-2.5 text-sm font-semibold bg-ink-950 text-white rounded-xl hover:bg-ink-800 disabled:opacity-50 shadow-sm"
                    >
                      {saving ? 'Adding Member…' : 'Add Member'}
                    </button>
                  </div>
                </form>
              </div>
            )}
          </>
        )}
      </div>
    </PageShell>
  );
}
