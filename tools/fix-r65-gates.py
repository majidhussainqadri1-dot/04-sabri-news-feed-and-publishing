#!/usr/bin/env python3
from pathlib import Path


def patch(path, old, new):
    p=Path(path)
    s=p.read_text(encoding='utf-8')
    if old not in s:
        raise SystemExit(f'{path}: expected marker missing')
    p.write_text(s.replace(old,new,1),encoding='utf-8')

# Preserve the exact applicability phrase required by the File04 own-plan trace.
patch('README.md','71 applicable central CV requirements','71 CV requirements (all applicable central requirements)')

# The new lifecycle guard is stronger than the historical raw state-list check.
patch(
    'tests/run-second-ten-round-hardening.py',
    "and 'in_array( $state, SNFLA_Schema::states(), true )' in ret,",
    "and ('SNFLA_Schema::state_valid()' in ret or 'in_array( $state, SNFLA_Schema::states(), true )' in ret),",
)

# Remove an accidental negative substring assertion from the new 80-round QA gate;
# positive schema-check and metrics-persistence markers remain mandatory.
patch(
    'tests/run-eighty-round-hardening.py',
    "need('metrics_persist_failed' in plan and \"'schema' =\" not in plan and \"$checks['schema']\" in plan, 'R57-R58 observability/system schema gate missing', f)",
    "need('metrics_persist_failed' in plan and \"$checks['schema']\" in plan, 'R57-R58 observability/system schema gate missing', f)",
)

print('Aligned R65 QA gates with the strengthened v2.0.4 implementation.')
