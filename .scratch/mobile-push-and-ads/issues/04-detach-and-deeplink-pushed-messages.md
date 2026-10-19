# 04: Detach the device and open tapped pushes at the right screen

**What to build:** When the operator logs out, switches to a different store server, or has their session revoked, their device stops being a push target for the workspace they have left. When a push arrives and they tap it, the app opens at the screen that message is about and the unread badge is current. This slice carries the ordering that keeps one installation's pushes away from another installation's devices.

**Blocked by:** 01: Extract the notification category→route mapping into a shared helper, 03: Subscribe a logged-in device to push

**Status:** ready-for-agent

- [ ] An explicit user logout unregisters the device *before* the local session is cleared, while the token is still valid, and then detaches the push identity locally.
- [ ] Switching store servers goes through that same path, so the previous server's pushes stop immediately.
- [ ] An involuntary local logout — the unauthorized-response path, which is contractually local-only — detaches the push identity but makes no network call and does not unregister the device.
- [ ] Logging in again after an involuntary logout re-registers and corrects the binding, so a token expiry never permanently breaks delivery.
- [ ] A test asserts the explicit-logout ordering, including that unregistration precedes session clearing.
- [ ] A test asserts the involuntary-logout path performs no unregistration call.
- [ ] Tapping a push opens the screen the message is about, using the same shared mapping the inbox uses — not a second copy of it.
- [ ] Tapping a push refreshes the unread badge.
- [ ] A push about a Broadcast — a hand-composed message with no backing inbox row — navigates correctly and does not appear in the inbox or inflate the unread count.
- [ ] A manual verification checklist is written down in the mobile app's README: install, grant permission, confirm the device bound, send a test Broadcast, tap it and confirm the destination, switch store and confirm the device is gone.
