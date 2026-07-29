<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Privacy {
	const PAGE_SIZE = 25;

	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
	}

	public function exporters( $exporters ) {
		$exporters['sabri-news'] = array(
			'exporter_friendly_name' => 'Sabri publication and interaction data',
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	public function erasers( $erasers ) {
		$erasers['sabri-news'] = array(
			'eraser_friendly_name' => 'Sabri publication and interaction data',
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	public function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}
		$page   = max( 1, absint( $page ) );
		$offset = ( $page - 1 ) * self::PAGE_SIZE;
		$items  = $this->export_rows( $user->ID, $offset, self::PAGE_SIZE );
		$data   = array();
		foreach ( $items as $index => $item ) {
			$data[] = array(
				'group_id'    => 'sabri-news',
				'group_label' => 'Sabri News and Publishing',
				'item_id'     => 'user-' . $user->ID . '-page-' . $page . '-item-' . $index,
				'data'        => array(
					array( 'name' => 'Record type', 'value' => $item['type'] ),
					array( 'name' => 'Record', 'value' => $item['value'] ),
				),
			);
		}
		return array( 'data' => $data, 'done' => count( $items ) < self::PAGE_SIZE );
	}

	private function export_rows( $user_id, $offset, $limit ) {
		global $wpdb;
		$rows = array();
		$authored = get_posts(
			array(
				'post_type'      => SNP_Content::TYPE,
				'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
				'author'         => absint( $user_id ),
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		foreach ( $authored as $post ) {
			$rows[] = array( 'type' => 'Authored publication', 'value' => $post->post_title . ' — ' . $post->post_status . ' — ' . SNP_Publication_State::state( $post->ID ) );
		}
		$liked = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}snp_reactions WHERE user_id=%d ORDER BY id ASC", $user_id ) );
		foreach ( $liked as $post_id ) {
			$rows[] = array( 'type' => 'Liked publication', 'value' => get_the_title( $post_id ) );
		}
		$saved = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}snp_saves WHERE user_id=%d ORDER BY id ASC", $user_id ) );
		foreach ( $saved as $post_id ) {
			$rows[] = array( 'type' => 'Saved publication', 'value' => get_the_title( $post_id ) );
		}
		$reports = $wpdb->get_results( $wpdb->prepare( "SELECT post_id,reason,status,outcome,details,appeal_status,appeal_note FROM {$wpdb->prefix}snp_reports WHERE user_id=%d ORDER BY id ASC", $user_id ) );
		foreach ( $reports as $report ) {
			$rows[] = array( 'type' => 'Publication report', 'value' => get_the_title( $report->post_id ) . ' — ' . $report->reason . ' — ' . $report->status . ' — ' . $report->outcome . ' — appeal: ' . $report->appeal_status . ( $report->details ? ' — ' . $report->details : '' ) . ( $report->appeal_note ? ' — ' . $report->appeal_note : '' ) );
		}
		$staged = $wpdb->get_results( $wpdb->prepare( "SELECT post_id,purpose,created_at FROM {$wpdb->prefix}snp_media_staging WHERE owner_user_id=%d ORDER BY id ASC", $user_id ) );
		foreach ( $staged as $media ) {
			$rows[] = array( 'type' => 'Encrypted pending media', 'value' => get_the_title( $media->post_id ) . ' — ' . $media->purpose . ' — ' . $media->created_at );
		}
		$audit = $wpdb->get_results( $wpdb->prepare( "SELECT post_id,action,note,created_at FROM {$wpdb->prefix}snp_audit_log WHERE actor_id=%d ORDER BY id ASC", $user_id ) );
		foreach ( $audit as $event ) {
			$rows[] = array( 'type' => 'Editorial or interaction audit', 'value' => get_the_title( $event->post_id ) . ' — ' . $event->action . ' — ' . $event->note . ' — ' . $event->created_at );
		}
		return array_slice( $rows, absint( $offset ), absint( $limit ) );
	}

	public function erase( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user || $page > 1 ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		global $wpdb;
		$affected = array_unique(
			array_merge(
				$wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}snp_reactions WHERE user_id=%d", $user->ID ) ),
				$wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}snp_saves WHERE user_id=%d", $user->ID ) )
			)
		);
		$removed  = false;
		$removed  = $wpdb->delete( $wpdb->prefix . 'snp_reactions', array( 'user_id' => $user->ID ), array( '%d' ) ) > 0 || $removed;
		$removed  = $wpdb->delete( $wpdb->prefix . 'snp_saves', array( 'user_id' => $user->ID ), array( '%d' ) ) > 0 || $removed;
		$reporter = hash_hmac( 'sha256', 'erased|' . wp_generate_uuid4(), wp_salt( 'auth' ) );
		$anonymized = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}snp_reports SET user_id=0, reporter_hash=%s, details='[erased by privacy request]', appeal_note='[erased by privacy request]', resolution_note='[retained editorial decision; personal details removed]', open_key=NULL WHERE user_id=%d",
				$reporter,
				$user->ID
			)
		);
		$audit_anonymized = SNP_Audit::anonymize_actor( $user->ID );
		$staged_removed = $wpdb->delete( $wpdb->prefix . 'snp_media_staging', array( 'owner_user_id' => $user->ID ), array( '%d' ) );
		foreach ( $affected as $post_id ) {
			SNP_Interactions::recalculate( absint( $post_id ) );
		}
		$authored = get_posts(
			array(
				'post_type'      => SNP_Content::TYPE,
				'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
				'author'         => $user->ID,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		$messages = $authored ? array( 'Authored publications and canonical editorial decisions are retained for platform integrity. A separate content-governance decision is required for removal or reassignment.' ) : array();
		return array( 'items_removed' => $removed || $anonymized > 0 || $audit_anonymized > 0 || $staged_removed > 0, 'items_retained' => (bool) $authored, 'messages' => $messages, 'done' => true );
	}

	public static function retention() {
		global $wpdb;
		$anonymize = gmdate( 'Y-m-d H:i:s', time() - 180 * DAY_IN_SECONDS );
		$delete    = gmdate( 'Y-m-d H:i:s', time() - 365 * DAY_IN_SECONDS );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}snp_reports WHERE status='resolved' AND user_id<>0 AND resolved_at<%s", $anonymize ) );
		foreach ( $rows as $row ) {
			$wpdb->update(
				$wpdb->prefix . 'snp_reports',
				array( 'user_id' => 0, 'reporter_hash' => hash_hmac( 'sha256', 'retained|' . $row->id . '|' . wp_generate_uuid4(), wp_salt( 'auth' ) ), 'details' => '[retained report details removed]', 'appeal_note' => '[retained appeal details removed]', 'resolution_note' => '[retained editorial outcome; personal details removed]' ),
				array( 'id' => absint( $row->id ) ),
				array( '%d', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}snp_reports WHERE status='resolved' AND resolved_at<%s", $delete ) );
	}
}
