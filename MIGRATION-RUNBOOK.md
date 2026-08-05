# File 04 Migration Runbook

## Preconditions

1. Use WordPress staging, never the live site for the first execution.
2. Verify File 00, corrected identity/verification dependencies and File 21 package `1.0.3.2+` are active.
3. Assign canonical capabilities through File 00 governance:
   - `sabri_feed_run_migrations`
   - `sabri_file04_review_migrations`
   - `sabri_file04_retire_adapter` only to the final retirement authority.
4. Complete fresh two-factor step-up.
5. Create a database/files backup and prove a restore in an isolated staging copy.
6. Record the backup reference, SHA-256 and UTC creation time through `/backup-proof`.

## Controlled sequence

### 1. Inventory lock

Call `POST /inventory` with the current `expected_state` and `expected_version`. Preserve the returned source signature and counts.

Reject the run if source counts, tables, routes or maximum modification timestamp are unexpected.

### 2. Dry-run

Call `POST /dry-run` with `limit ≤ 100`. Review every candidate checksum and every conflict. A migration batch can contain only IDs in the current unchanged dry-run report.

Resolve a conflict only after independent evidence review. The resolution code must describe the actual decision; it is not a bypass.

### 3. Batch migration

Call `POST /migrate` with:

- explicit `legacy_ids` from the current dry-run;
- lifecycle state/version;
- a unique `Idempotency-Key` header;
- WordPress REST nonce;
- current File 00 step-up session.

The adapter locks the migration, verifies the source signature and delegates canonical writes to File 21. Legacy source data is retained.

Repeat dry-run and migrate for subsequent batches. Never exceed 100 publications or 10,000 interaction records per publication provider call.

### 4. Reconciliation

After no unmigrated candidate remains, call `/reconcile`. The report must show:

- source total equals verified mappings;
- every File 04 map agrees with File 21's mapping;
- target provenance matches the legacy ID;
- source and target checksums remain unchanged;
- zero open conflicts;
- `green: true`.

### 5. Redirect cutover

Call `/cutover` only against a green reconciliation report and valid backup proof. Test every representative legacy URL. During cutover/fallback redirects are temporary `302`; only retired mappings use permanent `301`.

### 6. Read-only fallback observation

Call `/fallback` with a bounded 1–168 hour observation window. File 04 remains read-only. Unmapped/unsafe legacy routes return a private, noindex `404/410`, never a source rendering or write surface.

### 7. Retirement

After the fallback window expires, complete a rollback rehearsal, replay migration, regain green reconciliation, and type the exact confirmation:

`RETIRE FILE 04 LEGACY ADAPTER`

Retirement preserves all source and evidence.

## Required evidence bundle

- inventory JSON and source signature;
- dry-run report checksum;
- backup/restore proof;
- migration run UUIDs and idempotency hashes;
- mappings and conflict dispositions;
- reconciliation report checksum;
- redirect test results;
- rollback proof;
- fallback timestamps;
- retirement evidence checksum;
- GitHub commit, CI run and release package SHA-256.
