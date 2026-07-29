<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Publishing {
	public function hooks() {
		add_shortcode( 'sabri_publish_form', array( $this, 'form' ) );
		add_action( 'admin_post_snp_submit_publication', array( $this, 'submit' ) );
	}

	public function form() {
		if ( ! is_user_logged_in() ) {
			return $this->notice( 'An approved account is required to submit a publication.', wp_login_url( get_permalink() ), 'Log In' );
		}
		if ( ! SNP_Permissions::can_submit() ) {
			return '<div class="snp-notice"><strong>Publishing is unavailable.</strong><p>File 00 membership, File 03 profile eligibility, and File 09 doctor verification must all be active and approved.</p></div>';
		}
		$message = isset( $_GET['snp_message'] ) ? sanitize_key( wp_unslash( $_GET['snp_message'] ) ) : '';
		ob_start();
		?>
		<main class="snp-shell" aria-labelledby="snp-publish-title">
			<header class="snp-page-header"><span class="snp-eyebrow">Publishing Service</span><h1 id="snp-publish-title">Create Publication</h1><p>Every candidate is assembled privately before editorial publication. Patient Cases always require independent privacy review.</p></header>
			<?php if ( $message ) : ?><div class="snp-notice snp-notice--success"><?php echo esc_html( 'published' === $message ? 'The publication passed all gates and is now live.' : 'The publication was saved privately and entered review.' ); ?></div><?php endif; ?>
			<form class="snp-publish-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
				<input type="hidden" name="action" value="snp_submit_publication"><?php wp_nonce_field( 'snp_submit_publication', 'snp_nonce' ); ?>
				<label>Publication title <input type="text" name="post_title" maxlength="180" required></label>
				<label>Approved topic <select name="topic" required><option value="">Select one topic</option><?php foreach ( SNP_Content::topics() as $slug => $name ) : if ( in_array( $slug, array( 'founder-update', 'platform-news' ), true ) && ! SNP_Permissions::is_founder() ) { continue; } ?><option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select></label>
				<label class="snp-field-full">Short introduction <textarea name="post_excerpt" rows="3" maxlength="500" required></textarea></label>
				<label class="snp-field-full">Complete publication <textarea name="post_content" rows="14" required></textarea><small>Do not include a personalized prescription, guaranteed cure, emergency-care delay, or identifying patient information.</small></label>
				<label>Featured image <input type="file" name="featured_image" accept="image/jpeg,image/png,image/webp"><small>Encrypted while pending; maximum 5 MB and 24 megapixels.</small></label>
				<label>Additional image <input type="file" name="additional_image" accept="image/jpeg,image/png,image/webp"><small>Encrypted while pending; maximum 5 MB and 24 megapixels.</small></label>
				<label>External video link <input type="url" name="video_url" placeholder="https://www.youtube.com/..."></label>
				<label>Keywords <input type="text" name="tags" maxlength="240"></label>
				<label>Language <input type="text" value="English (United States)" readonly><input type="hidden" name="language" value="en-US"></label>
				<label>References <textarea name="references" rows="4" placeholder="One source or reference per line"></textarea></label>
				<div class="snp-field-full snp-case-consent" hidden data-snp-case-consent>
					<strong>Protected Patient Case record</strong>
					<label>Consent record ID <input type="text" name="case_consent_record_id" maxlength="190"></label>
					<label>Consent form/version <input type="text" name="case_consent_version" maxlength="80"></label>
					<label>Consent obtained on <input type="date" name="case_consent_obtained_at"></label>
					<label>Permitted scope <textarea name="case_consent_scope" rows="3" maxlength="1000"></textarea></label>
					<label>Redaction summary <textarea name="case_redaction_summary" rows="3" maxlength="1000"></textarea></label>
					<label class="snp-check"><input type="checkbox" name="case_anonymized" value="1"> I have removed names, phone numbers, email addresses, exact addresses, identity numbers, and other identifying details.</label>
					<label class="snp-check"><input type="checkbox" name="case_consent" value="1"> I confirm that the consent record is valid for the submitted text.</label>
					<label class="snp-check"><input type="checkbox" name="case_image_consent" value="1"> The consent record specifically permits every submitted patient image.</label>
					<p><strong>Patient Cases never publish immediately.</strong> A separate authorized privacy reviewer must approve the evidence and redaction record.</p>
				</div>
				<label class="snp-check snp-field-full"><input type="checkbox" name="medical_notice" value="1" required> I confirm that this is educational content and does not replace emergency or qualified clinical care.</label>
				<button class="snp-button" type="submit"><?php echo esc_html( SNP_Permissions::can_instant_publish( get_current_user_id(), '' ) ? 'Submit Securely' : 'Submit for Review' ); ?></button>
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
		if ( ! SNP_Rate_Limiter::allowed( 'publication_submit', get_current_user_id(), 6, HOUR_IN_SECONDS ) ) {
			wp_die( esc_html__( 'The publication submission limit has been reached. Please try again later.', 'sabri-news-publishing' ), '', array( 'response' => 429 ) );
		}

		$title      = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
		$excerpt    = isset( $_POST['post_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['post_excerpt'] ) ) : '';
		$content    = isset( $_POST['post_content'] ) ? $this->sanitize_content( wp_unslash( $_POST['post_content'] ) ) : '';
		$topic      = isset( $_POST['topic'] ) ? sanitize_title( wp_unslash( $_POST['topic'] ) ) : '';
		$references = isset( $_POST['references'] ) ? sanitize_textarea_field( wp_unslash( $_POST['references'] ) ) : '';
		$title      = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 180 ) : substr( $title, 0, 180 );
		$excerpt    = function_exists( 'mb_substr' ) ? mb_substr( $excerpt, 0, 500 ) : substr( $excerpt, 0, 500 );

		if ( '' === $title || '' === trim( wp_strip_all_tags( $content ) ) || ! SNP_Content::topic_allowed( $topic ) || empty( $_POST['medical_notice'] ) ) {
			$this->fail( 'Complete the title, content, approved topic, and medical confirmation.' );
		}
		if ( in_array( $topic, array( 'founder-update', 'platform-news' ), true ) && ! SNP_Permissions::is_founder() ) {
			$this->fail( 'That topic is reserved for the canonical Founder account.' );
		}
		$video = isset( $_POST['video_url'] ) ? esc_url_raw( wp_unslash( $_POST['video_url'] ) ) : '';
		if ( $video && ! $this->allowed_video( $video ) ) {
			$this->fail( 'Only valid YouTube or Vimeo links are accepted.' );
		}

		$case = $this->patient_case_data( $topic );
		if ( is_wp_error( $case ) ) {
			$this->fail( $case->get_error_message() );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'      => SNP_Content::TYPE,
				'post_status'    => 'draft',
				'post_author'    => get_current_user_id(),
				'post_title'     => $title,
				'post_excerpt'   => $excerpt,
				'post_content'   => $content,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			$this->fail( 'The private publication candidate could not be created.' );
		}

		try {
			SNP_Content::seed_topics();
			$term = get_term_by( 'slug', $topic, SNP_Content::TAX );
			if ( ! $term || is_wp_error( wp_set_object_terms( $post_id, array( (int) $term->term_id ), SNP_Content::TAX, false ) ) ) {
				throw new RuntimeException( 'The approved topic could not be assigned.' );
			}
			update_post_meta( $post_id, '_snp_video_url', $video );
			update_post_meta( $post_id, '_snp_tags', isset( $_POST['tags'] ) ? sanitize_text_field( wp_unslash( $_POST['tags'] ) ) : '' );
			update_post_meta( $post_id, '_snp_language', 'en-US' );
			update_post_meta( $post_id, '_snp_references', $references );
			update_post_meta( $post_id, '_snp_medical_notice', '1' );
			update_post_meta( $post_id, '_snp_featured', '0' );
			update_post_meta( $post_id, '_snp_pinned', '0' );
			foreach ( $case as $key => $value ) {
				update_post_meta( $post_id, '_snp_' . $key, $value );
			}
			$consent_id = isset( $case['case_consent_record_id'] ) ? $case['case_consent_record_id'] : '';
			foreach ( array( 'featured_image' => 'featured', 'additional_image' => 'additional' ) as $field => $purpose ) {
				$staged = SNP_Media::stage_request( $field, $post_id, $purpose, $consent_id );
				if ( is_wp_error( $staged ) ) {
					throw new RuntimeException( $staged->get_error_message() );
				}
			}
			if ( 'patient-cases' === $topic && ( ! empty( $_FILES['featured_image']['name'] ) || ! empty( $_FILES['additional_image']['name'] ) ) && empty( $_POST['case_image_consent'] ) ) {
				throw new RuntimeException( 'Every Patient Case image requires image-specific consent.' );
			}
			$state = 'patient-cases' === $topic ? 'privacy_review' : 'pending';
			SNP_Publication_State::set_state( $post_id, $state );
			SNP_Audit::record( $post_id, 'submitted', '', array( 'state' => $state, 'topic' => $topic ) );

			$published = false;
			if ( SNP_Permissions::can_instant_publish( get_current_user_id(), $topic ) ) {
				$result = SNP_Publication_State::approve( $post_id, 'Canonical Founder immediate publication after private assembly.', get_current_user_id() );
				if ( is_wp_error( $result ) ) {
					throw new RuntimeException( $result->get_error_message() );
				}
				$published = true;
			}
			do_action( 'snp_publication_submitted', $post_id, $state );
			SNP_Notification_Adapter::emit( 'publication_submitted', SNP_Notification_Adapter::editorial_recipients(), array( 'post_id' => $post_id, 'state' => $state, 'author_id' => get_current_user_id() ) );
			$this->redirect( $published ? 'published' : 'submitted' );
		} catch ( Throwable $error ) {
			SNP_Media::delete_post_media( $post_id );
			wp_delete_post( $post_id, true );
			$this->fail( $error->getMessage() );
		}
	}


	private function sanitize_content( $content ) {
		$allowed = array(
			'p'          => array(),
			'br'         => array(),
			'strong'     => array(),
			'b'          => array(),
			'em'         => array(),
			'i'          => array(),
			'u'          => array(),
			'ul'         => array(),
			'ol'         => array(),
			'li'         => array(),
			'h2'         => array(),
			'h3'         => array(),
			'h4'         => array(),
			'blockquote' => array(),
			'a'          => array( 'href' => true, 'title' => true, 'rel' => true ),
		);
		return wp_kses( (string) $content, $allowed );
	}

	private function patient_case_data( $topic ) {
		if ( 'patient-cases' !== $topic ) {
			return array();
		}
		$record    = isset( $_POST['case_consent_record_id'] ) ? sanitize_text_field( wp_unslash( $_POST['case_consent_record_id'] ) ) : '';
		$version   = isset( $_POST['case_consent_version'] ) ? sanitize_text_field( wp_unslash( $_POST['case_consent_version'] ) ) : '';
		$obtained  = isset( $_POST['case_consent_obtained_at'] ) ? sanitize_text_field( wp_unslash( $_POST['case_consent_obtained_at'] ) ) : '';
		$scope     = isset( $_POST['case_consent_scope'] ) ? sanitize_textarea_field( wp_unslash( $_POST['case_consent_scope'] ) ) : '';
		$redaction = isset( $_POST['case_redaction_summary'] ) ? sanitize_textarea_field( wp_unslash( $_POST['case_redaction_summary'] ) ) : '';
		if ( ! $record || ! $version || ! SNP_Publication_State::valid_consent_date( $obtained ) || ! $scope || ! $redaction || empty( $_POST['case_anonymized'] ) || empty( $_POST['case_consent'] ) ) {
			return new WP_Error( 'snp_case', 'Patient Cases require a consent record ID, version, date, permitted scope, redaction summary, anonymization confirmation, and consent confirmation.' );
		}
		return array(
			'case_consent_record_id'  => $record,
			'case_consent_version'    => $version,
			'case_consent_obtained_at'=> $obtained,
			'case_consent_scope'      => $scope,
			'case_redaction_summary'  => $redaction,
			'case_image_consent'      => empty( $_POST['case_image_consent'] ) ? '0' : '1',
			'case_consent_withdrawn'  => '0',
		);
	}

	private function allowed_video( $url ) {
		if ( ! wp_http_validate_url( $url ) ) {
			return false;
		}
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		return in_array( $host, array( 'youtube.com', 'www.youtube.com', 'youtu.be', 'vimeo.com', 'www.vimeo.com' ), true );
	}

	private function redirect( $message ) {
		$pages = (array) get_option( 'snp_page_map', array() );
		$url   = ! empty( $pages['publish'] ) ? get_permalink( absint( $pages['publish'] ) ) : home_url( '/' );
		wp_safe_redirect( add_query_arg( 'snp_message', sanitize_key( $message ), $url ) );
		exit;
	}

	private function fail( $message ) {
		wp_die( esc_html( $message ), esc_html__( 'Publication not accepted', 'sabri-news-publishing' ), array( 'response' => 400, 'back_link' => true ) );
	}

	private function notice( $message, $url, $label ) {
		return '<div class="snp-notice"><p>' . esc_html( $message ) . '</p><a class="snp-button" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></div>';
	}
}
