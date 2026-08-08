#!/usr/bin/env python3
from __future__ import annotations
import argparse, hashlib, json, shutil, subprocess, sys, tempfile, uuid, zipfile
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
VERSION='2.0.3'
PACKAGE_ROOT='04-sabri-news-feed-legacy-adapter'
FIXED_DT=(2026,8,8,0,0,0)
GENERATED_NAMES={'SOURCE-INVENTORY.tsv','CHECKSUMS.sha256','RELEASE-LOCK.json','PACKAGE-MANIFEST.json','PACKAGE-CHECKSUMS.sha256'}
SOURCE_EXCLUDE={'.git','.pytest_cache','__pycache__'}
INSTALL_EXCLUDE={'.git','.github','tests','tools','.gitignore','FORTY-ROUND-AUDIT.json','FORTY-ROUND-AUDIT.md'} | GENERATED_NAMES

CENTRAL_PLAN_ID='SSH-CENTRAL-CONSOLIDATED-2026-08-07'
FILE_PLAN_ID='SSH-F04-PLAN-2026-v1.0'
CENTRAL_CV_COUNT=71
FILE_CEN_COUNT=2
AJ_COUNT=15
FUTURE18_COUNT=18
CENTRAL_PLAN_REVIEW_ROUNDS=2
FUTURE18_REVIEW_ROUNDS=2
TEN_ROUND_REVIEW_ROUNDS=10
SECOND_TEN_ROUND_REVIEW_ROUNDS=10
THIRD_TEN_ROUND_REVIEW_ROUNDS=10
HISTORICAL_REVIEW_ROUNDS=40

def sha256_bytes(data:bytes)->str:return hashlib.sha256(data).hexdigest()
def sha256_file(path:Path)->str:
    h=hashlib.sha256()
    with path.open('rb') as f:
        for chunk in iter(lambda:f.read(1024*1024),b''):h.update(chunk)
    return h.hexdigest()

def rel_files():
    out=[]
    for p in ROOT.rglob('*'):
        if not p.is_file(): continue
        rel=p.relative_to(ROOT)
        if any(part in SOURCE_EXCLUDE for part in rel.parts): continue
        if p.suffix=='.pyc' or rel.as_posix() in GENERATED_NAMES: continue
        out.append(rel)
    return sorted(out,key=lambda x:x.as_posix())

def canonical_source_entries():
    return [(r.as_posix(),(ROOT/r).stat().st_size,sha256_file(ROOT/r)) for r in rel_files()]

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

def source_evidence(entries):
    inventory='path\tbytes\tsha256\n'+''.join(f'{p}\t{s}\t{h}\n' for p,s,h in entries)
    checksums=''.join(f'{h}  {p}\n' for p,s,h in entries)
    return inventory,checksums

