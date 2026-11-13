#!/usr/bin/env python3
"""Black-box File04->File26 canonical resolution contract; no DB or site."""
import json
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CASES = {
    'healthy': 'canonical',
    'existing_projection': 'existing',
    'pre_cutover': 'unmapped',
    'file21_unavailable': 'unavailable',
    'target_missing': 'unmapped',
    'conflict_status': 'unmapped',
    'interaction_pending': 'unmapped',
    'target_mismatch': 'unmapped',
    'mapping_error': 'unmapped',
    'missing_mapping': 'unmapped',
    'source_digest_missing': 'unmapped',
    'target_digest_missing': 'unmapped',
    'invalid_provenance': 'unmapped',
    'open_conflict': 'unmapped',
    'conflict_ledger_failed': 'unmapped',
    'private_target': 'canonical_nonpublic',
}
errors = []
for scenario, expected in CASES.items():
    proc = subprocess.run(
        ['php', '-n', 'tests/file26-resolution-scenario.php', scenario],
        cwd=ROOT, capture_output=True, text=True, timeout=15,
    )
    if proc.returncode or proc.stderr:
        errors.append(f'{scenario}: PHP error {proc.stderr.strip()} exit={proc.returncode}')
        continue
    try:
        payload = json.loads(proc.stdout)
    except (ValueError, TypeError) as exc:
        errors.append(f'{scenario}: invalid response {exc}; {proc.stdout[:300]!r}')
        continue
    result = payload['result']
    actual = 'existing' if scenario == 'existing_projection' and result == {'trusted': 'upstream'} else result.get('status')
    if actual != expected:
        errors.append(f'{scenario}: expected {expected}, got {actual}')
    if actual not in ('canonical', 'existing') and isinstance(result, dict) and (
        'canonical_url' in result or 'canonical_id' in result
    ):
        errors.append(f'{scenario}: unverified canonical metadata exposed')
    if scenario == 'healthy' and (
        result.get('canonical_id') != 999 or result.get('indexable') is not False
    ):
        errors.append('healthy: wrong canonical id or legacy indexability')
if errors:
    print('File26 legacy resolution contract FAIL:', file=sys.stderr)
    for error in errors:
        print('- ' + error, file=sys.stderr)
    sys.exit(1)
print(f'File26 legacy resolution contract PASS: {len(CASES)} scenarios, no unverified public canonical mapping.')
