<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Publication_State {
	const STATE_META    = '_snp_editorial_state';
	const SNAPSHOT_META = '_snp_approved_snapshot';
	const SANCTION_META = '_snp_publication_sanctioned';
	private static $transitioning = false;

	public static function states() {
		return array( 'draft', 'pending', 'privacy_review', 'approved', 'rejected', 'hidden', 'withdrawn' );
	}

	public static function state( $post_id ) {
		$state = sanitize_key( (string) get_post_meta( absint( $post_id ), self::STATE_META, true ) );
		return in_array( $state, self::states(), true ) ? $state : 'draft';
	}

	public static function set_state( $post_id, $state ) {
		$state = sanitize_key( $state );
		if ( ! in_array( $state, self::states(), true ) ) {
			return false;
		}
		return (bool) update_post_meta( absint( $post_id ), self::STATE_META, $state );
	}

	public static function material_data( $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post || SNP_Content::TYPE !== $post->post_type ) {
			return array();
		}
		return array(
			'post_author'          => absint( $post->post_author ),
			'post_title'           => (string) $post->post_title,
			'post_excerpt'         => (string) $post->post_excerpt,
			'post_content'         => (string) $post->post_content,
			'topic'                => SNP_Content::topic_slug( $post_id ),
			'video_url'            => (string) get_post_meta( $post_id, '_snp_video_url', true ),
			'references'           => (string) get_post_meta( $post_id, '_snp_references', true ),
			'tags'                 => (string) get_post_meta( $post_id, '_snp_tags', true ),
			'language'             => (string) get_post_meta( $post_id, '_snp_language', true ),
			'medical_notice'       => (string) get_post_meta( $post_id, '_snp_medical_notice', true ),
			'featured_image_id'    => get_post_thumbnail_id( $post_id ),
			'additional_image_id'  => absint( get_post_meta( $post_id, '_snp_additional_image_id', true ) ),
			'case_consent_record'  => (string) get_post_meta( $post_id, '_snp_case_consent_record_id', true ),
			'case_consent_version' => (string) get_post_meta( $post_id, '_snp_case_consent_version', true ),
			'case_consent_scope'   => (string) get_post_meta( $post_id, '_snp_case_consent_scope', true ),
			'case_consent_date'    => (string) get_post_meta( $post_id, '_snp_case_consent_obtained_at', true ),
			'case_image_consent'   => (string) get_post_meta( $post_id, '_snp_case_image_consent', true ),
			'case_redaction'       => (string) get_post_meta( $post_id, '_snp_case_redaction_summary', true ),
			'case_privacy_at'      => (string) get_post_meta( $post_id, '_snp_case_privacy_reviewed_at', true ),
			'case_privacy_reviewer'=> absint( get_post_meta( $post_id, '_snp_case_privacy_reviewer_id', true ) ),
			'case_withdrawn'       => (string) get_post_meta( $post_id, '_snp_case_consent_withdrawn', true ),
		);
	}

	public static function fingerprint( array $data ) {
		ksort( $data );
		return hash( 'sha256', wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	public static function snapshot( $post_id ) {
		$value = get_post_meta( absint( $post_id ), self::SNAPSHOT_META, true );
		return is_array( $value ) ? $value : array();
	}

	public static function snapshot_matches( $post_id ) {
		$snapshot = self::snapshot( $post_id );
		return ! empty( $snapshot['fingerprint'] )
			&& hash_equals( (string) $snapshot['fingerprint'], self::fingerprint( self::material_data( $post_id ) ) );
	}


	public static function valid_consent_date( $date ) {
		$date = (string) $date;
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}
		$timezone = new DateTimeZone( 'UTC' );
		$value    = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $timezone );
		$errors   = DateTimeImmutable::getLastErrors();
		if ( ! $value || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) || $value->format( 'Y-m-d' ) !== $date ) {
			return false;
		}
		return $value->setTime( 23, 59, 59 )->getTimestamp() <= time();
	}

	public static function patient_case_ready( $post_id ) {
		if ( 'patient-cases' !== SNP_Content::topic_slug( $post_id ) ) {
			return true;
		}
		$date = (string) get_post_meta( $post_id, '_snp_case_consent_obtained_at', true );
		global $wpdb;
		$staged_media = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}snp_media_staging WHERE post_id=%d", absint( $post_id ) ) ) );
		$has_media = get_post_thumbnail_id( $post_id ) || absint( get_post_meta( $post_id, '_snp_additional_image_id', true ) ) || $staged_media > 0;
		return '' !== (string) get_post_meta( $post_id, '_snp_case_consent_record_id', true )
			&& '' !== (string) get_post_meta( $post_id, '_snp_case_consent_version', true )
			&& self::valid_consent_date( $date )
			&& '' !== (string) get_post_meta( $post_id, '_snp_case_consent_scope', true )
			&& '' !== (string) get_post_meta( $post_id, '_snp_case_redaction_summary', true )
			&& absint( get_post_meta( $post_id, '_snp_case_privacy_reviewer_id', true ) ) > 0
			&& '' !== (string) get_post_meta( $post_id, '_snp_case_privacy_reviewed_at', true )
			&& ( ! $has_media || '1' === (string) get_post_meta( $post_id, '_snp_case_image_consent', true ) )
			&& '1' !== (string) get_post_meta( $post_id, '_snp_case_consent_withdrawn', true );
	}

	public static function public_eligible( $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post || SNP_Content::TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return false;
		}
		return 'approved' === self::state( $post_id )
			&& '1' !== (string) get_post_meta( $post_id, self::SANCTION_META, true )
			&& SNP_Content::topic_allowed( SNP_Content::topic_slug( $post_id ) )
			&& SNP_Permissions::author_eligible( $post->post_author )
			&& self::patient_case_ready( $post_id )
			&& self::snapshot_matches( $post_id );
	}

	public static function approve( $post_id, $note, $reviewer_id = 0 ) {
		$post_id     = absint( $post_id );
		$reviewer_id = $reviewer_id ? absint( $reviewer_id ) : get_current_user_id();
		$post        = get_post( $post_id );
		if ( ! $post || SNP_Content::TYPE !== $post->post_type ) {
			return new WP_Error( 'snp_approval', 'The publication cannot be approved.' );
		}
		$topic = SNP_Content::topic_slug( $post_id );
		$authorized = SNP_Permissions::can_moderate( $reviewer_id )
			|| ( $reviewer_id === (int) $post->post_author && SNP_Permissions::can_instant_publish( $reviewer_id, $topic ) );
		if ( ! $authorized ) {
			return new WP_Error( 'snp_approval_permission', 'The publication reviewer is not authorized.' );
		}
		if ( $reviewer_id === (int) $post->post_author && ! SNP_Permissions::can_instant_publish( $reviewer_id, $topic ) ) {
			return new WP_Error( 'snp_independent_review', 'Authors cannot approve their own reviewed publications.' );
		}
		if ( ! SNP_Permissions::author_eligible( $post->post_author ) || ! SNP_Content::topic_allowed( SNP_Content::topic_slug( $post_id ) ) || ! self::patient_case_ready( $post_id ) ) {
			return new WP_Error( 'snp_approval_gate', 'The publication does not satisfy current author, topic, or privacy requirements.' );
		}
		$media = SNP_Media::materialize_for_publish( $post_id );
		if ( is_wp_error( $media ) ) {
			return $media;
		}
		$data = self::material_data( $post_id );
		update_post_meta(
			$post_id,
			self::SNAPSHOT_META,
			array(
				'schema'      => 2,
				'fields'      => $data,
				'fingerprint' => self::fingerprint( $data ),
				'reviewer_id' => $reviewer_id,
				'reviewed_at' => current_time( 'mysql', true ),
			)
		);
		self::$transitioning = true;
		self::set_state( $post_id, 'approved' );
		$published = wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish', 'comment_status' => 'open' ), true );
		self::$transitioning = false;
		if ( is_wp_error( $published ) || ! $published ) {
			delete_post_meta( $post_id, self::SNAPSHOT_META );
			self::set_state( $post_id, 'pending' );
			SNP_Media::delete_post_media( $post_id );
			return is_wp_error( $published ) ? $published : new WP_Error( 'snp_publish_transition', 'The final publish transition failed.' );
		}
		SNP_Audit::record( $post_id, 'approved', $note, array( 'reviewer_id' => $reviewer_id ) );
		SNP_Interactions::recalculate( $post_id );
		do_action( 'snp_publication_approved', $post_id, $reviewer_id );
		SNP_Notification_Adapter::emit( 'publication_approved', array( absint( $post->post_author ) ), array( 'post_id' => $post_id, 'reviewer_id' => $reviewer_id ) );
		return true;
	}

	public static function enforce_after_save( $post_id, $post, $update ) {
		unset( $update );
		if ( self::$transitioning || ! $post instanceof WP_Post || SNP_Content::TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}
		if ( ! self::public_eligible( $post_id ) ) {
			self::$transitioning = true;
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
			self::set_state( $post_id, 'pending' );
			self::$transitioning = false;
			if ( 'patient-cases' === SNP_Content::topic_slug( $post_id ) ) {
				SNP_Media::delete_post_media( $post_id );
			}
			SNP_Audit::record( $post_id, 'approval_invalidated', 'Published material or author eligibility changed; re-review is required.' );
		}
	}

	public static function guard_single() {
		if ( ! is_singular( SNP_Content::TYPE ) ) {
			return;
		}
		$post_id = get_queried_object_id();
		if ( self::public_eligible( $post_id ) || SNP_Permissions::can_moderate() ) {
			if ( ! self::public_eligible( $post_id ) ) {
				nocache_headers();
				header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
			}
			return;
		}
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		include get_404_template();
		exit;
	}
}
