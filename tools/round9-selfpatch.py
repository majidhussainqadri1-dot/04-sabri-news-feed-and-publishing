from pathlib import Path

ROOT = Path('.')

def read(path): return Path(path).read_text(encoding='utf-8')
def write(path, content): Path(path).write_text(content, encoding='utf-8')
def replace(path, old, new, count=None):
    p=Path(path); s=p.read_text(encoding='utf-8')
    if old not in s:
        raise SystemExit(f'round9 target missing in {path}: {old[:120]!r}')
    p.write_text(s.replace(old,new, -1 if count is None else count), encoding='utf-8')

# Current release metadata: new security/reliability corrections require a patch release.
for path in [
    'sabri-news-feed-legacy-adapter.php',
    'tests/run-architecture.py',
    'tests/run-central-reviews.py',
    'tests/run-future18.py',
    'tests/run-future18-reviews.py',
    'tests/run-ten-round-post-future18.py',
    'tools/build-release.py',
    '.github/workflows/file04-legacy-adapter-ci.yml',
]:
    p=Path(path); s=p.read_text(encoding='utf-8'); p.write_text(s.replace('2.0.1','2.0.2'),encoding='utf-8')

# WordPress metadata/changelog.
wp = read('readme.txt').replace('Stable tag: 2.0.1','Stable tag: 2.0.2',1)
wp = wp.replace('Version 2.0.1 retains', 'Version 2.0.2 retains', 1)
marker='== Changelog ==\n\n'
entry="""= 2.0.2 =
* Second fresh ten-round adversarial hardening after v2.0.1.
* Made capability extension filters deny-only so they cannot grant migration authority.
* Made corrupted lifecycle state fail closed instead of reopening legacy mutation paths.
* Bound Future18 visual, redirect/citation and DR provider evidence to the exact current source/request.
* Replaced self-clearing contract drift with an explicit signed, source-bound baseline workflow.
* Made cryptographic receipt persistence and audit durable/fail-closed.
* Made File 26 system evidence manifest-bound and source-level production readiness permanently non-promoting.
* Required real observability samples before canary approval and source/request-bound File 21 media evidence.
* Added the deterministic second-ten-round audit gate and v2.0.2 release evidence.

"""
if marker not in wp: raise SystemExit('readme changelog marker missing')
wp=wp.replace(marker,marker+entry,1)
write('readme.txt',wp)

# Human README reflects the new review layer while preserving the old audit as history.
readme = read('README.md').replace('v2.0.1','v2.0.2')
readme = readme.replace('## v2.0.2 post-Future18 hardening', '## v2.0.2 second ten-round hardening', 1)
old_para='The ten-round adversarial review retained all 18 Future18 capabilities and corrected the gaps found after v2.0.0: Future18 action/evidence routes now require fresh File 00 current-action authority; failed Future18 callbacks receive non-2xx HTTP status; visual-diff verification requires the full requested desktop/mobile/RTL/accessibility matrix and explicit critical-diff counts; redirect/citation provider verification is a production-readiness gate; DR GameDay verification requires every requested disposable-staging exercise to pass; cryptographic receipts cannot be minted unless the underlying migration/reconciliation/rollback/cutover evidence is independently verifiable; and release metadata/CI/package evidence is bound to v2.0.2.'
new_para='The first ten-round audit remains historical evidence. A second fresh ten-round adversarial audit then reopened the corrected v2.0.1 source and found additional fail-closed gaps: capability filters could grant authority; corrupted lifecycle state could fall back to a write-capable state; provider attestations were not always bound to the current source/request; contract drift could auto-clear its own baseline; receipt/evidence persistence could report success after durability failure; source checks could imply production readiness; canary control could approve with zero samples; and File 21 media evidence needed anti-replay binding. Those defects are corrected in v2.0.2 without changing canonical ownership.'
if old_para not in readme: raise SystemExit('README hardening paragraph target missing')
readme=readme.replace(old_para,new_para,1)
readme=readme.replace('See `TEN-ROUND-POST-FUTURE18-AUDIT.md` for the ten review rounds', 'See `TEN-ROUND-POST-FUTURE18-AUDIT.md` for the first ten review rounds and `SECOND-TEN-ROUND-HARDENING-AUDIT.md` for the second fresh ten-round audit',1)
write('README.md',readme)

