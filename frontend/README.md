# MyStores — Frontend

React 19 + TypeScript SPA for [MyStores](../README.md), the multi-marketplace (Shopee + Lazada) store manager. Served by the Laravel backend at the app root.

Stack: Vite, Tailwind CSS 4, React Router, Axios, `pdf-lib` (client-side label PDF printing).

## Commands

```bash
npm install
npm run dev      # dev server
npm run build    # production build (output is served by Laravel)
npm run lint     # lint
```

Dev server defaults to `http://localhost:5173`; set `FRONTEND_URL` in the backend `.env` to match (it drives CORS and OAuth redirects).

## Structure

```
src/
├── api/            # Axios client (auth interceptor, 401 auto-logout) + request dedupe cache
├── components/     # AppShell, Layout, ProtectedRoute, StoreSwitcher, NotificationBell,
│                   # OrderDetailDrawer, ArrangeShipmentModal, PrintLabelButton,
│                   # PromotionProductPicker, orders/ and lazada/ tables & drawers
├── context/        # Auth, Store selection, Toast
├── lib/            # shippingLabelPdf.ts
├── pages/          # Dashboard, Login, Orders (+ Shopee/Lazada/Unified panels), Stock,
│                   # Promotions, PickList, Team, Settings
├── utils/          # tokenRemaining.ts
├── App.tsx         # Router + providers
└── index.css       # Tailwind + global styles
```

## API

All requests go to the Laravel backend under `/api/` with a `Bearer` Sanctum token. See the [API Overview](../README.md#api-overview) for the full endpoint list.
