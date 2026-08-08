#!/usr/bin/env python3
from pathlib import Path
import re, subprocess, sys

ROOT = Path(__file__).resolve().parents[1]

def text(path):
    return (ROOT / path).read_text(encoding='utf-8')

def report(name, checks):
    failed=[message for ok,message in checks if not ok]
    if failed:
        print(f'{name}: FAIL', file=sys.stderr)
        for message in failed: print('-',message,file=sys.stderr)
        return False
    print(f'{name}: PASS ({len(checks)} checks)')
    return True

main=text('sabri-news-feed-legacy-adapter.php')
central=text('includes/class-snfla-central-plan.php')
completion=text('includes/class-snfla-plan-completion.php')
migration=text('includes/class-snfla-migration.php')
plugin=text('includes/class-snfla-plugin.php')
file21=text('includes/class-snfla-file21-adapter.php')
reconciliation=text('includes/class-snfla-reconciliation.php')
css=text('assets/css/admin.css')
workflow=text('.github/workflows/file04-legacy-adapter-ci.yml')
build=text('tools/build-release.py')
trace=text('REQUIREMENTS-TRACEABILITY.md')
status=text('STATUS.md')
all_php='\n'.join(p.read_text(encoding='utf-8') for p in ROOT.rglob('*.php') if '.git' not in p.parts and 'tests' not in p.parts)

round1=report('Central-plan Review/Fix Round 1 — requirements, ownership and File 04 plan traceability',[
    ('Version: 2.0.0' in main, 'Runtime must be promoted to 2.0.0.'),
    ('class-snfla-central-plan.php' in main and 'SNFLA_Central_Plan::boot' in main, 'Central-plan contract layer must be loaded.'),
    ('class-snfla-plan-completion.php' in main and 'SNFLA_Plan_Completion::boot' in main, 'File 04 own-plan completion layer must be loaded.'),
    ('array( 37, 49 )' in central and 'array( 74, 84 )' in central and 'array( 239, 285 )' in central, 'All 71 File-04-applicable CV requirements must be registered.'),
    ('F04-CEN-01' in central and 'F04-CEN-02' in central, 'Both File-specific central requirements must be registered.'),
    ('File 21' in central and 'File 26' in central and 'File 20' in central and 'File 25' in central and 'File 24' in central, 'Canonical owner boundaries must be explicit.'),
    ('integration_regression_only' in central and 'duplicate truth store' in central, 'Modern features owned elsewhere must not become File 04 backends.'),
    ('estimated_dispositions' in migration and 'storage_estimate' in migration and 'time_estimate' in migration and 'sample_diffs' in migration, 'File 04 dry-run must implement its own plan evidence fields.'),
    ('sabri_file00_platform_uuid_v1' in completion and 'sabri_file21_legacy_media_preflight_v1' in completion, 'File 00 authorship and File 21 media/reference contracts must be explicit.'),
    ('Central CV/CEN/AJ ID' in trace, 'Human traceability document must describe the complete modern trace chain.'),
])

round2=report('Central-plan Review/Fix Round 2 — fresh adversarial/source regression',[
    (re.search(r'\bOFFSET\b|\bTRUNCATE\b|maybe_unserialize|(?<![A-Za-z_])unserialize\s*\(|wp_cache_flush\s*\(', all_php, re.I) is None, 'Forbidden unsafe patterns must remain absent.'),
    ('wp_insert_post(' not in central and '$wpdb->posts' not in central, 'Central-plan layer must not write canonical publication storage.'),
    ('LegacyPublicationMigration::migrate_selected' in file21 and 'LegacyPublicationRollback::rollback_selected' in file21, 'Canonical migration/rollback must stay behind File 21 commands.'),
    ('do_not_allow' in plugin and 'block_legacy_comment_write' in plugin and 'block_legacy_meta_write' in plugin, 'Legacy truth writes must remain fail-closed.'),
    ("LIMIT %d" in completion and 'ID>%d' in completion and "numberposts' => -1" not in completion and "posts_per_page' => -1" not in completion, 'Media/reference traversal must be bounded and keyset based.'),
    ('p75_ms' in completion and 'p95_ms' in completion and 'snfla_operational_alert_v1' in completion, 'Performance and observability evidence must exist.'),
    ('snfla_verify_cutover_cache_invalidation' in reconciliation and 'snfla_verify_cutover_search_reindex' in reconciliation, 'Cutover must require cache and canonical-search evidence.'),
    ('focus-visible' in css and 'prefers-reduced-motion' in css and 'direction:rtl' in css and 'unicode-bidi' in css, 'RTL/accessibility/reduced-motion source safeguards must exist.'),
    ('codex/file-04-*' in workflow and 'run-central-plan.php' in workflow and 'run-file04-own-plan.py' in workflow and 'run-central-reviews.py' in workflow, 'Workflow must target modern File 04 branches and execute all current review gates.'),
    ("VERSION='2.0.0'" in build and 'central_plan_review_rounds' in build, 'Deterministic package generator must identify v2.0.0 and the two new review rounds.'),
    ('production_ready' in central and 'staging_accepted_pending' in status, 'Source completion must not fabricate staging/production acceptance.'),
])

for command,label in [
    (['php', str(ROOT/'tests/run-central-plan.php')], 'central-plan contract checks'),
    ([sys.executable, str(ROOT/'tests/run-file04-own-plan.py')], 'File 04 own-plan checks'),
]:
    proc=subprocess.run(command,cwd=ROOT,text=True,capture_output=True)
    if proc.returncode != 0:
        print(proc.stdout,end='')
        print(proc.stderr,end='',file=sys.stderr)
        if label.startswith('central'): round1=False
        else: round2=False
    else:
        print(proc.stdout,end='')

if not (round1 and round2):
    sys.exit(1)
print('Two consecutive post-plan source reviews passed with zero known source-review blockers in the tested scope.')
