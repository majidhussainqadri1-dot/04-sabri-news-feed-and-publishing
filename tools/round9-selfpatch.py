from pathlib import Path


def read(path): return Path(path).read_text(encoding='utf-8')
def write(path, s): Path(path).write_text(s, encoding='utf-8')

# 1) Align current release surfaces/tests to the new patch release.
for path in [
    'sabri-news-feed-legacy-adapter.php','tests/run-architecture.py','tests/run-central-reviews.py',
    'tests/run-future18.py','tests/run-future18-reviews.py','tests/run-ten-round-post-future18.py',
    'tools/build-release.py','.github/workflows/file04-legacy-adapter-ci.yml']:
    s=read(path).replace('2.0.1','2.0.2')
    write(path,s)

# 2) WordPress readme and changelog.
wp=read('readme.txt').replace('Stable tag: 2.0.1','Stable tag: 2.0.2').replace('Version 2.0.1 retains','Version 2.0.2 retains')
if '= 2.0.2 =' not in wp:
    entry="""= 2.0.2 =
* Second fresh ten-round adversarial hardening after v2.0.1.
* Capability filters are deny-only and cannot grant migration authority.
* Corrupted lifecycle state fails closed instead of reopening legacy mutations.
* Future18 visual/redirect/DR and File21 media evidence is bound to the current source/request.
* Contract drift uses an explicit signed source-bound baseline and cannot self-clear.
* Receipt, GameDay and redirect evidence persistence/audit paths fail closed.
* File26/system evidence is bound and source code cannot self-promote production readiness.
* Canary approval requires real observability samples.
* Added deterministic second-ten-round audit QA and v2.0.2 release integration.

"""
    wp=wp.replace('== Changelog ==\n\n','== Changelog ==\n\n'+entry,1)
write('readme.txt',wp)

# 3) README current section, keeping first audit historical.
rd=read('README.md').replace('v2.0.1','v2.0.2')
start=rd.find('## v2.0.2 post-Future18 hardening')
end=rd.find('\n## Controlled runtime sequence', start)
if start < 0 or end < 0:
    raise SystemExit('README hardening section boundary missing')
section="""## v2.0.2 second ten-round hardening

The first ten-round audit remains historical v2.0.1 evidence. A second fresh ten-round audit reopened that corrected source and found further fail-closed gaps in capability-filter authority, corrupted lifecycle handling, stale provider-evidence replay, self-clearing contract drift, evidence durability, source-vs-production readiness, zero-sample canary approval and File21 media attestation binding. Those defects are corrected in v2.0.2 without changing canonical ownership.

The storage schema intentionally remains **v1.3.0** because these corrections add no custom-table schema migration.
"""
rd=rd[:start]+section+rd[end:]
rd=rd.replace('See `TEN-ROUND-POST-FUTURE18-AUDIT.md` for the ten review rounds', 'See `TEN-ROUND-POST-FUTURE18-AUDIT.md` for the historical first ten rounds and `SECOND-TEN-ROUND-HARDENING-AUDIT.md` for this second fresh audit')
write('README.md',rd)

# 4) Truthful in-progress status before Round 10.
write('STATUS.md',"""# File 04 v2.0.2 — Second Ten-Round Hardening Truthful Status Register

`source_status=v2.0.2-second-ten-round-review-in-progress`
`staging_accepted_pending=true`
`live_deployed=false`
`operational=false`

| Status | Decision | Evidence boundary |
|---|---|---|
| Specified | **Complete for current source scope** | Consolidated central plan + File 04 plan + 71 CV + 2 CEN + 15 AJ + FR-001..013 + NFR-001..010 + F04-FUT-001..018 |
| Coded | **Corrective source implemented through Round 9** | Fail-closed authorization/lifecycle, anti-replay evidence, explicit baseline, durable proof, truthful release state and observability gates |
| Packaged | **v2.0.2 candidate pending exact-head deterministic gate** | Two installable and two complete-source builds must be byte-identical |
| Automated-QA Green | **Pending exact final head** | Architecture, own-plan, central-plan, Future18, both ten-round gates, secret/PII scan, PHP 8.1/8.3 |
| Staging-Accepted | **Pending** | Hostinger + real File00/21/26 + browser/RTL/WCAG + restore/rollback/DR evidence |
| Live-Deployed | **Pending** | Founder-approved controlled deployment only |
| Operational | **Pending** | Real monitoring/SLO/support/incident/backup/retirement evidence required |

## Second ten-round audit progress

Defects were found and corrected in **Rounds 2, 3, 4, 5, 6, 7, 8 and 9**. **Round 1 found no new defect. Round 10 is intentionally pending until the corrected Round-9 head receives a fresh final adversarial regression.**

Source code cannot mark Staging-Accepted, Live-Deployed or Operational. `production_ready` stays false at source level until external acceptance exists. Storage schema remains **1.3.0**.
""")

