#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT=Path(__file__).resolve().parents[1]
def t(p): return (ROOT/p).read_text(encoding='utf-8')
def need(c,m,f):
    if not c: f.append(m)

f=[]
main=t('sabri-news-feed-legacy-adapter.php')
integrity=t('includes/class-snfla-integrity.php')
checksum=t('includes/class-snfla-checksum.php')
audit=t('includes/class-snfla-audit.php')
rest=t('includes/class-snfla-rest.php')
mapping=t('includes/class-snfla-mapping.php')
interactions=t('includes/class-snfla-interaction-provider.php')
file21=t('includes/class-snfla-file21-adapter.php')
migration=t('includes/class-snfla-migration.php')
db=t('includes/class-snfla-database.php')
workflow=t('.github/workflows/file04-legacy-adapter-ci.yml')
build=t('tools/build-release.py')
record=t('SECOND-EIGHTY-ROUND-HARDENING-AUDIT.md')

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
need("VERSION='2.0.5'" in build and 'SECOND_EIGHTY_ROUND_REVIEW_ROUNDS=80' in build and 'run-second-eighty-round-hardening.py' in build,'R26-R27 builder integration missing',f)
need('run-second-eighty-round-hardening.py' in workflow,'R27 CI integration missing',f)
for i in range(1,81): need(f'| {i} |' in record,f'audit record missing round {i}',f)

if f:
    print('Second 80-round gate failed:',file=sys.stderr)
    for item in f: print('-',item,file=sys.stderr)
    sys.exit(1)
print('Second 80-round gate passed for implemented controls; audit record enumerates all 80 rounds.')
