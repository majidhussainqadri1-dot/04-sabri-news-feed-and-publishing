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

		if ( ! SNFLA_Database::acquire_lock( 'audit_chain', 5 ) ) {
			return false;
		}
		try {
			$previous = (string) $wpdb->get_var( "SELECT event_hash FROM {$t['audit']} ORDER BY id DESC LIMIT 1" );
			$payload  = array(
				'event_uuid'    => $uuid,
				'actor_digest'  => self::actor_digest( $actor_id ),
				'action'        => $action,
				'object_ref'    => sanitize_text_field( $object_ref ),
				'context'       => $context,
				'prev_hash'     => $previous,
				'created_at'    => $created,
			);
			$event_hash = hash_hmac( 'sha256', wp_json_encode( SNFLA_Checksum::canonicalize( $payload ) ), wp_salt( 'auth' ) );
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
		if ( false !== $result ) {
			do_action( 'smc_audit_event', 'file_04_legacy_adapter', array( 'event' => $action, 'actor_id' => absint( $actor_id ), 'context' => $context, 'created_at' => $created ) );
		}
		return false !== $result;
	}

	public static function verify_chain() {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$rows = $wpdb->get_results( "SELECT event_uuid,actor_digest,action,object_ref,context_json,prev_hash,event_hash,created_at FROM {$t['audit']} ORDER BY id ASC", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array( 'valid' => false, 'checked' => 0, 'error' => 'audit_query_failed' );
		}
		$previous = '';
		$checked = 0;
		foreach ( $rows as $row ) {
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
			$actual = hash_hmac( 'sha256', wp_json_encode( SNFLA_Checksum::canonicalize( $payload ) ), wp_salt( 'auth' ) );
			if ( ! hash_equals( $previous, (string) $row['prev_hash'] ) || ! hash_equals( $actual, (string) $row['event_hash'] ) ) {
				return array( 'valid' => false, 'checked' => $checked, 'error' => 'audit_chain_mismatch', 'event_uuid' => (string) $row['event_uuid'] );
			}
			$previous = (string) $row['event_hash'];
			$checked++;
		}
		return array( 'valid' => true, 'checked' => $checked, 'head_hash' => $previous );
	}

	public static function has_event( $action, $object_ref = '', $context_key = '', $context_value = '' ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$action = sanitize_key( $action );
		$object_ref = sanitize_text_field( $object_ref );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT context_json FROM {$t['audit']} WHERE action=%s AND object_ref=%s ORDER BY id DESC LIMIT 2000",
				$action,
				$object_ref
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return false;
		}
		foreach ( $rows as $row ) {
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
		return false;
	}

	public static function redact( array $context ) {
		$deny = array( 'name', 'email', 'phone', 'address', 'cnic', 'passport', 'content', 'post_content', 'details', 'notes', 'ip', 'user_agent', 'consent' );
		$out  = array();
		foreach ( $context as $key => $value ) {
			$key = sanitize_key( $key );
			if ( in_array( $key, $deny, true ) || preg_match( '/(?:email|phone|address|identity|content|details|note|consent|patient|ip)/', $key ) ) {
				$out[ $key ] = '[redacted]';
				continue;
			}
			if ( is_array( $value ) ) {
				$out[ $key ] = self::redact( array_slice( $value, 0, 100, true ) );
			} elseif ( is_bool( $value ) || is_numeric( $value ) ) {
				$out[ $key ] = $value;
			} else {
				$out[ $key ] = substr( sanitize_text_field( (string) $value ), 0, 300 );
			}
		}
		return $out;
	}
}
