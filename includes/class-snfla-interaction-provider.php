<?php
defined( 'ABSPATH' ) || exit;

/**
 * Exact, resumable and rollback-aware bridge from the historical File 04
 * interaction tables into File 21's canonical interaction repository.
 */
final class SNFLA_Interaction_Provider {
	const PAGE_SIZE             = 500;
	const DEFAULT_RECORD_BUDGET = 5000;
	const MAX_RECORD_BUDGET     = 50000;

	public static function register() {
		add_filter( 'sabri_hnf_legacy_interaction_migration_providers', array( __CLASS__, 'provider' ) );
	}

	public static function provider( $providers ) {
		$providers = is_array( $providers ) ? $providers : array();
		$providers[ SNFLA_File21_Adapter::INTERACTION_PROVIDER ] = array(
			'label'             => 'File 04 exact resumable legacy interaction importer',
			'callback'          => array( __CLASS__, 'migrate' ),
			'source_schema'     => 'snp_reactions+snp_saves+snp_views+snp_reports',
			'supports_rollback' => true,
			'supports_resume'   => true,
		);
		return $providers;
	}

	/** Called synchronously by File 21 while File 04 already owns the migration lock. */
	public static function migrate( array $context ) {
		$budget = self::budget( $context['max_records'] ?? self::DEFAULT_RECORD_BUDGET );
		if ( $budget <= 0 ) { return array( 'status' => 'failed', 'migrated_records' => 0, 'migrated_metrics' => array(), 'skipped_records' => 0, 'processed_records' => 0, 'remaining' => true, 'errors' => array( 'interaction_budget_invalid' ) ); }
		return self::run(
			self::strict_positive_id( $context['legacy_id'] ?? 0 ),
			self::strict_positive_id( $context['target_id'] ?? 0 ),
			self::strict_positive_id( $context['actor_id'] ?? 0 ),
			$budget
		);
	}

