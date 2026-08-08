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
plugin=text('includes/class-snfla-plugin.php')
file21=text('includes/class-snfla-file21-adapter.php')
css=text('assets/css/admin.css')
workflow=text('.github/workflows/file04-legacy-adapter-ci.yml')
build=text('tools/build-release.py')
trace=text('REQUIREMENTS-TRACEABILITY.md')
status=text('STATUS.md')
all_php='\n'.join(p.read_text(encoding='utf-8') for p in ROOT.rglob('*.php') if '.git' not in p.parts and 'tests' not in p.parts)

round1=report('Central-plan Review/Fix Round 1 — requirements, ownership and traceability',[
    ('Version: 1.3.0' in main, 'Runtime must be promoted to 1.3.0.'),
    ('class-snfla-central-plan.php' in main and 'SNFLA_Central_Plan::boot' in main, 'Central-plan contract layer must be loaded.'),
    ('array( 37, 49 )' in central and 'array( 74, 84 )' in central and 'array( 239, 285 )' in central, 'All 71 File-04-applicable CV requirements must be registered.'),
    ('F04-CEN-01' in central and 'F04-CEN-02' in central, 'Both File-specific central requirements must be registered.'),
    ('File 21' in central and 'File 26' in central and 'File 20' in central and 'File 25' in central and 'File 24' in central, 'Canonical owner boundaries must be explicit.'),
    ('integration_regression_only' in central and 'duplicate truth store' in central, 'Modern features owned elsewhere must not become File 04 backends.'),
    ('sabri_file26_legacy_resolution_v1' in central, 'File 26 compatibility contract must exist.'),
    ('Central CV/CEN/AJ ID' in trace, 'Human traceability document must describe the complete modern trace chain.'),
])

round2=report('Central-plan Review/Fix Round 2 — fresh adversarial/source regression',[
    (re.search(r'\bOFFSET\b|\bTRUNCATE\b|maybe_unserialize|(?<![A-Za-z_])unserialize\s*\(|wp_cache_flush\s*\(', all_php, re.I) is None, 'Forbidden unsafe patterns must remain absent.'),
    ('wp_insert_post(' not in central and '$wpdb->posts' not in central, 'Central-plan layer must not write canonical publication storage.'),
    ('LegacyPublicationMigration::migrate_selected' in file21 and 'LegacyPublicationRollback::rollback_selected' in file21, 'Canonical migration/rollback must stay behind File 21 commands.'),
    ('do_not_allow' in plugin and 'block_legacy_comment_write' in plugin and 'block_legacy_meta_write' in plugin, 'Legacy truth writes must remain fail-closed.'),
    ('focus-visible' in css and 'prefers-reduced-motion' in css and 'direction:rtl' in css and 'unicode-bidi' in css, 'RTL/accessibility/reduced-motion source safeguards must exist.'),
    ('codex/file-04-*' in workflow and 'run-central-plan.php' in workflow and 'run-central-reviews.py' not in workflow, 'Workflow must target modern File 04 branches and run central-plan tests without recursion.'),
    ("VERSION='1.3.0'" in build and 'central_plan_review_rounds' in build, 'Deterministic package generator must identify v1.3.0 and the two new review rounds.'),
    ('production_ready' in central and 'staging_accepted_pending' in status, 'Source completion must not fabricate staging/production acceptance.'),
])

php = subprocess.run(['php', str(ROOT/'tests/run-central-plan.php')], cwd=ROOT, text=True, capture_output=True)
if php.returncode != 0:
    print(php.stdout, end='')
    print(php.stderr, end='', file=sys.stderr)
    round1=False
else:
    print(php.stdout, end='')

if not (round1 and round2):
    sys.exit(1)
print('Two consecutive post-central-plan source reviews passed with zero known source-review blockers.')
