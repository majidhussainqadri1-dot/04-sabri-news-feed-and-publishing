<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_File21_Adapter {
	const INTERACTION_PROVIDER = 'snfla_file04';

	public static function status() {
		return array(
			'available'       => SNFLA_Capabilities::file21_ready(),
			'package_version' => defined( 'SABRI_HNF_PACKAGE_VERSION' ) ? SABRI_HNF_PACKAGE_VERSION : '',
			'runtime_version' => defined( 'SABRI_HNF_VERSION' ) ? SABRI_HNF_VERSION : '',
			'required_package'=> SNFLA_FILE21_MIN_PACKAGE,
			'required_runtime'=> SNFLA_FILE21_MIN_RUNTIME,
		);
	}

	public static function preview( $limit = 100 ) {
		if ( ! SNFLA_Capabilities::file21_ready() ) {
			return new WP_Error( 'snfla_file21_unavailable', 'Canonical File 21 migration services are unavailable.' );
		}
		return \Sabri\HomeNewsFeed\LegacyPublicationMigration::preview( min( 100, max( 1, absint( $limit ) ) ) );
	}

	public static function migrate( array $legacy_ids, $actor_id, $with_interactions = true ) {
		$legacy_ids = self::strict_id_batch( $legacy_ids );
		$actor_id = self::strict_positive_id( $actor_id );
		if ( empty( $legacy_ids ) || $actor_id <= 0 ) { return new WP_Error( 'snfla_file21_contract_ids_invalid', 'Canonical File 21 migration requires positive unique IDs and actor identity.' ); }
		if ( ! SNFLA_Capabilities::file21_ready() ) {
			return new WP_Error( 'snfla_file21_unavailable', 'Canonical File 21 migration services are unavailable.' );
		}
		$preflight = SNFLA_Plan_Completion::migration_preflight( $legacy_ids );
		if ( is_wp_error( $preflight ) ) { return $preflight; }
		$author_context = array();
		$media_context  = array();
		foreach ( (array) ( $preflight['records'] ?? array() ) as $legacy_id => $row ) {
			$author_context[ absint( $legacy_id ) ] = array(
				'user_id'       => absint( $row['author']['user_id'] ?? 0 ),
				'platform_uuid' => sanitize_text_field( (string) ( $row['author']['platform_uuid'] ?? '' ) ),
				'placeholder'   => ! empty( $row['author']['placeholder'] ),
			);
			$media_context[ absint( $legacy_id ) ] = array(
				'provider_id'     => sanitize_key( (string) ( $row['media']['provider_id'] ?? '' ) ),
				'reference_count' => absint( $row['media']['reference_count'] ?? 0 ),
			);
		}
		$result = \Sabri\HomeNewsFeed\LegacyPublicationMigration::migrate_selected(
			$legacy_ids,
			absint( $actor_id ),
			array(
				'copy_comments'         => true,
				'copy_media'            => true,
				'copy_references'       => true,
				'target'                => 'auto',
				'migrate_interactions'  => (bool) $with_interactions,
				'interaction_provider'  => self::INTERACTION_PROVIDER,
				'author_identity_context'=> $author_context,
				'media_preflight_context'=> $media_context,
			)
		);
		return SNFLA_Plan_Completion::verify_file21_result( $legacy_ids, $result, absint( $actor_id ) );
	}

	public static function rollback( array $legacy_ids, $actor_id ) {
		$legacy_ids = self::strict_id_batch( $legacy_ids );
		$actor_id = self::strict_positive_id( $actor_id );
		if ( empty( $legacy_ids ) || $actor_id <= 0 ) { return new WP_Error( 'snfla_file21_contract_ids_invalid', 'Canonical File 21 rollback requires positive unique IDs and actor identity.' ); }
		if ( ! SNFLA_Capabilities::file21_ready() ) {
			return new WP_Error( 'snfla_file21_unavailable', 'Canonical File 21 rollback services are unavailable.' );
		}
		$result = \Sabri\HomeNewsFeed\LegacyPublicationRollback::rollback_selected( $legacy_ids, $actor_id );
		if ( is_wp_error( $result ) ) { return $result; }
		if ( ! is_array( $result ) ) { return new WP_Error( 'snfla_file21_rollback_result_invalid', 'File 21 rollback returned an invalid result envelope.' ); }
		$allowed = array_fill_keys( $legacy_ids, true );
		$rolled = isset( $result['rolled_back'] ) && is_array( $result['rolled_back'] ) ? $result['rolled_back'] : array();
		foreach ( $rolled as $raw_legacy_id => $row ) {
			$legacy_id = self::strict_positive_id( $raw_legacy_id );
			$target_id = is_array( $row ) ? self::strict_positive_id( $row['target_id'] ?? 0 ) : 0;
			if ( $legacy_id <= 0 || ! isset( $allowed[ $legacy_id ] ) || $target_id <= 0 || ! self::rolled_back_target_valid( $legacy_id, $target_id ) ) {
				return new WP_Error( 'snfla_file21_rollback_result_unverified', 'File 21 rollback returned an unexpected or unverifiable rolled-back target.' );
			}
		}
		foreach ( array( 'skipped', 'already_rolled_back' ) as $collection ) {
			if ( ! isset( $result[ $collection ] ) ) { continue; }
			if ( ! is_array( $result[ $collection ] ) ) { return new WP_Error( 'snfla_file21_rollback_result_invalid' ); }
			foreach ( $result[ $collection ] as $key => $value ) {
				$candidate = is_int( $key ) || ( is_string( $key ) && ctype_digit( $key ) ) ? $key : ( is_array( $value ) ? ( $value['legacy_id'] ?? 0 ) : $value );
				$id = self::strict_positive_id( $candidate );
				if ( $id <= 0 || ! isset( $allowed[ $id ] ) ) { return new WP_Error( 'snfla_file21_rollback_unexpected_result_ids' ); }
			}
		}
		return $result;
	}

	public static function target_for( $legacy_id ) {
		$legacy_id = self::strict_positive_id( $legacy_id );
		if ( $legacy_id <= 0 || ! SNFLA_Capabilities::file21_ready() ) { return 0; }
		return self::strict_positive_id( \Sabri\HomeNewsFeed\LegacyPublicationMigration::target_for( $legacy_id ) );
	}

	private static function strict_id_batch( array $ids ) {
		if ( empty( $ids ) || count( $ids ) > SNFLA_Migration::MAX_BATCH ) { return array(); }
		$out=array(); $seen=array();
		foreach ( $ids as $raw ) { $id=self::strict_positive_id( $raw ); if ( $id<=0 || isset( $seen[$id] ) ) { return array(); } $seen[$id]=true; $out[]=$id; }
		return $out;
	}

	private static function strict_positive_id( $value ) {
		if ( is_int( $value ) ) { return $value > 0 ? $value : 0; }
		if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) { return 0; }
		$parsed = (int) $value;
		return $parsed > 0 && (string) $parsed === $value ? $parsed : 0;
	}

	public static function migration_target_valid( $legacy_id, $target_id ) {
		$legacy_id = self::strict_positive_id( $legacy_id );
		$target_id = self::strict_positive_id( $target_id );
		$post = $target_id > 0 ? get_post( $target_id ) : null;
		return $legacy_id > 0
			&& $post instanceof WP_Post
			&& in_array( $post->post_type, array( 'post', 'sabri_news' ), true )
			&& self::target_for( $legacy_id ) === $target_id
			&& absint( get_post_meta( $target_id, '_sabri_hnf_legacy_source_id', true ) ) === $legacy_id
			&& SNFLA_Inventory::LEGACY_POST_TYPE === (string) get_post_meta( $target_id, '_sabri_hnf_legacy_source_type', true );
	}

	public static function rolled_back_target_valid( $legacy_id, $target_id ) {
		$legacy_id = self::strict_positive_id( $legacy_id );
		$target_id = self::strict_positive_id( $target_id );
		$post = $target_id > 0 ? get_post( $target_id ) : null;
		return $legacy_id > 0
			&& $post instanceof WP_Post
			&& in_array( $post->post_type, array( 'post', 'sabri_news' ), true )
			&& 'private' === (string) $post->post_status
			&& 0 === self::target_for( $legacy_id )
			&& absint( get_post_meta( $target_id, '_sabri_hnf_legacy_source_id', true ) ) === $legacy_id
			&& SNFLA_Inventory::LEGACY_POST_TYPE === (string) get_post_meta( $target_id, '_sabri_hnf_legacy_source_type', true );
	}

	/**
	 * Contain a File 21 migration target only through File 21's canonical
	 * rollback command. File 04 never writes File 21 posts or tables directly.
	 */
	public static function contain_orphan_target( $legacy_id, $target_id, $actor_id, $reason_code ) {
		$legacy_id  = absint( $legacy_id );
		$target_id  = absint( $target_id );
		$actor_id   = absint( $actor_id );
		$reason_code = sanitize_key( $reason_code ) ?: 'canonical_mapping_unverified';
		if ( $legacy_id <= 0 || $target_id <= 0
			|| absint( get_post_meta( $target_id, '_sabri_hnf_legacy_source_id', true ) ) !== $legacy_id
			|| SNFLA_Inventory::LEGACY_POST_TYPE !== (string) get_post_meta( $target_id, '_sabri_hnf_legacy_source_type', true ) ) {
			return new WP_Error( 'snfla_orphan_target_provenance_invalid', 'The File 21 target could not be safely attributed to this legacy record.', array( 'status' => 409 ) );
		}
		if ( $actor_id <= 0 || $actor_id !== get_current_user_id() ) {
			return new WP_Error( 'snfla_orphan_target_actor_invalid', 'Fresh canonical actor authority is required for containment.', array( 'status' => 403 ) );
		}
		$rollback = self::rollback( array( $legacy_id ), $actor_id );
		if ( is_wp_error( $rollback ) ) {
			return new WP_Error( 'snfla_file21_containment_command_failed', 'File 21 rejected the canonical containment command.', array( 'status' => 500, 'reason_code' => $reason_code, 'file21_code' => $rollback->get_error_code() ) );
		}
		$rolled = is_array( $rollback ) ? (array) ( $rollback['rolled_back'] ?? array() ) : array();
		$row    = isset( $rolled[ $legacy_id ] ) && is_array( $rolled[ $legacy_id ] ) ? $rolled[ $legacy_id ] : array();
		if ( absint( $row['target_id'] ?? 0 ) !== $target_id || self::target_public( $target_id ) || 'private' !== (string) get_post_status( $target_id ) || self::target_for( $legacy_id ) > 0 ) {
			return new WP_Error( 'snfla_file21_containment_unverified', 'File 21 did not verify a private, redirect-disabled containment state.', array( 'status' => 500, 'legacy_id' => $legacy_id, 'target_id' => $target_id ) );
		}
		do_action( 'sabri_hnf_invalidate_cache' );
		return array( 'contained' => true, 'canonical_owner' => 'File 21', 'provider_id' => 'file21_legacy_publication_rollback', 'status' => 'private', 'reason_code' => $reason_code );
	}

	public static function public_author_eligible( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 || ! self::status()['available'] ) {
			return false;
		}
		$adapter = '\\Sabri\\HomeNewsFeed\\CanonicalIdentityAdapter';
		return ( is_callable( array( $adapter, 'is_founder' ) ) && $adapter::is_founder( $user_id ) )
			|| ( is_callable( array( $adapter, 'is_administrator' ) ) && $adapter::is_administrator( $user_id ) )
			|| ( is_callable( array( $adapter, 'is_verified_doctor' ) ) && $adapter::is_verified_doctor( $user_id ) );
	}

	public static function target_public( $target_id ) {
		$post = get_post( absint( $target_id ) );
		return $post instanceof WP_Post && 'publish' === $post->post_status && in_array( $post->post_type, array( 'post', 'sabri_news' ), true );
	}
}
