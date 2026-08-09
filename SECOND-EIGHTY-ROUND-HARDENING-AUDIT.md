# File 04 — Second Fresh Eighty-Round Hardening Audit

**Candidate release:** 2.0.5  
**Storage schema:** 1.3.0  
**Baseline reviewed:** main `b16fce24cd743ff0f84b082643457b44fc85eec8`  
**Method:** each round is a fresh review on the corrected source; every found defect is corrected before the next round. The historical first 80-round audit is ancestry evidence only and is not counted here.

| Round | Result / correction |
|---:|---|
| 1 | **Defect.** Checkpoint ID normalization could alias malformed negative IDs to positive IDs; strict canonical positive checkpoint IDs now fail closed. |
| 2 | **Defect.** Taxonomy read errors could masquerade as an empty term set in source/target checksum projections; checksum/equivalence now fails closed. |
| 3 | **Defect.** Audit event lookup trusted matching JSON without authenticating the row HMAC; matching rows are now cryptographically authenticated. |
| 4 | **Defect.** Empty sanitized audit action identifiers could create/query ambiguous evidence; empty actions are rejected. |
| 5 | **Defect.** REST integrity status surfaced unsigned/tampered last-check fields; only valid signed evidence is surfaced. |
| 6 | **Defect.** REST status surfaced dry-run/reconciliation fields without authenticated report/audit binding; untrusted reports are hidden. |
| 7 | **Defect.** Mapping upsert allowed legacy ID zero/arbitrary status strings; positive identity and exact status allowlist are required. |
| 8 | **Defect.** Malformed non-empty mapping checksums were silently blanked; malformed checksums now reject the write. |
| 9 | **Defect.** Mapping reads/deletes used `absint` aliasing; canonical positive IDs are now required. |
| 10 | **Defect.** Mapping target IDs/types/run UUIDs were normalized or unconstrained; exact nonnegative target ID, target type and UUID4 semantics are enforced. |
| 11 | **Defect.** Dry-run row writes accepted malformed source/run identity evidence; UUID/signatures/checksum/ID/target type are validated. |
| 12 | **Defect.** Dry-run row reads accepted aliased request identities and malformed stored checksum/eligible evidence; both request and row evidence fail closed. |
| 13 | **Defect.** Malformed interaction progress cursor/boolean state could skip work; progress schema is validated before use. |
| 14 | **Defect.** File21 interaction-provider context used `absint` aliases; provider identity inputs now require canonical positive IDs. |
| 15 | **Defect.** File21 target resolution normalized malformed input/output IDs; exact positive IDs are required on both sides. |
| 16 | **Defect.** File21 migrate/rollback boundary accepted unvalidated ID batches/actor IDs; positive unique bounded batches are enforced. |
| 17 | **Defect.** Operation run creation allowed arbitrary operation/status/UUID values; create-run now requires migrate/rollback, running, UUID4. |
| 18 | **Defect.** Run finalization allowed arbitrary terminal status/UUID; exact terminal allowlist and UUID4 are required. |
| 19 | **Defect.** Existing run-ledger lookup/row state lacked strict operation/hash/UUID/status/signature validation; corrupt rows now fail closed. |
| 20 | **Defect.** REST `expected_version` used `absint`, so negative values could alias positive lifecycle versions; exact positive integer semantics are enforced. |
| 21 | **Defect.** `schema_healthy()` trusted a stale unsigned health option rather than current physical schema; physical schema is verified once per request. |
| 22 | **Defect.** Schema upgrade fast path could skip repair because stale health option said OK; fast path now requires current physical schema health. |
| 23 | **Defect.** Activation ignored persistence failure for plugin/schema health/version evidence; activation now compensates/fails closed. |
| 24 | **Defect.** Mandatory daily integrity cron scheduling failure was ignored; activation now blocks/compensates if scheduling fails. |
| 25 | **Defect.** Page-quarantine status surfaced an unsigned option; it now reads only signed activation-handover evidence. |
| 26 | **Defect.** Material corrections were still labelled v2.0.4; runtime/docs/current tests/builder/CI were promoted to v2.0.5 while storage schema stays 1.3.0. |
| 27 | **Defect.** The new independent 80-round review had no distinct permanent audit/gate; this audit record and `tests/run-second-eighty-round-hardening.py` establish that permanent trace. |
| 28 | **Defect.** The historical first-eighty regression gate contained stale brittle assertions after stronger controls changed implementation markers; it was aligned to the strengthened v2.0.5 controls without weakening the historical invariants. |
| 29 | **Defect.** Physical schema verification checked names but not column type/nullability/default contracts; exact critical column contracts are now checked. |
| 30 | **Defect.** Index verification did not reject prefix indexes; `Sub_part` is now inspected and prefix indexes cannot satisfy full identity/index contracts. |
| 31 | **Defect.** Database lock names/timeouts were normalized, allowing aliases; lock names and timeout are now exact bounded values with DB-error checks. |
| 32 | **Defect.** Database lock-release failure was silent; release is verified and a privacy-safe critical operational alert is emitted on failure. |
| 33 | **No new defect.** Deactivation/scheduler shutdown semantics were re-reviewed; inactive-plugin cron callbacks cannot execute loaded File04 code, and activation/retirement controls remained fail-closed. |
| 34 | **Defect.** REST mapping-status evidence could silently omit unknown/corrupt mapping states; exact status/count validation now blocks corrupt evidence. |
| 35 | **No new defect.** Mapping/conflict `COUNT(*)` representation was freshly rechecked after R34; strict nonnegative decimal validation is sufficient for the queried DB aggregate surface. |
| 36 | **Defect.** Conflict-ledger operations referenced fields absent from the actual physical conflict table and omitted required fingerprint data; persistence/resolution was realigned to the declared schema, fingerprinted, locked and integrity-checked. |
| 37 | **Defect.** R10 target-type validation accidentally omitted the legitimate `source_only` quarantine disposition; `source_only` was restored to the exact allowlist. |
| 38 | **Defect.** Quarantine disposition still used lossy normalized IDs; it now requires positive unique canonical IDs. |
| 39 | **Defect.** Core migration still used lossy normalized IDs; migration batches now require positive unique canonical IDs. |
| 40 | **Defect.** Core rollback still used lossy normalized IDs; rollback batches now require positive unique canonical IDs. |
| 41 | **Defect.** File21 rollback results were trusted without result-envelope/provenance verification; returned rolled-back/skipped IDs are now bounded to the requested batch and rolled-back targets are verified. |
| 42 | **Defect.** `migration_target_valid()` still aliased malformed IDs with `absint`; exact positive IDs are now required. |
| 43 | **Defect.** `rolled_back_target_valid()` still aliased malformed IDs; exact positive IDs are now required. |
| 44 | **Defect.** Orphan-containment identity values still used `absint`; legacy/target/actor IDs now require canonical positive identities. |
| 45 | **Defect.** Interaction resume normalized actor/legacy IDs; exact positive identities are now required. |
| 46 | **Defect.** Interaction record budget normalized malformed/negative values; only exact bounded integer budgets are accepted. |
| 47 | **Defect.** Legacy interaction source-row IDs could alias through `absint`; exact positive source-row IDs are required. |
| 48 | **Defect.** Legacy interaction table-probe DB errors could look like absent tables and falsely imply no work; DB probe errors now block with explicit source-schema errors. |
| 49 | **Defect.** Future18 REST ID schemas allowed weak scalar/array identity normalization; exact positive scalar IDs and positive unique bounded ID arrays are now enforced. |
| 50 | **Defect.** Future18 Digital Twin used lossy normalized ID batches; it now uses strict positive unique canonical IDs. |
| 51 | **Defect.** Redirect/Citation Observatory used lossy normalized ID batches; it now uses strict positive unique canonical IDs. |
| 52 | **Defect.** Future18 direct scalar methods still normalized legacy/target/actor IDs; shared strict positive identity validation now covers those call paths. |
| 53 | **Defect.** A signed rollback proof could be stale when used for retirement confidence; rollback proof must now be current-source-bound, recent, UUID-bound and authenticated by the rollback audit event. |
| 54 | **Defect.** Stored DR GameDay evidence could remain accepted indefinitely; retirement confidence now requires fresh signed current-source/request-bound GameDay evidence and its authenticated audit event. |
| 55 | **Defect.** File00/File21/File26 contract descriptors were not bound to the current source/manifest/request/freshness window; exact echo binding, provider identity and a 15-minute verification window are now required. |
| 56 | **Defect.** REST `/backup-proof` called the core backup-proof function with an incompatible scalar signature and incomplete evidence; the route now accepts/builds the complete restore-evidence object expected by the core function. |
| 57 | **Defect.** WP-CLI status invoked a nonce-protected REST status path without a nonce/current read actor; CLI now verifies read authority and supplies a REST nonce. |
| 58 | **Defect.** CLI lifecycle versions were arbitrary strings and could reach lossy core coercion; `--expected-version` now requires an exact positive decimal integer. |
| 59 | **Defect.** Fallback hours/lifecycle version were normalized with `absint`; both are now exact bounded integers. |
| 60 | **Defect.** Dry-run limit/lifecycle version were normalized with `absint`; both are now exact bounded integers. |
| 61 | **Defect.** REST fallback exposed a `minutes` parameter while the core interpreted the value as hours; the REST contract now uses bounded `hours` consistently. |
| 62 | **Defect.** REST cutover callback referenced nonexistent `SNFLA_Reconciliation::cutover()` instead of the canonical `approve_cutover()` method; the callback now invokes the real cutover API. |
| 63 | **Defect.** Reconciliation run still normalized lifecycle version via `absint`; it now requires an exact positive integer. |
| 64 | **Defect.** Cutover approval still normalized lifecycle version via `absint`; it now requires an exact positive integer through state assertion and transition. |
| 65 | **Defect.** The permanent second-eighty audit/gate/status trace had fallen behind R28-R64, so the repository could no longer prove which later corrections were part of this fresh audit. The audit record, deterministic gate and truthful status were brought through R65 before any final regression rounds are claimed. |
| 66 | **Defect.** Fresh exact-head QA exposed two source defects: the R36 conflict hardening had introduced literal escaped indentation that broke PHP syntax, and WP-CLI status referenced nonexistent `SNFLA_REST::NAMESPACE` instead of `SNFLA_REST::NS`. Both were corrected before continuing. |
| 67 | **Defect.** WP-CLI numeric options arrive as strings while the strengthened core expects canonical integers; explicit numeric overrides could therefore become unusable. A strict bounded decimal `integer_arg()` parser now normalizes only valid CLI numeric syntax into exact integers. |
| 68 | **Defect.** Mapping writes were strict but existing mapping rows were trusted on read. `mapping_row_valid()` now verifies legacy/target identity, target type, state, checksums, run UUID and progress JSON before any row is accepted. |
| 69 | **Defect.** Conflict-ledger integrity checked fingerprint shape but not fingerprint correctness and did not validate redacted JSON. The expected fingerprint is now recomputed and compared, and stored redacted JSON must decode cleanly. |
| 70 | **Defect.** Dry-run conflict codes were decoded and sanitized, allowing malformed stored values to alias canonical codes. Stored codes must now already be unique, nonempty canonical keys. |
| 71 | **Defect.** Core migration still accepted actor/version values through lossy equality/coercion. Migration now requires exact positive actor/version integers and uses exact lifecycle version/equality semantics. |
| 72 | **Defect.** Core rollback likewise allowed lossy actor/version semantics. Rollback now requires exact positive actor/version integers and exact actor identity. |
| 73 | **Defect.** `revalidate_actor()` itself normalized the expected actor with `absint`, so malformed direct callers could alias the current actor. Expected/current actor identity is now exact positive-integer equality. |
| 74 | **Defect.** Shared lifecycle primitives still normalized expected versions and did not strictly validate actor IDs. `transition()`, `assert_current()` and `recover_to_batch()` now enforce canonical positive identity/version semantics centrally. |
| 75 | **Defect.** Lifecycle audit-failure compensation restored old state/version without verifying restoration. Restoration is now verified; failed compensation raises a critical alert and manual-recovery error. |
| 76 | **Defect.** Conflict-resolution/supersession/system-resolution audit compensation did not explicitly escalate restoration failure. Exact restoration is now checked and critical operational alerts are emitted on compensation failure. |
| 77 | **Defect.** Interaction-ledger write paths still used lossy IDs/contribution coercion. Ledger writes now require strict legacy/target/canonical identity, source-row rules, strict contribution counts and boolean migration flags. |
| 78 | **Defect.** Interaction-ledger read/query/rollback paths still normalized identities, kinds, statuses and limits. Those paths now use strict positive/nonnegative IDs, kind/status allowlists, exact bounded limits and DB-error checks. |
| 79 | **Defect.** After R66-R78 the permanent second-eighty audit/status/gate no longer described or asserted the corrected source, so exact-head QA could not prove those rounds. The audit record and permanent deterministic gate were advanced through R79; R80 remains deliberately unclaimed until final fresh review. |
| 80 | **PENDING — not yet claimed.** |

## Current count before final regressions

Defect rounds so far: **1–32, 34, 36–79**. Clean rounds so far: **33, 35**. **R80 alone remains deliberately unclaimed** until one final fresh adversarial review and exact-head regression are completed on the corrected R79 source.

## Evidence boundary

Repository/source review only. Hostinger staging, real File00/File21/File26 providers/data, browser/device accessibility, isolated restore, rollback/DR rehearsal, Founder approval, live deployment and operational monitoring remain separate external gates.
