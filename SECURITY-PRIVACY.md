# Security, Privacy and Resilience Controls

## Authorization

Mutation requires an authenticated current user, a canonical capability, File 00 fresh identity/two-factor assurance and a valid REST nonce. File 21 separately revalidates its own migration capability and assurance. No role or capability is created or granted by File 04.

## Data minimization

Evidence tables contain identifiers, hashes, state, bounded checkpoints and redacted context. Post bodies, patient details, report narratives, consent documents, email addresses, phone numbers, identity numbers, IP addresses and user agents are not copied into File 04 evidence.

Legacy report details remain in the retained read-only source. Canonical migrated report notes contain only a fixed provenance statement.

## Integrity

- source inventory signature;
- per-record source and target SHA-256;
- idempotency HMAC;
- advisory database locks;
- one mapping per legacy ID;
- conflict fingerprints;
- append-only HMAC audit chain;
- exact lifecycle optimistic concurrency.

## Web controls

Legacy content is excluded from search and sitemaps. Source comments, pings, metadata, updates, trash and deletion are blocked. Old public shortcodes and AJAX interaction actions are neutralized. Legacy routes are never rendered by this plugin; they redirect only to same-origin verified canonical targets or return private noindex errors.

## Resilience

Migration and rollback are bounded, resumable and non-destructive. Target modifications stop automated rollback. Retirement requires backup proof, reconciliation, rollback rehearsal and an expired observation window. Uninstall retains all evidence and source data.

## External validation gates

Static tests do not prove WordPress hooks, MySQL locking semantics, File 00 session assurance, File 21 runtime behavior, cache/CDN behavior, large datasets, media/comment edge cases or restore viability. These must be demonstrated on staging.
