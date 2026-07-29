<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Comments {
	public function hooks() {
		add_filter( 'option_comment_registration', '__return_true' );
		add_filter( 'preprocess_comment', array( $this, 'prepare' ) );
		add_filter( 'pre_comment_approved', array( $this, 'approval' ), 10, 2 );
		add_action( 'comment_post', array( $this, 'recalculate' ) );
	}

	public function prepare( $data ) {
		$post = ! empty( $data['comment_post_ID'] ) ? get_post( absint( $data['comment_post_ID'] ) ) : false;
		if ( ! $post || SNP_Content::TYPE !== $post->post_type ) {
			return $data;
		}
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Please log in before commenting.', 'sabri-news-publishing' ), '', array( 'response' => 403, 'back_link' => true ) );
		}
		$key   = 'snp_comments_' . get_current_user_id();
		$count = absint( get_transient( $key ) );
		if ( $count >= 5 ) {
			wp_die( esc_html__( 'Please wait before posting another comment.', 'sabri-news-publishing' ), '', array( 'response' => 429, 'back_link' => true ) );
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
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
		$user_id = ! empty( $data['user_ID'] ) ? absint( $data['user_ID'] ) : get_current_user_id();
		return SNP_Permissions::is_founder( $user_id ) || user_can( $user_id, 'manage_sabri_news' ) ? 1 : 0;
	}

	public function recalculate( $comment_id ) {
		$comment = get_comment( $comment_id );
		if ( $comment && SNP_Content::TYPE === get_post_type( $comment->comment_post_ID ) ) {
			SNP_Interactions::recalculate( $comment->comment_post_ID );
		}
	}
}
