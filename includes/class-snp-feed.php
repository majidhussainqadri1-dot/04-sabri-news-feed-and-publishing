<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Feed {
	public function hooks() {
		add_shortcode( 'sabri_news_home', array( $this, 'home' ) );
		add_shortcode( 'sabri_news_feed', array( $this, 'feed' ) );
		add_filter( 'the_content', array( $this, 'replace_foundation' ), 8 );
		add_filter( 'the_content', array( $this, 'single_content' ), 20 );
	}

	public function replace_foundation( $content ) {
		if ( ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$pages = (array) get_option( 'spf_page_map', array() );
		$id    = get_queried_object_id();
		if ( ! empty( $pages['home'] ) && (int) $pages['home'] === (int) $id && has_shortcode( $content, 'sabri_platform_home' ) ) {
			return preg_replace( '/\[sabri_platform_home[^\]]*\]/', '[sabri_news_home]', $content );
		}
		if ( ! empty( $pages['news'] ) && (int) $pages['news'] === (int) $id && has_shortcode( $content, 'sabri_platform_module' ) ) {
			return '[sabri_news_feed]';
		}
		return $content;
	}

	public function home() {
		ob_start();
		?>
		<div class="snp-shell snp-home">
			<?php echo $this->navigation( 'home' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<header class="snp-hero"><div><span class="snp-eyebrow">Sabri Social Homeopathy Platform</span><h1>Classical Homeopathy News and Global Learning</h1><p>Read freely without registration. Create an account to Like, Unlike, comment, save or report. Publishing is limited to the Founder and verified doctors.</p></div><form role="search" method="get"><label class="screen-reader-text" for="snp-home-search">Search publications</label><input id="snp-home-search" name="news_search" type="search" placeholder="Search approved publications"><button type="submit">Search</button></form></header>
			<?php echo $this->feed( array( 'heading' => 'News Feed', 'home' => '1' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public function feed( $atts = array() ) {
		$atts = shortcode_atts( array( 'mode' => '', 'heading' => 'News', 'home' => '0' ), $atts, 'sabri_news_feed' );
		$mode = $atts['mode'] ? sanitize_key( $atts['mode'] ) : ( isset( $_GET['feed'] ) ? sanitize_key( wp_unslash( $_GET['feed'] ) ) : 'popular' );
		if ( ! in_array( $mode, array( 'popular', 'latest', 'founder', 'doctors', 'saved' ), true ) ) {
			$mode = 'popular';
		}
		if ( 'saved' === $mode && ! is_user_logged_in() ) {
			return '<div class="snp-notice"><p>Log in to see your saved publications.</p><a class="snp-button" href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Log In</a></div>';
		}
		$topic  = isset( $_GET['news_topic'] ) ? sanitize_title( wp_unslash( $_GET['news_topic'] ) ) : '';
		$search = isset( $_GET['news_search'] ) ? sanitize_text_field( wp_unslash( $_GET['news_search'] ) ) : '';
		$paged  = max( 1, get_query_var( 'paged' ), isset( $_GET['news_page'] ) ? absint( $_GET['news_page'] ) : 1 );
		$query  = new WP_Query( $this->query_args( $mode, $topic, $search, $paged ) );
		ob_start();
		?>
		<section class="snp-feed" aria-labelledby="snp-feed-title">
			<?php if ( '1' !== $atts['home'] ) { echo $this->navigation( 'news' ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="snp-feed-head"><div><span class="snp-eyebrow">Public News Area</span><h2 id="snp-feed-title"><?php echo esc_html( $atts['heading'] ); ?></h2></div><?php $pages = (array) get_option( 'snp_page_map', array() ); if ( SNP_Permissions::can_submit() && ! empty( $pages['publish'] ) ) : ?><a class="snp-button" href="<?php echo esc_url( get_permalink( $pages['publish'] ) ); ?>">Create Publication</a><?php endif; ?></div>
			<nav class="snp-feed-tabs" aria-label="News feed filters"><?php foreach ( array( 'popular' => 'For You', 'latest' => 'Latest', 'founder' => 'Founder Posts', 'doctors' => 'Doctor Posts', 'saved' => 'Saved' ) as $key => $label ) : ?><a class="<?php echo $mode === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'feed', $key, remove_query_arg( array( 'news_page' ) ) ) ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?></nav>
			<form class="snp-feed-filter" method="get"><input type="hidden" name="feed" value="<?php echo esc_attr( $mode ); ?>"><label>Search<input type="search" name="news_search" value="<?php echo esc_attr( $search ); ?>"></label><label>Approved topic<select name="news_topic"><option value="">All approved topics</option><?php foreach ( SNP_Content::topics() as $slug => $name ) : ?><option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $topic, $slug ); ?>><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select></label><button class="snp-button" type="submit">Apply</button></form>
			<div class="snp-post-grid">
			<?php if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); echo $this->card( get_post() ); endwhile; else : ?><div class="snp-empty"><h3>No publications found</h3><p>Published Founder and approved doctor material will appear here.</p></div><?php endif; ?>
			</div>
			<?php if ( $query->max_num_pages > 1 ) : ?><nav class="snp-pagination" aria-label="Publication pages"><?php echo wp_kses_post( paginate_links( array( 'total' => $query->max_num_pages, 'current' => $paged, 'format' => '?news_page=%#%', 'add_args' => array_filter( array( 'feed' => $mode, 'news_topic' => $topic, 'news_search' => $search ) ) ) ) ); ?></nav><?php endif; ?>
			<p class="snp-disclaimer">Publications are educational. They do not replace diagnosis, emergency services or advice from a qualified healthcare professional.</p>
		</section>
		<?php
		wp_reset_postdata();
		return ob_get_clean();
	}

	private function query_args( $mode, $topic, $search, $paged ) {
		$args = array( 'post_type' => SNP_Content::TYPE, 'post_status' => 'publish', 'posts_per_page' => 12, 'paged' => $paged, 's' => $search, 'ignore_sticky_posts' => true );
		$args['tax_query'] = array( array( 'taxonomy' => SNP_Content::TAX, 'field' => 'slug', 'terms' => $topic && SNP_Content::topic_allowed( $topic ) ? array( $topic ) : array_keys( SNP_Content::topics() ) ) );
		if ( 'latest' === $mode ) {
			$args['orderby'] = 'date'; $args['order'] = 'DESC';
		} elseif ( 'founder' === $mode ) {
			$args['author'] = SNP_Permissions::founder_id(); $args['orderby'] = 'date'; $args['order'] = 'DESC';
		} elseif ( 'doctors' === $mode ) {
			$args['author__in'] = $this->doctor_ids(); $args['orderby'] = 'date'; $args['order'] = 'DESC';
		} elseif ( 'saved' === $mode ) {
			$args['post__in'] = $this->saved_ids(); $args['orderby'] = 'post__in';
		} else {
			$args['meta_key'] = '_snp_viral_score'; $args['orderby'] = 'meta_value_num'; $args['order'] = 'DESC';
		}
		return $args;
	}

	private function saved_ids() {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}snp_saves WHERE user_id = %d ORDER BY created_at DESC", get_current_user_id() ) );
		return $ids ? array_map( 'absint', $ids ) : array( 0 );
	}

	private function doctor_ids() {
		$ids = get_users( array( 'role' => 'sabri_doctor_verified', 'fields' => 'ID' ) );
		return $ids ? array_map( 'absint', $ids ) : array( 0 );
	}

	private function card( $post ) {
		$post_id = $post->ID; $author_id = (int) $post->post_author;
		ob_start(); ?>
		<article class="snp-card" data-post-id="<?php echo absint( $post_id ); ?>">
			<a class="snp-card-media" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" aria-label="Read <?php echo esc_attr( get_the_title( $post_id ) ); ?>"><?php if ( has_post_thumbnail( $post_id ) ) { echo get_the_post_thumbnail( $post_id, 'medium_large', array( 'loading' => 'lazy', 'alt' => get_the_title( $post_id ) ) ); } else { echo '<span>SH</span>'; } ?></a>
			<div class="snp-card-body"><div class="snp-author"><a href="<?php echo esc_url( SNP_Permissions::profile_url( $author_id ) ); ?>"><?php echo $this->avatar( $author_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a><div><strong><a href="<?php echo esc_url( SNP_Permissions::profile_url( $author_id ) ); ?>"><?php echo esc_html( get_the_author_meta( 'display_name', $author_id ) ); ?></a></strong><span><?php echo esc_html( SNP_Permissions::author_label( $author_id ) ); ?> · <time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $post_id ) ); ?>"><?php echo esc_html( get_the_date( '', $post_id ) ); ?></time></span></div></div>
			<span class="snp-topic"><?php echo esc_html( SNP_Content::topic_name( $post_id ) ); ?></span><h3><a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a></h3><p><?php echo esc_html( wp_trim_words( get_the_excerpt( $post_id ), 28 ) ); ?></p>
			<?php echo $this->actions( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</article>
		<?php return ob_get_clean();
	}

	public function actions( $post_id ) {
		$logged = is_user_logged_in(); $liked = $logged && SNP_Interactions::liked( $post_id ); $saved = $logged && SNP_Interactions::saved( $post_id );
		ob_start(); ?>
		<div class="snp-actions" data-snp-actions>
			<?php if ( $logged ) : ?><button type="button" data-snp-action="like" class="<?php echo $liked ? 'is-active' : ''; ?>" aria-pressed="<?php echo $liked ? 'true' : 'false'; ?>"><?php echo $liked ? 'Unlike' : 'Like'; ?> <span data-snp-count><?php echo absint( SNP_Interactions::likes( $post_id ) ); ?></span></button><?php else : ?><a href="<?php echo esc_url( wp_login_url( get_permalink( $post_id ) ) ); ?>">Like <span><?php echo absint( SNP_Interactions::likes( $post_id ) ); ?></span></a><?php endif; ?>
			<a href="<?php echo esc_url( get_permalink( $post_id ) . '#comments' ); ?>">Comment <span><?php echo absint( get_comments_number( $post_id ) ); ?></span></a>
			<?php if ( $logged ) : ?><button type="button" data-snp-action="save" class="<?php echo $saved ? 'is-active' : ''; ?>" aria-pressed="<?php echo $saved ? 'true' : 'false'; ?>"><?php echo $saved ? 'Saved' : 'Save'; ?></button><?php else : ?><a href="<?php echo esc_url( wp_login_url( get_permalink( $post_id ) ) ); ?>">Save</a><?php endif; ?>
			<button type="button" data-snp-share data-url="<?php echo esc_url( get_permalink( $post_id ) ); ?>" data-title="<?php echo esc_attr( get_the_title( $post_id ) ); ?>">Share</button>
			<?php if ( $logged ) : ?><details class="snp-report"><summary>Report</summary><form data-snp-report><select name="reason" required><option value="">Choose reason</option><option value="medical-misinformation">Medical misinformation</option><option value="spam">Spam</option><option value="harassment">Harassment</option><option value="copyright">Copyright concern</option><option value="inappropriate">Inappropriate content</option><option value="other">Other</option></select><textarea name="details" maxlength="1000" placeholder="Optional details"></textarea><button type="submit">Send report</button></form></details><?php endif; ?>
			<span class="snp-views"><?php echo absint( get_post_meta( $post_id, '_snp_views', true ) ); ?> views</span>
			<?php if ( ! $logged ) : ?><span class="snp-login-note"><a href="<?php echo esc_url( wp_login_url( get_permalink( $post_id ) ) ); ?>">Log in to interact</a></span><?php endif; ?>
		</div>
		<?php return ob_get_clean();
	}

	private function avatar( $user_id ) {
		if ( class_exists( 'SPD_Helpers' ) ) {
			$image_id = absint( SPD_Helpers::get( $user_id, 'profile_photo_id', 0 ) );
			if ( $image_id ) {
				return wp_get_attachment_image( $image_id, array( 42, 42 ), false, array( 'alt' => get_the_author_meta( 'display_name', $user_id ), 'loading' => 'lazy' ) );
			}
		}
		$name = get_the_author_meta( 'display_name', $user_id );
		$initial = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1 ) : substr( $name, 0, 1 );
		return '<span class="snp-avatar-fallback" aria-hidden="true">' . esc_html( strtoupper( $initial ) ) . '</span>';
	}

	public function single_content( $content ) {
		if ( ! is_singular( SNP_Content::TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post_id = get_the_ID(); $extra = absint( get_post_meta( $post_id, '_snp_additional_image_id', true ) ); $video = get_post_meta( $post_id, '_snp_video_url', true ); $refs = get_post_meta( $post_id, '_snp_references', true );
		$head = '<div class="snp-single-meta"><span class="snp-topic">' . esc_html( SNP_Content::topic_name( $post_id ) ) . '</span><span>' . esc_html( SNP_Permissions::author_label( get_post_field( 'post_author', $post_id ) ) ) . '</span></div>';
		$tail = $extra ? '<figure class="snp-additional-image">' . wp_get_attachment_image( $extra, 'large', false, array( 'loading' => 'lazy' ) ) . '</figure>' : '';
		$tail .= $video ? '<p><a class="snp-button" href="' . esc_url( $video ) . '" target="_blank" rel="noopener noreferrer">Watch related video</a></p>' : '';
		if ( $refs ) { $tail .= '<section class="snp-references"><h2>References</h2><p>' . nl2br( esc_html( $refs ) ) . '</p></section>'; }
		$tail .= '<p class="snp-disclaimer">This publication is educational and does not replace diagnosis, emergency services or advice from a qualified healthcare professional. Results can vary.</p>' . $this->actions( $post_id );
		return '<div class="snp-single" data-post-id="' . absint( $post_id ) . '">' . $head . $content . $tail . '</div>';
	}

	private function navigation( $active ) {
		$specs = array(
			'home' => array( 'Home', 'sabri-platform-home' ), 'news' => array( 'News', 'sabri-news' ), 'founder' => array( 'Founder', 'sabri-founder' ), 'learn' => array( 'Learn Sabri Classical Homeopathy', 'learn-sabri-classical-homeopathy' ), 'encyclopedia' => array( 'Encyclopedia', 'homeopathy-encyclopedia' ), 'doctors' => array( 'Doctors', 'homeopathy-doctors' ), 'clinic' => array( 'Worldwide Clinic', 'worldwide-clinic' ), 'videos' => array( 'Video Wall', 'video-wall' ), 'reels' => array( 'Reels', 'reels' ), 'pdf' => array( 'PDF Library', 'pdf-library' ), 'radar' => array( 'Radar', 'homeopathy-radar' ), 'ai' => array( 'Sabri Classical Homeopathy AI', 'sabri-classical-homeopathy-ai' ), 'network' => array( 'Network', 'homeopathy-network' ), 'marketplace' => array( 'Marketplace', 'homeopathy-marketplace' ),
		);
		$pages = (array) get_option( 'spf_page_map', array() ); $out = '<nav class="snp-main-nav" aria-label="Main platform navigation">';
		foreach ( $specs as $key => $spec ) { $url = ! empty( $pages[ $key ] ) ? get_permalink( $pages[ $key ] ) : home_url( '/' . $spec[1] . '/' ); $out .= '<a class="' . ( $active === $key ? 'is-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $spec[0] ) . '</a>'; }
		return $out . '</nav>';
	}
}
