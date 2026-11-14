#!/usr/bin/env python3
"""S0 Hostinger collector: synthetic read-only and checksum-parity negative tests."""
from pathlib import Path
import tempfile, subprocess, json, hashlib

ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / 'tools/collect_file04_deployed_readonly.php'

def digest(data):
    return hashlib.sha256(data).hexdigest()

def run(root):
    proc = subprocess.run(['php',str(SCRIPT),str(root)],capture_output=True,text=True,check=False)
    try: data=json.loads(proc.stdout)
    except json.JSONDecodeError as e: raise AssertionError('Collector must emit JSON only: '+repr(proc.stdout[:180])) from e
    return proc.returncode,data,proc.stdout

def snapshot(root):
    return {str(p.relative_to(root)):digest(p.read_bytes()) for p in root.rglob('*') if p.is_file()}

with tempfile.TemporaryDirectory(prefix='file04-s0-fixture-') as temp:
    root=Path(temp)
    (root/'wp-includes').mkdir()
    plug=root/'wp-content/plugins/04-sabri-news-feed-legacy-adapter'
    (plug/'includes').mkdir(parents=True)
    (root/'wp-includes/version.php').write_text("<?php $wp_version = '6.7.2';\n")
    (root/'wp-config.php').write_text('PRIVATE_TEST_SECRET_NEVER_EXPORTED')
    examples={
        'sabri-news-feed-legacy-adapter.php':b'<?php\n/**\n * Plugin Name: Sabri File04\n * Version: 2.0.8\n */\n',
        'includes/class-demo.php':b'<?php class Demo {}\n',
    }
    for name,content in examples.items(): (plug/name).write_bytes(content)
    (plug/'PACKAGE-CHECKSUMS.sha256').write_text(''.join(digest(content)+'  '+name+'\n' for name,content in sorted(examples.items())))
    before=snapshot(root)
    code,data,output=run(root)
    assert code==0 and data['ok'] and data['file04']['manifest_parity_verified']
    assert data['file04']['manifest_entries']==2 and data['file04']['files_scanned']==3
    assert data['file04']['plugin_version_from_header']=='2.0.8'
    assert data['wordpress_version_from_core_file']=='6.7.2'
    assert data['plugin_active'].startswith('UNVERIFIED') and data['actual_db_schema'].startswith('UNVERIFIED')
    assert data['credentials_collected'] is False and data['raw_content_exported'] is False
    assert str(root) not in output and 'PRIVATE_TEST_SECRET' not in output
    assert snapshot(root)==before, 'Read-only collector wrote to fixture'
    (plug/'includes/class-demo.php').write_bytes(b'<?php class Demo { /* changed */ }\n')
    code,data,_=run(root)
    assert code==0 and data['file04']['manifest_mismatches']==1 and not data['file04']['manifest_parity_verified']
    (plug/'extra.txt').write_text('untracked')
    code,data,_=run(root)
    assert code==0 and data['file04']['unexpected_files']==1 and not data['file04']['manifest_parity_verified']
    (plug/'includes/class-demo.php').unlink()
    code,data,_=run(root)
    assert code==0 and data['file04']['manifest_missing_files']==1 and not data['file04']['manifest_parity_verified']
    (plug/'sabri-news-feed-legacy-adapter.php').unlink()
    code,data,_=run(root)
    assert code==0 and data['file04']['signature_file_found'] is False
    assert 'NOT PROOF OF ABSENCE' in data['file04']['status']
    code,data,_=run(root/'missing')
    assert code!=0 and data['ok'] is False
    text=SCRIPT.read_text()
    for dangerous in ('wp-load.php','wp-config.php','file_put_contents','mysqli_connect','shell_exec(', 'wp_insert_post('):
        assert dangerous not in text, 'Collector must not read secrets/bootstrap DB or write: '+dangerous
print('File04 S0 read-only collector PASS: seven positive/negative fixtures, no leaked secrets or file changes.')
