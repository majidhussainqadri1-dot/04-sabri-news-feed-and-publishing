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
| 28 | **PENDING — not yet claimed.** |
| 29 | **PENDING — not yet claimed.** |
| 30 | **PENDING — not yet claimed.** |
| 31 | **PENDING — not yet claimed.** |
| 32 | **PENDING — not yet claimed.** |
| 33 | **PENDING — not yet claimed.** |
| 34 | **PENDING — not yet claimed.** |
| 35 | **PENDING — not yet claimed.** |
| 36 | **PENDING — not yet claimed.** |
| 37 | **PENDING — not yet claimed.** |
| 38 | **PENDING — not yet claimed.** |
| 39 | **PENDING — not yet claimed.** |
| 40 | **PENDING — not yet claimed.** |
| 41 | **PENDING — not yet claimed.** |
| 42 | **PENDING — not yet claimed.** |
| 43 | **PENDING — not yet claimed.** |
| 44 | **PENDING — not yet claimed.** |
| 45 | **PENDING — not yet claimed.** |
| 46 | **PENDING — not yet claimed.** |
| 47 | **PENDING — not yet claimed.** |
| 48 | **PENDING — not yet claimed.** |
| 49 | **PENDING — not yet claimed.** |
| 50 | **PENDING — not yet claimed.** |
| 51 | **PENDING — not yet claimed.** |
| 52 | **PENDING — not yet claimed.** |
| 53 | **PENDING — not yet claimed.** |
| 54 | **PENDING — not yet claimed.** |
| 55 | **PENDING — not yet claimed.** |
| 56 | **PENDING — not yet claimed.** |
| 57 | **PENDING — not yet claimed.** |
| 58 | **PENDING — not yet claimed.** |
| 59 | **PENDING — not yet claimed.** |
| 60 | **PENDING — not yet claimed.** |
| 61 | **PENDING — not yet claimed.** |
| 62 | **PENDING — not yet claimed.** |
| 63 | **PENDING — not yet claimed.** |
| 64 | **PENDING — not yet claimed.** |
| 65 | **PENDING — not yet claimed.** |
| 66 | **PENDING — not yet claimed.** |
| 67 | **PENDING — not yet claimed.** |
| 68 | **PENDING — not yet claimed.** |
| 69 | **PENDING — not yet claimed.** |
| 70 | **PENDING — not yet claimed.** |
| 71 | **PENDING — not yet claimed.** |
| 72 | **PENDING — not yet claimed.** |
| 73 | **PENDING — not yet claimed.** |
| 74 | **PENDING — not yet claimed.** |
| 75 | **PENDING — not yet claimed.** |
| 76 | **PENDING — not yet claimed.** |
| 77 | **PENDING — not yet claimed.** |
| 78 | **PENDING — not yet claimed.** |
| 79 | **PENDING — not yet claimed.** |
| 80 | **PENDING — not yet claimed.** |

## Evidence boundary

Repository/source review only. Hostinger staging, real File00/File21/File26 providers/data, browser/device accessibility, isolated restore, rollback/DR rehearsal, Founder approval, live deployment and operational monitoring remain separate external gates.
