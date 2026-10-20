# 04: Replace the frontend order helpers

**What to build:** The frontend gets one module for canonical status predicates and presentation (badge tone, dot, label, and the capability mirrors for cancel, ship and print) plus one module for the formatting helpers both old files duplicated. The two helper files are deleted and every consumer uses the new modules. The same lifecycle state renders one way everywhere, and a null canonical status renders a neutral badge with the raw text.

**Blocked by:** 02

**Status:** ready-for-agent

- [ ] Vitest is configured with jsdom and Testing Library, and a test script runs.
- [ ] One module owns canonical predicates and presentation; one module owns the formatting helpers.
- [ ] Both old helper modules are deleted and every import is updated.
- [ ] Shopee PROCESSED and Lazada ready_to_ship render the same badge tone and label.
- [ ] A null canonical status renders a neutral badge with the raw status text.
- [ ] Cancel, ship and print visibility match the per-platform sets.
- [ ] Table-driven specs cover mapping, presentation and predicates for both platforms.
- [ ] Build, lint and tests pass.
