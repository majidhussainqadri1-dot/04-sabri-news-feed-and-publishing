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
		if ( ! SNFLA_Capabilities::file21_ready() ) {
			return new WP_Error( 'snfla_file21_unavailable', 'Canonical File 21 migration services are unavailable.' );
		}
		return \Sabri\HomeNewsFeed\LegacyPublicationMigration::migrate_selected(
			$legacy_ids,
			absint( $actor_id ),
			array(
				'copy_comments'         => true,
				'target'                => 'auto',
				'migrate_interactions'  => (bool) $with_interactions,
				'interaction_provider'  => self::INTERACTION_PROVIDER,
			)
		);
	}

	public static function rollback( array $legacy_ids, $actor_id ) {
		if ( ! SNFLA_Capabilities::file21_ready() ) {
			return new WP_Error( 'snfla_file21_unavailable', 'Canonical File 21 rollback services are unavailable.' );
		}
		return \Sabri\HomeNewsFeed\LegacyPublicationRollback::rollback_selected( $legacy_ids, absint( $actor_id ) );
	}

	public static function target_for( $legacy_id ) {
		if ( ! SNFLA_Capabilities::file21_ready() ) {
			return 0;
		}
		return absint( \Sabri\HomeNewsFeed\LegacyPublicationMigration::target_for( absint( $legacy_id ) ) );
	}

	public static function migration_target_valid( $legacy_id, $target_id ) {
		$legacy_id = absint( $legacy_id );
		$target_id = absint( $target_id );
		$post = $target_id > 0 ? get_post( $target_id ) : null;
		return $legacy_id > 0
			&& $post instanceof WP_Post
			&& in_array( $post->post_type, array( 'post', 'sabri_news' ), true )
			&& self::target_for( $legacy_id ) === $target_id
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
