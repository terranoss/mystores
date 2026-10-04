import Spinner from '../Spinner';
import type { OrdersTableProps, OrdersTableRow } from './ordersTableTypes';

export default function OrdersTable<TRow extends OrdersTableRow>({
  title,
  loadingText,
  emptyText,
  rows,
  loading = false,
  columns,
  bulkToolbar,
  pagination,
  allSelectableSelected = false,
  disableSelectAll = false,
  onToggleSelectAll,
  onOpenDetail,
  renderItems,
  renderStatus,
  renderActions,
  onToggleSelected,
  getSelectTitle,
}: OrdersTableProps<TRow>) {
  const showPlatformColumn = columns?.platform ?? false;
  const showStoreColumn = columns?.store ?? false;
  const showBuyerColumn = columns?.buyer ?? false;
  const showShipByColumn = columns?.shipBy ?? false;

  return (
    <div className="bg-white rounded-2xl border border-ink-100 shadow-sm overflow-hidden">
      {bulkToolbar && (
        <div className="px-4 sm:px-6 py-3 border-b border-ink-100 bg-ink-50/40">
          {bulkToolbar}
        </div>
      )}

      {loading ? (
        <div className="flex items-center justify-center py-20 gap-2 text-ink-400 text-sm">
          <Spinner /> {loadingText}
        </div>
      ) : rows.length === 0 ? (
        <div className="p-12 text-center text-sm text-ink-400">{emptyText}</div>
      ) : (
        <>
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse" aria-label={title}>
              <thead>
                <tr className="border-b border-ink-100 bg-ink-50/50 text-xs font-semibold text-ink-500 uppercase tracking-wider">
                  <th className="px-4 py-4 w-10">
                    {onToggleSelectAll ? (
                      <input
                        type="checkbox"
                        checked={allSelectableSelected}
                        disabled={disableSelectAll}
                        onChange={onToggleSelectAll}
                        onClick={(e) => e.stopPropagation()}
                        title="Select all printable orders on this page"
                        aria-label="Select all printable orders"
                        className="rounded border-ink-300"
                      />
                    ) : null}
                  </th>
                  {showPlatformColumn && (
                    <th className="px-4 py-4 whitespace-nowrap">
                      Platform
                    </th>
                  )}
                  <th className="px-4 py-4 whitespace-nowrap">
                    Order
                  </th>
                  <th className="px-4 py-4 whitespace-nowrap">
                    Ordered
                  </th>
                  {showStoreColumn && (
                    <th className="px-4 py-4 whitespace-nowrap hidden sm:table-cell">
                      Store
                    </th>
                  )}
                  <th className="px-4 py-4 min-w-[200px]">
                    Items
                  </th>
                  {showBuyerColumn && (
                    <th className="px-4 py-4 whitespace-nowrap">
                      Buyer
                    </th>
                  )}
                  <th className="px-4 py-4 whitespace-nowrap">
                    Status
                  </th>
                  {showShipByColumn && (
                    <th className="px-4 py-4 whitespace-nowrap">
                      Ship by
                    </th>
                  )}
                  <th className="px-4 py-4 text-right whitespace-nowrap">
                    Total
                  </th>
                  <th className="px-4 py-4 text-right" />
                </tr>
              </thead>
              <tbody className="divide-y divide-ink-50">
                {rows.map((row) => (
                  <tr
                    key={row.id}
                    onClick={() => onOpenDetail?.(row)}
                    className={`hover:bg-ink-50/50 transition ${onOpenDetail ? 'cursor-pointer' : ''}`}
                  >
                    <td
                      className="px-4 py-4 align-top"
                      onClick={(e) => e.stopPropagation()}
                    >
                      {onToggleSelected ? (
                        <input
                          type="checkbox"
                          checked={!!row.selected}
                          disabled={!row.selectable}
                          onChange={() => onToggleSelected(row)}
                          title={getSelectTitle?.(row)}
                          aria-label={`Select order ${row.orderSn}`}
                          className="rounded border-ink-300"
                        />
                      ) : null}
                    </td>
                    {showPlatformColumn && (
                      <td className="px-4 py-4 align-top whitespace-nowrap">
                        {row.platform === 'lazada' ? (
                          <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-600 text-white">
                            Lazada
                          </span>
                        ) : (
                          <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-600 text-white">
                            Shopee
                          </span>
                        )}
                      </td>
                    )}
                    <td className="px-4 py-4 align-top">
                      <p className="font-mono text-sm font-semibold text-ink-900 whitespace-nowrap">
                        {row.orderSn}
                      </p>
                      <p className="text-xs text-ink-400 mt-0.5 whitespace-nowrap">{row.orderMeta || '—'}</p>
                    </td>
                    <td className="px-4 py-4 align-top whitespace-nowrap">
                      <span className="text-sm text-ink-700">
                        {row.orderedAtText || '—'}
                      </span>
                    </td>
                    {showStoreColumn && (
                      <td className="px-4 py-4 align-top hidden sm:table-cell whitespace-nowrap">
                        <span className="text-sm text-ink-700">
                          {row.storeName || '—'}
                        </span>
                      </td>
                    )}
                    <td className="px-4 py-4 align-top min-w-[200px]">{renderItems(row)}</td>
                    {showBuyerColumn && (
                      <td className="px-4 py-4 align-top whitespace-nowrap">
                        <span className="text-sm text-ink-700">
                          {row.buyerText || '—'}
                        </span>
                      </td>
                    )}
                    <td className="px-4 py-4 align-top whitespace-nowrap">{renderStatus(row)}</td>
                    {showShipByColumn && (
                      <td className="px-4 py-4 align-top whitespace-nowrap">
                        <span
                          className={`text-sm ${
                            row.shipByTone === 'overdue'
                              ? 'text-red-600 font-semibold'
                              : row.shipByTone === 'due_today'
                                ? 'text-amber-600 font-semibold'
                                : 'text-ink-700'
                          }`}
                        >
                          {row.shipByText || '—'}
                        </span>
                      </td>
                    )}
                    <td className="px-4 py-4 align-top text-right whitespace-nowrap">
                      <span className="font-semibold text-ink-900 text-sm font-mono">
                        {row.totalText}
                      </span>
                    </td>
                    <td
                      className="px-4 py-4 align-top text-right whitespace-nowrap"
                      onClick={(e) => e.stopPropagation()}
                    >
                      {renderActions(row)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {pagination && (
            <div className="px-4 sm:px-6 py-3 bg-ink-50/30 border-t border-ink-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-ink-400">
              <span>
                Page {pagination.page} of {pagination.lastPage} · {pagination.total} order
                {pagination.total !== 1 ? 's' : ''}
              </span>
              <div className="flex items-center gap-2">
                <button
                  onClick={pagination.onPrev}
                  disabled={pagination.page <= 1}
                  className="px-3 py-1.5 rounded-lg border border-ink-200 text-ink-700 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed transition"
                >
                  Prev
                </button>
                <button
                  onClick={pagination.onNext}
                  disabled={pagination.page >= pagination.lastPage}
                  className="px-3 py-1.5 rounded-lg border border-ink-200 text-ink-700 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed transition"
                >
                  Next
                </button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}
