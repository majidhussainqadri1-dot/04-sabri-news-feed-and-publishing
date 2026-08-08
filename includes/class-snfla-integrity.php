<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Integrity {
	const EVIDENCE_FIELD = 'evidence_hmac';
	const STALE_RUN_SECONDS = 900;

	public static function report_checksum_valid( $report ) {
		if ( ! is_array( $report ) || empty( $report['report_checksum'] ) || ! preg_match( '/^[a-f0-9]{64}$/', (string) $report['report_checksum'] ) ) {
			return false;
		}
		$expected = (string) $report['report_checksum'];
		unset( $report['report_checksum'] );
		return hash_equals( $expected, SNFLA_Checksum::hash( $report ) );
	}

	public static function sign_evidence( array $evidence ) {
		unset( $evidence[ self::EVIDENCE_FIELD ] );
		$evidence[ self::EVIDENCE_FIELD ] = hash_hmac(
			'sha256',
			SNFLA_Checksum::encode( $evidence ),
			wp_salt( 'auth' )
		);
		return $evidence;
	}

	public static function evidence_valid( $evidence ) {
		if ( ! is_array( $evidence ) || empty( $evidence[ self::EVIDENCE_FIELD ] ) || ! preg_match( '/^[a-f0-9]{64}$/', (string) $evidence[ self::EVIDENCE_FIELD ] ) ) {
			return false;
		}
		$expected = (string) $evidence[ self::EVIDENCE_FIELD ];
		unset( $evidence[ self::EVIDENCE_FIELD ] );
		$actual = hash_hmac(
			'sha256',
			SNFLA_Checksum::encode( $evidence ),
			wp_salt( 'auth' )
		);
		return hash_equals( $expected, $actual );
	}

	public static function normalized_ids( array $ids, $limit = 100 ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		sort( $ids, SORT_NUMERIC );
		return array_slice( $ids, 0, max( 1, absint( $limit ) ) );
	}

	public static function checkpoint_matches( $run, array $legacy_ids, $source_signature ) {
		if ( ! is_array( $run ) ) {
			return false;
		}
		$checkpoint = isset( $run['checkpoint'] ) && is_array( $run['checkpoint'] ) ? $run['checkpoint'] : array();
		$stored_ids = self::normalized_ids( (array) ( $checkpoint['legacy_ids'] ?? array() ) );
		$stored_signature = strtolower( (string) ( $run['source_signature'] ?? '' ) );
		$source_signature = strtolower( (string) $source_signature );
		return 1 === preg_match( '/^[a-f0-9]{64}$/D', $stored_signature )
			&& 1 === preg_match( '/^[a-f0-9]{64}$/D', $source_signature )
			&& hash_equals( $stored_signature, $source_signature )
			&& $stored_ids === self::normalized_ids( $legacy_ids );
	}

	public static function run_is_stale( $run ) {
		if ( ! is_array( $run ) || 'running' !== ( $run['status'] ?? '' ) ) { return false; }
		if ( empty( $run['started_at'] ) ) { return true; }
		$started = strtotime( (string) $run['started_at'] . ' UTC' );
		return false === $started || $started < time() - self::STALE_RUN_SECONDS || $started > time() + 300;
	}
}