# Truthful current status; Round 10 is deliberately not pre-certified here.
status = """# File 04 v2.0.2 — Second Ten-Round Hardening Truthful Status Register

`source_status=v2.0.2-second-ten-round-review-in-progress`
`staging_accepted_pending=true`
`live_deployed=false`
`operational=false`

| Status | Decision | Evidence boundary |
|---|---|---|
| Specified | **Complete for current source scope** | Consolidated central plan + File 04 plan + 71 CV + 2 CEN + 15 AJ + FR-001..013 + NFR-001..010 + F04-FUT-001..018 |
| Coded | **Second-audit corrective source implemented through Round 9** | Fail-closed authorization/lifecycle, request-bound provider evidence, explicit contract baseline, durable evidence, truthful release state and observability gates |
| Packaged | **v2.0.2 candidate pending exact-head deterministic gate** | Builder produces and compares installable and complete-source packages |
| Automated-QA Green | **Pending exact final head** | Architecture, own-plan, central-plan, Future18, prior ten-round gate, second-ten-round gate, secret/PII scan and PHP 8.1/8.3 |
| Staging-Accepted | **Pending** | Hostinger + real File 00/21/26 + browser/RTL/WCAG + backup/restore/rollback/DR evidence |
| Live-Deployed | **Pending** | Founder-approved controlled deployment only |
| Operational | **Pending** | Real monitoring/SLO/support/incident/backup/migration-window/retirement evidence required |

## Second ten-round audit progress

Defects have been found and corrected in **Rounds 2, 3, 4, 5, 6, 7, 8 and 9**. **Round 1 found no new defect. Round 10 remains to be executed on the corrected Round-9 source and is not pre-certified.**

The detailed record is `SECOND-TEN-ROUND-HARDENING-AUDIT.md`. The previous `TEN-ROUND-POST-FUTURE18-AUDIT.md` remains historical evidence for v2.0.1 and is not rewritten.

## Canonical ownership preserved

File 21 remains publication/Home/News/feed truth; File 26 search/discovery; File 20 shell; File 25 visual system; File 24 assurance coordination. File 04 remains a temporary migration/compatibility adapter. No second composer, feed, ranking backend, search index, moderation store or dual-write truth path is introduced.

## Release truth

Source code cannot mark this adapter Staging-Accepted, Live-Deployed or Operational. `production_ready` remains false at source level until external acceptance exists. Storage schema remains **1.3.0** because no custom-table schema changed.
"""
write('STATUS.md',status)

# Second ten-round audit record. Round 10 intentionally pending until fresh final review.
audit = """# File 04 — Second Fresh Ten-Round Hardening Audit

**Candidate release:** 2.0.2  
**Storage schema:** 1.3.0  
**Base reviewed:** main `cc296b06ec732da708480e6ab61e920db9ad5f03`  
**Method:** each round is a fresh Review → immediate correction → affected regression cycle. Staging/live/operational evidence is not fabricated by this source audit.

| Round | Focus | Result / correction |
|---|---|---|
| 1 | Governing scope, canonical owners, duplicate backend | **No new defect.** File 04 remained adapter-only; File 21/26/20/25/24 boundaries intact. |
| 2 | Authorization and extension hooks | **Defect.** Capability filters could elevate `false` to `true`; changed to deny-only extension semantics. |
| 3 | Lifecycle corruption / fail-closed mutation law | **Defect.** Unknown persisted state could fall back to `legacy_active`; introduced invalid-state sentinel and blocked mutation/recovery until repair. |
| 4 | Provider evidence replay | **Defect.** Visual/redirect/DR evidence was not cryptographically tied to current source/request; added source signature + deterministic request digest echo validation. |
| 5 | Contract drift | **Defect.** Drift observation auto-updated its own baseline and could self-clear; replaced with explicit signed source-bound baseline recording under step-up authority. |
| 6 | Receipt durability | **Defect.** Receipt could return success if option/audit persistence failed; persistence and audit are now fail-closed with rollback. |
| 7 | System/release truth | **Defect.** File26 acceptance and audit status were not sufficiently bound/blocking and source blockers could imply production readiness; corrected manifest binding, blocker propagation and permanent source-level `production_ready=false`. |
| 8 | Observability + File21 media + evidence durability | **Defect.** Canary could approve with zero samples; File21 media attestations lacked request/source anti-replay; GameDay/redirect evidence could survive durability failures as verified. All corrected fail-closed. |
| 9 | Release/version/QA integration | **Defect.** Material v2.0.1 corrections were not yet represented as a new release or deterministic second-audit gate; aligned runtime/docs/builder/CI/tests to v2.0.2 and added this second-audit test. |
| 10 | Final fresh adversarial regression after Round 9 | **PENDING — must not be pre-certified.** |

## Governing evidence boundary

This audit is source/repository evidence only. Hostinger staging, real File00/File21/File26 contracts, real legacy data, browser/device/WCAG/RTL evidence, isolated restore, rollback rehearsal, Founder approval, live deployment and operational monitoring remain separate gates.
"""
write('SECOND-TEN-ROUND-HARDENING-AUDIT.md',audit)

