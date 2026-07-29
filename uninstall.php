<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Default: retain publications, consent-linked media, audit evidence, reports,
// interactions, and configuration to prevent accidental legal or clinical loss.
// Destructive purge requires both an explicit code constant and an administrator option.
if ( ! defined( 'SABRI_ALLOW_DESTRUCTIVE_UNINSTALL' ) || true !== SABRI_ALLOW_DESTRUCTIVE_UNINSTALL || '1' !== get_option( 'snp_allow_destructive_uninstall', '0' ) ) {
	return;
}

global $wpdb;
wp_clear_scheduled_hook( 'snp_recalculate_scores' );
wp_clear_scheduled_hook( 'snp_daily_maintenance' );
$posts = get_posts(
	array(
		'post_type'      => 'snp_publication',
		'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $posts as $post_id ) {
	$attachments = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'post_parent' => $post_id, 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_snp_owned_media', 'meta_value' => '1' ) );
	foreach ( $attachments as $attachment_id ) {
		if ( absint( get_post_meta( $attachment_id, '_snp_owner_post_id', true ) ) === absint( $post_id ) ) {
			wp_delete_attachment( $attachment_id, true );
		}
	}
	wp_delete_post( $post_id, true );
}
foreach ( array( 'snp_reactions', 'snp_saves', 'snp_views', 'snp_reports', 'snp_audit_log', 'snp_rate_limits', 'snp_media_staging' ) as $suffix ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$suffix}" );
}
$map = (array) get_option( 'snp_page_map', array() );
foreach ( $map as $key => $page_id ) {
	if ( $key === get_post_meta( absint( $page_id ), '_snp_managed_page_key', true ) ) {
		wp_delete_post( absint( $page_id ), true );
	}
}
$terms = get_terms( array( 'taxonomy' => 'snp_topic', 'hide_empty' => false, 'fields' => 'ids' ) );
if ( ! is_wp_error( $terms ) ) {
	foreach ( $terms as $term_id ) {
		wp_delete_term( $term_id, 'snp_topic' );
	}
}
delete_option( 'snp_page_map' );
delete_option( 'snp_version' );
delete_option( 'snp_schema_version' );
delete_option( 'snp_allow_destructive_uninstall' );
