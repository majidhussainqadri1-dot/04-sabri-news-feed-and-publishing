#!/usr/bin/env python3
from pathlib import Path
import re, subprocess, sys

ROOT = Path(__file__).resolve().parents[1]
def text(path): return (ROOT / path).read_text(encoding='utf-8')
def report(name, checks):
    failed = [message for ok, message in checks if not ok]
    if failed:
        print(name + ': FAIL', file=sys.stderr)
        for message in failed: print('-', message, file=sys.stderr)
        return False
    print(f'{name}: PASS ({len(checks)} checks)')
    return True

future = text('includes/class-snfla-future18.php')
main = text('sabri-news-feed-legacy-adapter.php')
trace = text('FUTURE18-TRACEABILITY.md')
workflow = text('.github/workflows/file04-legacy-adapter-ci.yml')
build = text('tools/build-release.py')
hard = text('includes/class-snfla-post-audit-hardening.php')

round1 = report('Future18 Review/Fix Round 1 — completeness, ownership, safety', [
    (len(re.findall(r"'F04-FUT-\d{3}'\s*=>", future)) == 18, 'Exactly 18 stable Future18 requirements must exist.'),
    ('Version: 2.0.4' in main and 'SNFLA_Future18::boot' in main and 'SNFLA_Post_Audit_Hardening::boot' in main, 'Future18 plus second-audit v2.0.4 runtime must load and boot.'),
    (all(f'F04-FUT-{i:03d}' in trace for i in range(1, 19)), 'Human traceability must cover F04-FUT-001..018.'),
    ('File 21' in future and 'File 26' in future and 'File 20' in future and 'File 25' in future and 'File 24' in future, 'Canonical ownership boundaries must remain explicit.'),
    ('wp_insert_post(' not in future and '$wpdb->posts' not in future and 'wp_insert_post(' not in hard, 'Future18/hardening must not create a parallel canonical write backend.'),
    ('SNFLA_Migration::migrate(' not in future and 'SNFLA_Migration::migrate(' not in hard, 'Adaptive controls/hardening must not auto-invoke migration.'),
    ('ai_can_approve' in future and 'ai_can_publish' in future and 'human_decision_required' in future, 'AI quarantine advice must remain human-governed.'),
    ('production_chaos_allowed' in future and 'disposable_staging' in future and 'validate_gameday_evidence' in hard, 'GameDay must never target production and evidence must be hardened.'),
])

static = subprocess.run([sys.executable, str(ROOT/'tests/run-future18.py')], cwd=ROOT, text=True, capture_output=True)
if static.returncode != 0:
    print(static.stdout, end='')
    print(static.stderr, end='', file=sys.stderr)
    round1 = False
else:
    print(static.stdout, end='')

round2 = report('Future18 Review/Fix Round 2 — fresh adversarial release regression', [
    ('no-store, no-cache, must-revalidate, private' in future and 'X-Robots-Tag' in future, 'Future18 evidence endpoints must be private and noindex.'),
    ('MAX_RECEIPTS' in future and 'MAX_CHECKPOINTS' in future and 'MAX_IDS' in future, 'All new evidence/sampling surfaces must be bounded.'),
    ('contract_drift' in future and 'block_mutation' in future, 'Contract drift must fail closed.'),
    ('mutations_performed' in future and 'non_mutating' in future, 'Digital Twin/replay must remain non-mutating.'),
    ('silent_normalization_performed' in future and "preg_match( '//u'" in future, 'Unicode/RTL fidelity guard must verify without silent normalization.'),
    ('automatically_authorizes_retirement' in future and 'founder_approval_required' in future, 'Retirement score must not replace Founder authority.'),
    ('run-future18.py' in workflow and 'run-future18-reviews.py' in workflow and 'run-ten-round-post-future18.py' in workflow, 'Exact-head workflow must execute Future18 and post-audit gates.'),
    ("VERSION='2.0.4'" in build and 'FUTURE18_COUNT=18' in build and 'TEN_ROUND_REVIEW_ROUNDS=10' in build, 'Deterministic release builder must bind v2.0.4 to all 18 enhancements and ten-round evidence.'),
])

if not (round1 and round2): sys.exit(1)
print('Two consecutive fresh Future18 review/fix/retest rounds passed with the v2.0.4 second-audit hardening present.')
