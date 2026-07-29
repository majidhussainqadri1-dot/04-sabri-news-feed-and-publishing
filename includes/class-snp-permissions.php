<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Permissions {
	const CAP_SUBMIT   = 'smc_submit_publications';
	const CAP_MODERATE = 'smc_moderate_publications';
	const CAP_PRIVACY  = 'smc_review_patient_case_privacy';
	const CAP_FEATURE  = 'smc_feature_publications';
	const CAP_REPORTS  = 'smc_view_publication_reports';
	const CAP_SENSITIVE_REPORTS = 'smc_view_sensitive_publication_reports';

	public static function dependencies_available() {
		return SNP_Membership_Adapter::available()
			&& SNP_Profile_Adapter::file03_available()
			&& SNP_Profile_Adapter::file09_available();
	}

	public static function is_founder( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return $user_id && SNP_Membership_Adapter::is_founder( $user_id ) && SNP_Membership_Adapter::is_active( $user_id );
	}

	public static function can_submit( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id || ! self::dependencies_available() || ! SNP_Profile_Adapter::author_eligible( $user_id ) ) {
			return false;
		}
		$allowed = self::is_founder( $user_id ) || SNP_Membership_Adapter::has_capability( $user_id, self::CAP_SUBMIT );
		return (bool) apply_filters( 'snp_can_submit_publication', $allowed, $user_id );
	}

	public static function can_moderate( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$allowed = $user_id && SNP_Membership_Adapter::has_capability( $user_id, self::CAP_MODERATE );
		return (bool) apply_filters( 'snp_can_moderate_publications', $allowed, $user_id );
	}

	public static function can_review_patient_privacy( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$allowed = $user_id && SNP_Membership_Adapter::has_capability( $user_id, self::CAP_PRIVACY );
		return (bool) apply_filters( 'snp_can_review_patient_privacy', $allowed, $user_id );
	}

	public static function can_feature( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return $user_id && SNP_Membership_Adapter::has_capability( $user_id, self::CAP_FEATURE );
	}

	public static function can_view_reports( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return $user_id && SNP_Membership_Adapter::has_capability( $user_id, self::CAP_REPORTS );
	}

	public static function can_view_sensitive_reports( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return $user_id && SNP_Membership_Adapter::has_capability( $user_id, self::CAP_SENSITIVE_REPORTS );
	}

	public static function can_instant_publish( $user_id = 0, $topic = '' ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( 'patient-cases' === sanitize_title( $topic ) ) {
			return false;
		}
		$allowed = self::is_founder( $user_id ) && self::can_submit( $user_id );
		return (bool) apply_filters( 'snp_can_instant_publish', $allowed, $user_id, sanitize_title( $topic ) );
	}

	public static function author_eligible( $user_id ) {
		return self::dependencies_available() && SNP_Profile_Adapter::author_eligible( absint( $user_id ) );
	}
}
