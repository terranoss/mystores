# Spec: Release MyStore as Free Open Source

Status: ready-for-agent

## Problem Statement

MyStore was built as a commercial self-hosted product and carries a licensing
subsystem that exists purely to enforce a purchase. An operator who wants to run
MyStore has to buy a perpetual license key from a third-party storefront and
enter it into the installer's second step before the application will proceed.
Activation calls out to a remote licence server over HTTPS, verifies an RS256
JWT, and caches the result to disk.

The licensing subsystem is also the project's largest source of confusion. It
is frequently conflated with two unrelated things: the install lock (a one-shot
marker that stops the installer being re-run) and the missing software license.
In fact, **MyStore had no `LICENSE` file at all**. The README carried an MIT
badge and a `[LICENSE](LICENSE)` link pointing at a file that did not exist, and
the backend package manifest declared `"license": "MIT"`. Nobody was legally
permitted to use, modify, or redistribute the project.

The owner wants MyStore released free to the public.

## Solution

MyStore becomes free and open source under the MIT License, with no purchase,
no license key, no activation call, and no phone-home of any kind.

The licensing subsystem is deleted outright rather than made optional, so there
is no residual path that can block an install or fail without a network. A real
`LICENSE` file is added at the repository root. The installer loses its License
step and runs in four steps: Requirements → Database → Admin → Ready.

The distinction that caused the confusion is written down: the **install lock**
is retained and renamed in the domain vocabulary, and it is explicitly *not* an
entitlement. It grants nothing, expires never, and is verified by nobody.

## User Stories

1. As an operator evaluating MyStore, I want to read the licence terms before
   installing anything, so that I know what I am legally allowed to do.
2. As an operator evaluating MyStore, I want the licence to be a real file in the
   repository rather than a badge pointing at nothing, so that I can verify the
   grant myself.
3. As an operator installing MyStore, I want to complete setup without a
   purchase, so that I am not blocked on a commercial transaction.
4. As an operator installing MyStore, I want the installer to not require
   outbound network access to a licence server, so that installation works in
   air-gapped or firewalled hosting.
5. As an operator installing MyStore, I want the wizard to have one fewer step,
   so that setup is faster and has less to go wrong.
6. As an operator installing MyStore, I want the requirements check to remain the
   first step, so that I learn about a missing PHP extension before I start
   entering credentials.
7. As an operator installing MyStore, I want no field anywhere in the wizard
   that hints at a purchase, so that I am not left wondering what I am missing.
8. As an operator installing MyStore, I want the install to work with no
   `license_key` field present in the request, so that an older client fails
   cleanly rather than mysteriously.
9. As a self-hoster, I want to modify MyStore and run my own fork, so that I can
   adapt it to my business without asking permission.
10. As a self-hoster, I want to host MyStore as a service for my own customers,
    so that I can charge for hosting without a licence negotiation.
11. As a self-hoster, I want the install lock to remain, so that nobody can
    re-run the installer against my live installation.
12. As a self-hoster, I want to understand that the install lock is not a
    licence, so that I do not mistake one for the other.
13. As a self-hoster, I want to know that deleting the install lock re-opens the
    installer, so that I can make an informed decision about whether to block
    the route at my web server.
14. As a self-hoster, I want the API to serve requests with no install lock
    present, so that a fresh clone is not blocked by an unexplained 503.
15. As a self-hoster, I want clear guidance that I should block the installer
    route after setup, so that I close the re-install hole myself.
16. As a contributor, I want the licensing history to be explained, so that I do
    not "restore" a licence gate I stumble across in git history and think
    feature gating is a missing feature.
17. As a contributor, I want a glossary, so that I use the project's vocabulary
    rather than inventing my own.
18. As a contributor, I want to know that introducing a gate requires an ADR
    conversation, so that entitlement is a deliberate decision.
19. As a documentation reader, I want the install manual to describe four steps,
    so that the documentation matches the application I am actually running.
20. As a documentation reader, I want no instructions telling me to enter a
    licence key, so that I am not blocked by a stale manual.
21. As a documentation reader, I want to be told to block the installer route
    after setup, so that the manual carries the security responsibility that
    the middleware previously carried.
22. As a release engineer, I want the packaging tooling to build without
    referencing a licence service, so that releases do not fail a stale
    pre-flight check.
23. As a release engineer, I want packaging to verify that the open-source
    licence file ships, so that a release cannot go out unlicensed.
