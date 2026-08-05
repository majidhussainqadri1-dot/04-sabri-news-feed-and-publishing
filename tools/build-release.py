#!/usr/bin/env python3
from __future__ import annotations
import argparse, hashlib, json, pathlib, zipfile

ROOT = pathlib.Path(__file__).resolve().parents[1]
GENERATED = {"SOURCE-INVENTORY.tsv", "CHECKSUMS.sha256", "RELEASE-LOCK.json", "MANIFEST.md"}
EXCLUDE_PARTS = {".git", "__pycache__"}
PACKAGE_ROOT_FILES = {
    "sabri-news-feed-legacy-adapter.php",
    "uninstall.php",
    "readme.txt",
    "README.md",
    "ARCHITECTURE.md",
    "MIGRATION-RUNBOOK.md",
    "ROLLBACK-RUNBOOK.md",
    "SECURITY-PRIVACY.md",
    "STATUS.md",
    "REQUIREMENTS-TRACEABILITY.md",
    "SOURCE-INVENTORY.tsv",
    "CHECKSUMS.sha256",
    "RELEASE-LOCK.json",
    "MANIFEST.md",
}
PACKAGE_DIRS = {"includes", "assets"}
FORBIDDEN_PACKAGE_DIRS = {".github", "tests", "tools", ".git", "__pycache__"}


def included_files(include_generated: bool = True):
    out=[]
    for path in ROOT.rglob('*'):
        if not path.is_file():
            continue
        rel=path.relative_to(ROOT).as_posix()
        if any(part in EXCLUDE_PARTS for part in path.parts):
            continue
        if path.suffix in {'.zip','.pyc'}:
            continue
        if not include_generated and rel in GENERATED:
            continue
        out.append((rel,path))
    return sorted(out)


def package_files():
    out=[]
    for rel,path in included_files(True):
        first=rel.split('/',1)[0]
        if rel in PACKAGE_ROOT_FILES or first in PACKAGE_DIRS:
            out.append((rel,path))
    for rel,_ in out:
        first=rel.split('/',1)[0]
        if first in FORBIDDEN_PACKAGE_DIRS:
            raise SystemExit(f'Forbidden development path in package: {rel}')
    required={
        'sabri-news-feed-legacy-adapter.php',
        'uninstall.php',
        'readme.txt',
        'RELEASE-LOCK.json',
        'SOURCE-INVENTORY.tsv',
        'CHECKSUMS.sha256',
    }
    present={rel for rel,_ in out}
    missing=sorted(required-present)
    if missing:
        raise SystemExit('Missing required package files: '+', '.join(missing))
    if not any(rel.startswith('includes/') for rel,_ in out):
        raise SystemExit('Package contains no runtime includes')
    return sorted(out)


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def source_rows():
    return [(sha(path), path.stat().st_size, rel) for rel,path in included_files(False)]


def source_tree(rows):
    canonical=''.join(f'{h}\t{s}\t{p}\n' for h,s,p in rows).encode()
    return hashlib.sha256(canonical).hexdigest()


def generate():
    rows=source_rows()
    (ROOT/'SOURCE-INVENTORY.tsv').write_text('sha256\tbytes\tpath\n'+''.join(f'{h}\t{s}\t{p}\n' for h,s,p in rows),encoding='utf-8')
    (ROOT/'CHECKSUMS.sha256').write_text(''.join(f'{h}  {p}\n' for h,_,p in rows),encoding='utf-8')
    lock={
        'schema':1,'file_number':'04','module':'Sabri News Feed Legacy Foundation Adapter','version':'1.0.1',
        'source_file_count':len(rows),'php_file_count':sum(p.endswith('.php') for _,_,p in rows),
        'source_size':sum(s for _,s,_ in rows),'source_tree_sha256':source_tree(rows),
        'inventory_file':'SOURCE-INVENTORY.tsv','checksums_file':'CHECKSUMS.sha256',
        'canonical_owner':'File 21','legacy_source_retained':True,'destructive':False,
        'production_package_excludes':['.github','tests','tools'],
    }
    lock_bytes=(json.dumps(lock,ensure_ascii=False,indent=2,sort_keys=True)+'\n').encode()
    (ROOT/'RELEASE-LOCK.json').write_bytes(lock_bytes)
    manifest=f"""# Release Manifest — File 04 v1.0.1

- Module: Sabri News Feed Legacy Foundation Adapter
- Canonical owner: File 21
- Source files: `{lock['source_file_count']}`
- PHP files: `{lock['php_file_count']}`
- Source bytes: `{lock['source_size']}`
- Source-tree SHA-256: `{lock['source_tree_sha256']}`
- Release-lock SHA-256: `{hashlib.sha256(lock_bytes).hexdigest()}`
- Source retained: `true`
- Destructive migration/uninstall: `false`
- Production ZIP excludes development-only `.github`, `tests`, and `tools` paths.

The manifest proves exact source identity only. It does not authorize staging, merge, production or retirement.
"""
    (ROOT/'MANIFEST.md').write_text(manifest,encoding='utf-8')
    return lock


