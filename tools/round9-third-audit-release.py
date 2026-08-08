from pathlib import Path

ROOT=Path('.')
def read(path): return Path(path).read_text(encoding='utf-8')
def write(path,s): Path(path).write_text(s,encoding='utf-8')

# Current executable QA surfaces must follow the new patch release. Historical
# audit documents are intentionally not rewritten.
for path in [
    'tests/run-architecture.py','tests/run-central-reviews.py','tests/run-future18.py',
    'tests/run-future18-reviews.py','tests/run-ten-round-post-future18.py',
    'tests/run-second-ten-round-hardening.py','tools/build-release.py',
    '.github/workflows/file04-legacy-adapter-ci.yml'
]:
    s=read(path).replace('2.0.2','2.0.3')
    write(path,s)

# WordPress package metadata: preserve historical changelog entries.
wp=read('readme.txt')
wp=wp.replace('Stable tag: 2.0.2','Stable tag: 2.0.3',1)
wp=wp.replace('Version 2.0.2 retains', 'Version 2.0.3 retains', 1)
if '= 2.0.3 =' not in wp:
    entry="""= 2.0.3 =
* Third fresh ten-round adversarial audit of the corrected v2.0.2 source.
* Implemented the plan-required time-bounded immutable legacy read fallback instead of tombstone-only behavior.
* Prevented checksum collisions when JSON encoding fails and made audit JSON evidence fail closed.
* Preserved exact opaque idempotency keys instead of normalizing them through human-text sanitization.
* Bound restore, cutover cache/search and retirement route-handoff provider attestations to exact current requests/source evidence with fresh timestamps.
* Prevented same-path query/fragment redirect loops by validating normalized same-origin paths.
* Made mapping/interaction evidence JSON persistence fail closed on encoding errors.
* Added a deterministic third-ten-round audit gate and v2.0.3 exact-head release evidence.

"""
    wp=wp.replace('== Changelog ==\n\n','== Changelog ==\n\n'+entry,1)
write('readme.txt',wp)

write('README.md',"""# File 04 — News Feed and Publishing — Legacy Foundation Adapter v2.0.3

File 04 remains a **temporary, write-disabled, auditable and reversible migration/compatibility adapter**. File 21 is the sole canonical publication/Home/News/feed owner; File 26 owns search/discovery; File 20 owns the shell; File 25 owns the visual system; File 24 coordinates assurance. File 04 does not create a second composer, feed, ranking service, comments/reactions store, moderation backend, search engine or permanent public route system.

## Governing scope

The source trace covers the consolidated governing plan, File 04 FR-001..013 and NFR-001..010, 71 applicable CV requirements, F04-CEN-01..02, 15 acceptance journeys and F04-FUT-001..018.

## v2.0.3 third fresh ten-round hardening

A third independent ten-round review reopened the merged v2.0.2 source rather than reusing earlier results. Rounds 1–9 found and corrected: the tombstone-only fallback/plan mismatch; JSON/hash false-success behavior; opaque idempotency-key normalization; stale restore-verifier evidence; stale cutover cache/search evidence; replayable retirement handoff evidence; same-path redirect-loop risk; mapping evidence serialization false success; and release/QA metadata drift. Round 10 is reserved for a fresh exact-head adversarial regression after all Round-9 changes.

Storage schema remains **1.3.0** because these corrections add no custom-table schema migration.

## Truthful lifecycle status

Repository/source completion is separate from Hostinger staging, live deployment and operations. Source code must never self-promote `production_ready`; real File00/File21/File26 contracts, real legacy data, browsers/devices/WCAG/RTL, backup/isolated restore, rollback/DR rehearsal, Founder acceptance and production monitoring remain external gates.

See `THIRD-TEN-ROUND-HARDENING-AUDIT.md`, `SECOND-TEN-ROUND-HARDENING-AUDIT.md`, `TEN-ROUND-POST-FUTURE18-AUDIT.md`, `FUTURE18-TRACEABILITY.md` and `REQUIREMENTS-TRACEABILITY.md`.
""")

