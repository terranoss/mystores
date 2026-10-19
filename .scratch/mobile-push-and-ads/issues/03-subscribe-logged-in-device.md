# 03: Subscribe a logged-in device to push

**What to build:** The operator logs into the mobile app and their device becomes eligible to receive pushes for the workspace they just signed into — with the app closed, with nothing else open. Granting notification permission is a single prompt. If the server is unreachable the operator still gets into the app. This slice ends at "the device is correctly bound and I can see it"; opening a tapped push and detaching on logout are the next slice.

**Blocked by:** 02: Register a device against a workspace

**Status:** ready-for-agent

- [ ] The push SDK is added and the app starts it during app startup.
- [ ] Android 13+ notification permission is requested once, and is not gated on authentication.
- [ ] Permission being previously denied does not break startup or login.
- [ ] Becoming authenticated registers the device, then sets the push external identity from the workspace public identifier the server returned.
- [ ] The push lifecycle is reachable through a narrow seam with a fake, so the sequence of calls can be asserted in a test.
- [ ] A test asserts the register ordering: authenticated, then register device, then set external identity.
- [ ] A test asserts that a registration failure leaves the operator logged in with the app fully usable.
- [ ] Registration failures are logged and swallowed — they never surface to the operator as an error and never block entry to the app.
- [ ] No external credential is hardcoded; anything supplied at build time is supplied at build time.
- [ ] The app remains pointed at whatever server the operator connected to, with no extra per-install configuration beyond what connecting already asks.
