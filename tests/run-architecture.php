<?php
$root = dirname( __DIR__ );
function arch_fail( $message ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
function arch_assert( $condition, $message ) { if ( ! $condition ) { arch_fail( $message ); } }

$files = array();
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
	$path = str_replace( '\\', '/', $file->getPathname() );
	if ( false !== strpos( $path, '/.git/' ) || preg_match( '/\.(zip|tar|gz)$/', $path ) ) { continue; }
	$relative = ltrim( substr( $path, strlen( $root ) ), '/' );
	$files[ $relative ] = file_get_contents( $path );
}

foreach ( array_keys( $files ) as $path ) {
	arch_assert( false === strpos( $path, 'class-snp-' ), 'obsolete SNP class file remains: ' . $path );
}
$php = '';
foreach ( $files as $path => $content ) { if ( str_ends_with( $path, '.php' ) && ! str_starts_with( $path, 'tests/' ) ) { $php .= "\n/* {$path} */\n{$content}"; } }

$forbidden = array(
	"define( 'SNP_VERSION'" => 'obsolete parallel runtime version',
	'class SNP_' => 'obsolete parallel runtime class',
	'add_role(' => 'role creation',
	'add_cap(' => 'capability grant',
	'set_role(' => 'role mutation',
	'current_user_can( \'manage_options\'' => 'administrator fallback authority',
	'wp_insert_post(' => 'File 04 canonical post creation',
	'DROP TABLE' => 'destructive table removal',
	'_snp_viral_score' => 'legacy feed ranking ownership',
	'sabri_file21_publication_provider' => 'parallel feed provider ownership',
	'sabri_universal_composer_types' => 'public composer ownership',
	'wp_redirect(' => 'unsafe redirect primitive',
	'wp_delete_post(' => 'source or target deletion',
	'wp_delete_comment(' => 'comment deletion',
);
foreach ( $forbidden as $needle => $label ) { arch_assert( false === strpos( $php, $needle ), $label . ' is present' ); }

$required = array(
	"Plugin Name: Sabri News Feed Legacy Foundation Adapter" => 'canonical plugin identity',
	"define( 'SNFLA_VERSION', '1.0.0' )" => 'version constant',
	"LegacyPublicationMigration::migrate_selected" => 'File 21 canonical migration boundary',
	"LegacyPublicationRollback::rollback_selected" => 'File 21 rollback boundary',
	"sabri_hnf_legacy_interaction_migration_providers" => 'File 21 interaction provider contract',
	"InteractionRepository::insert_row" => 'canonical interaction insert boundary',
	"InteractionRepository::update_rows" => 'canonical interaction rollback boundary',
	"SNFLA_Inventory::unchanged" => 'source signature gate',
	"Idempotency-Key" => 'idempotency contract',
	"GET_LOCK" => 'database operation lock',
	"X-Robots-Tag: noindex" => 'legacy indexing protection',
	"wp_safe_redirect" => 'safe redirect',
	"RETIRE FILE 04 LEGACY ADAPTER" => 'explicit retirement confirmation',
	"pre_delete_post" => 'source deletion guard',
	"update_post_metadata" => 'source metadata guard',
	"wp_insert_post_empty_content" => 'source creation/update guard',
	"sabri/v1/legacy/file-04" => 'required REST namespace',
);
foreach ( $required as $needle => $label ) { arch_assert( false !== strpos( $php, $needle ), $label . ' is missing' ); }

foreach ( array( '/status', '/dry-run', '/migrate', '/rollback', '/retire' ) as $route ) {
	arch_assert( false !== strpos( $files['includes/class-snfla-rest.php'], "'{$route}'" ), 'required REST route missing: ' . $route );
}
arch_assert( 0 === preg_match( '/(?:INSERT|UPDATE|DELETE)\s+(?:INTO\s+)?[^\n]*sabri_feed_/i', $php ), 'direct File 21 table mutation SQL detected' );
arch_assert( false !== strpos( $files['uninstall.php'], 'No destructive' ), 'retention-only uninstall policy missing' );

fwrite( STDOUT, "File 04 architecture checks passed.\n" );
