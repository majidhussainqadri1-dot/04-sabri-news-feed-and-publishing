#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
def text(path): return (ROOT / path).read_text(encoding='utf-8')
def need(cond, message, failures):
    if not cond: failures.append(message)

main = text('sabri-news-feed-legacy-adapter.php')
hard = text('includes/class-snfla-post-audit-hardening.php')
future = text('includes/class-snfla-future18.php')
workflow = text('.github/workflows/file04-legacy-adapter-ci.yml')
build = text('tools/build-release.py')
readme = text('README.md')
wpreadme = text('readme.txt')
status = text('STATUS.md')
fail = []

# Historical Review 1 — governing architecture remains adapter-only.
need('File 21' in future and 'File 26' in future and 'File 20' in future and 'File 25' in future and 'File 24' in future, 'R1 canonical owners must remain explicit.', fail)
need('wp_insert_post(' not in hard and '$wpdb->posts' not in hard and 'SNFLA_Migration::migrate(' not in hard, 'R1 hardening must not create a duplicate backend or invoke migration.', fail)

# Historical Review 2 — Future18 actions require current-action / step-up authority.
need('rest_request_before_callbacks' in hard and 'SNFLA_Capabilities::current_actor( SNFLA_Capabilities::CAP_REVIEW )' in hard, 'R2 Future18 mutating/evidence actions must require fresh current_actor authority.', fail)
need("'GET', 'HEAD', 'OPTIONS'" in hard, 'R2 read-only methods must remain distinct from action methods.', fail)

# Historical Review 3 — internal callback failures must not travel as HTTP 200.
need('rest_post_dispatch' in hard and 'set_status' in hard and 'status_for_error_code' in hard, 'R3 Future18 error envelopes must restore non-2xx HTTP semantics.', fail)
need('X-SNFLA-Error-Code' in hard, 'R3 hardened error response must expose a stable non-sensitive machine code.', fail)

# Historical Review 4 — visual evidence requires the complete requested matrix.
for token in ['validate_visual_diff_evidence', 'full_requested_matrix_passed', 'critical_diff_count_zero', 'missing_or_failed_checks']:
    need(token in hard, f'R4 missing visual-diff hardening token: {token}', fail)

# Historical Review 5 — redirect/citation continuity is a real release gate.
for token in ['validate_redirect_observatory_evidence', 'redirect_citation_observatory_evidence_pending', 'REDIRECT_EVIDENCE_OPTION', 'release_blocking']:
    need(token in hard, f'R5 missing redirect/citation release hardening: {token}', fail)

# Historical Review 6 — DR GameDay cannot self-attest with one boolean.
for token in ['validate_gameday_evidence', 'all_required_exercises_passed', 'production_environment_refused', 'required_exercises']:
    need(token in hard, f'R6 missing DR GameDay evidence validation: {token}', fail)

# Historical Review 7 — receipt creation requires independently verifiable operation evidence.
for token in ['validate_receipt_request', 'snfla_receipt_evidence_unverified', 'migration_equivalent', 'validate_current_report', 'SNFLA_Rollback::proof', 'redirect_cutover_side_effects_completed']:
    need(token in hard, f'R7 missing cryptographic receipt authenticity gate: {token}', fail)

# Historical Review 8 — privacy/accessibility/localization guardrails remain present.
css = text('assets/css/admin.css')
audit = text('includes/class-snfla-audit.php')
for token in ['#087a4e', 'focus-visible', 'min-height:44px', 'prefers-reduced-motion', 'forced-colors']:
    need(token in css, f'R8 missing admin accessibility/brand guardrail: {token}', fail)
need('redact' in audit and 'patient' in audit and 'authorization' in audit and 'credential' in audit, 'R8 audit redaction must remain privacy-safe.', fail)
need('silent_normalization_performed' in future and "preg_match( '//u'" in future, 'R8 Unicode/RTL fidelity guard must remain non-normalizing.', fail)

# Historical Review 9 — current release metadata/CI/package must retain these controls after later patches.
need('Version: 2.0.4' in main and "SNFLA_VERSION', '2.0.4'" in main, 'Current runtime version must be 2.0.4 after the second audit.', fail)
need('Stable tag: 2.0.4' in wpreadme, 'Current WordPress stable tag must match runtime.', fail)
need('2.0.4' in readme and '2.0.4' in status, 'Current human release/status docs must identify 2.0.4.', fail)
need("VERSION='2.0.4'" in build and 'TEN_ROUND_REVIEW_ROUNDS=10' in build, 'Deterministic builder must retain first-ten-round evidence under v2.0.4.', fail)
need('run-ten-round-post-future18.py' in build and 'run-ten-round-post-future18.py' in workflow, 'Builder and CI must execute the historical ten-round hardening gate.', fail)
need("- main" in workflow and "audit/file-04-*" in workflow, 'Push CI must cover main and audit branches.', fail)

# Historical Review 10 — final source shape must preserve Future18 count and schema boundary.
need("SNFLA_SCHEMA_VERSION', '1.3.0'" in main, 'No unnecessary DB schema bump is permitted.', fail)
need(sum(1 for i in range(1, 19) if f'F04-FUT-{i:03d}' in future) == 18, 'All 18 Future18 IDs must still exist.', fail)
need('class-snfla-post-audit-hardening.php' in main and 'SNFLA_Post_Audit_Hardening::boot' in main, 'Hardening runtime must be loaded and booted.', fail)

if fail:
    print('Historical ten-round post-Future18 audit gate failed:', file=sys.stderr)
    for item in fail: print('-', item, file=sys.stderr)
    sys.exit(1)
print('Historical first ten-round post-Future18 guardrails remain present under v2.0.4.')
