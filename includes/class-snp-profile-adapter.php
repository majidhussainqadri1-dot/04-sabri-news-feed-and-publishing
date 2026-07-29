<?php
defined( 'ABSPATH' ) || exit;

/**
 * Read-only profile and doctor-verification projection.
 */
final class SNP_Profile_Adapter {
	public static function file03_available() {
		return class_exists( 'SPD_Membership_Adapter', false )
			&& class_exists( 'SPD_Verification_Adapter', false );
	}

	public static function file09_available() {
		return defined( 'GDO_VERSION' ) || class_exists( 'GDO_Helpers', false );
	}

	public static function doctor_eligible( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || ! SNP_Membership_Adapter::available() || ! self::file03_available() || ! self::file09_available() ) {
			return false;
		}
		return SNP_Membership_Adapter::is_doctor( $user_id )
			&& SNP_Membership_Adapter::is_active( $user_id )
			&& SPD_Verification_Adapter::directory_eligible( $user_id );
	}

	public static function author_eligible( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || ! SNP_Membership_Adapter::is_active( $user_id ) ) {
			return false;
		}
		if ( SNP_Membership_Adapter::is_founder( $user_id ) ) {
			return true;
		}
		return self::doctor_eligible( $user_id );
	}

	public static function eligible_author_ids( $doctors_only = false ) {
		$ids = array();
		if ( ! $doctors_only ) {
			$founder_id = SNP_Membership_Adapter::founder_id();
			if ( $founder_id && self::author_eligible( $founder_id ) ) {
				$ids[] = $founder_id;
			}
		}
		$candidates = get_users(
			array(
				'fields'     => 'ids',
				'number'     => -1,
				'meta_key'   => '_smc_requested_role',
				'meta_value' => 'sabri_doctor',
			)
		);
		$candidates = apply_filters( 'snp_candidate_doctor_user_ids', $candidates );
		if ( is_array( $candidates ) ) {
			foreach ( array_unique( array_map( 'absint', $candidates ) ) as $user_id ) {
				if ( self::doctor_eligible( $user_id ) ) {
					$ids[] = $user_id;
				}
			}
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	public static function profile_url( $user_id ) {
		$user_id = absint( $user_id );
		$url     = get_author_posts_url( $user_id );
		if ( self::file03_available() && class_exists( 'SPD_Helpers', false ) && method_exists( 'SPD_Helpers', 'profile_url' ) ) {
			$url = SPD_Helpers::profile_url( $user_id );
		}
		return esc_url_raw( apply_filters( 'snp_author_profile_url', $url, $user_id ) );
	}

	public static function avatar_id( $user_id ) {
		$user_id = absint( $user_id );
		$id      = absint( get_user_meta( $user_id, '_spd_profile_photo_id', true ) );
		return absint( apply_filters( 'snp_author_avatar_id', $id, $user_id ) );
	}

	public static function author_label( $user_id ) {
		if ( SNP_Permissions::is_founder( $user_id ) ) {
			return 'Verified Founder';
		}
		if ( self::doctor_eligible( $user_id ) ) {
			return 'Verified Doctor';
		}
		return 'Author';
	}
}
