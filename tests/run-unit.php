<?php
require __DIR__ . '/bootstrap.php';
require dirname( __DIR__ ) . '/includes/class-snfla-checksum.php';
require dirname( __DIR__ ) . '/includes/class-snfla-schema.php';
require dirname( __DIR__ ) . '/includes/class-snfla-retirement.php';

function snfla_assert( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}

$a = array( 'z' => 1, 'a' => array( 'b' => 2, 'a' => 3 ) );
$b = array( 'a' => array( 'a' => 3, 'b' => 2 ), 'z' => 1 );
snfla_assert( SNFLA_Checksum::hash( $a ) === SNFLA_Checksum::hash( $b ), 'canonical hash must ignore associative key order' );
snfla_assert( SNFLA_Checksum::hash( $a ) !== SNFLA_Checksum::hash( array( 'z' => 2, 'a' => array( 'b' => 2, 'a' => 3 ) ) ), 'canonical hash must detect material change' );

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

update_option( SNFLA_Schema::STATE_OPTION, 'redirect_cutover' );
update_option( SNFLA_Schema::STATE_VERSION_OPTION, 9 );
$recovery = SNFLA_Schema::recover_to_batch( 'redirect_cutover', 9, 7, array( 'reason' => 'test' ) );
snfla_assert( is_array( $recovery ) && 'batch_migration' === $recovery['state'] && 10 === $recovery['version'], 'rollback recovery transition failed' );

snfla_assert( 'RETIRE FILE 04 LEGACY ADAPTER' === SNFLA_Retirement::CONFIRMATION, 'retirement confirmation phrase changed' );
snfla_assert( count( SNFLA_Audit::$events ) >= 3, 'lifecycle actions must emit audit evidence' );

fwrite( STDOUT, "File 04 unit checks passed.\n" );
