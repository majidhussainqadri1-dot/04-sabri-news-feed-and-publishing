<?php
require __DIR__ . '/bootstrap.php';
if ( ! defined( 'SNFLA_VERSION' ) ) { define( 'SNFLA_VERSION', '1.3.0' ); }
require_once dirname( __DIR__ ) . '/includes/class-snfla-central-plan.php';

$failures = array();
function cp_check( $condition, $message ) {
	global $failures;
	if ( ! $condition ) { $failures[] = $message; }
}

$expected_cv = array();
foreach ( array( array( 37, 49 ), array( 74, 84 ), array( 239, 285 ) ) as $range ) {
	for ( $i = $range[0]; $i <= $range[1]; $i++ ) { $expected_cv[] = sprintf( 'CV-%03d', $i ); }
}
$expected_aj = array( 'AJ-07', 'AJ-10', 'AJ-24', 'AJ-25', 'AJ-28', 'AJ-31', 'AJ-32', 'AJ-33', 'AJ-34', 'AJ-35', 'AJ-36', 'AJ-37', 'AJ-38', 'AJ-39', 'AJ-40' );

cp_check( 71 === count( $expected_cv ), 'Test fixture must contain the exact 71 File 04 central-plan CV requirements.' );
cp_check( $expected_cv === SNFLA_Central_Plan::cv_ids(), 'Central-plan CV registry must match the authoritative File 04 ranges exactly.' );
cp_check( $expected_aj === SNFLA_Central_Plan::aj_ids(), 'Acceptance-journey registry must match the File 04 plan.' );
cp_check( array( 'F04-CEN-01', 'F04-CEN-02' ) === SNFLA_Central_Plan::cen_ids(), 'File-specific central requirements must remain complete.' );

$requirements = SNFLA_Central_Plan::requirements();
cp_check( 71 === count( $requirements ), 'Every applicable CV ID must have a traceability row.' );
foreach ( $expected_cv as $id ) {
	$row = isset( $requirements[ $id ] ) ? $requirements[ $id ] : array();
	cp_check( ! empty( $row['owner'] ), $id . ' must declare a canonical owner.' );
	cp_check( ! empty( $row['enforcement'] ), $id . ' must declare File 04 enforcement mode.' );
	cp_check( ! empty( $row['priority'] ), $id . ' must declare priority.' );
	cp_check( ! empty( $row['code_location'] ), $id . ' must declare code/evidence location.' );
	cp_check( ! empty( $row['contract'] ), $id . ' must declare a contract boundary.' );
	cp_check( isset( $row['test_id'] ) && 'CP-' . $id === $row['test_id'], $id . ' must have deterministic test trace.' );
}

$manifest = SNFLA_Central_Plan::module_manifest();
cp_check( '04' === $manifest['file_number'], 'Manifest must identify File 04.' );
cp_check( 'File 21' === $manifest['canonical_public_owner'], 'File 21 must remain the sole canonical public publication owner.' );
cp_check( 'File 26' === $manifest['canonical_search_owner'], 'File 26 must remain the canonical search/discovery owner.' );
cp_check( 'File 20' === $manifest['canonical_shell_owner'], 'File 20 must remain the shell owner.' );
cp_check( 'File 25' === $manifest['canonical_visual_owner'], 'File 25 must remain the visual-system owner.' );
cp_check( 'File 24' === $manifest['assurance_owner'], 'File 24 must remain the assurance owner without replacing native enforcement.' );
cp_check( 'forbidden' === $manifest['legacy_writes'], 'New File 04 legacy truth writes must remain forbidden.' );
cp_check( false !== strpos( $manifest['after_cutover'], 'read_only' ), 'Post-cutover lifecycle must become read-only before retirement.' );
cp_check( 71 === $manifest['applicable_cv_count'], 'Manifest must advertise the exact applicable central-plan count.' );

foreach ( range( 37, 49 ) as $n ) {
	$id = sprintf( 'CV-%03d', $n );
	cp_check( 'integration_regression_only' === $requirements[ $id ]['enforcement'], $id . ' must not create a duplicate File 21/File 26 backend.' );
}
foreach ( range( 74, 84 ) as $n ) {
	$id = sprintf( 'CV-%03d', $n );
	cp_check( 'integration_regression_only' === $requirements[ $id ]['enforcement'], $id . ' must not create a duplicate community backend.' );
}
foreach ( range( 262, 280 ) as $n ) {
	$id = sprintf( 'CV-%03d', $n );
	cp_check( 'native_assurance' === $requirements[ $id ]['enforcement'], $id . ' must remain a native File 04 assurance obligation.' );
}

$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-snfla-central-plan.php' );
cp_check( false === strpos( $source, 'wp_insert_post(' ), 'Central-plan reconciliation must never add direct publication writes.' );
cp_check( false === strpos( $source, '$wpdb->posts' ), 'Central-plan reconciliation must never write File 21 tables directly.' );
cp_check( false !== strpos( $source, 'sabri_file26_legacy_resolution_v1' ), 'A versioned File 26 legacy-resolution integration contract must be exposed.' );
cp_check( false !== strpos( $source, 'source_trace_complete' ), 'Truthful release-readiness separation must be implemented.' );

if ( $failures ) {
	fwrite( STDERR, "Central-plan checks failed:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

echo 'Central-plan reconciliation checks passed: 71 CV requirements, 2 F04-CEN requirements and 15 AJ journeys traced without duplicate ownership.' . PHP_EOL;
