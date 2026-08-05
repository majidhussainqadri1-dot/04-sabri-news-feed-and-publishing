<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Database {
	public static function tables() {
		global $wpdb;
		return array(
			'runs'      => $wpdb->prefix . 'snfla_runs',
			'map'       => $wpdb->prefix . 'snfla_map',
			'conflicts' => $wpdb->prefix . 'snfla_conflicts',
			'audit'     => $wpdb->prefix . 'snfla_audit',
		);
	}

	public static function activate() {
		self::install();
		$deactivated = self::deactivate_obsolete_runtime();
		if ( is_wp_error( $deactivated ) ) {
			if ( function_exists( 'deactivate_plugins' ) ) { deactivate_plugins( plugin_basename( SNFLA_FILE ), true ); }
			wp_die( esc_html( $deactivated->get_error_message() ), esc_html__( 'File 04 activation blocked', SNFLA_TEXT_DOMAIN ), array( 'response' => 500 ) );
		}
		$page_quarantine = self::quarantine_legacy_pages();
		if ( is_wp_error( $page_quarantine ) ) {
			if ( function_exists( 'deactivate_plugins' ) ) { deactivate_plugins( plugin_basename( SNFLA_FILE ), true ); }
			wp_die( esc_html( $page_quarantine->get_error_message() ), esc_html__( 'File 04 activation blocked', SNFLA_TEXT_DOMAIN ), array( 'response' => 500 ) );
		}
		update_option( 'snfla_activation_handover', array( 'deactivated_plugins' => $deactivated, 'legacy_pages' => $page_quarantine, 'recorded_at_utc' => gmdate( 'Y-m-d H:i:s' ) ), false );
		if ( ! get_option( SNFLA_Schema::STATE_OPTION, false ) ) {
			add_option( SNFLA_Schema::STATE_OPTION, 'legacy_active', '', false );
		}
		if ( ! get_option( SNFLA_Schema::STATE_VERSION_OPTION, false ) ) {
			add_option( SNFLA_Schema::STATE_VERSION_OPTION, 1, '', false );
		}
		update_option( 'snfla_schema_version', SNFLA_SCHEMA_VERSION, false );
		update_option( 'snfla_plugin_version', SNFLA_VERSION, false );
		if ( 'retired' !== (string) get_option( SNFLA_Schema::STATE_OPTION, 'legacy_active' ) && ! wp_next_scheduled( 'snfla_daily_integrity_check' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'snfla_daily_integrity_check' );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'snfla_daily_integrity_check' );
	}

	public static function maybe_upgrade() {
		if ( SNFLA_SCHEMA_VERSION !== (string) get_option( 'snfla_schema_version', '' ) ) {
			self::install();
			update_option( 'snfla_schema_version', SNFLA_SCHEMA_VERSION, false );
		}
	}

	private static function deactivate_obsolete_runtime() {
		if ( ! function_exists( 'deactivate_plugins' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
		if ( ! function_exists( 'deactivate_plugins' ) ) { return array(); }
		$current = plugin_basename( SNFLA_FILE );
		$deactivated = array();
		$active = (array) get_option( 'active_plugins', array() );
		$sitewide = is_multisite() ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array();
		foreach ( array_unique( array_merge( $active, array_keys( $sitewide ) ) ) as $plugin ) {
			$plugin = sanitize_text_field( (string) $plugin );
			if ( $plugin === $current || 'sabri-news-publishing.php' !== basename( $plugin ) ) { continue; }
			$network = isset( $sitewide[ $plugin ] );
			deactivate_plugins( $plugin, true, $network );
			$still_active = $network ? is_plugin_active_for_network( $plugin ) : is_plugin_active( $plugin );
			if ( $still_active ) { return new WP_Error( 'snfla_obsolete_runtime_deactivation_failed', 'The obsolete File 04 publishing runtime could not be disabled safely.' ); }
			$deactivated[] = array( 'plugin_hash' => hash( 'sha256', $plugin ), 'network' => $network );
		}
		return $deactivated;
	}

	private static function quarantine_legacy_pages() {
		$map = (array) get_option( 'snp_page_map', array() );
		$records = array();
		foreach ( $map as $key => $page_id ) {
			$page_id = absint( $page_id );
			$page = $page_id > 0 ? get_post( $page_id ) : null;
			if ( ! $page instanceof WP_Post || 'page' !== $page->post_type ) { continue; }
			$managed_key = sanitize_key( (string) get_post_meta( $page_id, '_snp_managed_page_key', true ) );
			$managed_flag = '1' === (string) get_post_meta( $page_id, '_snp_managed_page', true );
			$legacy_shortcode = preg_match( '/\[(?:sabri_publish_form|sabri_news_feed|sabri_publication_feed|sabri_my_publication_reports)\b/i', (string) $page->post_content );
			if ( ! $managed_key && ! $managed_flag && ! $legacy_shortcode ) { continue; }
			$original_status = sanitize_key( $page->post_status );
			if ( in_array( $original_status, array( 'publish', 'future' ), true ) ) {
				$result = wp_update_post( array( 'ID' => $page_id, 'post_status' => 'private' ), true );
				if ( is_wp_error( $result ) || ! $result ) { return new WP_Error( 'snfla_legacy_page_quarantine_failed', 'A legacy File 04 public page could not be quarantined safely.', array( 'page_id' => $page_id ) ); }
			}
			$records[] = array( 'page_id' => $page_id, 'map_key' => sanitize_key( $key ), 'managed_key' => $managed_key, 'original_status' => $original_status, 'quarantined_status' => in_array( $original_status, array( 'publish', 'future' ), true ) ? 'private' : $original_status, 'content_checksum' => hash( 'sha256', (string) $page->post_content ) );
		}
		if ( ! empty( $records ) ) {
			update_option( 'snfla_legacy_page_quarantine', $records, false );
			if ( ! SNFLA_Audit::record( 'legacy_pages_quarantined', get_current_user_id(), array( 'page_count' => count( $records ), 'page_ids' => array_column( $records, 'page_id' ) ) ) ) {
				return new WP_Error( 'snfla_legacy_page_quarantine_audit_failed', 'Legacy pages were made private, but the required audit evidence could not be written.' );
			}
		}
		return $records;
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
			status varchar(24) NOT NULL,
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
