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
		if ( ! get_option( SNFLA_Schema::STATE_OPTION, false ) ) {
			add_option( SNFLA_Schema::STATE_OPTION, 'legacy_active', '', false );
		}
		if ( ! get_option( SNFLA_Schema::STATE_VERSION_OPTION, false ) ) {
			add_option( SNFLA_Schema::STATE_VERSION_OPTION, 1, '', false );
		}
		update_option( 'snfla_schema_version', SNFLA_SCHEMA_VERSION, false );
		update_option( 'snfla_plugin_version', SNFLA_VERSION, false );
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
