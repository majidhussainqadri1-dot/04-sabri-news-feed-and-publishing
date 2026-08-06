<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Plugin {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}

	public function boot() {
		if ( 'retired' === SNFLA_Schema::state() ) {
			add_action( 'admin_init', array( 'SNFLA_Retirement', 'deactivate_retired_plugin' ), 1 );
			add_action( 'wp_loaded', array( 'SNFLA_Retirement', 'deactivate_retired_plugin' ), 1 );
			return;
		}
		SNFLA_Database::maybe_upgrade();
		add_action( 'init', array( $this, 'register_legacy_schema' ), 9999 );
		add_action( 'init', array( $this, 'neutralize_legacy_runtime' ), 1000 );
		add_action( 'rest_api_init', array( 'SNFLA_REST', 'register' ) );
		add_filter( 'wp_insert_post_empty_content', array( $this, 'block_legacy_post_write' ), 999, 2 );
		add_filter( 'wp_insert_post_data', array( $this, 'protect_legacy_post_data' ), 999, 4 );
		add_filter( 'pre_delete_post', array( $this, 'block_legacy_delete' ), 999, 3 );
		add_filter( 'pre_delete_attachment', array( $this, 'block_legacy_attachment_delete' ), 999, 3 );
		add_filter( 'pre_trash_post', array( $this, 'block_legacy_trash' ), 999, 3 );
		add_filter( 'add_post_metadata', array( $this, 'block_legacy_meta_write' ), 999, 5 );
		add_filter( 'update_post_metadata', array( $this, 'block_legacy_meta_write' ), 999, 5 );
		add_filter( 'delete_post_metadata', array( $this, 'block_legacy_meta_write' ), 999, 5 );
		add_filter( 'comments_open', array( $this, 'close_legacy_comments' ), 999, 2 );
		add_filter( 'pings_open', array( $this, 'close_legacy_comments' ), 999, 2 );
		add_filter( 'preprocess_comment', array( $this, 'block_legacy_comment_write' ), 999 );
		add_filter( 'wp_update_comment_data', array( $this, 'block_legacy_comment_update' ), 999, 3 );
		add_filter( 'pre_delete_comment', array( $this, 'block_legacy_comment_delete' ), 999, 3 );
		add_action( 'delete_comment', array( $this, 'block_legacy_comment_action' ), 1, 2 );
		add_action( 'trash_comment', array( $this, 'block_legacy_comment_action' ), 1, 2 );
		add_action( 'untrash_comment', array( $this, 'block_legacy_comment_action' ), 1, 2 );
		add_action( 'spam_comment', array( $this, 'block_legacy_comment_action' ), 1, 2 );
		add_action( 'unspam_comment', array( $this, 'block_legacy_comment_action' ), 1, 2 );
		add_action( 'transition_comment_status', array( $this, 'restore_legacy_comment_status' ), 1, 3 );
		add_filter( 'add_comment_metadata', array( $this, 'block_legacy_comment_meta_write' ), 999, 5 );
		add_filter( 'update_comment_metadata', array( $this, 'block_legacy_comment_meta_write' ), 999, 5 );
		add_filter( 'delete_comment_metadata', array( $this, 'block_legacy_comment_meta_write' ), 999, 5 );
		add_filter( 'pre_insert_term', array( $this, 'block_legacy_term_insert' ), 999, 2 );
		add_action( 'pre_delete_term', array( $this, 'block_legacy_term_delete' ), 999, 2 );
		add_action( 'edit_terms', array( $this, 'block_legacy_term_edit' ), 999, 2 );
		add_filter( 'add_term_metadata', array( $this, 'block_legacy_term_meta_write' ), 999, 5 );
		add_filter( 'update_term_metadata', array( $this, 'block_legacy_term_meta_write' ), 999, 5 );
		add_filter( 'delete_term_metadata', array( $this, 'block_legacy_term_meta_write' ), 999, 5 );
		add_action( 'add_term_relationship', array( $this, 'block_legacy_term_relationship_add' ), 999, 3 );
		add_action( 'delete_term_relationships', array( $this, 'block_legacy_term_relationship_delete' ), 999, 3 );
		add_action( 'snfla_daily_integrity_check', array( $this, 'daily_integrity_check' ) );
		add_filter( 'wp_sitemaps_post_types', array( $this, 'exclude_sitemap' ) );
		add_action( 'pre_get_posts', array( $this, 'exclude_search' ) );
		SNFLA_Interaction_Provider::register();
		SNFLA_Redirects::register();
		SNFLA_Admin::register();
		SNFLA_CLI::register();
	}

	public function register_legacy_schema() {
		register_post_type(
			SNFLA_Inventory::LEGACY_POST_TYPE,
			array(
				'labels'              => array( 'name' => 'Legacy File 04 Publications', 'singular_name' => 'Legacy File 04 Publication' ),
				'public'              => false,
				'publicly_queryable'  => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'query_var'           => true,
				'rewrite'             => array( 'slug' => 'publication', 'with_front' => false ),
				'supports'            => array( 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'comments', 'revisions' ),
				'capability_type'     => array( 'snfla_legacy_record', 'snfla_legacy_records' ),
				'map_meta_cap'        => true,
				'capabilities'        => array(
					'create_posts' => 'do_not_allow', 'edit_posts' => 'do_not_allow', 'edit_others_posts' => 'do_not_allow', 'edit_post' => 'do_not_allow',
					'publish_posts' => 'do_not_allow', 'delete_posts' => 'do_not_allow', 'delete_post' => 'do_not_allow', 'read_private_posts' => 'do_not_allow',
				),
			)
		);
		register_taxonomy( SNFLA_Inventory::LEGACY_TAXONOMY, array( SNFLA_Inventory::LEGACY_POST_TYPE ), array( 'public' => false, 'publicly_queryable' => false, 'show_ui' => false, 'show_in_rest' => false, 'rewrite' => false, 'hierarchical' => true ) );
	}

	public function neutralize_legacy_runtime() {
		foreach ( array( 'sabri_news_home', 'sabri_news_feed', 'sabri_publish_form', 'sabri_publication_feed', 'sabri_my_publication_reports' ) as $shortcode ) {
			remove_shortcode( $shortcode );
			add_shortcode( $shortcode, '__return_empty_string' );
		}
		remove_all_actions( 'wp_ajax_snp_interact' );
		remove_all_actions( 'wp_ajax_nopriv_snp_interact' );
	}

	public function block_legacy_post_write( $maybe_empty, $postarr ) {
		$post_type   = sanitize_key( $postarr['post_type'] ?? 'post' );
		$post_parent = absint( $postarr['post_parent'] ?? 0 );
		if ( SNFLA_Inventory::LEGACY_POST_TYPE === $post_type || ( $post_parent > 0 && SNFLA_Inventory::LEGACY_POST_TYPE === get_post_type( $post_parent ) ) ) {
			return true;
		}
		return $maybe_empty;
	}


	public function protect_legacy_post_data( $data, $postarr, $unsanitized_postarr, $update ) {
		unset( $unsanitized_postarr );
		$post_id = absint( $postarr['ID'] ?? 0 );
		$existing = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $update || ! $this->post_is_legacy_evidence( $existing ) ) {
			return $data;
		}
		foreach ( array( 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_excerpt', 'post_status', 'comment_status', 'ping_status', 'post_password', 'post_name', 'post_modified', 'post_modified_gmt', 'post_parent', 'menu_order', 'post_type', 'post_mime_type' ) as $field ) {
			if ( property_exists( $existing, $field ) ) {
				$data[ $field ] = $existing->{$field};
			}
		}
		return $data;
	}

	public function block_legacy_comment_write( $commentdata ) {
		$post_id = absint( $commentdata['comment_post_ID'] ?? 0 );
		if ( $post_id > 0 && SNFLA_Inventory::LEGACY_POST_TYPE === get_post_type( $post_id ) ) {
			wp_die( esc_html__( 'Legacy File 04 comments are read-only.', SNFLA_TEXT_DOMAIN ), '', array( 'response' => 403 ) );
		}
		return $commentdata;
	}

	public function block_legacy_comment_update( $data, $comment, $commentarr ) {
		unset( $commentarr );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$comment_id = $comment instanceof WP_Comment ? absint( $comment->comment_ID ) : absint( is_array( $comment ) ? ( $comment['comment_ID'] ?? 0 ) : ( $data['comment_ID'] ?? 0 ) );
		if ( $this->comment_is_legacy( $comment_id ) ) {
			return new WP_Error( 'snfla_legacy_comment_read_only', 'Legacy File 04 comments are read-only.' );
		}
		return $data;
	}

	public function block_legacy_comment_delete( $delete, $comment, $force_delete ) {
		unset( $force_delete );
		$comment_id = $comment instanceof WP_Comment ? absint( $comment->comment_ID ) : absint( is_object( $comment ) ? ( $comment->comment_ID ?? 0 ) : 0 );
		return $this->comment_is_legacy( $comment_id ) ? false : $delete;
	}

	public function block_legacy_comment_action( $comment_id, $comment = null ) {
		$comment_id = $comment instanceof WP_Comment ? absint( $comment->comment_ID ) : absint( $comment_id );
		if ( $this->comment_is_legacy( $comment_id ) ) {
			wp_die( esc_html__( 'Legacy File 04 comments are immutable migration evidence.', SNFLA_TEXT_DOMAIN ), '', array( 'response' => 403 ) );
		}
	}

	public function restore_legacy_comment_status( $new_status, $old_status, $comment ) {
		unset( $new_status );
		if ( ! $comment instanceof WP_Comment || ! $this->comment_is_legacy( absint( $comment->comment_ID ) ) ) {
			return;
		}

		$status_map = array(
			'approved'     => '1',
			'unapproved'   => '0',
			'hold'         => '0',
			'spam'         => 'spam',
			'trash'        => 'trash',
			'post-trashed' => 'post-trashed',
		);
		$restore_status = isset( $status_map[ $old_status ] ) ? $status_map[ $old_status ] : sanitize_key( (string) $old_status );
		if ( '' === $restore_status ) {
			return;
		}

		global $wpdb;
		$restored = false;
		if ( isset( $wpdb->comments ) && method_exists( $wpdb, 'update' ) ) {
			$wpdb->last_error = '';
			$restored = false !== $wpdb->update(
				$wpdb->comments,
				array( 'comment_approved' => $restore_status ),
				array( 'comment_ID' => absint( $comment->comment_ID ) ),
				array( '%s' ),
				array( '%d' )
			) && empty( $wpdb->last_error );
		}
		if ( $restored && function_exists( 'clean_comment_cache' ) ) {
			clean_comment_cache( absint( $comment->comment_ID ) );
		}
		wp_die( esc_html__( 'Legacy File 04 comment status changes are not permitted.', SNFLA_TEXT_DOMAIN ), '', array( 'response' => $restored ? 403 : 500 ) );
	}

	public function block_legacy_comment_meta_write( $check, $object_id, $meta_key, $meta_value, $extra ) {
		unset( $meta_key, $meta_value, $extra );
		return $this->comment_is_legacy( absint( $object_id ) ) ? false : $check;
	}

	private function comment_is_legacy( $comment_id ) {
		$comment = get_comment( absint( $comment_id ) );
		return $comment instanceof WP_Comment && SNFLA_Inventory::LEGACY_POST_TYPE === get_post_type( absint( $comment->comment_post_ID ) );
	}

	public function block_legacy_delete( $delete, $post, $force_delete ) {
		unset( $force_delete );
		return $this->post_is_legacy_evidence( $post ) ? false : $delete;
	}

	public function block_legacy_attachment_delete( $delete, $post, $force_delete ) {
		unset( $force_delete );
		return $this->post_is_legacy_evidence( $post ) ? false : $delete;
	}

	public function block_legacy_trash( $trash, $post, $previous_status ) {
		unset( $previous_status );
		return $this->post_is_legacy_evidence( $post ) ? false : $trash;
	}

	public function block_legacy_meta_write( $check, $object_id, $meta_key, $meta_value, $extra ) {
		unset( $meta_key, $meta_value, $extra );
		return $this->post_is_legacy_evidence( get_post( absint( $object_id ) ) ) ? false : $check;
	}

	private function post_is_legacy_evidence( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return false;
		}
		if ( SNFLA_Inventory::LEGACY_POST_TYPE === (string) $post->post_type ) {
			return true;
		}
		return absint( $post->post_parent ?? 0 ) > 0 && SNFLA_Inventory::LEGACY_POST_TYPE === get_post_type( absint( $post->post_parent ) );
	}

	public function block_legacy_term_insert( $term, $taxonomy ) {
		return SNFLA_Inventory::LEGACY_TAXONOMY === $taxonomy ? new WP_Error( 'snfla_legacy_taxonomy_read_only', 'Legacy File 04 taxonomy is read-only.' ) : $term;
	}

	public function block_legacy_term_delete( $term, $taxonomy ) {
		unset( $term );
		if ( SNFLA_Inventory::LEGACY_TAXONOMY === $taxonomy ) {
			wp_die( esc_html__( 'Legacy File 04 taxonomy is read-only.', SNFLA_TEXT_DOMAIN ), '', array( 'response' => 403 ) );
		}
	}

	public function block_legacy_term_edit( $term_id, $taxonomy ) {
		unset( $term_id );
		if ( SNFLA_Inventory::LEGACY_TAXONOMY === $taxonomy ) {
			wp_die( esc_html__( 'Legacy File 04 taxonomy is read-only.', SNFLA_TEXT_DOMAIN ), '', array( 'response' => 403 ) );
		}
	}

	public function block_legacy_term_meta_write( $check, $object_id, $meta_key, $meta_value, $extra ) {
		unset( $meta_key, $meta_value, $extra );
		$term = get_term( absint( $object_id ) );
		return $term instanceof WP_Term && SNFLA_Inventory::LEGACY_TAXONOMY === $term->taxonomy ? false : $check;
	}

	public function block_legacy_term_relationship_add( $object_id, $tt_id, $taxonomy ) {
		unset( $tt_id );
		if ( SNFLA_Inventory::LEGACY_TAXONOMY === $taxonomy || SNFLA_Inventory::LEGACY_POST_TYPE === get_post_type( absint( $object_id ) ) ) {
			wp_die( esc_html__( 'Legacy File 04 term relationships are read-only.', SNFLA_TEXT_DOMAIN ), '', array( 'response' => 403 ) );
		}
	}

	public function block_legacy_term_relationship_delete( $object_id, $tt_ids, $taxonomy ) {
		unset( $tt_ids );
		if ( SNFLA_Inventory::LEGACY_TAXONOMY === $taxonomy || SNFLA_Inventory::LEGACY_POST_TYPE === get_post_type( absint( $object_id ) ) ) {
			wp_die( esc_html__( 'Legacy File 04 term relationships are read-only.', SNFLA_TEXT_DOMAIN ), '', array( 'response' => 403 ) );
		}
	}

	public function daily_integrity_check() {
		if ( ! SNFLA_Retirement::mutations_allowed() ) { wp_clear_scheduled_hook( 'snfla_daily_integrity_check' ); return; }
		if ( ! SNFLA_Database::acquire_lock( 'operation', 0 ) ) { return; }
		try {
			$evidence = array(
				'checked_at_utc'    => gmdate( 'Y-m-d H:i:s' ),
				'source_unchanged'  => SNFLA_Inventory::unchanged(),
				'audit_chain'       => SNFLA_Audit::verify_chain(),
				'reconciliation_ok' => SNFLA_Reconciliation::validate_current_report(),
			);
			$evidence = SNFLA_Integrity::sign_evidence( $evidence );
			update_option( 'snfla_last_integrity_check', $evidence, false );
			SNFLA_Audit::record( 'daily_integrity_checked', 0, array( 'source_unchanged' => $evidence['source_unchanged'], 'audit_valid' => ! empty( $evidence['audit_chain']['valid'] ), 'reconciliation_ok' => $evidence['reconciliation_ok'] ) );
		} finally {
			SNFLA_Database::release_lock( 'operation' );
		}
	}

	public function close_legacy_comments( $open, $post_id ) {
		return SNFLA_Inventory::LEGACY_POST_TYPE === get_post_type( absint( $post_id ) ) ? false : $open;
	}

	public function exclude_sitemap( $post_types ) {
		if ( is_array( $post_types ) ) { unset( $post_types[ SNFLA_Inventory::LEGACY_POST_TYPE ] ); }
		return $post_types;
	}

	public function exclude_search( $query ) {
		if ( $query instanceof WP_Query && ! is_admin() && $query->is_search() ) {
			$types = (array) $query->get( 'post_type' );
			if ( empty( $types ) ) { $types = get_post_types( array( 'exclude_from_search' => false ) ); }
			$query->set( 'post_type', array_values( array_diff( $types, array( SNFLA_Inventory::LEGACY_POST_TYPE ) ) ) );
		}
	}
}
