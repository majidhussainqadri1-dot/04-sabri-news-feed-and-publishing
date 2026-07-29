<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Interactions {
	public function hooks() {
		add_action( 'wp_ajax_snp_interact', array( $this, 'ajax' ) );
		add_action( 'template_redirect', array( $this, 'track_view' ) );
	}

	public function ajax() {
		check_ajax_referer( 'snp_interaction', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Please log in to use this action.' ), 401 );
		}
		$user_id = get_current_user_id();
		$post_id = isset( $_POST['postId'] ) ? absint( $_POST['postId'] ) : 0;
		$kind    = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$post    = get_post( $post_id );
		if ( ! $post || SNP_Content::TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			wp_send_json_error( array( 'message' => 'Publication not found.' ), 404 );
		}
		if ( $this->rate_limited( $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Please wait before trying another action.' ), 429 );
		}
		if ( 'like' === $kind ) {
			$active = $this->toggle( 'snp_reactions', $post_id, $user_id );
			self::recalculate( $post_id );
			wp_send_json_success( array( 'active' => $active, 'label' => $active ? 'Unlike' : 'Like', 'count' => self::likes( $post_id ) ) );
		}
		if ( 'save' === $kind ) {
			$active = $this->toggle( 'snp_saves', $post_id, $user_id );
			self::recalculate( $post_id );
			wp_send_json_success( array( 'active' => $active, 'label' => $active ? 'Saved' : 'Save', 'count' => self::saves( $post_id ) ) );
		}
		if ( 'report' === $kind ) {
			$this->report( $post_id, $user_id );
			wp_send_json_success( array( 'message' => 'Report received for administrator review.' ) );
		}
		wp_send_json_error( array( 'message' => 'Unknown action.' ), 400 );
	}

	private function toggle( $suffix, $post_id, $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . $suffix;
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE post_id = %d AND user_id = %d", $post_id, $user_id ) );
		if ( $id ) {
			$wpdb->delete( $table, array( 'id' => absint( $id ) ), array( '%d' ) );
			return false;
		}
		$wpdb->insert( $table, array( 'post_id' => $post_id, 'user_id' => $user_id, 'created_at' => current_time( 'mysql', true ) ), array( '%d', '%d', '%s' ) );
		return true;
	}

	private function report( $post_id, $user_id ) {
		global $wpdb;
		$allowed = array( 'medical-misinformation', 'spam', 'harassment', 'copyright', 'inappropriate', 'other' );
		$reason  = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '';
		$details = isset( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['details'] ) ) : '';
		if ( ! in_array( $reason, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => 'Please choose a valid report reason.' ), 400 );
		}
		$details = function_exists( 'mb_substr' ) ? mb_substr( $details, 0, 1000 ) : substr( $details, 0, 1000 );
		$wpdb->replace(
			$wpdb->prefix . 'snp_reports',
			array( 'post_id' => $post_id, 'user_id' => $user_id, 'reason' => $reason, 'details' => $details, 'status' => 'open', 'created_at' => current_time( 'mysql', true ) ),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	private function rate_limited( $user_id ) {
		$key   = 'snp_actions_' . absint( $user_id );
		$count = absint( get_transient( $key ) );
		if ( $count >= 30 ) {
			return true;
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return false;
	}

	public function track_view() {
		if ( ! is_singular( SNP_Content::TYPE ) ) {
			return;
		}
		$post_id = get_queried_object_id();
		$cookie  = 'snp_viewed_' . absint( $post_id );
		if ( isset( $_COOKIE[ $cookie ] ) ) {
			return;
		}
		$count = absint( get_post_meta( $post_id, '_snp_views', true ) );
		update_post_meta( $post_id, '_snp_views', $count + 1 );
		setcookie( $cookie, '1', array( 'expires' => time() + 12 * HOUR_IN_SECONDS, 'path' => COOKIEPATH ? COOKIEPATH : '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		self::recalculate( $post_id );
	}

	public static function likes( $post_id ) {
		global $wpdb;
		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}snp_reactions WHERE post_id = %d", absint( $post_id ) ) ) );
	}

	public static function saves( $post_id ) {
		global $wpdb;
		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}snp_saves WHERE post_id = %d", absint( $post_id ) ) ) );
	}

	public static function liked( $post_id, $user_id = 0 ) {
		global $wpdb;
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return $user_id && (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}snp_reactions WHERE post_id = %d AND user_id = %d", absint( $post_id ), $user_id ) );
	}

	public static function saved( $post_id, $user_id = 0 ) {
		global $wpdb;
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return $user_id && (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}snp_saves WHERE post_id = %d AND user_id = %d", absint( $post_id ), $user_id ) );
	}

	public static function recalculate( $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post || SNP_Content::TYPE !== $post->post_type ) {
			return;
		}
		$views    = absint( get_post_meta( $post_id, '_snp_views', true ) );
		$likes    = self::likes( $post_id );
		$saves    = self::saves( $post_id );
		$comments = absint( get_comments_number( $post_id ) );
		$hours    = max( 1, ( time() - get_post_time( 'U', true, $post ) ) / HOUR_IN_SECONDS );
		$bonus    = SNP_Permissions::is_founder( $post->post_author ) ? 12 : 0;
		$bonus   += '1' === get_post_meta( $post_id, '_snp_featured', true ) ? 40 : 0;
		$bonus   += '1' === get_post_meta( $post_id, '_snp_pinned', true ) ? 1000 : 0;
		$score    = ( $views * 0.1 + $likes * 3 + $comments * 5 + $saves * 4 + $bonus + 1 ) / pow( $hours + 2, 0.55 );
		update_post_meta( $post_id, '_snp_viral_score', round( $score, 6 ) );
	}

	public static function recalculate_all() {
		$ids = get_posts( array( 'post_type' => SNP_Content::TYPE, 'post_status' => 'publish', 'posts_per_page' => 200, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true ) );
		foreach ( $ids as $post_id ) {
			self::recalculate( $post_id );
		}
	}
}
