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
	"'permission_callback' => '__return_true'" => 'public REST permission callback',
	'array_slice( (array) $values, 0, 50 )' => 'truncated metadata custody',
);
foreach ( $forbidden as $needle => $label ) { arch_assert( false === strpos( $php, $needle ), $label . ' is present' ); }

$required = array(
	"Plugin Name: Sabri News Feed Legacy Foundation Adapter" => 'canonical plugin identity',
	"define( 'SNFLA_VERSION', '1.0.1' )" => 'version constant',
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
	"pre_insert_term" => 'legacy taxonomy creation guard',
	"add_term_relationship" => 'legacy taxonomy relationship guard',
	"report_checksum_valid" => 'dry-run and reconciliation tamper detection',
	"evidence_hmac" => 'signed backup rollback fallback evidence',
	"verify_chain" => 'audit chain verification',
	"publication_projection_mismatch" => 'semantic migration reconciliation',
	"snfla_idempotency_conflict" => 'idempotency payload collision protection',
	"rollback_conflict" => 'partial rollback quarantine',
	"SNFLA_Rollback::proof" => 'retirement rollback-proof gate',
	"source_total" => 'current source-count revalidation',
	"has_event" => 'reconciliation audit-event binding',
	"MAX_REPORT_AGE" => 'reconciliation freshness gate',
	"source_signature' => \$signature" => 'rollback proof source binding',
	"backup_proof_recorded" => 'backup proof audit binding',
	"function resolve_conflict" => 'CLI conflict-resolution command',
	"wp_clear_scheduled_hook( 'snfla_daily_integrity_check' )" => 'retirement cron shutdown',
	"snfla_legacy_page_quarantine" => 'quarantine evidence',
	"register_legacy_schema' ), 9999" => 'final read-only post-type authority',
	"quarantine_legacy_pages" => 'legacy page quarantine',
	"deactivate_obsolete_runtime" => 'obsolete runtime deactivation',
	"mutation commands are disabled" => 'CLI retirement gate',
	"snfla_stale_run_finalize_failed" => 'stale-run ledger finalization guard',
	"function fallback" => 'CLI fallback command',
	"function cutover" => 'CLI cutover command',
	"function backup_proof" => 'CLI backup-proof command',
	"snfla_idempotent_previous_failure" => 'failed idempotency replay guard',
	"view_count" => 'legacy view-count preservation',
	"canonical_reaction_conflict" => 'canonical user-intent conflict guard',
	"created_by_migration" => 'interaction rollback provenance',
);
foreach ( $required as $needle => $label ) { arch_assert( false !== strpos( $php, $needle ), $label . ' is missing' ); }

foreach ( array( '/status', '/dry-run', '/migrate', '/rollback', '/retire' ) as $route ) {
	arch_assert( false !== strpos( $files['includes/class-snfla-rest.php'], "'{$route}'" ), 'required REST route missing: ' . $route );
}
arch_assert( 0 === preg_match( '/(?:INSERT|UPDATE|DELETE)\s+(?:INTO\s+)?[^\n]*sabri_feed_/i', $php ), 'direct File 21 table mutation SQL detected' );
arch_assert( false !== strpos( $files['uninstall.php'], 'No destructive' ), 'retention-only uninstall policy missing' );
arch_assert( false !== strpos( $files['tools/build-release.py'], 'FORBIDDEN_PACKAGE_DIRS' ), 'production-package development-path guard missing' );
arch_assert( false !== strpos( $files['tools/build-release.py'], 'PACKAGE_DIRS = {"includes", "assets"}' ), 'production-package runtime allowlist missing' );

fwrite( STDOUT, "File 04 architecture checks passed.\n" );
