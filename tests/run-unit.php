<?php
require __DIR__ . '/bootstrap.php';
require dirname( __DIR__ ) . '/includes/class-snfla-checksum.php';
require dirname( __DIR__ ) . '/includes/class-snfla-integrity.php';
require dirname( __DIR__ ) . '/includes/class-snfla-schema.php';
require dirname( __DIR__ ) . '/includes/class-snfla-retirement.php';

function snfla_assert( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}

$a = array( 'z' => 1, 'a' => array( 'b' => 2, 'a' => 3 ) );
$b = array( 'a' => array( 'a' => 3, 'b' => 2 ), 'z' => 1 );
snfla_assert( SNFLA_Checksum::hash( $a ) === SNFLA_Checksum::hash( $b ), 'canonical hash must ignore associative key order' );
snfla_assert( SNFLA_Checksum::hash( $a ) !== SNFLA_Checksum::hash( array( 'z' => 2, 'a' => array( 'b' => 2, 'a' => 3 ) ) ), 'canonical hash must detect material change' );

$report = array( 'schema' => 2, 'source_signature' => str_repeat( 'a', 64 ), 'candidates' => array( array( 'legacy_id' => 1 ) ) );
$report['report_checksum'] = SNFLA_Checksum::hash( $report );
snfla_assert( SNFLA_Integrity::report_checksum_valid( $report ), 'untampered report checksum must validate' );
$tampered = $report; $tampered['candidates'][0]['legacy_id'] = 2;
snfla_assert( ! SNFLA_Integrity::report_checksum_valid( $tampered ), 'tampered dry-run/reconciliation report must fail' );

$proof = SNFLA_Integrity::sign_evidence( array( 'reference' => 'backup-1', 'checksum' => str_repeat( 'b', 64 ) ) );
snfla_assert( SNFLA_Integrity::evidence_valid( $proof ), 'signed evidence must validate' );
$proof['reference'] = 'backup-2';
snfla_assert( ! SNFLA_Integrity::evidence_valid( $proof ), 'tampered evidence must fail' );

$run = array( 'source_signature' => str_repeat( 'c', 64 ), 'checkpoint' => array( 'legacy_ids' => array( 3, 1, 2 ) ), 'status' => 'completed' );
snfla_assert( SNFLA_Integrity::checkpoint_matches( $run, array( 2, 3, 1 ), str_repeat( 'c', 64 ) ), 'idempotency checkpoint must normalize IDs' );
snfla_assert( ! SNFLA_Integrity::checkpoint_matches( $run, array( 1, 2, 4 ), str_repeat( 'c', 64 ) ), 'idempotency key reuse for another batch must fail' );
$stale_run = array( 'status' => 'running', 'started_at' => gmdate( 'Y-m-d H:i:s', time() - SNFLA_Integrity::STALE_RUN_SECONDS - 5 ) );
snfla_assert( SNFLA_Integrity::run_is_stale( $stale_run ), 'stale running operation must be detected' );

update_option( SNFLA_Schema::STATE_OPTION, 'legacy_active' );
update_option( SNFLA_Schema::STATE_VERSION_OPTION, 1 );
$first = SNFLA_Schema::transition( 'inventory_locked', 'legacy_active', 1, 7 );
snfla_assert( is_array( $first ) && 'inventory_locked' === $first['state'] && 2 === $first['version'], 'first lifecycle transition failed' );
$self = SNFLA_Schema::transition( 'inventory_locked', 'inventory_locked', 2, 7 );
snfla_assert( is_array( $self ) && 3 === $self['version'], 'versioned inventory self-transition failed' );
$stale = SNFLA_Schema::transition( 'dry_run_ready', 'inventory_locked', 2, 7 );
snfla_assert( is_wp_error( $stale ) && 'snfla_state_conflict' === $stale->get_error_code(), 'stale lifecycle request must fail closed' );
$invalid = SNFLA_Schema::transition( 'retired', 'inventory_locked', 3, 7 );
snfla_assert( is_wp_error( $invalid ) && 'snfla_invalid_transition' === $invalid->get_error_code(), 'invalid lifecycle jump must fail closed' );

SNFLA_Audit::$fail = true;
$rolled_back = SNFLA_Schema::transition( 'dry_run_ready', 'inventory_locked', 3, 7 );
snfla_assert( is_wp_error( $rolled_back ) && 'snfla_lifecycle_audit_failed' === $rolled_back->get_error_code(), 'audit failure must roll back lifecycle transition' );
snfla_assert( 'inventory_locked' === SNFLA_Schema::state() && 3 === SNFLA_Schema::version(), 'lifecycle state must be restored after audit failure' );
SNFLA_Audit::$fail = false;

update_option( SNFLA_Schema::STATE_OPTION, 'redirect_cutover' );
update_option( SNFLA_Schema::STATE_VERSION_OPTION, 9 );
$recovery = SNFLA_Schema::recover_to_batch( 'redirect_cutover', 9, 7, array( 'reason' => 'test' ) );
snfla_assert( is_array( $recovery ) && 'batch_migration' === $recovery['state'] && 10 === $recovery['version'], 'rollback recovery transition failed' );


$valid_returned_proof = SNFLA_Integrity::sign_evidence( array( 'performed_at_utc' => gmdate( 'Y-m-d H:i:s' ), 'source_signature' => str_repeat( 'd', 64 ), 'non_destructive' => true ) );
snfla_assert( SNFLA_Integrity::evidence_valid( $valid_returned_proof ), 'rollback proof must remain valid after construction' );
$invalid_date = SNFLA_Integrity::sign_evidence( array( 'expires_at_utc' => 'not-a-date', 'read_only' => true ) );
snfla_assert( SNFLA_Integrity::evidence_valid( $invalid_date ), 'evidence HMAC test fixture must be valid before semantic date validation' );

snfla_assert( 'RETIRE FILE 04 LEGACY ADAPTER' === SNFLA_Retirement::CONFIRMATION, 'retirement confirmation phrase changed' );
snfla_assert( count( SNFLA_Audit::$events ) >= 3, 'lifecycle actions must emit audit evidence' );

fwrite( STDOUT, "File 04 unit checks passed.\n" );
