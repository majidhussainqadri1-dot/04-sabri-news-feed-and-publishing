<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Permissions {
	public static function founder_id() {
		return absint( get_option( 'spf_founder_user_id', 0 ) );
	}

	public static function is_founder( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return $user_id && $user_id === self::founder_id();
	}

	public static function is_verified_doctor( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		if ( class_exists( 'SPD_Helpers' ) && SPD_Helpers::is_doctor( $user_id ) ) {
			return 'verified' === SPD_Helpers::verification_status( $user_id );
		}
		$user = get_userdata( $user_id );
		return $user && in_array( 'sabri_doctor_verified', (array) $user->roles, true );
	}

	public static function can_submit( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return $user_id && ( user_can( $user_id, 'manage_sabri_news' ) || self::is_founder( $user_id ) || self::is_verified_doctor( $user_id ) );
	}

	public static function initial_status( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return user_can( $user_id, 'manage_sabri_news' ) || self::is_founder( $user_id ) ? 'publish' : 'pending';
	}

	public static function author_label( $user_id ) {
		if ( self::is_founder( $user_id ) ) {
			return 'Verified Founder';
		}
		if ( self::is_verified_doctor( $user_id ) ) {
			return 'Verified Doctor';
		}
		return 'Author';
	}

	public static function profile_url( $user_id ) {
		if ( class_exists( 'SPD_Helpers' ) ) {
			return SPD_Helpers::profile_url( $user_id );
		}
		return get_author_posts_url( absint( $user_id ) );
	}
}

