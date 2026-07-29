<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Activator {
	public static function activate() {
		if ( ! SNP_Permissions::dependencies_available() ) {
			deactivate_plugins( plugin_basename( SNP_FILE ) );
			wp_die( esc_html__( 'Files 00, corrected File 03, and File 09 must be active before File 04 can be activated.', 'sabri-news-publishing' ) );
		}
		SNP_Content::register();
		SNP_Content::seed_topics();
		self::tables();
		SNP_Audit::upgrade_chain();
		self::migrate_legacy_publications();
		self::migrate_legacy_reports();
		self::pages();
		self::schedule();
		update_option( 'snp_version', SNP_VERSION, false );
		update_option( 'snp_schema_version', SNP_SCHEMA_VERSION, false );
		set_transient( 'snp_activation_notice', '1', 120 );
		flush_rewrite_rules();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'snp_recalculate_scores' );
		wp_clear_scheduled_hook( 'snp_daily_maintenance' );
		flush_rewrite_rules();
	}

	public static function maybe_upgrade() {
		if ( SNP_SCHEMA_VERSION !== (string) get_option( 'snp_schema_version', '' ) ) {
			SNP_Content::seed_topics();
			self::tables();
			SNP_Audit::upgrade_chain();
			self::migrate_legacy_publications();
			self::migrate_legacy_reports();
			self::pages();
			self::schedule();
			update_option( 'snp_schema_version', SNP_SCHEMA_VERSION, false );
		}
	}

	private static function schedule() {
		if ( ! wp_next_scheduled( 'snp_recalculate_scores' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'snp_recalculate_scores' );
		}
		if ( ! wp_next_scheduled( 'snp_daily_maintenance' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'snp_daily_maintenance' );
		}
	}

	private static function tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		dbDelta( "CREATE TABLE {$wpdb->prefix}snp_reactions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY post_user (post_id,user_id),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}snp_saves (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY post_user (post_id,user_id),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}snp_views (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			viewer_hash char(64) NOT NULL,
			view_day date NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY post_viewer_day (post_id,viewer_hash,view_day),
			KEY post_day (post_id,view_day)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}snp_reports (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reporter_hash char(64) NOT NULL DEFAULT '',
			open_key char(64) DEFAULT NULL,
			reason varchar(40) NOT NULL,
			details text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			outcome varchar(40) NOT NULL DEFAULT '',
			resolution_note text NOT NULL,
			resolver_id bigint(20) unsigned NOT NULL DEFAULT 0,
			resolved_at datetime DEFAULT NULL,
			appeal_status varchar(20) NOT NULL DEFAULT '',
			appeal_note text NOT NULL,
			appealed_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY open_key (open_key),
			KEY post_status (post_id,status),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}snp_audit_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			actor_digest char(64) NOT NULL DEFAULT '',
			action varchar(40) NOT NULL,
			note text NOT NULL,
			context_json longtext NOT NULL,
			details_digest char(64) NOT NULL DEFAULT '',
			prev_hash char(64) NOT NULL DEFAULT '',
			event_hash char(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY actor_id (actor_id),
			KEY created_at (created_at)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}snp_rate_limits (
			key_hash char(64) NOT NULL,
			bucket varchar(40) NOT NULL,
			window_start bigint(20) unsigned NOT NULL,
			hits int(10) unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (key_hash,bucket,window_start),
			KEY updated_at (updated_at)
		) {$charset};" );

		// File 04 v0.1.0 used a UNIQUE post_user report index that prevented report history.
		$legacy_index = $wpdb->get_var( "SHOW INDEX FROM {$wpdb->prefix}snp_reports WHERE Key_name='post_user'" );
		if ( $legacy_index ) {
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}snp_reports DROP INDEX post_user" );
		}

		dbDelta( "CREATE TABLE {$wpdb->prefix}snp_media_staging (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			owner_user_id bigint(20) unsigned NOT NULL,
			purpose varchar(30) NOT NULL,
			mime_type varchar(60) NOT NULL,
			original_extension varchar(10) NOT NULL,
			ciphertext_b64 longtext NOT NULL,
			nonce_hex varchar(32) NOT NULL,
			tag_hex varchar(64) NOT NULL,
			consent_record_id varchar(190) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY post_purpose (post_id,purpose),
			KEY post_id (post_id),
			KEY owner_user_id (owner_user_id),
			KEY created_at (created_at)
		) {$charset};" );
	}

	private static function migrate_legacy_publications() {
		$ids = get_posts(
			array(
				'post_type'      => SNP_Content::TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $ids as $post_id ) {
			if ( ! get_post_meta( $post_id, SNP_Publication_State::SNAPSHOT_META, true ) ) {
				wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft', 'comment_status' => 'closed' ) );
				SNP_Publication_State::set_state( $post_id, 'pending' );
				SNP_Audit::record( $post_id, 'legacy_publication_quarantined', 'Version 0.1.0 publication requires governed re-review before public release.' );
			}
		}
	}

	private static function migrate_legacy_reports() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT id,post_id,user_id,status,open_key,reporter_hash FROM {$wpdb->prefix}snp_reports" );
		foreach ( $rows as $row ) {
			$updates = array();
			$formats = array();
			if ( ! $row->reporter_hash ) {
				$updates['reporter_hash'] = hash_hmac( 'sha256', 'legacy|' . absint( $row->user_id ) . '|' . absint( $row->id ), wp_salt( 'auth' ) );
				$formats[] = '%s';
			}
			if ( 'open' === $row->status && ! $row->open_key ) {
				$updates['open_key'] = hash( 'sha256', absint( $row->post_id ) . '|' . absint( $row->user_id ) . '|legacy-open|' . absint( $row->id ) );
				$formats[] = '%s';
			} elseif ( 'open' !== $row->status ) {
				$updates['open_key'] = null;
				$formats[] = '%s';
			}
			if ( $updates ) {
				$wpdb->update( $wpdb->prefix . 'snp_reports', $updates, array( 'id' => absint( $row->id ) ), $formats, array( '%d' ) );
			}
		}
		$unique = $wpdb->get_var( "SHOW INDEX FROM {$wpdb->prefix}snp_reports WHERE Key_name='open_key_unique'" );
		if ( ! $unique ) {
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}snp_reports ADD UNIQUE KEY open_key_unique (open_key)" );
		}
	}

	private static function pages() {
		$map            = (array) get_option( 'snp_page_map', array() );
		$map['publish'] = self::page( 'publish', 'Create Publication', 'create-publication', '[sabri_publish_form]' );
		$map['saved']   = self::page( 'saved', 'Saved Publications', 'saved-publications', '[sabri_publication_feed mode="saved"]' );
		$map['reports'] = self::page( 'reports', 'My Publication Reports', 'my-publication-reports', '[sabri_my_publication_reports]' );
		update_option( 'snp_page_map', $map, false );
	}

	private static function page( $key, $title, $slug, $content ) {
		$map      = (array) get_option( 'snp_page_map', array() );
		$existing = ! empty( $map[ $key ] ) ? get_post( absint( $map[ $key ] ) ) : null;
		$legacy_content = 'saved' === $key ? '[sabri_news_feed mode="saved"]' : $content;
		if ( $existing instanceof WP_Post ) {
			$owned = $key === get_post_meta( $existing->ID, '_snp_managed_page_key', true );
			$legacy_exact = trim( (string) $existing->post_content ) === $legacy_content;
			if ( $owned || $legacy_exact ) {
				wp_update_post( array( 'ID' => $existing->ID, 'post_title' => $title, 'post_content' => $content ) );
				update_post_meta( $existing->ID, '_snp_managed_page_key', $key );
				delete_post_meta( $existing->ID, '_snp_managed_page' );
				return $existing->ID;
			}
		}
		$collision = get_page_by_path( $slug );
		if ( $collision instanceof WP_Post ) {
			if ( $key === get_post_meta( $collision->ID, '_snp_managed_page_key', true ) && trim( (string) $collision->post_content ) === $content ) {
				return $collision->ID;
			}
			$slug .= '-file-04';
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $content,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		update_post_meta( $id, '_snp_managed_page_key', $key );
		return absint( $id );
	}
}