# 5) Second audit record; do not pre-certify Round 10.
write('SECOND-TEN-ROUND-HARDENING-AUDIT.md',"""# File 04 — Second Fresh Ten-Round Hardening Audit

**Candidate release:** 2.0.2  
**Storage schema:** 1.3.0  
**Base reviewed:** main `cc296b06ec732da708480e6ab61e920db9ad5f03`  
**Method:** fresh Review → immediate correction → affected regression. External staging/live/operational evidence remains separate.

| Round | Focus | Result / correction |
|---|---|---|
| 1 | Governing scope and canonical ownership | **No new defect.** Adapter-only boundary preserved. |
| 2 | Authorization extension hooks | **Defect.** Filters could elevate denied authority; changed to deny-only semantics. |
| 3 | Lifecycle corruption | **Defect.** Unknown state could fall back to `legacy_active`; invalid state now fails closed. |
| 4 | Provider evidence replay | **Defect.** Visual/redirect/DR evidence was not bound to current source/request; source signature + request digest binding added. |
| 5 | Contract drift | **Defect.** Observation could auto-clear its own baseline; explicit signed source-bound baseline added. |
| 6 | Receipt durability | **Defect.** Persistence/audit failure could still return receipt success; now fail-closed with rollback. |
| 7 | System/release truth | **Defect.** File26/audit evidence was insufficiently blocking and source checks could imply production readiness; fixed binding/blocker propagation and source-level `production_ready=false`. |
| 8 | Observability, media evidence, proof durability | **Defect.** Zero-sample canary, unbound File21 media attestations and evidence-durability false-success paths were corrected. |
| 9 | Release/version/QA integration | **Defect.** Material corrections still identified as v2.0.1 and had no deterministic second-audit gate; aligned v2.0.2 runtime/docs/builder/CI/tests. |
| 10 | Final fresh adversarial regression after Round 9 | **PENDING — not pre-certified.** |

## Evidence boundary
Source/repository review does not prove Hostinger staging, real File00/File21/File26 providers/data, real-device accessibility, isolated restore, rollback rehearsal, Founder approval, live deployment or operations.
""")

# 6) Deterministic second-audit gate for completed R1-R9.
write('tests/run-second-ten-round-hardening.py',r'''#!/usr/bin/env python3
from pathlib import Path
import sys
ROOT=Path(__file__).resolve().parents[1]
def t(p): return (ROOT/p).read_text(encoding='utf-8')
def need(c,m,f):
    if not c:f.append(m)
f=[]
main=t('sabri-news-feed-legacy-adapter.php');caps=t('includes/class-snfla-capabilities.php');schema=t('includes/class-snfla-schema.php');ret=t('includes/class-snfla-retirement.php');future=t('includes/class-snfla-future18.php');hard=t('includes/class-snfla-post-audit-hardening.php');plan=t('includes/class-snfla-plan-completion.php');wf=t('.github/workflows/file04-legacy-adapter-ci.yml');build=t('tools/build-release.py');audit=t('SECOND-TEN-ROUND-HARDENING-AUDIT.md');allphp='\n'.join(p.read_text(encoding='utf-8') for p in ROOT.rglob('*.php') if '.git' not in p.parts and 'tests' not in p.parts)
need('Version: 2.0.2' in main and "SNFLA_VERSION', '2.0.2'" in main,'v2.0.2 runtime missing',f)
need("SNFLA_SCHEMA_VERSION', '1.3.0'" in main,'schema must remain 1.3.0',f)
need("apply_filters( 'snfla_actor_capability_allowed', true" in caps and "apply_filters( 'snfla_read_capability_allowed', true" in caps,'capability filters must be deny-only',f)
need("const INVALID_STATE = 'invalid'" in schema and 'snfla_lifecycle_state_invalid' in schema and 'in_array( $state, SNFLA_Schema::states(), true )' in ret,'lifecycle invalid-state fail-closed missing',f)
need('provider_request_bound' in hard and 'request_digest' in hard and 'source_signature' in hard and 'request_digest' in future,'provider anti-replay binding missing',f)
need("'/future/contract-baseline'" in future and 'record_contract_baseline' in future and 'baseline_missing_or_invalid' in future,'explicit contract baseline missing',f)
need('snfla_receipt_persist_failed' in future and 'snfla_receipt_audit_failed' in future,'receipt durability fail-closed missing',f)
need('manifest_digest' in plan and "'production_ready'] = false" in plan and 'file04_system_' in plan,'system/File26/production truth hardening missing',f)
need('observability_samples_missing' in future and 'sample_count' in future,'zero-sample canary blocker missing',f)
need('snfla_media_source_signature_invalid' in plan and 'provider_bound' in plan and 'verify_bound' in plan,'File21 media evidence binding missing',f)
need('snfla_gameday_persist_failed' in future and 'snfla_gameday_audit_failed' in future,'GameDay durability fail-closed missing',f)
need('persistence_failed' in hard and 'audit_persistence_failed' in hard,'redirect proof durability fail-closed missing',f)
need('run-second-ten-round-hardening.py' in wf and 'run-second-ten-round-hardening.py' in build,'second-audit gate missing from CI/builder',f)
need("VERSION='2.0.2'" in build and 'SECOND_TEN_ROUND_REVIEW_ROUNDS=10' in build,'builder v2.0.2 second-audit metadata missing',f)
need('Round 10' in audit and 'PENDING' in audit,'Round10 must remain pending before final fresh review',f)
need('wp_insert_post(' not in hard and '$wpdb->posts' not in hard and 'SNFLA_Migration::migrate(' not in hard,'duplicate backend introduced',f)
need('LegacyPublicationMigration::migrate_selected' in allphp and 'LegacyPublicationRollback::rollback_selected' in allphp,'File21 canonical command boundary missing',f)
if f:
 print('Second ten-round gate failed:',file=sys.stderr)
 [print('-',x,file=sys.stderr) for x in f]
 sys.exit(1)
print('Second ten-round hardening gate passed for completed Rounds 1-9; Round 10 remains pending fresh final review.')
''')

