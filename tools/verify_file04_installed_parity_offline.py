#!/usr/bin/env python3
"""Offline File04 host-JSON-to-approved-ZIP parity. No live/DB claim or network."""
import argparse, datetime, hashlib, json, re, sys, zipfile
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

def valid_manifest(files):
    """Verify every ZIP member against the release manifest, independently."""
    key = 'PACKAGE-CHECKSUMS.sha256'
    content = files.get(key)
    if content is None or len(content) > 262144:
        return False
    try:
        lines = content.decode('utf-8').splitlines()
    except UnicodeDecodeError:
        return False
    expected = {}
    for line in lines:
        match = re.fullmatch(r'([a-f0-9]{64})  ([A-Za-z0-9_./-]+)', line)
        if match is None:
            return False
        checksum, name = match.groups()
        if '..' in name or name.startswith('/') or name == key or name in expected:
            return False
        expected[name] = checksum
    return set(expected) == set(files) - {key} and all(
        digest(files[name]) == checksum for name, checksum in expected.items()
    )


def audit(report_path,package_path):
    if report_path.stat().st_size>1048576 or package_path.stat().st_size>50000000:
        result('UNVERIFIED',['input_too_large']); return 2
    try:
        report=json.loads(report_path.read_text(encoding='utf-8'))
        if not isinstance(report, dict) or not isinstance(report.get('file04'), dict):
            result('UNVERIFIED',['host_report_structure_invalid']); return 2
        plugin=report['file04']
        with zipfile.ZipFile(package_path) as archive:
            names=archive.namelist()
            if not names or len(names)>1000 or len(names)!=len(set(names)):
                result('UNVERIFIED',['archive_file_count_or_duplicates_invalid']);return 2
            if any(m.is_dir() or m.filename.startswith('/') or '..' in Path(m.filename).parts or '\\' in m.filename for m in archive.infolist()):
                result('UNVERIFIED',['unsafe_archive_path']);return 2
            members=archive.infolist()
            if any(m.file_size>10485760 for m in members) or sum(m.file_size for m in members)>52428800:
                result('UNVERIFIED',['archive_uncompressed_limit_exceeded']);return 2
            if any(((m.external_attr>>16)&0o170000)==0o120000 for m in members):
                result('UNVERIFIED',['archive_contains_symlink']);return 2
            roots={name.split('/')[0] for name in names}
            if len(roots)!=1 or '' in roots:
                result('UNVERIFIED',['archive_multiple_roots']);return 2
            root=roots.pop()+'/'
            contents={name[len(root):]:archive.read(name) for name in names}
    except (OSError,ValueError,zipfile.BadZipFile,UnicodeDecodeError,KeyError,TypeError,EOFError):
        result('UNVERIFIED',['input_unreadable_or_invalid']);return 2
    package_hash=digest(package_path.read_bytes())
    required=('sabri-news-feed-legacy-adapter.php','PACKAGE-CHECKSUMS.sha256')
    if not all(key in contents for key in required):
        result('UNVERIFIED',['not_a_file04_installable_package'],package_hash,len(contents));return 2
    if not valid_manifest(contents):
        result('UNVERIFIED',['approved_zip_manifest_integrity_failed'],package_hash,len(contents));return 2
    tree=''.join(digest(blob)+'  '+str(len(blob))+'  '+name+'\n' for name,blob in sorted(contents.items()))
    checks={
        'entry_file_sha256':digest(contents[required[0]]),
        'package_manifest_sha256':digest(contents[required[1]]),
        'read_only_tree_sha256':digest(tree.encode()),
        'files_scanned':len(contents),
    }
    if (report.get('collector')!='file04-readonly-filesystem-evidence-v1'
            or type(report.get('schema')) is not int or report['schema']!=1
            or report.get('ok') is not True
            or report.get('credentials_collected') is not False
            or report.get('raw_content_exported') is not False
            or plugin.get('signature_file_found') is not True):
        result('UNVERIFIED',['collector_identity_or_safety_flags_invalid'],package_hash,len(contents));return 2
    raw_timestamp=report.get('observed_at_utc')
    if (not isinstance(raw_timestamp,str)
            or re.fullmatch(r'[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z',raw_timestamp) is None):
        result('UNVERIFIED',['stale_or_invalid_host_capture_timestamp'],package_hash,len(contents));return 2
    try:
        stamp=datetime.datetime.strptime(raw_timestamp,'%Y-%m-%dT%H:%M:%SZ').replace(tzinfo=datetime.timezone.utc)
        now=datetime.datetime.now(datetime.timezone.utc)
        fresh=(datetime.timedelta(seconds=-300)<=now-stamp<=datetime.timedelta(days=1))
    except (ValueError,OverflowError): fresh=False
    if not fresh:
        result('UNVERIFIED',['stale_or_invalid_host_capture_timestamp'],package_hash,len(contents));return 2
    if (plugin.get('scan_complete') is not True
            or plugin.get('manifest_parity_verified') is not True
            or any(type(plugin.get(k)) is not int or plugin[k]!=0
                   for k in ('manifest_missing_files','manifest_mismatches','unexpected_files'))
            or type(plugin.get('manifest_entries')) is not int
            or plugin['manifest_entries']!=len(contents)-1):
        result('UNVERIFIED',['host_scan_or_local_manifest_not_verified'],package_hash,len(contents));return 2
    if (type(plugin.get('files_scanned')) is not int
            or type(plugin.get('bytes_scanned')) is not int
            or plugin['bytes_scanned']<0):
        result('UNVERIFIED',['host_file_count_or_size_invalid'],package_hash,len(contents));return 2
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
    except (OSError,ValueError,TypeError,AttributeError):
        result('UNVERIFIED',['input_access_or_structure_failed']);sys.exit(2)