write('STATUS.md',"""# File 04 v2.0.3 — Third Ten-Round Hardening Truthful Status Register

`source_status=v2.0.3-third-ten-round-review-in-progress`
`known_unresolved_source_scope_blockers=0`
`staging_accepted_pending=true`
`live_deployed=false`
`operational=false`

| Status | Decision | Evidence boundary |
|---|---|---|
| Specified | **Complete for current source scope** | Consolidated plan + File04 plan + CV/CEN/AJ + FR/NFR + Future18 |
| Coded | **Corrective source implemented through Round 9** | Third fresh audit corrections are present; Round 10 exact-head regression still pending |
| Packaged | **v2.0.3 candidate pending final exact-head gate** | Deterministic installable + complete-source double build |
| Automated-QA Green | **Pending final exact head** | Existing gates + new third-ten-round gate + PHP 8.1/8.3 + secret/PII scan |
| Staging-Accepted | **Pending** | Hostinger real integration/browser/restore/rollback evidence |
| Live-Deployed | **Pending** | Founder-approved controlled deployment |
| Operational | **Pending** | Sustained monitoring/support/backup/incident/retirement evidence |

## Third-audit progress

Defects were found and corrected in **Rounds 1, 2, 3, 4, 5, 6, 7, 8 and 9**. **Round 10 remains deliberately unclaimed until the fully corrected Round-9 head is freshly reviewed and exact-head CI is green.**

Canonical ownership remains unchanged and source code continues to report production/staging/operational acceptance as external pending gates.
""")

write('THIRD-TEN-ROUND-HARDENING-AUDIT.md',"""# File 04 — Third Fresh Ten-Round Hardening Audit

**Candidate release:** 2.0.3  
**Storage schema:** 1.3.0  
**Base reviewed:** main `54253e6de2dc68c2c57f7e0d4fd474bd0622de8e`  
**Method:** each round is a new Review → immediate correction → affected regression. Earlier ten-round audits are historical evidence only.

| Round | Focus | Result / correction |
|---|---|---|
| 1 | File04 plan behavior / controlled fallback | **Defect.** `read_only_fallback` was tombstone-only (410), conflicting with the plan's time-bounded read-only legacy fallback. Added immutable public-source fallback only while File21 target resolution is unavailable, with no-store/noindex and existing global write guards. |
| 2 | Evidence encoding / hashing / audit chain | **Defect.** JSON encoding failure could collapse checksums to SHA-256 of an empty string and audit context persistence could fail ambiguously. Added deterministic encode fallback for hashing and strict audit JSON rejection. |
| 3 | REST idempotency | **Defect.** `sanitize_text_field()` normalized opaque idempotency keys and could make distinct raw tokens collide. Keys are now exact printable ASCII bytes with strict length/character validation. |
| 4 | Backup/restore attestation | **Defect.** Restore verifier could return generic/stale `verified` evidence without proving the exact current request/source. Added request digest, source echo and fresh verification-time binding. |
| 5 | Cutover cache/search attestation | **Defect.** Cache/search provider evidence was not bound to the exact source/reconciliation request. Added source/reconciliation/request-digest/fresh-time verification. |
| 6 | Retirement route-handoff attestation | **Defect.** Batch/final route-handoff acknowledgements did not prove the exact request/previous-chain/source. Added request digests, source/chain echoes and freshness requirements. |
| 7 | Redirect loop integrity | **Defect.** Full-URL comparison could accept the same legacy path when only query/fragment differed. Redirect targets now require the same origin and a different normalized path. |
| 8 | Mapping/interactions evidence serialization | **Defect.** `wp_json_encode()` failure could persist blank/malformed ledger evidence while the write reported success. Mapping and interaction evidence now fail closed before DB writes. |
| 9 | Version/release/QA integration | **Defect.** Material third-audit changes were still represented as v2.0.2 and no deterministic third-audit gate existed. Runtime/docs/tests/builder/CI are aligned to v2.0.3 and the third-audit gate is first-class release evidence. |
| 10 | Final fresh adversarial regression after Round 9 | **PENDING — not pre-certified.** |

## Evidence boundary

This audit proves repository/source behavior only. Hostinger staging, real File00/File21/File26 providers and data, browser/device accessibility, isolated restore, rollback/DR rehearsal, Founder approval, live deployment and measured operations remain separate release gates.
""")

