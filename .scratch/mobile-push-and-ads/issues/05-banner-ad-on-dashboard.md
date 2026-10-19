# 05: Show a banner ad on the Dashboard

**What to build:** The operator sees a small advertisement at the bottom of the Dashboard and nowhere else in the app. When no advertisement is available the space stays where it was, so the Dashboard does not visibly jump while they are scrolling. Nothing about the ad setup can earn a policy strike from a developer testing their own build.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] A banner ad renders above the Dashboard's bottom navigation.
- [ ] No ad renders on the Orders, Stock, pick-list, or Team screens.
- [ ] On an ad failing to load, the reserved height is preserved and a thin divider marks the space as intentional.
- [ ] The Dashboard content does not reflow when an ad fails to load.
- [ ] Debug and ordinary builds use Google's published test advertisement units.
- [ ] Live advertisement units are used only when an explicit build flag is set at build time.
- [ ] The advertisement application identifier is supplied at build time, defaulting to Google's test value; it is not hardcoded.
- [ ] No credential is committed to the repository.
- [ ] The Android minimum supported version is raised as far as the advertisement SDK requires and no further.
- [ ] That minimum-version change is written down in the mobile app's README, with the reason.
- [ ] The existing launcher-icon minimum-version setting is aligned with the new value.
