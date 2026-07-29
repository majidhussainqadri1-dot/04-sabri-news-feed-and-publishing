<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Interactions {
	public function hooks() {
		add_action( 'wp_ajax_snp_interact', array( $this, 'ajax' ) );
		add_action( 'template_redirect', array( $this, 'track_view' ), 20 );
		add_shortcode( 'sabri_my_publication_reports', array( $this, 'my_reports' ) );
		add_action( 'admin_post_snp_appeal_report', array( $this, 'appeal_report' ) );
	}

	public function my_reports() {
		if ( ! is_user_logged_in() ) {
			return '<div class="snp-notice">Log in to view publication reports.</div>';
		}
		global $wpdb;
		$reports = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}snp_reports WHERE user_id=%d ORDER BY created_at DESC LIMIT 200", get_current_user_id() ) );
		ob_start();
		?><main class="snp-shell"><h1>My Publication Reports</h1><table><thead><tr><th>Publication</th><th>Status</th><th>Outcome</th><th>Resolution</th><th>Appeal</th></tr></thead><tbody><?php if ( $reports ) : foreach ( $reports as $report ) : ?><tr><td><?php echo esc_html( get_the_title( $report->post_id ) ); ?></td><td><?php echo esc_html( $report->status ); ?></td><td><?php echo esc_html( $report->outcome ); ?></td><td><?php echo esc_html( $report->resolution_note ); ?></td><td><?php if ( 'resolved' === $report->status && ! $report->appeal_status && $report->resolved_at && strtotime( $report->resolved_at . ' UTC' ) >= time() - 30 * DAY_IN_SECONDS ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="snp_appeal_report"><input type="hidden" name="report_id" value="<?php echo absint( $report->id ); ?>"><?php wp_nonce_field( 'snp_appeal_' . $report->id ); ?><textarea name="appeal_note" required maxlength="1000" placeholder="Explain the appeal"></textarea><button class="snp-button" type="submit">Submit appeal</button></form><?php else : ?><?php echo esc_html( $report->appeal_status ? $report->appeal_status : 'Not available' ); ?><?php endif; ?></td></tr><?php endforeach; else : ?><tr><td colspan="5">No reports found.</td></tr><?php endif; ?></tbody></table></main><?php
		return ob_get_clean();
	}

	public function appeal_report() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Log in before submitting an appeal.', 'sabri-news-publishing' ), '', array( 'response' => 403 ) );
		}
		global $wpdb;
		$id   = isset( $_POST['report_id'] ) ? absint( $_POST['report_id'] ) : 0;
		$note = isset( $_POST['appeal_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['appeal_note'] ) ) : '';
		$note = function_exists( 'mb_substr' ) ? mb_substr( $note, 0, 1000 ) : substr( $note, 0, 1000 );
		check_admin_referer( 'snp_appeal_' . $id );
		$report = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}snp_reports WHERE id=%d AND user_id=%d", $id, get_current_user_id() ) );
		if ( ! $report || 'resolved' !== $report->status || $report->appeal_status || ! $note || ! $report->resolved_at || strtotime( $report->resolved_at . ' UTC' ) < time() - 30 * DAY_IN_SECONDS ) {
			wp_die( esc_html__( 'This report is not eligible for appeal.', 'sabri-news-publishing' ), '', array( 'response' => 400 ) );
		}
		$open_key = hash( 'sha256', absint( $report->post_id ) . '|' . get_current_user_id() . '|appeal|' . $id );
		$wpdb->update( $wpdb->prefix . 'snp_reports', array( 'status' => 'appealed', 'appeal_status' => 'pending', 'appeal_note' => $note, 'appealed_at' => current_time( 'mysql', true ), 'open_key' => $open_key ), array( 'id' => $id ), array( '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
		SNP_Audit::record( $report->post_id, 'report_appealed', $note, array( 'report_id' => $id ) );
		do_action( 'snp_report_appealed', $id, absint( $report->post_id ) );
		SNP_Notification_Adapter::emit( 'report_appealed', SNP_Notification_Adapter::editorial_recipients(), array( 'report_id' => $id, 'post_id' => absint( $report->post_id ) ) );
		$pages = (array) get_option( 'snp_page_map', array() );
		wp_safe_redirect( ! empty( $pages['reports'] ) ? get_permalink( absint( $pages['reports'] ) ) : home_url( '/' ) );
		exit;
	}

	public function ajax() {
		nocache_headers();
		check_ajax_referer( 'snp_interaction', 'nonce' );
		if ( ! is_user_logged_in() || ! SNP_Membership_Adapter::is_active( get_current_user_id() ) ) {
			wp_send_json_error( array( 'message' => 'An approved account is required.' ), 401 );
		}
		$user_id = get_current_user_id();
		$post_id = isset( $_POST['postId'] ) ? absint( $_POST['postId'] ) : 0;
		$kind    = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		if ( ! SNP_Publication_State::public_eligible( $post_id ) ) {
			wp_send_json_error( array( 'message' => 'The publication is not available.' ), 404 );
		}
		if ( ! SNP_Rate_Limiter::allowed( 'interaction', $user_id, 30, MINUTE_IN_SECONDS ) ) {
			wp_send_json_error( array( 'message' => 'Please wait before trying another action.' ), 429 );
		}
		$post = get_post( $post_id );
		if ( in_array( $kind, array( 'like_add', 'like_remove' ), true ) ) {
			if ( (int) $post->post_author === (int) $user_id ) {
				wp_send_json_error( array( 'message' => 'Authors cannot Like their own publications.' ), 400 );
			}
			$active = 'like_add' === $kind ? $this->add( 'snp_reactions', $post_id, $user_id ) : $this->remove( 'snp_reactions', $post_id, $user_id );
			self::recalculate( $post_id );
			wp_send_json_success( array( 'active' => $active, 'nextKind' => $active ? 'like_remove' : 'like_add', 'label' => $active ? 'Unlike' : 'Like', 'count' => self::likes( $post_id ) ) );
		}
		if ( in_array( $kind, array( 'save_add', 'save_remove' ), true ) ) {
			$active = 'save_add' === $kind ? $this->add( 'snp_saves', $post_id, $user_id ) : $this->remove( 'snp_saves', $post_id, $user_id );
			self::recalculate( $post_id );
			wp_send_json_success( array( 'active' => $active, 'nextKind' => $active ? 'save_remove' : 'save_add', 'label' => $active ? 'Saved' : 'Save' ) );
		}
		if ( 'report' === $kind ) {
			$result = $this->report( $post_id, $user_id );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ), 409 );
			}
			wp_send_json_success( array( 'message' => 'Report received for protected editorial review.' ) );
		}
		wp_send_json_error( array( 'message' => 'Unknown action.' ), 400 );
	}

	private function add( $suffix, $post_id, $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . $suffix;
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} (post_id,user_id,created_at) VALUES (%d,%d,%s)", $post_id, $user_id, current_time( 'mysql', true ) ) );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$table} WHERE post_id=%d AND user_id=%d", $post_id, $user_id ) );
	}

	private function remove( $suffix, $post_id, $user_id ) {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . $suffix, array( 'post_id' => $post_id, 'user_id' => $user_id ), array( '%d', '%d' ) );
		return false;
	}

	private function report( $post_id, $user_id ) {
		global $wpdb;
		$allowed = array( 'medical-misinformation', 'privacy', 'spam', 'harassment', 'copyright', 'inappropriate', 'other' );
		$reason  = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '';
		$details = isset( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['details'] ) ) : '';
		if ( ! in_array( $reason, $allowed, true ) ) {
			return new WP_Error( 'snp_report_reason', 'Choose a valid report reason.' );
		}
		$details       = function_exists( 'mb_substr' ) ? mb_substr( $details, 0, 1000 ) : substr( $details, 0, 1000 );
		$reporter_hash = hash_hmac( 'sha256', 'user|' . $user_id, wp_salt( 'auth' ) );
		$open_key      = hash( 'sha256', $post_id . '|' . $user_id . '|open' );
		$result        = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->prefix}snp_reports (post_id,user_id,reporter_hash,open_key,reason,details,status,created_at) VALUES (%d,%d,%s,%s,%s,%s,'open',%s)",
				$post_id,
				$user_id,
				$reporter_hash,
				$open_key,
				$reason,
				$details,
				current_time( 'mysql', true )
			)
		);
		if ( 1 !== $result ) {
			return new WP_Error( 'snp_report_exists', 'An open report from this account already exists for this publication.' );
		}
		SNP_Audit::record( $post_id, 'report_created', '', array( 'report_reason' => $reason ) );
		do_action( 'snp_report_created', $post_id, $wpdb->insert_id );
		SNP_Notification_Adapter::emit( 'report_created', SNP_Notification_Adapter::editorial_recipients(), array( 'report_id' => absint( $wpdb->insert_id ), 'post_id' => $post_id, 'reason' => $reason ) );
		return true;
	}

	public function track_view() {
		if ( ! is_singular( SNP_Content::TYPE ) ) {
			return;
		}
		$post_id = get_queried_object_id();
		if ( ! SNP_Publication_State::public_eligible( $post_id ) ) {
			return;
		}
		$ip         = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( (string) $_SERVER['HTTP_USER_AGENT'], 0, 180 ) : '';
		if ( ! is_user_logged_in() && ! $ip ) {
			return;
		}
		$subject = is_user_logged_in() ? 'u:' . get_current_user_id() : 'a:' . $ip . '|' . $user_agent;
		if ( ! SNP_Rate_Limiter::allowed( 'view', $subject, 120, HOUR_IN_SECONDS ) ) {
			return;
		}
		$day         = gmdate( 'Y-m-d' );
		$viewer_hash = hash_hmac( 'sha256', $day . '|' . $subject, wp_salt( 'nonce' ) );
		global $wpdb;
		$result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->prefix}snp_views (post_id,viewer_hash,view_day,created_at) VALUES (%d,%s,%s,%s)", $post_id, $viewer_hash, $day, current_time( 'mysql', true ) ) );
		if ( 1 === $result ) {
			self::recalculate( $post_id );
		}
	}

	public static function likes( $post_id ) {
		global $wpdb;
		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}snp_reactions WHERE post_id=%d", absint( $post_id ) ) ) );
	}

	public static function saves( $post_id ) {
		global $wpdb;
		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}snp_saves WHERE post_id=%d", absint( $post_id ) ) ) );
	}

	public static function views( $post_id ) {
		global $wpdb;
		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}snp_views WHERE post_id=%d", absint( $post_id ) ) ) );
	}

	public static function liked( $post_id, $user_id = 0 ) {
		global $wpdb;
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return $user_id && (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->prefix}snp_reactions WHERE post_id=%d AND user_id=%d", absint( $post_id ), $user_id ) );
	}

	public static function saved( $post_id, $user_id = 0 ) {
		global $wpdb;
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return $user_id && (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->prefix}snp_saves WHERE post_id=%d AND user_id=%d", absint( $post_id ), $user_id ) );
	}

	public static function recalculate( $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post || SNP_Content::TYPE !== $post->post_type || ! SNP_Publication_State::public_eligible( $post_id ) ) {
			delete_post_meta( absint( $post_id ), '_snp_viral_score' );
			return;
		}
		$views    = self::views( $post_id );
		$likes    = self::likes( $post_id );
		$saves    = self::saves( $post_id );
		$comments = absint( get_comments_number( $post_id ) );
		$hours    = max( 1, ( time() - get_post_time( 'U', true, $post ) ) / HOUR_IN_SECONDS );
		$bonus    = SNP_Membership_Adapter::is_founder( $post->post_author ) ? 12 : 0;
		$bonus   += '1' === get_post_meta( $post_id, '_snp_featured', true ) ? 40 : 0;
		$bonus   += '1' === get_post_meta( $post_id, '_snp_pinned', true ) ? 1000 : 0;
		$score    = ( $views * 0.1 + $likes * 3 + $comments * 5 + $saves * 4 + $bonus + 1 ) / pow( $hours + 2, 0.55 );
		update_post_meta( $post_id, '_snp_viral_score', round( $score, 6 ) );
	}

	public static function recalculate_all() {
		$ids = get_posts( array( 'post_type' => SNP_Content::TYPE, 'post_status' => 'publish', 'posts_per_page' => 500, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true ) );
		foreach ( $ids as $post_id ) {
			self::recalculate( $post_id );
		}
	}

	public static function cleanup_orphans() {
		global $wpdb;
		foreach ( array( 'snp_reactions', 'snp_saves', 'snp_views', 'snp_reports' ) as $suffix ) {
			$table = $wpdb->prefix . $suffix;
			$wpdb->query( "DELETE t FROM {$table} t LEFT JOIN {$wpdb->posts} p ON p.ID=t.post_id WHERE p.ID IS NULL" );
		}
	}
}