def run_source_gates():
    subprocess.run(['php',str(ROOT/'tests/run-unit.php')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)
    subprocess.run(['php',str(ROOT/'tests/run-central-plan.php')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)
    subprocess.run([sys.executable,str(ROOT/'tests/run-architecture.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)
    subprocess.run([sys.executable,str(ROOT/'tests/run-file04-own-plan.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)
    subprocess.run([sys.executable,str(ROOT/'tests/run-future18.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)
    subprocess.run([sys.executable,str(ROOT/'tests/run-central-reviews.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)
    subprocess.run([sys.executable,str(ROOT/'tests/run-future18-reviews.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)
    subprocess.run([sys.executable,str(ROOT/'tests/run-ten-round-post-future18.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)
    subprocess.run([sys.executable,str(ROOT/'tests/run-second-ten-round-hardening.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)
    subprocess.run([sys.executable,str(ROOT/'tests/run-third-ten-round-hardening.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)

def build(output:Path,source_output:Path):
    run_source_gates()
    entries=canonical_source_entries()
    src_hash=tree_hash(entries)
    inventory,checksums=source_evidence(entries)

    with tempfile.TemporaryDirectory(prefix='snfla-build-') as td:
        td=Path(td)
        pkg=td/PACKAGE_ROOT
        pkg.mkdir()
        payload=[]
        for rel in rel_files():
            if any(part in INSTALL_EXCLUDE for part in rel.parts) or rel.as_posix() in INSTALL_EXCLUDE: continue
            dest=pkg/rel; dest.parent.mkdir(parents=True,exist_ok=True); shutil.copyfile(ROOT/rel,dest); payload.append(rel)

        payload_entries=[(r.as_posix(),(pkg/r).stat().st_size,sha256_file(pkg/r)) for r in sorted(payload,key=lambda x:x.as_posix())]
        payload_hash=tree_hash(payload_entries)
        manifest={
          'schema':4,
          'file_number':'04',
          'module':'News Feed and Publishing — Legacy Foundation Adapter',
          'version':VERSION,
          'package_root':PACKAGE_ROOT,
          'governing_plans':[CENTRAL_PLAN_ID,FILE_PLAN_ID],
          'central_plan':{'applicable_cv_count':CENTRAL_CV_COUNT,'file_specific_cen_count':FILE_CEN_COUNT,'acceptance_journey_count':AJ_COUNT},
          'future18':{'count':FUTURE18_COUNT,'requirement_range':'F04-FUT-001..018','traceability':'FUTURE18-TRACEABILITY.md','review_rounds':FUTURE18_REVIEW_ROUNDS,'canonical_owner_policy':'adapter-only; no duplicate publication/search/feed truth'},
          'post_future18_hardening':{'version':'1.0.0','review_rounds':TEN_ROUND_REVIEW_ROUNDS,'gate':'tests/run-ten-round-post-future18.py','scope':'authorization, REST failures, visual/redirect/DR evidence, receipt authenticity, release consistency'},
          'second_ten_round_hardening':{'version':'1.0.0','review_rounds':SECOND_TEN_ROUND_REVIEW_ROUNDS,'gate':'tests/run-second-ten-round-hardening.py','audit':'SECOND-TEN-ROUND-HARDENING-AUDIT.md','scope':'second fresh fail-closed/security/reliability review'},
          'third_ten_round_hardening':{'version':'1.0.0','review_rounds':THIRD_TEN_ROUND_REVIEW_ROUNDS,'gate':'tests/run-third-ten-round-hardening.py','audit':'THIRD-TEN-ROUND-HARDENING-AUDIT.md','scope':'third fresh plan/anti-replay/serialization/release review'},
          'canonical_owners':{'publications_home_news_feed':'File 21','search_discovery':'File 26','shell':'File 20','visual_system':'File 25','assurance':'File 24'},
          'legacy_source_retained':True,
          'non_destructive':True,
          'legacy_writes':'forbidden',
          'payload_file_count':len(payload_entries),
          'payload_bytes':sum(x[1] for x in payload_entries),
          'payload_tree_sha256':payload_hash,
          'checksum_manifest':'PACKAGE-CHECKSUMS.sha256',
          'source_inventory':'SOURCE-INVENTORY.tsv',
          'release_lock':'RELEASE-LOCK.json',
          'sbom':'SBOM.cdx.json',
          'central_plan_review_rounds':CENTRAL_PLAN_REVIEW_ROUNDS,
          'future18_review_rounds':FUTURE18_REVIEW_ROUNDS,
          'ten_round_post_future18_review_rounds':TEN_ROUND_REVIEW_ROUNDS,
          'second_ten_round_review_rounds':SECOND_TEN_ROUND_REVIEW_ROUNDS,
          'third_ten_round_review_rounds':THIRD_TEN_ROUND_REVIEW_ROUNDS,
          'historical_v120_review_rounds':HISTORICAL_REVIEW_ROUNDS,
          'contracts':{
            'File 00':{'required_for_mutation':True,'purpose':'current identity, step-up and migration capabilities'},
            'File 01':{'required_for_platform_release':True,'purpose':'module/route/contract registry and release evidence'},
            'File 20':{'required_for_platform_release':True,'purpose':'canonical shell/layout mounting'},
            'File 21':{'required_for_activation':True,'minimum_package':'1.0.3.2','minimum_runtime':'1.0.3','purpose':'canonical publication, interaction, rollback and route ownership'},
            'File 22':{'required':False,'must_not_call_for_import':True,'purpose':'human composer boundary only'},
            'File 24':{'required_for_platform_release':True,'purpose':'assurance evidence; native File 04/File 21 enforcement preserved'},
            'File 25':{'required_for_platform_release':True,'purpose':'visual tokens/components; File 04 does not create a theme'},
            'File 26':{'required_for_platform_release':True,'purpose':'canonical search/discovery; consumes read-only legacy-resolution contract only'},
          },
        }
        sbom={
          'bomFormat':'CycloneDX','specVersion':'1.5',
          'serialNumber':'urn:uuid:'+str(uuid.UUID(hex=hashlib.sha256((VERSION+payload_hash).encode()).hexdigest()[:32])),
          'version':1,
          'metadata':{'component':{'bom-ref':'file04@'+VERSION,'type':'application','name':PACKAGE_ROOT,'version':VERSION,'properties':[
              {'name':'sabri:canonical-publication-owner','value':'File 21'},
              {'name':'sabri:canonical-search-owner','value':'File 26'},
              {'name':'sabri:purpose','value':'temporary legacy migration and compatibility adapter'},
              {'name':'sabri:central-plan','value':CENTRAL_PLAN_ID},
              {'name':'sabri:post-future18-hardening','value':'three-ten-round-audits-v3'},
          ]}},
          'components':[
            {'bom-ref':'wordpress@>=6.0','type':'framework','name':'WordPress','version':'>=6.0','scope':'required'},
            {'bom-ref':'php@>=8.1','type':'platform','name':'PHP','version':'>=8.1','scope':'required'},
            {'bom-ref':'file00@current-action','type':'application','name':'Sabri Membership Core (File 00)','version':'versioned current-action contract','scope':'required'},
            {'bom-ref':'file21@1.0.3','type':'application','name':'Sabri Complete Home and News Feed (File 21)','version':'package >=1.0.3.2; runtime >=1.0.3','scope':'required'},
            {'bom-ref':'file26@versioned','type':'application','name':'Canonical Search and Discovery (File 26)','version':'versioned legacy-resolution consumer contract','scope':'optional'},
          ],
          'dependencies':[{'ref':'file04@'+VERSION,'dependsOn':['wordpress@>=6.0','php@>=8.1','file00@current-action','file21@1.0.3']}],
        }
        release_lock={
          'schema':4,
          'version':VERSION,
          'governing_plans':[CENTRAL_PLAN_ID,FILE_PLAN_ID],
          'source_tree_sha256':src_hash,
          'source_file_count':len(entries),
          'central_plan_cv_count':CENTRAL_CV_COUNT,
          'file_specific_cen_count':FILE_CEN_COUNT,
          'acceptance_journey_count':AJ_COUNT,
          'future18_count':FUTURE18_COUNT,
          'central_plan_review_rounds':CENTRAL_PLAN_REVIEW_ROUNDS,
          'future18_review_rounds':FUTURE18_REVIEW_ROUNDS,
          'ten_round_post_future18_review_rounds':TEN_ROUND_REVIEW_ROUNDS,
          'second_ten_round_review_rounds':SECOND_TEN_ROUND_REVIEW_ROUNDS,
          'third_ten_round_review_rounds':THIRD_TEN_ROUND_REVIEW_ROUNDS,
          'historical_v120_review_rounds':HISTORICAL_REVIEW_ROUNDS,
          'known_unresolved_source_scope_blockers':0,
          'truthful_status':{
            'specified':'complete current source scope',
            'coded':'v2.0.3 third-ten-round hardened candidate',
            'packaged':'reproducible candidate when this build succeeds',
            'automated_qa':'source gates executed by builder/CI',
            'staging_accepted':'pending',
            'live_deployed':'pending',
            'operational':'pending',
          },
        }
        (pkg/'SBOM.cdx.json').write_text(json.dumps(sbom,sort_keys=True,indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
        (pkg/'PACKAGE-MANIFEST.json').write_text(json.dumps(manifest,sort_keys=True,indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
        (pkg/'SOURCE-INVENTORY.tsv').write_text(inventory,encoding='utf-8')
        (pkg/'RELEASE-LOCK.json').write_text(json.dumps(release_lock,sort_keys=True,indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
        shipped_without_checks=sorted([p.relative_to(pkg) for p in pkg.rglob('*') if p.is_file()],key=lambda x:x.as_posix())
        package_checks=''.join(f'{sha256_file(pkg/r)}  {r.as_posix()}\n' for r in shipped_without_checks)
        (pkg/'PACKAGE-CHECKSUMS.sha256').write_text(package_checks,encoding='utf-8')
        shipped=sorted([p.relative_to(pkg) for p in pkg.rglob('*') if p.is_file()],key=lambda x:x.as_posix())
        write_zip(output,pkg,shipped,PACKAGE_ROOT)

        source_stage=td/'complete-source'; source_stage.mkdir()
        for rel in rel_files():
            dest=source_stage/rel; dest.parent.mkdir(parents=True,exist_ok=True); shutil.copyfile(ROOT/rel,dest)
        (source_stage/'SOURCE-INVENTORY.tsv').write_text(inventory,encoding='utf-8')
        (source_stage/'CHECKSUMS.sha256').write_text(checksums,encoding='utf-8')
        (source_stage/'RELEASE-LOCK.json').write_text(json.dumps(release_lock,sort_keys=True,indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
        source_members=sorted([p.relative_to(source_stage) for p in source_stage.rglob('*') if p.is_file()],key=lambda x:x.as_posix())
        write_zip(source_output,source_stage,source_members,f'{PACKAGE_ROOT}-{VERSION}-complete-source')

    install_sha=sha256_file(output); source_zip_sha=sha256_file(source_output)
    return {
      'version':VERSION,
      'source_tree_sha256':src_hash,
      'source_file_count':len(entries),
      'central_plan_cv_count':CENTRAL_CV_COUNT,
      'file_specific_cen_count':FILE_CEN_COUNT,
      'acceptance_journey_count':AJ_COUNT,
      'future18_count':FUTURE18_COUNT,
      'central_plan_review_rounds':CENTRAL_PLAN_REVIEW_ROUNDS,
      'future18_review_rounds':FUTURE18_REVIEW_ROUNDS,
      'ten_round_post_future18_review_rounds':TEN_ROUND_REVIEW_ROUNDS,
          'second_ten_round_review_rounds':SECOND_TEN_ROUND_REVIEW_ROUNDS,
          'third_ten_round_review_rounds':THIRD_TEN_ROUND_REVIEW_ROUNDS,
      'known_unresolved_source_scope_blockers':0,
      'installable_zip':str(output),'installable_zip_sha256':install_sha,
      'complete_source_zip':str(source_output),'complete_source_zip_sha256':source_zip_sha,
    }

def verify_only():
    with tempfile.TemporaryDirectory(prefix='snfla-verify-') as td:
        td=Path(td)
        a=td/'a.zip'; sa=td/'sa.zip'; b=td/'b.zip'; sb=td/'sb.zip'
        first=build(a,sa); second=build(b,sb)
        if a.read_bytes()!=b.read_bytes(): raise SystemExit('Installable package is not byte-identical across two builds.')
        if sa.read_bytes()!=sb.read_bytes(): raise SystemExit('Complete-source package is not byte-identical across two builds.')
        if first['source_tree_sha256']!=second['source_tree_sha256']: raise SystemExit('Source-tree digest changed between deterministic builds.')
        print(json.dumps({
          'deterministic':True,
          'version':VERSION,
          'source_tree_sha256':first['source_tree_sha256'],
          'installable_zip_sha256':sha256_file(a),
          'complete_source_zip_sha256':sha256_file(sa),
          'central_plan_cv_count':CENTRAL_CV_COUNT,
          'central_plan_review_rounds':CENTRAL_PLAN_REVIEW_ROUNDS,
          'future18_count':FUTURE18_COUNT,
          'future18_review_rounds':FUTURE18_REVIEW_ROUNDS,
          'ten_round_post_future18_review_rounds':TEN_ROUND_REVIEW_ROUNDS,
          'second_ten_round_review_rounds':SECOND_TEN_ROUND_REVIEW_ROUNDS,
          'third_ten_round_review_rounds':THIRD_TEN_ROUND_REVIEW_ROUNDS,
        },sort_keys=True))

def main():
    ap=argparse.ArgumentParser()
    ap.add_argument('--output',type=Path,default=Path('/mnt/data/04-sabri-news-feed-legacy-adapter-2.0.3.zip'))
    ap.add_argument('--source-output',type=Path,default=Path('/mnt/data/04-sabri-news-feed-legacy-adapter-2.0.3-complete-source.zip'))
    ap.add_argument('--verify-only',action='store_true')
    args=ap.parse_args()
    if args.verify_only: verify_only(); return
    result=build(args.output,args.source_output); print(json.dumps(result,sort_keys=True))
if __name__=='__main__': main()
