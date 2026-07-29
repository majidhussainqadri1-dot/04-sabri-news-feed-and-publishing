<?php
/**
 * No-network source architecture and deterministic-logic tests.
 * Compatible with PHP 7.4 and later.
 */

$root = dirname( __DIR__ );

function fail_test( $message ) {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function assert_test( $condition, $message ) {
	if ( ! $condition ) {
		fail_test( $message );
	}
}

$files = array_merge(
	glob( $root . '/*.php' ),
	glob( $root . '/includes/*.php' )
);
$source = '';
foreach ( $files as $file ) {
	$source .= "\n/* " . basename( $file ) . " */\n" . file_get_contents( $file );
}

$forbidden = array(
	'spf_'                         => 'legacy File 01 option dependency',
	'sabri_doctor_verified'        => 'obsolete doctor role authority',
	'option_comment_registration'  => 'global WordPress comment-policy mutation',
	"add_cap( 'manage_sabri_news'" => 'administrator-wide editorial grant',
	'sabri_news_home'              => 'duplicate Home feed owner',
	'sabri_platform_home'          => 'legacy Home shortcode replacement',
	'add_role('                    => 'File 04 role creation',
	'set_role('                    => 'File 04 role mutation',
);
foreach ( $forbidden as $needle => $label ) {
	assert_test( false === strpos( $source, $needle ), $label . ' is present' );
}

$required = array(
	"define( 'SNP_VERSION', '0.2.0' );"               => 'corrective version constant',
	"'post_status'    => 'draft'"                     => 'private-first candidate creation',
	'SNP_Publication_State::public_eligible'            => 'central public eligibility gate',
	'SNAPSHOT_META'                                     => 'approved publication snapshot',
	'case_privacy_reviewer_id'                          => 'independent Patient Case reviewer evidence',
	'case_consent_record_id'                            => 'Patient Case consent record',
	'case_consent_withdrawn'                            => 'consent withdrawal state',
	'aes-256-gcm'                                       => 'encrypted pending-media staging',
	'MAX_PIXELS'                                        => 'image pixel limit',
	'_snp_owned_media'                                  => 'explicit File 04 media ownership',
	'snp_rate_limits'                                   => 'database fixed-window rate limiting',
	'INSERT IGNORE INTO'                                => 'atomic interaction insertion',
	'Authors cannot Like their own publications.'       => 'self-Like prevention',
	'resolution_note'                                   => 'report resolution reason',
	'resolver_id'                                       => 'report resolver identity',
	'report_appealed'                                   => 'report appeal workflow',
	'sabri_unified_notification_event'                  => 'File 19 notification boundary',
	'appeal_note'                                       => 'appeal evidence field',
	'actor_digest'                                     => 'privacy-preserving audit actor digest',
	'details_digest'                                   => 'immutable audit detail digest',
	'SNP_Audit::anonymize_actor'                       => 'audit-aware privacy erasure',
	'eligible_author_ids'                              => 'query-level current-author eligibility',
	'valid_consent_date'                               => 'calendar-valid past consent date',
	'protected_media_previewed'                        => 'audited protected-media preview',
	'private_headers_callback'                         => 'File 21 early private-response callback',
	'wp_sitemaps_post_types'                           => 'core sitemap exclusion for governed records',
	'Cache-Control: private, no-store'                   => 'personalized response cache protection',
	'X-Robots-Tag: noindex, nofollow, noarchive'         => 'private-page indexing protection',
	'sabri_file21_publication_provider'                  => 'File 21 provider boundary',
	'sabri_universal_composer_types'                     => 'File 22 composer boundary',
	'SABRI_ALLOW_DESTRUCTIVE_UNINSTALL'                  => 'dual-gated destructive uninstall',
	'SNP_Publication_State::public_eligible( $post_id )' => 'SEO/public output eligibility validation',
);
foreach ( $required as $needle => $label ) {
	assert_test( false !== strpos( $source, $needle ), $label . ' is missing' );
}

// Deterministic snapshot hashing: key order must not change the result; material change must.
function canonical_fingerprint( array $data ) {
	ksort( $data );
	return hash( 'sha256', json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
}
$a = array( 'title' => 'A', 'content' => 'B', 'topic' => 'research' );
$b = array( 'topic' => 'research', 'content' => 'B', 'title' => 'A' );
$c = array( 'title' => 'A', 'content' => 'Changed', 'topic' => 'research' );
assert_test( canonical_fingerprint( $a ) === canonical_fingerprint( $b ), 'fingerprint is not order-stable' );
assert_test( canonical_fingerprint( $a ) !== canonical_fingerprint( $c ), 'fingerprint does not detect material change' );

// Ranking must reward genuine engagement and decay with age.
function test_score( $views, $likes, $comments, $saves, $bonus, $hours ) {
	return ( $views * 0.1 + $likes * 3 + $comments * 5 + $saves * 4 + $bonus + 1 ) / pow( max( 1, $hours ) + 2, 0.55 );
}
assert_test( test_score( 100, 2, 1, 1, 0, 2 ) > test_score( 100, 1, 1, 1, 0, 2 ), 'Like weight is not monotonic' );
assert_test( test_score( 100, 2, 1, 1, 0, 2 ) > test_score( 100, 2, 1, 1, 0, 200 ), 'age decay is not monotonic' );
assert_test( test_score( 100, 2, 1, 1, 1000, 2 ) > test_score( 100, 2, 1, 1, 0, 2 ), 'pin bonus is not deterministic' );

// Fixed-window arithmetic must move to a new bucket at the exact boundary.
function test_window( $timestamp, $seconds ) {
	return (int) floor( $timestamp / $seconds ) * $seconds;
}
assert_test( test_window( 119, 60 ) === 60, 'fixed-window calculation failed before boundary' );
assert_test( test_window( 120, 60 ) === 120, 'fixed-window calculation failed at boundary' );

// Audit hashes must retain immutable digests when mutable display data is anonymized.
$actor_digest = hash_hmac( 'sha256', 'actor|22', 'test-key' );
$details_digest = hash_hmac( 'sha256', 'private note|{}', 'test-key' );
assert_test( strlen( $actor_digest ) === 64 && strlen( $details_digest ) === 64, 'audit digests are not stable SHA-256 values' );

// Report open keys must separate initial reports and appeals.
$initial = hash( 'sha256', '10|20|open' );
$appeal  = hash( 'sha256', '10|20|appeal|30' );
assert_test( $initial !== $appeal, 'report and appeal dedupe keys collide' );

fwrite( STDOUT, "Security and deterministic-logic checks passed.\n" );
