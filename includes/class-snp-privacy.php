<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Privacy {
	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
	}

	public function exporters( $exporters ) {
		$exporters['sabri-news'] = array( 'exporter_friendly_name' => 'Sabri news interactions', 'callback' => array( $this, 'export' ) );
		return $exporters;
	}

	public function erasers( $erasers ) {
		$erasers['sabri-news'] = array( 'eraser_friendly_name' => 'Sabri news interactions', 'callback' => array( $this, 'erase' ) );
		return $erasers;
	}

	public function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user || $page > 1 ) {
			return array( 'data' => array(), 'done' => true );
		}
		global $wpdb; $data = array();
		$liked = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}snp_reactions WHERE user_id = %d", $user->ID ) );
		$saved = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}snp_saves WHERE user_id = %d", $user->ID ) );
		$reports = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, reason, details, status FROM {$wpdb->prefix}snp_reports WHERE user_id = %d", $user->ID ) );
		foreach ( $liked as $post_id ) { $data[] = array( 'name' => 'Liked publication', 'value' => get_the_title( $post_id ) ); }
		foreach ( $saved as $post_id ) { $data[] = array( 'name' => 'Saved publication', 'value' => get_the_title( $post_id ) ); }
		foreach ( $reports as $report ) { $data[] = array( 'name' => 'Publication report', 'value' => get_the_title( $report->post_id ) . ' — ' . $report->reason . ' — ' . $report->status . ( $report->details ? ' — ' . $report->details : '' ) ); }
		$authored = get_posts( array( 'post_type' => SNP_Content::TYPE, 'post_status' => array( 'publish', 'pending', 'draft', 'private' ), 'author' => $user->ID, 'posts_per_page' => 200, 'no_found_rows' => true ) );
		foreach ( $authored as $post ) { $data[] = array( 'name' => 'Authored publication', 'value' => $post->post_title . ' — ' . $post->post_status ); }
		if ( ! $data ) { return array( 'data' => array(), 'done' => true ); }
		return array( 'data' => array( array( 'group_id' => 'sabri-news', 'group_label' => 'Sabri News and Publishing', 'item_id' => 'user-' . $user->ID, 'data' => $data ) ), 'done' => true );
	}

	public function erase( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user || $page > 1 ) { return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ); }
		global $wpdb; $removed = false;
		$removed = $wpdb->delete( $wpdb->prefix . 'snp_reactions', array( 'user_id' => $user->ID ), array( '%d' ) ) > 0 || $removed;
		$removed = $wpdb->delete( $wpdb->prefix . 'snp_saves', array( 'user_id' => $user->ID ), array( '%d' ) ) > 0 || $removed;
		$removed = $wpdb->delete( $wpdb->prefix . 'snp_reports', array( 'user_id' => $user->ID ), array( '%d' ) ) > 0 || $removed;
		$authored = count_user_posts( $user->ID, SNP_Content::TYPE, true );
		return array( 'items_removed' => $removed, 'items_retained' => $authored > 0, 'messages' => $authored > 0 ? array( 'Published content and moderation audit history are retained for platform integrity; use the normal WordPress user/content deletion workflow when appropriate.' ) : array(), 'done' => true );
	}
}