# 7) Builder integrates this review gate and evidence. Use guarded string transforms.
b=read('tools/build-release.py')
if 'SECOND_TEN_ROUND_REVIEW_ROUNDS=10' not in b:
    b=b.replace('TEN_ROUND_REVIEW_ROUNDS=10\nHISTORICAL_REVIEW_ROUNDS=40','TEN_ROUND_REVIEW_ROUNDS=10\nSECOND_TEN_ROUND_REVIEW_ROUNDS=10\nHISTORICAL_REVIEW_ROUNDS=40',1)
if "tests/run-second-ten-round-hardening.py" not in b:
    anchor="subprocess.run([sys.executable,str(ROOT/'tests/run-ten-round-post-future18.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)"
    b=b.replace(anchor,anchor+"\n    subprocess.run([sys.executable,str(ROOT/'tests/run-second-ten-round-hardening.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)",1)
if "'second_ten_round_hardening'" not in b:
    anchor="'post_future18_hardening':{'version':'1.0.0','review_rounds':TEN_ROUND_REVIEW_ROUNDS,'gate':'tests/run-ten-round-post-future18.py','scope':'authorization, REST failures, visual/redirect/DR evidence, receipt authenticity, release consistency'},"
    addition=anchor+"\n          'second_ten_round_hardening':{'version':'1.0.0','review_rounds':SECOND_TEN_ROUND_REVIEW_ROUNDS,'gate':'tests/run-second-ten-round-hardening.py','audit':'SECOND-TEN-ROUND-HARDENING-AUDIT.md','scope':'second fresh fail-closed/security/reliability review'},"
    b=b.replace(anchor,addition,1)
# add second review count after every first-ten count location if absent nearby
b=b.replace("'ten_round_post_future18_review_rounds':TEN_ROUND_REVIEW_ROUNDS,\n", "'ten_round_post_future18_review_rounds':TEN_ROUND_REVIEW_ROUNDS,\n          'second_ten_round_review_rounds':SECOND_TEN_ROUND_REVIEW_ROUNDS,\n")
b=b.replace("'coded':'v2.0.2 post-Future18 hardened candidate'","'coded':'v2.0.2 second-ten-round hardened candidate'")
b=b.replace("'sabri:post-future18-hardening','value':'ten-round-v1'","'sabri:post-future18-hardening','value':'two-ten-round-audits-v2'")
write('tools/build-release.py',b)

# 8) CI executes second audit before packaging and in PHP matrix.
w=read('.github/workflows/file04-legacy-adapter-ci.yml')
if 'Second fresh ten-round hardening gate' not in w:
    w=w.replace('      - name: Deterministic release evidence\n', '      - name: Second fresh ten-round hardening gate\n        run: python3 tests/run-second-ten-round-hardening.py\n      - name: Deterministic release evidence\n',1)
if 'Second fresh ten-round hardening checks' not in w:
    w=w.rstrip()+"\n      - name: Second fresh ten-round hardening checks\n        run: python3 tests/run-second-ten-round-hardening.py\n"
write('.github/workflows/file04-legacy-adapter-ci.yml',w)

# 9) Self-delete temporary patch controls.
Path('tools/round9-selfpatch.py').unlink()
Path('.github/workflows/round9-selfpatch.yml').unlink()
