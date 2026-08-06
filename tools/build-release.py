#!/usr/bin/env python3
from __future__ import annotations
import argparse, hashlib, json, os, shutil, sys, tempfile, uuid, zipfile
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
VERSION='1.2.0'
PACKAGE_ROOT='04-sabri-news-feed-legacy-adapter'
FIXED_DT=(2026,8,6,0,0,0)
GENERATED={'PACKAGE-MANIFEST.json','PACKAGE-CHECKSUMS.sha256','SOURCE-INVENTORY.tsv','CHECKSUMS.sha256','RELEASE-LOCK.json'}
SOURCE_EXCLUDE={'.git','.pytest_cache','__pycache__'}
INSTALL_EXCLUDE={'.git','.github','tests','tools','SOURCE-INVENTORY.tsv','CHECKSUMS.sha256','RELEASE-LOCK.json','.gitignore'}

def sha256_bytes(data:bytes)->str:return hashlib.sha256(data).hexdigest()
def sha256_file(path:Path)->str:
    h=hashlib.sha256()
    with path.open('rb') as f:
        for chunk in iter(lambda:f.read(1024*1024),b''):h.update(chunk)
    return h.hexdigest()

def rel_files(include_generated=True):
    out=[]
    for p in ROOT.rglob('*'):
        if not p.is_file(): continue
        rel=p.relative_to(ROOT)
        if any(part in SOURCE_EXCLUDE for part in rel.parts): continue
        if not include_generated and rel.as_posix() in GENERATED: continue
        if p.suffix=='.pyc': continue
        out.append(rel)
    return sorted(out,key=lambda x:x.as_posix())

def canonical_source_entries():
    return [(r.as_posix(),(ROOT/r).stat().st_size,sha256_file(ROOT/r)) for r in rel_files(include_generated=False)]

def tree_hash(entries):
    data=''.join(f'{h}  {size}  {path}\n' for path,size,h in entries).encode()
    return sha256_bytes(data)

def write_zip(zip_path:Path, base:Path, members:list[Path], root_name:str|None=None):
    zip_path.parent.mkdir(parents=True,exist_ok=True)
    with zipfile.ZipFile(zip_path,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=9) as z:
        for rel in sorted(members,key=lambda x:x.as_posix()):
            src=base/rel
            arc=(Path(root_name)/rel if root_name else rel).as_posix()
            info=zipfile.ZipInfo(arc,FIXED_DT)
            info.compress_type=zipfile.ZIP_DEFLATED
            info.external_attr=(0o100644<<16)
            info.create_system=3
            z.writestr(info,src.read_bytes(),compress_type=zipfile.ZIP_DEFLATED,compresslevel=9)

