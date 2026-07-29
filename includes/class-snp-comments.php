<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Comments {
	public function hooks() {
		add_filter( 'preprocess_comment', array( $this, 'prepare' ) );
		add_filter( 'pre_comment_approved', array( $this, 'approval' ), 10, 2 );
		add_filter( 'comments_open', array( $this, 'comments_open' ), 10, 2 );
		add_action( 'comment_post', array( $this, 'after_comment' ), 10, 3 );
	}

	public function comments_open( $open, $post_id ) {
		if ( SNP_Content::TYPE !== get_post_type( $post_id ) ) {
			return $open;
		}
		return SNP_Publication_State::public_eligible( $post_id );
	}

	public function prepare( $data ) {
		$post = ! empty( $data['comment_post_ID'] ) ? get_post( absint( $data['comment_post_ID'] ) ) : false;
		if ( ! $post || SNP_Content::TYPE !== $post->post_type ) {
			return $data;
		}
		if ( ! SNP_Publication_State::public_eligible( $post->ID ) ) {
			wp_die( esc_html__( 'Comments are unavailable for this publication.', 'sabri-news-publishing' ), '', array( 'response' => 403 ) );
		}
		if ( ! is_user_logged_in() || ! SNP_Membership_Adapter::is_active( get_current_user_id() ) ) {
			wp_die( esc_html__( 'An approved account is required before commenting.', 'sabri-news-publishing' ), '', array( 'response' => 403, 'back_link' => true ) );
		}
		if ( ! SNP_Rate_Limiter::allowed( 'comment', get_current_user_id(), 5, 10 * MINUTE_IN_SECONDS ) ) {
			wp_die( esc_html__( 'Please wait before posting another comment.', 'sabri-news-publishing' ), '', array( 'response' => 429, 'back_link' => true ) );
		}
		$data['comment_author_IP']  = '';
		$data['comment_author_url'] = '';
		if ( ! empty( $data['comment_parent'] ) ) {
			$parent = get_comment( absint( $data['comment_parent'] ) );
			if ( $parent && $parent->comment_parent ) {
				$data['comment_parent'] = absint( $parent->comment_parent );
			}
		}
		return $data;
	}

	public function approval( $approved, $data ) {
		$post = ! empty( $data['comment_post_ID'] ) ? get_post( absint( $data['comment_post_ID'] ) ) : false;
		if ( ! $post || SNP_Content::TYPE !== $post->post_type ) {
			return $approved;
		}
		$content = isset( $data['comment_content'] ) ? (string) $data['comment_content'] : '';
		if ( $this->contains_sensitive_data( $content ) ) {
			return 0;
		}
		$user_id = ! empty( $data['user_ID'] ) ? absint( $data['user_ID'] ) : get_current_user_id();
		return SNP_Permissions::is_founder( $user_id ) || SNP_Permissions::can_moderate( $user_id ) ? 1 : 0;
	}

	public function after_comment( $comment_id, $approved, $data ) {
		$post_id = ! empty( $data['comment_post_ID'] ) ? absint( $data['comment_post_ID'] ) : 0;
		if ( SNP_Content::TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		if ( $this->contains_sensitive_data( isset( $data['comment_content'] ) ? (string) $data['comment_content'] : '' ) ) {
			update_comment_meta( $comment_id, '_snp_sensitive_review', '1' );
			SNP_Audit::record( $post_id, 'comment_sensitive_review', 'Comment held because it may contain contact, identity, or medical-record details.', array( 'comment_id' => absint( $comment_id ) ) );
		}
		if ( $approved ) {
			SNP_Interactions::recalculate( $post_id );
		}
	}

	private function contains_sensitive_data( $content ) {
		$content = (string) $content;
		return (bool) preg_match( '/(?:[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}|\+?\d[\d\s().-]{7,}\d|\b(?:CNIC|passport|identity|medical record|patient id)\b)/i', $content );
	}
}