24. As a release engineer, I want the packaging output to describe the product as
    MIT-licensed and free, so that recipients are not asked for a key.
25. As a release engineer, I want paid-storefront automation gone from the
    repository, so that a public codebase does not contain scripts whose only
    purpose is to sell it.
26. As a release engineer, I want stale build output from paid releases cleaned
    up, so that a licensed installer is not shipped by accident.
27. As a security reviewer, I want the removal to be traceable, so that I can
    confirm no entitlement path survives anywhere in the codebase.
28. As a security reviewer, I want to see that no licensing columns, plan tiers,
    or subscription state exist in the schema, so that I know the removal was
    complete rather than superficial.
29. As an existing customer who already bought a licence, I want to
    understand my position, so that I know what happens to code I already have.
30. As an existing customer, I want to keep using the copy I already have, so
    that the open-sourcing does not retroactively break my deployment.

## Implementation Decisions

- **Licence chosen: MIT, holder "MyStore Contributors".** AGPL-3.0 was
  considered and rejected — maximum adoption was preferred over preventing a
  hosted competitor. Any additional restriction (no-resale, no-trademark) would
  have removed the OSI grant entirely, so no such clause is added.
- **The licensing subsystem is deleted, not deprecated.** No licence key field,
  no activation endpoint, no activation service, no machine fingerprinting, no
  hand-rolled JWT verification, and no cached licence certificate on disk.
- **There was never a runtime gate beyond the install lock.** No plan tiers,
  trial periods, subscription status, or per-feature entitlement existed in the
  schema, the services, the frontend, or the mobile app, so no entitlement
  removal is required anywhere else.
- **The activation endpoint is removed outright**, not shimmed with a 410. The
  project has no external API consumers to protect, so a compatibility response
  would be pure cost.
- **The installer's activation requirement is removed from the execute
  contract**, including the secondary re-verification of a cached licence. The
  request no longer carries a key.
- **A dedicated install-lock service replaces the licensing service.** It owns
  exactly two behaviours: reporting whether the installer has run, and recording
  that it has. It writes the same lock file as before; the name is unchanged so
  that deployments and tests are not disturbed. The service is documented in
  code as an install lock and explicitly not an entitlement.
- **The global installation-check middleware is deleted.** It previously gated
  every request behind the lock file, returning 503 or redirecting when absent.
  With it gone, the API serves requests unconditionally and the lock is enforced
  only by the installer's execute action, which refuses once the lock exists.
- **The installer drops from five steps to four:** Requirements → Database →
  Admin → Ready. No replacement step is introduced, because the first step was
  already a full requirements check of PHP version, extensions, and writable
  paths — a separate step would have duplicated it.
- **The first step is renamed** from "Preflight" to "Requirements", and its
  continuation affordance no longer refers to a licence step.
- **The installer's completion summary relabels the lock** from a security lock
  to an installer lock, since it is no longer what secures the installation.
- **The install wizard's footer licence statement is replaced** with an MIT
  attribution and drops the third-party licensing attribution.
- **A root licence file is added** and the package manifests are made
  consistent with it, including the root manifest which previously declared no
  licence at all.
- **The README licence section is rewritten** to state the project is free and
  open source, to state plainly that no key, activation, or purchase is needed,
  and to record that MIT is irrevocable so previously distributed copies retain
  their grants.
- **The buyer-facing install manual is updated** to match the new four-step
  wizard, to remove all licence-key instructions, and to carry the instruction
  to block the installer route after setup.
- **Paid-storefront design documentation is deleted**, since a public repository
  should not ship a description of how the removed licence system was built, its
  product slug, its pricing, or its key format.
- **Commerce automation is deleted**, including the scripts that created and
  repriced the product on the third-party storefront. The corresponding
  storefront listing was removed separately by the owner and lives outside this
  repository.
- **Remaining release packaging is retained but reframed** as open-source
  distribution tooling. Its pre-flight checklist no longer asserts that the
  licensing engine exists and is configured for a product code; it now asserts
  that the root licence file exists and declares MIT. Its generated quick-start
  no longer instructs the recipient to enter a licence key, describes four
  steps instead of five, and adds the instruction to block the installer route.
  Stale artefact exclusions for the licence certificate are removed.
- **Stale paid-release build output is deleted.** It was already excluded from
  version control, so this is local cleanup only and carries no repository
  effect.
