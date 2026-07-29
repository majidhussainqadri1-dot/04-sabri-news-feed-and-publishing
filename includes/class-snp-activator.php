<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Activator {
	public static function activate() {
		SNP_Content::register();
		SNP_Content::seed_topics();
		self::roles();
		self::tables();
		self::pages();
		if ( ! wp_next_scheduled( 'snp_recalculate_scores' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'snp_recalculate_scores' );
		}
		update_option( 'snp_version', SNP_VERSION, false );
		set_transient( 'snp_activation_notice', '1', 120 );
		flush_rewrite_rules();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'snp_recalculate_scores' );
		flush_rewrite_rules();
	}

	private static function roles() {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'manage_sabri_news' );
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
			KEY post_id (post_id)
		) {$charset};" );
		dbDelta( "CREATE TABLE {$wpdb->prefix}snp_saves (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY post_user (post_id,user_id),
			KEY user_id (user_id)
		) {$charset};" );
		dbDelta( "CREATE TABLE {$wpdb->prefix}snp_reports (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			reason varchar(40) NOT NULL,
			details text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY post_user (post_id,user_id),
			KEY status (status)
		) {$charset};" );
		dbDelta( "CREATE TABLE {$wpdb->prefix}snp_audit_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			actor_id bigint(20) unsigned NOT NULL,
			action varchar(30) NOT NULL,
			note text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY created_at (created_at)
		) {$charset};" );
	}

	private static function pages() {
		$map = (array) get_option( 'snp_page_map', array() );
		$map['publish'] = self::page( 'Create Publication', 'create-publication', '[sabri_publish_form]' );
		$map['saved']   = self::page( 'Saved Posts', 'saved-posts', '[sabri_news_feed mode="saved"]' );
		update_option( 'snp_page_map', $map, false );
	}

	private static function page( $title, $slug, $content ) {
		$page = get_page_by_path( $slug );
		if ( $page instanceof WP_Post ) {
			if ( get_post_meta( $page->ID, '_snp_managed_page', true ) ) {
				wp_update_post( array( 'ID' => $page->ID, 'post_content' => $content ) );
			}
			return $page->ID;
		}
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content ), true );
		if ( ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_snp_managed_page', '1' );
			return $id;
		}
		return 0;
	}
}