	/** Resume a previously bounded interaction import without re-importing recorded source rows. */
	public static function resume( $actor_id, $legacy_id, $max_records = self::DEFAULT_RECORD_BUDGET ) {
		$legacy_id = self::strict_positive_id( $legacy_id );
		$actor_id  = self::strict_positive_id( $actor_id );
		if ( $legacy_id <= 0 || $actor_id <= 0 ) { return new WP_Error( 'snfla_interaction_resume_identity_invalid', 'Canonical positive actor and legacy IDs are required.', array( 'status' => 400 ) ); }
		if ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) {
			return new WP_Error( 'snfla_operation_locked', 'Another File 04 operation is running.', array( 'status' => 423 ) );
		}
		$lock_name = 'interactions_' . $legacy_id;
		if ( ! SNFLA_Database::acquire_lock( $lock_name, 5 ) ) {
			SNFLA_Database::release_lock( 'operation' );
			return new WP_Error( 'snfla_interaction_resume_locked', 'This interaction migration is already being resumed.', array( 'status' => 423 ) );
		}
		try {
			$authorized_actor = SNFLA_Capabilities::revalidate_actor( $actor_id, SNFLA_Capabilities::CAP_RUN );
			if ( is_wp_error( $authorized_actor ) ) {
				return new WP_Error( 'snfla_interaction_resume_authority_changed', 'Interaction-resume authority changed while waiting for the operation lock.', array( 'status' => 403, 'cause' => $authorized_actor->get_error_code() ) );
			}
			$map = SNFLA_Mapping::get_checked( $legacy_id );
			if ( is_wp_error( $map ) ) { return $map; }
			if ( 'interaction_pending' !== (string) ( $map['status'] ?? '' ) ) {
				return new WP_Error( 'snfla_interaction_resume_unavailable', 'This legacy publication has no resumable interaction migration.', array( 'status' => 409 ) );
			}
			$target_id = absint( $map['target_id'] ?? 0 );
			if ( $target_id <= 0 ) { $target_id = SNFLA_File21_Adapter::target_for( $legacy_id ); }
			if ( ! SNFLA_File21_Adapter::migration_target_valid( $legacy_id, $target_id ) ) {
				return new WP_Error( 'snfla_interaction_resume_target_changed', 'The canonical File 21 target changed or lost migration provenance.', array( 'status' => 409 ) );
			}
			$budget = self::budget( $max_records );
			if ( $budget <= 0 ) { return new WP_Error( 'snfla_interaction_budget_invalid', 'Interaction record budget must be an integer within the supported bound.', array( 'status' => 400 ) ); }
			$result = self::run( $legacy_id, $target_id, $actor_id, $budget );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( in_array( (string) ( $result['status'] ?? '' ), array( 'migrated', 'nothing_to_migrate' ), true ) ) {
				$current = SNFLA_Mapping::get_checked( $legacy_id );
				if ( is_wp_error( $current ) ) { return $current; }
				if ( 'interaction_pending' !== (string) ( $current['status'] ?? '' ) || absint( $current['target_id'] ?? 0 ) !== $target_id || ! SNFLA_File21_Adapter::migration_target_valid( $legacy_id, $target_id ) ) {
					return new WP_Error( 'snfla_interaction_resume_state_changed', 'The interaction mapping changed before finalization.', array( 'status' => 409 ) );
				}
				$progress = SNFLA_Mapping::progress_checked( $legacy_id );
				if ( is_wp_error( $progress ) ) { return $progress; }
				$updated = SNFLA_Mapping::upsert(
					$legacy_id,
					array(
						'target_id'          => $target_id,
						'target_type'        => $current['target_type'] ?? get_post_type( $target_id ),
						'status'             => 'migrated',
						'source_checksum'    => $current['source_checksum'] ?? SNFLA_Checksum::post( $legacy_id ),
						'target_checksum'    => SNFLA_Checksum::migration_projection_checksum( $target_id, false ),
						'run_uuid'           => $current['run_uuid'] ?? '',
						'last_error_code'    => '',
						'interaction_ledger' => $progress,
					)
				);
				if ( ! $updated || ! SNFLA_Mapping::resolve_system_conflicts( $legacy_id, array( 'interaction_partial', 'interaction_migration_incomplete' ), $actor_id, 'interaction_resume_completed' ) ) {
					return new WP_Error( 'snfla_interaction_resume_finalize_failed', 'Interaction migration completed, but its mapping/conflict evidence could not be finalized.', array( 'status' => 500 ) );
				}
			}
			return $result;
		} finally {
			SNFLA_Database::release_lock( $lock_name );
			SNFLA_Database::release_lock( 'operation' );
		}
	}

	private static function strict_positive_id( $value ) {
		if ( is_int( $value ) ) { return $value > 0 ? $value : 0; }
		if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) { return 0; }
		$parsed = (int) $value;
		return $parsed > 0 && (string) $parsed === $value ? $parsed : 0;
	}

	private static function budget( $value ) {
		if ( ! is_int( $value ) || $value < 1 || $value > self::MAX_RECORD_BUDGET ) { return 0; }
		return max( self::PAGE_SIZE, $value );
	}

	private static function run( $legacy_id, $target_id, $actor_id, $record_budget ) {
		$base = array(
			'status'            => 'failed',
			'migrated_records'  => 0,
			'migrated_metrics'  => array(),
			'skipped_records'   => 0,
			'processed_records' => 0,
			'remaining'         => false,
			'errors'            => array(),
		);
		if ( $legacy_id <= 0 || $target_id <= 0 || $actor_id !== get_current_user_id() || ! current_user_can( 'sabri_feed_run_migrations' ) ) {
			$base['errors'][] = 'provider_authorization_failed';
			return $base;
		}
		if ( ! SNFLA_Capabilities::file21_ready() || ! class_exists( '\\Sabri\\HomeNewsFeed\\InteractionRepository' ) ) {
			$base['errors'][] = 'canonical_interaction_repository_unavailable';
			return $base;
		}
		if ( ! \Sabri\HomeNewsFeed\CanonicalIdentityAdapter::current_action_ready( $actor_id ) ) {
			$base['errors'][] = 'provider_fresh_authorization_required';
			return $base;
		}
		if ( ! SNFLA_File21_Adapter::migration_target_valid( $legacy_id, $target_id ) ) {
			$base['errors'][] = 'target_provenance_failed';
			return $base;
		}

		$progress = SNFLA_Mapping::progress_checked( $legacy_id );
		if ( is_wp_error( $progress ) ) {
			$base['errors'][] = $progress->get_error_code();
			return $base;
		}
		$progress['interaction_progress'] = isset( $progress['interaction_progress'] ) && is_array( $progress['interaction_progress'] ) ? $progress['interaction_progress'] : array();
		$metrics   = array( 'reactions' => 0, 'saves' => 0, 'views' => 0, 'reports' => 0 );
		$skipped   = 0;
		$processed = 0;
		$errors    = array();
		$remaining = false;
		$budget    = self::budget( $record_budget );

		foreach ( array_keys( $metrics ) as $kind ) {
			$state = $progress['interaction_progress'][ $kind ] ?? array();
			if ( ! empty( $state['complete'] ) ) {
				continue;
			}
			if ( $budget <= 0 ) {
				$remaining = true;
				break;
			}
			$result             = self::migrate_kind( $kind, $legacy_id, $target_id, $budget, $progress );
			$metrics[ $kind ]  += $result['migrated'];
			$skipped          += $result['skipped'];
			$processed        += $result['processed'];
			$budget           -= $result['processed'];
			$remaining         = $remaining || ! empty( $result['remaining'] );
			$errors            = array_merge( $errors, $result['errors'] );
			if ( ! SNFLA_Mapping::update_progress( $legacy_id, $target_id, $progress ) ) {
				$errors[]  = 'interaction_progress_persist_failed';
				$remaining = true;
				break;
			}
			if ( ! empty( $result['errors'] ) ) {
				break;
			}
		}

		$migrated = array_sum( $metrics );
		$status   = ! empty( $errors ) ? ( $migrated > 0 ? 'partial' : 'failed' ) : ( $remaining ? 'partial' : ( $migrated > 0 ? 'migrated' : 'nothing_to_migrate' ) );
		if ( ! SNFLA_Audit::record( 'legacy_interactions_migration_progressed', $actor_id, array( 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'metrics' => $metrics, 'processed' => $processed, 'skipped' => $skipped, 'remaining' => $remaining, 'status' => $status ), 'legacy:' . $legacy_id ) ) {
			$errors[] = 'interaction_audit_failed';
			$status   = $migrated > 0 ? 'partial' : 'failed';
		}
		return array(
			'status'            => $status,
			'migrated_records'  => $migrated,
			'migrated_metrics'  => $metrics,
			'skipped_records'   => $skipped,
			'processed_records' => $processed,
			'remaining'         => $remaining,
			'errors'            => array_slice( array_values( array_unique( array_map( 'sanitize_key', $errors ) ) ), 0, 100 ),
			'progress'          => SNFLA_Audit::redact( $progress['interaction_progress'] ),
		);
	}

	private static function progress_state_valid( array $state ) {
		if ( isset( $state['cursor'] ) && ( ! is_int( $state['cursor'] ) || $state['cursor'] < 0 ) ) { return false; }
		foreach ( array( 'complete', 'meta_complete' ) as $flag ) { if ( isset( $state[ $flag ] ) && ! is_bool( $state[ $flag ] ) ) { return false; } }
		return true;
	}

	private static function migrate_kind( $kind, $legacy_id, $target_id, $budget, array &$progress ) {
		global $wpdb;
		$legacy_table = $wpdb->prefix . 'snp_' . $kind;
		$exists       = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $legacy_table ) ) );
		$has_table    = $exists === $legacy_table;
		$state        = isset( $progress['interaction_progress'][ $kind ] ) && is_array( $progress['interaction_progress'][ $kind ] ) ? $progress['interaction_progress'][ $kind ] : array();
		$report       = array( 'processed' => 0, 'migrated' => 0, 'skipped' => 0, 'remaining' => false, 'errors' => array() );
		if ( ! self::progress_state_valid( $state ) ) { $report['errors'][] = 'interaction_progress_state_corrupt'; return $report; }
		$cursor       = isset( $state['cursor'] ) ? (int) $state['cursor'] : 0;
		$meta_complete = 'views' !== $kind || ( isset( $state['meta_complete'] ) && true === $state['meta_complete'] );
		$fatal        = false;

		if ( $has_table ) {
			while ( $budget > 0 && ! $fatal ) {
				$limit = min( self::PAGE_SIZE, $budget );
				$wpdb->last_error = '';
				$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$legacy_table}` WHERE post_id=%d AND id>%d ORDER BY id ASC LIMIT %d", $legacy_id, $cursor, $limit ), ARRAY_A );
				if ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) {
					$report['errors'][] = 'interaction_source_query_failed_' . $kind;
					$fatal = true;
					break;
				}
				if ( empty( $rows ) ) {
					break;
				}
				foreach ( $rows as $row ) {
					$source_row_id = self::strict_positive_id( $row['id'] ?? 0 );
					$report['processed']++;
					$budget--;
					if ( $source_row_id <= 0 ) {
						$report['errors'][] = 'legacy_interaction_source_id_missing';
						$fatal = true;
						break;
					}
					$outcome = self::migrate_source_row( $kind, $legacy_id, $target_id, $source_row_id, $row, 'legacy_table' );
					$report['migrated'] += absint( $outcome['migrated'] ?? 0 );
					$report['skipped']  += absint( $outcome['skipped'] ?? 0 );
					if ( ! empty( $outcome['error'] ) ) {
						$report['errors'][] = sanitize_key( $outcome['error'] );
						$fatal = true;
						break;
					}
					$cursor = $source_row_id;
				}
				if ( count( $rows ) < $limit ) {
					break;
				}
			}
		}

		$wpdb->last_error = '';
		$table_has_more = $has_table && 0 < absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$legacy_table}` WHERE post_id=%d AND id>%d ORDER BY id ASC LIMIT 1", $legacy_id, $cursor ) ) );
		if ( $has_table && ! empty( $wpdb->last_error ) ) {
			$report['errors'][] = 'interaction_source_probe_failed_' . $kind;
			$fatal = true;
			$table_has_more = true;
		}

		// The original File 04 release stored views only in _snp_views. Newer
		// corrective builds may also have a row table. Once table rows are
		// exhausted, migrate only the exact positive aggregate delta as one
		// synthetic, ledger-backed canonical view contribution (source row 0).
		if ( 'views' === $kind && ! $fatal && ! $table_has_more && ! $meta_complete ) {
			if ( $budget <= 0 ) {
				$report['remaining'] = true;
			} else {
				$extra = self::legacy_view_meta_extra( $legacy_id );
				if ( is_wp_error( $extra ) ) {
					$report['errors'][] = $extra->get_error_code();
					$fatal = true;
				} elseif ( $extra > 0 ) {
					$row = self::synthetic_view_row( $legacy_id, $extra );
					$report['processed']++;
					$budget--;
					$outcome = self::migrate_source_row( 'views', $legacy_id, $target_id, 0, $row, 'legacy_view_meta' );
					$report['migrated'] += absint( $outcome['migrated'] ?? 0 );
					$report['skipped']  += absint( $outcome['skipped'] ?? 0 );
					if ( ! empty( $outcome['error'] ) ) {
						$report['errors'][] = sanitize_key( $outcome['error'] );
						$fatal = true;
					}
				}
				if ( ! $fatal ) {
					$meta_complete = true;
				}
			}
		}

		$has_more = $fatal || $table_has_more || ( 'views' === $kind && ! $meta_complete );
		$report['remaining'] = $report['remaining'] || $has_more;
		$progress['interaction_progress'][ $kind ] = array(
			'cursor'         => $cursor,
			'complete'       => ! $has_more,
			'meta_complete'  => 'views' === $kind ? $meta_complete : true,
			'processed'      => absint( $state['processed'] ?? 0 ) + $report['processed'],
			'updated_at_utc' => gmdate( 'Y-m-d H:i:s' ),
		);
		return $report;
	}

	private static function migrate_source_row( $kind, $legacy_id, $target_id, $source_row_id, array $row, $source_kind ) {
		global $wpdb;
		$recorded = SNFLA_Mapping::interaction_source_recorded( $legacy_id, $kind, $source_row_id );
		if ( is_wp_error( $recorded ) ) { return array( 'migrated' => 0, 'skipped' => 0, 'error' => $recorded->get_error_code() ); }
		if ( $recorded ) { return array( 'migrated' => 0, 'skipped' => 1, 'error' => '' ); }
		$canonical = self::canonical_row( $kind, $row, $target_id );
		if ( is_wp_error( $canonical ) ) {
			return array( 'migrated' => 0, 'skipped' => 0, 'error' => $canonical->get_error_code() );
		}
		$existing = self::canonical_existing_row( $kind, $canonical );
		if ( is_array( $existing ) ) {
			$prior        = SNFLA_Mapping::interaction_original_by_canonical( $legacy_id, $kind, absint( $existing['id'] ?? 0 ) );
			if ( is_wp_error( $prior ) ) { return array( 'migrated' => 0, 'skipped' => 0, 'error' => $prior->get_error_code() ); }
			$before       = self::original_fields( $kind, $existing );
			$baseline     = isset( $prior['baseline'] ) && is_array( $prior['baseline'] ) ? $prior['baseline'] : $before;
			$created      = ! empty( $prior['created_by_migration'] );
			$contribution = 'views' === $kind ? max( 1, absint( $canonical['view_count'] ?? 1 ) ) : 1;
			$changes      = self::existing_changes( $kind, $existing, $canonical, $legacy_id, $baseline, $created, $contribution );
			if ( is_wp_error( $changes ) ) {
				return array( 'migrated' => 0, 'skipped' => 0, 'error' => $changes->get_error_code() );
			}
			if ( ! empty( $changes ) ) {
				$result = \Sabri\HomeNewsFeed\InteractionRepository::update_rows( $kind, $changes, array( 'id' => absint( $existing['id'] ) ) );
				if ( empty( $result['ok'] ) ) {
					return array( 'migrated' => 0, 'skipped' => 0, 'error' => 'canonical_interaction_update_failed' );
				}
			}
			$ledger = array( 'created_by_migration' => $created, 'baseline' => $baseline, 'source_contribution' => $contribution, 'source_kind' => sanitize_key( $source_kind ) );
			if ( ! SNFLA_Mapping::record_interaction_row( $legacy_id, $target_id, $kind, $source_row_id, absint( $existing['id'] ), $ledger ) ) {
				if ( ! empty( $changes ) ) {
					\Sabri\HomeNewsFeed\InteractionRepository::update_rows( $kind, $before, array( 'id' => absint( $existing['id'] ) ) );
				}
				return array( 'migrated' => 0, 'skipped' => 0, 'error' => 'interaction_ledger_persist_failed' );
			}
			return array( 'migrated' => 1, 'skipped' => 0, 'error' => '' );
		}

		$result = \Sabri\HomeNewsFeed\InteractionRepository::insert_row( $kind, $canonical );
		if ( empty( $result['ok'] ) ) {
			return array( 'migrated' => 0, 'skipped' => 0, 'error' => isset( $result['code'] ) ? sanitize_key( $result['code'] ) : 'canonical_insert_failed' );
		}
		$row_id = absint( $result['id'] ?? $result['row_id'] ?? $wpdb->insert_id );
		if ( $row_id <= 0 ) {
			$inserted = self::canonical_existing_row( $kind, $canonical );
			$row_id   = is_array( $inserted ) ? absint( $inserted['id'] ?? 0 ) : 0;
		}
		if ( $row_id <= 0 ) {
			return array( 'migrated' => 0, 'skipped' => 0, 'error' => 'canonical_row_id_missing' );
		}
		$contribution = 'views' === $kind ? max( 1, absint( $canonical['view_count'] ?? 1 ) ) : 1;
		$ledger = array( 'created_by_migration' => true, 'baseline' => array(), 'source_contribution' => $contribution, 'source_kind' => sanitize_key( $source_kind ) );
		if ( ! SNFLA_Mapping::record_interaction_row( $legacy_id, $target_id, $kind, $source_row_id, $row_id, $ledger ) ) {
			self::compensate_insert( $kind, $row_id );
			return array( 'migrated' => 0, 'skipped' => 0, 'error' => 'interaction_ledger_persist_failed' );
		}
		return array( 'migrated' => 1, 'skipped' => 0, 'error' => '' );
	}

	/** Exact aggregate delta not already represented by the optional legacy views table. */
	/** Build the deterministic synthetic source row for historical meta-only views. */
	private static function synthetic_view_row( $legacy_id, $view_count ) {
		$legacy_id = absint( $legacy_id );
		$post      = get_post( $legacy_id );
		$created   = $post instanceof WP_Post && ! empty( $post->post_date_gmt ) ? (string) $post->post_date_gmt : gmdate( 'Y-m-d H:i:s' );
		return array(
			'id'             => 0,
			'post_id'        => $legacy_id,
			'user_id'        => 0,
			'view_count'     => max( 1, absint( $view_count ) ),
			'view_day'       => substr( $created, 0, 10 ),
			'created_at'     => $created,
			'updated_at'     => $created,
			'anonymous_hash' => hash_hmac( 'sha256', 'legacy-view-meta|' . $legacy_id, wp_salt( 'nonce' ) ),
		);
	}

	public static function legacy_view_meta_extra( $legacy_id ) {
		global $wpdb;
		$legacy_id = absint( $legacy_id );
		$raw = get_post_meta( $legacy_id, '_snp_views', true );
		if ( '' === $raw || null === $raw ) {
			return 0;
		}
		if ( is_array( $raw ) || is_object( $raw ) || ! preg_match( '/^\d+$/', trim( (string) $raw ) ) ) {
			return new WP_Error( 'legacy_view_aggregate_invalid' );
		}
		$meta_total = absint( $raw );
		$table      = $wpdb->prefix . 'snp_views';
		$wpdb->last_error = '';
		$exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( ! empty( $wpdb->last_error ) ) { return new WP_Error( 'legacy_view_table_probe_failed' ); }
		if ( $exists !== $table ) {
			return $meta_total;
		}
		$wpdb->last_error = '';
		$columns_raw = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
		if ( ! is_array( $columns_raw ) || ! empty( $wpdb->last_error ) ) { return new WP_Error( 'legacy_view_schema_probe_failed' ); }
		$columns = array_map( 'strtolower', $columns_raw );
		$wpdb->last_error = '';
		$table_total_raw = in_array( 'view_count', $columns, true )
			? $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(GREATEST(view_count,1)),0) FROM `{$table}` WHERE post_id=%d", $legacy_id ) )
			: $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE post_id=%d", $legacy_id ) );
		if ( ! empty( $wpdb->last_error ) || null === $table_total_raw ) { return new WP_Error( 'legacy_view_aggregate_query_failed' ); }
		$table_total = absint( $table_total_raw );
		if ( $meta_total < $table_total ) {
			return new WP_Error( 'legacy_view_aggregate_less_than_table' );
		}
		return $meta_total - $table_total;
	}

	private static function compensate_insert( $kind, $row_id ) {
		$status = array( 'reactions' => 'removed', 'saves' => 'removed', 'views' => 'ignored', 'reports' => 'dismissed' );
		if ( isset( $status[ $kind ] ) ) {
			$fields = array( 'status' => $status[ $kind ] );
			if ( 'views' === $kind ) {
				$fields['view_count'] = 1;
			}
			\Sabri\HomeNewsFeed\InteractionRepository::update_rows( $kind, $fields, array( 'id' => absint( $row_id ) ) );
		}
	}

	private static function canonical_row( $kind, array $row, $target_id ) {
		$created = self::datetime( $row['created_at'] ?? '' );
		$updated = self::datetime( $row['updated_at'] ?? $created );
		switch ( $kind ) {
			case 'reactions':
				$user_id = absint( $row['user_id'] ?? 0 );
				if ( $user_id <= 0 || ! get_userdata( $user_id ) ) { return new WP_Error( 'legacy_reaction_user_missing' ); }
				$type = sanitize_key( $row['reaction_type'] ?? 'like' );
				$type = in_array( $type, array( 'like', 'dislike' ), true ) ? $type : 'like';
				return array( 'post_id' => $target_id, 'user_id' => $user_id, 'reaction_type' => $type, 'status' => 'active', 'created_at' => $created, 'updated_at' => $updated );
			case 'saves':
				$user_id = absint( $row['user_id'] ?? 0 );
				if ( $user_id <= 0 || ! get_userdata( $user_id ) ) { return new WP_Error( 'legacy_save_user_missing' ); }
				return array( 'user_id' => $user_id, 'post_id' => $target_id, 'collection_key' => 'default', 'status' => 'active', 'created_at' => $created, 'updated_at' => $updated );
			case 'views':
				$user_id = absint( $row['user_id'] ?? 0 );
				$day     = sanitize_text_field( $row['view_day'] ?? substr( $created, 0, 10 ) );
				$hash    = strtolower( (string) ( $row['viewer_hash'] ?? $row['anonymous_hash'] ?? '' ) );
				if ( $user_id > 0 && ! get_userdata( $user_id ) ) { return new WP_Error( 'legacy_view_user_missing' ); }
				if ( $user_id <= 0 ) { $hash = preg_match( '/^[a-f0-9]{64}$/', $hash ) ? $hash : hash_hmac( 'sha256', 'legacy-view|' . (string) ( $row['id'] ?? '' ) . '|' . $target_id, wp_salt( 'nonce' ) ); }
				return array( 'post_id' => $target_id, 'user_id' => $user_id, 'anonymous_hash' => $user_id > 0 ? '' : $hash, 'view_date' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ? $day : gmdate( 'Y-m-d' ), 'view_count' => max( 1, absint( $row['view_count'] ?? 1 ) ), 'status' => 'counted', 'created_at' => $created, 'updated_at' => $updated );
			case 'reports':
				$user_id = absint( $row['user_id'] ?? $row['reporter_user_id'] ?? 0 );
				if ( $user_id <= 0 || ! get_userdata( $user_id ) ) { return new WP_Error( 'legacy_report_user_missing' ); }
				$reason = sanitize_key( $row['reason'] ?? 'other' );
				$status = sanitize_key( $row['status'] ?? 'open' );
				$status = in_array( $status, array( 'open', 'triaged', 'resolved', 'dismissed', 'duplicate' ), true ) ? $status : 'open';
				$notes  = sanitize_textarea_field( (string) ( $row['details'] ?? $row['notes'] ?? '' ) );
				return array( 'reporter_user_id' => $user_id, 'object_type' => 'post', 'object_id' => $target_id, 'reason' => $reason ?: 'other', 'status' => $status, 'duplicate_hash' => hash_hmac( 'sha256', 'legacy-report|' . (string) ( $row['id'] ?? '' ) . '|' . $target_id, wp_salt( 'auth' ) ), 'notes' => $notes, 'created_at' => $created, 'updated_at' => $updated );
		}
		return new WP_Error( 'unsupported_interaction_kind' );
	}

	private static function existing_changes( $kind, array $existing, array $canonical, $legacy_id, array $baseline, $created_by_migration, $source_contribution ) {
		$changes        = array();
		$current_status = sanitize_key( $existing['status'] ?? '' );
		$desired_status = sanitize_key( $canonical['status'] ?? '' );

		// Never revive, reopen or otherwise overwrite a canonical user's current
		// state. Existing rows are reusable only when their non-aggregate payload
		// already equals the legacy projection. Views are the sole additive case
		// and are protected by the recorded baseline plus contribution ledger.
		if ( $current_status !== $desired_status ) {
			return new WP_Error( 'canonical_' . sanitize_key( $kind ) . '_state_conflict' );
		}
		if ( 'reactions' === $kind && sanitize_key( $existing['reaction_type'] ?? 'like' ) !== sanitize_key( $canonical['reaction_type'] ?? 'like' ) ) {
			return new WP_Error( 'canonical_reaction_conflict' );
		}
		if ( 'reports' === $kind && ( sanitize_key( $existing['reason'] ?? '' ) !== sanitize_key( $canonical['reason'] ?? '' ) || (string) ( $existing['notes'] ?? '' ) !== (string) ( $canonical['notes'] ?? '' ) ) ) {
			return new WP_Error( 'canonical_report_conflict' );
		}
		if ( 'views' === $kind ) {
			$recorded         = SNFLA_Mapping::interaction_contribution_total( $legacy_id, $kind, absint( $existing['id'] ?? 0 ) );
			$baseline_count   = $created_by_migration ? 0 : max( 1, absint( $baseline['view_count'] ?? 1 ) );
			$expected_current = $baseline_count + $recorded;
			if ( absint( $existing['view_count'] ?? 0 ) !== $expected_current ) {
				return new WP_Error( 'canonical_view_modified_after_partial_migration' );
			}
			$desired = $expected_current + max( 1, absint( $source_contribution ) );
			if ( absint( $existing['view_count'] ?? 0 ) !== $desired ) { $changes['view_count'] = $desired; }
		}
		return $changes;
	}

	private static function original_fields( $kind, array $existing ) {
		$fields = array( 'status' => sanitize_key( $existing['status'] ?? '' ) );
		if ( 'reactions' === $kind ) { $fields['reaction_type'] = sanitize_key( $existing['reaction_type'] ?? 'like' ); }
		if ( 'views' === $kind ) { $fields['view_count'] = max( 1, absint( $existing['view_count'] ?? 1 ) ); }
		return $fields;
	}

	private static function canonical_existing_row( $kind, array $row ) {
		global $wpdb;
		$table = \Sabri\HomeNewsFeed\InteractionRepository::table_name( $kind );
		if ( '' === $table ) { return null; }
		switch ( $kind ) {
			case 'reactions': $sql = $wpdb->prepare( "SELECT id,status,post_id,user_id,reaction_type FROM `{$table}` WHERE user_id=%d AND post_id=%d ORDER BY (status='active') DESC,id DESC LIMIT 1", $row['user_id'], $row['post_id'] ); break;
			case 'saves': $sql = $wpdb->prepare( "SELECT id,status,post_id,user_id,collection_key FROM `{$table}` WHERE user_id=%d AND post_id=%d AND collection_key=%s ORDER BY (status='active') DESC,id DESC LIMIT 1", $row['user_id'], $row['post_id'], $row['collection_key'] ); break;
			case 'views': $sql = $wpdb->prepare( "SELECT id,status,post_id,user_id,anonymous_hash,view_date,view_count FROM `{$table}` WHERE post_id=%d AND user_id=%d AND anonymous_hash=%s AND view_date=%s ORDER BY id DESC LIMIT 1", $row['post_id'], $row['user_id'], $row['anonymous_hash'], $row['view_date'] ); break;
			case 'reports': $sql = $wpdb->prepare( "SELECT id,status,object_type,object_id,reporter_user_id,duplicate_hash,reason,notes FROM `{$table}` WHERE reporter_user_id=%d AND object_type=%s AND object_id=%d AND duplicate_hash=%s ORDER BY id DESC LIMIT 1", $row['reporter_user_id'], $row['object_type'], $row['object_id'], $row['duplicate_hash'] ); break;
			default: return null;
		}
		$result = $wpdb->get_row( $sql, ARRAY_A );
		return is_array( $result ) ? $result : null;
	}

	private static function datetime( $value ) {
		$value = sanitize_text_field( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ? $value : gmdate( 'Y-m-d H:i:s' );
	}

	public static function reconcile( $legacy_id, $target_id ) {
		global $wpdb;
		$issues       = array();
		$checked      = 0;
		$source_total = 0;
		$ledger_total = 0;
		foreach ( array( 'reactions', 'saves', 'views', 'reports' ) as $kind ) {
			$table  = $wpdb->prefix . 'snp_' . $kind;
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( $exists !== $table ) { continue; }
			$cursor = 0;
			do {
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE post_id=%d AND id>%d ORDER BY id ASC LIMIT %d", absint( $legacy_id ), $cursor, self::PAGE_SIZE ), ARRAY_A );
				if ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) { $issues[] = 'interaction_source_query_failed_' . $kind; break; }
				foreach ( $rows as $row ) {
					$source_id = absint( $row['id'] ?? 0 );
					$cursor    = max( $cursor, $source_id );
					$source_total++;
					$canonical = self::canonical_row( $kind, $row, $target_id );
					if ( is_wp_error( $canonical ) ) { $issues[] = $canonical->get_error_code(); continue; }
					$existing = self::canonical_existing_row( $kind, $canonical );
					if ( ! is_array( $existing ) || ! self::row_belongs_to_target( $kind, $existing, $target_id ) ) { $issues[] = 'interaction_missing_' . $kind; continue; }
					$recorded = SNFLA_Mapping::interaction_source_recorded( $legacy_id, $kind, $source_id );
					if ( is_wp_error( $recorded ) ) { $issues[] = $recorded->get_error_code(); continue; }
					if ( ! $recorded ) { $issues[] = 'interaction_ledger_missing_' . $kind; continue; }
					if ( sanitize_key( $existing['status'] ?? '' ) !== sanitize_key( $canonical['status'] ?? '' ) ) { $issues[] = 'interaction_status_mismatch_' . $kind; continue; }
					if ( 'reactions' === $kind && sanitize_key( $existing['reaction_type'] ?? '' ) !== sanitize_key( $canonical['reaction_type'] ?? '' ) ) { $issues[] = 'interaction_type_mismatch_reactions'; continue; }
					if ( 'reports' === $kind && ( sanitize_key( $existing['reason'] ?? '' ) !== sanitize_key( $canonical['reason'] ?? '' ) || (string) ( $existing['notes'] ?? '' ) !== (string) ( $canonical['notes'] ?? '' ) ) ) { $issues[] = 'interaction_payload_mismatch_reports'; continue; }
					$checked++;
				}
			} while ( count( $rows ) === self::PAGE_SIZE );
			}

		// Reconcile the deterministic synthetic row used for the historical
		// meta-only _snp_views aggregate after every optional table row.
		$view_extra = self::legacy_view_meta_extra( $legacy_id );
		if ( is_wp_error( $view_extra ) ) {
			$issues[] = $view_extra->get_error_code();
		} elseif ( $view_extra > 0 ) {
			$source_total++;
			$synthetic = self::synthetic_view_row( $legacy_id, $view_extra );
			$canonical = self::canonical_row( 'views', $synthetic, $target_id );
			if ( is_wp_error( $canonical ) ) {
				$issues[] = $canonical->get_error_code();
			} else {
				$existing = self::canonical_existing_row( 'views', $canonical );
				if ( ! is_array( $existing ) || ! self::row_belongs_to_target( 'views', $existing, $target_id ) ) {
					$issues[] = 'interaction_missing_views_meta';
				} else {
					$recorded = SNFLA_Mapping::interaction_source_recorded( $legacy_id, 'views', 0 );
					if ( is_wp_error( $recorded ) ) {
						$issues[] = $recorded->get_error_code();
					} elseif ( ! $recorded ) {
						$issues[] = 'interaction_ledger_missing_views_meta';
					} elseif ( 'counted' !== sanitize_key( $existing['status'] ?? '' ) ) {
						$issues[] = 'interaction_status_mismatch_views_meta';
					} else {
						$checked++;
					}
				}
			}
		}

		$group_cursor = 0;
		do {
			$groups = SNFLA_Mapping::interaction_canonical_groups( $legacy_id, $group_cursor, self::PAGE_SIZE );
			if ( is_wp_error( $groups ) ) { $issues[] = $groups->get_error_code(); break; }
			foreach ( $groups as $ledger ) {
				$group_cursor = max( $group_cursor, absint( $ledger['id'] ?? 0 ) );
				$kind         = sanitize_key( $ledger['kind'] ?? '' );
				$table        = \Sabri\HomeNewsFeed\InteractionRepository::table_name( $kind );
				$canonical_id = absint( $ledger['canonical_row_id'] ?? 0 );
				$wpdb->last_error = '';
				$canonical    = '' !== $table ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id=%d", $canonical_id ), ARRAY_A ) : null;
				if ( ! empty( $wpdb->last_error ) ) { $issues[] = 'canonical_interaction_query_failed_' . $kind; continue; }
				if ( ! is_array( $canonical ) || ! self::row_belongs_to_target( $kind, $canonical, $target_id ) ) {
					$issues[] = 'interaction_provenance_mismatch_' . $kind;
					continue;
				}
				if ( 'views' === $kind ) {
					$original       = json_decode( (string) ( $ledger['original_json'] ?? '{}' ), true );
					if ( ! is_array( $original ) || JSON_ERROR_NONE !== json_last_error() ) { $issues[] = 'interaction_ledger_original_corrupt'; continue; }
					$baseline       = isset( $original['baseline'] ) && is_array( $original['baseline'] ) ? $original['baseline'] : array();
					$baseline_count = ! empty( $ledger['created_by_migration'] ) ? 0 : max( 1, absint( $baseline['view_count'] ?? 1 ) );
					$contribution = SNFLA_Mapping::interaction_contribution_total( $legacy_id, 'views', $canonical_id );
					if ( is_wp_error( $contribution ) ) { $issues[] = $contribution->get_error_code(); continue; }
					$expected       = $baseline_count + $contribution;
					if ( absint( $canonical['view_count'] ?? 0 ) !== $expected || 'counted' !== sanitize_key( $canonical['status'] ?? '' ) ) {
						$issues[] = 'interaction_aggregate_mismatch_views';
					}
				}
			}
		} while ( count( $groups ) === self::PAGE_SIZE );

		$ledger_cursor = 0;
		do {
			$ledger_rows = SNFLA_Mapping::interaction_rows( $legacy_id, $ledger_cursor, self::PAGE_SIZE );
			if ( is_wp_error( $ledger_rows ) ) { $issues[] = $ledger_rows->get_error_code(); break; }
			foreach ( $ledger_rows as $ledger ) {
				$ledger_cursor = max( $ledger_cursor, absint( $ledger['id'] ?? 0 ) );
				$ledger_total++;
			}
		} while ( count( $ledger_rows ) === self::PAGE_SIZE );

		return array(
			'valid'        => empty( $issues ) && $checked === $source_total && $ledger_total === $source_total,
			'checked'      => $checked,
			'source_total' => $source_total,
			'ledger_total' => $ledger_total,
			'issues'       => array_values( array_unique( array_map( 'sanitize_key', $issues ) ) ),
		);
	}

	public static function rollback( $legacy_id, $actor_id ) {
		global $wpdb;
		$current = SNFLA_Mapping::get_checked( $legacy_id );
		if ( is_wp_error( $current ) ) { return array( 'success' => false, 'updated' => 0, 'errors' => array( $current->get_error_code() ) ); }
		$target_id = absint( $current['target_id'] ?? 0 );
		$updated   = 0;
		$errors    = array();
		$fallback  = array( 'reactions' => 'removed', 'saves' => 'removed', 'views' => 'ignored', 'reports' => 'dismissed' );
		$allowed   = array( 'reactions' => array( 'active', 'removed' ), 'saves' => array( 'active', 'removed' ), 'views' => array( 'counted', 'ignored' ), 'reports' => array( 'open', 'triaged', 'resolved', 'dismissed', 'duplicate' ) );
		$cursor    = 0;
		do {
			$groups = SNFLA_Mapping::interaction_canonical_groups( $legacy_id, $cursor, self::PAGE_SIZE );
			if ( is_wp_error( $groups ) ) { $errors[] = $groups->get_error_code(); break; }
			foreach ( $groups as $row ) {
				$cursor       = max( $cursor, absint( $row['id'] ?? 0 ) );
				$kind         = sanitize_key( $row['kind'] ?? '' );
				$canonical_id = absint( $row['canonical_row_id'] ?? 0 );
				if ( ! isset( $fallback[ $kind ] ) ) { continue; }
				$table     = \Sabri\HomeNewsFeed\InteractionRepository::table_name( $kind );
				$wpdb->last_error = '';
				$canonical = '' !== $table ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id=%d", $canonical_id ), ARRAY_A ) : null;
				if ( ! empty( $wpdb->last_error ) ) { $errors[] = 'interaction_rollback_query_' . $kind; continue; }
				if ( ! is_array( $canonical ) || ! self::row_belongs_to_target( $kind, $canonical, $target_id ) ) { $errors[] = 'interaction_rollback_provenance_' . $kind; continue; }
				$original = json_decode( (string) ( $row['original_json'] ?? '{}' ), true );
				if ( ! is_array( $original ) || JSON_ERROR_NONE !== json_last_error() ) { $errors[] = 'interaction_ledger_original_corrupt'; continue; }
				$baseline = isset( $original['baseline'] ) && is_array( $original['baseline'] ) ? $original['baseline'] : array();
				if ( ! empty( $row['created_by_migration'] ) || ! empty( $original['created_by_migration'] ) ) {
					$restore = array( 'status' => $fallback[ $kind ] );
					if ( 'views' === $kind ) { $restore['view_count'] = 1; }
				} else {
					$status  = sanitize_key( $baseline['status'] ?? '' );
					$restore = array( 'status' => in_array( $status, $allowed[ $kind ], true ) ? $status : $fallback[ $kind ] );
					if ( 'reactions' === $kind ) { $restore['reaction_type'] = in_array( sanitize_key( $baseline['reaction_type'] ?? 'like' ), array( 'like', 'dislike' ), true ) ? sanitize_key( $baseline['reaction_type'] ) : 'like'; }
					if ( 'views' === $kind ) { $restore['view_count'] = max( 1, absint( $baseline['view_count'] ?? 1 ) ); }
				}
				$result = \Sabri\HomeNewsFeed\InteractionRepository::update_rows( $kind, $restore, array( 'id' => $canonical_id ) );
				if ( empty( $result['ok'] ) ) { $errors[] = 'interaction_rollback_' . $kind; } else { $updated++; }
			}
		} while ( count( $groups ) === self::PAGE_SIZE );
		if ( empty( $errors ) && ! SNFLA_Mapping::mark_interaction_ledger_rolled_back( $legacy_id ) ) { $errors[] = 'interaction_ledger_finalize_failed'; }
		if ( ! SNFLA_Audit::record( 'legacy_interactions_rolled_back', $actor_id, array( 'legacy_id' => absint( $legacy_id ), 'updated' => $updated, 'errors' => $errors ), 'legacy:' . absint( $legacy_id ) ) ) { $errors[] = 'interaction_rollback_audit_failed'; }
		return array( 'success' => empty( $errors ), 'updated' => $updated, 'errors' => array_values( array_unique( $errors ) ) );
	}

	private static function row_belongs_to_target( $kind, array $row, $target_id ) {
		if ( in_array( $kind, array( 'reactions', 'saves', 'views' ), true ) ) { return absint( $row['post_id'] ?? 0 ) === absint( $target_id ); }
		return 'reports' === $kind && 'post' === (string) ( $row['object_type'] ?? 'post' ) && absint( $row['object_id'] ?? 0 ) === absint( $target_id );
	}
}
