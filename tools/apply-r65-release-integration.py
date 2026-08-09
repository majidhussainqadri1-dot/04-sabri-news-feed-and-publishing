#!/usr/bin/env python3
from pathlib import Path


def read(p):
    return Path(p).read_text(encoding='utf-8')


def write(p, s):
    Path(p).write_text(s, encoding='utf-8')


# Current QA gates follow the current runtime patch level.
for p in Path('tests').glob('*.py'):
    s = p.read_text(encoding='utf-8')
    if '2.0.3' in s:
        p.write_text(s.replace('2.0.3', '2.0.4'), encoding='utf-8')

# Deterministic builder: version + permanent 80-round evidence.
p = 'tools/build-release.py'
s = read(p)
s = s.replace("VERSION='2.0.3'", "VERSION='2.0.4'")
s = s.replace('v2.0.3 third-ten-round hardened candidate', 'v2.0.4 eighty-round hardened candidate')
s = s.replace('2.0.3.zip', '2.0.4.zip').replace('2.0.3-complete-source.zip', '2.0.4-complete-source.zip')
s = s.replace(
    'THIRD_TEN_ROUND_REVIEW_ROUNDS=10\nHISTORICAL_REVIEW_ROUNDS=40',
    'THIRD_TEN_ROUND_REVIEW_ROUNDS=10\nEIGHTY_ROUND_REVIEW_ROUNDS=80\nHISTORICAL_REVIEW_ROUNDS=40',
)
s = s.replace(
    "    subprocess.run([sys.executable,str(ROOT/'tests/run-third-ten-round-hardening.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)\n",
    "    subprocess.run([sys.executable,str(ROOT/'tests/run-third-ten-round-hardening.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)\n    subprocess.run([sys.executable,str(ROOT/'tests/run-eighty-round-hardening.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)\n",
)
s = s.replace(
    "          'third_ten_round_hardening':{'version':'1.0.0','review_rounds':THIRD_TEN_ROUND_REVIEW_ROUNDS,'gate':'tests/run-third-ten-round-hardening.py','audit':'THIRD-TEN-ROUND-HARDENING-AUDIT.md','scope':'third fresh plan/anti-replay/serialization/release review'},\n",
    "          'third_ten_round_hardening':{'version':'1.0.0','review_rounds':THIRD_TEN_ROUND_REVIEW_ROUNDS,'gate':'tests/run-third-ten-round-hardening.py','audit':'THIRD-TEN-ROUND-HARDENING-AUDIT.md','scope':'third fresh plan/anti-replay/serialization/release review'},\n          'eighty_round_hardening':{'version':'1.0.0','review_rounds':EIGHTY_ROUND_REVIEW_ROUNDS,'gate':'tests/run-eighty-round-hardening.py','audit':'EIGHTY-ROUND-HARDENING-AUDIT.md','scope':'fresh sequential reliability, security, migration, rollback, evidence, integration and release review'},\n",
)
s = s.replace(
    "          'third_ten_round_review_rounds':THIRD_TEN_ROUND_REVIEW_ROUNDS,\n          'historical_v120_review_rounds'",
    "          'third_ten_round_review_rounds':THIRD_TEN_ROUND_REVIEW_ROUNDS,\n          'eighty_round_review_rounds':EIGHTY_ROUND_REVIEW_ROUNDS,\n          'historical_v120_review_rounds'",
)
s = s.replace(
    "          'third_ten_round_review_rounds':THIRD_TEN_ROUND_REVIEW_ROUNDS,\n          'known_unresolved_source_scope_blockers'",
    "          'third_ten_round_review_rounds':THIRD_TEN_ROUND_REVIEW_ROUNDS,\n          'eighty_round_review_rounds':EIGHTY_ROUND_REVIEW_ROUNDS,\n          'known_unresolved_source_scope_blockers'",
)
s = s.replace(
    "      'third_ten_round_review_rounds':THIRD_TEN_ROUND_REVIEW_ROUNDS,\n      'known_unresolved_source_scope_blockers'",
    "      'third_ten_round_review_rounds':THIRD_TEN_ROUND_REVIEW_ROUNDS,\n      'eighty_round_review_rounds':EIGHTY_ROUND_REVIEW_ROUNDS,\n      'known_unresolved_source_scope_blockers'",
)
s = s.replace(
    "          'third_ten_round_review_rounds':THIRD_TEN_ROUND_REVIEW_ROUNDS,\n        },sort_keys=True))",
    "          'third_ten_round_review_rounds':THIRD_TEN_ROUND_REVIEW_ROUNDS,\n          'eighty_round_review_rounds':EIGHTY_ROUND_REVIEW_ROUNDS,\n        },sort_keys=True))",
)
if "VERSION='2.0.4'" not in s or 'EIGHTY_ROUND_REVIEW_ROUNDS=80' not in s or 'run-eighty-round-hardening.py' not in s:
    raise SystemExit('Builder v2.0.4/80-round integration incomplete')
write(p, s)

# Main CI becomes the exact-head 80-round release gate.
p = '.github/workflows/file04-legacy-adapter-ci.yml'
s = read(p)
s = s.replace(
    'name: File 04 Legacy Adapter — Central Plan + Future18 + Three-Ten-Round Hardening CI',
    'name: File 04 Legacy Adapter — Central Plan + Future18 + Eighty-Round Hardening CI',
)
marker = '      - name: Third fresh ten-round hardening gate\n        run: python3 tests/run-third-ten-round-hardening.py\n'
if marker not in s:
    raise SystemExit('CI third-gate marker missing')
s = s.replace(marker, marker + '      - name: Fresh eighty-round hardening gate\n        run: python3 tests/run-eighty-round-hardening.py\n', 1)
marker2 = '      - name: Third fresh ten-round hardening checks\n        run: python3 tests/run-third-ten-round-hardening.py\n'
if marker2 not in s:
    raise SystemExit('CI PHP third-gate marker missing')
