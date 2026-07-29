<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Feed {
	public function hooks() {
		add_shortcode( 'sabri_publication_feed', array( __CLASS__, 'shortcode' ) );
		add_filter( 'the_content', array( $this, 'single_content' ), 20 );
	}

	public static function shortcode( $atts = array() ) {
		return self::render( is_array( $atts ) ? $atts : array() );
	}

	public static function query( array $args = array() ) {
		$defaults = array(
			'mode'           => 'popular',
			'topic'          => '',
			'search'         => '',
			'page'           => 1,
			'posts_per_page' => 12,
		);
		$args     = wp_parse_args( $args, $defaults );
		$mode     = in_array( $args['mode'], array( 'popular', 'latest', 'founder', 'doctors', 'saved' ), true ) ? $args['mode'] : 'popular';
		$topic    = sanitize_title( $args['topic'] );
		$paged    = max( 1, absint( $args['page'] ) );
		$query    = array(
			'post_type'           => SNP_Content::TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => min( 48, max( 1, absint( $args['posts_per_page'] ) ) ),
			'paged'               => $paged,
			's'                   => sanitize_text_field( $args['search'] ),
			'ignore_sticky_posts' => true,
			'meta_query'          => array(
				'relation' => 'AND',
				array( 'key' => SNP_Publication_State::STATE_META, 'value' => 'approved' ),
				array( 'key' => SNP_Publication_State::SNAPSHOT_META, 'compare' => 'EXISTS' ),
				array(
					'relation' => 'OR',
					array( 'key' => SNP_Publication_State::SANCTION_META, 'compare' => 'NOT EXISTS' ),
					array( 'key' => SNP_Publication_State::SANCTION_META, 'value' => '1', 'compare' => '!=' ),
				),
			),
			'tax_query'           => array(
				array(
					'taxonomy' => SNP_Content::TAX,
					'field'    => 'slug',
					'terms'    => $topic && SNP_Content::topic_allowed( $topic ) ? array( $topic ) : array_keys( SNP_Content::topics() ),
				),
			),
		);
		$eligible_authors = SNP_Profile_Adapter::eligible_author_ids( 'doctors' === $mode );
		$query['author__in'] = $eligible_authors ? $eligible_authors : array( 0 );
		if ( 'latest' === $mode ) {
			$query['orderby'] = 'date';
			$query['order']   = 'DESC';
		} elseif ( 'founder' === $mode ) {
			$founder_id = SNP_Membership_Adapter::founder_id();
			if ( ! $founder_id ) {
				$query['post__in'] = array( 0 );
			}
			$query['author']  = $founder_id;
			$query['orderby'] = 'date';
			$query['order']   = 'DESC';
		} elseif ( 'saved' === $mode ) {
			$query['post__in'] = self::saved_ids();
			$query['orderby']  = 'post__in';
		} else {
			$query['meta_key'] = '_snp_viral_score';
			$query['orderby']  = 'meta_value_num';
			$query['order']    = 'DESC';
		}
		$wp_query = new WP_Query( apply_filters( 'snp_publication_query_args', $query, $args ) );
		$wp_query->posts = array_values(
			array_filter(
				$wp_query->posts,
				function ( $post ) use ( $mode ) {
					if ( ! SNP_Publication_State::public_eligible( $post->ID ) ) {
						return false;
					}
					return 'doctors' !== $mode || SNP_Profile_Adapter::doctor_eligible( $post->post_author );
				}
			)
		);
		$wp_query->post_count = count( $wp_query->posts );
		return $wp_query;
	}

	public static function render( array $args = array() ) {
		$mode = isset( $args['mode'] ) ? sanitize_key( $args['mode'] ) : ( isset( $_GET['feed'] ) ? sanitize_key( wp_unslash( $_GET['feed'] ) ) : 'popular' );
		if ( is_user_logged_in() || 'saved' === $mode ) {
			SNP_Plugin::send_private_headers();
		}
		do_action( 'snp_feed_rendering', $mode, $args );
		if ( 'saved' === $mode && ! is_user_logged_in() ) {
			return '<div class="snp-notice"><p>Log in to see saved publications.</p><a class="snp-button" href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Log In</a></div>';
		}
		$topic  = isset( $args['topic'] ) ? sanitize_title( $args['topic'] ) : ( isset( $_GET['news_topic'] ) ? sanitize_title( wp_unslash( $_GET['news_topic'] ) ) : '' );
		$search = isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : ( isset( $_GET['news_search'] ) ? sanitize_text_field( wp_unslash( $_GET['news_search'] ) ) : '' );
		$page   = isset( $args['page'] ) ? absint( $args['page'] ) : max( 1, isset( $_GET['news_page'] ) ? absint( $_GET['news_page'] ) : 1 );
		$query  = self::query( array( 'mode' => $mode, 'topic' => $topic, 'search' => $search, 'page' => $page ) );
		ob_start();
		?>
		<section class="snp-feed" aria-labelledby="snp-feed-title">
			<div class="snp-feed-head"><div><span class="snp-eyebrow">Approved Publishing Service</span><h2 id="snp-feed-title"><?php echo esc_html( isset( $args['heading'] ) ? $args['heading'] : 'News Publications' ); ?></h2></div><?php $pages = (array) get_option( 'snp_page_map', array() ); if ( SNP_Permissions::can_submit() && ! empty( $pages['publish'] ) ) : ?><a class="snp-button" href="<?php echo esc_url( get_permalink( absint( $pages['publish'] ) ) ); ?>">Create Publication</a><?php endif; ?></div>
			<nav class="snp-feed-tabs" aria-label="Publication filters"><?php foreach ( array( 'popular' => 'For You', 'latest' => 'Latest', 'founder' => 'Founder Posts', 'doctors' => 'Doctor Posts', 'saved' => 'Saved' ) as $key => $label ) : ?><a class="<?php echo $mode === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'feed', $key, remove_query_arg( 'news_page' ) ) ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?></nav>
			<form class="snp-feed-filter" method="get"><input type="hidden" name="feed" value="<?php echo esc_attr( $mode ); ?>"><label>Search<input type="search" name="news_search" value="<?php echo esc_attr( $search ); ?>"></label><label>Approved topic<select name="news_topic"><option value="">All approved topics</option><?php foreach ( SNP_Content::topics() as $slug => $name ) : ?><option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $topic, $slug ); ?>><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select></label><button class="snp-button" type="submit">Apply</button></form>
			<div class="snp-post-grid"><?php if ( $query->have_posts() ) : foreach ( $query->posts as $post ) { echo self::card( $post ); } else : ?><div class="snp-empty"><h3>No approved publications found</h3></div><?php endif; ?></div>
			<?php if ( $query->max_num_pages > 1 ) : ?><nav class="snp-pagination" aria-label="Publication pages"><?php echo wp_kses_post( paginate_links( array( 'total' => $query->max_num_pages, 'current' => $page, 'format' => '?news_page=%#%', 'add_args' => array_filter( array( 'feed' => $mode, 'news_topic' => $topic, 'news_search' => $search ) ) ) ) ); ?></nav><?php endif; ?>
			<p class="snp-disclaimer">Publications are educational and do not replace diagnosis, emergency services, or advice from a qualified healthcare professional.</p>
		</section>
		<?php
		wp_reset_postdata();
		return ob_get_clean();
	}

	private static function saved_ids() {
		if ( ! is_user_logged_in() ) {
			return array( 0 );
		}
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}snp_saves WHERE user_id = %d ORDER BY created_at DESC", get_current_user_id() ) );
		return $ids ? array_map( 'absint', $ids ) : array( 0 );
	}

	private static function card( $post ) {
		$post_id   = absint( $post->ID );
		$author_id = absint( $post->post_author );
		ob_start();
		?>
		<article class="snp-card" data-post-id="<?php echo $post_id; ?>">
			<a class="snp-card-media" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo has_post_thumbnail( $post_id ) ? get_the_post_thumbnail( $post_id, 'medium_large', array( 'loading' => 'lazy', 'alt' => get_the_title( $post_id ) ) ) : '<span>SH</span>'; ?></a>
			<div class="snp-card-body"><div class="snp-author"><a href="<?php echo esc_url( SNP_Profile_Adapter::profile_url( $author_id ) ); ?>"><?php echo self::avatar( $author_id ); ?></a><div><strong><a href="<?php echo esc_url( SNP_Profile_Adapter::profile_url( $author_id ) ); ?>"><?php echo esc_html( get_the_author_meta( 'display_name', $author_id ) ); ?></a></strong><span><?php echo esc_html( SNP_Profile_Adapter::author_label( $author_id ) ); ?> · <time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $post_id ) ); ?>"><?php echo esc_html( get_the_date( '', $post_id ) ); ?></time></span></div></div>
			<span class="snp-topic"><?php echo esc_html( SNP_Content::topic_name( $post_id ) ); ?></span><h3><a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a></h3><p><?php echo esc_html( wp_trim_words( get_the_excerpt( $post_id ), 28 ) ); ?></p>
			<?php echo self::actions( $post_id ); ?></div>
		</article>
		<?php
		return ob_get_clean();
	}

	public static function actions( $post_id ) {
		$logged = is_user_logged_in() && SNP_Membership_Adapter::is_active( get_current_user_id() );
		$liked  = $logged && SNP_Interactions::liked( $post_id );
		$saved  = $logged && SNP_Interactions::saved( $post_id );
		ob_start();
		?>
		<div class="snp-actions" data-snp-actions>
			<?php if ( $logged ) : ?><button type="button" data-snp-action="<?php echo $liked ? 'like_remove' : 'like_add'; ?>" class="<?php echo $liked ? 'is-active' : ''; ?>" aria-pressed="<?php echo $liked ? 'true' : 'false'; ?>"><?php echo $liked ? 'Unlike' : 'Like'; ?> <span data-snp-count><?php echo absint( SNP_Interactions::likes( $post_id ) ); ?></span></button><?php else : ?><a href="<?php echo esc_url( wp_login_url( get_permalink( $post_id ) ) ); ?>">Like <span><?php echo absint( SNP_Interactions::likes( $post_id ) ); ?></span></a><?php endif; ?>
			<a href="<?php echo esc_url( get_permalink( $post_id ) . '#comments' ); ?>">Comment <span><?php echo absint( get_comments_number( $post_id ) ); ?></span></a>
			<?php if ( $logged ) : ?><button type="button" data-snp-action="<?php echo $saved ? 'save_remove' : 'save_add'; ?>" class="<?php echo $saved ? 'is-active' : ''; ?>" aria-pressed="<?php echo $saved ? 'true' : 'false'; ?>"><?php echo $saved ? 'Saved' : 'Save'; ?></button><?php else : ?><a href="<?php echo esc_url( wp_login_url( get_permalink( $post_id ) ) ); ?>">Save</a><?php endif; ?>
			<button type="button" data-snp-share data-url="<?php echo esc_url( get_permalink( $post_id ) ); ?>" data-title="<?php echo esc_attr( get_the_title( $post_id ) ); ?>">Share</button>
			<?php if ( $logged ) : ?><details class="snp-report"><summary>Report</summary><form data-snp-report><select name="reason" required><option value="">Choose reason</option><option value="medical-misinformation">Medical misinformation</option><option value="privacy">Privacy concern</option><option value="spam">Spam</option><option value="harassment">Harassment</option><option value="copyright">Copyright concern</option><option value="inappropriate">Inappropriate content</option><option value="other">Other</option></select><textarea name="details" maxlength="1000" placeholder="Optional details"></textarea><button type="submit">Send report</button></form></details><?php endif; ?>
			<span class="snp-views"><?php echo absint( SNP_Interactions::views( $post_id ) ); ?> views</span>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function avatar( $user_id ) {
		$image_id = SNP_Profile_Adapter::avatar_id( $user_id );
		if ( $image_id ) {
			return wp_get_attachment_image( $image_id, array( 42, 42 ), false, array( 'alt' => get_the_author_meta( 'display_name', $user_id ), 'loading' => 'lazy' ) );
		}
		$name    = get_the_author_meta( 'display_name', $user_id );
		$initial = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1 ) : substr( $name, 0, 1 );
		return '<span class="snp-avatar-fallback" aria-hidden="true">' . esc_html( strtoupper( $initial ) ) . '</span>';
	}

	public function single_content( $content ) {
		if ( ! is_singular( SNP_Content::TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post_id = get_the_ID();
		if ( ! SNP_Publication_State::public_eligible( $post_id ) && ! SNP_Permissions::can_moderate() ) {
			return '';
		}
		$extra = absint( get_post_meta( $post_id, '_snp_additional_image_id', true ) );
		$video = get_post_meta( $post_id, '_snp_video_url', true );
		$refs  = get_post_meta( $post_id, '_snp_references', true );
		$head  = '<div class="snp-single-meta"><span class="snp-topic">' . esc_html( SNP_Content::topic_name( $post_id ) ) . '</span><span>' . esc_html( SNP_Profile_Adapter::author_label( get_post_field( 'post_author', $post_id ) ) ) . '</span></div>';
		$tail  = $extra ? '<figure class="snp-additional-image">' . wp_get_attachment_image( $extra, 'large', false, array( 'loading' => 'lazy' ) ) . '</figure>' : '';
		$tail .= $video ? '<p><a class="snp-button" href="' . esc_url( $video ) . '" target="_blank" rel="noopener noreferrer">Watch related video</a></p>' : '';
		if ( $refs ) {
			$tail .= '<section class="snp-references"><h2>References</h2><p>' . nl2br( esc_html( $refs ) ) . '</p></section>';
		}
		$tail .= '<p class="snp-disclaimer">This publication is educational and does not replace diagnosis, emergency services, or advice from a qualified healthcare professional. Results can vary.</p>' . self::actions( $post_id );
		return '<div class="snp-single" data-post-id="' . absint( $post_id ) . '">' . $head . $content . $tail . '</div>';
	}
}
