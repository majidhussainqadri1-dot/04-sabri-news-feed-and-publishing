#!/usr/bin/env python3
"""Offline V12.3 P02 parity gate: do not mistake filesystem match for live acceptance."""
from pathlib import Path
import tempfile,zipfile,subprocess,hashlib,json,datetime,warnings,os,io,contextlib,importlib.util

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

    # Additional adversarial input cases: invalid JSON shape and typed safety flags.
    baseline = json.loads(collected.stdout)
    bad_reports = [
        ('json_list', '[]'),
        ('nested_list', json.dumps({'file04': []})),
        ('timestamp_integer', json.dumps({**baseline, 'observed_at_utc': 17})),
        ('timestamp_null', json.dumps({**baseline, 'observed_at_utc': None})),
        ('naive_timestamp', json.dumps({**baseline, 'observed_at_utc': '2026-11-14 13:00:00'})),
        ('schema_bool', json.dumps({**baseline, 'schema': True})),
        ('privacy_flag_untrue', json.dumps({**baseline, 'raw_content_exported': True})),
        ('scan_string_true', json.dumps({**baseline, 'file04': {**baseline['file04'], 'scan_complete': 'true'}})),
        ('manifest_string_true', json.dumps({**baseline, 'file04': {**baseline['file04'], 'manifest_parity_verified': 'true'}})),
        ('missing_count', json.dumps({**baseline, 'file04': {**baseline['file04'], 'manifest_entries': None}})),
        ('false_boolean_count', json.dumps({**baseline, 'file04': {**baseline['file04'], 'manifest_missing_files': False}})),
    ]
    for name, text in bad_reports:
        host.write_text(text)
        proc = subprocess.run(['python3', str(VERIFIER), str(host), str(approved)],
                              capture_output=True, text=True)
        assert proc.returncode == 2 and not proc.stderr, (name, proc.returncode, proc.stderr[-300:])
        response = json.loads(proc.stdout)
        assert response['filesystem_parity'] == 'UNVERIFIED', (name, response)
        assert response['live_verification'] == 'NOT PERFORMED'

    host.write_text(collected.stdout)
    forged = root/'forged.zip'
    with zipfile.ZipFile(forged,'w',zipfile.ZIP_DEFLATED) as z:
        for name,content in sorted(original.items()):
            z.writestr('04-sabri-news-feed-legacy-adapter/'+name,
                       b'<?php /* altered */' if name=='includes/example.php' else content)
    code,data,_=verify(host,forged)
    assert code==2 and 'approved_zip_manifest_integrity_failed' in data['reasons']

    traversal = root/'traversal.zip'
    with zipfile.ZipFile(traversal,'w') as z:
        z.writestr('04-sabri-news-feed-legacy-adapter/../../unsafe.php',b'<?php ?>')
    code,data,_=verify(host,traversal)
    assert code==2 and data['filesystem_parity']=='UNVERIFIED'

    duplicate = root/'duplicate.zip'
    with warnings.catch_warnings():
        warnings.simplefilter('ignore',UserWarning)
        with zipfile.ZipFile(duplicate,'w') as z:
            z.writestr('04-sabri-news-feed-legacy-adapter/duplicate.txt','first')
            z.writestr('04-sabri-news-feed-legacy-adapter/duplicate.txt','second')
    code,data,_=verify(host,duplicate)
    assert code==2 and 'archive_file_count_or_duplicates_invalid' in data['reasons']

    # P02 TOCTOU negative case: the ZIP must be hashed from the identical
    # bytes used for ZIP inspection, even if its disk path is swapped.
    host.write_text(collected.stdout)
    original_sha=sha(approved.read_bytes())
    replacement=root/'replacement.zip'
    changed_files=dict(original)
    changed_files['includes/example.php']=b'<?php echo 999;'
    changed_files['PACKAGE-CHECKSUMS.sha256']=''.join(
        sha(value)+'  '+name+'\n' for name,value in sorted(changed_files.items())
        if name!='PACKAGE-CHECKSUMS.sha256'
    ).encode()
    with zipfile.ZipFile(replacement,'w',zipfile.ZIP_DEFLATED) as z:
        for name,value in sorted(changed_files.items()):
            z.writestr('04-sabri-news-feed-legacy-adapter/'+name,value)
    replacement_sha=sha(replacement.read_bytes())
    assert replacement_sha!=original_sha
    spec=importlib.util.spec_from_file_location('file04_parity_under_test',VERIFIER)
    verifier=importlib.util.module_from_spec(spec)
    spec.loader.exec_module(verifier)
    original_zip_class=zipfile.ZipFile
    class SwapOnOpen(original_zip_class):
        def __enter__(self):
            os.replace(replacement,approved)
            return super().__enter__()
    zipfile.ZipFile=SwapOnOpen
    output=io.StringIO()
    try:
        with contextlib.redirect_stdout(output):
            decision=verifier.audit(host,approved)
    finally:
        zipfile.ZipFile=original_zip_class
    response=json.loads(output.getvalue())
    assert decision==0 and response['filesystem_parity']=='MATCH'
    assert response['approved_zip_sha256']==original_sha, 'Reported ZIP checksum must be from inspected bytes'
    assert sha(approved.read_bytes())==replacement_sha, 'Fixture must swap ZIP on disk'
print('File04 independent offline P02 gate PASS: 21 positive/negative fixtures; MATCH is filesystem-only.')
