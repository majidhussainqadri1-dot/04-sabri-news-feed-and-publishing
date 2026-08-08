<?php
defined( 'ABSPATH' ) || exit;

/**
 * File 04 own-plan completion controls introduced in v1.3.0.
 *
 * This class deliberately provides migration preflight/evidence, operations,
 * performance and observability without taking ownership away from File 00,
 * File 20, File 21, File 24, File 25 or File 26.
 */
final class SNFLA_Plan_Completion {
	const ANALYSIS_OPTION = 'snfla_dry_run_analysis_v1';
	const METRICS_OPTION  = 'snfla_metrics_v1';
	const MAX_METRICS     = 500;
	private static $request_started = array();

	public static function boot() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ), 40 );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'rest_start' ), 10, 3 );
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'rest_finish' ), 10, 3 );
		add_filter( 'snfla_release_readiness_v1', array( __CLASS__, 'augment_release_readiness' ), 20, 1 );
	}

	public static function register_routes() {
		register_rest_route(
			SNFLA_REST::NAMESPACE,
			'/plan/dry-run-analysis',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rest_dry_run_analysis' ),
				'permission_callback' => array( __CLASS__, 'permission_run' ),
			)
		);
		register_rest_route(
			SNFLA_REST::NAMESPACE,
			'/plan/migration-preflight',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rest_migration_preflight' ),
				'permission_callback' => array( __CLASS__, 'permission_run' ),
				'args'                => array(
					'legacy_ids' => array(
						'required' => true,
						'type' => 'array',
						'items' => array( 'type' => 'integer' ),
						'validate_callback' => static function ( $value ) {
							return is_array( $value ) && count( $value ) >= 1 && count( $value ) <= SNFLA_Migration::MAX_BATCH;
						},
					),
				),
			)
		);
		register_rest_route(
			SNFLA_REST::NAMESPACE,
			'/plan/system-check',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_system_check' ),
				'permission_callback' => array( __CLASS__, 'permission_review' ),
			)
		);
		register_rest_route(
			SNFLA_REST::NAMESPACE,
			'/plan/metrics',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_metrics' ),
				'permission_callback' => array( __CLASS__, 'permission_review' ),
			)
		);
	}

	public static function permission_run( WP_REST_Request $request ) {
		$nonce = SNFLA_Capabilities::verify_rest_nonce( $request );
		if ( is_wp_error( $nonce ) ) { return $nonce; }
		$actor = SNFLA_Capabilities::current_read_actor( SNFLA_Capabilities::CAP_RUN );
		return is_wp_error( $actor ) ? $actor : true;
	}

	public static function permission_review( WP_REST_Request $request ) {
		unset( $request );
		$actor = SNFLA_Capabilities::current_read_actor( SNFLA_Capabilities::CAP_REVIEW );
		return is_wp_error( $actor ) ? $actor : true;
	}

	private static function rest_payload( $code, $data ) {
		$response = rest_ensure_response( array( 'ok' => true, 'code' => sanitize_key( $code ), 'data' => $data, 'trace_id' => wp_generate_uuid4() ) );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
		return $response;
	}

	public static function rest_dry_run_analysis( WP_REST_Request $request ) {
		unset( $request );
		$actor = get_current_user_id();
		$result = self::dry_run_analysis( $actor );
		return is_wp_error( $result ) ? $result : self::rest_payload( 'snfla_dry_run_analysis', $result );
	}

	public static function rest_migration_preflight( WP_REST_Request $request ) {
		$result = self::migration_preflight( (array) $request->get_param( 'legacy_ids' ) );
		return is_wp_error( $result ) ? $result : self::rest_payload( 'snfla_migration_preflight', $result );
	}

	public static function rest_system_check( WP_REST_Request $request ) {
		unset( $request );
		return self::rest_payload( 'snfla_system_check', self::system_check() );
	}

	public static function rest_metrics( WP_REST_Request $request ) {
		unset( $request );
		return self::rest_payload( 'snfla_metrics', self::metrics_summary() );
	}

	/**
	 * Resolve an immutable File 00 platform UUID. No display name, role slug or
	 * mutable e-mail fallback is accepted as identity evidence.
	 */
	public static function authorship_preflight( $legacy_id, $author_id = 0 ) {
		$legacy_id = absint( $legacy_id );
		$post      = get_post( $legacy_id );
		$author_id = $author_id > 0 ? absint( $author_id ) : ( $post instanceof WP_Post ? absint( $post->post_author ) : 0 );
		$user      = $author_id > 0 ? get_userdata( $author_id ) : false;
		$placeholder = false;

		if ( ! $user ) {
			$replacement = apply_filters(
				'sabri_file00_legacy_author_placeholder_v1',
				array( 'verified' => false ),
				array( 'file_number' => '04', 'legacy_id' => $legacy_id, 'legacy_author_id' => $author_id )
			);
			if ( ! is_array( $replacement ) || empty( $replacement['verified'] ) || empty( $replacement['user_id'] ) || empty( $replacement['platform_uuid'] ) ) {
				return new WP_Error( 'snfla_author_placeholder_contract_required', 'A deleted or unknown legacy author requires a File 00 governed placeholder contract; File 04 will not guess attribution.', array( 'status' => 412, 'legacy_id' => $legacy_id ) );
			}
			$author_id = absint( $replacement['user_id'] );
			$user      = $author_id > 0 ? get_userdata( $author_id ) : false;
			$uuid      = strtolower( trim( (string) $replacement['platform_uuid'] ) );
			$placeholder = true;
			if ( ! $user || ! self::valid_uuid( $uuid ) ) {
				return new WP_Error( 'snfla_author_placeholder_invalid', 'The governed author placeholder contract returned an invalid identity.', array( 'status' => 412, 'legacy_id' => $legacy_id ) );
			}
		} else {
			$uuid = apply_filters(
				'sabri_file00_platform_uuid_v1',
				'',
				$author_id,
				array( 'file_number' => '04', 'legacy_id' => $legacy_id, 'purpose' => 'legacy_publication_migration' )
			);
			$uuid = strtolower( trim( (string) $uuid ) );
			if ( ! self::valid_uuid( $uuid ) ) {
				return new WP_Error( 'snfla_author_platform_uuid_unresolved', 'File 00 must resolve the author to an immutable platform UUID before migration.', array( 'status' => 412, 'legacy_id' => $legacy_id, 'author_id' => $author_id ) );
			}
		}

		return array(
			'verified'      => true,
			'user_id'       => $author_id,
			'platform_uuid' => $uuid,
			'placeholder'   => $placeholder,
			'legacy_id'     => $legacy_id,
		);
	}

	private static function valid_uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value );
	}

	/**
	 * Build a privacy-minimized media/reference manifest and require the
	 * canonical File 21 provider to attest ownership/rights/alt/broken-link
	 * handling before a media-bearing record becomes migration-eligible.
	 */
	public static function media_preflight( $legacy_id ) {
		$legacy_id = absint( $legacy_id );
		if ( $legacy_id <= 0 ) {
			return new WP_Error( 'snfla_media_legacy_id_invalid', 'A valid legacy publication ID is required.', array( 'status' => 400 ) );
		}
		$refs = array();
		global $wpdb;
		$cursor = 0;
		$batch_size = 200;
		do {
			$wpdb->last_error = '';
			$attachment_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_parent=%d AND post_type='attachment' AND post_status='inherit' AND ID>%d ORDER BY ID ASC LIMIT %d",
					$legacy_id,
					$cursor,
					$batch_size
				)
			);
			if ( ! empty( $wpdb->last_error ) || ! is_array( $attachment_ids ) ) {
				return new WP_Error( 'snfla_media_attachment_query_failed', 'Legacy attachment references could not be read safely.', array( 'status' => 500, 'legacy_id' => $legacy_id ) );
			}
			foreach ( array_map( 'absint', $attachment_ids ) as $attachment_id ) {
				if ( $attachment_id <= $cursor ) { return new WP_Error( 'snfla_media_attachment_cursor_invalid', 'Legacy attachment traversal could not make forward progress.', array( 'status' => 500, 'legacy_id' => $legacy_id ) ); }
				$cursor = $attachment_id;
				$file = get_attached_file( $attachment_id, true );
				$mime = (string) get_post_mime_type( $attachment_id );
				$alt  = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
				$refs[] = array(
					'reference_id' => 'attachment:' . $attachment_id,
					'type'         => 'attachment',
					'attachment_id'=> $attachment_id,
					'mime'         => sanitize_mime_type( $mime ),
					'bytes'        => is_string( $file ) && is_file( $file ) ? (int) filesize( $file ) : 0,
					'sha256'       => is_string( $file ) && is_file( $file ) ? hash_file( 'sha256', $file ) : '',
					'alt_present'  => '' !== trim( $alt ),
					'file_present' => is_string( $file ) && is_file( $file ),
				);
			}
		} while ( count( $attachment_ids ) === $batch_size );
		foreach ( array( '_snp_video_url', '_snp_media_manifest', '_snp_source_ledger' ) as $key ) {
			$value = get_post_meta( $legacy_id, $key, true );
			$present = is_array( $value ) ? ! empty( $value ) : ( is_object( $value ) || '' !== trim( (string) $value ) );
			if ( $present ) {
				$refs[] = array( 'reference_id' => 'meta:' . $key, 'type' => 'legacy_reference', 'meta_key' => $key, 'value_digest' => hash( 'sha256', wp_json_encode( SNFLA_Audit::redact( $value ) ) ?: $key ) );
			}
		}
		if ( empty( $refs ) ) {
			return array( 'verified' => true, 'provider_id' => 'file04_no_media', 'legacy_id' => $legacy_id, 'references' => array(), 'reference_count' => 0 );
		}
		foreach ( $refs as $ref ) {
			if ( 'attachment' === $ref['type'] && ( empty( $ref['file_present'] ) || ! preg_match( '/^[a-f0-9]{64}$/', (string) $ref['sha256'] ) ) ) {
				return new WP_Error( 'snfla_media_reference_broken', 'A legacy attachment is missing or cannot be checksummed; it must be repaired or quarantined.', array( 'status' => 412, 'legacy_id' => $legacy_id, 'reference_id' => $ref['reference_id'] ) );
			}
		}
		$request = array(
			'file_number' => '04',
			'legacy_id' => $legacy_id,
			'references' => $refs,
			'required_assertions' => array( 'ownership_verified', 'rights_or_license_verified', 'alt_policy_verified', 'duplicate_hash_checked', 'broken_links_zero', 'canonical_target_contract' ),
		);
		$evidence = apply_filters( 'sabri_file21_legacy_media_preflight_v1', array( 'verified' => false ), $request );
		if ( ! is_array( $evidence ) || empty( $evidence['verified'] ) || empty( $evidence['provider_id'] ) || empty( $evidence['ownership_verified'] ) || empty( $evidence['rights_or_license_verified'] ) || empty( $evidence['alt_policy_verified'] ) || empty( $evidence['duplicate_hash_checked'] ) || ! array_key_exists( 'broken_links', $evidence ) || 0 !== absint( $evidence['broken_links'] ) ) {
			return new WP_Error( 'snfla_file21_media_contract_required', 'Media/reference migration requires a current File 21 provider attestation for rights, ownership, alt policy, duplicate hashes and broken links.', array( 'status' => 412, 'legacy_id' => $legacy_id ) );
		}
		$accepted = array_values( array_unique( array_map( 'sanitize_text_field', (array) ( $evidence['accepted_reference_ids'] ?? array() ) ) ) );
		$required = array_values( array_map( static function ( $ref ) { return (string) $ref['reference_id']; }, $refs ) );
		sort( $accepted ); sort( $required );
		if ( $accepted !== $required ) {
			return new WP_Error( 'snfla_file21_media_reference_coverage_incomplete', 'File 21 did not attest every legacy media/reference object.', array( 'status' => 412, 'legacy_id' => $legacy_id ) );
		}
		return array(
			'verified' => true,
			'provider_id' => sanitize_key( (string) $evidence['provider_id'] ),
			'legacy_id' => $legacy_id,
			'references' => $refs,
			'reference_count' => count( $refs ),
			'evidence' => SNFLA_Audit::redact( $evidence ),
		);
	}

	public static function migration_preflight( array $legacy_ids ) {
		$legacy_ids = SNFLA_Integrity::normalized_ids( $legacy_ids, SNFLA_Migration::MAX_BATCH );
		if ( empty( $legacy_ids ) ) {
			return new WP_Error( 'snfla_empty_preflight_batch', 'Select at least one legacy publication.', array( 'status' => 400 ) );
		}
		if ( ! SNFLA_Inventory::unchanged() ) {
			return new WP_Error( 'snfla_inventory_changed', 'The legacy source changed after inventory lock.', array( 'status' => 409 ) );
		}
		$rows = array();
		foreach ( $legacy_ids as $legacy_id ) {
			$post = get_post( $legacy_id );
			if ( ! $post instanceof WP_Post || SNFLA_Inventory::LEGACY_POST_TYPE !== $post->post_type ) {
				return new WP_Error( 'snfla_preflight_source_invalid', 'A selected legacy publication is unavailable.', array( 'status' => 404, 'legacy_id' => $legacy_id ) );
			}
			$author = self::authorship_preflight( $legacy_id, $post->post_author );
			if ( is_wp_error( $author ) ) { return $author; }
			$media = self::media_preflight( $legacy_id );
			if ( is_wp_error( $media ) ) { return $media; }
			$rows[ $legacy_id ] = array( 'author' => $author, 'media' => $media, 'source_checksum' => SNFLA_Checksum::post( $legacy_id ) );
		}
		return array( 'verified' => true, 'legacy_ids' => $legacy_ids, 'records' => $rows, 'checked_at_utc' => gmdate( 'Y-m-d H:i:s' ) );
	}

	/** Verify canonical post-migration media/reference coverage or force rollback. */
	public static function verify_file21_result( array $legacy_ids, $result, $actor_id ) {
		if ( is_wp_error( $result ) || ! is_array( $result ) ) { return $result; }
		$migrated = (array) ( $result['migrated'] ?? array() );
		foreach ( $legacy_ids as $legacy_id ) {
			$legacy_id = absint( $legacy_id );
			if ( ! isset( $migrated[ $legacy_id ] ) ) { continue; }
			$target_id = absint( $migrated[ $legacy_id ]['target_id'] ?? 0 );
			$preflight = self::media_preflight( $legacy_id );
			if ( is_wp_error( $preflight ) ) { return $preflight; }
			if ( empty( $preflight['reference_count'] ) ) { continue; }
			$verify = apply_filters(
				'sabri_file21_verify_migrated_legacy_media_v1',
				array( 'verified' => false ),
				array( 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'references' => $preflight['references'], 'provider_id' => $preflight['provider_id'] )
			);
			if ( ! is_array( $verify ) || empty( $verify['verified'] ) || empty( $verify['provider_id'] ) || absint( $verify['target_id'] ?? 0 ) !== $target_id || absint( $verify['verified_reference_count'] ?? -1 ) !== absint( $preflight['reference_count'] ) ) {
				$containment = $target_id > 0 ? SNFLA_File21_Adapter::contain_orphan_target( $legacy_id, $target_id, $actor_id, 'media_reference_post_migration_unverified' ) : null;
				return new WP_Error( 'snfla_file21_media_post_migration_unverified', 'Canonical File 21 media/reference migration could not be verified; the target was contained where possible.', array( 'status' => 412, 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'contained' => is_array( $containment ) && ! empty( $containment['contained'] ) ) );
			}
		}
		return $result;
	}

	/**
	 * Complete the dry-run planning evidence required by F04-FR-004 and 11.2.
	 * This is non-mutating with respect to legacy/canonical content.
	 */
	public static function dry_run_estimates( array $sample, array $totals ) {
		global $wpdb;
		$queries = array(
			'publication_bytes' => $wpdb->prepare( "SELECT COALESCE(SUM(OCTET_LENGTH(post_title)+OCTET_LENGTH(post_content)+OCTET_LENGTH(post_excerpt)),0) FROM {$wpdb->posts} WHERE post_type=%s", SNFLA_Inventory::LEGACY_POST_TYPE ),
			'meta_bytes' => $wpdb->prepare( "SELECT COALESCE(SUM(OCTET_LENGTH(pm.meta_key)+OCTET_LENGTH(pm.meta_value)),0) FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE p.post_type=%s", SNFLA_Inventory::LEGACY_POST_TYPE ),
			'comment_bytes' => $wpdb->prepare( "SELECT COALESCE(SUM(OCTET_LENGTH(c.comment_content)+OCTET_LENGTH(c.comment_author)+OCTET_LENGTH(c.comment_author_email)),0) FROM {$wpdb->comments} c INNER JOIN {$wpdb->posts} p ON p.ID=c.comment_post_ID WHERE p.post_type=%s", SNFLA_Inventory::LEGACY_POST_TYPE ),
		);
		$parts = array();
		foreach ( $queries as $key => $sql ) {
			$wpdb->last_error = '';
			$value = $wpdb->get_var( $sql );
			if ( ! empty( $wpdb->last_error ) || ! is_numeric( $value ) ) {
				return new WP_Error( 'snfla_dry_run_size_estimate_failed', 'Dry-run storage estimation failed closed because source size could not be measured.', array( 'status' => 500, 'component' => $key ) );
			}
			$parts[ $key ] = max( 0, (int) $value );
		}
		$wpdb->last_error = '';
		$attachment_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} a INNER JOIN {$wpdb->posts} p ON p.ID=a.post_parent WHERE a.post_type='attachment' AND p.post_type=%s", SNFLA_Inventory::LEGACY_POST_TYPE ) );
		if ( ! empty( $wpdb->last_error ) || ! is_numeric( $attachment_count ) ) { return new WP_Error( 'snfla_dry_run_attachment_count_failed', 'Legacy attachment counts could not be measured safely.', array( 'status' => 500 ) ); }
		$attachment_count = max( 0, (int) $attachment_count );
		$attachment_bytes = apply_filters( 'snfla_storage_estimate_media_bytes_v1', null, $attachment_count, SNFLA_Inventory::locked() );
		if ( $attachment_count > 0 && ! is_numeric( $attachment_bytes ) ) { return new WP_Error( 'snfla_media_storage_estimate_provider_required', 'A bounded storage provider estimate is required for legacy attachments; File 04 will not perform an unbounded filesystem scan.', array( 'status' => 412, 'attachment_count' => $attachment_count ) ); }
		$parts['attachment_bytes'] = max( 0, (int) $attachment_bytes );
		$parts['attachment_count'] = $attachment_count;
		$source_bytes = $parts['publication_bytes'] + $parts['meta_bytes'] + $parts['comment_bytes'] + $parts['attachment_bytes'];
		$items = absint( $totals['candidate_count'] ?? 0 );
		$throughput = (float) apply_filters( 'snfla_migration_estimated_items_per_second', 5.0, $items, $source_bytes );
		$throughput = min( 1000.0, max( 0.1, $throughput ) );
		$seconds = $items > 0 ? (int) ceil( $items / $throughput ) : 0;
		$preview = SNFLA_File21_Adapter::preview( min( 20, max( 1, count( $sample ) ) ) );
		if ( is_wp_error( $preview ) ) { return $preview; }
		$already = absint( $totals['already_migrated'] ?? 0 );
		$conflict = absint( $totals['conflict_count'] ?? 0 );
		$eligible = absint( $totals['eligible_count'] ?? 0 );
		return array(
			'estimated_dispositions' => array(
				'create' => $eligible,
				'update' => 0,
				'merge' => 0,
				'quarantine' => max( 0, $conflict - $already ),
				'skip' => $already,
				'delete' => 0,
			),
			'storage_estimate' => array(
				'source_logical_bytes' => $source_bytes,
				'components' => $parts,
				'estimated_target_logical_bytes' => (int) ceil( $source_bytes * 1.20 ),
				'estimate_only' => true,
			),
			'time_estimate' => array(
				'items' => $items,
				'assumed_items_per_second' => $throughput,
				'estimated_seconds' => $seconds,
				'planning_range_seconds' => array( (int) ceil( $seconds * 0.5 ), (int) ceil( $seconds * 2.0 ) ),
				'not_an_slo' => true,
			),
			'sample_diffs' => SNFLA_Audit::redact( $preview ),
		);
	}

	public static function dry_run_analysis( $actor_id ) {
		$authorized = SNFLA_Capabilities::revalidate_actor( $actor_id, SNFLA_Capabilities::CAP_RUN );
		if ( is_wp_error( $authorized ) ) { return $authorized; }
		$dry = get_option( SNFLA_Schema::DRY_RUN_OPTION, array() );
		$locked = SNFLA_Inventory::locked();
		if ( ! SNFLA_Integrity::report_checksum_valid( $dry ) || empty( $dry['complete_scan'] ) || empty( $dry['run_uuid'] ) || empty( $dry['source_signature'] ) || empty( $locked['source_signature'] ) || ! hash_equals( (string) $locked['source_signature'], (string) $dry['source_signature'] ) || ! SNFLA_Inventory::unchanged() ) {
			return new WP_Error( 'snfla_current_dry_run_required', 'A current complete dry-run bound to the locked source is required.', array( 'status' => 412 ) );
		}
		$totals = array(
			'candidate_count' => absint( $dry['candidate_count'] ?? 0 ),
			'eligible_count' => absint( $dry['eligible_count'] ?? 0 ),
			'conflict_count' => absint( $dry['conflict_count'] ?? 0 ),
			'already_migrated' => absint( $dry['already_migrated'] ?? 0 ),
		);
		$analysis = self::dry_run_estimates( (array) ( $dry['candidate_sample'] ?? array() ), $totals );
		if ( is_wp_error( $analysis ) ) { return $analysis; }
		$analysis = array_merge(
			array(
				'schema' => 1,
				'run_uuid' => (string) $dry['run_uuid'],
				'source_signature' => (string) $dry['source_signature'],
				'created_at_utc' => gmdate( 'Y-m-d H:i:s' ),
				'non_mutating' => true,
				'canonical_owner' => 'File 21',
			),
			$analysis
		);
		$analysis['analysis_checksum'] = SNFLA_Checksum::hash( $analysis );
		if ( ! update_option( self::ANALYSIS_OPTION, $analysis, false ) && get_option( self::ANALYSIS_OPTION, array() ) !== $analysis ) {
			return new WP_Error( 'snfla_dry_run_analysis_persist_failed', 'The dry-run planning analysis could not be persisted.', array( 'status' => 500 ) );
		}
		SNFLA_Audit::record( 'dry_run_analysis_completed', $actor_id, array( 'run_uuid' => $analysis['run_uuid'], 'analysis_checksum' => $analysis['analysis_checksum'], 'source_signature' => $analysis['source_signature'] ), 'dry-run-analysis:' . $analysis['run_uuid'] );
		return $analysis;
	}

	public static function current_dry_run_analysis() {
		$analysis = get_option( self::ANALYSIS_OPTION, array() );
		$dry = get_option( SNFLA_Schema::DRY_RUN_OPTION, array() );
		if ( ! is_array( $analysis ) || empty( $analysis['analysis_checksum'] ) ) { return array(); }
		$expected_checksum = strtolower( (string) $analysis['analysis_checksum'] );
		$checksum_payload = $analysis;
		unset( $checksum_payload['analysis_checksum'] );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $expected_checksum ) || ! hash_equals( $expected_checksum, SNFLA_Checksum::hash( $checksum_payload ) ) ) { return array(); }
		if ( empty( $dry['run_uuid'] ) || empty( $analysis['run_uuid'] ) || ! hash_equals( (string) $dry['run_uuid'], (string) $analysis['run_uuid'] ) || empty( $dry['source_signature'] ) || ! hash_equals( (string) $dry['source_signature'], (string) ( $analysis['source_signature'] ?? '' ) ) ) {
			return array();
		}
		return $analysis;
	}

	public static function system_check() {
		$checks = array();
		$checks['runtime'] = array( 'status' => version_compare( PHP_VERSION, '8.1', '>=' ) ? 'pass' : 'blocker', 'php' => PHP_VERSION, 'wordpress' => get_bloginfo( 'version' ), 'required_php' => '>=8.1', 'staging_target' => 'WordPress 7.0.1 / PHP 8.3.x fresh re-verification required' );
		$checks['file21'] = array_merge( array( 'status' => SNFLA_Capabilities::file21_ready() ? 'pass' : 'blocker' ), SNFLA_File21_Adapter::status() );
		$file26_manifest = SNFLA_Central_Plan::module_manifest();
		$file26_request = array( 'consumer' => 'File 04', 'purpose' => 'legacy_resolution_search_handoff', 'manifest_digest' => SNFLA_Checksum::hash( $file26_manifest ) );
		$file26 = apply_filters( 'sabri_file26_accept_file04_contract_v1', array( 'accepted' => false, 'status' => 'unknown' ), $file26_request );
		$file26_bound = is_array( $file26 ) && ! empty( $file26['accepted'] ) && ! empty( $file26['provider_id'] ) && ! empty( $file26['manifest_digest'] ) && hash_equals( (string) $file26_request['manifest_digest'], (string) $file26['manifest_digest'] );
		$checks['file26'] = array( 'status' => $file26_bound ? 'pass' : 'unknown', 'request_digest' => $file26_request['manifest_digest'], 'evidence' => SNFLA_Audit::redact( is_array( $file26 ) ? $file26 : array() ) );
		$checks['inventory'] = array( 'status' => SNFLA_Inventory::unchanged() ? 'pass' : 'blocker', 'locked' => ! empty( SNFLA_Inventory::locked() ) );
		$analysis = self::current_dry_run_analysis();
		$checks['dry_run_analysis'] = array( 'status' => ! empty( $analysis ) ? 'pass' : 'unknown', 'checksum' => $analysis['analysis_checksum'] ?? '' );
		$checks['backup_restore'] = array( 'status' => SNFLA_Migration::backup_proof_valid() ? 'pass' : 'unknown' );
		$reconciliation = SNFLA_Reconciliation::report();
		$checks['reconciliation'] = array( 'status' => ! empty( $reconciliation['green'] ) && SNFLA_Reconciliation::validate_current_report( $reconciliation ) ? 'pass' : 'unknown', 'report_checksum' => $reconciliation['report_checksum'] ?? '' );
		$audit = SNFLA_Audit::verify_chain();
		$checks['audit'] = array_merge( array( 'status' => ! empty( $audit['valid'] ) ? 'pass' : 'blocker' ), is_array( $audit ) ? $audit : array( 'valid' => false ) );
		$checks['metrics'] = array( 'status' => empty( self::metrics_summary()['sample_count'] ) ? 'unknown' : 'pass', 'summary' => self::metrics_summary() );
		$checks['queue'] = array( 'status' => 'not_applicable', 'reason' => 'File 04 uses bounded synchronous migration batches and canonical provider contracts; no independent File 04 delivery queue is owned.' );
		$checks['cache_search'] = array( 'status' => SNFLA_Schema::state() === 'redirect_cutover' || SNFLA_Schema::state() === 'read_only_fallback' || SNFLA_Schema::state() === 'retired' ? 'evidence_in_audit_chain' : 'pending_cutover', 'canonical_search_owner' => 'File 26' );
		$blockers = array(); $unknown = array();
		foreach ( $checks as $key => $row ) {
			if ( ! is_array( $row ) ) { continue; }
			if ( 'blocker' === ( $row['status'] ?? '' ) ) { $blockers[] = $key; }
			elseif ( in_array( (string) ( $row['status'] ?? '' ), array( 'unknown', 'pending_cutover' ), true ) ) { $unknown[] = $key; }
		}
		return array( 'schema' => 2, 'checked_at_utc' => gmdate( 'Y-m-d H:i:s' ), 'checks' => $checks, 'blockers' => $blockers, 'unknown_or_pending' => $unknown, 'healthy_for_current_lifecycle' => empty( $blockers ), 'production_acceptance_separate' => true, 'production_accepted' => false );
	}

	public static function rest_start( $result, $server, $request ) {
		unset( $server );
		if ( $request instanceof WP_REST_Request && 0 === strpos( (string) $request->get_route(), '/' . SNFLA_REST::NAMESPACE ) ) {
			self::$request_started[ spl_object_hash( $request ) ] = microtime( true );
		}
		return $result;
	}

	public static function rest_finish( $response, $handler, $request ) {
		unset( $handler );
		if ( ! $request instanceof WP_REST_Request || 0 !== strpos( (string) $request->get_route(), '/' . SNFLA_REST::NAMESPACE ) ) { return $response; }
		$key = spl_object_hash( $request );
		$start = self::$request_started[ $key ] ?? microtime( true );
		unset( self::$request_started[ $key ] );
		$duration_ms = max( 0.0, ( microtime( true ) - $start ) * 1000 );
		if ( is_wp_error( $response ) ) {
			$error_data = $response->get_error_data();
			$status = is_array( $error_data ) ? (int) ( $error_data['status'] ?? 500 ) : 500;
		} else {
			$status = $response instanceof WP_REST_Response ? $response->get_status() : 200;
		}
		self::record_metric( (string) $request->get_route(), $duration_ms, $status );
		return $response;
	}

	private static function record_metric( $route, $duration_ms, $status ) {
		$route = preg_replace( '/[^a-zA-Z0-9_\-\/]/', '', $route );
		$row = array( 'route' => substr( $route, 0, 190 ), 'duration_ms' => round( (float) $duration_ms, 3 ), 'status' => (int) $status, 'at' => time() );
		$metrics = get_option( self::METRICS_OPTION, array() );
		$metrics = is_array( $metrics ) ? $metrics : array();
		$metrics[] = $row;
		if ( count( $metrics ) > self::MAX_METRICS ) { $metrics = array_slice( $metrics, -1 * self::MAX_METRICS ); }
		update_option( self::METRICS_OPTION, $metrics, false );
		$threshold = (float) apply_filters( 'snfla_rest_p95_budget_ms', 2000.0, $route );
		if ( $status >= 500 || $duration_ms > $threshold ) {
			do_action( 'snfla_operational_alert_v1', array( 'owner' => 'File 04 release operator', 'severity' => $status >= 500 ? 'high' : 'medium', 'code' => $status >= 500 ? 'rest_error' : 'latency_budget_exceeded', 'route' => $route, 'duration_ms' => round( $duration_ms, 3 ), 'status' => $status, 'trace_safe' => true ) );
		}
	}

	public static function metrics_summary() {
		$metrics = get_option( self::METRICS_OPTION, array() );
		$metrics = is_array( $metrics ) ? array_values( $metrics ) : array();
		$durations = array_map( static function ( $row ) { return (float) ( $row['duration_ms'] ?? 0 ); }, $metrics );
		sort( $durations, SORT_NUMERIC );
		$errors = count( array_filter( $metrics, static function ( $row ) { return (int) ( $row['status'] ?? 0 ) >= 500; } ) );
		return array(
			'sample_count' => count( $metrics ),
			'p75_ms' => self::percentile( $durations, 0.75 ),
			'p95_ms' => self::percentile( $durations, 0.95 ),
			'error_count' => $errors,
			'error_rate' => count( $metrics ) ? round( $errors / count( $metrics ), 6 ) : 0,
			'objective' => array( 'endpoint_specific_p95_required_in_staging' => true, 'silent_failure' => 'forbidden', 'production_slo_requires_real_monitoring' => true ),
		);
	}

	private static function percentile( array $values, $p ) {
		$count = count( $values );
		if ( 0 === $count ) { return null; }
		$index = (int) ceil( $p * $count ) - 1;
		$index = min( $count - 1, max( 0, $index ) );
		return round( (float) $values[ $index ], 3 );
	}

	public static function augment_release_readiness( $readiness ) {
		$readiness = is_array( $readiness ) ? $readiness : array();
		$blockers = (array) ( $readiness['blockers'] ?? array() );
		$analysis = self::current_dry_run_analysis();
		if ( SNFLA_Schema::state() !== 'legacy_active' && empty( $analysis ) ) { $blockers[] = 'file04_dry_run_analysis_pending'; }
		$system = self::system_check();
		foreach ( (array) ( $system['blockers'] ?? array() ) as $system_blocker ) { $blockers[] = 'file04_system_' . sanitize_key( $system_blocker ); }
		$readiness['file04_system_check'] = $system;
		$readiness['performance_metrics'] = self::metrics_summary();
		$readiness['blockers'] = array_values( array_unique( $blockers ) );
		$readiness['source_candidate_ready'] = empty( $readiness['blockers'] );
		// Source/CI evidence can never self-promote this adapter to production acceptance.
		$readiness['production_ready'] = false;
		$readiness['external_acceptance_required'] = array( 'hostinger_staging', 'real_file00_file21_file26_contracts', 'backup_restore', 'real_role_journeys', 'founder_approval', 'production_monitoring_rollback_window' );
		return $readiness;
	}
}