write('tests/run-third-ten-round-hardening.py',r'''#!/usr/bin/env python3
from pathlib import Path
import sys
ROOT=Path(__file__).resolve().parents[1]
def t(p): return (ROOT/p).read_text(encoding='utf-8')
def need(c,m,f):
    if not c:f.append(m)
f=[]
main=t('sabri-news-feed-legacy-adapter.php'); redirects=t('includes/class-snfla-redirects.php'); checksum=t('includes/class-snfla-checksum.php'); audit=t('includes/class-snfla-audit.php'); rest=t('includes/class-snfla-rest.php'); migration=t('includes/class-snfla-migration.php'); recon=t('includes/class-snfla-reconciliation.php'); retirement=t('includes/class-snfla-retirement.php'); mapping=t('includes/class-snfla-mapping.php'); workflow=t('.github/workflows/file04-legacy-adapter-ci.yml'); build=t('tools/build-release.py'); record=t('THIRD-TEN-ROUND-HARDENING-AUDIT.md')
need('Version: 2.0.3' in main and "SNFLA_VERSION', '2.0.3'" in main,'v2.0.3 runtime metadata missing',f)
need("SNFLA_SCHEMA_VERSION', '1.3.0'" in main,'storage schema must remain 1.3.0',f)
need('legacy_public_fallback_allowed' in redirects and 'public_source_fallback' in redirects and 'X-Sabri-File04-Fallback' in redirects and "'tombstone_only' => false" in redirects,'Round1 read-only fallback implementation missing',f)
need('snfla-ser-v1:' in checksum and 'base64_encode( serialize( $canonical ) )' in checksum and "return '';" not in checksum.split('public static function encode',1)[1].split('public static function hash',1)[0],'Round2 checksum encoding must not collapse to empty input',f)
need('audit_context_json_invalid' in audit and 'if ( ! is_string( $context_json ) )' in audit,'Round2 audit JSON fail-closed check missing',f)
need("preg_match( '/^[!-~]+$/D', $key )" in rest and "sanitize_text_field( (string) $request->get_header( 'Idempotency-Key' )" not in rest,'Round3 exact idempotency-token validation missing',f)
need("$verification_request['request_digest']" in migration and 'provider_bound' in migration and '15 * MINUTE_IN_SECONDS' in migration,'Round4 restore attestation binding missing',f)
need("$context['request_digest']" in recon and 'integration_evidence_valid( $cache_evidence' in recon and 'reconciliation_checksum' in recon and '15 * MINUTE_IN_SECONDS' in recon,'Round5 cutover attestation binding missing',f)
need("$request['request_digest']" in retirement and "$summary['request_digest']" in retirement and 'previous_checksum' in retirement and 'verified_at_utc' in retirement,'Round6 retirement handoff binding missing',f)
need('rawurldecode' in redirects and '$target_path !== $legacy_path' in redirects and '$home_port !== $target_port' in redirects,'Round7 normalized same-origin redirect-loop guard missing',f)
need('$interaction_json = wp_json_encode' in mapping and 'if ( ! is_string( $interaction_json ) )' in mapping and '$original_json = wp_json_encode' in mapping and 'if ( ! is_string( $original_json ) )' in mapping,'Round8 mapping evidence encoding guards missing',f)
need('run-third-ten-round-hardening.py' in workflow and 'run-third-ten-round-hardening.py' in build,'Round9 third audit gate must run in CI and builder',f)
need("VERSION='2.0.3'" in build and 'THIRD_TEN_ROUND_REVIEW_ROUNDS=10' in build,'Round9 builder metadata missing',f)
need("'audit-file-04-*'" in workflow,'Current third-audit branch family must be covered by push CI',f)
need('Round 10' in record and ('PENDING' in record or 'No new defect' in record or 'Defect.' in record),'Round10 state must be explicit',f)
need('wp_insert_post(' not in redirects and 'SNFLA_Migration::migrate(' not in redirects,'Third audit must not add a publication/migration backend',f)
if f:
 print('Third ten-round hardening gate failed:',file=sys.stderr)
 [print('-',x,file=sys.stderr) for x in f]
 sys.exit(1)
print('Third ten-round hardening source gate passed for implemented controls and audit trace.')
''')

