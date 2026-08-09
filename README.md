# File 04 — News Feed and Publishing — Legacy Foundation Adapter v2.0.4

File 04 remains a **temporary, write-disabled, auditable and reversible migration/compatibility adapter**. File 21 is the sole canonical publication/Home/News/feed owner; File 26 owns search/discovery; File 20 owns the shell; File 25 owns the visual system; File 24 coordinates assurance. File 04 does not create a second composer, feed, ranking service, comments/reactions store, moderation backend, search engine or permanent public route system.

## Governing scope

The source trace covers the consolidated governing plan, File 04 FR-001..013 and NFR-001..010, 71 CV requirements (all applicable central requirements), F04-CEN-01..02, 15 acceptance journeys and F04-FUT-001..018.

## v2.0.4 fresh eighty-round hardening

A new sequential 80-round audit reopened the corrected source and applied **Review → immediate correction → affected regression** before every next round. **Rounds 1–65 discovered genuine repository/source defects and each was corrected before the next round. Rounds 66–80 were then completed as fresh focused regression reviews and found no new repository/source defect.** Those final clean rounds rechecked canonical ownership, File21/File26 boundaries, authorization, privacy, bounded queries, concurrency/idempotency, recovery, redirects/fallback, quarantine, PHP 8.1/8.3, accessibility/RTL, deterministic packaging and lifecycle truth. See `EIGHTY-ROUND-HARDENING-AUDIT.md`.

Storage schema remains **1.3.0** because these corrections do not require a custom-table schema migration.

## Truthful lifecycle status

Repository/source completion is separate from Hostinger staging, live deployment and operations. Source code must never self-promote `production_ready`; real File00/File21/File26 contracts, real legacy data, browsers/devices/WCAG/RTL, backup/isolated restore, rollback/DR rehearsal, Founder acceptance and production monitoring remain external gates.
