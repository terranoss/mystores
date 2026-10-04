import { PageShell } from '../components/PageShell';
import UnifiedOrdersPanel from './UnifiedOrdersPanel';

export default function Orders() {
  return (
    <PageShell>
      <div className="w-full max-w-none space-y-6">
        <div>
          <h2 className="font-heading text-2xl font-bold text-ink-950">Orders</h2>
          <p className="text-ink-400 text-sm mt-1">
            Manage Shopee and Lazada orders in one place. Filter by platform for fulfillment queues.
          </p>
        </div>

        <UnifiedOrdersPanel />
      </div>
    </PageShell>
  );
}
