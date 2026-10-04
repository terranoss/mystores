import { createContext, useContext, useState, useEffect, useCallback, type ReactNode } from 'react';
import { getStores, type ShopeeStore, type MarketplaceType } from '../api/client';
import { useAuth } from './AuthContext';

interface StoreContextType {
  stores: ShopeeStore[];
  filteredStores: ShopeeStore[];
  selectedStoreId: number | null;
  setSelectedStoreId: (id: number | null) => void;
  selectedMarketplace: MarketplaceType | 'all';
  setSelectedMarketplace: (m: MarketplaceType | 'all') => void;
  refetchStores: () => void;
  loading: boolean;
}

const StoreContext = createContext<StoreContextType | null>(null);

export function StoreProvider({ children }: { children: ReactNode }) {
  const { user } = useAuth();
  const [stores, setStores] = useState<ShopeeStore[]>([]);
  const [selectedStoreId, setSelectedStoreId] = useState<number | null>(null);
  const [selectedMarketplace, setSelectedMarketplace] = useState<MarketplaceType | 'all'>('all');
  const [loading, setLoading] = useState(true);

  const fetchStores = useCallback(() => {
    if (!user) {
      setStores([]);
      setLoading(false);
      return;
    }
    setLoading(true);
    getStores()
      .then(setStores)
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [user]);

  useEffect(() => {
    fetchStores();
  }, [fetchStores]);

  // Reset store selection when marketplace filter changes
  useEffect(() => {
    setSelectedStoreId(null);
  }, [selectedMarketplace]);

  const filteredStores =
    selectedMarketplace === 'all'
      ? stores
      : stores.filter((s) => s.platform === selectedMarketplace);

  return (
    <StoreContext.Provider
      value={{
        stores,
        filteredStores,
        selectedStoreId,
        setSelectedStoreId,
        selectedMarketplace,
        setSelectedMarketplace,
        refetchStores: fetchStores,
        loading,
      }}
    >
      {children}
    </StoreContext.Provider>
  );
}

export function useStore() {
  const ctx = useContext(StoreContext);
  if (!ctx) throw new Error('useStore must be used within StoreProvider');
  return ctx;
}
