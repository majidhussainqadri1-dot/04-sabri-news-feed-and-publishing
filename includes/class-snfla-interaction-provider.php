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
			$base['errors'][] = 'provider_authorization_failed'; return $base;
		}
		if ( ! SNFLA_Capabilities::file21_ready() || ! class_exists( '\Sabri\HomeNewsFeed\InteractionRepository' ) ) {
			$base['errors'][] = 'canonical_interaction_repository_unavailable'; return $base;
		}
		if ( absint( get_post_meta( $target_id, '_sabri_hnf_legacy_source_id', true ) ) !== $legacy_id ) {
			$base['errors'][] = 'target_provenance_failed'; return $base;
		}
		$metrics = array( 'reactions' => 0, 'saves' => 0, 'views' => 0, 'reports' => 0 );
		$skipped = 0; $errors = array(); $budget = $limit;
		foreach ( array_keys( $metrics ) as $kind ) {
			if ( $budget <= 0 ) { $errors[] = 'record_limit_reached'; break; }
			$result = self::migrate_kind( $kind, $legacy_id, $target_id, $budget );
			$metrics[ $kind ] += $result['migrated']; $skipped += $result['skipped']; $budget -= $result['processed'];
			$errors = array_merge( $errors, $result['errors'] );
		}
		$migrated = array_sum( $metrics );
		$status = empty( $errors ) ? ( $migrated > 0 ? 'migrated' : 'nothing_to_migrate' ) : ( $migrated > 0 ? 'partial' : 'failed' );
		if ( ! SNFLA_Audit::record( 'legacy_interactions_migrated', $actor_id, array( 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'metrics' => $metrics, 'skipped' => $skipped, 'status' => $status ), 'legacy:' . $legacy_id ) ) { $errors[] = 'interaction_audit_failed'; $status = $migrated > 0 ? 'partial' : 'failed'; }
		return array( 'status' => $status, 'migrated_records' => $migrated, 'migrated_metrics' => $metrics, 'skipped_records' => $skipped, 'errors' => array_slice( array_values( array_unique( $errors ) ), 0, 50 ) );
	}

	private static function migrate_kind( $kind, $legacy_id, $target_id, $limit ) {
		global $wpdb;
		$legacy_table = $wpdb->prefix . 'snp_' . $kind;
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $legacy_table ) ) );
		if ( $exists !== $legacy_table ) { return array( 'processed' => 0, 'migrated' => 0, 'skipped' => 0, 'errors' => array() ); }
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$legacy_table}` WHERE post_id=%d ORDER BY id ASC LIMIT %d", $legacy_id, $limit ), ARRAY_A );
		$report = array( 'processed' => 0, 'migrated' => 0, 'skipped' => 0, 'errors' => array() );
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$report['processed']++;
			$canonical = self::canonical_row( $kind, $row, $target_id );
			if ( is_wp_error( $canonical ) ) { $report['skipped']++; $report['errors'][] = $canonical->get_error_code(); continue; }
			$existing = self::canonical_existing_row( $kind, $canonical );
			if ( is_array( $existing ) ) {
				$changes = self::existing_changes( $kind, $existing, $canonical, $legacy_id );
				if ( is_wp_error( $changes ) ) { $report['skipped']++; $report['errors'][] = $changes->get_error_code(); continue; }
				if ( empty( $changes ) ) { $report['skipped']++; continue; }
				$original = self::original_fields( $kind, $existing );
				$result = \Sabri\HomeNewsFeed\InteractionRepository::update_rows( $kind, $changes, array( 'id' => absint( $existing['id'] ) ) );
				if ( empty( $result['ok'] ) ) { $report['skipped']++; $report['errors'][] = 'canonical_interaction_update_failed'; continue; }
				if ( ! SNFLA_Mapping::append_interaction_row( $legacy_id, $target_id, $kind, absint( $existing['id'] ), $original ) ) {
					\Sabri\HomeNewsFeed\InteractionRepository::update_rows( $kind, $original, array( 'id' => absint( $existing['id'] ) ) );
					$report['skipped']++; $report['errors'][] = 'interaction_ledger_persist_failed'; continue;
				}
				$report['migrated']++; continue;
			}
			$result = \Sabri\HomeNewsFeed\InteractionRepository::insert_row( $kind, $canonical );
			if ( empty( $result['ok'] ) ) { $report['skipped']++; $report['errors'][] = isset( $result['code'] ) ? sanitize_key( $result['code'] ) : 'canonical_insert_failed'; continue; }
			$row_id = absint( $wpdb->insert_id );
			if ( $row_id <= 0 ) {
				$inserted = self::canonical_existing_row( $kind, $canonical );
				$row_id = is_array( $inserted ) ? absint( $inserted['id'] ?? 0 ) : 0;
			}
			if ( $row_id <= 0 ) { $report['errors'][] = 'canonical_row_id_missing'; $report['skipped']++; continue; }
			if ( ! SNFLA_Mapping::append_interaction_row( $legacy_id, $target_id, $kind, $row_id, array( 'created_by_migration' => true ) ) ) {
				self::compensate_insert( $kind, $row_id );
				$report['errors'][] = 'interaction_ledger_persist_failed'; $report['skipped']++; continue;
			}
			$report['migrated']++;
		}
		return $report;
	}

	private static function compensate_insert( $kind, $row_id ) {
		$status = array( 'reactions' => 'removed', 'saves' => 'removed', 'views' => 'ignored', 'reports' => 'dismissed' );
		if ( isset( $status[ $kind ] ) ) { \Sabri\HomeNewsFeed\InteractionRepository::update_rows( $kind, array( 'status' => $status[ $kind ] ), array( 'id' => absint( $row_id ) ) ); }
	}

	private static function canonical_row( $kind, array $row, $target_id ) {
		$created = self::datetime( $row['created_at'] ?? '' ); $updated = self::datetime( $row['updated_at'] ?? $created );
		switch ( $kind ) {
			case 'reactions':
				$user_id = absint( $row['user_id'] ?? 0 ); if ( $user_id <= 0 || ! get_userdata( $user_id ) ) { return new WP_Error( 'legacy_reaction_user_missing' ); }
				$type = sanitize_key( $row['reaction_type'] ?? 'like' ); $type = in_array( $type, array( 'like', 'dislike' ), true ) ? $type : 'like';
				return array( 'post_id' => $target_id, 'user_id' => $user_id, 'reaction_type' => $type, 'status' => 'active', 'created_at' => $created, 'updated_at' => $updated );
			case 'saves':
				$user_id = absint( $row['user_id'] ?? 0 ); if ( $user_id <= 0 || ! get_userdata( $user_id ) ) { return new WP_Error( 'legacy_save_user_missing' ); }
				return array( 'user_id' => $user_id, 'post_id' => $target_id, 'collection_key' => 'default', 'status' => 'active', 'created_at' => $created, 'updated_at' => $updated );
			case 'views':
				$user_id = absint( $row['user_id'] ?? 0 ); $day = sanitize_text_field( $row['view_day'] ?? substr( $created, 0, 10 ) ); $hash = strtolower( (string) ( $row['viewer_hash'] ?? $row['anonymous_hash'] ?? '' ) );
				if ( $user_id <= 0 ) { $hash = preg_match( '/^[a-f0-9]{64}$/', $hash ) ? $hash : hash_hmac( 'sha256', 'legacy-view|' . (string) ( $row['id'] ?? '' ) . '|' . $target_id, wp_salt( 'nonce' ) ); }
				return array( 'post_id' => $target_id, 'user_id' => $user_id, 'anonymous_hash' => $user_id > 0 ? '' : $hash, 'view_date' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ? $day : gmdate( 'Y-m-d' ), 'view_count' => max( 1, absint( $row['view_count'] ?? 1 ) ), 'status' => 'counted', 'created_at' => $created, 'updated_at' => $updated );
			case 'reports':
				$user_id = absint( $row['user_id'] ?? $row['reporter_user_id'] ?? 0 ); $reason = sanitize_key( $row['reason'] ?? 'other' ); $status = sanitize_key( $row['status'] ?? 'open' );
				$status = in_array( $status, array( 'open', 'triaged', 'resolved', 'dismissed', 'duplicate' ), true ) ? $status : 'open';
				return array( 'reporter_user_id' => $user_id, 'object_type' => 'post', 'object_id' => $target_id, 'reason' => $reason ?: 'other', 'status' => $status, 'duplicate_hash' => hash_hmac( 'sha256', 'legacy-report|' . (string) ( $row['id'] ?? '' ) . '|' . $target_id, wp_salt( 'auth' ) ), 'notes' => '[legacy report body retained only in read-only source]', 'created_at' => $created, 'updated_at' => $updated );
		}
		return new WP_Error( 'unsupported_interaction_kind' );
	}

	private static function existing_changes( $kind, array $existing, array $canonical, $legacy_id ) {
		$changes = array();
		$current_status = sanitize_key( $existing['status'] ?? '' );
		$desired_status = sanitize_key( $canonical['status'] ?? '' );
		if ( 'reactions' === $kind && 'active' === $current_status && sanitize_key( $existing['reaction_type'] ?? 'like' ) !== sanitize_key( $canonical['reaction_type'] ?? 'like' ) ) {
			return new WP_Error( 'canonical_reaction_conflict' );
		}
		if ( $current_status !== $desired_status ) { $changes['status'] = $desired_status; }
		if ( 'reactions' === $kind && sanitize_key( $existing['reaction_type'] ?? 'like' ) !== sanitize_key( $canonical['reaction_type'] ?? 'like' ) ) { $changes['reaction_type'] = sanitize_key( $canonical['reaction_type'] ); }
		if ( 'views' === $kind ) {
			$legacy_count = max( 1, absint( $canonical['view_count'] ?? 1 ) );
			$ledger_original = self::ledger_original_for( $legacy_id, $kind, absint( $existing['id'] ?? 0 ) );
			if ( ! empty( $ledger_original ) ) {
				$desired_count = ! empty( $ledger_original['created_by_migration'] ) ? $legacy_count : max( 1, absint( $ledger_original['view_count'] ?? 1 ) ) + $legacy_count;
			} else {
				$desired_count = max( 1, absint( $existing['view_count'] ?? 1 ) ) + $legacy_count;
			}
			if ( absint( $existing['view_count'] ?? 0 ) !== $desired_count ) { $changes['view_count'] = $desired_count; }
		}
		if ( 'reports' === $kind && ( sanitize_key( $existing['reason'] ?? '' ) !== sanitize_key( $canonical['reason'] ?? '' ) || (string) ( $existing['notes'] ?? '' ) !== (string) ( $canonical['notes'] ?? '' ) ) ) {
			return new WP_Error( 'canonical_report_conflict' );
		}
		return $changes;
	}

	private static function original_fields( $kind, array $existing ) {
		$fields = array( 'status' => sanitize_key( $existing['status'] ?? '' ) );
		if ( 'reactions' === $kind ) { $fields['reaction_type'] = sanitize_key( $existing['reaction_type'] ?? 'like' ); }
		if ( 'views' === $kind ) { $fields['view_count'] = max( 1, absint( $existing['view_count'] ?? 1 ) ); }
		return $fields;
	}

	private static function ledger_original_for( $legacy_id, $kind, $row_id ) {
		$current = SNFLA_Mapping::get( $legacy_id );
		$ledger = $current && ! empty( $current['interaction_ledger_json'] ) ? json_decode( $current['interaction_ledger_json'], true ) : array();
		foreach ( (array) ( $ledger[ $kind ] ?? array() ) as $entry ) {
			if ( absint( $entry['id'] ?? 0 ) === absint( $row_id ) ) { return is_array( $entry['original'] ?? null ) ? $entry['original'] : array( 'status' => sanitize_key( $entry['status'] ?? '' ) ); }
		}
		return array();
	}

	private static function canonical_existing_row( $kind, array $row ) {
		global $wpdb; $table = \Sabri\HomeNewsFeed\InteractionRepository::table_name( $kind ); if ( '' === $table ) { return null; }
		switch ( $kind ) {
			case 'reactions': $sql = $wpdb->prepare( "SELECT id,status,post_id,user_id,reaction_type FROM `{$table}` WHERE user_id=%d AND post_id=%d ORDER BY (status='active') DESC,id DESC LIMIT 1", $row['user_id'], $row['post_id'] ); break;
			case 'saves': $sql = $wpdb->prepare( "SELECT id,status,post_id,user_id,collection_key FROM `{$table}` WHERE user_id=%d AND post_id=%d AND collection_key=%s ORDER BY (status='active') DESC,id DESC LIMIT 1", $row['user_id'], $row['post_id'], $row['collection_key'] ); break;
			case 'views': $sql = $wpdb->prepare( "SELECT id,status,post_id,user_id,anonymous_hash,view_date,view_count FROM `{$table}` WHERE post_id=%d AND user_id=%d AND anonymous_hash=%s AND view_date=%s ORDER BY id DESC LIMIT 1", $row['post_id'], $row['user_id'], $row['anonymous_hash'], $row['view_date'] ); break;
			case 'reports': $sql = $wpdb->prepare( "SELECT id,status,object_type,object_id,reporter_user_id,duplicate_hash,reason,notes FROM `{$table}` WHERE reporter_user_id=%d AND object_type=%s AND object_id=%d AND duplicate_hash=%s ORDER BY id DESC LIMIT 1", $row['reporter_user_id'], $row['object_type'], $row['object_id'], $row['duplicate_hash'] ); break;
			default: return null;
		}
		$result = $wpdb->get_row( $sql, ARRAY_A ); return is_array( $result ) ? $result : null;
	}

	private static function datetime( $value ) {
		$value = sanitize_text_field( (string) $value ); return preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ? $value : gmdate( 'Y-m-d H:i:s' );
	}

	public static function reconcile( $legacy_id, $target_id ) {
		global $wpdb;
		$issues = array(); $checked = 0; $source_total = 0;
		foreach ( array( 'reactions', 'saves', 'views', 'reports' ) as $kind ) {
			$legacy_table = $wpdb->prefix . 'snp_' . $kind;
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $legacy_table ) ) );
			if ( $exists !== $legacy_table ) { continue; }
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$legacy_table}` WHERE post_id=%d ORDER BY id ASC LIMIT 10001", absint( $legacy_id ) ), ARRAY_A );
			if ( ! is_array( $rows ) ) { $issues[] = 'interaction_source_query_failed_' . $kind; continue; }
			if ( count( $rows ) > 10000 ) { $issues[] = 'interaction_reconciliation_limit_' . $kind; $rows = array_slice( $rows, 0, 10000 ); }
			foreach ( $rows as $row ) {
				$source_total++;
				$canonical = self::canonical_row( $kind, $row, $target_id );
				if ( is_wp_error( $canonical ) ) { $issues[] = $canonical->get_error_code(); continue; }
				$existing = self::canonical_existing_row( $kind, $canonical );
				if ( ! is_array( $existing ) || ! self::row_belongs_to_target( $kind, $existing, $target_id ) ) { $issues[] = 'interaction_missing_' . $kind; continue; }
				if ( sanitize_key( $existing['status'] ?? '' ) !== sanitize_key( $canonical['status'] ?? '' ) ) { $issues[] = 'interaction_status_mismatch_' . $kind; continue; }
				if ( 'reactions' === $kind && sanitize_key( $existing['reaction_type'] ?? '' ) !== sanitize_key( $canonical['reaction_type'] ?? '' ) ) { $issues[] = 'interaction_type_mismatch_reactions'; continue; }
				if ( 'views' === $kind ) {
					$original = self::ledger_original_for( $legacy_id, $kind, absint( $existing['id'] ?? 0 ) );
					$expected_count = ! empty( $original ) && empty( $original['created_by_migration'] ) ? max( 1, absint( $original['view_count'] ?? 1 ) ) + max( 1, absint( $canonical['view_count'] ?? 1 ) ) : max( 1, absint( $canonical['view_count'] ?? 1 ) );
					if ( absint( $existing['view_count'] ?? 0 ) !== $expected_count ) { $issues[] = 'interaction_count_mismatch_views'; continue; }
				}
				if ( 'reports' === $kind && ( sanitize_key( $existing['reason'] ?? '' ) !== sanitize_key( $canonical['reason'] ?? '' ) || (string) ( $existing['notes'] ?? '' ) !== (string) ( $canonical['notes'] ?? '' ) ) ) { $issues[] = 'interaction_payload_mismatch_reports'; continue; }
				$checked++;
			}
		}
		$current = SNFLA_Mapping::get( $legacy_id );
		$ledger = $current && ! empty( $current['interaction_ledger_json'] ) ? json_decode( $current['interaction_ledger_json'], true ) : array();
		foreach ( is_array( $ledger ) ? $ledger : array() as $kind => $rows ) {
			$table = class_exists( '\\Sabri\\HomeNewsFeed\\InteractionRepository' ) ? \Sabri\HomeNewsFeed\InteractionRepository::table_name( $kind ) : '';
			if ( '' === $table ) { $issues[] = 'interaction_table_unavailable_' . sanitize_key( $kind ); continue; }
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$id = absint( $row['id'] ?? 0 ); if ( $id <= 0 ) { $issues[] = 'interaction_ledger_row_invalid'; continue; }
				$canonical = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id=%d", $id ), ARRAY_A );
				if ( ! is_array( $canonical ) || ! self::row_belongs_to_target( $kind, $canonical, $target_id ) ) { $issues[] = 'interaction_provenance_mismatch_' . sanitize_key( $kind ); }
			}
		}
		return array( 'valid' => empty( $issues ) && $checked === $source_total, 'checked' => $checked, 'source_total' => $source_total, 'issues' => array_values( array_unique( array_map( 'sanitize_key', $issues ) ) ) );
	}

	public static function rollback( $legacy_id, $actor_id ) {
		global $wpdb; $current = SNFLA_Mapping::get( $legacy_id ); $target_id = absint( $current['target_id'] ?? 0 );
		$ledger = $current && ! empty( $current['interaction_ledger_json'] ) ? json_decode( $current['interaction_ledger_json'], true ) : array();
		if ( ! is_array( $ledger ) || empty( $ledger ) ) { return array( 'success' => true, 'updated' => 0, 'errors' => array() ); }
		$updated = 0; $errors = array(); $fallback = array( 'reactions' => 'removed', 'saves' => 'removed', 'views' => 'ignored', 'reports' => 'dismissed' );
		$allowed = array( 'reactions' => array( 'active', 'removed' ), 'saves' => array( 'active', 'removed' ), 'views' => array( 'counted', 'ignored' ), 'reports' => array( 'open', 'triaged', 'resolved', 'dismissed', 'duplicate' ) );
		foreach ( $ledger as $kind => $rows ) {
			if ( ! isset( $fallback[ $kind ] ) ) { continue; }
			$table = \Sabri\HomeNewsFeed\InteractionRepository::table_name( $kind );
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$id = absint( $row['id'] ?? 0 ); if ( $id <= 0 || '' === $table ) { $errors[] = 'interaction_rollback_' . sanitize_key( $kind ); continue; }
				$canonical = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id=%d", $id ), ARRAY_A );
				if ( ! is_array( $canonical ) || ! self::row_belongs_to_target( $kind, $canonical, $target_id ) ) { $errors[] = 'interaction_rollback_provenance_' . sanitize_key( $kind ); continue; }
				$original = is_array( $row['original'] ?? null ) ? $row['original'] : array( 'status' => sanitize_key( $row['status'] ?? '' ) );
				if ( ! empty( $original['created_by_migration'] ) ) {
					$restore = array( 'status' => $fallback[ $kind ] );
				} else {
					$status = sanitize_key( $original['status'] ?? '' );
					$restore = array( 'status' => in_array( $status, $allowed[ $kind ], true ) ? $status : $fallback[ $kind ] );
					if ( 'reactions' === $kind ) { $restore['reaction_type'] = in_array( sanitize_key( $original['reaction_type'] ?? 'like' ), array( 'like', 'dislike' ), true ) ? sanitize_key( $original['reaction_type'] ) : 'like'; }
					if ( 'views' === $kind ) { $restore['view_count'] = max( 1, absint( $original['view_count'] ?? 1 ) ); }
				}
				$result = \Sabri\HomeNewsFeed\InteractionRepository::update_rows( $kind, $restore, array( 'id' => $id ) );
				if ( empty( $result['ok'] ) ) { $errors[] = 'interaction_rollback_' . sanitize_key( $kind ); } else { $updated++; }
			}
		}
		if ( ! SNFLA_Audit::record( 'legacy_interactions_rolled_back', $actor_id, array( 'legacy_id' => absint( $legacy_id ), 'updated' => $updated, 'errors' => $errors ), 'legacy:' . absint( $legacy_id ) ) ) { $errors[] = 'interaction_rollback_audit_failed'; }
		return array( 'success' => empty( $errors ), 'updated' => $updated, 'errors' => array_values( array_unique( $errors ) ) );
	}

	private static function row_belongs_to_target( $kind, array $row, $target_id ) {
		if ( in_array( $kind, array( 'reactions', 'saves', 'views' ), true ) ) { return absint( $row['post_id'] ?? 0 ) === absint( $target_id ); }
		return 'reports' === $kind && 'post' === (string) ( $row['object_type'] ?? 'post' ) && absint( $row['object_id'] ?? 0 ) === absint( $target_id );
	}
}
