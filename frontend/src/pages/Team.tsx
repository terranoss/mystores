import { useEffect, useState } from 'react';
import { PageShell } from '../components/PageShell';
import Spinner from '../components/Spinner';
import { useToast } from '../context/ToastContext';
import {
  acceptWorkspaceInvite,
  getWorkspace,
  getWorkspaceActivity,
  inviteWorkspaceMember,
  removeWorkspaceMember,
  type WorkspaceActivityEntry,
  type WorkspaceInviteRow,
  type WorkspaceMemberRow,
  type WorkspaceResponse,
} from '../api/client';

export default function Team() {
  const { showToast } = useToast();
  const [data, setData] = useState<WorkspaceResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [email, setEmail] = useState('');
  const [inviteToken, setInviteToken] = useState<string | null>(null);
  const [acceptToken, setAcceptToken] = useState('');
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

  const handleInvite = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email.trim()) return;
    setSaving(true);
    setInviteToken(null);
    try {
      const res = await inviteWorkspaceMember(email.trim());
      setInviteToken(res.invite.token);
      setEmail('');
      showToast('Invite created. Share the token with your teammate.', 'success');
      await load();
    } catch (err: any) {
      showToast(err?.response?.data?.message || 'Invite failed.', 'error');
    } finally {
      setSaving(false);
    }
  };

  const handleAccept = async (e: React.FormEvent) => {
    e.preventDefault();
    if (acceptToken.trim().length !== 64) {
      showToast('Paste the full 64-character invite token.', 'error');
      return;
    }
    setSaving(true);
    try {
      await acceptWorkspaceInvite(acceptToken.trim());
      setAcceptToken('');
      showToast('Joined workspace.', 'success');
      await load();
    } catch (err: any) {
      showToast(err?.response?.data?.message || 'Could not accept invite.', 'error');
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
              <div className="bg-white rounded-2xl border border-ink-100 shadow-sm p-6 space-y-4">
                <h3 className="font-heading text-base font-semibold text-ink-950">Invite member</h3>
                <form onSubmit={handleInvite} className="flex flex-col sm:flex-row gap-3">
                  <input
                    type="email"
                    required
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="teammate@email.com"
                    className="flex-1 px-4 py-2.5 rounded-xl border border-ink-200 text-sm focus:outline-none focus:ring-2 focus:ring-clay-400/40"
                  />
                  <button
                    type="submit"
                    disabled={saving}
                    className="px-4 py-2.5 text-sm font-semibold bg-ink-950 text-white rounded-xl hover:bg-ink-800 disabled:opacity-50"
                  >
                    {saving ? 'Creating…' : 'Create invite'}
                  </button>
                </form>
                {inviteToken && (
                  <div className="p-3 rounded-xl bg-ink-50 border border-ink-100 space-y-2">
                    <p className="text-xs font-medium text-ink-600">
                      Invite token (share with teammate after they log in):
                    </p>
                    <code className="block text-xs break-all font-mono text-ink-800">{inviteToken}</code>
                  </div>
                )}
                {data.pending_invites.length > 0 && (
                  <div className="space-y-2">
                    <p className="text-xs font-semibold text-ink-500 uppercase tracking-wide">Pending</p>
                    {data.pending_invites.map((inv: WorkspaceInviteRow) => (
                      <p key={inv.id} className="text-sm text-ink-600">
                        {inv.email} · expires {new Date(inv.expires_at).toLocaleDateString()}
                      </p>
                    ))}
                  </div>
                )}
              </div>
            )}

            <div className="bg-white rounded-2xl border border-ink-100 shadow-sm p-6 space-y-4">
              <h3 className="font-heading text-base font-semibold text-ink-950">Accept an invite</h3>
              <p className="text-sm text-ink-500">
                Log in with the invited email, then paste the invite token from the owner.
              </p>
              <form onSubmit={handleAccept} className="flex flex-col sm:flex-row gap-3">
                <input
                  type="text"
                  value={acceptToken}
                  onChange={(e) => setAcceptToken(e.target.value)}
                  placeholder="64-character invite token"
                  className="flex-1 px-4 py-2.5 rounded-xl border border-ink-200 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-clay-400/40"
                />
                <button
                  type="submit"
                  disabled={saving}
                  className="px-4 py-2.5 text-sm font-semibold border border-ink-200 text-ink-800 rounded-xl hover:bg-ink-50 disabled:opacity-50"
                >
                  Accept invite
                </button>
              </form>
            </div>
          </>
        )}
      </div>
    </PageShell>
  );
}
