=== Sabri News Feed Legacy Foundation Adapter ===
Contributors: sabrihomeopathy
Tags: migration, legacy adapter, publishing, audit, rollback
Requires at least: 6.0
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPLv2 or later

Read-only, auditable and reversible migration of historical File 04 publications, comments and interactions into canonical File 21.

== Description ==

File 04 no longer owns Home, News, publishing, feeds, ranking, comments, reactions, saves, reports, navigation or public composition. Version 1.0.1 inventories and checksums legacy records, performs bounded dry-runs, delegates canonical migration to File 21, records deterministic mappings and conflicts, reconciles counts and checksums, controls legacy redirects, supports non-destructive rollback/replay and retires after strict acceptance gates.

The historical source is retained and write-disabled. No role or capability is granted by this plugin. File 00 fresh identity/two-factor assurance and canonical migration capabilities are mandatory.

== Installation ==

1. Install on staging.
2. Activate File 00 and File 21 package 1.0.3.2 or later.
3. Assign canonical migration/review capabilities through File 00 governance.
4. Activate this adapter.
5. Follow MIGRATION-RUNBOOK.md.

== Privacy ==

Evidence tables contain identifiers, checksums, lifecycle state and redacted bounded context. They do not copy post bodies, patient details, report narratives, consent documents, email, phone, identity numbers, IP addresses or user agents. Source data remains retained and read-only.

== Changelog ==

= 1.0.1 =
* Blocks retry when a stale run cannot be durably finalized; excludes development workflows/tests/tools from the production ZIP.
* Safely deactivates the obsolete runtime and privately quarantines only proven legacy-owned pages during activation.
* Preserves reaction types and merged view counts with reversible field-level interaction ledgers.
* Binds backup and rollback proofs to the locked source signature; enforces fresh reconciliation evidence; quarantines lifecycle/ledger failures; removes ambiguous secondary-audit outcomes.
* Added signed operational evidence, audit-chain verification, semantic reconciliation, exact interaction reconciliation, strict idempotency collision checks, rollback quarantine, taxonomy write guards, operation locks and retirement revalidation.

= 1.0.0 =
* Replaced the obsolete parallel News Feed and Publishing runtime with the canonical File 21 legacy adapter architecture.
* Added lifecycle, inventory, dry-run, backup proof, idempotent batch migration, interaction provider, reconciliation, conflict quarantine, safe redirects, read-only fallback, rollback/replay, retirement, REST, WP-CLI, admin evidence and HMAC audit controls.
