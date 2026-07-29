=== Sabri News Feed and Publishing ===
Contributors: sabrihomeopathy
Tags: publishing, editorial workflow, news feed, privacy, moderation
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later

Governed publication composition, editorial state, interactions, protected reports, and approved feed data for the Sabri Social Homeopathy Platform.

== Description ==

File 04 version 0.2.0 is a publishing and editorial service. It is not the platform shell and does not own the Home or News pages.

Authority boundaries:
* File 00 owns identity, active membership, Founder identity, editorial capabilities, and canonical audit authority.
* Corrected File 03 supplies approved public-profile projection.
* File 09 supplies doctor-verification eligibility.
* File 20 owns shell and navigation.
* File 21 owns canonical Home and News feed rendering.
* File 19 may deliver notifications through File 04 actions.
* File 22 may invoke File 04 through the universal composer integration filter.

Core controls:
* Fail-closed dependencies and no administrator-wide capability grants.
* Dedicated publication capabilities supplied by File 00.
* Private-first candidate creation; no publication is live before all metadata and media gates pass.
* Mandatory independent privacy review for Patient Cases.
* Consent record, version, scope, date, redaction summary, image consent, and withdrawal state.
* Approved publication snapshots and automatic withdrawal when content or author eligibility changes.
* Encrypted database staging for pending images; public Media Library materialization only after approval.
* Current-author revalidation on every public query and single view.
* Atomic interaction writes, fixed-window database rate limits, self-Like prevention, daily deduplicated view records, and deterministic score calculation.
* Protected reports with resolution outcome, reason, resolver, timestamp, canonical audit event, and privacy-aware reporter access.
* Publication-specific comment controls without changing the global WordPress comment setting.
* Private/no-store headers for saved feeds, composition pages, previews, and personalized logged-in feed responses.
* Exact page ownership with collision-safe slugs.
* Privacy export pagination, erasure/anonymization, retention, migrations, and guarded destructive uninstall.
* Article schema only for currently approved and publicly eligible publications.

== Installation ==

1. Activate File 00 — Sabri Membership Core.
2. Activate corrected File 03 and File 09 verification integration.
3. Activate File 04.
4. Supply editorial capabilities through File 00 governance.
5. Connect File 04's provider API to File 21 for canonical feed rendering.
6. Complete staging tests before merge or production deployment.

== Privacy ==

Pending publication images are encrypted in a staging table and are not added to the public Media Library until approval. Likes, saves, reports, views, audits, and authored publications participate in WordPress privacy export and erasure procedures. Reports and editorial evidence follow retention and anonymization rules rather than unsafe unconditional deletion.

== Changelog ==

= 0.2.0 =
* Corrected identity, verification, shell, feed, capability, publishing, Patient Case, privacy, interaction, report, media, audit, migration, SEO, and uninstall boundaries.

= 0.1.0 =
* Original historical baseline.
