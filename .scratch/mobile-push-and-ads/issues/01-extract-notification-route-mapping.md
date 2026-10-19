# 01: Extract the notification category→route mapping into a shared helper

**What to build:** Tapping a notification in the inbox still takes the operator to exactly the same screen it does today, but the rule deciding which screen is now a single reusable mapping rather than logic embedded in the inbox. Prefactoring — this exists so the push tap handler added later reuses one mapping instead of writing a second copy that will drift from the first.

**Blocked by:** None (can start immediately)

**Status:** resolved

- [x] The category-to-route mapping has exactly one implementation in the app.
- [x] Order notifications (new, status, urgent) still open the Orders screen.
- [x] Pick-list comment notifications still open the correct pick-list screen, distinguished by the message's `kind`.
- [x] Team activity notifications still open the Team screen.
- [x] Store reauthorization notifications still open the Dashboard.
- [x] A notification with an unrecognised category navigates to no screen and throws nothing.
- [x] Marking a tapped notification read still happens on tap, unchanged.
- [x] The inbox renders identically to before this change — same icons, same category colours.
