# File 04 v1.3.0 — Public-Safe Operations, Release and Retirement Runbook

This runbook is deliberately public-safe. It defines roles, gates, evidence shapes and escalation semantics without storing production secrets, provider credentials, private incident details, backup locations or patient/user records.

## 1. Constitutional operating boundary

File 04 is a temporary legacy migration/compatibility adapter. File 21 owns canonical publications/Home/News/feed state; File 26 owns canonical search/discovery; File 20 owns the shell; File 25 owns visual-system presentation; File 24 coordinates assurance. File 04 must remain reversible, bounded and removable.

A successful command is not a successful migration. A green CI run is not staging acceptance. A staging acceptance is not live deployment. Live deployment is not operational completion.

## 2. Release rings

Every release moves only forward through these rings unless rollback is invoked:

1. **Local/source:** syntax, unit, architecture, central-plan and File 04 own-plan tests.
2. **Exact-head CI:** pinned dependencies, secret/PII indicator scan, PHP 8.1/8.3, deterministic package rebuild twice.
3. **Hostinger staging:** fresh install, supported upgrade, real File 00/File 21/File 26 contracts, representative legacy data, browser/RTL/accessibility, cache/search and restore checks.
4. **Staff/operator acceptance:** migration operator + content reviewer journeys, diagnostics, dry-run interpretation, quarantine and rollback drill.
5. **Canary migration:** explicitly approved bounded subset/window with parity metrics and stop conditions. This is migration control, not standing dual-write architecture.
6. **Gradual controlled cutover:** bounded batches, final delta, zero blocking conflicts, canonical route/search handoff and monitored redirect window.
7. **Full redirect/read-only fallback:** legacy writes remain closed; canonical File 21 routes own public truth.
8. **Retirement:** after fallback expiry, rollback proof, route/search handoff and Founder approval, self-deactivate and remove permanent dependency.

No ring may be skipped merely because an earlier ring is green.

## 3. Required evidence before staging approval

- exact branch/commit and v1.3.0 package checksum;
- deterministic package and complete-source rebuild equality;
- zero known unaccepted source-scope defects after two consecutive current-plan reviews;
- File 00 immutable author identity contract available;
- File 21 canonical migration, interaction, media/reference and rollback contracts available;
- File 26 legacy-resolution/search handoff contract available;
- locked source inventory and complete dry-run with counts, conflicts, disposition, storage/time estimate and sampled canonical preview;
- backup plus isolated restore proof bound to the current source signature;
- no secrets/private operational evidence committed to this public repository.

## 4. Migration operator procedure

1. Confirm lifecycle and exact expected version.
2. Run System Check; treat `blocker` as stop, `unknown` as unresolved evidence, never as success.
3. Capture/verify source inventory lock.
4. Run complete dry-run. Resolve or explicitly quarantine every blocking disposition.
5. Review storage/time estimates as planning estimates, not SLO guarantees.
6. Verify independently produced backup/restore evidence.
7. Select a bounded batch no larger than the source-enforced maximum.
8. Revalidate current File 00 actor, lifecycle, mapping and source signature after lock acquisition.
9. Run migration through File 21 canonical commands only.
10. Verify authorship, target provenance, media/reference coverage and interaction contribution ledgers.
11. If post-migration verification fails, contain/rollback the canonical target through File 21; never repair File 21 tables directly.
12. Reconcile batch counts/checksums and persist evidence before moving to the next batch.

## 5. Cutover procedure

Cutover is blocked until the current full reconciliation is green and current backup/restore proof is accepted.

Required cutover evidence:

- source signature unchanged;
- final delta captured and reconciled;
- zero unresolved migration conflicts;
- File 21 target provenance valid;
- File 21 interaction provider reconciled;
- cache invalidation requested and independently verified;
- File 26 search reindex/handoff requested and independently verified;
- canonical route manifest complete;
- smoke tests show canonical 301 redirects without loops;
- quarantined/gone records produce governed non-public outcomes;
- Founder-approved cutover change ticket.

## 6. Rollback and stop conditions

Immediately stop the current batch/ring on any of the following:

