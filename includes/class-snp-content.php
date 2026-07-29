<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Content {
	const TYPE = 'snp_publication';
	const TAX  = 'snp_topic';

	public static function topics() {
		return array(
			'founder-update'            => 'Founder Update',
			'classical-homeopathy'      => 'Classical Homeopathy',
			'homeopathy-education'      => 'Homeopathy Education',
			'materia-medica'            => 'Materia Medica',
			'repertory'                 => 'Repertory',
			'clinical-education'        => 'Clinical Education',
			'research'                  => 'Research',
			'nutrition'                 => 'Nutrition',
			'public-health-education'   => 'Public Health Education',
			'platform-news'             => 'Platform News',
			'pathology'                 => 'Pathology',
			'anatomy'                   => 'Anatomy',
			'principles-of-hygiene'     => 'Principles of Hygiene',
			'islamic-spiritual-healing' => 'Islamic Spiritual Healing',
			'patient-cases'             => 'Patient Cases',
			'homeopathy-philosophy'     => 'Homeopathy Philosophy',
		);
	}

	public static function register() {
		register_post_type(
			self::TYPE,
			array(
				'labels'             => array( 'name' => 'Publications', 'singular_name' => 'Publication' ),
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => false,
				'show_in_rest'       => false,
				'has_archive'        => false,
				'exclude_from_search'=> true,
				'rewrite'            => array( 'slug' => 'publication', 'with_front' => false ),
				'supports'           => array( 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'comments', 'revisions' ),
				'taxonomies'         => array( self::TAX ),
				'capability_type'    => array( 'snp_publication', 'snp_publications' ),
				'map_meta_cap'       => true,
				'capabilities'       => array(
					'create_posts'           => 'do_not_allow',
					'edit_posts'             => SNP_Permissions::CAP_MODERATE,
					'edit_others_posts'      => SNP_Permissions::CAP_MODERATE,
					'edit_published_posts'   => SNP_Permissions::CAP_MODERATE,
					'publish_posts'          => SNP_Permissions::CAP_MODERATE,
					'delete_posts'           => SNP_Permissions::CAP_MODERATE,
					'delete_others_posts'    => SNP_Permissions::CAP_MODERATE,
					'delete_published_posts' => SNP_Permissions::CAP_MODERATE,
					'read_private_posts'     => SNP_Permissions::CAP_MODERATE,
				),
				'delete_with_user'   => false,
			)
		);
		register_taxonomy(
			self::TAX,
			array( self::TYPE ),
			array(
				'labels'             => array( 'name' => 'Approved Topics', 'singular_name' => 'Approved Topic' ),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => false,
				'show_in_rest'       => false,
				'hierarchical'       => true,
				'rewrite'            => false,
			)
		);
	}

	public static function seed_topics() {
		foreach ( self::topics() as $slug => $name ) {
			if ( ! get_term_by( 'slug', $slug, self::TAX ) ) {
				wp_insert_term( $name, self::TAX, array( 'slug' => $slug ) );
			}
		}
	}

	public static function topic_allowed( $slug ) {
		return isset( self::topics()[ sanitize_title( $slug ) ] );
	}

	public static function topic_slug( $post_id ) {
		$terms = get_the_terms( absint( $post_id ), self::TAX );
		return $terms && ! is_wp_error( $terms ) ? sanitize_title( $terms[0]->slug ) : '';
	}

	public static function topic_name( $post_id ) {
		$slug = self::topic_slug( $post_id );
		return isset( self::topics()[ $slug ] ) ? self::topics()[ $slug ] : '';
	}
}
