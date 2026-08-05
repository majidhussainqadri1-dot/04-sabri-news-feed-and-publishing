<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Interaction_Provider {
	public static function register() {
		add_filter( 'sabri_hnf_legacy_interaction_migration_providers', array( __CLASS__, 'provider' ) );
	}

	public static function provider( $providers ) {
		$providers = is_array( $providers ) ? $providers : array();
		$providers[ SNFLA_File21_Adapter::INTERACTION_PROVIDER ] = array(
			'label'             => 'File 04 exact legacy interaction importer',
			'callback'          => array( __CLASS__, 'migrate' ),
			'source_schema'     => 'snp_reactions+snp_saves+snp_views+snp_reports',
			'supports_rollback' => true,
		);
		return $providers;
	}

	public static function migrate( array $context ) {
		$legacy_id = absint( $context['legacy_id'] ?? 0 );
		$target_id = absint( $context['target_id'] ?? 0 );
		$actor_id  = absint( $context['actor_id'] ?? 0 );
		$limit     = min( 10000, max( 1, absint( $context['max_records'] ?? 10000 ) ) );
		$base      = array( 'status' => 'failed', 'migrated_records' => 0, 'migrated_metrics' => array(), 'skipped_records' => 0, 'errors' => array() );

		if ( $legacy_id <= 0 || $target_id <= 0 || $actor_id !== get_current_user_id() || ! current_user_can( 'sabri_feed_run_migrations' ) ) {
			$base['errors'][] = 'provider_authorization_failed';
			return $base;
		}
		if ( ! SNFLA_Capabilities::file21_ready() || ! class_exists( '\\Sabri\\HomeNewsFeed\\InteractionRepository' ) ) {
			$base['errors'][] = 'canonical_interaction_repository_unavailable';
			return $base;
		}
		if ( absint( get_post_meta( $target_id, '_sabri_hnf_legacy_source_id', true ) ) !== $legacy_id ) {
			$base['errors'][] = 'target_provenance_failed';
			return $base;
		}

		$metrics = array( 'reactions' => 0, 'saves' => 0, 'views' => 0, 'reports' => 0 );
		$skipped = 0;
		$errors  = array();
		$budget  = $limit;
		foreach ( array( 'reactions', 'saves', 'views', 'reports' ) as $kind ) {
			if ( $budget <= 0 ) {
				$errors[] = 'record_limit_reached';
				break;
			}
			$result = self::migrate_kind( $kind, $legacy_id, $target_id, $budget );
			$metrics[ $kind ] += $result['migrated'];
			$skipped          += $result['skipped'];
			$budget           -= $result['processed'];
			$errors            = array_merge( $errors, $result['errors'] );
		}
		$migrated = array_sum( $metrics );
		$status   = empty( $errors ) ? ( $migrated > 0 ? 'migrated' : 'nothing_to_migrate' ) : ( $migrated > 0 ? 'partial' : 'failed' );
		SNFLA_Audit::record( 'legacy_interactions_migrated', $actor_id, array( 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'metrics' => $metrics, 'skipped' => $skipped, 'status' => $status ), 'legacy:' . $legacy_id );
		return array(
			'status'           => $status,
			'migrated_records' => $migrated,
			'migrated_metrics' => $metrics,
			'skipped_records'  => $skipped,
			'errors'           => array_slice( array_values( array_unique( $errors ) ), 0, 50 ),
		);
	}

	private static function migrate_kind( $kind, $legacy_id, $target_id, $limit ) {
		global $wpdb;
		$legacy_table = $wpdb->prefix . 'snp_' . $kind;
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $legacy_table ) ) );
		if ( $exists !== $legacy_table ) {
			return array( 'processed' => 0, 'migrated' => 0, 'skipped' => 0, 'errors' => array() );
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$legacy_table}` WHERE post_id=%d ORDER BY id ASC LIMIT %d", $legacy_id, $limit ), ARRAY_A );
		$report = array( 'processed' => 0, 'migrated' => 0, 'skipped' => 0, 'errors' => array() );
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$report['processed']++;
			$canonical = self::canonical_row( $kind, $row, $target_id );
			if ( is_wp_error( $canonical ) ) {
				$report['skipped']++;
				$report['errors'][] = $canonical->get_error_code();
				continue;
			}
			$existing = self::canonical_existing_row( $kind, $canonical );
			if ( is_array( $existing ) ) {
				$desired_status = sanitize_key( $canonical['status'] ?? '' );
				$current_status = sanitize_key( $existing['status'] ?? '' );
				if ( $desired_status === $current_status ) {
					$report['skipped']++;
					continue;
				}
				$result = \Sabri\HomeNewsFeed\InteractionRepository::update_rows( $kind, array( 'status' => $desired_status ), array( 'id' => absint( $existing['id'] ) ) );
				if ( empty( $result['ok'] ) ) {
					$report['skipped']++;
					$report['errors'][] = 'canonical_reactivation_failed';
					continue;
				}
				SNFLA_Mapping::append_interaction_row( $legacy_id, $target_id, $kind, absint( $existing['id'] ), $current_status );
				$report['migrated']++;
				continue;
			}
			$result = \Sabri\HomeNewsFeed\InteractionRepository::insert_row( $kind, $canonical );
			if ( empty( $result['ok'] ) ) {
				$report['skipped']++;
				$report['errors'][] = isset( $result['code'] ) ? sanitize_key( $result['code'] ) : 'canonical_insert_failed';
				continue;
			}
			$row_id = absint( $wpdb->insert_id );
			if ( $row_id <= 0 ) {
				$report['errors'][] = 'canonical_row_id_missing';
				continue;
			}
			SNFLA_Mapping::append_interaction_row( $legacy_id, $target_id, $kind, $row_id, '' );
			$report['migrated']++;
		}
		return $report;
	}

	private static function canonical_row( $kind, array $row, $target_id ) {
		$created = self::datetime( $row['created_at'] ?? '' );
		$updated = self::datetime( $row['updated_at'] ?? $created );
		switch ( $kind ) {
			case 'reactions':
				$user_id = absint( $row['user_id'] ?? 0 );
				if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
					return new WP_Error( 'legacy_reaction_user_missing' );
				}
				return array( 'post_id' => $target_id, 'user_id' => $user_id, 'reaction_type' => in_array( sanitize_key( $row['reaction_type'] ?? 'like' ), array( 'like', 'dislike' ), true ) ? sanitize_key( $row['reaction_type'] ?? 'like' ) : 'like', 'status' => 'active', 'created_at' => $created, 'updated_at' => $updated );
			case 'saves':
				$user_id = absint( $row['user_id'] ?? 0 );
				if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
					return new WP_Error( 'legacy_save_user_missing' );
				}
				return array( 'user_id' => $user_id, 'post_id' => $target_id, 'collection_key' => 'default', 'status' => 'active', 'created_at' => $created, 'updated_at' => $updated );
			case 'views':
				$user_id = absint( $row['user_id'] ?? 0 );
				$day = isset( $row['view_day'] ) ? sanitize_text_field( $row['view_day'] ) : substr( $created, 0, 10 );
				$hash = strtolower( (string) ( $row['viewer_hash'] ?? $row['anonymous_hash'] ?? '' ) );
				if ( $user_id <= 0 ) {
					$hash = preg_match( '/^[a-f0-9]{64}$/', $hash ) ? $hash : hash_hmac( 'sha256', 'legacy-view|' . (string) ( $row['id'] ?? '' ) . '|' . $target_id, wp_salt( 'nonce' ) );
				}
				return array( 'post_id' => $target_id, 'user_id' => $user_id, 'anonymous_hash' => $user_id > 0 ? '' : $hash, 'view_date' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ? $day : gmdate( 'Y-m-d' ), 'view_count' => max( 1, absint( $row['view_count'] ?? 1 ) ), 'status' => 'counted', 'created_at' => $created, 'updated_at' => $updated );
			case 'reports':
				$user_id = absint( $row['user_id'] ?? $row['reporter_user_id'] ?? 0 );
				$reason = sanitize_key( $row['reason'] ?? 'other' );
				$status = sanitize_key( $row['status'] ?? 'open' );
				$status = in_array( $status, array( 'open', 'triaged', 'resolved', 'dismissed', 'duplicate' ), true ) ? $status : 'open';
				$duplicate = hash_hmac( 'sha256', 'legacy-report|' . (string) ( $row['id'] ?? '' ) . '|' . $target_id, wp_salt( 'auth' ) );
				return array( 'reporter_user_id' => $user_id, 'object_type' => 'post', 'object_id' => $target_id, 'reason' => '' !== $reason ? $reason : 'other', 'status' => $status, 'duplicate_hash' => $duplicate, 'notes' => '[legacy report body retained only in read-only source]', 'created_at' => $created, 'updated_at' => $updated );
		}
		return new WP_Error( 'unsupported_interaction_kind' );
	}

	private static function canonical_existing_row( $kind, array $row ) {
		global $wpdb;
		$table = \Sabri\HomeNewsFeed\InteractionRepository::table_name( $kind );
		if ( '' === $table ) {
			return null;
		}
		switch ( $kind ) {
			case 'reactions':
				$sql = $wpdb->prepare( "SELECT id,status FROM `{$table}` WHERE user_id=%d AND post_id=%d AND status='active' ORDER BY id DESC LIMIT 1", $row['user_id'], $row['post_id'] );
				break;
			case 'saves':
				$sql = $wpdb->prepare( "SELECT id,status FROM `{$table}` WHERE user_id=%d AND post_id=%d AND collection_key=%s ORDER BY id DESC LIMIT 1", $row['user_id'], $row['post_id'], $row['collection_key'] );
				break;
			case 'views':
				$sql = $wpdb->prepare( "SELECT id,status FROM `{$table}` WHERE post_id=%d AND user_id=%d AND anonymous_hash=%s AND view_date=%s ORDER BY id DESC LIMIT 1", $row['post_id'], $row['user_id'], $row['anonymous_hash'], $row['view_date'] );
				break;
			case 'reports':
				$sql = $wpdb->prepare( "SELECT id,status FROM `{$table}` WHERE reporter_user_id=%d AND object_type=%s AND object_id=%d AND duplicate_hash=%s ORDER BY id DESC LIMIT 1", $row['reporter_user_id'], $row['object_type'], $row['object_id'], $row['duplicate_hash'] );
				break;
			default:
				return null;
		}
		$result = $wpdb->get_row( $sql, ARRAY_A );
		return is_array( $result ) ? $result : null;
	}

	private static function datetime( $value ) {
		$value = sanitize_text_field( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ? $value : gmdate( 'Y-m-d H:i:s' );
	}

	public static function rollback( $legacy_id, $actor_id ) {
		$current = SNFLA_Mapping::get( $legacy_id );
		$ledger  = $current && ! empty( $current['interaction_ledger_json'] ) ? json_decode( $current['interaction_ledger_json'], true ) : array();
		if ( ! is_array( $ledger ) || empty( $ledger ) ) {
			return array( 'success' => true, 'updated' => 0, 'errors' => array() );
		}
		$updated = 0;
		$errors  = array();
		$status_map = array( 'reactions' => 'removed', 'saves' => 'removed', 'views' => 'ignored', 'reports' => 'dismissed' );
		$allowed_statuses = array(
			'reactions' => array( 'active', 'removed' ),
			'saves' => array( 'active', 'removed' ),
			'views' => array( 'counted', 'ignored' ),
			'reports' => array( 'open', 'triaged', 'resolved', 'dismissed', 'duplicate' ),
		);
		foreach ( $ledger as $kind => $rows ) {
			if ( ! isset( $status_map[ $kind ] ) ) {
				continue;
			}
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$id = absint( $row['id'] ?? 0 );
				if ( $id <= 0 ) {
					continue;
				}
				$original = sanitize_key( $row['status'] ?? '' );
				$rollback_status = in_array( $original, $allowed_statuses[ $kind ], true ) ? $original : $status_map[ $kind ];
				$result = \Sabri\HomeNewsFeed\InteractionRepository::update_rows( $kind, array( 'status' => $rollback_status ), array( 'id' => $id ) );
				if ( empty( $result['ok'] ) ) {
					$errors[] = 'interaction_rollback_' . sanitize_key( $kind );
				} else {
					$updated++;
				}
			}
		}
		SNFLA_Audit::record( 'legacy_interactions_rolled_back', $actor_id, array( 'legacy_id' => absint( $legacy_id ), 'updated' => $updated, 'errors' => $errors ), 'legacy:' . absint( $legacy_id ) );
		return array( 'success' => empty( $errors ), 'updated' => $updated, 'errors' => array_values( array_unique( $errors ) ) );
	}
}
