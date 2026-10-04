import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useParams } from 'react-router-dom';
import { PageShell } from '../components/PageShell';
import { useKeepAlivePath } from '../components/KeepAlivePages';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';
import {
  completePickListItem,
  deletePickListComment,
  fetchPickListPhotoObjectUrl,
  getPendingPickList,
  getTodayPickList,
  markPickListCommentsRead,
  postPickListComment,
  uncompletePickListItem,
  type PickListKind,
  type TodayPickListItem,
  type TodayPickListResponse,
} from '../api/client';
import './PickList.css';

function itemKeyOf(item: TodayPickListItem): string {
  if (item.item_key) return item.item_key;
  return `${item.item_id ?? item.item_name}_${item.model_id ?? ''}`;
}

function AuthenticatedThumb({
  completionId,
  onClick,
}: {
  completionId: number;
  onClick?: () => void;
}) {
  const [url, setUrl] = useState<string | null>(null);

  useEffect(() => {
    let revoked: string | null = null;
    let cancelled = false;
    fetchPickListPhotoObjectUrl(completionId)
      .then((u) => {
        if (cancelled) {
          URL.revokeObjectURL(u);
          return;
        }
        revoked = u;
        setUrl(u);
      })
      .catch(() => {});
    return () => {
      cancelled = true;
      if (revoked) URL.revokeObjectURL(revoked);
    };
  }, [completionId]);

  if (!url) {
    return (
      <span className="picklist-thumb" style={{ display: 'inline-block', background: '#f3f4f6' }} />
    );
  }

  return (
    <img
      src={url}
      alt="Pick photo"
      className="picklist-thumb"
      onClick={(e) => {
        e.stopPropagation();
        onClick?.();
      }}
    />
  );
}

