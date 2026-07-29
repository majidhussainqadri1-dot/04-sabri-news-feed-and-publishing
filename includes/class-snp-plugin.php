<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Plugin {
	public function run() {
		add_action( 'init', array( 'SNP_Content', 'register' ) );
		( new SNP_Publishing() )->hooks();
		( new SNP_Feed() )->hooks();
		( new SNP_Interactions() )->hooks();
		( new SNP_Comments() )->hooks();
		( new SNP_Admin() )->hooks();
		( new SNP_SEO() )->hooks();
		( new SNP_Privacy() )->hooks();
		add_action( 'template_redirect', array( 'SNP_Content', 'guard_public_topic' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'snp_recalculate_scores', array( 'SNP_Interactions', 'recalculate_all' ) );
	}

	public function assets() {
		global $post;
		$pages = (array) get_option( 'spf_page_map', array() );
		$snp_pages = (array) get_option( 'snp_page_map', array() );
		$core_pages = array_filter( array( isset( $pages['home'] ) ? $pages['home'] : 0, isset( $pages['news'] ) ? $pages['news'] : 0 ) );
		$is_module_page = $post instanceof WP_Post && ( in_array( (int) $post->ID, array_map( 'absint', array_merge( $core_pages, $snp_pages ) ), true ) || has_shortcode( $post->post_content, 'sabri_news_feed' ) || has_shortcode( $post->post_content, 'sabri_publish_form' ) );
		if ( ! is_singular( SNP_Content::TYPE ) && ! is_post_type_archive( SNP_Content::TYPE ) && ! $is_module_page ) {
			return;
		}
		wp_enqueue_style( 'snp-news', SNP_URL . 'assets/css/news.css', array(), SNP_VERSION );
		wp_enqueue_style( 'snp-news-avatar', SNP_URL . 'assets/css/avatar.css', array( 'snp-news' ), SNP_VERSION );
		wp_enqueue_script( 'snp-news', SNP_URL . 'assets/js/news.js', array(), SNP_VERSION, true );
		wp_localize_script( 'snp-news', 'snpNews', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'snp_interaction' ), 'loginUrl' => wp_login_url( home_url( '/' ) ) ) );
	}

	public function admin_assets( $hook ) {
		if ( false !== strpos( $hook, 'sabri-news' ) || ( isset( $_GET['post_type'] ) && SNP_Content::TYPE === sanitize_key( wp_unslash( $_GET['post_type'] ) ) ) ) {
			wp_enqueue_style( 'snp-news-admin', SNP_URL . 'assets/css/admin.css', array(), SNP_VERSION );
			wp_enqueue_style( 'snp-news-admin-review', SNP_URL . 'assets/css/admin-review.css', array( 'snp-news-admin' ), SNP_VERSION );
		}
	}
}
