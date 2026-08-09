<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Database {
	const HANDOVER_OPTION = 'snfla_activation_handover';

	public static function tables() {
		global $wpdb;
		return array(
			'runs'               => $wpdb->prefix . 'snfla_runs',
			'map'                => $wpdb->prefix . 'snfla_map',
			'conflicts'          => $wpdb->prefix . 'snfla_conflicts',
			'audit'              => $wpdb->prefix . 'snfla_audit',
			'dry_run'            => $wpdb->prefix . 'snfla_dry_run',
			'interaction_ledger' => $wpdb->prefix . 'snfla_interaction_ledger',
		);
	}

	public static function activate( $network_wide = false ) {
		if ( $network_wide || ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'is_network_admin' ) && is_network_admin() ) ) {
			self::activation_failure( new WP_Error( 'snfla_network_activation_unsupported', 'File 04 must be activated per site so its migration evidence and table prefix remain site-scoped.' ) );
		}
		$actor_id = get_current_user_id();
		$preflight = SNFLA_Capabilities::activation_preflight();
		if ( is_wp_error( $preflight ) ) {
			self::activation_failure( $preflight );
		}
		SNFLA_Plugin::instance()->register_legacy_schema();

		$snapshot = self::capture_activation_handover( $actor_id );
		if ( is_wp_error( $snapshot ) ) {
			self::activation_failure( $snapshot );
		}
		if ( ! self::persist_option( self::HANDOVER_OPTION, $snapshot ) ) {
			self::activation_failure( new WP_Error( 'snfla_handover_persist_failed', 'The activation handover snapshot could not be persisted.' ) );
		}

		self::install();
		$schema = self::verify_schema();
		if ( is_wp_error( $schema ) ) {
			self::compensate_or_fail( $snapshot, $actor_id, $schema );
		}

		$failure = null;
		$deactivated = self::deactivate_obsolete_runtime( (array) ( $snapshot['obsolete_plugins'] ?? array() ) );
		if ( is_wp_error( $deactivated ) ) {
			$failure = $deactivated;
		}
		$page_quarantine = array();
		if ( ! $failure ) {
			$page_quarantine = self::quarantine_legacy_pages( (array) ( $snapshot['legacy_pages'] ?? array() ) );
			if ( is_wp_error( $page_quarantine ) ) {
				$failure = $page_quarantine;
			}
		}

		if ( $failure ) {
			self::compensate_or_fail( $snapshot, $actor_id, $failure );
		}

		$state_sentinel = new stdClass();
		$stored_state = get_option( SNFLA_Schema::STATE_OPTION, $state_sentinel );
		$version_sentinel = new stdClass();
		$stored_version = get_option( SNFLA_Schema::STATE_VERSION_OPTION, $version_sentinel );
		if ( $stored_state === $state_sentinel ) {
			if ( ! add_option( SNFLA_Schema::STATE_OPTION, 'legacy_active', '', false ) ) { self::compensate_or_fail( $snapshot, $actor_id, new WP_Error( 'snfla_lifecycle_state_init_failed', 'Initial lifecycle state could not be persisted.' ) ); }
		} elseif ( ! in_array( sanitize_key( (string) $stored_state ), SNFLA_Schema::states(), true ) ) {
			self::compensate_or_fail( $snapshot, $actor_id, new WP_Error( 'snfla_lifecycle_state_corrupt', 'A corrupt persisted lifecycle state blocks activation.' ) );
		}
		if ( $stored_version === $version_sentinel ) {
			if ( ! add_option( SNFLA_Schema::STATE_VERSION_OPTION, 1, '', false ) ) { self::compensate_or_fail( $snapshot, $actor_id, new WP_Error( 'snfla_lifecycle_version_init_failed', 'Initial lifecycle version could not be persisted.' ) ); }
		} elseif ( SNFLA_Schema::version() < 1 ) {
			self::compensate_or_fail( $snapshot, $actor_id, new WP_Error( 'snfla_lifecycle_version_corrupt', 'A corrupt persisted lifecycle version blocks activation.' ) );
		}
		if ( ! self::persist_option( 'snfla_schema_version', SNFLA_SCHEMA_VERSION ) || ! self::persist_option( 'snfla_plugin_version', SNFLA_VERSION ) || ! self::persist_option( 'snfla_schema_health', array( 'ok' => true, 'code' => '', 'checked_at_utc' => gmdate( 'Y-m-d H:i:s' ) ) ) ) {
			self::compensate_or_fail( $snapshot, $actor_id, new WP_Error( 'snfla_activation_version_evidence_failed', 'Activation version/schema-health evidence could not be persisted.' ) );
		}

		$final_handover = $snapshot;
		$final_handover['deactivated_plugins'] = $deactivated;
		$final_handover['quarantined_pages']   = $page_quarantine;
		$final_handover['completed_at_utc']    = gmdate( 'Y-m-d H:i:s' );
		$final_handover = SNFLA_Integrity::sign_evidence( $final_handover );
		if ( ! self::persist_option( self::HANDOVER_OPTION, $final_handover ) ) {
			self::compensate_or_fail( $snapshot, $actor_id, new WP_Error( 'snfla_handover_finalize_failed', 'The activation handover could not be finalized safely.' ) );
		}

		SNFLA_Plugin::instance()->register_legacy_schema();
		self::refresh_runtime_state();
		if ( 'retired' !== (string) get_option( SNFLA_Schema::STATE_OPTION, 'legacy_active' ) && ! wp_next_scheduled( 'snfla_daily_integrity_check' ) ) {
			$scheduled = wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'snfla_daily_integrity_check', array(), true );
			if ( is_wp_error( $scheduled ) || false === $scheduled ) {
				self::compensate_or_fail( $snapshot, $actor_id, new WP_Error( 'snfla_integrity_schedule_failed', 'The mandatory daily integrity check could not be scheduled.' ) );
			}
		}
		if ( ! SNFLA_Audit::record( 'adapter_activation_handover_completed', $actor_id, array( 'plugin_count' => count( $deactivated ), 'page_count' => count( $page_quarantine ), 'schema_version' => SNFLA_SCHEMA_VERSION ) ) ) {
			self::compensate_or_fail( $snapshot, $actor_id, new WP_Error( 'snfla_activation_audit_failed', 'Activation was reverted because its audit evidence could not be written.' ) );
		}
		set_transient( 'snfla_activation_notice', '1', 120 );
	}

	private static function compensate_or_fail( array $snapshot, $actor_id, WP_Error $cause ) {
		$compensation = self::restore_handover_snapshot( $snapshot, $actor_id, true );
		if ( is_wp_error( $compensation ) ) {
			self::activation_failure(
				new WP_Error(
					'snfla_activation_compensation_failed',
					'File 04 activation failed and its automatic compensation was incomplete. Manual recovery is required before retrying.',
					array( 'cause' => $cause->get_error_code(), 'compensation' => $compensation->get_error_code() )
				)
			);
		}
		self::activation_failure( $cause );
	}

	private static function activation_failure( WP_Error $error ) {
		if ( function_exists( 'deactivate_plugins' ) ) {
			deactivate_plugins( plugin_basename( SNFLA_FILE ), true );
		}
		wp_die( esc_html( $error->get_error_message() ), esc_html__( 'File 04 activation blocked', SNFLA_TEXT_DOMAIN ), array( 'response' => 500 ) );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'snfla_daily_integrity_check' );
	}

	/** Public-safe page handover evidence without legacy page bodies or titles. */
	public static function public_page_quarantine_status() {
		$handover = get_option( self::HANDOVER_OPTION, array() );
		if ( ! SNFLA_Integrity::evidence_valid( $handover ) ) { return array(); }
		$rows = isset( $handover['quarantined_pages'] ) && is_array( $handover['quarantined_pages'] ) ? $handover['quarantined_pages'] : array();
		$result = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! is_array( $row ) ) { continue; }
			$result[] = array(
				'page_id'            => absint( $row['page_id'] ?? 0 ),
				'original_status'    => sanitize_key( (string) ( $row['original_status'] ?? '' ) ),
				'quarantined_status' => sanitize_key( (string) ( $row['quarantined_status'] ?? '' ) ),
				'content_checksum'   => preg_match( '/^[a-f0-9]{64}$/', (string) ( $row['content_checksum'] ?? '' ) ) ? (string) $row['content_checksum'] : '',
				'slug_checksum'      => preg_match( '/^[a-f0-9]{64}$/', (string) ( $row['slug_checksum'] ?? '' ) ) ? (string) $row['slug_checksum'] : '',
			);
		}
		return $result;
	}

	public static function maybe_upgrade() {
		$stored_version = (string) get_option( 'snfla_schema_version', '' );
		if ( SNFLA_SCHEMA_VERSION === $stored_version && self::schema_healthy() ) { return; }
		if ( ! self::acquire_lock( 'schema_upgrade', 5 ) ) {
			self::persist_option( 'snfla_schema_health', array( 'ok' => false, 'code' => 'snfla_schema_upgrade_locked', 'checked_at_utc' => gmdate( 'Y-m-d H:i:s' ) ) );
			return;
		}
		try {
		self::install();
		$verified = self::verify_schema();
		if ( is_wp_error( $verified ) ) {
			self::persist_option( 'snfla_schema_health', array( 'ok' => false, 'code' => $verified->get_error_code(), 'checked_at_utc' => gmdate( 'Y-m-d H:i:s' ) ) );
			return;
		}
		self::persist_option( 'snfla_schema_version', SNFLA_SCHEMA_VERSION );
		self::persist_option( 'snfla_schema_health', array( 'ok' => true, 'checked_at_utc' => gmdate( 'Y-m-d H:i:s' ) ) );
		} finally { self::release_lock( 'schema_upgrade' ); }
	}

	public static function schema_healthy() {
		static $verified_this_request = null;
		if ( SNFLA_SCHEMA_VERSION !== (string) get_option( 'snfla_schema_version', '' ) ) { return false; }
		if ( null !== $verified_this_request ) { return $verified_this_request; }
		$verified = self::verify_schema();
		$verified_this_request = ! is_wp_error( $verified );
		self::persist_option( 'snfla_schema_health', array( 'ok' => $verified_this_request, 'code' => is_wp_error( $verified ) ? $verified->get_error_code() : '', 'checked_at_utc' => gmdate( 'Y-m-d H:i:s' ) ) );
		return $verified_this_request;
	}

	private static function capture_activation_handover( $actor_id ) {
		$plugins = self::discover_obsolete_plugins();
		$pages   = self::discover_legacy_pages();
		if ( is_wp_error( $pages ) ) {
			return $pages;
		}
		$option_names = array( SNFLA_Schema::STATE_OPTION, SNFLA_Schema::STATE_VERSION_OPTION, 'snfla_schema_version', 'snfla_plugin_version', 'snfla_legacy_page_quarantine', self::HANDOVER_OPTION );
		$adapter_options = array();
		foreach ( $option_names as $option_name ) {
			$sentinel = new stdClass();
			$value = get_option( $option_name, $sentinel );
			$adapter_options[ $option_name ] = array( 'exists' => $value !== $sentinel, 'value' => $value !== $sentinel ? $value : null );
		}
		$table_baseline = self::capture_table_baseline();
		if ( is_wp_error( $table_baseline ) ) { return $table_baseline; }
		$captured_inventory = SNFLA_Inventory::capture();
		if ( is_wp_error( $captured_inventory ) ) { return $captured_inventory; }
		$rewrite_rules_encoded = wp_json_encode( SNFLA_Checksum::canonicalize( (array) get_option( 'rewrite_rules', array() ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $rewrite_rules_encoded ) ) { return new WP_Error( 'snfla_rewrite_rules_evidence_encoding_failed', 'Rewrite-rule handover evidence could not be encoded safely.' ); }
		$snapshot = array(
			'schema'                 => 4,
			'captured_at_utc'        => gmdate( 'Y-m-d H:i:s' ),
			'actor_digest'           => SNFLA_Audit::actor_digest( $actor_id ),
			'obsolete_plugins'       => $plugins,
			'legacy_pages'           => $pages,
			'rewrite_rules_checksum' => hash( 'sha256', $rewrite_rules_encoded ),
			'source_data_signature'  => SNFLA_Inventory::data_signature( $captured_inventory ),
			'adapter_options'        => $adapter_options,
			'adapter_table_baseline' => $table_baseline,
		);
		return SNFLA_Integrity::sign_evidence( $snapshot );
	}


	private static function capture_table_baseline() {
		global $wpdb;
		$baseline = array();
		foreach ( self::tables() as $key => $table ) {
			$wpdb->last_error = '';
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( ! empty( $wpdb->last_error ) ) {
				return new WP_Error( 'snfla_table_baseline_failed', 'The pre-activation adapter table baseline could not be captured safely.', array( 'table_key' => sanitize_key( $key ) ) );
			}
			$baseline[ sanitize_key( $key ) ] = array( 'table_hash' => hash( 'sha256', $table ), 'existed' => $exists === $table );
		}
		return $baseline;
	}

	private static function discover_obsolete_plugins() {
		if ( ! function_exists( 'is_plugin_active' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$current  = plugin_basename( SNFLA_FILE );
		$active   = (array) get_option( 'active_plugins', array() );
		$sitewide = is_multisite() ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array();
		$records  = array();
		foreach ( array_unique( array_merge( $active, array_keys( $sitewide ) ) ) as $plugin ) {
			$plugin = sanitize_text_field( (string) $plugin );
			if ( $plugin === $current || '' === $plugin ) {
				continue;
			}
			$matches = 'sabri-news-publishing.php' === basename( $plugin );
			if ( ! $matches && function_exists( 'get_plugin_data' ) ) {
				$file = WP_PLUGIN_DIR . '/' . $plugin;
				if ( is_file( $file ) ) {
					$data = get_plugin_data( $file, false, false );
					$matches = 'sabri-news-publishing' === sanitize_key( (string) ( $data['TextDomain'] ?? '' ) ) || false !== stripos( (string) ( $data['Name'] ?? '' ), 'Sabri News Feed and Publishing' );
				}
			}
			if ( ! $matches ) {
				continue;
			}
			$records[] = array( 'plugin' => $plugin, 'network' => isset( $sitewide[ $plugin ] ) );
		}
		return $records;
	}

	private static function discover_legacy_pages() {
		global $wpdb;
		$ids = array();
		foreach ( (array) get_option( 'snp_page_map', array() ) as $page_id ) {
			$page_id = absint( $page_id );
			if ( $page_id > 0 ) {
				$ids[ $page_id ] = true;
			}
		}
		$patterns = array( '[sabri_news_home', '[sabri_news_feed', '[sabri_publish_form', '[sabri_publication_feed', '[sabri_my_publication_reports' );
		$cursor = 0;
		do {
			$where = array();
			$args  = array( 'page', $cursor );
			foreach ( $patterns as $pattern ) {
				$where[] = 'post_content LIKE %s';
				$args[]  = '%' . $wpdb->esc_like( $pattern ) . '%';
			}
			$args[] = 250;
			$sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type=%s AND ID>%d AND (" . implode( ' OR ', $where ) . ') ORDER BY ID ASC LIMIT %d';
			$wpdb->last_error = '';
			$batch = $wpdb->get_col( $wpdb->prepare( $sql, $args ) );
			if ( null === $batch || ! empty( $wpdb->last_error ) ) {
				return new WP_Error( 'snfla_legacy_page_discovery_failed', 'Legacy File 04 pages could not be inventoried safely.' );
			}
			foreach ( (array) $batch as $page_id ) {
				$page_id = absint( $page_id );
				if ( $page_id > 0 ) {
					$ids[ $page_id ] = true;
					$cursor = max( $cursor, $page_id );
				}
			}
		} while ( count( (array) $batch ) === 250 );

		$records = array();
		foreach ( array_keys( $ids ) as $page_id ) {
			$page = get_post( $page_id );
			if ( ! $page instanceof WP_Post || 'page' !== $page->post_type ) { continue; }
			$has_legacy_shortcode = false;
			foreach ( array( 'sabri_news_home', 'sabri_news_feed', 'sabri_publish_form', 'sabri_publication_feed', 'sabri_my_publication_reports' ) as $shortcode ) {
				if ( has_shortcode( (string) $page->post_content, $shortcode ) ) { $has_legacy_shortcode = true; break; }
			}
			if ( ! $has_legacy_shortcode ) { continue; }
			$records[] = array(
				'page_id'          => $page_id,
				'original_status'  => sanitize_key( $page->post_status ),
				'original_title'   => (string) $page->post_title,
				'original_content' => (string) $page->post_content,
				'original_slug'    => (string) $page->post_name,
				'content_checksum' => hash( 'sha256', (string) $page->post_content ),
				'slug_checksum'    => hash( 'sha256', (string) $page->post_name ),
			);
		}
		usort( $records, static function ( $a, $b ) { return absint( $a['page_id'] ) <=> absint( $b['page_id'] ); } );
		return $records;
	}

	private static function deactivate_obsolete_runtime( array $records ) {
		if ( ! function_exists( 'deactivate_plugins' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			return empty( $records ) ? array() : new WP_Error( 'snfla_plugin_api_unavailable', 'WordPress plugin management APIs are unavailable.' );
		}
		$deactivated = array();
		foreach ( $records as $record ) {
			$plugin  = sanitize_text_field( (string) ( $record['plugin'] ?? '' ) );
			$network = ! empty( $record['network'] );
			if ( '' === $plugin ) { continue; }
			if ( $network ) {
				return new WP_Error( 'snfla_network_legacy_runtime_requires_network_operator', 'A network-wide legacy publishing runtime was detected. A per-site File 04 activation must not mutate network-wide plugin state.', array( 'plugin_hash' => hash( 'sha256', $plugin ) ) );
			}
			deactivate_plugins( $plugin, true, false );
			$still_active = $network ? is_plugin_active_for_network( $plugin ) : is_plugin_active( $plugin );
			if ( $still_active ) {
				return new WP_Error( 'snfla_obsolete_runtime_deactivation_failed', 'The obsolete File 04 publishing runtime could not be disabled safely.', array( 'plugin_hash' => hash( 'sha256', $plugin ) ) );
			}
			$deactivated[] = array( 'plugin' => $plugin, 'network' => $network, 'plugin_hash' => hash( 'sha256', $plugin ) );
		}
		return $deactivated;
	}

	private static function quarantine_legacy_pages( array $records ) {
		$quarantined = array();
		foreach ( $records as $record ) {
			$page_id = absint( $record['page_id'] ?? 0 );
			$page = $page_id > 0 ? get_post( $page_id ) : null;
			if ( ! $page instanceof WP_Post || 'page' !== $page->post_type ) {
				continue;
			}
			$original_status = sanitize_key( (string) ( $record['original_status'] ?? '' ) );
			$content_matches = hash_equals( (string) ( $record['content_checksum'] ?? '' ), hash( 'sha256', (string) $page->post_content ) );
			$slug_matches = hash_equals( (string) ( $record['slug_checksum'] ?? '' ), hash( 'sha256', (string) $page->post_name ) );
			$status_matches = '' !== $original_status && sanitize_key( (string) $page->post_status ) === $original_status;
			if ( ! $content_matches || ! $slug_matches || ! $status_matches ) {
				return new WP_Error( 'snfla_legacy_page_changed', 'A legacy File 04 page changed after the activation snapshot and was not quarantined.', array( 'page_id' => $page_id ) );
			}
			if ( in_array( $page->post_status, array( 'publish', 'future' ), true ) ) {
				$result = wp_update_post( array( 'ID' => $page_id, 'post_status' => 'private' ), true );
				if ( is_wp_error( $result ) || ! $result ) {
					return new WP_Error( 'snfla_legacy_page_quarantine_failed', 'A legacy File 04 public page could not be quarantined safely.', array( 'page_id' => $page_id ) );
				}
			}
			$quarantined[] = array_merge( $record, array( 'quarantined_status' => in_array( $original_status, array( 'publish', 'future' ), true ) ? 'private' : $original_status ) );
		}
		if ( ! self::persist_option( 'snfla_legacy_page_quarantine', $quarantined ) ) {
			return new WP_Error( 'snfla_legacy_page_quarantine_evidence_failed', 'Legacy page quarantine completed but its exact restoration evidence could not be persisted.' );
		}
		return $quarantined;
	}

	public static function restore_activation_handover( $actor_id, $confirmation ) {
		$actor_id = absint( $actor_id );
		if ( $actor_id <= 0 || $actor_id !== get_current_user_id() || ! current_user_can( 'sabri_feed_run_migrations' ) || ! SNFLA_Capabilities::file21_ready() || ! \Sabri\HomeNewsFeed\CanonicalIdentityAdapter::current_action_ready( $actor_id ) ) {
			return new WP_Error( 'snfla_handover_restore_authorization_failed', 'Fresh File 00 and File 21 migration authority is required for a full handover restore.', array( 'status' => 403 ) );
		}
		if ( 'RESTORE_FILE04_HANDOVER' !== (string) $confirmation ) {
			return new WP_Error( 'snfla_handover_confirmation_required', 'Exact handover restore confirmation is required.', array( 'status' => 400 ) );
		}
		$evidence = get_option( self::HANDOVER_OPTION, array() );
		if ( ! SNFLA_Integrity::evidence_valid( $evidence ) ) {
			return new WP_Error( 'snfla_handover_evidence_invalid', 'The activation handover evidence is missing or invalid.', array( 'status' => 412 ) );
		}
		$current_inventory = SNFLA_Inventory::capture();
		if ( is_wp_error( $current_inventory ) ) { return $current_inventory; }
		$current_signature = SNFLA_Inventory::data_signature( $current_inventory );
		if ( empty( $evidence['source_data_signature'] ) || ! hash_equals( (string) $evidence['source_data_signature'], $current_signature ) ) {
			return new WP_Error( 'snfla_handover_source_changed', 'The legacy source changed after the activation handover; automatic full handover restore is blocked.', array( 'status' => 409 ) );
		}
		$result = self::restore_handover_snapshot( $evidence, $actor_id, false );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! SNFLA_Audit::record( 'activation_handover_restored', $actor_id, $result ) ) {
			return new WP_Error( 'snfla_handover_restore_audit_failed', 'Handover restore completed but its audit evidence could not be written.', array( 'status' => 500 ) );
		}
		return $result;
	}

	private static function restore_handover_snapshot( array $snapshot, $actor_id, $activation_compensation ) {
		if ( ! function_exists( 'activate_plugin' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$pages = ! empty( $snapshot['quarantined_pages'] ) ? (array) $snapshot['quarantined_pages'] : (array) ( $snapshot['legacy_pages'] ?? array() );
		$page_preflight_errors = array();
		foreach ( $pages as $record ) {
			$page_id = absint( $record['page_id'] ?? 0 );
			$page    = $page_id > 0 ? get_post( $page_id ) : null;
			if ( ! $page instanceof WP_Post || 'page' !== $page->post_type ) {
				continue;
			}
			$status               = sanitize_key( (string) ( $record['original_status'] ?? 'draft' ) );
			$expected_quarantined = sanitize_key( (string) ( $record['quarantined_status'] ?? ( in_array( $status, array( 'publish', 'future' ), true ) ? 'private' : $status ) ) );
			$content_matches      = hash_equals( (string) ( $record['content_checksum'] ?? '' ), hash( 'sha256', (string) $page->post_content ) );
			$slug_matches         = hash_equals( (string) ( $record['slug_checksum'] ?? '' ), hash( 'sha256', (string) $page->post_name ) );
			$status_matches       = $activation_compensation ? in_array( $page->post_status, array( $status, $expected_quarantined ), true ) : $page->post_status === $expected_quarantined;
			if ( ! $content_matches || ! $slug_matches || ! $status_matches ) {
				$page_preflight_errors[ $page_id ] = ! $content_matches ? 'page_content_changed' : ( ! $slug_matches ? 'page_slug_changed' : 'page_status_changed_after_handover' );
			}
		}
		if ( ! empty( $page_preflight_errors ) ) {
			return new WP_Error( 'snfla_handover_page_preflight_failed', 'Legacy page state changed after the handover snapshot; restoration was blocked before mutation.', array( 'status' => 409, 'page_errors' => $page_preflight_errors ) );
		}

		$plugin_errors    = array();
		$restored_plugins = array();
		$plugins = ! empty( $snapshot['deactivated_plugins'] ) ? (array) $snapshot['deactivated_plugins'] : (array) ( $snapshot['obsolete_plugins'] ?? array() );
		foreach ( $plugins as $record ) {
			$plugin  = sanitize_text_field( (string) ( $record['plugin'] ?? '' ) );
			$network = ! empty( $record['network'] );
			if ( '' === $plugin || ! function_exists( 'activate_plugin' ) ) {
				if ( '' !== $plugin ) { $plugin_errors[ hash( 'sha256', $plugin ) ] = 'plugin_api_unavailable'; }
				continue;
			}
			$result = activate_plugin( $plugin, '', $network, true );
			if ( is_wp_error( $result ) ) {
				$plugin_errors[ hash( 'sha256', $plugin ) ] = $result->get_error_code();
				continue;
			}
			$active = $network ? is_plugin_active_for_network( $plugin ) : is_plugin_active( $plugin );
			if ( ! $active ) {
				$plugin_errors[ hash( 'sha256', $plugin ) ] = 'plugin_not_active_after_restore';
				continue;
			}
			$restored_plugins[] = hash( 'sha256', $plugin );
		}

		$page_errors    = array();
		$restored_pages = array();
		foreach ( $pages as $record ) {
			$page_id = absint( $record['page_id'] ?? 0 );
			$page    = $page_id > 0 ? get_post( $page_id ) : null;
			if ( ! $page instanceof WP_Post || 'page' !== $page->post_type ) {
				continue;
			}
			$postarr = array(
				'ID'           => $page_id,
				'post_status'  => sanitize_key( (string) ( $record['original_status'] ?? 'draft' ) ),
				'post_title'   => (string) ( $record['original_title'] ?? $page->post_title ),
				'post_content' => (string) ( $record['original_content'] ?? $page->post_content ),
				'post_name'    => (string) ( $record['original_slug'] ?? $page->post_name ),
			);
			$result = wp_update_post( wp_slash( $postarr ), true );
			if ( is_wp_error( $result ) || ! $result ) {
				$page_errors[ $page_id ] = 'page_restore_failed';
				continue;
			}
			$restored = get_post( $page_id );
			if ( ! $restored instanceof WP_Post || ! hash_equals( (string) ( $record['content_checksum'] ?? '' ), hash( 'sha256', (string) $restored->post_content ) ) || ! hash_equals( (string) ( $record['slug_checksum'] ?? '' ), hash( 'sha256', (string) $restored->post_name ) ) || sanitize_key( $restored->post_status ) !== sanitize_key( $postarr['post_status'] ) ) {
				$page_errors[ $page_id ] = 'page_restore_verification_failed';
				continue;
			}
			$restored_pages[] = $page_id;
		}

		$option_errors = array();
		foreach ( (array) ( $snapshot['adapter_options'] ?? array() ) as $option_name => $option_record ) {
			$option_name = sanitize_key( (string) $option_name );
			if ( '' === $option_name || ! is_array( $option_record ) ) { continue; }
			if ( ! empty( $option_record['exists'] ) ) {
				$current = get_option( $option_name, null );
				if ( $current !== $option_record['value'] && ! update_option( $option_name, $option_record['value'], false ) ) { $option_errors[ $option_name ] = 'option_restore_failed'; }
			} elseif ( false === delete_option( $option_name ) && false !== get_option( $option_name, false ) ) {
				$option_errors[ $option_name ] = 'option_delete_failed';
			}
		}
		$table_errors = $activation_compensation ? self::remove_new_activation_tables( (array) ( $snapshot['adapter_table_baseline'] ?? array() ) ) : array();
		self::refresh_runtime_state();
		$result = array(
			'activation_compensation' => (bool) $activation_compensation,
			'restored_pages'          => $restored_pages,
			'restored_plugin_hashes'  => $restored_plugins,
			'page_errors'             => $page_errors,
			'plugin_errors'           => $plugin_errors,
			'option_errors'           => $option_errors,
			'table_errors'            => $table_errors,
			'actor_digest'            => SNFLA_Audit::actor_digest( $actor_id ),
			'restored_at_utc'         => gmdate( 'Y-m-d H:i:s' ),
		);
		return empty( $page_errors ) && empty( $plugin_errors ) && empty( $option_errors ) && empty( $table_errors ) ? $result : new WP_Error( 'snfla_handover_restore_partial', 'The activation handover could not be restored completely.', array( 'status' => 409, 'result' => SNFLA_Audit::redact( $result ) ) );
	}


	private static function remove_new_activation_tables( array $baseline ) {
		global $wpdb;
		$errors = array();
		foreach ( self::tables() as $key => $table ) {
			$record = isset( $baseline[ $key ] ) && is_array( $baseline[ $key ] ) ? $baseline[ $key ] : array();
			if ( ! empty( $record['existed'] ) ) { continue; }
			if ( ! isset( $record['table_hash'] ) || ! hash_equals( (string) $record['table_hash'], hash( 'sha256', $table ) ) ) {
				$errors[ $key ] = 'table_baseline_invalid';
				continue;
			}
			$wpdb->last_error = '';
			$result = $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
			if ( false === $result || ! empty( $wpdb->last_error ) ) { $errors[ $key ] = 'table_drop_failed'; }
		}
		return $errors;
	}

	private static function refresh_runtime_state() {
		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}
		do_action( 'sabri_hnf_invalidate_cache' );
		do_action( 'snfla_targeted_cache_invalidation' );
		do_action( 'snfla_handover_state_changed' );
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$t       = self::tables();

		dbDelta( "CREATE TABLE {$t['runs']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			run_uuid char(36) NOT NULL,
			operation varchar(32) NOT NULL,
			status varchar(24) NOT NULL,
			actor_digest char(64) NOT NULL,
			idempotency_hash char(64) NOT NULL,
			source_signature char(64) NOT NULL,
			checkpoint_json longtext NOT NULL,
			summary_json longtext NOT NULL,
			started_at datetime NOT NULL,
			finished_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY run_uuid (run_uuid),
			UNIQUE KEY operation_idempotency (operation,idempotency_hash),
			KEY operation_status (operation,status),
			KEY started_at (started_at)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$t['map']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			legacy_id bigint(20) unsigned NOT NULL,
			target_id bigint(20) unsigned NOT NULL DEFAULT 0,
			target_type varchar(32) NOT NULL DEFAULT '',
			status varchar(32) NOT NULL,
			source_checksum char(64) NOT NULL,
			target_checksum char(64) NOT NULL DEFAULT '',
			run_uuid char(36) NOT NULL,
			interaction_ledger_json longtext NOT NULL,
			last_error_code varchar(96) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY legacy_id (legacy_id),
			KEY target_id (target_id),
			KEY run_uuid (run_uuid),
			KEY status (status)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$t['conflicts']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			legacy_id bigint(20) unsigned NOT NULL DEFAULT 0,
			conflict_code varchar(96) NOT NULL,
			severity varchar(16) NOT NULL,
			fingerprint char(64) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			redacted_context_json longtext NOT NULL,
			run_uuid char(36) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			resolved_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY conflict_fingerprint (fingerprint),
			KEY legacy_status (legacy_id,status),
			KEY severity_status (severity,status)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$t['audit']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_uuid char(36) NOT NULL,
			actor_digest char(64) NOT NULL,
			action varchar(96) NOT NULL,
			object_ref varchar(128) NOT NULL DEFAULT '',
			context_json longtext NOT NULL,
			prev_hash char(64) NOT NULL DEFAULT '',
			event_hash char(64) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_uuid (event_uuid),
			KEY action_created (action,created_at),
			KEY object_ref (object_ref)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$t['dry_run']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			run_uuid char(36) NOT NULL,
			source_signature char(64) NOT NULL,
			legacy_id bigint(20) unsigned NOT NULL,
			source_checksum char(64) NOT NULL,
			target_type varchar(32) NOT NULL DEFAULT 'auto',
			eligible tinyint(1) unsigned NOT NULL DEFAULT 0,
			conflict_codes_json longtext NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY run_legacy (run_uuid,legacy_id),
			KEY source_legacy (source_signature,legacy_id),
			KEY run_uuid (run_uuid),
			KEY eligible (eligible)
		) {$charset};" );

		$dry_indexes = (array) $wpdb->get_results( "SHOW INDEX FROM `{$t['dry_run']}`", ARRAY_A );
		$has_legacy_unique = false;
		foreach ( $dry_indexes as $index ) {
			if ( 'source_legacy' === (string) ( $index['Key_name'] ?? '' ) && 0 === absint( $index['Non_unique'] ?? 1 ) ) {
				$has_legacy_unique = true;
				break;
			}
		}
		if ( $has_legacy_unique ) {
			$wpdb->query( "ALTER TABLE `{$t['dry_run']}` DROP INDEX `source_legacy`" );
		}
		$dry_indexes = (array) $wpdb->get_results( "SHOW INDEX FROM `{$t['dry_run']}`", ARRAY_A );
		$index_names = array();
		foreach ( $dry_indexes as $index ) {
			$index_names[ (string) ( $index['Key_name'] ?? '' ) ] = true;
		}
		if ( empty( $index_names['run_legacy'] ) ) {
			$wpdb->query( "ALTER TABLE `{$t['dry_run']}` ADD UNIQUE KEY `run_legacy` (`run_uuid`,`legacy_id`)" );
		}
		if ( empty( $index_names['source_legacy'] ) ) {
			$wpdb->query( "ALTER TABLE `{$t['dry_run']}` ADD KEY `source_legacy` (`source_signature`,`legacy_id`)" );
		}

		dbDelta( "CREATE TABLE {$t['interaction_ledger']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			legacy_id bigint(20) unsigned NOT NULL,
			target_id bigint(20) unsigned NOT NULL,
			kind varchar(24) NOT NULL,
			source_row_id bigint(20) unsigned NOT NULL,
			canonical_row_id bigint(20) unsigned NOT NULL,
			contribution_count bigint(20) unsigned NOT NULL DEFAULT 0,
			original_json longtext NOT NULL,
			created_by_migration tinyint(1) unsigned NOT NULL DEFAULT 0,
			status varchar(24) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_row (legacy_id,kind,source_row_id),
			KEY target_kind (target_id,kind),
			KEY canonical_row (kind,canonical_row_id),
			KEY status (status)
		) {$charset};" );
	}

	public static function verify_schema() {
		global $wpdb;
		$required = array(
			'runs' => array( 'id','run_uuid','operation','status','actor_digest','idempotency_hash','source_signature','checkpoint_json','summary_json','started_at','finished_at' ),
			'map' => array( 'id','legacy_id','target_id','target_type','status','source_checksum','target_checksum','run_uuid','interaction_ledger_json','last_error_code','created_at','updated_at' ),
			'conflicts' => array( 'id','legacy_id','conflict_code','severity','fingerprint','status','redacted_context_json','run_uuid','created_at','resolved_at' ),
			'audit' => array( 'id','event_uuid','actor_digest','action','object_ref','context_json','prev_hash','event_hash','created_at' ),
			'dry_run' => array( 'id','run_uuid','source_signature','legacy_id','source_checksum','target_type','eligible','conflict_codes_json','created_at' ),
			'interaction_ledger' => array( 'id','legacy_id','target_id','kind','source_row_id','canonical_row_id','contribution_count','original_json','created_by_migration','status','created_at','updated_at' ),
		);
		$tables = self::tables();
		$errors = array();
		foreach ( $required as $key => $columns ) {
			$table = $tables[ $key ];
			$wpdb->last_error = '';
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( ! empty( $wpdb->last_error ) ) {
				$errors[] = $key . '_table_query_failed';
				continue;
			}
			if ( $exists !== $table ) {
				$errors[] = $key . '_table_missing';
				continue;
			}
			$wpdb->last_error = '';
			$actual = array_map( 'strtolower', (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ) );
			if ( ! empty( $wpdb->last_error ) ) {
				$errors[] = $key . '_column_query_failed';
				continue;
			}
			foreach ( $columns as $column ) {
				if ( ! in_array( strtolower( $column ), $actual, true ) ) {
					$errors[] = $key . '_' . $column . '_missing';
				}
			}
		}
		$required_indexes = array(
			'runs' => array( 'run_uuid' => array( true, array('run_uuid') ), 'operation_idempotency' => array( true, array('operation','idempotency_hash') ) ),
			'map' => array( 'legacy_id' => array( true, array('legacy_id') ) ),
			'conflicts' => array( 'conflict_fingerprint' => array( true, array('fingerprint') ) ),
			'audit' => array( 'event_uuid' => array( true, array('event_uuid') ) ),
			'dry_run' => array( 'run_legacy' => array( true, array('run_uuid','legacy_id') ), 'source_legacy' => array( false, array('source_signature','legacy_id') ) ),
			'interaction_ledger' => array( 'source_row' => array( true, array('legacy_id','kind','source_row_id') ), 'canonical_row' => array( false, array('kind','canonical_row_id') ) ),
		);
		foreach ( $required_indexes as $key => $index_rules ) {
			$wpdb->last_error = '';
			$rows = (array) $wpdb->get_results( "SHOW INDEX FROM `{$tables[ $key ]}`", ARRAY_A );
			if ( ! empty( $wpdb->last_error ) ) {
				$errors[] = $key . '_index_query_failed';
				continue;
			}
			$actual_indexes = array();
			foreach ( $rows as $index ) {
				$name = (string) ( $index['Key_name'] ?? '' );
				if ( '' === $name ) { continue; }
				if ( ! isset( $actual_indexes[ $name ] ) ) { $actual_indexes[ $name ] = array( 'unique' => 0 === absint( $index['Non_unique'] ?? 1 ), 'columns' => array() ); }
				$seq = max( 1, absint( $index['Seq_in_index'] ?? 1 ) );
				$actual_indexes[ $name ]['columns'][ $seq ] = strtolower( (string) ( $index['Column_name'] ?? '' ) );
			}
			foreach ( $actual_indexes as &$index ) { ksort( $index['columns'], SORT_NUMERIC ); $index['columns'] = array_values( $index['columns'] ); } unset( $index );
			foreach ( $index_rules as $name => $rule ) {
				$must_be_unique = ! empty( $rule[0] ); $required_columns = array_map( 'strtolower', (array) ( $rule[1] ?? array() ) );
				if ( ! array_key_exists( $name, $actual_indexes ) ) { $errors[] = $key . '_' . $name . '_index_missing'; }
				elseif ( $must_be_unique && empty( $actual_indexes[ $name ]['unique'] ) ) { $errors[] = $key . '_' . $name . '_unique_missing'; }
				elseif ( $required_columns !== $actual_indexes[ $name ]['columns'] ) { $errors[] = $key . '_' . $name . '_columns_mismatch'; }
			}
		}
		return empty( $errors ) ? true : new WP_Error( 'snfla_schema_verification_failed', 'File 04 database schema verification failed.', array( 'errors' => array_values( array_unique( $errors ) ) ) );
	}

	private static function persist_option( $name, $value ) {
		$current = get_option( $name, null );
		if ( $current === $value ) {
			return true;
		}
		return update_option( $name, $value, false );
	}

	public static function acquire_lock( $name, $timeout = 5 ) {
		global $wpdb;
		$name = substr( $wpdb->prefix . 'snfla_' . sanitize_key( $name ), 0, 64 );
		return 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,%d)', $name, max( 0, absint( $timeout ) ) ) );
	}

	public static function release_lock( $name ) {
		global $wpdb;
		$name = substr( $wpdb->prefix . 'snfla_' . sanitize_key( $name ), 0, 64 );
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
	}
}
