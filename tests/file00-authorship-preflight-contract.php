<?php
/**
 * File 04 consumer contract: accept only File 00's exact placeholder provider
 * and a UUID that reverse-resolves to the same File 00-owned user.
 * Loaded by tests/run-unit.php after tests/bootstrap.php.
 */
$GLOBALS['f04_posts'] = array( 501 => 77, 502 => 999 );
$GLOBALS['f04_users'] = array( 77 => true, 88 => true );
$GLOBALS['f04_uuid'] = '00000000-0000-4000-8000-000000000077';
$GLOBALS['f04_subjects'] = array('00000000-0000-4000-8000-000000000077'=>77,'00000000-0000-4000-8000-000000000088'=>88);
$GLOBALS['f04_placeholder_users'] = array(88=>true);
$GLOBALS['f04_resolved'] = 77;
$GLOBALS['f04_placeholder'] = array();
function get_post( $id ) {
    $post = new WP_Post();
    $post->post_author = $GLOBALS['f04_posts'][(int) $id] ?? 0;
    return $post;
}
function get_userdata( $id ) {
    return ! empty( $GLOBALS['f04_users'][(int) $id] ) ? (object) array( 'ID' => (int) $id ) : false;
}
function smc_get_user_id_by_subject_uuid( $uuid ) { return is_string( $uuid ) ? (int) ( $GLOBALS['f04_subjects'][$uuid] ?? 0 ) : 0; }
function smc_is_legacy_author_placeholder( $id ) { return ! empty( $GLOBALS['f04_placeholder_users'][(int) $id] ); }
function apply_filters( $tag, $value, ...$args ) {
    if ( 'sabri_file00_platform_uuid_v1' === $tag ) { return $GLOBALS['f04_uuid']; }
    if ( 'sabri_file00_legacy_author_placeholder_v1' === $tag ) { return $GLOBALS['f04_placeholder']; }
    return $value;
}
require_once dirname( __DIR__ ) . '/includes/class-snfla-plan-completion.php';
$f04_cases = array();
$f04_check = static function( $name, $condition ) use ( &$f04_cases ) { $f04_cases[] = array( $name, (bool) $condition ); };
$normal = SNFLA_Plan_Completion::authorship_preflight( 501 );
$f04_check( 'canonical existing author UUID maps back to the same user', is_array( $normal ) && 77 === (int) ( $normal['user_id'] ?? 0 ) );
$GLOBALS['f04_subjects']['00000000-0000-4000-8000-000000000077'] = 88;
$normal_wrong = SNFLA_Plan_Completion::authorship_preflight( 501 );
$f04_check( 'valid-looking UUID for a different user is rejected', is_wp_error( $normal_wrong ) );
$GLOBALS['f04_subjects']['00000000-0000-4000-8000-000000000077'] = 77;
$GLOBALS['f04_posts'][502] = 999;
$request = array( 'file_number' => '04', 'legacy_id' => 502, 'legacy_author_id' => 999 );
$digest = SNFLA_Checksum::hash( $request );
$GLOBALS['f04_placeholder'] = array(
    'verified' => true,
    'provider_id' => 'file00_legacy_author_placeholder_v1',
    'user_id' => 88,
    'platform_uuid' => '00000000-0000-4000-8000-000000000088',
    'legacy_id' => 502,
    'legacy_author_id' => 999,
    'request_digest' => $digest,
    'attribution_class' => 'unknown_or_deleted_legacy_author',
);
$placeholder = SNFLA_Plan_Completion::authorship_preflight( 502 );
$f04_check( 'exact File 00 placeholder claim and reverse mapping accepted', is_array( $placeholder ) && ! empty( $placeholder['placeholder'] ) );
$GLOBALS['f04_placeholder']['provider_id'] = 'third_party';
$spoof = SNFLA_Plan_Completion::authorship_preflight( 502 );
$f04_check( 'non-File00 placeholder provider is rejected', is_wp_error( $spoof ) );
$GLOBALS['f04_placeholder']['provider_id'] = 'file00_legacy_author_placeholder_v1';
$GLOBALS['f04_subjects']['00000000-0000-4000-8000-000000000088'] = 77;
$wrong_placeholder_owner = SNFLA_Plan_Completion::authorship_preflight( 502 );
$f04_check( 'placeholder UUID resolving to another user is rejected', is_wp_error( $wrong_placeholder_owner ) );
$GLOBALS['f04_subjects']['00000000-0000-4000-8000-000000000088'] = 88;
$GLOBALS['f04_placeholder_users'][88] = false;
$not_placeholder = SNFLA_Plan_Completion::authorship_preflight( 502 );
$f04_check( 'ordinary account cannot impersonate the File00 placeholder', is_wp_error( $not_placeholder ) );
$GLOBALS['f04_placeholder_users'][88] = true;
$GLOBALS['f04_placeholder']['request_digest'] = str_repeat( '0', 64 );
$bad_digest = SNFLA_Plan_Completion::authorship_preflight( 502 );
$f04_check( 'mismatched request digest is rejected', is_wp_error( $bad_digest ) );
$failed = array_values( array_filter( $f04_cases, static function( $case ) { return ! $case[1]; } ) );
if ( $failed ) {
    foreach ( $failed as $case ) { fwrite( STDERR, 'File04 authorship FAIL: ' . $case[0] . "\n" ); }
    exit( 1 );
}
echo 'File04 File00 authorship consumer contract: ' . count( $f04_cases ) . '/' . count( $f04_cases ) . " PASS\n";
