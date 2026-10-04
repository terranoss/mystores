import { useState } from 'react';
import Spinner from './Spinner';
import { downloadShippingLabel, type Order } from '../api/client';
import { mergeAwbAndPacking, openPdfBlob } from '../lib/shippingLabelPdf';

interface Props {
  order: Order;
}

/**
 * One-click Air Waybill + Packing List:
 * 1. Fetches AWB + packing list JSON from the API
 * 2. Merges with pdf-lib in the browser
 * 3. Opens the combined PDF in a new tab
 */
export default function PrintLabelButton({ order }: Props) {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handlePrint = async () => {
    setLoading(true);
    setError(null);
    try {
      const docs = await downloadShippingLabel(order.id);
      const mergedBytes = await mergeAwbAndPacking(docs.awbPdf, docs.packingListPdf);
      openPdfBlob(mergedBytes, docs.filename || `awb-packing-${order.order_sn}.pdf`);
    } catch (e: any) {
      let message = e?.message || 'Failed to download shipping label.';
      if (e?.response?.data?.message) {
        message = e.response.data.message;
      }
      setError(message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex flex-col items-start gap-1">
      <button
        onClick={handlePrint}
        disabled={loading}
        title="Print AWB + Packing List"
        className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg border border-indigo-200 text-indigo-700 hover:bg-indigo-50 disabled:opacity-60 transition"
      >
        {loading ? (
          <>
            <Spinner />
            <span>Generating…</span>
          </>
        ) : (
          <>
            <span>🖨</span>
            <span>Print AWB + Packing</span>
          </>
        )}
      </button>
      {error && (
        <p className="text-xs text-red-600 max-w-[12rem] leading-snug">{error}</p>
      )}
    </div>
  );
}
