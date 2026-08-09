# File 04 — Fresh Eighty-Round Hardening Audit

**Candidate release:** 2.0.4  
**Storage schema:** 1.3.0  
**Baseline reviewed:** main `4d6266413b377fda451894b1c7076f2ce93f50fc`  
**Method:** each round is a genuinely fresh Review → immediate correction when a defect is found → affected regression → next round on corrected source. Historical audits are ancestry evidence only and are not counted here.

| Round | Focus | Result / correction |
|---:|---|---|
| 1 | Daily integrity truth | **Defect.** Integrity evidence could ignore audit/persistence failure. Persisted signed `ok`/audit state and critical alerting. |
| 2 | Lifecycle version corruption | **Defect.** Corrupt/zero stored lifecycle version could normalize to 1. Version corruption now fails closed. |
| 3 | Lifecycle persistence compensation | **Defect.** Partial state/version rollback was assumed. Previous pair is now restored and verified or manual recovery is surfaced. |
| 4 | Checkpoint source identity | **Defect.** Empty/non-SHA signatures could match. Checkpoints require two valid 64-hex signatures before `hash_equals`. |
| 5 | Stale operation detection | **Defect.** Missing/malformed/future `started_at` could make a running operation immortal. Such state is now stale/corrupt. |
| 6 | Audit event lookup | **Defect.** Malformed matching audit JSON could be skipped in favor of older evidence. Matching corruption now fails closed. |
| 7 | Audit UTF-8 safety | **Defect.** Byte truncation could cut multibyte text and break JSON. UTF-8-safe bounded truncation added. |
| 8 | Audit key collisions | **Defect.** Sanitized keys could collide/overwrite evidence. Deterministic non-colliding normalized keys added. |
| 9 | Multisite legacy deactivation | **Defect.** Per-site activation could deactivate a network-wide legacy plugin. Network-wide state now requires network operator action. |
| 10 | Rewrite-rule handover checksum | **Defect.** JSON encoding failure was unchecked. Canonical encoding must succeed before checksum evidence is accepted. |
| 11 | Activation lifecycle initialization | **Defect.** Falsey/corrupt lifecycle options could be treated as absent. Existing corruption now blocks activation. |
| 12 | Legacy page-map ownership | **Defect.** An option map could point at an unrelated normal page. Quarantine candidates must still contain a recognized legacy shortcode. |
| 13 | Legacy page quarantine race | **Defect.** Only content checksum was revalidated. Captured slug, status and content must all still match. |
| 14 | Schema upgrade concurrency | **Defect.** Upgrade path lacked serialization. Added schema-upgrade lock and explicit health evidence. |
| 15 | Schema column completeness | **Defect.** Verification checked only subsets of declared columns. All declared custom-table columns are now verified. |
| 16 | Index shape integrity | **Defect.** Index name/uniqueness was checked without ordered columns. Exact ordered index columns are now verified. |
| 17 | Mutation against unhealthy schema | **Defect.** Protected mutation did not block known bad/mismatched File04 storage. Positive schema health is now required. |
| 18 | REST body idempotency token | **Defect.** Human-text sanitization could normalize opaque keys. Body token is now exact printable ASCII. |
| 19 | REST idempotency trimming | **Defect.** Header/body values were trimmed before comparison. Exact accepted bytes are preserved. |
| 20 | REST object IDs | **Defect.** Negative/duplicate IDs could be normalized later. Boundary now requires positive unique decimal IDs. |
| 21 | Fallback status evidence | **Defect.** Fields from invalid signed fallback evidence could be surfaced. Untrusted evidence is now hidden. |
| 22 | Retirement status evidence | **Defect.** Fields from invalid retirement evidence could be surfaced. Untrusted evidence is now hidden. |
| 23 | REST error status | **Defect.** Arbitrary metadata could yield an invalid HTTP status. Errors are clamped to 4xx/5xx. |
| 24 | Dry-run conflict JSON | **Defect.** Encoding failure could reach persistence. Dry-run candidate write now fails closed. |
| 25 | Conflict context JSON | **Defect.** Conflict context encoding failure/empty code could persist ambiguous evidence. Both now block the write. |
| 26 | Interaction original evidence | **Defect.** Malformed ledger JSON was treated as empty. It now returns explicit corruption error. |
| 27 | Interaction migration cursor | **Defect.** Cursor advanced before row outcome. It now advances only after safe migrate/skip completion. |
| 28 | Core migration idempotency | **Defect.** Migration trimmed the already validated opaque key. Core now preserves exact ASCII token semantics. |
| 29 | Rollback idempotency | **Defect.** Rollback trimmed the opaque key. Core rollback now preserves exact ASCII token semantics. |
| 30 | Restore table counts | **Defect.** `absint` could hide negative/bad evidence and extra keys. Counts must be exact non-negative integers with known keys. |
| 31 | Inventory compensation | **Defect.** Failed lifecycle transition assumed old inventory evidence was restored. Restoration is now verified. |
| 32 | Backup-proof compensation | **Defect.** Audit failure assumed previous proof restoration. Compensation is verified or manual recovery is flagged. |
| 33 | Retirement compensation | **Defect.** Failed retirement transition assumed previous evidence restoration. Compensation is now verified. |
| 34 | Rollback progress corruption | **Defect.** Destructive preflight used a helper that hid malformed progress JSON. It now uses checked progress and blocks corruption. |
| 35 | Resumable migration ledger reads | **Defect.** Warning/resume path used unchecked mapping/progress reads. Corruption and persistence failures now become blockers. |
| 36 | Partial rollback retry provenance | **Defect.** Active-mapping validation could reject a legitimately already-rolled-back target. Added separate rolled-back provenance validation. |
| 37 | Full activation-handover restore | **Defect.** Restore readiness ignored some unresolved local mapping states. Only rolled-back/quarantined dispositions are excluded from remaining work. |
| 38 | Reconciliation compensation | **Defect.** Audit failure assumed previous report restoration. Restoration is verified or manual recovery is surfaced. |
| 39 | Interaction reconciliation evidence | **Defect.** Malformed original JSON became empty baseline. Reconciliation now records corruption and fails closed. |
| 40 | Interaction rollback evidence | **Defect.** Malformed original JSON became empty baseline. Rollback now records corruption and refuses unsafe reversal. |
| 41 | System conflict resolution atomicity | **Defect.** Conflicts could remain resolved when audit write failed. Exact changed rows are compensated to open. |
| 42 | Dry-run conflict supersession atomicity | **Defect.** Superseded conflicts could remain changed after audit failure. Exact changed rows are compensated. |
| 43 | File21 result object scope | **Defect.** Unrequested migrated legacy IDs could be processed. Returned IDs are bounded to the requested batch. |
| 44 | Plan-preflight REST IDs | **Defect.** Secondary route had weaker ID validation. It now requires the same positive unique identity semantics. |
| 45 | Dry-run analysis audit binding | **Defect.** Analysis could persist/be accepted without its audit event. Audit failure now reverts verified evidence. |
| 46 | Meta-reference digest | **Defect.** Encoding failure could collapse digest input to the meta key. Canonical checksum encoding is now used. |
| 47 | Media-preflight freshness | **Defect.** File21 media preflight evidence could be replayed indefinitely. Added bounded verification timestamp. |
| 48 | Post-migration media verification freshness | **Defect.** Media verification could be stale/replayed. Added bounded freshness and strict count semantics. |
| 49 | Broken-link count typing | **Defect.** Nonnumeric input could become zero via `absint`. Evidence must be strict integer zero. |
| 50 | Deleted-author placeholder binding | **Defect.** Placeholder attestation was not bound to the exact legacy identity/request. Added request digest and identity echoes. |
| 51 | File21 returned collection scope | **Defect.** Only migrated collection was bounded. `migrated`, `skipped` and `warnings` IDs must all belong to the requested batch. |
| 52 | File21 migrated row shape | **Defect.** A malformed non-array migrated row could be consumed. Returned migrated rows now require array shape. |
| 53 | Emergency quarantine ledger failure | **Defect.** Mapping read/write failure could be suppressed during emergency quarantine. Checked reads and critical failure alerts added. |
| 54 | Positive physical schema health | **Defect.** Matching schema-version alone could look healthy. Protected operations require positive health evidence/verification. |
| 55 | Network-wide File04 retirement | **Defect.** One site could deactivate a network-active adapter globally. Network retirement now requires network operator action. |
| 56 | Mutation surface with corrupt lifecycle version | **Defect.** Mutation gate checked state string only. It now requires full `state_valid()` including lifecycle version. |
| 57 | Metrics persistence | **Defect.** Observability write failure was silent. Failed metrics persistence now raises an operational alert. |
| 58 | System Check schema gate | **Defect.** System Check omitted File04 storage health. Explicit schema status/blocker added. |
| 59 | File26 contract freshness | **Defect.** Manifest-bound File26 acceptance could remain stale. Fresh bounded verification time is now required. |
| 60 | Media storage estimate | **Defect.** Negative/nonnumeric provider evidence could be guessed/clamped. Estimate must be finite and non-negative. |
| 61 | WP-CLI object IDs | **Defect.** CLI `absint` changed negative identity and allowed duplicates. CLI now requires positive unique decimal IDs. |
| 62 | WP-CLI JSON output | **Defect.** Encoding failure could print blank success. CLI now returns explicit output-encoding error. |
| 63 | Admin signed evidence display | **Defect.** Raw invalid fallback/retirement evidence could be shown as if meaningful. Invalid evidence is explicitly marked and hidden. |
| 64 | Admin ledger DB read errors | **Defect.** Mapping/conflict DB failures could display as empty data. Admin now surfaces explicit read errors. |
| 65 | Release / QA integration | **Defect.** Runtime had moved to 2.0.4 while builder, WordPress stable tag, docs and historical/current QA gates still expected 2.0.3; CI failed. Harmonized release metadata, builder, CI and tests and added this permanent 80-round gate. |
| 66 | Canonical ownership / duplicate-backend regression | **PENDING — fresh review not pre-certified.** |
| 67 | File21 command/write boundary | **PENDING — fresh review not pre-certified.** |
| 68 | File26 search/discovery boundary | **PENDING — fresh review not pre-certified.** |
| 69 | Authentication / CSRF / current-action authority | **PENDING — fresh review not pre-certified.** |
| 70 | Privacy / PII / redaction / signed-evidence display | **PENDING — fresh review not pre-certified.** |
| 71 | Bounded queries / keyset traversal / performance | **PENDING — fresh review not pre-certified.** |
| 72 | Idempotency / locks / resumability | **PENDING — fresh review not pre-certified.** |
| 73 | Backup / restore / rollback compensation | **PENDING — fresh review not pre-certified.** |
| 74 | Redirect / fallback / loop / privacy behavior | **PENDING — fresh review not pre-certified.** |
| 75 | Quarantine / source-only / sensitive containment | **PENDING — fresh review not pre-certified.** |
| 76 | PHP 8.1 compatibility | **PENDING — fresh review not pre-certified.** |
| 77 | PHP 8.3 compatibility | **PENDING — fresh review not pre-certified.** |
| 78 | Accessibility / RTL / localization | **PENDING — fresh review not pre-certified.** |
| 79 | Deterministic packaging / secret-PII scan | **PENDING — fresh review not pre-certified.** |
| 80 | Final exact-head architecture / lifecycle truth | **PENDING — fresh review not pre-certified.** |

## Evidence boundary

This audit is repository/source evidence only. Hostinger staging, real File00/File21/File26 providers and legacy data, browser/device accessibility, isolated restore, rollback/DR rehearsal, Founder approval, live deployment and measured operations remain external gates and are not fabricated by this record.
