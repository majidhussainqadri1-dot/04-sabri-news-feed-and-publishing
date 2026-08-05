<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Plugin {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}

	public function boot() {
		SNFLA_Database::maybe_upgrade();
		add_action( 'init', array( $this, 'register_legacy_schema' ), 0 );
		add_action( 'init', array( $this, 'neutralize_legacy_runtime' ), 1000 );
		add_action( 'rest_api_init', array( 'SNFLA_REST', 'register' ) );
		add_filter( 'wp_insert_post_empty_content', array( $this, 'block_legacy_post_write' ), 999, 2 );
		add_filter( 'wp_insert_post_data', array( $this, 'protect_legacy_post_data' ), 999, 4 );
		add_filter( 'pre_delete_post', array( $this, 'block_legacy_delete' ), 999, 3 );
		add_filter( 'pre_trash_post', array( $this, 'block_legacy_trash' ), 999, 3 );
		add_filter( 'add_post_metadata', array( $this, 'block_legacy_meta_write' ), 999, 5 );
		add_filter( 'update_post_metadata', array( $this, 'block_legacy_meta_write' ), 999, 5 );
		add_filter( 'delete_post_metadata', array( $this, 'block_legacy_meta_write' ), 999, 5 );
		add_filter( 'comments_open', array( $this, 'close_legacy_comments' ), 999, 2 );
		add_filter( 'pings_open', array( $this, 'close_legacy_comments' ), 999, 2 );
		add_filter( 'preprocess_comment', array( $this, 'block_legacy_comment_write' ), 999 );
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
		if ( isset( $postarr['post_type'] ) && SNFLA_Inventory::LEGACY_POST_TYPE === $postarr['post_type'] ) {
			return true;
		}
		return $maybe_empty;
	}


	public function protect_legacy_post_data( $data, $postarr, $unsanitized_postarr, $update ) {
		unset( $unsanitized_postarr );
		$post_id = absint( $postarr['ID'] ?? 0 );
		$existing = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $update || ! $existing instanceof WP_Post || SNFLA_Inventory::LEGACY_POST_TYPE !== $existing->post_type ) {
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

	public function block_legacy_delete( $delete, $post, $force_delete ) {
		unset( $force_delete );
		return $post instanceof WP_Post && SNFLA_Inventory::LEGACY_POST_TYPE === $post->post_type ? false : $delete;
	}

	public function block_legacy_trash( $trash, $post, $previous_status ) {
		unset( $previous_status );
		return $post instanceof WP_Post && SNFLA_Inventory::LEGACY_POST_TYPE === $post->post_type ? false : $trash;
	}

	public function block_legacy_meta_write( $check, $object_id, $meta_key, $meta_value, $extra ) {
		unset( $meta_key, $meta_value, $extra );
		return SNFLA_Inventory::LEGACY_POST_TYPE === get_post_type( absint( $object_id ) ) ? false : $check;
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
