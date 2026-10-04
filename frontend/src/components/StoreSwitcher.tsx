import { useStore } from '../context/StoreContext';
import type { MarketplaceType } from '../api/client';

type Tab = { label: string; value: MarketplaceType | 'all' };

const TABS: Tab[] = [
  { label: 'All', value: 'all' },
  { label: 'Shopee', value: 'shopee' },
  { label: 'Lazada', value: 'lazada' },
];

export default function StoreSwitcher() {
  const { filteredStores, selectedStoreId, setSelectedStoreId, selectedMarketplace, setSelectedMarketplace } =
    useStore();

  return (
    <div className="flex items-center gap-2">
      {/* Marketplace tabs */}
      <div className="flex rounded-xl border border-ink-200 overflow-hidden text-sm">
        {TABS.map((tab) => (
          <button
            key={tab.value}
            onClick={() => setSelectedMarketplace(tab.value)}
            className={`px-3 py-1.5 transition-colors ${
              selectedMarketplace === tab.value
                ? tab.value === 'shopee'
                  ? 'bg-red-600 text-white font-medium'
                  : tab.value === 'lazada'
                    ? 'bg-blue-600 text-white font-medium'
                    : 'bg-ink-950 text-white font-medium'
                : 'bg-white text-ink-600 hover:bg-ink-50'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Store dropdown — only shown when there are stores to switch between */}
      {filteredStores.length > 1 && (
        <select
          value={selectedStoreId ?? ''}
          onChange={(e) => setSelectedStoreId(e.target.value ? Number(e.target.value) : null)}
          className="px-3 py-2 rounded-xl border border-ink-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-clay-400/40 min-w-[140px]"
        >
          <option value="">All Stores</option>
          {filteredStores.map((s) => (
            <option key={s.id} value={s.id}>
              {s.shop_name || `Shop #${s.shop_id}`}
            </option>
          ))}
        </select>
      )}
    </div>
  );
}
