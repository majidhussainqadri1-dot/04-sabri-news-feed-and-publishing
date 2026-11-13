#!/usr/bin/env python3
"""Black-box simulated legacy redirect guards; no WP database or live writes."""
import json
import subprocess
import sys
from pathlib import Path

root = Path(__file__).resolve().parents[1]
scenarios = {
    'healthy': ('redirect', 301),
    'conflict_status': ('deny', 404),
    'interaction_pending': ('deny', 404),
    'target_mismatch': ('deny', 404),
    'missing_mapping': ('deny', 404),
    'empty_source_digest': ('deny', 404),
    'empty_target_digest': ('deny', 404),
    'invalid_provenance': ('deny', 404),
    'open_conflict': ('deny', 404),
    'conflict_ledger_failed': ('deny', 404),
    'private_target': ('deny', 404),
    'self_loop': ('deny', 404),
    'mapping_error': ('error', 503),
    'redirect_rejected': ('error', 503),
}
errors = []
for name, (outcome, status) in scenarios.items():
    proc = subprocess.run(
        ['php', '-n', 'tests/redirect-scenario.php', name],
        cwd=root, capture_output=True, text=True, timeout=10,
    )
    if proc.returncode or proc.stderr:
        errors.append(f'{name}: runtime error {proc.stderr.strip()} exit={proc.returncode}')
        continue
    try:
        val = json.loads(proc.stdout.strip())
    except (ValueError, TypeError):
        errors.append(f'{name}: invalid response {proc.stdout!r}')
        continue
    actual = ('redirect', val['code']) if val['redirect'] else (
        ('error' if val['status'] >= 500 else 'deny'), val['status']
    )
    if actual != (outcome, status):
        errors.append(f'{name}: expected {(outcome,status)}, got {actual}')
if errors:
    print('Legacy redirect guards FAILED:', file=sys.stderr)
    for e in errors: print(' - ' + e, file=sys.stderr)
    sys.exit(1)
print(f'Legacy redirect guards PASS: {len(scenarios)} independent real handle() cases, including conflicting mapping and provenance.')
