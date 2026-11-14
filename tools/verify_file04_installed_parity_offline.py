#!/usr/bin/env python3
"""Offline File04 host-JSON-to-approved-ZIP parity. No live/DB claim or network."""
import argparse, datetime, hashlib, json, sys, zipfile
from pathlib import Path

def digest(data):
    return hashlib.sha256(data).hexdigest()

def result(status, reasons, package_hash=None, files=None):
    print(json.dumps({
        'scope':'FILESYSTEM_ONLY', 'filesystem_parity':status, 'reasons':reasons,
        'approved_zip_sha256':package_hash, 'package_file_count':files,
        'deployed_version':'UNVERIFIED unless host evidence is operator-attested',
        'actual_db_version':'UNVERIFIED','migration_state':'UNVERIFIED',
        'live_verification':'NOT PERFORMED',
    },sort_keys=True,indent=2))

def audit(report_path,package_path):
    if report_path.stat().st_size>1048576 or package_path.stat().st_size>50000000:
        result('UNVERIFIED',['input_too_large']); return 2
    try:
        report=json.loads(report_path.read_text(encoding='utf-8'))
        plugin=report.get('file04',{})
        with zipfile.ZipFile(package_path) as archive:
            names=archive.namelist()
            if len(names)>1000 or len(names)!=len(set(names)):
                result('UNVERIFIED',['archive_file_count_or_duplicates_invalid']);return 2
            if any(name.startswith('/') or '..' in Path(name).parts or '\\' in name for name in names):
                result('UNVERIFIED',['unsafe_archive_path']);return 2
            members=archive.infolist()
            if any(m.file_size>10485760 for m in members) or sum(m.file_size for m in members)>52428800:
                result('UNVERIFIED',['archive_uncompressed_limit_exceeded']);return 2
            if any(((m.external_attr>>16)&0o170000)==0o120000 for m in members):
                result('UNVERIFIED',['archive_contains_symlink']);return 2
            roots={name.split('/')[0] for name in names}
            if len(roots)!=1:
                result('UNVERIFIED',['archive_multiple_roots']);return 2
            root=roots.pop()+'/'
            contents={name[len(root):]:archive.read(name) for name in names}
    except (OSError,ValueError,zipfile.BadZipFile,UnicodeDecodeError,KeyError,TypeError):
        result('UNVERIFIED',['input_unreadable_or_invalid']);return 2
    package_hash=digest(package_path.read_bytes())
    required=('sabri-news-feed-legacy-adapter.php','PACKAGE-CHECKSUMS.sha256')
    if not all(key in contents for key in required):
        result('UNVERIFIED',['not_a_file04_installable_package'],package_hash,len(contents));return 2
    tree=''.join(digest(blob)+'  '+str(len(blob))+'  '+name+'\n' for name,blob in sorted(contents.items()))
    checks={
        'entry_file_sha256':digest(contents[required[0]]),
        'package_manifest_sha256':digest(contents[required[1]]),
        'read_only_tree_sha256':digest(tree.encode()),
        'files_scanned':len(contents),
    }
    if report.get('collector')!='file04-readonly-filesystem-evidence-v1' or report.get('schema')!=1 or not plugin.get('signature_file_found'):
        result('UNVERIFIED',['collector_identity_or_file_evidence_missing'],package_hash,len(contents));return 2
    try:
        stamp=datetime.datetime.fromisoformat(report['observed_at_utc'].replace('Z','+00:00'))
        now=datetime.datetime.now(datetime.timezone.utc)
        fresh=(datetime.timedelta(seconds=-300)<=now-stamp<=datetime.timedelta(days=1))
    except (KeyError,ValueError,TypeError): fresh=False
    if not fresh:
        result('UNVERIFIED',['stale_or_invalid_host_capture_timestamp'],package_hash,len(contents));return 2
    if not plugin.get('scan_complete') or not plugin.get('manifest_parity_verified'):
        result('UNVERIFIED',['host_scan_or_local_manifest_not_verified'],package_hash,len(contents));return 2
    mismatches=[field for field,expected in checks.items() if plugin.get(field)!=expected]
    if mismatches:
        result('MISMATCH',['approved_package_differs:'+','.join(mismatches)],package_hash,len(contents));return 1
    result('MATCH',['approved_zip_and_operator_host_files_are_byte_identical_by_manifest','db_migration_and_active_plugin_still_unverified'],package_hash,len(contents));return 0

if __name__=='__main__':
    parser=argparse.ArgumentParser()
    parser.add_argument('host_report_json',type=Path)
    parser.add_argument('approved_installable_zip',type=Path)
    arguments=parser.parse_args()
    try:sys.exit(audit(arguments.host_report_json,arguments.approved_installable_zip))
    except OSError:result('UNVERIFIED',['input_access_failed']);sys.exit(2)
