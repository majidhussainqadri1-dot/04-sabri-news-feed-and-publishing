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

	public static function target_public( $target_id ) {
		$post = get_post( absint( $target_id ) );
		return $post instanceof WP_Post && 'publish' === $post->post_status && in_array( $post->post_type, array( 'post', 'sabri_news' ), true );
	}
}
