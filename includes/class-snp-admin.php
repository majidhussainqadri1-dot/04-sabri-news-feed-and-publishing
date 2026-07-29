<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Admin {
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_snp_review_post', array( $this, 'review' ) );
		add_action( 'admin_post_snp_resolve_report', array( $this, 'resolve_report' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	public function menu() {
		add_menu_page( 'News & Publishing', 'News & Publishing', 'manage_sabri_news', 'sabri-news-publishing', array( $this, 'dashboard' ), 'dashicons-megaphone', 28 );
		add_submenu_page( 'sabri-news-publishing', 'Moderation', 'Moderation', 'manage_sabri_news', 'sabri-news-publishing', array( $this, 'dashboard' ) );
		add_submenu_page( 'sabri-news-publishing', 'Reported Publications', 'Reports', 'manage_sabri_news', 'sabri-news-reports', array( $this, 'reports' ) );
		add_submenu_page( 'sabri-news-publishing', 'All Publications', 'All Publications', 'manage_sabri_news', 'edit.php?post_type=' . SNP_Content::TYPE );
		add_submenu_page( 'sabri-news-publishing', 'Comment Moderation', 'Comments', 'manage_sabri_news', 'edit-comments.php' );
	}

	public function dashboard() {
		$this->guard();
		$status = isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : 'pending';
		if ( ! in_array( $status, array( 'pending', 'publish', 'draft', 'private' ), true ) ) { $status = 'pending'; }
		$posts = get_posts( array( 'post_type' => SNP_Content::TYPE, 'post_status' => $status, 'posts_per_page' => 100, 'orderby' => 'date', 'order' => 'DESC' ) );
		?>
		<div class="wrap snp-admin"><h1>News & Publishing Moderation</h1><p>Only the sixteen approved topics may be published. Patient Cases must remain anonymized and carry appropriate permission.</p>
		<nav class="snp-admin-tabs"><?php foreach ( array( 'pending' => 'Pending', 'publish' => 'Published', 'draft' => 'Rejected / Draft', 'private' => 'Hidden' ) as $key => $label ) : ?><a class="<?php echo $status === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'sabri-news-publishing', 'post_status' => $key ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?></nav>
		<table class="widefat striped"><thead><tr><th>Publication</th><th>Author and topic</th><th>Signals</th><th>Moderation</th></tr></thead><tbody>
		<?php if ( $posts ) : foreach ( $posts as $post ) : $topic = SNP_Content::topic_slug( $post->ID ); ?>
		<tr><td><strong><?php echo esc_html( $post->post_title ); ?></strong><p><?php echo esc_html( wp_trim_words( $post->post_excerpt, 24 ) ); ?></p><details><summary>Review full content</summary><div class="snp-admin-content"><?php echo wp_kses_post( wpautop( $post->post_content ) ); ?></div></details><small><?php echo esc_html( get_the_date( '', $post ) ); ?></small></td><td><?php echo esc_html( get_the_author_meta( 'display_name', $post->post_author ) ); ?><br><span class="snp-admin-topic"><?php echo esc_html( SNP_Content::topic_name( $post->ID ) ?: 'Missing approved topic' ); ?></span><?php if ( 'patient-cases' === $topic ) : ?><br><strong>Patient safeguards: <?php echo '1' === get_post_meta( $post->ID, '_snp_case_anonymized', true ) && '1' === get_post_meta( $post->ID, '_snp_case_consent', true ) ? 'Confirmed' : 'Missing'; ?></strong><?php endif; ?></td><td><?php echo absint( SNP_Interactions::likes( $post->ID ) ); ?> likes<br><?php echo absint( get_comments_number( $post->ID ) ); ?> comments<br><?php echo absint( get_post_meta( $post->ID, '_snp_views', true ) ); ?> views</td><td><?php echo $this->review_form( $post ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td></tr>
		<?php endforeach; else : ?><tr><td colspan="4">No publications in this view.</td></tr><?php endif; ?></tbody></table></div>
		<?php
	}

	private function review_form( $post ) {
		ob_start(); ?>
		<form class="snp-review-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="snp_review_post"><input type="hidden" name="post_id" value="<?php echo absint( $post->ID ); ?>"><?php wp_nonce_field( 'snp_review_' . $post->ID ); ?><select name="review_action"><option value="approve">Approve and publish</option><option value="reject">Reject to draft</option><option value="feature"><?php echo '1' === get_post_meta( $post->ID, '_snp_featured', true ) ? 'Remove featured' : 'Feature'; ?></option><option value="pin"><?php echo '1' === get_post_meta( $post->ID, '_snp_pinned', true ) ? 'Unpin' : 'Pin'; ?></option><option value="hide">Hide publication</option></select><textarea name="note" rows="2" placeholder="Internal review note"></textarea><button class="button button-primary" type="submit">Apply</button></form>
		<?php return ob_get_clean();
	}

	public function review() {
		$this->guard(); $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0; check_admin_referer( 'snp_review_' . $post_id );
		$post = get_post( $post_id ); $action = isset( $_POST['review_action'] ) ? sanitize_key( wp_unslash( $_POST['review_action'] ) ) : ''; $note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		if ( ! $post || SNP_Content::TYPE !== $post->post_type || ! in_array( $action, array( 'approve', 'reject', 'feature', 'pin', 'hide' ), true ) ) { wp_die( esc_html__( 'Invalid moderation request.', 'sabri-news-publishing' ), '', array( 'response' => 400 ) ); }
		$topic = SNP_Content::topic_slug( $post_id );
		if ( 'approve' === $action ) {
			if ( ! SNP_Content::topic_allowed( $topic ) || ( 'patient-cases' === $topic && ( '1' !== get_post_meta( $post_id, '_snp_case_anonymized', true ) || '1' !== get_post_meta( $post_id, '_snp_case_consent', true ) ) ) ) { wp_die( esc_html__( 'This publication does not satisfy the approved topic or Patient Case safeguards.', 'sabri-news-publishing' ), '', array( 'response' => 400 ) ); }
			if ( ! SNP_Permissions::is_founder( $post->post_author ) && ! user_can( $post->post_author, 'manage_sabri_news' ) && ! SNP_Permissions::is_verified_doctor( $post->post_author ) ) { wp_die( esc_html__( 'The author is no longer approved to publish.', 'sabri-news-publishing' ), '', array( 'response' => 400 ) ); }
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
		} elseif ( 'reject' === $action ) { wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) ); update_post_meta( $post_id, '_snp_review_note', $note );
		} elseif ( 'hide' === $action ) { wp_update_post( array( 'ID' => $post_id, 'post_status' => 'private' ) );
		} elseif ( 'feature' === $action ) { update_post_meta( $post_id, '_snp_featured', '1' === get_post_meta( $post_id, '_snp_featured', true ) ? '0' : '1' );
		} elseif ( 'pin' === $action ) { update_post_meta( $post_id, '_snp_pinned', '1' === get_post_meta( $post_id, '_snp_pinned', true ) ? '0' : '1' ); }
		self::audit( $post_id, $action, $note ); SNP_Interactions::recalculate( $post_id );
		wp_safe_redirect( add_query_arg( array( 'page' => 'sabri-news-publishing', 'updated' => '1' ), admin_url( 'admin.php' ) ) ); exit;
	}

	public function reports() {
		$this->guard(); global $wpdb; $reports = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}snp_reports WHERE status = 'open' ORDER BY created_at DESC LIMIT 200" );
		?><div class="wrap snp-admin"><h1>Reported Publications</h1><table class="widefat striped"><thead><tr><th>Publication</th><th>Reporter</th><th>Reason</th><th>Details</th><th>Action</th></tr></thead><tbody><?php if ( $reports ) : foreach ( $reports as $report ) : ?><tr><td><a href="<?php echo esc_url( get_permalink( $report->post_id ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( $report->post_id ) ); ?></a></td><td><?php echo esc_html( get_the_author_meta( 'display_name', $report->user_id ) ); ?></td><td><?php echo esc_html( ucwords( str_replace( '-', ' ', $report->reason ) ) ); ?></td><td><?php echo esc_html( $report->details ); ?></td><td><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="snp_resolve_report"><input type="hidden" name="report_id" value="<?php echo absint( $report->id ); ?>"><?php wp_nonce_field( 'snp_resolve_' . $report->id ); ?><button class="button" type="submit">Mark resolved</button></form></td></tr><?php endforeach; else : ?><tr><td colspan="5">No open reports.</td></tr><?php endif; ?></tbody></table></div><?php
	}

	public function resolve_report() {
		$this->guard(); global $wpdb; $id = isset( $_POST['report_id'] ) ? absint( $_POST['report_id'] ) : 0; check_admin_referer( 'snp_resolve_' . $id ); $wpdb->update( $wpdb->prefix . 'snp_reports', array( 'status' => 'resolved' ), array( 'id' => $id ), array( '%s' ), array( '%d' ) ); wp_safe_redirect( admin_url( 'admin.php?page=sabri-news-reports' ) ); exit;
	}

	public static function audit( $post_id, $action, $note ) {
		global $wpdb; $wpdb->insert( $wpdb->prefix . 'snp_audit_log', array( 'post_id' => absint( $post_id ), 'actor_id' => get_current_user_id(), 'action' => sanitize_key( $action ), 'note' => sanitize_textarea_field( $note ), 'created_at' => current_time( 'mysql', true ) ), array( '%d', '%d', '%s', '%s', '%s' ) );
	}

	private function guard() { if ( ! current_user_can( 'manage_sabri_news' ) ) { wp_die( esc_html__( 'You are not allowed to moderate publications.', 'sabri-news-publishing' ), '', array( 'response' => 403 ) ); } }
	public function notice() { if ( current_user_can( 'manage_sabri_news' ) && get_transient( 'snp_activation_notice' ) ) { delete_transient( 'snp_activation_notice' ); echo '<div class="notice notice-success is-dismissible"><p><strong>Sabri News Feed and Publishing is active.</strong> Review pending publications from News &amp; Publishing.</p></div>'; } }
}