# Deterministic second-audit source gate.
test = r'''#!/usr/bin/env python3
from pathlib import Path
import sys
ROOT=Path(__file__).resolve().parents[1]
def text(path): return (ROOT/path).read_text(encoding='utf-8')
def need(cond,msg,fail):
    if not cond: fail.append(msg)
fail=[]
main=text('sabri-news-feed-legacy-adapter.php')
caps=text('includes/class-snfla-capabilities.php')
schema=text('includes/class-snfla-schema.php')
ret=text('includes/class-snfla-retirement.php')
future=text('includes/class-snfla-future18.php')
hard=text('includes/class-snfla-post-audit-hardening.php')
plan=text('includes/class-snfla-plan-completion.php')
workflow=text('.github/workflows/file04-legacy-adapter-ci.yml')
build=text('tools/build-release.py')
audit=text('SECOND-TEN-ROUND-HARDENING-AUDIT.md')
all_php='\n'.join(p.read_text(encoding='utf-8') for p in ROOT.rglob('*.php') if '.git' not in p.parts and 'tests' not in p.parts)

need('Version: 2.0.2' in main and "SNFLA_VERSION', '2.0.2'" in main, 'Current runtime must be v2.0.2.',fail)
need("SNFLA_SCHEMA_VERSION', '1.3.0'" in main, 'Storage schema must remain 1.3.0.',fail)
need("if ( $allowed )" in caps and "apply_filters( 'snfla_actor_capability_allowed', true" in caps and "apply_filters( 'snfla_read_capability_allowed', true" in caps, 'Capability extension filters must be deny-only.',fail)
need("const INVALID_STATE = 'invalid'" in schema and 'state_valid' in schema and 'snfla_lifecycle_state_invalid' in schema, 'Invalid persisted lifecycle state must fail closed.',fail)
need('in_array( $state, SNFLA_Schema::states(), true )' in ret and "'retired' !== $state" in ret, 'Mutation gate must reject invalid/retired states.',fail)
for token in ['source_signature','request_digest','provider_request_bound']:
    need(token in hard and token in future, f'Future provider evidence binding missing: {token}',fail)
need("'/future/contract-baseline'" in future and 'record_contract_baseline' in future and 'baseline_missing_or_invalid' in future, 'Contract drift must use explicit signed baseline workflow.',fail)
need("update_option( self::DRIFT_OPTION, $baseline" in future and "future18_contract_baseline_recorded" in future, 'Contract baseline must persist and audit explicitly.',fail)
need('snfla_receipt_persist_failed' in future and 'snfla_receipt_audit_failed' in future, 'Receipt durability must fail closed.',fail)
need('manifest_digest' in plan and 'file04_system_' in plan and "'production_ready'] = false" in plan, 'File26/system/production readiness truth gates missing.',fail)
need('observability_samples_missing' in future and 'sample_count' in future, 'Canary must reject zero observability samples.',fail)
need('snfla_media_source_signature_invalid' in plan and 'provider_bound' in plan and 'verify_bound' in plan, 'File21 media pre/post attestations must be source/request-bound.',fail)
need('snfla_gameday_persist_failed' in future and 'snfla_gameday_audit_failed' in future, 'GameDay durability must fail closed.',fail)
need('persistence_failed' in hard and 'audit_persistence_failed' in hard, 'Redirect/citation evidence must fail closed on durability/audit failures.',fail)
need('run-second-ten-round-hardening.py' in workflow and 'run-second-ten-round-hardening.py' in build, 'CI and deterministic builder must execute the second-audit gate.',fail)
need("VERSION='2.0.2'" in build and 'SECOND_TEN_ROUND_REVIEW_ROUNDS=10' in build, 'Builder must bind v2.0.2 and second ten-round evidence.',fail)
need('Round 10' in audit and 'PENDING' in audit, 'Round 10 must remain explicitly pending until the fresh final review actually runs.',fail)
need('wp_insert_post(' not in hard and '$wpdb->posts' not in hard and 'SNFLA_Migration::migrate(' not in hard, 'Hardening must not create a second publication/migration backend.',fail)
need('LegacyPublicationMigration::migrate_selected' in all_php and 'LegacyPublicationRollback::rollback_selected' in all_php, 'File21 canonical migration/rollback commands must remain present.',fail)

if fail:
    print('Second ten-round hardening gate failed:',file=sys.stderr)
    for x in fail: print('-',x,file=sys.stderr)
    sys.exit(1)
print('Second ten-round hardening gate passed for completed Rounds 1-9; Round 10 remains intentionally pending fresh final review.')
'''
write('tests/run-second-ten-round-hardening.py',test)

