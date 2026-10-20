# 07: Introduce the MarketplaceProductGateway

**What to build:** One gateway becomes the only product code that talks to either marketplace's product API, with one adapter per platform. It pushes stock and price and fetches item lists, details and models while preserving today's payload shapes and error handling. The Lazada adapter refreshes its token before calling, using the locked refresh, so concurrent product calls cannot rotate each other's token. ProductService receives the gateway as a dependency and no longer reaches the SDK itself.

**Blocked by:** 06

**Status:** ready-for-agent

- [ ] No product code outside the gateway reaches the marketplace SDK or product API.
- [ ] The gateway has one Shopee adapter and one Lazada adapter.
- [ ] Pushing stock and price and fetching lists, details and models preserve current response handling.
- [ ] The Lazada adapter refreshes its token before product calls, using the locked refresh path.
- [ ] ProductService receives the gateway by constructor injection.
- [ ] Existing product, stock and token tests pass and no test makes a network call.
- [ ] The backend test suite and the frontend build pass.
