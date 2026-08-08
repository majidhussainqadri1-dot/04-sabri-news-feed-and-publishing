<?php
require __DIR__ . '/bootstrap.php';

$failures = array();
function check( $condition, $message ) {
    global $failures;
    if ( ! $condition ) { $failures[] = $message; }
}

$a = array( 'z' => 1, 'a' => array( 'b' => 2, 'a' => 1 ) );
$b = array( 'a' => array( 'a' => 1, 'b' => 2 ), 'z' => 1 );
check( SNFLA_Checksum::hash( $a ) === SNFLA_Checksum::hash( $b ), 'Canonical hashes must ignore associative key order.' );
check( SNFLA_Checksum::encode( array( 'x' => INF, 'y' => NAN ) ) !== false, 'Non-finite floats must canonicalize into valid JSON.' );
$obj = new stdClass(); $obj->b = 2; $obj->a = 1;
$canonical_obj = SNFLA_Checksum::canonicalize( $obj );
check( isset( $canonical_obj['__class'], $canonical_obj['__properties'] ) && 'stdClass' === $canonical_obj['__class'] && $canonical_obj['__properties'] === array( 'a' => 1, 'b' => 2 ), 'Objects must canonicalize deterministically.' );
check( SNFLA_Integrity::normalized_ids( array( 4, 2, 4, -1, 0, 3 ), 3 ) === array( 1, 2, 3 ), 'IDs must be positive, unique, sorted and bounded.' );

$report = array( 'green' => true, 'value' => 7 );
$report['report_checksum'] = SNFLA_Checksum::hash( $report );
check( SNFLA_Integrity::report_checksum_valid( $report ), 'Report checksum must validate.' );
$report['value'] = 8;
check( ! SNFLA_Integrity::report_checksum_valid( $report ), 'Tampered report checksum must fail.' );

$evidence = SNFLA_Integrity::sign_evidence( array( 'source_signature' => str_repeat( 'a', 64 ), 'verified' => true ) );
check( SNFLA_Integrity::evidence_valid( $evidence ), 'Signed evidence must validate.' );
$evidence['verified'] = false;
check( ! SNFLA_Integrity::evidence_valid( $evidence ), 'Tampered evidence must fail.' );

check( in_array( 'inventory_locked', SNFLA_Schema::transitions()['legacy_active'], true ), 'Activated state must permit inventory lock.' );
check( ! in_array( 'retired', SNFLA_Schema::transitions()['legacy_active'], true ), 'Retirement must not bypass lifecycle gates.' );
$states = SNFLA_Schema::states();
check( 'legacy_active' === $states[0] && 'retired' === $states[ count( $states ) - 1 ], 'Lifecycle boundaries must remain ordered.' );

$redacted = SNFLA_Audit::redact( array( 'password' => 'secret', 'token_value' => 'secret', 'safe' => 'ok', 'nested' => array( 'email' => 'person@example.com' ) ) );
check( '[redacted]' === $redacted['password'] && '[redacted]' === $redacted['token_value'], 'Sensitive keys must be redacted.' );
check( 'ok' === $redacted['safe'], 'Non-sensitive data must remain available.' );
check( SNFLA_Audit::actor_digest( 42 ) === SNFLA_Audit::actor_digest( 42 ), 'Actor digest must be stable.' );
check( SNFLA_Audit::actor_digest( 42 ) !== SNFLA_Audit::actor_digest( 43 ), 'Actor digest must separate identities.' );

if ( $failures ) {
    fwrite( STDERR, "Unit checks failed:\n- " . implode( "\n- ", $failures ) . "\n" );
    exit( 1 );
}
echo "Deterministic unit checks passed (" . 16 . " assertions).\n";