- source inventory/signature changes unexpectedly;
- File 00 authority or File 21 canonical contract becomes unavailable/incompatible;
- database error is detected in inventory/mapping/reconciliation;
- target provenance or media/reference verification fails;
- unexplained count/checksum divergence appears;
- cache/search handoff cannot be verified;
- accessibility/security/privacy critical defect is discovered;
- latency/error behavior breaches the staging budget and risks data integrity;
- backup/restore proof becomes stale or invalid.

Rollback is non-destructive. It must protect post-cutover File 21 changes, use a checkpoint, reject changed-target unsafe reversal and preserve the legacy source. Never delete legacy source data merely to make counts match.

## 7. Observability and SLO acceptance

File 04 records bounded privacy-safe endpoint metrics and exposes p75, p95 and error-rate summaries. Source code does **not** invent production SLO attainment.

Staging must establish measured budgets for:

- dry-run/report endpoints;
- migration-preflight endpoint;
- system-check/metrics endpoints;
- bounded migration batch duration;
- reconciliation duration;
- redirect latency and error rate;
- cache/search provider acknowledgement latency.

Any production SLO/error budget must be approved from real monitoring evidence. Raw patient/user content, credentials and identity documents must not appear in metrics or alerts.

## 8. Degraded dependency behavior

- **File 00 unavailable:** all privileged state-changing operations fail closed.
- **File 21 unavailable/incompatible:** migration, rollback and canonical-target actions fail closed; legacy public truth is not re-enabled.
- **File 26 unavailable:** migration may not be declared cutover-complete; search handoff remains pending/unknown.
- **File 24/assurance view unavailable:** native File 04 authorization/integrity controls remain active; assurance status is unknown, not secure-by-assumption.
- **Cache/search provider evidence missing:** cutover is blocked.
- **Monitoring provider unavailable:** local bounded evidence remains, operator escalation required; no false healthy state.

## 9. Support and escalation

The public support path may explain migration status and route users to canonical content, but it is not a medical-emergency channel and must not expose private migration evidence.

Escalation classes:

- **P0:** data-loss risk, unauthorized public exposure, canonical ownership breach, destructive rollback risk, compromised privileged access. Stop operations immediately; Founder/Security/Release owners are required.
- **P1:** migration/reconciliation blocker, provider incompatibility, broken canonical routing/search handoff, major accessibility failure. Pause affected ring and correct before continuing.
- **P2:** non-blocking documentation/operator UX issue with no integrity/privacy/accessibility consequence. Record, fix and retest before final operational sign-off unless explicitly accepted as time-bounded risk.

Public repository records only sanitized incident IDs/outcomes; sensitive diagnosis, credentials, forensic evidence and contact rosters stay in approved private operational systems.

## 10. Capacity and cost forecasting

Before canary/full migration, record estimates for:

- legacy record count and logical bytes;
- attachment/reference count and provider-supplied bounded media-byte estimate;
- migration batch throughput and planning range;
- temporary storage growth and backup generation cost;
- cache/search reindex workload;
- operator time and rollback window.

Capacity limits may reduce batch size or defer a ring; they must never weaken integrity, privacy, accessibility or safety controls.

## 11. Vendor resilience

For every critical external dependency used during migration/cutover, the private operational register must document owner, region where relevant, SLA, security posture, export capability, credential rotation path, outage behavior, subprocessors where applicable and exit/replacement plan. File 04 does not store those secrets here.

A critical provider with no acceptable exit/restore path is a launch blocker, not an undocumented assumption.

## 12. On-call / incident lifecycle

`detect → classify → contain → preserve evidence → communicate → recover → verify → close → postmortem`

Every incident record must identify severity, affected release ring, exact version/commit, source signature where relevant, owner, containment decision, recovery proof, user-impact communication requirement and follow-up corrective test. Postmortems are blameless in tone but explicit about accountable controls and prevention work.

## 13. Retirement acceptance

File 04 may be retired only when:

- File 21 canonical data and interactions reconcile green;
- File 26 route/search handoff is verified;
- zero unresolved legacy migration conflicts remain;
- redirect/read-only fallback window has expired under the approved policy;
- rollback proof is current;
- no active consumer depends on File 04 as a permanent backend;
- self-deactivation is verified;
- Founder approves retirement;
- monitoring confirms no material regression during the controlled window.

Retirement removes dependency, not historical evidence required by retention/audit policy.
