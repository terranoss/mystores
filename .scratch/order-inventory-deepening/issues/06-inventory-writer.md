# 06: Introduce the InventoryWriter

**What to build:** One module owns applying a local stock or price change and recording it exactly once. It exposes a begin, succeed and fail lifecycle for queued pushes and an explicit dirty-flag restore, so every path — direct push, queued stock job, linked-store sync, webhook and Lazada update — leaves exactly one consistent audit row. The price job that is never dispatched is deleted.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Every stock or price mutation leaves exactly one inventory log row.
- [ ] A direct push writes a completed row; a queued push writes a pending row and then success or failure.
- [ ] A successful queued push closes its pending row instead of adding a second.
- [ ] A failure records the error message and restores the needs-push marker only when asked.
- [ ] Simple and variant products write the same fields, carrying the old and new values for the change that occurred.
- [ ] The never-dispatched price job is deleted and no dispatch site remains.
- [ ] ProductService no longer exposes the local-apply method publicly and no longer owns a private recorder.
- [ ] Unit tests cover begin, succeed, fail and apply; existing inventory tests pass.
