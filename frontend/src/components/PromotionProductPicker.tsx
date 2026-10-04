import { useEffect, useMemo, useState } from 'react';
import { getProducts, type Product, type ProductVariant } from '../api/client';
import Spinner from './Spinner';

export type PromoItemSelection = {
  productId: number;
  itemId: number;
  name: string;
  price: number;
  hasVariants: boolean;
  variants: ProductVariant[];
};

export type PromoSkuSelection = {
  key: string;
  productId: number;
  /** Lazada SkuId when known; null means backend should resolve from product_ids */
  skuId: number | string | null;
  label: string;
};

type ItemModeProps = {
  mode: 'item';
  selected: PromoItemSelection[];
  onChange: (next: PromoItemSelection[]) => void;
};

type SkuModeProps = {
  mode: 'sku';
  selected: PromoSkuSelection[];
  onChange: (next: PromoSkuSelection[]) => void;
};

type CommonProps = {
  storeId: number;
  disabled?: boolean;
};

type Props = CommonProps & (ItemModeProps | SkuModeProps);

function productToItemSelection(p: Product): PromoItemSelection {
  return {
    productId: p.id,
    itemId: p.item_id,
    name: p.item_name || `Item #${p.item_id}`,
    price: Number(p.price) || 0,
    hasVariants: Boolean(p.has_variants && p.variants?.length),
    variants: p.variants ?? [],
  };
}

function simpleSkuSelection(p: Product): PromoSkuSelection {
  return {
    key: `product-${p.id}`,
    productId: p.id,
    skuId: null,
    label: p.item_name || p.item_sku || `Product #${p.id}`,
  };
}

function variantSkuSelection(p: Product, v: ProductVariant): PromoSkuSelection {
  return {
    key: `sku-${v.model_id}`,
    productId: p.id,
    skuId: v.model_id,
    label: `${p.item_name || `Item #${p.item_id}`} — ${v.name || v.model_sku || `SKU ${v.model_id}`}`,
  };
}

export default function PromotionProductPicker(props: Props) {
  const { storeId, disabled = false, mode } = props;
  const [search, setSearch] = useState('');
  const [debounced, setDebounced] = useState('');
  const [loading, setLoading] = useState(false);
  const [products, setProducts] = useState<Product[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const t = window.setTimeout(() => setDebounced(search.trim()), 300);
    return () => window.clearTimeout(t);
  }, [search]);

  useEffect(() => {
    if (!storeId) {
      setProducts([]);
      return;
    }
    let cancelled = false;
    setLoading(true);
    setError(null);
    void getProducts({ store_id: storeId, search: debounced || undefined, page: 1 })
      .then((res) => {
        if (!cancelled) setProducts(res.data ?? []);
      })
      .catch((err: unknown) => {
        if (!cancelled) {
          setProducts([]);
          setError(err instanceof Error ? err.message : 'Failed to load products');
        }
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [storeId, debounced]);

  const selectedItemKeys = useMemo(() => {
    if (mode !== 'item') return new Set<number>();
    return new Set(props.selected.map((s) => s.productId));
  }, [mode, props]);

  const selectedSkuKeys = useMemo(() => {
    if (mode !== 'sku') return new Set<string>();
    return new Set(props.selected.map((s) => s.key));
  }, [mode, props]);

  function toggleItem(p: Product) {
    if (mode !== 'item' || disabled) return;
    const next = productToItemSelection(p);
    if (selectedItemKeys.has(p.id)) {
      props.onChange(props.selected.filter((s) => s.productId !== p.id));
      return;
    }
    props.onChange([...props.selected, next]);
  }

  function toggleSku(sel: PromoSkuSelection) {
    if (mode !== 'sku' || disabled) return;
    if (selectedSkuKeys.has(sel.key)) {
      props.onChange(props.selected.filter((s) => s.key !== sel.key));
      return;
    }
    props.onChange([...props.selected, sel]);
  }

  const selectedCount = mode === 'item' ? props.selected.length : props.selected.length;

  return (
    <div className="rounded-lg border border-ink-200 bg-ink-50/40">
      <div className="flex items-center gap-2 border-b border-ink-100 px-3 py-2">
        <input
          type="search"
          className="w-full rounded-md border border-ink-200 bg-white px-2.5 py-1.5 text-sm"
          placeholder="Search products…"
          value={search}
          disabled={disabled || !storeId}
          onChange={(e) => setSearch(e.target.value)}
          aria-label="Search products"
        />
        <span className="shrink-0 text-xs text-ink-500">{selectedCount} selected</span>
      </div>
      <div className="max-h-52 overflow-y-auto px-2 py-2">
        {loading ? (
          <div className="flex justify-center py-6">
            <Spinner />
          </div>
        ) : error ? (
          <p className="px-2 py-3 text-sm text-red-600">{error}</p>
        ) : products.length === 0 ? (
          <p className="px-2 py-3 text-sm text-ink-500">
            No products found for this store. Pull stock first, then try again.
          </p>
        ) : mode === 'item' ? (
          <ul className="space-y-1">
            {products.map((p) => {
              const checked = selectedItemKeys.has(p.id);
              return (
                <li key={p.id}>
                  <label className="flex cursor-pointer items-start gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-white">
                    <input
                      type="checkbox"
                      className="mt-0.5"
                      checked={checked}
                      disabled={disabled}
                      onChange={() => toggleItem(p)}
                    />
                    <span className="min-w-0">
                      <span className="block truncate font-medium text-ink-900">
                        {p.item_name || `Item #${p.item_id}`}
                      </span>
                      <span className="block text-xs text-ink-500">
                        ID {p.item_id}
                        {p.item_sku ? ` · ${p.item_sku}` : ''}
                        {p.has_variants ? ` · ${p.variants?.length || 0} variants` : ''}
                      </span>
                    </span>
                  </label>
                </li>
              );
            })}
          </ul>
        ) : (
          <ul className="space-y-1">
            {products.map((p) => {
              if (p.has_variants && p.variants?.length) {
                return p.variants.map((v) => {
                  const sel = variantSkuSelection(p, v);
                  const checked = selectedSkuKeys.has(sel.key);
                  return (
                    <li key={sel.key}>
                      <label className="flex cursor-pointer items-start gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-white">
                        <input
                          type="checkbox"
                          className="mt-0.5"
                          checked={checked}
                          disabled={disabled}
                          onChange={() => toggleSku(sel)}
                        />
                        <span className="min-w-0">
                          <span className="block truncate font-medium text-ink-900">{sel.label}</span>
                          <span className="block text-xs text-ink-500">SkuId {v.model_id}</span>
                        </span>
                      </label>
                    </li>
                  );
                });
              }
              const sel = simpleSkuSelection(p);
              const checked = selectedSkuKeys.has(sel.key);
              return (
                <li key={sel.key}>
                  <label className="flex cursor-pointer items-start gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-white">
                    <input
                      type="checkbox"
                      className="mt-0.5"
                      checked={checked}
                      disabled={disabled}
                      onChange={() => toggleSku(sel)}
                    />
                    <span className="min-w-0">
                      <span className="block truncate font-medium text-ink-900">{sel.label}</span>
                      <span className="block text-xs text-ink-500">
                        {p.item_sku ? `SKU ${p.item_sku} · ` : ''}SkuId resolved on save
                      </span>
                    </span>
                  </label>
                </li>
              );
            })}
          </ul>
        )}
      </div>
    </div>
  );
}
