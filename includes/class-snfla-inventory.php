<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Inventory {
	const LEGACY_POST_TYPE = 'snp_publication';
	const LEGACY_TAXONOMY  = 'snp_topic';

	public static function capture() {
		global $wpdb;
		$post_counts = array();
		foreach ( array( 'publish', 'pending', 'draft', 'private', 'trash' ) as $status ) {
			$post_counts[ $status ] = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s AND post_status=%s", self::LEGACY_POST_TYPE, $status ) ) );
		}
		$table_counts = array();
		foreach ( array( 'snp_reactions', 'snp_saves', 'snp_views', 'snp_reports', 'snp_audit_log', 'snp_media_staging', 'snp_rate_limits' ) as $suffix ) {
			$table = $wpdb->prefix . $suffix;
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			$table_counts[ $suffix ] = $exists === $table ? absint( $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ) ) : 0;
		}
		$max_modified = (string) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_type=%s", self::LEGACY_POST_TYPE ) );
		$comment_count = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} c INNER JOIN {$wpdb->posts} p ON p.ID=c.comment_post_ID WHERE p.post_type=%s", self::LEGACY_POST_TYPE ) ) );
		$term_count    = taxonomy_exists( self::LEGACY_TAXONOMY ) ? absint( wp_count_terms( array( 'taxonomy' => self::LEGACY_TAXONOMY, 'hide_empty' => false ) ) ) : 0;
		$legacy_ids    = array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type=%s AND post_status IN ('publish','draft','pending','private','future','trash') ORDER BY ID ASC", self::LEGACY_POST_TYPE ) ) );
		$post_hash     = hash_init( 'sha256' );
		foreach ( $legacy_ids as $legacy_id ) {
			hash_update( $post_hash, $legacy_id . ':' . SNFLA_Checksum::post( $legacy_id ) . "\n" );
		}
		$source_tree_checksum = hash_final( $post_hash );
		$comment_tree_checksum = self::query_digest( $wpdb->prepare( "SELECT c.comment_ID,c.comment_post_ID,c.comment_parent,c.user_id,c.comment_approved,c.comment_date_gmt,c.comment_author,c.comment_author_email,c.comment_author_url,c.comment_content,c.comment_type FROM {$wpdb->comments} c INNER JOIN {$wpdb->posts} p ON p.ID=c.comment_post_ID WHERE p.post_type=%s ORDER BY c.comment_ID ASC", self::LEGACY_POST_TYPE ) );
		$term_tree_checksum = self::query_digest( $wpdb->prepare( "SELECT tr.object_id,tr.term_taxonomy_id,tr.term_order,tt.taxonomy,t.slug,t.name FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id INNER JOIN {$wpdb->terms} t ON t.term_id=tt.term_id INNER JOIN {$wpdb->posts} p ON p.ID=tr.object_id WHERE p.post_type=%s AND tt.taxonomy=%s ORDER BY tr.object_id,tr.term_taxonomy_id", self::LEGACY_POST_TYPE, self::LEGACY_TAXONOMY ) );
		$table_digests = array();
		foreach ( array( 'snp_reactions', 'snp_saves', 'snp_views', 'snp_reports', 'snp_audit_log', 'snp_media_staging', 'snp_rate_limits' ) as $suffix ) {
			$table = $wpdb->prefix . $suffix;
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			$table_digests[ $suffix ] = $exists === $table ? self::query_digest( "SELECT * FROM `{$table}` ORDER BY 1 ASC" ) : hash( 'sha256', '' );
		}
		$routes        = array_filter(
			array(
				'snp_page_map' => (array) get_option( 'snp_page_map', array() ),
				'legacy_shortcodes' => array( 'sabri_news_home', 'sabri_news_feed', 'sabri_publish_form', 'sabri_publication_feed' ),
			)
		);
		$inventory = array(
			'schema'          => 1,
			'captured_at_utc' => gmdate( 'Y-m-d H:i:s' ),
			'post_counts'     => $post_counts,
			'comment_count'   => $comment_count,
			'term_count'      => $term_count,
			'table_counts'    => $table_counts,
			'legacy_record_count' => count( $legacy_ids ),
			'source_tree_checksum' => $source_tree_checksum,
			'comment_tree_checksum' => $comment_tree_checksum,
			'term_tree_checksum' => $term_tree_checksum,
			'table_digests' => $table_digests,
			'max_modified_gmt'=> $max_modified,
			'routes'          => $routes,
		);
		$inventory['source_signature'] = self::signature( $inventory );
		return $inventory;
	}


	private static function query_digest( $sql ) {
		global $wpdb;
		$hash   = hash_init( 'sha256' );
		$limit  = 1000;
		$offset = 0;
		do {
			$rows = $wpdb->get_results( $wpdb->prepare( $sql . ' LIMIT %d OFFSET %d', $limit, $offset ), ARRAY_A );
			$rows = is_array( $rows ) ? $rows : array();
			foreach ( $rows as $row ) {
				hash_update( $hash, wp_json_encode( SNFLA_Checksum::canonicalize( $row ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );
			}
			$offset += $limit;
		} while ( count( $rows ) === $limit );
		return hash_final( $hash );
	}

	public static function signature( array $inventory ) {
		unset( $inventory['captured_at_utc'], $inventory['source_signature'] );
		return SNFLA_Checksum::hash( $inventory );
	}

	public static function lock( $actor_id, $expected_state, $expected_version ) {
		$inventory = self::capture();
		update_option( SNFLA_Schema::INVENTORY_OPTION, $inventory, false );
		$transition = SNFLA_Schema::transition( 'inventory_locked', $expected_state, $expected_version, $actor_id, array( 'source_signature' => $inventory['source_signature'] ) );
		if ( is_wp_error( $transition ) ) {
			return $transition;
		}
		SNFLA_Audit::record( 'inventory_locked', $actor_id, array( 'source_signature' => $inventory['source_signature'], 'counts' => $inventory['post_counts'] ) );
		return array( 'inventory' => $inventory, 'lifecycle' => $transition );
	}

	public static function locked() {
		$locked = get_option( SNFLA_Schema::INVENTORY_OPTION, array() );
		return is_array( $locked ) ? $locked : array();
	}

	public static function unchanged() {
		$locked = self::locked();
		if ( empty( $locked['source_signature'] ) ) {
			return false;
		}
		$current = self::capture();
		return hash_equals( (string) $locked['source_signature'], (string) $current['source_signature'] );
	}
}
