<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Content {
	const TYPE = 'snp_publication';
	const TAX  = 'snp_topic';

	public static function topics() {
		return array(
			'founder-update'             => 'Founder Update',
			'classical-homeopathy'       => 'Classical Homeopathy',
			'homeopathy-education'       => 'Homeopathy Education',
			'materia-medica'             => 'Materia Medica',
			'repertory'                  => 'Repertory',
			'clinical-education'         => 'Clinical Education',
			'research'                   => 'Research',
			'nutrition'                  => 'Nutrition',
			'public-health-education'    => 'Public Health Education',
			'platform-news'              => 'Platform News',
			'pathology'                  => 'Pathology',
			'anatomy'                    => 'Anatomy',
			'principles-of-hygiene'      => 'Principles of Hygiene',
			'islamic-spiritual-healing'  => 'Islamic Spiritual Healing',
			'patient-cases'              => 'Patient Cases',
			'homeopathy-philosophy'      => 'Homeopathy Philosophy',
		);
	}

	public static function register() {
		register_post_type(
			self::TYPE,
			array(
				'labels' => array( 'name' => 'News Publications', 'singular_name' => 'Publication', 'add_new_item' => 'Add Publication', 'edit_item' => 'Edit Publication' ),
				'public' => true,
				'publicly_queryable' => true,
				'show_ui' => true,
				'show_in_menu' => false,
				'show_in_rest' => false,
				'has_archive' => 'news-publications',
				'rewrite' => array( 'slug' => 'publication', 'with_front' => false ),
				'supports' => array( 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'comments', 'revisions' ),
				'taxonomies' => array( self::TAX ),
				'capability_type' => 'post',
				'capabilities' => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap' => true,
				'delete_with_user' => false,
			)
		);

		register_taxonomy(
			self::TAX,
			array( self::TYPE ),
			array(
				'labels' => array( 'name' => 'Approved Topics', 'singular_name' => 'Approved Topic' ),
				'public' => true,
				'show_ui' => false,
				'show_in_rest' => false,
				'hierarchical' => true,
				'rewrite' => array( 'slug' => 'news-topic', 'with_front' => false ),
			)
		);

		foreach ( array( '_snp_video_url', '_snp_tags', '_snp_language', '_snp_featured', '_snp_pinned', '_snp_views', '_snp_viral_score' ) as $key ) {
			register_post_meta( self::TYPE, $key, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => function() { return current_user_can( 'manage_sabri_news' ); } ) );
		}
	}

	public static function seed_topics() {
		foreach ( self::topics() as $slug => $name ) {
			$term = get_term_by( 'slug', $slug, self::TAX );
			if ( ! $term ) {
				wp_insert_term( $name, self::TAX, array( 'slug' => $slug ) );
			}
		}
	}

	public static function topic_allowed( $slug ) {
		return isset( self::topics()[ sanitize_title( $slug ) ] );
	}

	public static function topic_name( $post_id ) {
		$terms = get_the_terms( absint( $post_id ), self::TAX );
		return $terms && ! is_wp_error( $terms ) ? $terms[0]->name : '';
	}

	public static function topic_slug( $post_id ) {
		$terms = get_the_terms( absint( $post_id ), self::TAX );
		return $terms && ! is_wp_error( $terms ) ? $terms[0]->slug : '';
	}

	public static function guard_public_topic() {
		if ( is_singular( self::TYPE ) && ! self::topic_allowed( self::topic_slug( get_queried_object_id() ) ) && ! current_user_can( 'manage_sabri_news' ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			include get_404_template();
			exit;
		}
	}
}