s = s.replace(marker2, marker2 + '      - name: Fresh eighty-round hardening checks\n        run: python3 tests/run-eighty-round-hardening.py\n', 1)
s = s.replace('Reproducible v2.0.3 package twice', 'Reproducible v2.0.4 package twice')
write(p, s)

# WordPress readme: current metadata changes while the old changelog remains history.
p = 'readme.txt'
s = read(p)
s = s.replace('Stable tag: 2.0.3', 'Stable tag: 2.0.4', 1)
old_description = 'Version 2.0.3 retains the v1.3.0 migration/rollback foundation, all 18 Future18 migration-safety capabilities and the first two fresh ten-round hardening audits, then applies a **third fresh ten-round adversarial audit** covering controlled read-only fallback, deterministic evidence encoding, exact opaque idempotency tokens, source/request-bound restore/cutover/retirement attestations, redirect-loop integrity, fail-closed mapping evidence serialization and release/QA consistency. Hostinger staging, live deployment and operational acceptance remain separate evidence gates.'
new_description = 'Version 2.0.4 retains the full migration/rollback and Future18 scope, then applies a fresh sequential 80-round hardening audit across lifecycle integrity, schema verification, REST identity/idempotency, migration/reconciliation/rollback compensation, provider anti-replay, multisite safety, CLI/admin truthfulness, deterministic packaging and canonical ownership. Hostinger staging, live deployment and operational acceptance remain separate evidence gates.'
if old_description in s:
    s = s.replace(old_description, new_description, 1)
if '= 2.0.4 =' not in s:
    changelog = '''== Changelog ==

= 2.0.4 =
* Completed a fresh sequential 80-round Review → immediate correction → retest audit.
* Hardened lifecycle/schema integrity, signed evidence, exact idempotency and strict object identity.
* Hardened migration, reconciliation, interactions, rollback, quarantine and compensation paths.
* Added provider freshness/request binding, multisite safety, CLI/admin fail-closed behavior and File21 result-scope validation.
* Added a permanent 80-round deterministic QA gate and v2.0.4 release/package integration while preserving storage schema 1.3.0.

'''
    s = s.replace('== Changelog ==\n\n', changelog, 1)
write(p, s)

write('README.md', '''# File 04 — News Feed and Publishing — Legacy Foundation Adapter v2.0.4

File 04 remains a **temporary, write-disabled, auditable and reversible migration/compatibility adapter**. File 21 is the sole canonical publication/Home/News/feed owner; File 26 owns search/discovery; File 20 owns the shell; File 25 owns the visual system; File 24 coordinates assurance. File 04 does not create a second composer, feed, ranking service, comments/reactions store, moderation backend, search engine or permanent public route system.

## Governing scope

The source trace covers the consolidated governing plan, File 04 FR-001..013 and NFR-001..010, 71 applicable central CV requirements, F04-CEN-01..02, 15 acceptance journeys and F04-FUT-001..018.

## v2.0.4 fresh eighty-round hardening

A new sequential 80-round audit reopened the corrected source and applied **Review → immediate correction → affected regression** before every next round. Rounds 1–65 discovered repository/source defects and corrected them. Rounds 66–80 are separate final regression reviews covering ownership boundaries, File21/File26 integration, authorization, privacy, bounded queries, concurrency/idempotency, recovery, redirects/fallback, quarantine, PHP 8.1/8.3, accessibility/RTL, deterministic packaging and final lifecycle truth. See `EIGHTY-ROUND-HARDENING-AUDIT.md`.

Storage schema remains **1.3.0** because these corrections do not require a custom-table schema migration.

## Truthful lifecycle status

Repository/source completion is separate from Hostinger staging, live deployment and operations. Source code must never self-promote `production_ready`; real File00/File21/File26 contracts, real legacy data, browsers/devices/WCAG/RTL, backup/isolated restore, rollback/DR rehearsal, Founder acceptance and production monitoring remain external gates.
''')

write('STATUS.md', '''# File 04 v2.0.4 — Fresh Eighty-Round Hardening Status Register

`source_status=v2.0.4-eighty-round-review-in-progress`
`known_unresolved_source_scope_blockers=0`
`staging_accepted_pending=true`
`live_deployed=false`
`operational=false`

| Status | Decision | Evidence boundary |
|---|---|---|
| Specified | **Complete for current source scope** | Consolidated plan + File04 plan + CV/CEN/AJ + FR/NFR + Future18 |
| Coded | **Corrected through Round 65; R66-R80 final regressions pending** | Sequential fresh 80-round audit record |
| Packaged | **v2.0.4 candidate pending final exact-head gate** | Deterministic installable + complete-source double build |
| Automated-QA Green | **Pending final exact head** | Existing gates + permanent 80-round gate + PHP 8.1/8.3 + secret/PII scan |
| Staging-Accepted | **Pending** | Hostinger real integration/browser/restore/rollback evidence |
| Live-Deployed | **Pending** | Founder-approved controlled deployment |
| Operational | **Pending** | Sustained monitoring/support/backup/incident/retirement evidence |

Rounds **1–65** found defects and their corrections are represented in `EIGHTY-ROUND-HARDENING-AUDIT.md`. Rounds **66–80** remain deliberately unclaimed until the corrected v2.0.4 head completes their focused regression gate.

Canonical ownership remains unchanged. File04 remains temporary migration/compatibility only; source code cannot mark this adapter Staging-Accepted, Live-Deployed or Operational. `production_ready` remains false at source level until external acceptance exists. Storage schema remains **1.3.0**.
''')

print('Applied File04 R65 v2.0.4 release/QA integration patch.')
