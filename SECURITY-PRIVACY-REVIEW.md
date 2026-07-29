# Security, Privacy, and Editorial Correction Record

This document records the corrective design implemented after the independent review of File 04 `0.1.0`.

## Corrected release blockers

1. Identity and editorial capabilities are read from File 00 and fail closed.
2. Doctor publishing eligibility requires accepted File 03 and File 09 projections.
3. File 04 no longer replaces Home/News pages or renders global navigation.
4. Ordinary administrators are not automatically editors or immediate publishers.
5. Every candidate is created as a private draft before metadata, topic, consent, and media are assembled.
6. Dedicated post capabilities and after-save enforcement prevent direct-editor bypass.
7. Public output requires current author eligibility, approved topic/state, no sanction, current snapshot, and Patient Case readiness.
8. Patient Cases require a consent record/version/date/scope, redaction summary, image-specific consent where applicable, and an independent privacy reviewer.
9. Pending media is encrypted at rest and can only be previewed through an authorized, noncacheable, audited endpoint.
10. Personalized File 04 pages and logged-in File 04 feed views receive private/no-store/noindex controls.
11. Likes/Saves, rate limits, and views use database atomicity/deduplication; self-Likes are blocked.
12. Reports retain history, require reasoned resolution, support time-limited appeals, and restrict sensitive details.
13. Comment rules are scoped to File 04 publications and hold probable personal/medical identifiers for review.
14. Privacy export is paginated; erasure removes interactions, anonymizes retained evidence, deletes encrypted pending media, and recalculates ranking.
15. Audit rows use immutable HMAC digests and a local chain while mutable personal details can be anonymized.
16. Media is re-encoded before publication, explicitly owned, and removed on rollback, rejection, withdrawal, or guarded purge.
17. Core XML sitemaps and standalone taxonomy archives do not expose records outside the canonical governed feed.

## Deliberate external blocker

File 09 `1.0.0` still has legacy role and `_spd_*` assumptions. File 04 remains fail closed and cannot receive production integration approval until File 09 is corrected and accepted separately.

## Evidence limits

Static checks and no-network unit tests cannot prove WordPress runtime behavior, database-engine compatibility, cache behavior, real media processing, concurrency under load, or cross-plugin integration. Those remain staging gates.
