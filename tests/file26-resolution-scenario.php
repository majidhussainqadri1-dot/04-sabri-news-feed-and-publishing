<?php
/** Standalone test fixture for the real File04 -> File26 resolver; never contacts a site. */
require __DIR__ . '/bootstrap.php';
$scenario = (string) ( $argv[1] ?? 'healthy' );
$GLOBALS['file26_scenario'] = $scenario;
$snfla_test_options[ SNFLA_Schema::STATE_OPTION ] = 'pre_cutover' === $scenario ? 'batch_migration' : 'redirect_cutover';
class SNFLA_Capabilities {
    public static function file21_ready() { return 'file21_unavailable' !== $GLOBALS['file26_scenario']; }
}
class SNFLA_File21_Adapter {
    public static function target_for( $id ) {
        unset( $id );
        return 'target_missing' === $GLOBALS['file26_scenario'] ? 0 : 999;
    }
    public static function migration_target_valid( $id, $target ) {
        return 123 === $id && 999 === $target && 'invalid_provenance' !== $GLOBALS['file26_scenario'];
    }
    public static function target_public( $id ) {
        unset( $id );
        return 'private_target' !== $GLOBALS['file26_scenario'];
    }
}
class SNFLA_Mapping {
    public static function get_checked( $id ) {
        unset( $id );
        if ( 'mapping_error' === $GLOBALS['file26_scenario'] ) { return new WP_Error( 'snfla_mapping_unavailable' ); }
        if ( 'missing_mapping' === $GLOBALS['file26_scenario'] ) { return array(); }
        $status = in_array( $GLOBALS['file26_scenario'], array( 'conflict_status', 'interaction_pending' ), true )
            ? ( 'conflict_status' === $GLOBALS['file26_scenario'] ? 'conflict' : 'interaction_pending' )
            : 'migrated';
        return array(
            'status' => $status,
            'target_id' => 'target_mismatch' === $GLOBALS['file26_scenario'] ? 1000 : 999,
            'source_checksum' => 'source_digest_missing' === $GLOBALS['file26_scenario'] ? '' : str_repeat( 'a', 64 ),
            'target_checksum' => 'target_digest_missing' === $GLOBALS['file26_scenario'] ? '' : str_repeat( 'b', 64 ),
        );
    }
    public static function open_conflict_codes( $id ) {
        unset( $id );
        if ( 'open_conflict' === $GLOBALS['file26_scenario'] ) { return array( 'canonical_target_invalid' ); }
        if ( 'conflict_ledger_failed' === $GLOBALS['file26_scenario'] ) { return array( 'conflict_ledger_read_failed' ); }
        return array();
    }
}
function get_permalink( $id ) { return 'https://test.local/new/' . (int) $id . '/'; }
function esc_url_raw( $url ) { return $url; }
require dirname( __DIR__ ) . '/includes/class-snfla-redirects.php';
require dirname( __DIR__ ) . '/includes/class-snfla-central-plan.php';
$existing = 'existing_projection' === $scenario ? array( 'trusted' => 'upstream' ) : null;
$result = SNFLA_Central_Plan::file26_resolution( $existing, 123 );
echo wp_json_encode( array( 'scenario' => $scenario, 'result' => $result ) ), "\n";
