#!/usr/bin/env python3
from pathlib import Path

def rd(p): return Path(p).read_text(encoding='utf-8')
def wr(p,s): Path(p).write_text(s,encoding='utf-8')

# R26 only. Do not modify .github from GitHub Actions because its token has no workflows permission.
files=['sabri-news-feed-legacy-adapter.php','README.md','STATUS.md','tools/build-release.py']
files += [str(p) for p in Path('tests').glob('run-*') if p.suffix in {'.py','.php'}]
for f in files:
    wr(f,rd(f).replace('2.0.4','2.0.5'))

s=rd('readme.txt')
s=s.replace('Stable tag: 2.0.4','Stable tag: 2.0.5',1)
s=s.replace('Version 2.0.4','Version 2.0.5',1)
if '= 2.0.5 =' not in s:
    entry="""= 2.0.5 =
* Fresh second 80-round adversarial source audit after v2.0.4.
* Hardened checkpoint IDs, taxonomy-read checksums, authenticated audit/status evidence, mapping identities/states/checksums, dry-run identity, interaction progress, File21 boundary IDs, run-ledger state, lifecycle version input, physical schema health, activation evidence/cron scheduling, and signed page-quarantine status.
* A distinct second-eighty audit record/gate is being added while the historical first 80-round audit remains unchanged.

"""
    s=s.replace('== Changelog ==\n\n','== Changelog ==\n\n'+entry,1)
wr('readme.txt',s)
wr('STATUS.md',"""# File 04 v2.0.5 — Second Fresh Eighty-Round Hardening Status Register

`source_status=v2.0.5-second-eighty-round-review-in-progress`
`known_unresolved_source_scope_blockers=0`
`staging_accepted_pending=true`
`live_deployed=false`
`operational=false`

The second fresh 80-round audit is independent from the historical first 80-round audit. Defects have been found and corrected through Round 26; later rounds remain unclaimed until freshly reviewed on the corrected source.

File 04 remains temporary migration/compatibility only. File21 retains publication/feed truth; File26 retains search/discovery; source code cannot self-promote Staging-Accepted, Live-Deployed or Operational. Storage schema remains 1.3.0.
""")
Path('tools/r26-r27-release.py').unlink()
