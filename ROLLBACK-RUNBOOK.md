# File 04 Non-Destructive Rollback Runbook

## Trigger conditions

Rollback is required when canonical content, comments, interactions, routes, permissions, privacy behavior or reconciliation differ from approved evidence.

## Preconditions

- Current File 00 step-up assurance and canonical migration capability.
- Recent valid backup/restore proof.
- Explicit legacy IDs and unique rollback idempotency key.
- Exact current lifecycle state/version.
- No target modification after migration. A changed target is quarantined because automatic rollback could erase legitimate post-cutover work.

## Execution

1. File 04 compares each canonical target with its recorded target checksum.
2. File 21's `LegacyPublicationRollback::rollback_selected()` makes canonical targets non-public and marks its mapping rolled back.
3. File 04 restores interaction rows to their pre-migration status; newly inserted rows become safely inactive rather than being deleted.
4. Source records remain untouched.
5. File 04 mapping becomes `rolled_back`, and the lifecycle recovers to `batch_migration`.
6. A redacted rollback proof and audit event are recorded.

## Replay

After correcting the cause:

1. lock/verify inventory;
2. dry-run the rolled-back records again;
3. migrate with a new idempotency key;
4. reconcile;
5. re-test redirects and privacy/cache behavior.

## Prohibitions

- Do not delete source records.
- Do not delete canonical interaction history directly.
- Do not force rollback over a modified target.
- Do not use database repair SQL against File 21 from File 04.
- Do not retire until a rollback rehearsal has been followed by successful replay and green reconciliation.
