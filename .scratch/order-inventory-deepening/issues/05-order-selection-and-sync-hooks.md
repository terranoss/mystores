# 05: Extract order selection and sync hooks

**What to build:** Selection and sync move into two hooks shared by the unified orders panel and the dashboard, so both screens behave identically from one implementation. The platform sync endpoints return an additive structured outcome — a failed flag and per-store results — so the hook reports failure from a real result rather than from message text.

**Blocked by:** 04

**Status:** ready-for-agent

- [ ] One selection hook is the only selection implementation; it selects only printable rows and clears correctly.
- [ ] One sync hook is the only sync implementation; the dashboard and the unified orders panel both use it.
- [ ] The sync endpoints return a failed flag and per-store outcomes in addition to the existing message array.
- [ ] The hook reports failure from the failed flag; no code inspects message text for a failure word.
- [ ] Success and failure each refresh once and show one toast.
- [ ] Hook tests cover selection, success, failure and refresh.
- [ ] Build, lint and tests pass.
