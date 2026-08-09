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

	public static function strict_positive_ids( array $ids, $limit = 100 ) {
		if ( ! is_int( $limit ) || $limit < 1 || count( $ids ) < 1 || count( $ids ) > $limit ) { return new WP_Error( 'snfla_invalid_id_batch' ); }
		$out=array(); $seen=array();
		foreach ( $ids as $raw ) {
			if ( is_int( $raw ) ) { $id=$raw; }
			elseif ( is_string( $raw ) && 1 === preg_match( '/^[1-9][0-9]*$/D', $raw ) ) { $id=(int)$raw; if ( $id<=0 || (string)$id !== $raw ) { return new WP_Error( 'snfla_invalid_id_batch' ); } }
			else { return new WP_Error( 'snfla_invalid_id_batch' ); }
			if ( $id<=0 || isset($seen[$id]) ) { return new WP_Error( 'snfla_invalid_id_batch' ); }
			$seen[$id]=true; $out[]=$id;
		}
		return $out;
	}

	public static function normalized_ids( array $ids, $limit = 100 ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		sort( $ids, SORT_NUMERIC );
		return array_slice( $ids, 0, max( 1, absint( $limit ) ) );
	}

	private static function strict_checkpoint_ids( array $ids, $limit = 100 ) {
		$limit = absint( $limit );
		if ( $limit < 1 || count( $ids ) > $limit ) {
			return null;
		}
		$normalized = array();
		$seen = array();
		foreach ( $ids as $id ) {
			if ( is_int( $id ) ) {
				$value = $id;
			} elseif ( is_string( $id ) && 1 === preg_match( '/^[1-9][0-9]*$/D', $id ) ) {
				$value = (int) $id;
				if ( $value <= 0 || (string) $value !== $id ) {
					return null;
				}
			} else {
				return null;
			}
			if ( $value <= 0 || isset( $seen[ $value ] ) ) {
				return null;
			}
			$seen[ $value ] = true;
			$normalized[] = $value;
		}
		sort( $normalized, SORT_NUMERIC );
		return $normalized;
	}

	public static function checkpoint_matches( $run, array $legacy_ids, $source_signature ) {
		if ( ! is_array( $run ) ) {
			return false;
		}
		$checkpoint = isset( $run['checkpoint'] ) && is_array( $run['checkpoint'] ) ? $run['checkpoint'] : array();
		$stored_ids = self::strict_checkpoint_ids( (array) ( $checkpoint['legacy_ids'] ?? array() ) );
		$requested_ids = self::strict_checkpoint_ids( $legacy_ids );
		if ( null === $stored_ids || null === $requested_ids ) {
			return false;
		}
		$stored_signature = strtolower( (string) ( $run['source_signature'] ?? '' ) );
		$source_signature = strtolower( (string) $source_signature );
		return 1 === preg_match( '/^[a-f0-9]{64}$/D', $stored_signature )
			&& 1 === preg_match( '/^[a-f0-9]{64}$/D', $source_signature )
			&& hash_equals( $stored_signature, $source_signature )
			&& $stored_ids === $requested_ids;
	}

	public static function run_is_stale( $run ) {
		if ( ! is_array( $run ) || 'running' !== ( $run['status'] ?? '' ) ) { return false; }
		if ( empty( $run['started_at'] ) ) { return true; }
		$started = strtotime( (string) $run['started_at'] . ' UTC' );
		return false === $started || $started < time() - self::STALE_RUN_SECONDS || $started > time() + 300;
	}
}
