<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Publishing {
	public function hooks() {
		add_shortcode( 'sabri_publish_form', array( $this, 'form' ) );
		add_action( 'admin_post_snp_submit_publication', array( $this, 'submit' ) );
	}

	public function form() {
		if ( ! is_user_logged_in() ) {
			return $this->notice( 'An account is required to publish.', wp_login_url( get_permalink() ), 'Log In' );
		}
		if ( ! SNP_Permissions::can_submit() ) {
			return '<div class="snp-notice"><strong>Publishing is restricted.</strong><p>Only the Founder, administrators and verified doctors may submit publications.</p></div>';
		}
		$message = isset( $_GET['snp_message'] ) ? sanitize_key( wp_unslash( $_GET['snp_message'] ) ) : '';
		ob_start();
		?>
		<main class="snp-shell" aria-labelledby="snp-publish-title">
			<header class="snp-page-header"><span class="snp-eyebrow">News & Publishing</span><h1 id="snp-publish-title">Create Publication</h1><p>Publications are permitted only within the approved educational topics listed below. Content must use American English.</p></header>
			<?php if ( $message ) : ?><div class="snp-notice snp-notice--success"><?php echo 'published' === $message ? 'Your publication is now live.' : 'Your publication was submitted for administrator review.'; ?></div><?php endif; ?>
			<form class="snp-publish-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
				<input type="hidden" name="action" value="snp_submit_publication"><?php wp_nonce_field( 'snp_submit_publication', 'snp_nonce' ); ?>
				<label>Publication title <input type="text" name="post_title" maxlength="180" required></label>
				<label>Approved topic <select name="topic" required><option value="">Select one topic</option><?php foreach ( SNP_Content::topics() as $slug => $name ) : if ( in_array( $slug, array( 'founder-update', 'platform-news' ), true ) && ! SNP_Permissions::is_founder() && ! current_user_can( 'manage_sabri_news' ) ) { continue; } ?><option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select></label>
				<label class="snp-field-full">Short introduction <textarea name="post_excerpt" rows="3" maxlength="500" required></textarea></label>
				<label class="snp-field-full">Complete publication <textarea name="post_content" rows="14" required></textarea><small>Do not include prescriptions for a specific reader, guaranteed cures, emergency-care delays or misleading medical claims.</small></label>
				<label>Featured image <input type="file" name="featured_image" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG or WebP; maximum 5 MB.</small></label>
				<label>Additional image <input type="file" name="additional_image" accept="image/jpeg,image/png,image/webp"><small>Optional; maximum 5 MB.</small></label>
				<label>External video link <input type="url" name="video_url" placeholder="https://www.youtube.com/..."><small>Optional YouTube or Vimeo link.</small></label>
				<label>Keywords <input type="text" name="tags" maxlength="240" placeholder="remedy, philosophy, research"></label>
				<label>Language <input type="text" value="English (United States)" readonly><input type="hidden" name="language" value="en-US"></label>
				<label>References <textarea name="references" rows="4" placeholder="One source or reference per line"></textarea></label>
				<div class="snp-field-full snp-case-consent" hidden data-snp-case-consent>
					<strong>Patient Case safeguards</strong>
					<label class="snp-check"><input type="checkbox" name="case_anonymized" value="1"> I have removed the patient’s name, phone, email, exact address, identity numbers and other identifying details.</label>
					<label class="snp-check"><input type="checkbox" name="case_consent" value="1"> I confirm that appropriate permission exists for any case information or image that could identify the patient.</label>
				</div>
				<label class="snp-check snp-field-full"><input type="checkbox" name="medical_notice" value="1" required> I understand that this publication is educational, must not promise a cure and must not replace emergency or qualified medical care.</label>
				<button class="snp-button" type="submit"><?php echo 'publish' === SNP_Permissions::initial_status() ? 'Publish Now' : 'Submit for Review'; ?></button>
			</form>
		</main>
		<?php
		return ob_get_clean();
	}

	public function submit() {
		if ( ! is_user_logged_in() || ! SNP_Permissions::can_submit() ) {
			wp_die( esc_html__( 'You are not allowed to submit publications.', 'sabri-news-publishing' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'snp_submit_publication', 'snp_nonce' );
		$title   = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
		$excerpt = isset( $_POST['post_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['post_excerpt'] ) ) : '';
		$content = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : '';
		$topic   = isset( $_POST['topic'] ) ? sanitize_title( wp_unslash( $_POST['topic'] ) ) : '';
		$title   = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 180 ) : substr( $title, 0, 180 );
		$excerpt = function_exists( 'mb_substr' ) ? mb_substr( $excerpt, 0, 500 ) : substr( $excerpt, 0, 500 );
		if ( '' === $title || '' === trim( wp_strip_all_tags( $content ) ) || ! SNP_Content::topic_allowed( $topic ) || empty( $_POST['medical_notice'] ) ) {
			$this->fail( 'Please complete the title, content, approved topic and medical confirmation.' );
		}
		if ( in_array( $topic, array( 'founder-update', 'platform-news' ), true ) && ! SNP_Permissions::is_founder() && ! current_user_can( 'manage_sabri_news' ) ) {
			$this->fail( 'That topic is reserved for official platform publishing.' );
		}
		if ( 'patient-cases' === $topic && ( empty( $_POST['case_anonymized'] ) || empty( $_POST['case_consent'] ) ) ) {
			$this->fail( 'Patient Cases require anonymization and consent confirmations.' );
		}
		foreach ( array( 'featured_image', 'additional_image' ) as $field ) {
			$error = $this->validate_image( $field );
			if ( $error ) {
				$this->fail( $error );
			}
		}
		$video = isset( $_POST['video_url'] ) ? esc_url_raw( wp_unslash( $_POST['video_url'] ) ) : '';
		if ( $video && ! $this->allowed_video( $video ) ) {
			$this->fail( 'Only valid YouTube or Vimeo links are accepted in this release.' );
		}
		$status = SNP_Permissions::initial_status();
		$post_id = wp_insert_post(
			array(
				'post_type' => SNP_Content::TYPE,
				'post_status' => $status,
				'post_author' => get_current_user_id(),
				'post_title' => $title,
				'post_excerpt' => $excerpt,
				'post_content' => $content,
				'comment_status' => 'open',
				'ping_status' => 'closed',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			$this->fail( 'The publication could not be saved.' );
		}
		SNP_Content::seed_topics();
		$topic_term = get_term_by( 'slug', $topic, SNP_Content::TAX );
		if ( ! $topic_term || is_wp_error( wp_set_object_terms( $post_id, array( (int) $topic_term->term_id ), SNP_Content::TAX, false ) ) ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
			$this->fail( 'The approved topic could not be assigned. The publication was kept as a private draft.' );
		}
		update_post_meta( $post_id, '_snp_video_url', $video );
		$tags = isset( $_POST['tags'] ) ? sanitize_text_field( wp_unslash( $_POST['tags'] ) ) : '';
		$tags = function_exists( 'mb_substr' ) ? mb_substr( $tags, 0, 240 ) : substr( $tags, 0, 240 );
		update_post_meta( $post_id, '_snp_tags', $tags );
		update_post_meta( $post_id, '_snp_language', 'en-US' );
		update_post_meta( $post_id, '_snp_featured', '0' );
		update_post_meta( $post_id, '_snp_pinned', '0' );
		update_post_meta( $post_id, '_snp_views', '0' );
		update_post_meta( $post_id, '_snp_references', isset( $_POST['references'] ) ? sanitize_textarea_field( wp_unslash( $_POST['references'] ) ) : '' );
		update_post_meta( $post_id, '_snp_medical_notice', '1' );
		if ( 'patient-cases' === $topic ) {
			update_post_meta( $post_id, '_snp_case_anonymized', '1' );
			update_post_meta( $post_id, '_snp_case_consent', '1' );
		}
		$featured = $this->upload( 'featured_image', $post_id );
		if ( $featured ) {
			set_post_thumbnail( $post_id, $featured );
		}
		$additional = $this->upload( 'additional_image', $post_id );
		if ( $additional ) {
			update_post_meta( $post_id, '_snp_additional_image_id', $additional );
		}
		SNP_Admin::audit( $post_id, 'publish' === $status ? 'published' : 'submitted', '' );
		SNP_Interactions::recalculate( $post_id );
		$pages = (array) get_option( 'snp_page_map', array() );
		$url   = ! empty( $pages['publish'] ) ? get_permalink( $pages['publish'] ) : home_url( '/' );
		wp_safe_redirect( add_query_arg( 'snp_message', 'publish' === $status ? 'published' : 'submitted', $url ) );
		exit;
	}

	private function validate_image( $field ) {
		if ( empty( $_FILES[ $field ]['name'] ) ) {
			return '';
		}
		$file = $_FILES[ $field ];
		if ( ! empty( $file['error'] ) ) {
			return 'An image upload could not be read.';
		}
		if ( (int) $file['size'] > 5 * MB_IN_BYTES ) {
			return 'Each image must be 5 MB or smaller.';
		}
		$type = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) );
		return empty( $type['type'] ) ? 'Only JPG, PNG and WebP images are allowed.' : '';
	}

	private function upload( $field, $post_id ) {
		if ( empty( $_FILES[ $field ]['name'] ) ) {
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_handle_upload( $field, $post_id, array(), array( 'test_form' => false, 'mimes' => array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) ) );
		return is_wp_error( $id ) ? 0 : absint( $id );
	}

	private function allowed_video( $url ) {
		if ( ! wp_http_validate_url( $url ) ) {
			return false;
		}
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		return in_array( $host, array( 'youtube.com', 'www.youtube.com', 'youtu.be', 'vimeo.com', 'www.vimeo.com' ), true );
	}

	private function fail( $message ) {
		wp_die( esc_html( $message ), esc_html__( 'Publication not accepted', 'sabri-news-publishing' ), array( 'response' => 400, 'back_link' => true ) );
	}

	private function notice( $message, $url, $label ) {
		return '<div class="snp-notice"><p>' . esc_html( $message ) . '</p><a class="snp-button" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></div>';
	}
}
