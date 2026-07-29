<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Plugin {
	public function run() {
		add_action( 'init', array( 'SNP_Content', 'register' ) );
		add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
		( new SNP_Privacy() )->hooks();

		if ( ! SNP_Permissions::dependencies_available() ) {
			return;
		}

		add_action( 'init', array( 'SNP_Activator', 'maybe_upgrade' ), 20 );
		( new SNP_Publishing() )->hooks();
		( new SNP_Feed() )->hooks();
		( new SNP_Interactions() )->hooks();
		( new SNP_Comments() )->hooks();
		( new SNP_Admin() )->hooks();
		( new SNP_SEO() )->hooks();
		add_action( 'template_redirect', array( 'SNP_Publication_State', 'guard_single' ), 1 );
		add_action( 'wp_after_insert_post', array( 'SNP_Publication_State', 'enforce_after_save' ), 20, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'template_redirect', array( $this, 'private_headers' ), 0 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_action( 'snp_recalculate_scores', array( 'SNP_Interactions', 'recalculate_all' ) );
		add_action( 'snp_daily_maintenance', array( $this, 'maintenance' ) );
		add_filter( 'sabri_universal_composer_types', array( $this, 'composer_type' ) );
		add_filter( 'sabri_file21_publication_provider', array( $this, 'file21_provider' ) );
		add_filter( 'wp_sitemaps_post_types', array( $this, 'sitemap_post_types' ) );
	}

	public function dependency_notice() {
		if ( SNP_Permissions::dependencies_available() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>File 04 is fail-closed.</strong> File 00, the corrected File 03 profile projection, and File 09 doctor verification must be active before publishing, editorial review, interactions, or feed data can run.</p></div>';
	}

	public function file21_provider( $provider ) {
		if ( empty( $provider ) ) {
			return array(
				'query_callback'          => 'snp_query_approved_publications',
				'render_callback'         => 'snp_render_publication_feed',
				'eligibility'             => array( 'SNP_Publication_State', 'public_eligible' ),
				'enqueue_callback'        => array( $this, 'enqueue_assets' ),
				'private_headers_callback'=> array( __CLASS__, 'send_private_headers' ),
				'version'                 => SNP_VERSION,
			);
		}
		return $provider;
	}

	public function sitemap_post_types( $post_types ) {
		if ( is_array( $post_types ) ) {
			unset( $post_types[ SNP_Content::TYPE ] );
		}
		return $post_types;
	}

	public function composer_type( $types ) {
		$types = is_array( $types ) ? $types : array();
		$types['publication'] = array(
			'label'         => 'Publication',
			'permission'    => array( 'SNP_Permissions', 'can_submit' ),
			'endpoint'      => 'snp_submit_publication',
			'private_first' => true,
		);
		return $types;
	}

	public function assets() {
		global $post;
		$pages          = (array) get_option( 'snp_page_map', array() );
		$is_module_page = $post instanceof WP_Post
			&& ( in_array( (int) $post->ID, array_map( 'absint', $pages ), true )
				|| has_shortcode( $post->post_content, 'sabri_publication_feed' )
				|| has_shortcode( $post->post_content, 'sabri_publish_form' ) );
		if ( ! is_singular( SNP_Content::TYPE ) && ! $is_module_page ) {
			return;
		}
		$this->enqueue_assets();
	}

	public function enqueue_assets() {
		wp_enqueue_style( 'snp-news', SNP_URL . 'assets/css/news.css', array(), SNP_VERSION );
		wp_enqueue_style( 'snp-news-avatar', SNP_URL . 'assets/css/avatar.css', array( 'snp-news' ), SNP_VERSION );
		wp_enqueue_script( 'snp-news', SNP_URL . 'assets/js/news.js', array(), SNP_VERSION, true );
		wp_localize_script(
			'snp-news',
			'snpNews',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'snp_interaction' ),
				'loginUrl' => wp_login_url( home_url( '/' ) ),
			)
		);
	}

	public function admin_assets( $hook ) {
		if ( false !== strpos( $hook, 'sabri-news' ) || ( isset( $_GET['post_type'] ) && SNP_Content::TYPE === sanitize_key( wp_unslash( $_GET['post_type'] ) ) ) ) {
			wp_enqueue_style( 'snp-news-admin', SNP_URL . 'assets/css/admin.css', array(), SNP_VERSION );
			wp_enqueue_style( 'snp-news-admin-review', SNP_URL . 'assets/css/admin-review.css', array( 'snp-news-admin' ), SNP_VERSION );
		}
	}

	private function is_private_request() {
		$pages = (array) get_option( 'snp_page_map', array() );
		if ( ! empty( $pages['publish'] ) && is_page( absint( $pages['publish'] ) ) ) {
			return true;
		}
		if ( ! empty( $pages['saved'] ) && is_page( absint( $pages['saved'] ) ) ) {
			return true;
		}
		if ( ! empty( $pages['reports'] ) && is_page( absint( $pages['reports'] ) ) ) {
			return true;
		}
		if ( isset( $_GET['feed'] ) && 'saved' === sanitize_key( wp_unslash( $_GET['feed'] ) ) ) {
			return true;
		}
		if ( is_singular( SNP_Content::TYPE ) && ! SNP_Publication_State::public_eligible( get_queried_object_id() ) ) {
			return true;
		}
		global $post;
		$is_feed = $post instanceof WP_Post && has_shortcode( $post->post_content, 'sabri_publication_feed' );
		return is_user_logged_in() && ( $is_feed || is_singular( SNP_Content::TYPE ) );
	}

	public function private_headers() {
		if ( $this->is_private_request() ) {
			self::send_private_headers();
		}
	}

	public static function send_private_headers() {
		if ( headers_sent() ) {
			return;
		}
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'Pragma: no-cache', true );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'Referrer-Policy: no-referrer', true );
		header( 'X-Frame-Options: SAMEORIGIN', true );
		header( 'X-Content-Type-Options: nosniff', true );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()', true );
	}

	public function robots( $robots ) {
		if ( $this->is_private_request() ) {
			$robots['noindex']   = true;
			$robots['nofollow']  = true;
			$robots['noarchive'] = true;
		}
		return $robots;
	}

	public function maintenance() {
		SNP_Rate_Limiter::cleanup();
		SNP_Media::cleanup_staging();
		SNP_Audit::retention();
		SNP_Privacy::retention();
		SNP_Interactions::cleanup_orphans();
	}
}
