<?php
/**
 * File04 S0: strictly read-only filesystem evidence collector.
 * Operator-only CLI: php collect_file04_deployed_readonly.php /absolute/path/to/wordpress
 * No WordPress bootstrap, database login, external network request or write.
 * Output never includes absolute paths, credential contents or private records.
 */
if ( PHP_SAPI !== 'cli' ) { exit( 64 ); }
function report( $data, $status = 0 ) {
    echo json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . PHP_EOL;
    exit( $status );
}
if ( count( $argv ) !== 2 || ! is_string( $argv[1] ) || '' === $argv[1] ) {
    report( array( 'ok'=>false, 'error'=>'usage: php collect_file04_deployed_readonly.php /absolute/path/to/wordpress' ), 64 );
}
$root = realpath( $argv[1] );
if ( false === $root || ! is_dir( $root . '/wp-includes' ) || ! is_dir( $root . '/wp-content/plugins' ) ) {
    report( array( 'ok'=>false, 'error'=>'WordPress root and plugins directory must exist' ), 65 );
}
$version = 'unknown';
$version_file = $root . '/wp-includes/version.php';
if ( is_file( $version_file ) && ! is_link( $version_file ) ) {
    $version_source = @file_get_contents( $version_file, false, null, 0, 16384 );
    if ( is_string( $version_source ) && preg_match( '/\$wp_version\s*=\s*[\'\"]([0-9][0-9A-Za-z.\-]*)[\'\"]/', $version_source, $m ) ) { $version = $m[1]; }
}
$matches = glob( $root . '/wp-content/plugins/*/sabri-news-feed-legacy-adapter.php' );
if ( ! is_array( $matches ) ) { $matches = array(); }
if ( count( $matches ) > 1 ) {
    report( array( 'ok'=>false, 'error'=>'Multiple File04 plugin candidates; manual operator disambiguation required', 'candidate_count'=>count( $matches ) ), 66 );
}
$base = array(
    'schema'=>1,
    'collector'=>'file04-readonly-filesystem-evidence-v1',
    'observed_at_utc'=>gmdate( 'Y-m-d\TH:i:s\Z' ),
    'wordpress_version_from_core_file'=>$version,
    'php_cli_version'=>PHP_VERSION,
    'php_web_runtime'=>'UNVERIFIED',
    'plugin_active'=>'UNVERIFIED (requires DB/WordPress evidence)',
    'actual_db_schema'=>'UNVERIFIED (requires authorized DB evidence)',
    'migration_state'=>'UNVERIFIED (requires authorized DB evidence)',
    'runtime_logs'=>'NOT COLLECTED',
    'credentials_collected'=>false,
    'raw_content_exported'=>false,
);
if ( count( $matches ) === 0 ) {
    $base['file04']=array( 'signature_file_found'=>false, 'status'=>'NOT FOUND BY EXPECTED FILENAME; NOT PROOF OF ABSENCE' );
    report( array_merge( array( 'ok'=>true ), $base ) );
}
$main = realpath( $matches[0] );
$dir = realpath( dirname( $matches[0] ) );
$plugins = realpath( $root . '/wp-content/plugins' );
if ( false === $main || false === $dir || false === $plugins || ! str_starts_with( $dir, $plugins . DIRECTORY_SEPARATOR ) || is_link( $matches[0] ) ) {
    report( array( 'ok'=>false, 'error'=>'Symlink or unsafe File04 path requires manual inspection' ), 67 );
}
$main_head = @file_get_contents( $main, false, null, 0, 8192 );
$plugin_version = 'unknown';
if ( is_string( $main_head ) && preg_match( '/^\s*\*?\s*Version:\s*([0-9A-Za-z.\-]+)/mi', $main_head, $m ) ) { $plugin_version=$m[1]; }
$checksums_file = $dir . '/PACKAGE-CHECKSUMS.sha256';
$expected=array(); $manifest_verified=false; $errors=array();
if ( is_file( $checksums_file ) && ! is_link( $checksums_file ) && @filesize( $checksums_file ) <= 262144 ) {
    $manifest = @file( $checksums_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
    if ( is_array( $manifest ) && count( $manifest ) <= 1000 ) {
        $manifest_verified=true;
        foreach ( $manifest as $line ) {
            if ( ! preg_match( '/^([a-f0-9]{64})  ([A-Za-z0-9_\.\/\-]+)$/D', $line, $m ) || str_contains( $m[2], '..' ) || str_starts_with( $m[2], '/' ) || isset( $expected[$m[2]] ) ) {
                $errors[]='manifest_format_or_duplicate_error'; $manifest_verified=false; break;
            }
            $expected[$m[2]]=$m[1];
        }
    }
}
$all=array(); $bytes=0; $file_limit=1000; $byte_limit=52428800; $truncated=false;
try {
    $iterator=new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
    foreach ( $iterator as $file ) {
        if ( $file->isLink() ) { $errors[]='symlink_encountered'; continue; }
        if ( ! $file->isFile() ) { continue; }
        $absolute=$file->getPathname();
        $relative=substr( $absolute, strlen($dir)+1 );
        $relative=str_replace(DIRECTORY_SEPARATOR,'/',$relative);
        $size=$file->getSize();
        if ( count($all)>=$file_limit || $bytes+$size>$byte_limit || $size>10485760 ) { $truncated=true; break; }
        $digest=@hash_file('sha256',$absolute);
        if ( ! is_string($digest) ) { $errors[]='file_hash_failed'; continue; }
        $all[$relative]=array( 'bytes'=>$size, 'sha256'=>$digest );
        $bytes+=$size;
    }
} catch ( Throwable $e ) { $errors[]='filesystem_traversal_failed'; }
ksort( $all, SORT_STRING );
$tree='';
foreach ( $all as $path=>$rec ) { $tree.=$rec['sha256'].'  '.$rec['bytes'].'  '.$path."\n"; }
$missing=0; $mismatched=0;
foreach ( $expected as $path=>$digest ) {
    if ( ! isset( $all[$path] ) ) { $missing++; }
    elseif ( ! hash_equals($digest,$all[$path]['sha256']) ) { $mismatched++; }
}
$extras=0;
foreach ( $all as $path=>$rec ) { if ( $path!=='PACKAGE-CHECKSUMS.sha256' && ! isset($expected[$path]) ) { $extras++; } }
$entry_digest = @hash_file('sha256',$main);
if ( ! is_string($entry_digest) ) { $errors[]='entry_hash_failed'; }
$manifest_digest = is_file($checksums_file) ? @hash_file('sha256',$checksums_file) : null;
if ( is_file($checksums_file) && ! is_string($manifest_digest) ) { $errors[]='manifest_hash_failed'; }
$checked=(bool)($manifest_verified && !$truncated && !$errors && count($expected)>0 && $missing===0 && $mismatched===0 && $extras===0);
$base['file04']=array(
    'signature_file_found'=>true,
    'plugin_version_from_header'=>$plugin_version,
    'entry_file_sha256'=>is_string($entry_digest)?$entry_digest:null,
    'package_manifest_present'=>is_file($checksums_file),
    'package_manifest_sha256'=>is_string($manifest_digest)?$manifest_digest:null,
    'files_scanned'=>count($all),
    'bytes_scanned'=>$bytes,
    'read_only_tree_sha256'=>hash('sha256',$tree),
    'manifest_entries'=>count($expected),
    'manifest_missing_files'=>$missing,
    'manifest_mismatches'=>$mismatched,
    'unexpected_files'=>$extras,
    'manifest_parity_verified'=>$checked,
    'scan_complete'=>!$truncated && !$errors,
    'safe_error_codes'=>array_values(array_unique($errors)),
    'deployed_artifact_checksum'=>'UNVERIFIED (installed tree is not the original ZIP bytes)',
);
report( array_merge( array( 'ok'=>true ), $base ) );
