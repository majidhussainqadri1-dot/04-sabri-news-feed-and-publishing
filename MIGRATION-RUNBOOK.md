# File 04 v1.2.0 Migration Runbook

## Preconditions

- Hostinger staging snapshot exists.
- Accepted File 00 and File 21 are active and compatible.
- Operator has current File 00 step-up and File 21 migration capability.
- File 04 activation handover completes without compensation errors.

## Procedure

1. **Inventory lock:** capture complete bounded counts/checksums/signature.
2. **Complete dry-run:** scan all active legacy IDs; each ID receives a persisted eligible/quarantine row.
3. **Exception review:** explicitly quarantine unsupported language/video/tag/featured/pinned/media/source-ledger fields, patient cases, attachments or comment metadata unless an approved File 21 canonical migration contract exists; never edit source records directly.
4. **Backup/restore proof:** provide artifact hashes, isolated restore references/counts/signature and approved verifier evidence.
5. **Bounded migration:** maximum 100 publications per publication batch.
6. **Interaction resume:** repeat `/interactions/resume` for `interaction_pending` mappings until status is migrated or a conflict is opened. Verify table-based interactions and the historical meta-only `_snp_views` aggregate.
7. **Reconciliation:** verify publication projection, mappings, comments, interactions, source totals and final delta.
8. **Cutover:** cache invalidation and search reindex adapters must return signed accepted evidence.
9. **Fallback:** open only the time-bounded read-only window.
10. **Retirement handoff:** the canonical route owner must acknowledge every redirect/gone batch and verify the final manifest checksum before File 04 becomes inert and self-deactivates.
11. **Acceptance:** capture logs, reports, screenshots, database evidence and Founder decision.

## Prohibited shortcuts

- No direct target writes from File 04.
- No skipped complete dry-run.
- No backup reference without actual restore rehearsal.
- No unbounded query or one-shot interaction assumption.
- No cutover while open conflicts or pending interactions exist.