def verify():
    missing=[f for f in GENERATED if not (ROOT/f).is_file()]
    if missing:
        raise SystemExit('Missing generated evidence: '+', '.join(missing))
    rows=source_rows()
    inventory=(ROOT/'SOURCE-INVENTORY.tsv').read_text(encoding='utf-8').splitlines()
    expected=['sha256\tbytes\tpath']+[f'{h}\t{s}\t{p}' for h,s,p in rows]
    if inventory!=expected:
        raise SystemExit('SOURCE-INVENTORY.tsv mismatch')
    checks=(ROOT/'CHECKSUMS.sha256').read_text(encoding='utf-8')
    if checks!=''.join(f'{h}  {p}\n' for h,_,p in rows):
        raise SystemExit('CHECKSUMS.sha256 mismatch')
    lock=json.loads((ROOT/'RELEASE-LOCK.json').read_text(encoding='utf-8'))
    if lock['source_file_count']!=len(rows) or lock['source_size']!=sum(s for _,s,_ in rows) or lock['source_tree_sha256']!=source_tree(rows):
        raise SystemExit('RELEASE-LOCK.json mismatch')
    package_files()
    return lock


def package(output: pathlib.Path):
    lock=verify()
    output.parent.mkdir(parents=True,exist_ok=True)
    packaged=package_files()
    with zipfile.ZipFile(output,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=9) as zf:
        for rel,path in packaged:
            info=zipfile.ZipInfo('sabri-news-feed-legacy-adapter/'+rel,date_time=(2026,1,1,0,0,0))
            info.compress_type=zipfile.ZIP_DEFLATED
            info.external_attr=(0o100644 & 0xFFFF)<<16
            zf.writestr(info,path.read_bytes())
    with zipfile.ZipFile(output) as zf:
        names=zf.namelist()
        forbidden=[n for n in names if len(n.split('/'))>1 and n.split('/')[1] in FORBIDDEN_PACKAGE_DIRS]
        if forbidden:
            raise SystemExit('Development-only files leaked into package: '+', '.join(forbidden))
        if any(n.endswith(('/tests/run-unit.php','/tests/run-architecture.php','/tools/build-release.py')) for n in names):
            raise SystemExit('Development executable leaked into package')
    digest=hashlib.sha256(output.read_bytes()).hexdigest()
    print(json.dumps({
        'package':str(output),
        'sha256':digest,
        'bytes':output.stat().st_size,
        'package_file_count':len(packaged),
        'source_tree_sha256':lock['source_tree_sha256'],
    },indent=2))


def main():
    parser=argparse.ArgumentParser()
    parser.add_argument('--generate',action='store_true')
    parser.add_argument('--verify-only',action='store_true')
    parser.add_argument('--output',type=pathlib.Path,default=ROOT.parent/'sabri-news-feed-legacy-adapter-1.0.1.zip')
    args=parser.parse_args()
    if args.generate:
        generate()
    if args.verify_only:
        print(json.dumps(verify(),indent=2))
        return
    if not args.generate:
        verify()
    package(args.output)


if __name__=='__main__':
    main()
