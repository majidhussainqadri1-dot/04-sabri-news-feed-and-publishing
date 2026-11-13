<?php
/** Isolated CLI-only fixture: no live WordPress, database or external network. */
require __DIR__ . '/bootstrap.php';
$scenario = (string) ( $argv[1] ?? 'healthy' );
$GLOBALS['redirect_scenario'] = $scenario;
$GLOBALS['redirect_status'] = 0;
$GLOBALS['redirect_url'] = '';
$GLOBALS['redirect_code'] = 0;
register_shutdown_function( static function () {
    echo json_encode(
        array(
            'scenario' => $GLOBALS['redirect_scenario'],
            'status'   => (int) $GLOBALS['redirect_status'],
            'redirect' => (string) $GLOBALS['redirect_url'],
            'code'     => (int) $GLOBALS['redirect_code'],
        ),
        JSON_UNESCAPED_SLASHES
    ), "\n";
} );
class SNFLA_Inventory { const LEGACY_POST_TYPE = 'snp_publication'; }
class SNFLA_Mapping {
    public static function get_checked( $id ) {
        unset( $id );
        if ( 'mapping_error' === $GLOBALS['redirect_scenario'] ) {
            return new WP_Error( 'snfla_mapping_unavailable' );
        }
        if ( 'missing_mapping' === $GLOBALS['redirect_scenario'] ) {
            return array();
        }
        $status = in_array( $GLOBALS['redirect_scenario'], array( 'conflict_status', 'interaction_pending' ), true ) ? $GLOBALS['redirect_scenario'] : 'migrated';
        if ( 'conflict_status' === $status ) { $status = 'conflict'; }
        return array(
            'status'          => $status,
            'target_id'       => 'target_mismatch' === $GLOBALS['redirect_scenario'] ? 1000 : 999,
            'source_checksum' => 'empty_source_digest' === $GLOBALS['redirect_scenario'] ? '' : str_repeat( 'a', 64 ),
            'target_checksum' => 'empty_target_digest' === $GLOBALS['redirect_scenario'] ? '' : str_repeat( 'b', 64 ),
        );
    }
    public static function open_conflict_codes( $id ) {
        unset( $id );
        return in_array( $GLOBALS['redirect_scenario'], array( 'open_conflict', 'conflict_ledger_failed' ), true )
            ? array( 'conflict_ledger_failed' === $GLOBALS['redirect_scenario'] ? 'conflict_ledger_read_failed' : 'canonical_target_invalid' )
            : array();
    }
}
class SNFLA_Capabilities { public static function file21_ready() { return true; } }
class SNFLA_File21_Adapter {
    public static function target_for( $id ) { unset( $id ); return 999; }
    public static function target_public( $id ) { unset( $id ); return 'private_target' !== $GLOBALS['redirect_scenario']; }
    public static function migration_target_valid( $id, $target ) {
        return $id === 123 && $target === 999 && 'invalid_provenance' !== $GLOBALS['redirect_scenario'];
    }
}
function is_singular( $type ) { return 'snp_publication' === $type; }
function get_queried_object_id() { return 123; }
function get_permalink( $id ) {
    if ( 999 === $id && 'self_loop' !== $GLOBALS['redirect_scenario'] ) { return 'https://test.local/new/999/'; }
    return 'https://test.local/old/123/';
}
function home_url( $path ) { unset( $path ); return 'https://test.local/'; }
function wp_http_validate_url( $url ) { return is_string( $url ) && '' !== $url; }
function wp_parse_url( $url ) { return parse_url( $url ); }
function untrailingslashit( $path ) { return rtrim( $path, '/' ); }
function wp_safe_redirect( $url, $code, $by ) {
    unset( $by );
    if ( 'redirect_rejected' === $GLOBALS['redirect_scenario'] ) { return false; }
    $GLOBALS['redirect_url'] = $url;
    $GLOBALS['redirect_code'] = $code;
    return true;
}
function status_header( $status ) { $GLOBALS['redirect_status'] = $status; }
function nocache_headers() {}
function get_404_template() { return false; }
$snfla_test_options[ SNFLA_Schema::STATE_OPTION ] = 'redirect_cutover';
require dirname( __DIR__ ) . '/includes/class-snfla-redirects.php';
SNFLA_Redirects::handle();
