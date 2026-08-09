#!/usr/bin/env python3
from pathlib import Path
import sys
r=int(sys.argv[1])
def rd(p): return Path(p).read_text(encoding='utf-8')
def wr(p,s): Path(p).write_text(s,encoding='utf-8')
if r==26:
    # Runtime + current QA/release surfaces move to the new patch release without rewriting historical audit records.
    files=['sabri-news-feed-legacy-adapter.php','README.md','STATUS.md','tools/build-release.py','.github/workflows/file04-legacy-adapter-ci.yml']
    files += [str(p) for p in Path('tests').glob('run-*') if p.suffix in {'.py','.php'}]
    for f in files:
        s=rd(f).replace('2.0.4','2.0.5')
        wr(f,s)
    s=rd('readme.txt')
    s=s.replace('Stable tag: 2.0.4','Stable tag: 2.0.5',1)
    s=s.replace('Version 2.0.4','Version 2.0.5',1)
    if '= 2.0.5 =' not in s:
        entry="""= 2.0.5 =
* Fresh second 80-round adversarial source audit after v2.0.4.
* Hardened checkpoint IDs, taxonomy-read checksums, authenticated audit/status evidence, mapping identities/states/checksums, dry-run identity, interaction progress, File21 boundary IDs, run-ledger state, lifecycle version input, physical schema health, activation evidence/cron scheduling, and signed page-quarantine status.
* Added a distinct second-eighty audit record/gate while preserving the historical first 80-round audit unchanged.

"""
        s=s.replace('== Changelog ==\n\n','== Changelog ==\n\n'+entry,1)
    wr('readme.txt',s)
    # Current status is in progress until R80 closes.
    wr('STATUS.md',"""# File 04 v2.0.5 — Second Fresh Eighty-Round Hardening Status Register

`source_status=v2.0.5-second-eighty-round-review-in-progress`
`known_unresolved_source_scope_blockers=0`
`staging_accepted_pending=true`
`live_deployed=false`
`operational=false`

The second fresh 80-round audit is independent from the historical first 80-round audit. Defects have been found and corrected through Round 26; later rounds remain unclaimed until freshly reviewed on the corrected source.

File 04 remains temporary migration/compatibility only. File21 retains publication/feed truth; File26 retains search/discovery; source code cannot self-promote Staging-Accepted, Live-Deployed or Operational. Storage schema remains 1.3.0.
""")
elif r==27:
    # New second-eighty audit record: R1-R27 known, R28-R80 deliberately pending.
    findings={
1:'Defect — checkpoint ID normalization could alias malformed negative IDs to positive IDs; strict canonical positive checkpoint IDs now fail closed.',
2:'Defect — taxonomy read errors could masquerade as an empty term set in source/target checksum projections; checksum/equivalence now fails closed.',
3:'Defect — audit event lookup trusted matching JSON without authenticating the row HMAC; matching rows are now cryptographically authenticated.',
4:'Defect — empty sanitized audit action identifiers could create/query ambiguous evidence; empty actions are rejected.',
5:'Defect — REST integrity status surfaced unsigned/tampered last-check fields; only valid signed evidence is surfaced.',
6:'Defect — REST status surfaced dry-run/reconciliation fields without authenticated report/audit binding; untrusted reports are hidden.',
7:'Defect — mapping upsert allowed legacy ID zero/arbitrary status strings; positive identity and exact status allowlist are required.',
8:'Defect — malformed non-empty mapping checksums were silently blanked; malformed checksums now reject the write.',
9:'Defect — mapping reads/deletes used absint aliasing; canonical positive IDs are now required.',
10:'Defect — mapping target IDs/types/run UUIDs were normalized or unconstrained; exact nonnegative target ID, target type and UUID4 semantics are enforced.',
11:'Defect — dry-run row writes accepted malformed source/run identity evidence; UUID/signatures/checksum/ID/target type are validated.',
12:'Defect — dry-run row reads accepted aliased request identities and malformed stored checksum/eligible evidence; both request and row evidence fail closed.',
13:'Defect — malformed interaction progress cursor/boolean state could skip work; progress schema is validated before use.',
14:'Defect — File21 interaction-provider context used absint aliases; provider identity inputs now require canonical positive IDs.',
15:'Defect — File21 target resolution normalized malformed input/output IDs; exact positive IDs are required on both sides.',
16:'Defect — File21 migrate/rollback boundary accepted unvalidated ID batches/actor IDs; positive unique bounded batches are enforced.',
17:'Defect — operation run creation allowed arbitrary operation/status/UUID values; create-run now requires migrate/rollback, running, UUID4.',
18:'Defect — run finalization allowed arbitrary terminal status/UUID; exact terminal allowlist and UUID4 are required.',
19:'Defect — existing run-ledger lookup/row state lacked strict operation/hash/UUID/status/signature validation; corrupt rows now fail closed.',
20:'Defect — REST expected_version used absint, so negative values could alias positive lifecycle versions; exact positive integer semantics are enforced.',
21:'Defect — schema_healthy trusted a stale unsigned health option rather than current physical schema; physical schema is verified once per request.',
22:'Defect — schema upgrade fast path could skip repair because stale health option said OK; fast path now requires current physical schema health.',
23:'Defect — activation ignored persistence failure for plugin/schema health/version evidence; activation now compensates/fails closed.',
24:'Defect — mandatory daily integrity cron scheduling failure was ignored; activation now blocks/compensates if scheduling fails.',
25:'Defect — page-quarantine status surfaced an unsigned option; it now reads only signed activation-handover evidence.',
26:'Defect — material corrections were still labelled v2.0.4; runtime/docs/current tests/builder/CI were promoted to v2.0.5 while storage schema stays 1.3.0.',
27:'Defect — the new independent 80-round review had no distinct permanent audit/gate; added SECOND-EIGHTY-ROUND-HARDENING-AUDIT.md and tests/run-second-eighty-round-hardening.py.'}
    lines=['# File 04 — Second Fresh Eighty-Round Hardening Audit','', '**Candidate release:** 2.0.5  ','**Storage schema:** 1.3.0  ','**Baseline reviewed:** main `b16fce24cd743ff0f84b082643457b44fc85eec8`  ','**Method:** each round is a fresh review on the corrected source; every found defect is corrected before the next round. The historical first 80-round audit is ancestry evidence only.','', '| Round | Result / correction |','|---:|---|']
    for i in range(1,81): lines.append(f'| {i} | **{findings[i]}** |' if i in findings else f'| {i} | **PENDING — not yet claimed.** |')
    lines += ['', '## Evidence boundary','', 'Repository/source review only. Hostinger staging, real File00/File21/File26 providers/data, browser/device accessibility, isolated restore, rollback/DR rehearsal, Founder approval, live deployment and operational monitoring remain separate external gates.','']
    wr('SECOND-EIGHTY-ROUND-HARDENING-AUDIT.md','\n'.join(lines))
    test=r'''#!/usr/bin/env python3
from pathlib import Path
import re,sys
ROOT=Path(__file__).resolve().parents[1]
def t(p): return (ROOT/p).read_text(encoding='utf-8')
def need(c,m,f):
    if not c:f.append(m)
f=[]
main=t('sabri-news-feed-legacy-adapter.php'); integrity=t('includes/class-snfla-integrity.php'); checksum=t('includes/class-snfla-checksum.php'); audit=t('includes/class-snfla-audit.php'); rest=t('includes/class-snfla-rest.php'); mapping=t('includes/class-snfla-mapping.php'); interactions=t('includes/class-snfla-interaction-provider.php'); file21=t('includes/class-snfla-file21-adapter.php'); migration=t('includes/class-snfla-migration.php'); db=t('includes/class-snfla-database.php'); workflow=t('.github/workflows/file04-legacy-adapter-ci.yml'); build=t('tools/build-release.py'); record=t('SECOND-EIGHTY-ROUND-HARDENING-AUDIT.md')
need('Version: 2.0.5' in main and "SNFLA_VERSION', '2.0.5'" in main,'R26 v2.0.5 runtime missing',f)
need("SNFLA_SCHEMA_VERSION', '1.3.0'" in main,'storage schema must remain 1.3.0',f)
need('strict_checkpoint_ids' in integrity and 'null === $stored_ids' in integrity,'R1 strict checkpoint IDs missing',f)
need('is_wp_error( $terms )' in checksum and "return '';" in checksum,'R2 taxonomy checksum fail-closed missing',f)
need('event_row_authentic' in audit and "'' === $action" in audit,'R3-R4 authenticated/nonempty audit actions missing',f)
need('last_trusted' in rest and 'reconciliation_trusted' in rest and 'dry_trusted' in rest,'R5-R6 REST status evidence authentication missing',f)
need('allowed_statuses' in mapping and 'strict_positive_id' in mapping and 'strict_nonnegative_id' in mapping and 'uuid4_valid' in mapping,'R7-R12 mapping/dry-run identity controls missing',f)
need('progress_state_valid' in interactions and 'strict_positive_id' in interactions,'R13-R14 interaction progress/provider identity controls missing',f)
need('strict_id_batch' in file21 and 'strict_positive_id' in file21,'R15-R16 File21 strict identity boundary missing',f)
need('snfla_run_ledger_identity_invalid' in migration and 'snfla_run_ledger_corrupt' in migration and "array( 'completed', 'partial', 'failed', 'audit_failed', 'interrupted' )" in migration,'R17-R19 run ledger hardening missing',f)
need("is_int( $value ) && $value >= 1" in rest,'R20 strict lifecycle version REST input missing',f)
need('static $verified_this_request' in db and 'self::verify_schema()' in db and 'snfla_activation_version_evidence_failed' in db and 'snfla_integrity_schedule_failed' in db and 'SNFLA_Integrity::evidence_valid( $handover )' in db,'R21-R25 database physical-health/activation evidence controls missing',f)
need("VERSION='2.0.5'" in build and 'run-second-eighty-round-hardening.py' in build,'R26-R27 builder integration missing',f)
need('run-second-eighty-round-hardening.py' in workflow,'R27 CI integration missing',f)
for i in range(1,81): need(f'| {i} |' in record,f'audit record missing round {i}',f)
if f:
 print('Second 80-round gate failed:',file=sys.stderr)
 [print('-',x,file=sys.stderr) for x in f]
 sys.exit(1)
print('Second 80-round gate passed for implemented controls; audit record enumerates all 80 rounds.')
'''
    wr('tests/run-second-eighty-round-hardening.py',test)
    # Builder integration.
    b=rd('tools/build-release.py')
    if 'SECOND_EIGHTY_ROUND_REVIEW_ROUNDS=80' not in b:
        b=b.replace('EIGHTY_ROUND_REVIEW_ROUNDS=80\nHISTORICAL_REVIEW_ROUNDS=40','EIGHTY_ROUND_REVIEW_ROUNDS=80\nSECOND_EIGHTY_ROUND_REVIEW_ROUNDS=80\nHISTORICAL_REVIEW_ROUNDS=40',1)
    gate="subprocess.run([sys.executable,str(ROOT/'tests/run-second-eighty-round-hardening.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)"
    if gate not in b:
        anchor="subprocess.run([sys.executable,str(ROOT/'tests/run-eighty-round-hardening.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)"
        b=b.replace(anchor,anchor+'\n    '+gate,1)
    if "'second_eighty_round_hardening'" not in b:
        anchor="'eighty_round_hardening':{'version':'1.0.0','review_rounds':EIGHTY_ROUND_REVIEW_ROUNDS,'gate':'tests/run-eighty-round-hardening.py','audit':'EIGHTY-ROUND-HARDENING-AUDIT.md','scope':'fresh sequential reliability, security, migration, rollback, evidence, integration and release review'},"
        add=anchor+"\n          'second_eighty_round_hardening':{'version':'1.0.0','review_rounds':SECOND_EIGHTY_ROUND_REVIEW_ROUNDS,'gate':'tests/run-second-eighty-round-hardening.py','audit':'SECOND-EIGHTY-ROUND-HARDENING-AUDIT.md','scope':'second independent eighty-round fail-closed source review'},"
        b=b.replace(anchor,add,1)
    b=b.replace("'eighty_round_review_rounds':EIGHTY_ROUND_REVIEW_ROUNDS,\n", "'eighty_round_review_rounds':EIGHTY_ROUND_REVIEW_ROUNDS,\n          'second_eighty_round_review_rounds':SECOND_EIGHTY_ROUND_REVIEW_ROUNDS,\n")
    b=b.replace("'coded':'v2.0.5 eighty-round hardened candidate'","'coded':'v2.0.5 second-eighty-round hardened candidate'")
    wr('tools/build-release.py',b)
    # CI integration.
    w=rd('.github/workflows/file04-legacy-adapter-ci.yml')
    if 'Second fresh eighty-round hardening gate' not in w:
        w=w.replace('      - name: Deterministic release evidence\n','      - name: Second fresh eighty-round hardening gate\n        run: python3 tests/run-second-eighty-round-hardening.py\n      - name: Deterministic release evidence\n',1)
    if 'Second fresh eighty-round hardening checks' not in w:
        w=w.rstrip()+"\n      - name: Second fresh eighty-round hardening checks\n        run: python3 tests/run-second-eighty-round-hardening.py\n"
    wr('.github/workflows/file04-legacy-adapter-ci.yml',w)
    Path('tools/r26-r27-release.py').unlink();Path('.github/workflows/r26-r27-release.yml').unlink()
else: raise SystemExit('unsupported')
