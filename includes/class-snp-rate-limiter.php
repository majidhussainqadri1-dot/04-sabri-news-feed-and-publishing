<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Rate_Limiter {
	public static function allowed( $bucket, $subject, $limit, $window_seconds ) {
		global $wpdb;
		$bucket         = sanitize_key( $bucket );
		$limit          = max( 1, absint( $limit ) );
		$window_seconds = max( 60, absint( $window_seconds ) );
		$window_start   = (int) floor( time() / $window_seconds ) * $window_seconds;
		$key_hash       = hash_hmac( 'sha256', $bucket . '|' . (string) $subject, wp_salt( 'nonce' ) );
		$table          = $wpdb->prefix . 'snp_rate_limits';

		$sql = $wpdb->prepare(
			"INSERT INTO {$table} (key_hash,bucket,window_start,hits,updated_at)
			 VALUES (%s,%s,%d,1,%s)
			 ON DUPLICATE KEY UPDATE hits = hits + 1, updated_at = VALUES(updated_at)",
			$key_hash,
			$bucket,
			$window_start,
			current_time( 'mysql', true )
		);
		$result = $wpdb->query( $sql );
		if ( false === $result ) {
			return false;
		}
		$hits = absint(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT hits FROM {$table} WHERE key_hash = %s AND bucket = %s AND window_start = %d",
					$key_hash,
					$bucket,
					$window_start
				)
			)
		);
		return $hits <= $limit;
	}

	public static function cleanup() {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - 2 * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}snp_rate_limits WHERE updated_at < %s", $cutoff ) );
	}
}