export default function PickList() {
  const { kind: kindParam } = useParams<{ kind: string }>();
  const keepAlivePath = useKeepAlivePath();
  const kind: PickListKind =
    keepAlivePath === 'pick-pending' || (!keepAlivePath && kindParam === 'pending')
      ? 'pending'
      : 'today';
  const printTitle =
    kind === 'pending' ? 'Pick List — Pending Orders' : "Pick List — Today's Orders";

  const { user } = useAuth();
  const { showToast } = useToast();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const pendingPickRef = useRef<TodayPickListItem | null>(null);

  const [data, setData] = useState<TodayPickListResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [selected, setSelected] = useState<TodayPickListItem | null>(null);
  const [commentDraft, setCommentDraft] = useState('');
  const [sendingComment, setSendingComment] = useState(false);
  const [drawerPhotoUrl, setDrawerPhotoUrl] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = kind === 'pending' ? await getPendingPickList() : await getTodayPickList();
      setData(res);
      setSelected((prev) => {
        if (!prev) return null;
        const key = itemKeyOf(prev);
        return res.aggregated_items.find((i) => itemKeyOf(i) === key) ?? null;
      });
    } catch {
      setError('Failed to load pick list.');
      setData(null);
    } finally {
      setLoading(false);
    }
  }, [kind]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    let revoked: string | null = null;
    let cancelled = false;
    setDrawerPhotoUrl(null);
    const id = selected?.completion?.id;
    if (!id) return;
    fetchPickListPhotoObjectUrl(id)
      .then((u) => {
        if (cancelled) {
          URL.revokeObjectURL(u);
          return;
        }
        revoked = u;
        setDrawerPhotoUrl(u);
      })
      .catch(() => {});
    return () => {
      cancelled = true;
      if (revoked) URL.revokeObjectURL(revoked);
    };
  }, [selected?.completion?.id]);

  const items = data?.aggregated_items ?? [];
  const pickedCount = useMemo(
    () => items.filter((i) => i.completion != null).length,
    [items]
  );
  const progressPct = items.length === 0 ? 0 : Math.round((pickedCount / items.length) * 100);

  const clearUnreadLocally = (key: string) => {
    setData((prev) => {
      if (!prev) return prev;
      return {
        ...prev,
        aggregated_items: prev.aggregated_items.map((i) =>
          itemKeyOf(i) === key
            ? { ...i, has_unread_comments: false, unread_comment_count: 0 }
            : i
        ),
      };
    });
  };

  const openItemDrawer = async (item: TodayPickListItem) => {
    const key = itemKeyOf(item);
    setSelected({
      ...item,
      has_unread_comments: false,
      unread_comment_count: 0,
    });
    if (item.has_unread_comments) {
      clearUnreadLocally(key);
      try {
        await markPickListCommentsRead({ kind, item_key: key });
      } catch {
        // keep UI cleared; next refresh will reconcile
      }
    }
  };

  const openFilePicker = (item: TodayPickListItem) => {
    pendingPickRef.current = item;
    fileInputRef.current?.click();
  };

  const onFileChosen = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    const item = pendingPickRef.current;
    e.target.value = '';
    pendingPickRef.current = null;
    if (!file || !item) return;
    const key = itemKeyOf(item);
    setBusyKey(key);
    try {
      await completePickListItem({
        kind,
        item_key: key,
        item_id: item.item_id,
        model_id: item.model_id,
        item_name: item.item_name,
        photo: file,
      });
      showToast('Item marked as picked.', 'success');
      await load();
    } catch {
      showToast('Failed to pick item. Photo is required.', 'error');
    } finally {
      setBusyKey(null);
    }
  };

  const handleCheckClick = (item: TodayPickListItem) => {
    if (busyKey) return;
    if (item.completion) {
      void openItemDrawer(item);
      return;
    }
    openFilePicker(item);
  };

  const handleUnpick = async () => {
    if (!selected) return;
    const key = itemKeyOf(selected);
    setBusyKey(key);
    try {
      await uncompletePickListItem({ kind, item_key: key });
      showToast('Item unpicked.', 'success');
      setSelected(null);
      await load();
    } catch {
      showToast('Failed to unpick item.', 'error');
    } finally {
      setBusyKey(null);
    }
  };

  const handleReplacePhoto = () => {
    if (!selected) return;
    openFilePicker(selected);
  };

  const handleSendComment = async () => {
    if (!selected) return;
    const body = commentDraft.trim();
    if (!body) return;
    setSendingComment(true);
    try {
      await postPickListComment({
        kind,
        item_key: itemKeyOf(selected),
        item_id: selected.item_id,
        model_id: selected.model_id,
        item_name: selected.item_name,
        body,
      });
      setCommentDraft('');
      await load();
      showToast('Comment posted.', 'success');
    } catch {
      showToast('Failed to post comment.', 'error');
    } finally {
      setSendingComment(false);
    }
  };

  const handleDeleteComment = async (id: number) => {
    try {
      await deletePickListComment(id);
      await load();
    } catch {
      showToast('Failed to delete comment.', 'error');
    }
  };

  const generatedLabel = data
    ? new Date(data.generated_at).toLocaleString('en-GB', {
        dateStyle: 'medium',
        timeStyle: 'short',
      })
    : '';

  return (
    <PageShell>
      <div className="picklist-page">
        <div className="picklist-toolbar picklist-no-print">
          <div className="picklist-meta">
            Interactive printout checklist — pick with photo, comment, then print.
          </div>
          <div className="picklist-toolbar-actions">
            <button
              type="button"
              className="picklist-btn picklist-btn-secondary"
              onClick={() => void load()}
              disabled={loading || busyKey != null}
            >
              Refresh
            </button>
            <button
              type="button"
              className="picklist-btn"
              onClick={() => window.print()}
              disabled={!data}
            >
              Print / Save as PDF
            </button>
          </div>
        </div>

        {error && <div className="picklist-error picklist-no-print">{error}</div>}

        {loading && !data ? (
          <div className="picklist-empty">Loading pick list…</div>
        ) : data ? (
          <div className="picklist-sheet">
            <div className="picklist-header-bar">
              <div>
                <div className="picklist-title">{printTitle}</div>
                <div className="picklist-meta">
                  Date: <strong>{data.date}</strong> &nbsp;|&nbsp; Generated:{' '}
                  <strong>{generatedLabel}</strong>
                </div>
              </div>
              <div className="picklist-meta" style={{ textAlign: 'right' }}>
                Orders: <strong>{data.total_orders}</strong> &nbsp;|&nbsp; SKUs:{' '}
                <strong>{data.total_skus}</strong> &nbsp;|&nbsp; Units:{' '}
                <strong>{data.total_units}</strong> &nbsp;|&nbsp; Picked:{' '}
                <strong>
                  {pickedCount}/{items.length}
                </strong>
              </div>
            </div>

            <div className="picklist-progress picklist-no-print">
              <div className="picklist-progress-bar">
                <div className="picklist-progress-fill" style={{ width: `${progressPct}%` }} />
              </div>
            </div>

            <div className="picklist-section-header">
              1. Warehouse Pick List (Lumped Totals for Shelf Picking)
            </div>

            <table className="picklist-table">
              <thead>
                <tr>
                  <th className="picklist-check-col">[ ]</th>
                  <th>Item Name & Variation</th>
                  <th className="picklist-sku-col">SKU / Model</th>
                  <th>Store / Platform</th>
                  <th className="picklist-qty-col">Qty</th>
                  <th className="picklist-actions-col picklist-no-print"> </th>
                </tr>
              </thead>
              <tbody>
                {items.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="picklist-empty">
                      {kind === 'pending'
                        ? 'No pending orders to pick.'
                        : 'No orders to pick today.'}
                    </td>
                  </tr>
                ) : (
                  items.map((item) => {
                    const key = itemKeyOf(item);
                    const picked = item.completion != null;
                    const comments = item.comment_count ?? item.comments?.length ?? 0;
                    const unread = item.has_unread_comments === true;
                    const unreadCount = item.unread_comment_count ?? 0;
                    return (
                      <tr
                        key={key}
                        className={`${picked ? 'is-picked' : ''} ${busyKey === key ? 'is-busy' : ''}`}
                      >
                        <td className="picklist-check-col">
                          <button
                            type="button"
                            className={`picklist-checkbox ${picked ? 'is-checked' : ''}`}
                            aria-label={picked ? 'Open pick details' : 'Mark picked with photo'}
                            disabled={busyKey != null}
                            onClick={() => handleCheckClick(item)}
                          >
                            {picked ? '✓' : ''}
                          </button>
                        </td>
                        <td>
                          <strong className="picklist-item-name">{item.item_name}</strong>
                          {picked && item.completion && (
                            <div className="picklist-meta" style={{ marginTop: 2 }}>
                              Picked by {item.completion.picked_by.name}
                            </div>
                          )}
                        </td>
                        <td className="picklist-sku-col">
                          {String(item.model_id || item.item_id || '—')}
                        </td>
                        <td>
                          {item.platforms.map((p) => (
                            <span
                              key={p}
                              className={`picklist-badge ${
                                p === 'lazada' ? 'picklist-badge-lazada' : 'picklist-badge-shopee'
                              }`}
                            >
                              {p}
                            </span>
                          ))}
                          <span style={{ color: '#4b5563', fontSize: 10 }}>
                            {item.store_names.join(', ')}
                          </span>
                        </td>
                        <td className="picklist-qty-col">{item.quantity}</td>
                        <td className="picklist-actions-col picklist-no-print">
                          {picked && item.completion && (
                            <AuthenticatedThumb
                              completionId={item.completion.id}
                              onClick={() => void openItemDrawer(item)}
                            />
                          )}
                          <button
                            type="button"
                            className={`picklist-icon-btn ${unread ? 'has-unread' : ''}`}
                            title={unread ? 'New comments' : 'Comments'}
                            disabled={busyKey != null}
                            onClick={() => void openItemDrawer(item)}
                          >
                            💬
                            {unread
                              ? unreadCount > 0
                                ? ` ${unreadCount}`
                                : ''
                              : comments > 0
                                ? ` ${comments}`
                                : ''}
                            {unread && <span className="picklist-unread-dot" aria-hidden />}
                          </button>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>

            <div className="picklist-section-header" style={{ marginTop: 18 }}>
              2. Order Packing Details (For Customer Parcel Sorting)
            </div>
            <div>
              {data.orders.length === 0 ? (
                <p className="picklist-empty" style={{ padding: 8 }}>
                  No orders for this list.
                </p>
              ) : (
                data.orders.map((order, idx) => (
                  <div key={order.id} className="picklist-order-row">
                    <div className="picklist-order-meta">
                      <span>
                        #{idx + 1} Order: <strong>{order.order_sn}</strong>{' '}
                        <span
                          className={`picklist-badge ${
                            order.platform === 'lazada'
                              ? 'picklist-badge-lazada'
                              : 'picklist-badge-shopee'
                          }`}
                        >
                          {order.platform}
                        </span>{' '}
                        ({order.shop_name})
                      </span>
                      <span style={{ color: '#4b5563' }}>
                        Status: {order.order_status}
                        {order.buyer_username ? ` | Buyer: ${order.buyer_username}` : ''}
                      </span>
                    </div>
                    <div className="picklist-order-items">
                      {order.items.map((i, iIdx) => (
                        <div key={`${order.id}-${iIdx}`}>
                          • {i.item_name} — <strong>x{i.quantity}</strong>
                        </div>
                      ))}
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        ) : null}

        <input
          ref={fileInputRef}
          type="file"
          accept="image/*"
          capture="environment"
          className="picklist-hidden-file"
          onChange={(e) => void onFileChosen(e)}
        />

        {selected && (
          <>
            <div
              className="picklist-drawer-backdrop picklist-no-print"
              onClick={() => setSelected(null)}
            />
            <aside className="picklist-drawer picklist-no-print">
              <div className="picklist-drawer-header">
                <div>
                  <div style={{ fontWeight: 800, fontSize: 15 }}>{selected.item_name}</div>
                  <div className="picklist-meta">Qty ×{selected.quantity}</div>
                </div>
                <button
                  type="button"
                  className="picklist-btn picklist-btn-secondary"
                  onClick={() => setSelected(null)}
                >
                  Close
                </button>
              </div>
              <div className="picklist-drawer-body">
                {selected.completion ? (
                  <>
                    {drawerPhotoUrl ? (
                      <img
                        src={drawerPhotoUrl}
                        alt="Pick photo"
                        className="picklist-drawer-photo"
                      />
                    ) : (
                      <div className="picklist-empty">Loading photo…</div>
                    )}
                    <div className="picklist-meta" style={{ marginBottom: 12 }}>
                      Picked by <strong>{selected.completion.picked_by.name}</strong>
                    </div>
                    <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
                      <button
                        type="button"
                        className="picklist-btn picklist-btn-secondary"
                        disabled={busyKey != null}
                        onClick={handleReplacePhoto}
                      >
                        Replace photo
                      </button>
                      <button
                        type="button"
                        className="picklist-btn picklist-btn-danger"
                        disabled={busyKey != null}
                        onClick={() => void handleUnpick()}
                      >
                        Unpick
                      </button>
                    </div>
                  </>
                ) : (
                  <div style={{ marginBottom: 16 }}>
                    <p className="picklist-meta" style={{ marginBottom: 8 }}>
                      Not picked yet. A photo is required to mark as picked.
                    </p>
                    <button
                      type="button"
                      className="picklist-btn"
                      disabled={busyKey != null}
                      onClick={() => openFilePicker(selected)}
                    >
                      Take / upload photo & pick
                    </button>
                  </div>
                )}

                <div className="picklist-section-header">Comments</div>
                {(selected.comments ?? []).length === 0 ? (
                  <p className="picklist-meta" style={{ margin: '8px 0 12px' }}>
                    No comments yet. Add a note for the team.
                  </p>
                ) : (
                  (selected.comments ?? []).map((c) => (
                    <div key={c.id} className="picklist-comment">
                      <div className="picklist-comment-meta">
                        <span>{c.user.name}</span>
                        <span style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                          <span style={{ fontWeight: 500, color: '#9ca3af' }}>
                            {c.created_at
                              ? new Date(c.created_at).toLocaleString('en-GB', {
                                  dateStyle: 'short',
                                  timeStyle: 'short',
                                })
                              : ''}
                          </span>
                          {user?.id === c.user.id && (
                            <button
                              type="button"
                              className="picklist-icon-btn"
                              onClick={() => void handleDeleteComment(c.id)}
                            >
                              Delete
                            </button>
                          )}
                        </span>
                      </div>
                      <div className="picklist-comment-body">{c.body}</div>
                    </div>
                  ))
                )}

                <div className="picklist-composer">
                  <textarea
                    value={commentDraft}
                    onChange={(e) => setCommentDraft(e.target.value)}
                    placeholder="Add a comment…"
                    rows={2}
                    maxLength={1000}
                  />
                  <button
                    type="button"
                    className="picklist-btn"
                    disabled={sendingComment || !commentDraft.trim()}
                    onClick={() => void handleSendComment()}
                  >
                    Send
                  </button>
                </div>
              </div>
            </aside>
          </>
        )}
      </div>
    </PageShell>
  );
}
