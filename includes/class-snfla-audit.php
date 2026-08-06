<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Audit {
	public static function actor_digest( $actor_id ) {
		return hash_hmac( 'sha256', 'file04-actor|' . absint( $actor_id ), wp_salt( 'auth' ) );
	}

	public static function record( $action, $actor_id, $context = array(), $object_ref = '' ) {
		global $wpdb;
		$t       = SNFLA_Database::tables();
		$action  = sanitize_key( $action );
		$context = self::redact( is_array( $context ) ? $context : array() );
		$created = gmdate( 'Y-m-d H:i:s' );
		$uuid    = wp_generate_uuid4();
		$result  = false;

		if ( ! SNFLA_Database::acquire_lock( 'audit_chain', 5 ) ) {
			return false;
		}
		try {
			$wpdb->last_error = '';
			$previous = (string) $wpdb->get_var( "SELECT event_hash FROM {$t['audit']} ORDER BY id DESC LIMIT 1" );
			if ( ! empty( $wpdb->last_error ) ) { return false; }
			$payload  = array(
				'event_uuid'    => $uuid,
				'actor_digest'  => self::actor_digest( $actor_id ),
				'action'        => $action,
				'object_ref'    => sanitize_text_field( $object_ref ),
				'context'       => $context,
				'prev_hash'     => $previous,
				'created_at'    => $created,
			);
			$event_hash = hash_hmac( 'sha256', SNFLA_Checksum::encode( $payload ), wp_salt( 'auth' ) );
			$result = $wpdb->insert(
				$t['audit'],
				array(
					'event_uuid'   => $uuid,
					'actor_digest' => $payload['actor_digest'],
					'action'       => $action,
					'object_ref'   => $payload['object_ref'],
					'context_json' => wp_json_encode( $context ),
					'prev_hash'    => $previous,
					'event_hash'   => $event_hash,
					'created_at'   => $created,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		} finally {
			SNFLA_Database::release_lock( 'audit_chain' );
		}
		if ( false !== $result && empty( $wpdb->last_error ) ) {
			do_action( 'smc_audit_event', 'file_04_legacy_adapter', array( 'event' => $action, 'actor_digest' => self::actor_digest( $actor_id ), 'context' => $context, 'created_at' => $created ) );
		}
		return false !== $result && empty( $wpdb->last_error );
	}

	public static function verify_chain() {
		global $wpdb;
		$t        = SNFLA_Database::tables();
		$previous = '';
		$checked  = 0;
		$cursor   = 0;
		do {
			$wpdb->last_error = '';
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id,event_uuid,actor_digest,action,object_ref,context_json,prev_hash,event_hash,created_at FROM {$t['audit']} WHERE id>%d ORDER BY id ASC LIMIT %d",
					$cursor,
					500
				),
				ARRAY_A
			);
			if ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) {
				return array( 'valid' => false, 'checked' => $checked, 'error' => 'audit_query_failed' );
			}
			foreach ( $rows as $row ) {
				$cursor  = max( $cursor, absint( $row['id'] ?? 0 ) );
				$context = json_decode( (string) $row['context_json'], true );
				$context = is_array( $context ) ? $context : array();
				$payload = array(
					'event_uuid'   => (string) $row['event_uuid'],
					'actor_digest' => (string) $row['actor_digest'],
					'action'       => (string) $row['action'],
					'object_ref'   => (string) $row['object_ref'],
					'context'      => $context,
					'prev_hash'    => $previous,
					'created_at'   => (string) $row['created_at'],
				);
				$actual = hash_hmac( 'sha256', SNFLA_Checksum::encode( $payload ), wp_salt( 'auth' ) );
				if ( ! hash_equals( $previous, (string) $row['prev_hash'] ) || ! hash_equals( $actual, (string) $row['event_hash'] ) ) {
					return array( 'valid' => false, 'checked' => $checked, 'error' => 'audit_chain_mismatch', 'event_uuid' => (string) $row['event_uuid'] );
				}
				$previous = (string) $row['event_hash'];
				$checked++;
			}
		} while ( count( $rows ) === 500 );
		return array( 'valid' => true, 'checked' => $checked, 'head_hash' => $previous );
	}

	public static function has_event( $action, $object_ref = '', $context_key = '', $context_value = '' ) {
		global $wpdb;
		$t          = SNFLA_Database::tables();
		$action     = sanitize_key( $action );
		$object_ref = sanitize_text_field( $object_ref );
		$cursor     = PHP_INT_MAX;
		do {
			$wpdb->last_error = '';
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id,context_json FROM {$t['audit']} WHERE action=%s AND object_ref=%s AND id<%d ORDER BY id DESC LIMIT %d",
					$action,
					$object_ref,
					$cursor,
					500
				),
				ARRAY_A
			);
			if ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) {
				return false;
			}
			foreach ( $rows as $row ) {
				$cursor  = min( $cursor, absint( $row['id'] ?? 0 ) );
				$context = json_decode( (string) ( $row['context_json'] ?? '' ), true );
				if ( ! is_array( $context ) ) {
					continue;
				}
				if ( '' === $context_key ) {
					return true;
				}
				if ( array_key_exists( $context_key, $context ) && hash_equals( (string) $context_value, (string) $context[ $context_key ] ) ) {
					return true;
				}
			}
		} while ( count( $rows ) === 500 && $cursor > 0 );
		return false;
	}

	public static function redact( array $context, $depth = 0 ) {
		if ( $depth >= 5 ) { return array( '_truncated' => true ); }
		$deny = array( 'name', 'email', 'phone', 'address', 'cnic', 'passport', 'content', 'post_content', 'details', 'notes', 'ip', 'user_agent', 'consent', 'password', 'secret', 'token', 'cookie', 'authorization', 'credential' );
		$out  = array();
		foreach ( array_slice( $context, 0, 100, true ) as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key ) { $key = 'field'; }
			if ( in_array( $key, $deny, true ) || preg_match( '/(?:email|phone|address|identity|content|detail|note|consent|patient|ip|secret|token|password|cookie|authorization|credential)/', $key ) ) {
				$out[ $key ] = '[redacted]';
				continue;
			}
			if ( is_array( $value ) ) {
				$out[ $key ] = self::redact( $value, $depth + 1 );
			} elseif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				$out[ $key ] = $value;
			} elseif ( null === $value ) {
				$out[ $key ] = null;
			} else {
				$out[ $key ] = substr( sanitize_text_field( is_object( $value ) ? get_class( $value ) : (string) $value ), 0, 300 );
			}
		}
		return $out;
	}
}
