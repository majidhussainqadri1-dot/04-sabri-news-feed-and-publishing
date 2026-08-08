# File 04 — News Feed and Publishing — Legacy Foundation Adapter v2.0.3

File 04 remains a **temporary, write-disabled, auditable and reversible migration/compatibility adapter**. File 21 is the sole canonical publication/Home/News/feed owner; File 26 owns search/discovery; File 20 owns the shell; File 25 owns the visual system; File 24 coordinates assurance. File 04 does not create a second composer, feed, ranking service, comments/reactions store, moderation backend, search engine or permanent public route system.

## Governing scope

The source trace covers the consolidated governing plan, File 04 FR-001..013 and NFR-001..010, **71 CV requirements** applicable to File 04, F04-CEN-01..02, 15 acceptance journeys and F04-FUT-001..018.

## v2.0.3 third fresh ten-round hardening

A third independent ten-round review reopened the merged v2.0.2 source rather than reusing earlier results. Rounds 1–9 found and corrected: the tombstone-only fallback/plan mismatch; JSON/hash false-success behavior; opaque idempotency-key normalization; stale restore-verifier evidence; stale cutover cache/search evidence; replayable retirement handoff evidence; same-path redirect-loop risk; mapping evidence serialization false success; and release/QA metadata drift. Round 10 is reserved for a fresh exact-head adversarial regression after all Round-9 changes.

Storage schema remains **1.3.0** because these corrections add no custom-table schema migration.

## Truthful lifecycle status

Repository/source completion is separate from Hostinger staging, live deployment and operations. Source code must never self-promote `production_ready`; real File00/File21/File26 contracts, real legacy data, browsers/devices/WCAG/RTL, backup/isolated restore, rollback/DR rehearsal, Founder acceptance and production monitoring remain external gates.

See `THIRD-TEN-ROUND-HARDENING-AUDIT.md`, `SECOND-TEN-ROUND-HARDENING-AUDIT.md`, `TEN-ROUND-POST-FUTURE18-AUDIT.md`, `FUTURE18-TRACEABILITY.md` and `REQUIREMENTS-TRACEABILITY.md`.