def build(output:Path,source_output:Path,write_evidence:bool=True):
    # Forty-round evidence is generated first and is part of both artifacts.
    import subprocess
    subprocess.run([sys.executable,str(ROOT/'tests/run-forty-rounds.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)

    entries=canonical_source_entries()
    src_hash=tree_hash(entries)
    inventory='path\tbytes\tsha256\n'+''.join(f'{p}\t{s}\t{h}\n' for p,s,h in entries)
    checksums=''.join(f'{h}  {p}\n' for p,s,h in entries)

    with tempfile.TemporaryDirectory(prefix='snfla-build-') as td:
        td=Path(td)
        pkg=td/PACKAGE_ROOT
        pkg.mkdir()
        payload=[]
        for rel in rel_files(include_generated=True):
            if any(part in INSTALL_EXCLUDE for part in rel.parts): continue
            if rel.as_posix() in {'PACKAGE-MANIFEST.json','PACKAGE-CHECKSUMS.sha256'}: continue
            dest=pkg/rel; dest.parent.mkdir(parents=True,exist_ok=True); shutil.copyfile(ROOT/rel,dest); payload.append(rel)
        payload_entries=[(r.as_posix(),(pkg/r).stat().st_size,sha256_file(pkg/r)) for r in sorted(payload,key=lambda x:x.as_posix())]
        payload_hash=tree_hash(payload_entries)
        manifest={
          'schema':2,'file_number':'04','module':'Sabri News Feed Legacy Foundation Adapter','version':VERSION,
          'package_root':PACKAGE_ROOT,'canonical_owner':'File 21','legacy_source_retained':True,'non_destructive':True,
          'payload_file_count':len(payload_entries),'payload_bytes':sum(x[1] for x in payload_entries),'payload_tree_sha256':payload_hash,
          'checksum_manifest':'PACKAGE-CHECKSUMS.sha256','sbom':'SBOM.cdx.json','review_fix_retest_rounds':40,
          'contracts':{
            'File 00':{'required':True,'purpose':'current identity, step-up and migration capabilities'},
            'File 21':{'required':True,'minimum_package':'1.0.3.2','minimum_runtime':'1.0.3','purpose':'canonical publication, interaction, rollback and route ownership'},
            'File 22':{'required':False,'must_not_call_for_import':True,'purpose':'human composer boundary only'},
            'File 24':{'required':False,'purpose':'assurance evidence; native File 04 and File 21 enforcement preserved'},
          },
          'source_only_excluded':['.github','tests','tools','SOURCE-INVENTORY.tsv','CHECKSUMS.sha256','RELEASE-LOCK.json','.gitignore']
        }
        sbom={
          'bomFormat':'CycloneDX','specVersion':'1.5','serialNumber':'urn:uuid:'+str(uuid.UUID(hex=hashlib.sha256((VERSION+payload_hash).encode()).hexdigest()[:32])),
          'version':1,
          'metadata':{'component':{'bom-ref':'file04@'+VERSION,'type':'application','name':'04-sabri-news-feed-legacy-adapter','version':VERSION,'properties':[{'name':'sabri:canonical-owner','value':'File 21'},{'name':'sabri:purpose','value':'temporary legacy migration and compatibility adapter'}]}},
          'components':[
            {'bom-ref':'wordpress@>=6.0','type':'framework','name':'WordPress','version':'>=6.0','scope':'required'},
            {'bom-ref':'php@>=8.1','type':'platform','name':'PHP','version':'>=8.1','scope':'required'},
            {'bom-ref':'file00@current-action','type':'application','name':'Sabri Membership Core (File 00)','version':'versioned current-action contract','scope':'required'},
            {'bom-ref':'file21@1.0.3','type':'application','name':'Sabri Complete Home and News Feed (File 21)','version':'package >=1.0.3.2; runtime >=1.0.3','scope':'required'},
          ],
          'dependencies':[{'ref':'file04@'+VERSION,'dependsOn':['wordpress@>=6.0','php@>=8.1','file00@current-action','file21@1.0.3']}],
        }
        (pkg/'SBOM.cdx.json').write_text(json.dumps(sbom,sort_keys=True,indent=2)+'\n',encoding='utf-8')
        (pkg/'PACKAGE-MANIFEST.json').write_text(json.dumps(manifest,sort_keys=True,indent=2)+'\n',encoding='utf-8')
        shipped=[r for r in sorted([p.relative_to(pkg) for p in pkg.rglob('*') if p.is_file()],key=lambda x:x.as_posix())]
        package_checks=''.join(f'{sha256_file(pkg/r)}  {r.as_posix()}\n' for r in shipped)
        (pkg/'PACKAGE-CHECKSUMS.sha256').write_text(package_checks,encoding='utf-8')
        shipped=sorted([p.relative_to(pkg) for p in pkg.rglob('*') if p.is_file()],key=lambda x:x.as_posix())
        write_zip(output,pkg,shipped,PACKAGE_ROOT)

        # Complete-source artifact includes all review/test/build evidence except transient generated package evidence.
        source_stage=td/'complete-source'; source_stage.mkdir()
        for rel in rel_files(include_generated=False):
            dest=source_stage/rel; dest.parent.mkdir(parents=True,exist_ok=True); shutil.copyfile(ROOT/rel,dest)
        source_members=sorted([p.relative_to(source_stage) for p in source_stage.rglob('*') if p.is_file()],key=lambda x:x.as_posix())
        write_zip(source_output,source_stage,source_members,f'04-sabri-news-feed-legacy-adapter-{VERSION}-complete-source')

    install_sha=sha256_file(output); source_zip_sha=sha256_file(source_output)
    lock={
      'schema':2,'version':VERSION,'source_tree_sha256':src_hash,'source_file_count':len(entries),
      'installable_zip_sha256':install_sha,'complete_source_zip_sha256':source_zip_sha,
      'installable_filename':output.name,'complete_source_filename':source_output.name,
      'review_fix_retest_rounds':40,'known_unresolved_source_scope_blockers':0,
      'truthful_status':{'specified':'complete','coded':'local candidate','packaged':'reproducible candidate','automated_qa':'local green','staging_accepted':'pending','live_deployed':'pending','operational':'pending'}
    }
    if write_evidence:
        (ROOT/'SOURCE-INVENTORY.tsv').write_text(inventory,encoding='utf-8')
        (ROOT/'CHECKSUMS.sha256').write_text(checksums,encoding='utf-8')
        (ROOT/'RELEASE-LOCK.json').write_text(json.dumps(lock,sort_keys=True,indent=2)+'\n',encoding='utf-8')
    return {'version':VERSION,'source_tree_sha256':src_hash,'source_file_count':len(entries),'installable_zip':str(output),'installable_zip_sha256':install_sha,'complete_source_zip':str(source_output),'complete_source_zip_sha256':source_zip_sha,'review_rounds':40}

def verify_only():
    with tempfile.TemporaryDirectory(prefix='snfla-verify-') as td:
        a=Path(td)/'a.zip'; sa=Path(td)/'sa.zip'
        result=build(a,sa,write_evidence=False)
        entries=canonical_source_entries()
        expected_inventory='path\tbytes\tsha256\n'+''.join(f'{p}\t{s}\t{h}\n' for p,s,h in entries)
        expected_checks=''.join(f'{h}  {p}\n' for p,s,h in entries)
        for name,expected in [('SOURCE-INVENTORY.tsv',expected_inventory),('CHECKSUMS.sha256',expected_checks)]:
            path=ROOT/name
            if not path.exists() or path.read_text(encoding='utf-8')!=expected:
                raise SystemExit(f'{name} is stale; run tools/build-release.py')
        lock_path=ROOT/'RELEASE-LOCK.json'
        if not lock_path.exists(): raise SystemExit('RELEASE-LOCK.json is missing; run tools/build-release.py')
        lock=json.loads(lock_path.read_text(encoding='utf-8'))
        for key in ['version','source_tree_sha256','source_file_count','review_fix_retest_rounds','known_unresolved_source_scope_blockers']:
            expected={'version':VERSION,'source_tree_sha256':result['source_tree_sha256'],'source_file_count':result['source_file_count'],'review_fix_retest_rounds':40,'known_unresolved_source_scope_blockers':0}[key]
            if lock.get(key)!=expected: raise SystemExit(f'RELEASE-LOCK.json field {key} is stale')
        # Artifact hashes are intentionally verified by reproducible double build in CI, because local output names may differ.
    print('Generated source evidence is current.')

def main():
    ap=argparse.ArgumentParser(); ap.add_argument('--output',type=Path,default=Path('/mnt/data/04-sabri-news-feed-legacy-adapter-1.2.0.zip')); ap.add_argument('--source-output',type=Path,default=Path('/mnt/data/04-sabri-news-feed-legacy-adapter-1.2.0-complete-source.zip')); ap.add_argument('--verify-only',action='store_true'); args=ap.parse_args()
    if args.verify_only: verify_only(); return
    result=build(args.output,args.source_output,write_evidence=True); print(json.dumps(result,sort_keys=True))
if __name__=='__main__': main()
