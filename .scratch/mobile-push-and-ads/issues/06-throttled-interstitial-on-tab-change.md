# 06: Show a throttled interstitial on tab change

**What to build:** Moving between the bottom navigation tabs occasionally shows a full-screen advertisement, but never often enough to feel like nagging, never at app launch, and never in the middle of work the operator is trying to confirm. The app opens immediately and stays immediate.

**Blocked by:** 05: Show a banner ad on the Dashboard

**Status:** ready-for-agent

- [ ] A full-screen advertisement can appear when the operator changes bottom navigation tab.
- [ ] It appears at most once in any five minute window.
- [ ] It never appears on app launch or on the first navigation of a session.
- [ ] It does not appear when the navigation was initiated by tapping a push notification.
- [ ] It does not appear immediately after capturing a pick-list photo.
- [ ] It does not appear immediately after confirming a stock or price edit.
- [ ] Loading and failure states are handled without throwing and without blocking navigation — the tab change always completes.
- [ ] It reuses the advertisement configuration and test/live switching established by the banner ticket rather than introducing a second copy.
- [ ] Live advertisement units are used only when the same explicit build flag is set.
