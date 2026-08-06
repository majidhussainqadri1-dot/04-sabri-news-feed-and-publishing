# File 04 v1.2.0 Security, Privacy and Resilience

- File 00 current-action assurance and File 21 compatibility fail closed.
- Activation mutations are preceded by a signed handover and followed by automatic compensation on failure.
- Source post/comment/term/meta writes are blocked across normal WordPress APIs.
- Patient-case records require explicit consent evidence and a supported File 21 consent-migration contract; otherwise they are quarantined.
- Unknown metadata and explicitly governed legacy language/video/tag/featured/pinned/media/source-ledger fields, attachments, non-approved comments and comment metadata are quarantined rather than silently lost.
- Backup and restore references are stored as hashes; an approved external staging verifier must attest restoration.
- Audit records are HMAC chained and contexts are redacted.
- No secrets, identity documents, patient charts, production dumps or private runbooks belong in the public repository/package.
- Migration and rollback use MySQL advisory locks, bounded batches, persistent cursors and idempotency keys.
- Uninstall is non-destructive; retirement does not delete the legacy source.
- Historical view aggregates are transferred through a deterministic synthetic ledger row without exposing viewer identity.
- Retirement requires checksum-bound, bounded route-manifest handoff to a verified canonical provider; the retired plugin is inert and self-deactivating.
