# 01: Delete the orphan order panels

**What to build:** The orders page keeps rendering the unified orders panel, and the two order panels that nothing imports are removed, leaving exactly one orders implementation in the app.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Neither orphan panel exists in the frontend source.
- [ ] No import, route, keep-alive entry, menu entry or lazy map references either panel.
- [ ] The orders page renders the unified orders panel exactly as before.
- [ ] The frontend build and lint pass.
- [ ] No order screen changes behaviour.