# Builder integrates second audit as a first-class deterministic gate/evidence dimension.
build=read('tools/build-release.py')
build=build.replace('TEN_ROUND_REVIEW_ROUNDS=10\nHISTORICAL_REVIEW_ROUNDS=40','TEN_ROUND_REVIEW_ROUNDS=10\nSECOND_TEN_ROUND_REVIEW_ROUNDS=10\nHISTORICAL_REVIEW_ROUNDS=40',1)
build=build.replace("subprocess.run([sys.executable,str(ROOT/'tests/run-ten-round-post-future18.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)","subprocess.run([sys.executable,str(ROOT/'tests/run-ten-round-post-future18.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)\n    subprocess.run([sys.executable,str(ROOT/'tests/run-second-ten-round-hardening.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)",1)
build=build.replace("'post_future18_hardening':{'version':'1.0.0','review_rounds':TEN_ROUND_REVIEW_ROUNDS,'gate':'tests/run-ten-round-post-future18.py','scope':'authorization, REST failures, visual/redirect/DR evidence, receipt authenticity, release consistency'},","'post_future18_hardening':{'version':'1.0.0','review_rounds':TEN_ROUND_REVIEW_ROUNDS,'gate':'tests/run-ten-round-post-future18.py','scope':'historical first ten-round hardening'},\n          'second_ten_round_hardening':{'version':'1.0.0','review_rounds':SECOND_TEN_ROUND_REVIEW_ROUNDS,'gate':'tests/run-second-ten-round-hardening.py','audit':'SECOND-TEN-ROUND-HARDENING-AUDIT.md','scope':'fail-closed authorization/lifecycle, anti-replay evidence, durable proof, truthful readiness and release consistency'},",1)
build=build.replace("'ten_round_post_future18_review_rounds':TEN_ROUND_REVIEW_ROUNDS,","'ten_round_post_future18_review_rounds':TEN_ROUND_REVIEW_ROUNDS,\n          'second_ten_round_review_rounds':SECOND_TEN_ROUND_REVIEW_ROUNDS,",2)
build=build.replace("'sabri:post-future18-hardening','value':'ten-round-v1'","'sabri:post-future18-hardening','value':'two-ten-round-audits-v2'",1)
build=build.replace("'coded':'v2.0.2 post-Future18 hardened candidate'","'coded':'v2.0.2 second-ten-round hardened candidate'",1)
build=build.replace("'ten_round_post_future18_review_rounds':TEN_ROUND_REVIEW_ROUNDS,\n      'known_unresolved_source_scope_blockers':0,","'ten_round_post_future18_review_rounds':TEN_ROUND_REVIEW_ROUNDS,\n      'second_ten_round_review_rounds':SECOND_TEN_ROUND_REVIEW_ROUNDS,\n      'known_unresolved_source_scope_blockers':0,",1)
build=build.replace("'ten_round_post_future18_review_rounds':TEN_ROUND_REVIEW_ROUNDS,\n        },sort_keys=True))","'ten_round_post_future18_review_rounds':TEN_ROUND_REVIEW_ROUNDS,\n          'second_ten_round_review_rounds':SECOND_TEN_ROUND_REVIEW_ROUNDS,\n        },sort_keys=True))",1)
build=build.replace("default=Path('/mnt/data/04-sabri-news-feed-legacy-adapter-2.0.2.zip')","default=Path('/mnt/data/04-sabri-news-feed-legacy-adapter-2.0.2.zip')")
write('tools/build-release.py',build)

# CI explicitly runs the second audit before deterministic packaging and in both PHP matrices.
workflow=read('.github/workflows/file04-legacy-adapter-ci.yml')
old="""      - name: Ten-round post-Future18 hardening gate
        run: python3 tests/run-ten-round-post-future18.py
      - name: Deterministic release evidence"""
new="""      - name: Ten-round post-Future18 hardening gate
        run: python3 tests/run-ten-round-post-future18.py
      - name: Second fresh ten-round hardening gate
        run: python3 tests/run-second-ten-round-hardening.py
      - name: Deterministic release evidence"""
if old not in workflow: raise SystemExit('workflow integrity insertion target missing')
workflow=workflow.replace(old,new,1)
old="""      - name: Ten-round post-Future18 hardening checks
        run: python3 tests/run-ten-round-post-future18.py
"""
new="""      - name: Ten-round post-Future18 hardening checks
        run: python3 tests/run-ten-round-post-future18.py
      - name: Second fresh ten-round hardening checks
        run: python3 tests/run-second-ten-round-hardening.py
"""
if old not in workflow: raise SystemExit('workflow matrix insertion target missing')
workflow=workflow.replace(old,new,1)
write('.github/workflows/file04-legacy-adapter-ci.yml',workflow)

# Remove one-time patch controls before committing the actual correction.
Path('tools/round9-selfpatch.py').unlink()
Path('.github/workflows/round9-selfpatch.yml').unlink()
