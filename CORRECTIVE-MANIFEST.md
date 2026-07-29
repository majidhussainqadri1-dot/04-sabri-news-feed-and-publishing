# Corrective Release Manifest — File 04 v0.2.0

## Identity

- Corrective branch: `audit/file-04-source-review`
- Immutable baseline commit: `b4d62e0dae045eb8b4b343c9b22b4ebbf3ffa94a`
- Original package SHA-256: `49ccc4d1c575b0208d067e25e2b90df63bc6b591e150992c33f8cd4cdedd53ea`
- Corrected source files: `26`
- Corrected PHP files: `20`
- Corrected source size: `143,206` bytes
- Corrected source-tree SHA-256: `24c490b8726b31de4b9485552f79c56e37d143208ccf118fc252ffb76c6b12e2`
- Corrective release-lock SHA-256: `d456da3a5c5e921e205e8ebee380f7189b18b2bfd306647457de9792cd854d88`

## Governing design

The corrected source is private-first and fail-closed. Publication candidates are assembled as drafts, protected Patient Cases require independent privacy review, public output requires an immutable approved snapshot and current author eligibility, and File 04 consumes—rather than recreates—the authorities of Files 00, 03, 09, 19, 20, 21, and 22.

## Verification artifacts

- `CORRECTIVE-SOURCE-INVENTORY.tsv`
- `CORRECTIVE-CHECKSUMS.sha256`
- `CORRECTIVE-RELEASE-LOCK.json`
- `.github/workflows/corrective-integrity.yml`
- `tests/security-unit.php`
- `SECURITY-PRIVACY-REVIEW.md`

The manifest is source and automated-QA evidence only. It is not deployment authorization.