# Deterministic builder: add a third audit gate/evidence dimension.
b=read('tools/build-release.py')
if 'THIRD_TEN_ROUND_REVIEW_ROUNDS=10' not in b:
    b=b.replace('SECOND_TEN_ROUND_REVIEW_ROUNDS=10\nHISTORICAL_REVIEW_ROUNDS=40','SECOND_TEN_ROUND_REVIEW_ROUNDS=10\nTHIRD_TEN_ROUND_REVIEW_ROUNDS=10\nHISTORICAL_REVIEW_ROUNDS=40',1)
gate="subprocess.run([sys.executable,str(ROOT/'tests/run-third-ten-round-hardening.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)"
if gate not in b:
    anchor="subprocess.run([sys.executable,str(ROOT/'tests/run-second-ten-round-hardening.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)"
    b=b.replace(anchor,anchor+'\n    '+gate,1)
if "'third_ten_round_hardening'" not in b:
    anchor="'second_ten_round_hardening':{'version':'1.0.0','review_rounds':SECOND_TEN_ROUND_REVIEW_ROUNDS,'gate':'tests/run-second-ten-round-hardening.py','audit':'SECOND-TEN-ROUND-HARDENING-AUDIT.md','scope':'second fresh fail-closed/security/reliability review'},"
    b=b.replace(anchor,anchor+"\n          'third_ten_round_hardening':{'version':'1.0.0','review_rounds':THIRD_TEN_ROUND_REVIEW_ROUNDS,'gate':'tests/run-third-ten-round-hardening.py','audit':'THIRD-TEN-ROUND-HARDENING-AUDIT.md','scope':'third fresh plan/anti-replay/serialization/release review'},",1)
b=b.replace("'second_ten_round_review_rounds':SECOND_TEN_ROUND_REVIEW_ROUNDS,\n", "'second_ten_round_review_rounds':SECOND_TEN_ROUND_REVIEW_ROUNDS,\n          'third_ten_round_review_rounds':THIRD_TEN_ROUND_REVIEW_ROUNDS,\n")
b=b.replace("'coded':'v2.0.3 second-ten-round hardened candidate'","'coded':'v2.0.3 third-ten-round hardened candidate'")
b=b.replace("'sabri:post-future18-hardening','value':'two-ten-round-audits-v2'","'sabri:post-future18-hardening','value':'three-ten-round-audits-v3'")
write('tools/build-release.py',b)

# Main CI: support the hyphenated audit branch and execute the third audit in both jobs.
w=read('.github/workflows/file04-legacy-adapter-ci.yml')
if "'audit-file-04-*'" not in w:
    w=w.replace("      - 'audit/file-04-*'\n","      - 'audit/file-04-*'\n      - 'audit-file-04-*'\n",1)
if 'Third fresh ten-round hardening gate' not in w:
    w=w.replace('      - name: Deterministic release evidence\n','      - name: Third fresh ten-round hardening gate\n        run: python3 tests/run-third-ten-round-hardening.py\n      - name: Deterministic release evidence\n',1)
if 'Third fresh ten-round hardening checks' not in w:
    w=w.rstrip()+"\n      - name: Third fresh ten-round hardening checks\n        run: python3 tests/run-third-ten-round-hardening.py\n"
w=w.replace('Two-Ten-Round Hardening CI','Three-Ten-Round Hardening CI')
w=w.replace('Reproducible v2.0.3 package twice','Reproducible v2.0.3 package twice')
write('.github/workflows/file04-legacy-adapter-ci.yml',w)

Path('tools/round9-third-audit-release.py').unlink()
Path('.github/workflows/round9-third-audit-release.yml').unlink()
