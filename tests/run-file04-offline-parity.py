#!/usr/bin/env python3
"""Offline V12.3 P02 parity gate: do not mistake filesystem match for live acceptance."""
from pathlib import Path
import tempfile,zipfile,subprocess,hashlib,json,datetime

ROOT=Path(__file__).resolve().parents[1]
COLLECTOR=ROOT/'tools/collect_file04_deployed_readonly.php'
VERIFIER=ROOT/'tools/verify_file04_installed_parity_offline.py'

def sha(data):return hashlib.sha256(data).hexdigest()
def verify(report,zip_path):
    p=subprocess.run(['python3',str(VERIFIER),str(report),str(zip_path)],capture_output=True,text=True)
    return p.returncode,json.loads(p.stdout),p.stdout

with tempfile.TemporaryDirectory(prefix='file04-parity-') as t:
    root=Path(t);wp=root/'wordpress'
    (wp/'wp-includes').mkdir(parents=True)
    plugin=wp/'wp-content/plugins/04-sabri-news-feed-legacy-adapter'
    (plugin/'includes').mkdir(parents=True)
    (wp/'wp-includes/version.php').write_text("<?php $wp_version = '6.7.2';")
    original={
        'sabri-news-feed-legacy-adapter.php':b'<?php\n/**\n * Version: 2.0.8\n */\n',
        'includes/example.php':b'<?php class Example {}\n',
    }
    for name,content in original.items():(plugin/name).write_bytes(content)
    original['PACKAGE-CHECKSUMS.sha256']=''.join(sha(content)+'  '+name+'\n' for name,content in sorted(original.items())).encode()
    (plugin/'PACKAGE-CHECKSUMS.sha256').write_bytes(original['PACKAGE-CHECKSUMS.sha256'])
    approved=root/'approved.zip'
    with zipfile.ZipFile(approved,'w',zipfile.ZIP_DEFLATED) as z:
        for name,content in sorted(original.items()):z.writestr('04-sabri-news-feed-legacy-adapter/'+name,content)
    host=root/'host.json'
    collected=subprocess.run(['php',str(COLLECTOR),str(wp)],capture_output=True,text=True,check=True)
    host.write_text(collected.stdout)
    code,data,out=verify(host,approved)
    assert code==0 and data['filesystem_parity']=='MATCH'
    assert data['actual_db_version']=='UNVERIFIED' and data['live_verification']=='NOT PERFORMED'
    assert str(wp) not in out
    parsed=json.loads(host.read_text())
    parsed['file04']['entry_file_sha256']='0'*64
    host.write_text(json.dumps(parsed))
    code,data,_=verify(host,approved)
    assert code==1 and data['filesystem_parity']=='MISMATCH'
    host.write_text(collected.stdout)
    (plugin/'includes/example.php').write_text('<?php /* edited */')
    changed=subprocess.run(['php',str(COLLECTOR),str(wp)],capture_output=True,text=True,check=True)
    host.write_text(changed.stdout)
    code,data,_=verify(host,approved)
    assert code==2 and data['filesystem_parity']=='UNVERIFIED'
    parsed=json.loads(collected.stdout)
    parsed['observed_at_utc']='2020-01-01T00:00:00Z'
    host.write_text(json.dumps(parsed))
    code,data,_=verify(host,approved)
    assert code==2 and 'stale_or_invalid_host_capture_timestamp' in data['reasons']
    host.write_text('{broken json')
    code,data,_=verify(host,approved)
    assert code==2 and data['filesystem_parity']=='UNVERIFIED'
    host.write_text(collected.stdout)
    badzip=root/'bad.zip'
    with zipfile.ZipFile(badzip,'w') as z:z.writestr('arbitrary.txt','x')
    code,data,_=verify(host,badzip)
    assert code==2 and data['filesystem_parity']=='UNVERIFIED'
    assert 'input_access_failed' not in data['reasons']
print('File04 independent offline P02 gate PASS: 6 positive/negative fixtures; MATCH is filesystem-only.')