- **A new ADR records the decision**, covering the context that no licence file
  existed, the five substantive decisions, the accepted consequence that anyone
  may host MyStore as a service, the operational consequence that deployments
  must block the installer themselves, the irrevocability of the prior grants,
  and the alternatives that were rejected with reasons.
- **A glossary is created**, leading with the install-lock-versus-licence
  distinction that caused the original confusion, and also pinning down
  workspace, store, platform, superadmin, stock, sync, and marketplace token
  terms that were previously only implicit in code.
- **Agent notes are corrected.** The install-lock gotcha is rewritten to describe
  the new behaviour and to state that there is no global middleware; a new
  gotcha states that there is no licence gate and that adding one is an ADR
  conversation; the automation warning is replaced with one about local-only
  packaging tooling; and the glossary/ADR locations are documented.

## Testing Decisions

- **A good test asserts external behaviour only.** Assertions go through HTTP
  against the installer endpoints, or against the install-lock service's public
  interface. No test reaches into private methods, reads source files, or asserts
  on the internal shape of a service beyond its documented contract.
- **One seam is used, and it already exists.** All installer behaviour is covered
  by the existing installer feature test file, which exercises the HTTP surface.
  No new test seam is introduced anywhere in the change.
- **Coverage of what was removed.** The three tests that exercised fingerprint
  determinism, JWT signature rejection, and licence-key validation are deleted,
  because the behaviour they described no longer exists. Keeping them would have
  meant testing removed code.
- **Coverage of what replaced it.** Two tests are added at the same seam. One
  asserts that the install action refuses once the lock file exists, which is the
  behaviour that the deleted middleware used to provide. One asserts that
  recording the lock makes the install report as installed and that the recorded
  payload carries an installation timestamp and a version.
- **Prior art.** The installer feature test file is the pattern: Laravel feature
  tests issuing JSON requests, asserting status codes and JSON fragments, with
  filesystem state cleaned up in teardown so tests do not leak an installed lock
  into one another.
- **Teardown simplification.** The teardown no longer deletes a licence
  certificate artefact, since none is produced.
- **No network is required by any test.** The suite makes no external calls and
  needs no credentials, which is now true of the installer tests by
  construction rather than by stubbing.
- **Frontend verification is lint and typecheck only.** The wizard change is
  verified by the existing lint pass rather than by new component tests, because
  the removed step was pure presentation and the repository has no existing
  component-test convention for the installer.

## Out of Scope

- **Any change to the Stacklane or Stripe production systems.** The storefront
  listing was already removed by the owner. Nothing in this change touches those
  services, and no script in this repository is executed against them.
- **Recovering or clawing back licences already granted.** MIT is irrevocable;
  copies already distributed keep whatever rights they were granted.
- **Introducing a replacement monetisation model**, such as paid support,
  hosting, or a sponsorship programme.
- **Adding per-feature gating of any kind**, including soft feature flags,
  premium tiers, or usage limits.
- **Hardening the installer beyond what was decided.** The decision to delete the
  global middleware and rely on the controller guard is recorded and accepted.
  Adding route-blocking middleware, authentication on the installer, or a
  re-entrancy warning is a separate change.
- **Renaming the install lock file or directory.** It was deliberately left
  alone; renaming would churn deployments, tests, and ignore rules for no gain.
- **Cleaning up the remaining pre-existing lint errors** across the frontend, or
  the single pre-existing lint error in the installer wizard.
- **Reworking the competitor analysis document**, which remains as reference
  material.
- **Making the demo or the Playwright specs aware of the change.**

## Further Notes

- The removal was driven by a design-tree grilling session, in which the
  ambiguity between "commercial licence key" and "software licence" was
  identified as the central problem — the request was initially about the former
  while the blocker for going public was the latter.
- **Verification performed.** The full backend suite passes (254 tests, 1001
  assertions), including the installer feature tests. The frontend lint pass
  reports no new problems attributable to this change; the single error in the
  installer wizard is present at the previous commit and was confirmed
  pre-existing by comparing against a clean tree.
- **Single security note worth repeating.** With the global middleware removed, a
  deployment that has not blocked the installer route at its web server exposes a
  re-runnable installer. This is recorded in the agent notes, the README-adjacent
  documentation, the generated release quick-start, and the ADR.
- **State at time of writing.** The change is implemented in the working tree and
  verified, but not committed. The spec is published as the record of the
  decision.