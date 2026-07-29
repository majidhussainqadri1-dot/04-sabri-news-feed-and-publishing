<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Audit {
	private static function lock_name() {
		global $wpdb;
		return substr( $wpdb->prefix . 'snp_audit_chain', 0, 64 );
	}

	private static function acquire_lock() {
		global $wpdb;
		return 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,5)', self::lock_name() ) );
	}

	private static function release_lock() {
		global $wpdb;
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::lock_name() ) );
	}

	private static function actor_digest( $actor_id ) {
		return hash_hmac( 'sha256', 'actor|' . absint( $actor_id ), wp_salt( 'auth' ) );
	}

	private static function details_digest( $note, array $context ) {
		return hash_hmac(
			'sha256',
			(string) $note . '|' . wp_json_encode( $context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			wp_salt( 'auth' )
		);
	}

	private static function event_hash( $post_id, $action, $actor_digest, $details_digest, $created, $previous ) {
		$payload = wp_json_encode(
			array(
				'post_id'       => absint( $post_id ),
				'action'        => sanitize_key( $action ),
				'actor_digest'  => (string) $actor_digest,
				'details_digest'=> (string) $details_digest,
				'created_at'    => (string) $created,
				'prev_hash'     => (string) $previous,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		return hash_hmac( 'sha256', (string) $payload, wp_salt( 'auth' ) );
	}

	public static function record( $post_id, $action, $note, array $context = array() ) {
		global $wpdb;
		$post_id  = absint( $post_id );
		$action   = sanitize_key( $action );
		$note     = sanitize_textarea_field( $note );
		$actor_id = get_current_user_id();
		$table    = $wpdb->prefix . 'snp_audit_log';
		if ( ! self::acquire_lock() ) {
			SNP_Membership_Adapter::audit( 'audit_lock_failed', array( 'post_id' => $post_id, 'action' => $action ) );
			return false;
		}
		try {
			$previous      = (string) $wpdb->get_var( "SELECT event_hash FROM {$table} ORDER BY id DESC LIMIT 1" );
			$created       = current_time( 'mysql', true );
			$actor_digest  = self::actor_digest( $actor_id );
			$details_digest= self::details_digest( $note, $context );
			$event_hash    = self::event_hash( $post_id, $action, $actor_digest, $details_digest, $created, $previous );
			$inserted = $wpdb->insert(
				$table,
				array(
					'post_id'       => $post_id,
					'actor_id'      => $actor_id,
					'actor_digest'  => $actor_digest,
					'action'        => $action,
					'note'          => $note,
					'context_json'  => wp_json_encode( $context ),
					'details_digest'=> $details_digest,
					'prev_hash'     => $previous,
					'event_hash'    => $event_hash,
					'created_at'    => $created,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		} finally {
			self::release_lock();
		}
		if ( false === $inserted ) {
			SNP_Membership_Adapter::audit( 'local_audit_insert_failed', array( 'post_id' => $post_id, 'action' => $action ) );
			return false;
		}
		SNP_Membership_Adapter::audit(
			$action,
			array(
				'post_id'    => $post_id,
				'note'       => $note,
				'event_hash' => $event_hash,
				'context'    => $context,
			)
		);
		return true;
	}

	public static function upgrade_chain() {
		global $wpdb;
		$table = $wpdb->prefix . 'snp_audit_log';
		if ( ! self::acquire_lock() ) {
			return false;
		}
		try {
			$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC" );
			$previous = '';
			foreach ( $rows as $row ) {
				$context = json_decode( (string) $row->context_json, true );
				$context = is_array( $context ) ? $context : array();
				$actor_digest   = $row->actor_digest ? (string) $row->actor_digest : self::actor_digest( $row->actor_id );
				$details_digest = $row->details_digest ? (string) $row->details_digest : self::details_digest( $row->note, $context );
				$event_hash     = self::event_hash( $row->post_id, $row->action, $actor_digest, $details_digest, $row->created_at, $previous );
				$updated = $wpdb->update(
					$table,
					array(
						'actor_digest'   => $actor_digest,
						'details_digest' => $details_digest,
						'prev_hash'      => $previous,
						'event_hash'     => $event_hash,
					),
					array( 'id' => absint( $row->id ) ),
					array( '%s', '%s', '%s', '%s' ),
					array( '%d' )
				);
				if ( false === $updated ) {
					return false;
				}
				$previous = $event_hash;
			}
			return true;
		} finally {
			self::release_lock();
		}
	}

	public static function anonymize_actor( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}
		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}snp_audit_log SET actor_id=0, note='[personal details removed]', context_json='{}' WHERE actor_id=%d",
				$user_id
			)
		);
	}

	public static function retention() {
		global $wpdb;
		$anonymize = gmdate( 'Y-m-d H:i:s', time() - 180 * DAY_IN_SECONDS );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}snp_audit_log SET actor_id=0, note='[retention-anonymized]', context_json='{}' WHERE created_at<%s AND (actor_id<>0 OR note NOT LIKE '[retention-anonymized]')",
				$anonymize
			)
		);
	}
}
