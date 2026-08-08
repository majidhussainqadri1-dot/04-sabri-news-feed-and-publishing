<?php
defined( 'ABSPATH' ) || exit;

/**
 * File 04 Future18 — intelligent migration-safety capabilities.
 *
 * These capabilities are intentionally adapter-only. They may observe, simulate,
 * verify, score, attest and recommend, but they never become the canonical
 * publication, feed, ranking, search, shell, visual or moderation owner.
 */
final class SNFLA_Future18 {
	const VERSION                 = '2.0.0';
	const TWIN_OPTION             = 'snfla_future18_latest_twin_v1';
	const DRIFT_OPTION            = 'snfla_future18_contract_drift_v1';
	const RECEIPTS_OPTION         = 'snfla_future18_receipts_v1';
	const CHECKPOINTS_OPTION      = 'snfla_future18_checkpoints_v1';
	const CANARY_OPTION           = 'snfla_future18_canary_v1';
	const SHADOW_OPTION           = 'snfla_future18_shadow_v1';
	const GAMEDAY_OPTION          = 'snfla_future18_gameday_v1';
	const MAX_RECEIPTS            = 500;
	const MAX_CHECKPOINTS         = 100;
	const MAX_IDS                 = 100;
	const RETIREMENT_GATE_COUNT   = 10;

	public static function boot() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ), 45 );
		add_action( 'snfla_daily_integrity_check', array( __CLASS__, 'scheduled_invariant_guardian' ), 30 );
		add_filter( 'snfla_release_readiness_v1', array( __CLASS__, 'augment_release_readiness' ), 30, 1 );
	}

	/** Exact 18-feature registry; IDs are stable release-contract identifiers. */
	public static function registry() {
		return array(
			'F04-FUT-001' => array( 'name' => 'Migration Digital Twin', 'priority' => 'P0', 'owner' => 'File 04', 'mode' => 'native_simulation' ),
			'F04-FUT-002' => array( 'name' => 'Schema & Contract Drift Sentinel', 'priority' => 'P0', 'owner' => 'File 04', 'mode' => 'native_assurance' ),
			'F04-FUT-003' => array( 'name' => 'Semantic Content Fidelity Engine', 'priority' => 'P0', 'owner' => 'File 04', 'mode' => 'native_verification' ),
			'F04-FUT-004' => array( 'name' => 'Visual Migration Diff Laboratory', 'priority' => 'P1', 'owner' => 'File 20/File 25', 'mode' => 'integration_verification' ),
			'F04-FUT-005' => array( 'name' => 'End-to-End Data Lineage Graph', 'priority' => 'P0', 'owner' => 'File 04', 'mode' => 'native_evidence' ),
			'F04-FUT-006' => array( 'name' => 'Migration Risk Scoring Engine', 'priority' => 'P0', 'owner' => 'File 04', 'mode' => 'advisory_only' ),
			'F04-FUT-007' => array( 'name' => 'AI-Assisted Quarantine Investigator', 'priority' => 'P1', 'owner' => 'File 04', 'mode' => 'advisory_only' ),
			'F04-FUT-008' => array( 'name' => 'Shadow-Read & Traffic Replay Lab', 'priority' => 'P0', 'owner' => 'File 04/File 21', 'mode' => 'read_only_comparison' ),
			'F04-FUT-009' => array( 'name' => 'Adaptive Canary Migration Controller', 'priority' => 'P0', 'owner' => 'File 04', 'mode' => 'approval_gated_controller' ),
			'F04-FUT-010' => array( 'name' => 'Continuous Migration Invariant Guardian', 'priority' => 'P0', 'owner' => 'File 04', 'mode' => 'native_assurance' ),
			'F04-FUT-011' => array( 'name' => 'Cryptographic Migration Receipt Ledger', 'priority' => 'P0', 'owner' => 'File 04', 'mode' => 'tamper_evident_evidence' ),
			'F04-FUT-012' => array( 'name' => 'Point-in-Time Migration Replay', 'priority' => 'P1', 'owner' => 'File 04', 'mode' => 'non_mutating_replay' ),
			'F04-FUT-013' => array( 'name' => 'Dependency Blast-Radius Analyzer', 'priority' => 'P0', 'owner' => 'File 04', 'mode' => 'read_only_analysis' ),
			'F04-FUT-014' => array( 'name' => 'Global Redirect & Citation Preservation Observatory', 'priority' => 'P0', 'owner' => 'File 04/File 21', 'mode' => 'read_only_verification' ),
			'F04-FUT-015' => array( 'name' => 'Urdu/Arabic Unicode & RTL Fidelity Guard', 'priority' => 'P0', 'owner' => 'File 04', 'mode' => 'native_verification' ),
			'F04-FUT-016' => array( 'name' => 'Automated Disaster-Recovery GameDay', 'priority' => 'P1', 'owner' => 'File 04/File 24', 'mode' => 'disposable_environment_only' ),
			'F04-FUT-017' => array( 'name' => 'Retirement Confidence & Dependency Sunset Engine', 'priority' => 'P0', 'owner' => 'File 04', 'mode' => 'advisory_gate' ),
			'F04-FUT-018' => array( 'name' => 'Migration Mission Control Center', 'priority' => 'P1', 'owner' => 'File 04', 'mode' => 'read_only_command_view' ),
		);
	}

	public static function register_routes() {
		$read = array( __CLASS__, 'permission_read' );
		$act  = array( __CLASS__, 'permission_action' );
		$ids  = array(
			'legacy_ids' => array(
				'required' => true,
				'type' => 'array',
				'items' => array( 'type' => 'integer' ),
				'validate_callback' => static function ( $value ) { return is_array( $value ) && count( $value ) >= 1 && count( $value ) <= self::MAX_IDS; },
			),
		);
		self::route( '/future/registry', WP_REST_Server::READABLE, 'rest_registry', $read );
		self::route( '/future/digital-twin', WP_REST_Server::CREATABLE, 'rest_digital_twin', $act, $ids );
		self::route( '/future/contract-drift', WP_REST_Server::READABLE, 'rest_contract_drift', $read );
		self::route( '/future/fidelity', WP_REST_Server::CREATABLE, 'rest_fidelity', $act, self::pair_args() );
		self::route( '/future/visual-diff', WP_REST_Server::CREATABLE, 'rest_visual_diff', $act, self::pair_args() );
		self::route( '/future/lineage', WP_REST_Server::READABLE, 'rest_lineage', $read, array( 'legacy_id' => self::id_arg() ) );
		self::route( '/future/risk', WP_REST_Server::READABLE, 'rest_risk', $read, array( 'legacy_id' => self::id_arg() ) );
		self::route( '/future/quarantine-advice', WP_REST_Server::CREATABLE, 'rest_quarantine_advice', $act, array( 'legacy_id' => self::id_arg() ) );
		self::route( '/future/shadow-read', WP_REST_Server::CREATABLE, 'rest_shadow_read', $act, self::pair_args() );
		self::route( '/future/canary', WP_REST_Server::CREATABLE, 'rest_canary', $act, array( 'requested_percent' => array( 'required' => true, 'type' => 'integer', 'enum' => array( 1, 5, 20, 50, 100 ) ) ) );
		self::route( '/future/invariants', WP_REST_Server::READABLE, 'rest_invariants', $read );
		self::route( '/future/receipt', WP_REST_Server::CREATABLE, 'rest_receipt', $act, array_merge( self::pair_args(), array( 'operation' => array( 'required' => true, 'type' => 'string', 'enum' => array( 'migration', 'reconciliation', 'rollback', 'cutover' ) ) ) ) );
		self::route( '/future/replay', WP_REST_Server::READABLE, 'rest_replay', $read, array( 'checkpoint_id' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) ) );
		self::route( '/future/blast-radius', WP_REST_Server::READABLE, 'rest_blast_radius', $read );
		self::route( '/future/redirect-observatory', WP_REST_Server::CREATABLE, 'rest_redirect_observatory', $act, $ids );
		self::route( '/future/unicode-fidelity', WP_REST_Server::CREATABLE, 'rest_unicode_fidelity', $act, self::pair_args() );
		self::route( '/future/gameday', WP_REST_Server::CREATABLE, 'rest_gameday', $act, array( 'environment' => array( 'required' => true, 'type' => 'string', 'enum' => array( 'disposable_staging' ) ) ) );
		self::route( '/future/retirement-confidence', WP_REST_Server::READABLE, 'rest_retirement_confidence', $read );
		self::route( '/future/mission-control', WP_REST_Server::READABLE, 'rest_mission_control', $read );
	}

	private static function route( $path, $methods, $callback, $permission, array $args = array() ) {
		register_rest_route( SNFLA_REST::NAMESPACE, $path, array(
			'methods' => $methods,
			'callback' => array( __CLASS__, $callback ),
			'permission_callback' => $permission,
			'args' => $args,
		) );
	}

	private static function id_arg() {
		return array( 'required' => true, 'type' => 'integer', 'minimum' => 1, 'sanitize_callback' => 'absint' );
	}

	private static function pair_args() {
		return array( 'legacy_id' => self::id_arg(), 'target_id' => self::id_arg() );
	}

	public static function permission_read( WP_REST_Request $request ) {
		unset( $request );
		$actor = SNFLA_Capabilities::current_read_actor( SNFLA_Capabilities::CAP_REVIEW );
		return is_wp_error( $actor ) ? $actor : true;
	}

	public static function permission_action( WP_REST_Request $request ) {
		if ( ! SNFLA_Retirement::mutations_allowed() ) {
			return new WP_Error( 'snfla_retired', 'The adapter is retired; Future18 evidence actions are disabled.', array( 'status' => 410 ) );
		}
		$nonce = SNFLA_Capabilities::verify_rest_nonce( $request );
		if ( is_wp_error( $nonce ) ) { return $nonce; }
		$actor = SNFLA_Capabilities::current_read_actor( SNFLA_Capabilities::CAP_REVIEW );
		return is_wp_error( $actor ) ? $actor : true;
	}

	private static function response( $code, $data ) {
		$response = rest_ensure_response( array(
			'ok' => ! is_wp_error( $data ),
			'code' => sanitize_key( $code ),
			'data' => is_wp_error( $data ) ? array( 'error' => $data->get_error_code(), 'message' => $data->get_error_message() ) : $data,
			'trace_id' => wp_generate_uuid4(),
		) );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
		return $response;
	}

	public static function rest_registry( WP_REST_Request $request ) { unset( $request ); return self::response( 'future18_registry', self::registry() ); }
	public static function rest_digital_twin( WP_REST_Request $request ) { return self::response( 'future18_digital_twin', self::digital_twin( (array) $request->get_param( 'legacy_ids' ), get_current_user_id() ) ); }
	public static function rest_contract_drift( WP_REST_Request $request ) { unset( $request ); return self::response( 'future18_contract_drift', self::contract_drift() ); }
	public static function rest_fidelity( WP_REST_Request $request ) { return self::response( 'future18_fidelity', self::content_fidelity( $request->get_param( 'legacy_id' ), $request->get_param( 'target_id' ) ) ); }
	public static function rest_visual_diff( WP_REST_Request $request ) { return self::response( 'future18_visual_diff', self::visual_diff( $request->get_param( 'legacy_id' ), $request->get_param( 'target_id' ) ) ); }
	public static function rest_lineage( WP_REST_Request $request ) { return self::response( 'future18_lineage', self::lineage_graph( $request->get_param( 'legacy_id' ) ) ); }
	public static function rest_risk( WP_REST_Request $request ) { return self::response( 'future18_risk', self::risk_score( $request->get_param( 'legacy_id' ) ) ); }
	public static function rest_quarantine_advice( WP_REST_Request $request ) { return self::response( 'future18_quarantine_advice', self::quarantine_advice( $request->get_param( 'legacy_id' ) ) ); }
	public static function rest_shadow_read( WP_REST_Request $request ) { return self::response( 'future18_shadow_read', self::shadow_read( $request->get_param( 'legacy_id' ), $request->get_param( 'target_id' ), get_current_user_id() ) ); }
	public static function rest_canary( WP_REST_Request $request ) { return self::response( 'future18_canary', self::canary_control( absint( $request->get_param( 'requested_percent' ) ), get_current_user_id() ) ); }
	public static function rest_invariants( WP_REST_Request $request ) { unset( $request ); return self::response( 'future18_invariants', self::invariant_guardian() ); }
	public static function rest_receipt( WP_REST_Request $request ) { return self::response( 'future18_receipt', self::create_receipt( $request->get_param( 'legacy_id' ), $request->get_param( 'target_id' ), $request->get_param( 'operation' ), get_current_user_id() ) ); }
	public static function rest_replay( WP_REST_Request $request ) { return self::response( 'future18_replay', self::replay_checkpoint( $request->get_param( 'checkpoint_id' ) ) ); }
	public static function rest_blast_radius( WP_REST_Request $request ) { unset( $request ); return self::response( 'future18_blast_radius', self::blast_radius() ); }
	public static function rest_redirect_observatory( WP_REST_Request $request ) { return self::response( 'future18_redirect_observatory', self::redirect_observatory( (array) $request->get_param( 'legacy_ids' ) ) ); }
	public static function rest_unicode_fidelity( WP_REST_Request $request ) { return self::response( 'future18_unicode_fidelity', self::unicode_fidelity( $request->get_param( 'legacy_id' ), $request->get_param( 'target_id' ) ) ); }
	public static function rest_gameday( WP_REST_Request $request ) { return self::response( 'future18_gameday', self::gameday( (string) $request->get_param( 'environment' ), get_current_user_id() ) ); }
	public static function rest_retirement_confidence( WP_REST_Request $request ) { unset( $request ); return self::response( 'future18_retirement_confidence', self::retirement_confidence() ); }
	public static function rest_mission_control( WP_REST_Request $request ) { unset( $request ); return self::response( 'future18_mission_control', self::mission_control() ); }

	/** F04-FUT-001 — deterministic, non-mutating migration simulation. */
	public static function digital_twin( array $legacy_ids, $actor_id = 0 ) {
		$legacy_ids = SNFLA_Integrity::normalized_ids( $legacy_ids, self::MAX_IDS );
		if ( empty( $legacy_ids ) ) { return new WP_Error( 'snfla_twin_empty', 'Select at least one legacy publication.', array( 'status' => 400 ) ); }
		if ( ! SNFLA_Inventory::unchanged() ) { return new WP_Error( 'snfla_twin_inventory_changed', 'The locked legacy inventory changed; rebuild the source lock before simulation.', array( 'status' => 409 ) ); }
		$locked = SNFLA_Inventory::locked();
		$rows = array();
		$totals = array( 'candidate_count' => 0, 'eligible_count' => 0, 'conflict_count' => 0, 'already_migrated' => 0 );
		$sample = array();
		foreach ( $legacy_ids as $legacy_id ) {
			$post = get_post( $legacy_id );
			if ( ! $post instanceof WP_Post || SNFLA_Inventory::LEGACY_POST_TYPE !== $post->post_type ) { return new WP_Error( 'snfla_twin_invalid_source', 'A requested legacy record is unavailable.', array( 'status' => 404, 'legacy_id' => $legacy_id ) ); }
			$conflicts = SNFLA_Migration::candidate_conflicts( $post, $legacy_id );
			$target_id = SNFLA_File21_Adapter::target_for( $legacy_id );
			$risk = self::risk_score( $legacy_id );
			$disposition = $target_id > 0 ? 'already_migrated' : ( empty( $conflicts ) ? 'create' : 'quarantine' );
			$rows[] = array(
				'legacy_id' => $legacy_id,
				'source_checksum' => SNFLA_Checksum::post( $legacy_id ),
				'target_id' => $target_id,
				'disposition' => $disposition,
				'conflict_codes' => array_values( array_map( 'sanitize_key', (array) $conflicts ) ),
				'risk' => is_wp_error( $risk ) ? array( 'score' => 100, 'tier' => 'critical', 'reason' => $risk->get_error_code() ) : $risk,
			);
			$totals['candidate_count']++;
			if ( $target_id > 0 ) { $totals['already_migrated']++; }
			elseif ( empty( $conflicts ) ) { $totals['eligible_count']++; }
			else { $totals['conflict_count']++; }
			if ( count( $sample ) < 20 ) { $sample[] = array( 'legacy_id' => $legacy_id, 'source_checksum' => SNFLA_Checksum::post( $legacy_id ), 'conflict_codes' => $conflicts ); }
		}
		$planning = method_exists( 'SNFLA_Plan_Completion', 'dry_run_estimates' ) ? SNFLA_Plan_Completion::dry_run_estimates( $sample, $totals ) : array();
		if ( is_wp_error( $planning ) ) { $planning = array( 'available' => false, 'reason' => $planning->get_error_code() ); }
		$external = apply_filters( 'snfla_future18_digital_twin_provider_v1', array( 'verified' => false ), array( 'rows' => $rows, 'totals' => $totals, 'planning' => $planning, 'source_signature' => $locked['source_signature'] ?? '' ) );
		$twin = array(
			'schema' => 1,
			'feature_id' => 'F04-FUT-001',
			'created_at_utc' => gmdate( 'Y-m-d H:i:s' ),
			'source_signature' => (string) ( $locked['source_signature'] ?? '' ),
			'non_mutating' => true,
			'canonical_owner' => 'File 21',
			'rows' => $rows,
			'totals' => $totals,
			'planning' => SNFLA_Audit::redact( $planning ),
			'external_simulation' => SNFLA_Audit::redact( is_array( $external ) ? $external : array() ),
		);
		$twin['twin_checksum'] = SNFLA_Checksum::hash( $twin );
		update_option( self::TWIN_OPTION, $twin, false );
		$checkpoint = self::store_checkpoint( 'digital_twin', $twin );
		if ( $actor_id > 0 ) { SNFLA_Audit::record( 'future18_digital_twin_completed', $actor_id, array( 'twin_checksum' => $twin['twin_checksum'], 'count' => count( $rows ), 'checkpoint_id' => $checkpoint['checkpoint_id'] ?? '' ), 'future18-twin:' . $twin['twin_checksum'] ); }
		$twin['checkpoint'] = $checkpoint;
		return $twin;
	}

	/** F04-FUT-002 — version/fingerprint drift detection across canonical contracts. */
	public static function contract_drift() {
		$manifest = SNFLA_Central_Plan::module_manifest();
		$descriptors = array(
			'File 00' => apply_filters( 'sabri_file00_contract_descriptor_v1', array( 'verified' => false, 'status' => 'unavailable' ), array( 'consumer' => 'File 04', 'purpose' => 'identity_authority' ) ),
			'File 21' => apply_filters( 'sabri_file21_contract_descriptor_v1', array( 'verified' => false, 'status' => 'unavailable' ), array( 'consumer' => 'File 04', 'purpose' => 'canonical_publication_migration' ) ),
			'File 26' => apply_filters( 'sabri_file26_contract_descriptor_v1', array( 'verified' => false, 'status' => 'unavailable' ), array( 'consumer' => 'File 04', 'purpose' => 'legacy_resolution_search_handoff' ) ),
		);
		$normalized = SNFLA_Checksum::canonicalize( SNFLA_Audit::redact( $descriptors ) );
		$fingerprint = SNFLA_Checksum::hash( array( 'manifest' => $manifest, 'contracts' => $normalized ) );
		$previous = get_option( self::DRIFT_OPTION, array() );
		$changed = ! empty( $previous['fingerprint'] ) && ! hash_equals( (string) $previous['fingerprint'], $fingerprint );
		$unverified = array();
		foreach ( $descriptors as $owner => $descriptor ) { if ( ! is_array( $descriptor ) || empty( $descriptor['verified'] ) ) { $unverified[] = $owner; } }
		$result = array(
			'feature_id' => 'F04-FUT-002', 'checked_at_utc' => gmdate( 'Y-m-d H:i:s' ), 'fingerprint' => $fingerprint,
			'changed_since_last_verified_snapshot' => $changed, 'unverified_contracts' => $unverified,
			'block_mutation' => $changed || ! empty( $unverified ), 'descriptors' => $normalized,
		);
		update_option( self::DRIFT_OPTION, $result, false );
		return $result;
	}

	/** F04-FUT-003 — exact textual/provenance fidelity plus optional semantic provider. */
	public static function content_fidelity( $legacy_id, $target_id ) {
		$legacy_id = absint( $legacy_id ); $target_id = absint( $target_id );
		$source = get_post( $legacy_id ); $target = get_post( $target_id );
		if ( ! $source instanceof WP_Post || SNFLA_Inventory::LEGACY_POST_TYPE !== $source->post_type || ! $target instanceof WP_Post ) { return new WP_Error( 'snfla_fidelity_record_missing', 'Both source and canonical target must exist.', array( 'status' => 404 ) ); }
		if ( ! SNFLA_File21_Adapter::migration_target_valid( $legacy_id, $target_id ) ) { return new WP_Error( 'snfla_fidelity_target_invalid', 'The target is not a verified File 21 migration target for this source.', array( 'status' => 412 ) ); }
		$fields = array(
			'title' => array( self::normalize_text( $source->post_title ), self::normalize_text( $target->post_title ) ),
			'content' => array( self::normalize_text( $source->post_content ), self::normalize_text( $target->post_content ) ),
			'excerpt' => array( self::normalize_text( $source->post_excerpt ), self::normalize_text( $target->post_excerpt ) ),
		);
		$matches = array(); $matched = 0;
		foreach ( $fields as $name => $pair ) { $ok = hash_equals( hash( 'sha256', $pair[0] ), hash( 'sha256', $pair[1] ) ); $matches[ $name ] = $ok; if ( $ok ) { $matched++; } }
		$provider = apply_filters( 'snfla_semantic_fidelity_provider_v1', array( 'verified' => false ), array(
			'legacy_id' => $legacy_id, 'target_id' => $target_id,
			'source' => array( 'title' => $source->post_title, 'content' => $source->post_content, 'excerpt' => $source->post_excerpt ),
			'target' => array( 'title' => $target->post_title, 'content' => $target->post_content, 'excerpt' => $target->post_excerpt ),
		) );
		$semantic_verified = is_array( $provider ) && ! empty( $provider['verified'] ) && isset( $provider['score'] ) && is_numeric( $provider['score'] );
		return array(
			'feature_id' => 'F04-FUT-003', 'legacy_id' => $legacy_id, 'target_id' => $target_id,
			'exact_field_matches' => $matches, 'exact_match_ratio' => $matched / max( 1, count( $matches ) ),
			'projection_equivalent' => SNFLA_Checksum::migration_equivalent( $legacy_id, $target_id ),
			'semantic_provider_verified' => $semantic_verified,
			'semantic_score' => $semantic_verified ? max( 0.0, min( 1.0, (float) $provider['score'] ) ) : null,
			'human_review_required' => ! $semantic_verified || $matched !== count( $matches ),
			'provider_evidence' => SNFLA_Audit::redact( is_array( $provider ) ? $provider : array() ),
		);
	}

	/** F04-FUT-004 — delegates rendering evidence to shell/visual owners; never renders a parallel public UI. */
	public static function visual_diff( $legacy_id, $target_id ) {
		$legacy_id = absint( $legacy_id ); $target_id = absint( $target_id );
		if ( ! SNFLA_File21_Adapter::migration_target_valid( $legacy_id, $target_id ) ) { return new WP_Error( 'snfla_visual_target_invalid', 'A verified File 21 target is required.', array( 'status' => 412 ) ); }
		$source_signature = (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' );
		$request = array( 'feature_id' => 'F04-FUT-004', 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'required' => array( 'desktop', 'mobile', 'rtl', 'keyboard', 'zoom_200', 'reduced_motion', 'dom_semantics', 'accessibility_tree' ), 'source_signature' => $source_signature );
		$request['request_digest'] = SNFLA_Checksum::hash( $request );
		$evidence = apply_filters( 'snfla_visual_migration_diff_provider_v1', array( 'verified' => false, 'owner' => 'File 20/File 25' ), $request );
		$verified = is_array( $evidence ) && ! empty( $evidence['verified'] ) && ! empty( $evidence['provider_id'] ) && isset( $evidence['diff_count'] );
		return array( 'feature_id' => 'F04-FUT-004', 'verified' => $verified, 'release_blocking' => ! $verified || absint( $evidence['critical_diff_count'] ?? 0 ) > 0, 'evidence' => SNFLA_Audit::redact( is_array( $evidence ) ? $evidence : array() ) );
	}

	/** F04-FUT-005 — privacy-minimized source→identity/media/interaction→target lineage. */
	public static function lineage_graph( $legacy_id ) {
		$legacy_id = absint( $legacy_id ); $post = get_post( $legacy_id );
		if ( ! $post instanceof WP_Post || SNFLA_Inventory::LEGACY_POST_TYPE !== $post->post_type ) { return new WP_Error( 'snfla_lineage_source_missing', 'Legacy source is unavailable.', array( 'status' => 404 ) ); }
		$author = SNFLA_Plan_Completion::authorship_preflight( $legacy_id, $post->post_author );
		$media = SNFLA_Plan_Completion::media_preflight( $legacy_id );
		$target_id = SNFLA_File21_Adapter::target_for( $legacy_id );
		$map = SNFLA_Mapping::get_checked( $legacy_id );
		$nodes = array(
			array( 'id' => 'legacy:' . $legacy_id, 'type' => 'legacy_publication', 'checksum' => SNFLA_Checksum::post( $legacy_id ) ),
			array( 'id' => 'author', 'type' => 'platform_identity', 'platform_uuid_digest' => ! is_wp_error( $author ) && ! empty( $author['platform_uuid'] ) ? hash( 'sha256', (string) $author['platform_uuid'] ) : '', 'verified' => ! is_wp_error( $author ) ),
			array( 'id' => 'media', 'type' => 'reference_manifest', 'reference_count' => ! is_wp_error( $media ) ? absint( $media['reference_count'] ?? 0 ) : 0, 'verified' => ! is_wp_error( $media ) ),
			array( 'id' => 'target:' . $target_id, 'type' => 'file21_canonical_target', 'target_id' => $target_id, 'verified' => $target_id > 0 && SNFLA_File21_Adapter::migration_target_valid( $legacy_id, $target_id ) ),
		);
		$edges = array(
			array( 'from' => 'legacy:' . $legacy_id, 'to' => 'author', 'relation' => 'authored_by' ),
			array( 'from' => 'legacy:' . $legacy_id, 'to' => 'media', 'relation' => 'references' ),
			array( 'from' => 'legacy:' . $legacy_id, 'to' => 'target:' . $target_id, 'relation' => 'migrates_to' ),
		);
		return array( 'feature_id' => 'F04-FUT-005', 'legacy_id' => $legacy_id, 'nodes' => $nodes, 'edges' => $edges, 'mapping' => SNFLA_Audit::redact( is_wp_error( $map ) ? array( 'error' => $map->get_error_code() ) : $map ), 'privacy_minimized' => true, 'graph_checksum' => SNFLA_Checksum::hash( array( 'nodes' => $nodes, 'edges' => $edges ) ) );
	}

	/** F04-FUT-006 — deterministic advisory risk; never authorizes migration by itself. */
	public static function risk_score( $legacy_id ) {
		$legacy_id = absint( $legacy_id ); $post = get_post( $legacy_id );
		if ( ! $post instanceof WP_Post || SNFLA_Inventory::LEGACY_POST_TYPE !== $post->post_type ) { return new WP_Error( 'snfla_risk_source_missing', 'Legacy source is unavailable.', array( 'status' => 404 ) ); }
		$reasons = array(); $score = 0;
		$conflicts = SNFLA_Migration::candidate_conflicts( $post, $legacy_id );
		foreach ( array_values( array_unique( array_map( 'sanitize_key', (array) $conflicts ) ) ) as $code ) {
			$weight = 8;
			if ( false !== strpos( $code, 'consent' ) || false !== strpos( $code, 'author' ) ) { $weight = 20; }
			elseif ( false !== strpos( $code, 'media' ) || false !== strpos( $code, 'attachment' ) || false !== strpos( $code, 'reference' ) ) { $weight = 15; }
			elseif ( false !== strpos( $code, 'already_migrated' ) ) { $weight = 3; }
			$score += $weight; $reasons[] = array( 'code' => $code, 'weight' => $weight );
		}
		$terms = wp_get_object_terms( $legacy_id, SNFLA_Inventory::LEGACY_TAXONOMY, array( 'fields' => 'slugs' ) );
		if ( is_array( $terms ) && in_array( 'patient-cases', $terms, true ) ) { $score += 20; $reasons[] = array( 'code' => 'sensitive_patient_case', 'weight' => 20 ); }
		if ( SNFLA_File21_Adapter::target_for( $legacy_id ) > 0 ) { $score += 5; $reasons[] = array( 'code' => 'existing_canonical_target', 'weight' => 5 ); }
		$score = min( 100, $score );
		$tier = $score >= 75 ? 'critical' : ( $score >= 50 ? 'high' : ( $score >= 25 ? 'medium' : 'low' ) );
		return array( 'feature_id' => 'F04-FUT-006', 'legacy_id' => $legacy_id, 'score' => $score, 'tier' => $tier, 'reasons' => $reasons, 'advisory_only' => true, 'human_review_required' => $score >= 50 );
	}

	/** F04-FUT-007 — deterministic remediation plus optional AI advisory; never approves/publishes. */
	public static function quarantine_advice( $legacy_id ) {
		$risk = self::risk_score( $legacy_id ); if ( is_wp_error( $risk ) ) { return $risk; }
		$remediation = array();
		foreach ( $risk['reasons'] as $reason ) {
			$code = $reason['code'];
			if ( false !== strpos( $code, 'author' ) ) { $remediation[] = 'Resolve immutable File 00 platform identity or governed placeholder evidence.'; }
			elseif ( false !== strpos( $code, 'consent' ) ) { $remediation[] = 'Obtain/verify governed patient-case consent and privacy disposition before migration.'; }
			elseif ( false !== strpos( $code, 'media' ) || false !== strpos( $code, 'attachment' ) || false !== strpos( $code, 'reference' ) ) { $remediation[] = 'Repair or attest File 21 media ownership/rights/alt/dedup/broken-link coverage.'; }
			else { $remediation[] = 'Resolve the underlying conflict and rerun deterministic dry-run/reconciliation evidence.'; }
		}
		$remediation = array_values( array_unique( $remediation ) );
		$context = array( 'legacy_id' => absint( $legacy_id ), 'risk' => $risk, 'deterministic_remediation' => $remediation );
		$ai = apply_filters( 'snfla_ai_quarantine_advisor_v1', array( 'verified' => false, 'advisory_only' => true ), SNFLA_Audit::redact( $context ) );
		return array( 'feature_id' => 'F04-FUT-007', 'legacy_id' => absint( $legacy_id ), 'risk' => $risk, 'deterministic_remediation' => $remediation, 'ai_advice' => SNFLA_Audit::redact( is_array( $ai ) ? $ai : array() ), 'ai_can_approve' => false, 'ai_can_publish' => false, 'human_decision_required' => true );
	}

	/** F04-FUT-008 — compares read projections; contains no write path or dual-write behavior. */
	public static function shadow_read( $legacy_id, $target_id, $actor_id = 0 ) {
		$legacy_id = absint( $legacy_id ); $target_id = absint( $target_id );
		$fidelity = self::content_fidelity( $legacy_id, $target_id ); if ( is_wp_error( $fidelity ) ) { return $fidelity; }
		$provider = apply_filters( 'sabri_file21_shadow_read_v1', array( 'verified' => false ), array( 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'read_only' => true ) );
		$result = array( 'feature_id' => 'F04-FUT-008', 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'read_only' => true, 'dual_write' => false, 'fidelity' => $fidelity, 'provider' => SNFLA_Audit::redact( is_array( $provider ) ? $provider : array() ), 'checked_at_utc' => gmdate( 'Y-m-d H:i:s' ) );
		$result['shadow_checksum'] = SNFLA_Checksum::hash( $result );
		update_option( self::SHADOW_OPTION, $result, false );
		if ( $actor_id > 0 ) { SNFLA_Audit::record( 'future18_shadow_read_completed', $actor_id, array( 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'shadow_checksum' => $result['shadow_checksum'] ), 'future18-shadow:' . $result['shadow_checksum'] ); }
		return $result;
	}

	/** F04-FUT-009 — staged recommendation/controller; it never calls migrate(). */
	public static function canary_control( $requested_percent, $actor_id = 0 ) {
		$allowed = array( 1, 5, 20, 50, 100 ); $requested_percent = absint( $requested_percent );
		if ( ! in_array( $requested_percent, $allowed, true ) ) { return new WP_Error( 'snfla_canary_phase_invalid', 'Allowed canary phases are 1, 5, 20, 50 and 100 percent.', array( 'status' => 400 ) ); }
		$invariants = self::invariant_guardian();
		$drift = self::contract_drift();
		$metrics = method_exists( 'SNFLA_Plan_Completion', 'metrics_summary' ) ? SNFLA_Plan_Completion::metrics_summary() : array();
		$error_rate = isset( $metrics['error_rate'] ) && is_numeric( $metrics['error_rate'] ) ? (float) $metrics['error_rate'] : null;
		$blockers = array();
		if ( ! empty( $invariants['blockers'] ) ) { $blockers[] = 'migration_invariant_failure'; }
		if ( ! empty( $drift['block_mutation'] ) ) { $blockers[] = 'contract_drift_or_unverified_contract'; }
		if ( null === $error_rate ) { $blockers[] = 'observability_error_rate_missing'; }
		elseif ( $error_rate > 0.01 ) { $blockers[] = 'error_rate_above_one_percent'; }
		$current = get_option( self::CANARY_OPTION, array( 'approved_percent' => 0 ) );
		$current_percent = absint( $current['approved_percent'] ?? 0 );
		$current_index = array_search( $current_percent, array_merge( array( 0 ), $allowed ), true );
		$request_index = array_search( $requested_percent, $allowed, true );
		$max_next = 0 === $current_percent ? 1 : ( isset( $allowed[ min( count( $allowed ) - 1, (int) $current_index ) ] ) ? $allowed[ min( count( $allowed ) - 1, (int) $current_index ) ] : $current_percent );
		if ( $current_percent > 0 ) { $idx = array_search( $current_percent, $allowed, true ); $max_next = false === $idx ? $current_percent : $allowed[ min( count( $allowed ) - 1, $idx + 1 ) ]; }
		if ( $requested_percent > $max_next ) { $blockers[] = 'canary_phase_skip_forbidden'; }
		$approved = empty( $blockers );
		$decision = array( 'feature_id' => 'F04-FUT-009', 'requested_percent' => $requested_percent, 'previous_percent' => $current_percent, 'approved' => $approved, 'approved_percent' => $approved ? $requested_percent : $current_percent, 'blockers' => array_values( array_unique( $blockers ) ), 'controller_only' => true, 'migration_invoked' => false, 'decided_at_utc' => gmdate( 'Y-m-d H:i:s' ) );
		if ( $approved ) { update_option( self::CANARY_OPTION, $decision, false ); }
		if ( $actor_id > 0 ) { SNFLA_Audit::record( 'future18_canary_decision', $actor_id, $decision, 'future18-canary:' . $requested_percent . ':' . gmdate( 'YmdHis' ) ); }
		return $decision;
	}

	/** F04-FUT-010 — fail-closed cross-invariant guardian. */
	public static function invariant_guardian() {
		$manifest = SNFLA_Central_Plan::module_manifest();
		$audit = SNFLA_Audit::verify_chain();
		$checks = array(
			'legacy_writes_forbidden' => 'forbidden' === ( $manifest['legacy_writes'] ?? '' ),
			'canonical_public_owner_file21' => 'File 21' === ( $manifest['canonical_public_owner'] ?? '' ),
			'canonical_search_owner_file26' => 'File 26' === ( $manifest['canonical_search_owner'] ?? '' ),
			'inventory_unchanged' => SNFLA_Inventory::unchanged(),
			'audit_chain_valid' => ! empty( $audit['valid'] ),
			'open_conflicts_zero' => 0 === SNFLA_Mapping::open_conflict_count(),
		);
		$blockers = array(); foreach ( $checks as $name => $ok ) { if ( ! $ok ) { $blockers[] = $name; } }
		return array( 'feature_id' => 'F04-FUT-010', 'checked_at_utc' => gmdate( 'Y-m-d H:i:s' ), 'checks' => $checks, 'blockers' => $blockers, 'green' => empty( $blockers ), 'fail_closed' => true );
	}

	public static function scheduled_invariant_guardian() {
		$result = self::invariant_guardian();
		if ( empty( $result['green'] ) ) { do_action( 'snfla_operational_alert_v1', 'future18_invariant_guardian_failed', 'blocker', SNFLA_Audit::redact( $result ) ); }
	}

	/** F04-FUT-011 — tamper-evident migration/reconciliation/rollback/cutover receipt. */
	public static function create_receipt( $legacy_id, $target_id, $operation, $actor_id ) {
		$legacy_id = absint( $legacy_id ); $target_id = absint( $target_id ); $operation = sanitize_key( $operation );
		if ( ! in_array( $operation, array( 'migration', 'reconciliation', 'rollback', 'cutover' ), true ) ) { return new WP_Error( 'snfla_receipt_operation_invalid', 'Unsupported receipt operation.', array( 'status' => 400 ) ); }
		$source_checksum = $legacy_id > 0 ? SNFLA_Checksum::post( $legacy_id ) : '';
		$target_checksum = $target_id > 0 ? SNFLA_Checksum::migration_projection_checksum( $target_id, false ) : '';
		$audit = SNFLA_Audit::verify_chain();
		$receipt = array(
			'schema' => 1, 'feature_id' => 'F04-FUT-011', 'receipt_id' => wp_generate_uuid4(), 'operation' => $operation,
			'legacy_id' => $legacy_id, 'target_id' => $target_id, 'source_checksum' => $source_checksum, 'target_checksum' => $target_checksum,
			'source_signature' => (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' ), 'audit_valid' => ! empty( $audit['valid'] ),
			'actor_digest' => SNFLA_Audit::actor_digest( $actor_id ), 'created_at_utc' => gmdate( 'Y-m-d H:i:s' ), 'contains_raw_personal_data' => false,
		);
		$receipt['receipt_checksum'] = SNFLA_Checksum::hash( $receipt );
		$receipt = SNFLA_Integrity::sign_evidence( $receipt );
		$all = get_option( self::RECEIPTS_OPTION, array() ); if ( ! is_array( $all ) ) { $all = array(); }
		$all[] = $receipt; if ( count( $all ) > self::MAX_RECEIPTS ) { $all = array_slice( $all, -self::MAX_RECEIPTS ); }
		update_option( self::RECEIPTS_OPTION, $all, false );
		SNFLA_Audit::record( 'future18_receipt_created', $actor_id, array( 'receipt_id' => $receipt['receipt_id'], 'receipt_checksum' => $receipt['receipt_checksum'], 'operation' => $operation ), 'future18-receipt:' . $receipt['receipt_id'] );
		return $receipt;
	}

	/** F04-FUT-012 — stores/verifies snapshots; replay never mutates either source or File 21. */
	private static function store_checkpoint( $kind, array $snapshot ) {
		$checkpoint = array( 'checkpoint_id' => wp_generate_uuid4(), 'kind' => sanitize_key( $kind ), 'created_at_utc' => gmdate( 'Y-m-d H:i:s' ), 'snapshot' => SNFLA_Audit::redact( $snapshot ), 'non_mutating' => true );
		$checkpoint['checkpoint_checksum'] = SNFLA_Checksum::hash( $checkpoint );
		$list = get_option( self::CHECKPOINTS_OPTION, array() ); if ( ! is_array( $list ) ) { $list = array(); }
		$list[] = $checkpoint; if ( count( $list ) > self::MAX_CHECKPOINTS ) { $list = array_slice( $list, -self::MAX_CHECKPOINTS ); }
		update_option( self::CHECKPOINTS_OPTION, $list, false ); return array( 'checkpoint_id' => $checkpoint['checkpoint_id'], 'checkpoint_checksum' => $checkpoint['checkpoint_checksum'] );
	}

	public static function replay_checkpoint( $checkpoint_id ) {
		$checkpoint_id = sanitize_text_field( (string) $checkpoint_id ); $list = get_option( self::CHECKPOINTS_OPTION, array() );
		foreach ( array_reverse( is_array( $list ) ? $list : array() ) as $checkpoint ) {
			if ( ! is_array( $checkpoint ) || ! hash_equals( $checkpoint_id, (string) ( $checkpoint['checkpoint_id'] ?? '' ) ) ) { continue; }
			$expected = (string) ( $checkpoint['checkpoint_checksum'] ?? '' ); $payload = $checkpoint; unset( $payload['checkpoint_checksum'] );
			$valid = preg_match( '/^[a-f0-9]{64}$/', $expected ) && hash_equals( $expected, SNFLA_Checksum::hash( $payload ) );
			return array( 'feature_id' => 'F04-FUT-012', 'verified' => (bool) $valid, 'replayed' => (bool) $valid, 'mutations_performed' => false, 'checkpoint' => $valid ? $checkpoint : array( 'checkpoint_id' => $checkpoint_id ) );
		}
		return new WP_Error( 'snfla_checkpoint_not_found', 'The requested Future18 checkpoint was not found.', array( 'status' => 404 ) );
	}

	/** F04-FUT-013 — dependency ownership and provider blast-radius evidence. */
	public static function blast_radius() {
		$manifest = SNFLA_Central_Plan::module_manifest();
		$base = array(
			'File 00' => array( 'impact' => 'identity/current-action capability', 'mutation_owner' => false ),
			'File 20' => array( 'impact' => 'admin shell/layout mount', 'mutation_owner' => false ),
			'File 21' => array( 'impact' => 'canonical posts/media/interactions/routes', 'mutation_owner' => true ),
			'File 24' => array( 'impact' => 'assurance/recovery evidence coordination', 'mutation_owner' => false ),
			'File 25' => array( 'impact' => 'visual tokens/components', 'mutation_owner' => false ),
			'File 26' => array( 'impact' => 'search index/legacy resolution', 'mutation_owner' => true ),
		);
		$provider = apply_filters( 'snfla_dependency_blast_radius_v1', array( 'verified' => false ), array( 'manifest' => $manifest, 'base_dependencies' => $base, 'source_signature' => SNFLA_Inventory::locked()['source_signature'] ?? '' ) );
		return array( 'feature_id' => 'F04-FUT-013', 'dependencies' => $base, 'provider_evidence' => SNFLA_Audit::redact( is_array( $provider ) ? $provider : array() ), 'read_only_analysis' => true, 'canonical_owners_unchanged' => true );
	}

	/** F04-FUT-014 — bounded URL/redirect/citation continuity verification. */
	public static function redirect_observatory( array $legacy_ids ) {
		$legacy_ids = SNFLA_Integrity::normalized_ids( $legacy_ids, self::MAX_IDS ); if ( empty( $legacy_ids ) ) { return new WP_Error( 'snfla_redirect_observatory_empty', 'Select one or more legacy IDs.', array( 'status' => 400 ) ); }
		$rows = array();
		foreach ( $legacy_ids as $legacy_id ) {
			$target_id = SNFLA_File21_Adapter::target_for( $legacy_id );
			$target_valid = $target_id > 0 && SNFLA_File21_Adapter::migration_target_valid( $legacy_id, $target_id );
			$target_url = $target_valid ? get_permalink( $target_id ) : false;
			$rows[] = array( 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'target_valid' => $target_valid, 'canonical_url_digest' => is_string( $target_url ) && '' !== $target_url ? hash( 'sha256', $target_url ) : '', 'broken' => ! $target_valid || ! is_string( $target_url ) || '' === $target_url );
		}
		$source_signature = (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' );
		$request = array( 'rows' => $rows, 'checks' => array( '301_or_410', 'redirect_loop', 'redirect_chain', 'query_preservation', 'fragment_preservation', 'external_citation_continuity' ), 'source_signature' => $source_signature );
		$request['request_digest'] = SNFLA_Checksum::hash( $request );
		$provider = apply_filters( 'snfla_redirect_citation_observatory_v1', array( 'verified' => false ), $request );
		$broken = count( array_filter( $rows, static function ( $row ) { return ! empty( $row['broken'] ); } ) );
		return array( 'feature_id' => 'F04-FUT-014', 'count' => count( $rows ), 'broken_count' => $broken, 'rows' => $rows, 'provider_evidence' => SNFLA_Audit::redact( is_array( $provider ) ? $provider : array() ), 'release_blocking' => $broken > 0 );
	}

	/** F04-FUT-015 — Arabic/Urdu codepoint/bidi/diacritic fidelity without silent normalization. */
	public static function unicode_fidelity( $legacy_id, $target_id ) {
		$legacy_id = absint( $legacy_id ); $target_id = absint( $target_id ); $source = get_post( $legacy_id ); $target = get_post( $target_id );
		if ( ! $source instanceof WP_Post || ! $target instanceof WP_Post ) { return new WP_Error( 'snfla_unicode_record_missing', 'Both source and target are required.', array( 'status' => 404 ) ); }
		$source_text = (string) $source->post_title . "\n" . (string) $source->post_content; $target_text = (string) $target->post_title . "\n" . (string) $target->post_content;
		$source_profile = self::unicode_profile( $source_text ); $target_profile = self::unicode_profile( $target_text );
		$checks = array(
			'valid_utf8' => ! empty( $source_profile['valid_utf8'] ) && ! empty( $target_profile['valid_utf8'] ),
			'arabic_codepoint_count_preserved' => $source_profile['arabic_count'] === $target_profile['arabic_count'],
			'diacritic_count_preserved' => $source_profile['mark_count'] === $target_profile['mark_count'],
			'bidi_control_count_preserved' => $source_profile['bidi_control_count'] === $target_profile['bidi_control_count'],
			'raw_text_hash_preserved' => hash_equals( $source_profile['sha256'], $target_profile['sha256'] ),
		);
		$failed = array(); foreach ( $checks as $name => $ok ) { if ( ! $ok ) { $failed[] = $name; } }
		return array( 'feature_id' => 'F04-FUT-015', 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'checks' => $checks, 'failed' => $failed, 'source_profile' => $source_profile, 'target_profile' => $target_profile, 'silent_normalization_performed' => false, 'human_review_required' => ! empty( $failed ) );
	}

	private static function unicode_profile( $text ) {
		$valid = 1 === preg_match( '//u', $text );
		$ar = $marks = $bidi = 0;
		if ( $valid ) {
			preg_match_all( '/\p{Arabic}/u', $text, $m ); $ar = count( $m[0] );
			preg_match_all( '/\p{M}/u', $text, $m ); $marks = count( $m[0] );
			preg_match_all( '/[\x{061C}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $text, $m ); $bidi = count( $m[0] );
		}
		return array( 'valid_utf8' => $valid, 'bytes' => strlen( $text ), 'arabic_count' => $ar, 'mark_count' => $marks, 'bidi_control_count' => $bidi, 'sha256' => hash( 'sha256', $text ) );
	}

	/** F04-FUT-016 — requires a disposable provider; production chaos is refused. */
	public static function gameday( $environment, $actor_id ) {
		$environment = sanitize_key( $environment );
		if ( 'disposable_staging' !== $environment ) { return new WP_Error( 'snfla_gameday_environment_forbidden', 'GameDay is allowed only in a disposable staging environment.', array( 'status' => 400 ) ); }
		$source_signature = (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' );
		$request = array( 'feature_id' => 'F04-FUT-016', 'environment' => $environment, 'required_exercises' => array( 'backup_restore', 'migration', 'provider_outage', 'queue_retry', 'cache_rebuild', 'search_reindex', 'rollback', 'reconciliation' ), 'production_chaos_allowed' => false, 'source_signature' => $source_signature );
		$request['request_digest'] = SNFLA_Checksum::hash( $request );
		$evidence = apply_filters( 'snfla_disaster_recovery_gameday_v1', array( 'verified' => false ), $request );
		$verified = is_array( $evidence ) && ! empty( $evidence['verified'] ) && ! empty( $evidence['provider_id'] ) && empty( $evidence['production_environment'] );
		$result = array( 'feature_id' => 'F04-FUT-016', 'verified' => $verified, 'environment' => $environment, 'production_chaos_allowed' => false, 'source_signature' => $source_signature, 'request_digest' => $request['request_digest'], 'evidence' => SNFLA_Audit::redact( is_array( $evidence ) ? $evidence : array() ), 'performed_at_utc' => gmdate( 'Y-m-d H:i:s' ) );
		if ( $verified ) { update_option( self::GAMEDAY_OPTION, SNFLA_Integrity::sign_evidence( $result ), false ); SNFLA_Audit::record( 'future18_gameday_verified', $actor_id, array( 'provider_id_digest' => hash( 'sha256', (string) $evidence['provider_id'] ) ), 'future18-gameday:' . gmdate( 'Ymd' ) ); }
		return $result;
	}

	/** F04-FUT-017 — evidence-based advisory retirement score; Founder authority remains mandatory. */
	public static function retirement_confidence() {
		$reconciliation = SNFLA_Reconciliation::report(); $rollback = SNFLA_Rollback::proof(); $invariants = self::invariant_guardian(); $drift = self::contract_drift();
		$system = method_exists( 'SNFLA_Plan_Completion', 'system_check' ) ? SNFLA_Plan_Completion::system_check() : array();
		$gameday = get_option( self::GAMEDAY_OPTION, array() );
		$gates = array(
			'legacy_writes_disabled' => empty( $invariants['checks']['legacy_writes_forbidden'] ) ? false : true,
			'zero_open_conflicts' => 0 === SNFLA_Mapping::open_conflict_count(),
			'fresh_green_reconciliation' => ! empty( $reconciliation['green'] ) && SNFLA_Reconciliation::validate_current_report( $reconciliation ),
			'rollback_proof_valid' => SNFLA_Integrity::evidence_valid( $rollback ),
			'backup_restore_valid' => SNFLA_Migration::backup_proof_valid(),
			'contract_drift_clear' => empty( $drift['block_mutation'] ),
			'file26_integration_accepted' => isset( $system['file26']['status'] ) && 'pass' === $system['file26']['status'],
			'operational_metrics_present' => isset( $system['metrics']['status'] ) && 'pass' === $system['metrics']['status'],
			'disaster_recovery_gameday_verified' => is_array( $gameday ) && SNFLA_Integrity::evidence_valid( $gameday ) && ! empty( $gameday['verified'] ) && ! empty( $gameday['source_signature'] ) && hash_equals( (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' ), (string) $gameday['source_signature'] ),
			'invariant_guardian_green' => ! empty( $invariants['green'] ),
		);
		$passed = count( array_filter( $gates ) ); $score = (int) round( 100 * $passed / self::RETIREMENT_GATE_COUNT );
		return array( 'feature_id' => 'F04-FUT-017', 'score_percent' => $score, 'passed_gates' => $passed, 'total_gates' => self::RETIREMENT_GATE_COUNT, 'gates' => $gates, 'eligible_for_founder_review' => self::RETIREMENT_GATE_COUNT === $passed, 'automatically_authorizes_retirement' => false, 'founder_approval_required' => true );
	}

	/** F04-FUT-018 — one privacy-safe operational view; authoritative ledgers remain in owners. */
	public static function mission_control() {
		$receipts = get_option( self::RECEIPTS_OPTION, array() ); $latest_receipt = is_array( $receipts ) && ! empty( $receipts ) ? end( $receipts ) : array();
		return array(
			'feature_id' => 'F04-FUT-018', 'version' => self::VERSION, 'registry_count' => count( self::registry() ), 'generated_at_utc' => gmdate( 'Y-m-d H:i:s' ),
			'lifecycle' => SNFLA_Schema::public_status(), 'file21' => SNFLA_Audit::redact( SNFLA_File21_Adapter::status() ),
			'system_check' => method_exists( 'SNFLA_Plan_Completion', 'system_check' ) ? SNFLA_Audit::redact( SNFLA_Plan_Completion::system_check() ) : array(),
			'metrics' => method_exists( 'SNFLA_Plan_Completion', 'metrics_summary' ) ? SNFLA_Audit::redact( SNFLA_Plan_Completion::metrics_summary() ) : array(),
			'invariants' => self::invariant_guardian(), 'contract_drift' => self::contract_drift(), 'canary' => get_option( self::CANARY_OPTION, array() ),
			'latest_twin' => self::summary_evidence( get_option( self::TWIN_OPTION, array() ), array( 'twin_checksum', 'created_at_utc', 'source_signature', 'totals' ) ),
			'latest_receipt' => self::summary_evidence( is_array( $latest_receipt ) ? $latest_receipt : array(), array( 'receipt_id', 'receipt_checksum', 'operation', 'created_at_utc' ) ),
			'retirement_confidence' => self::retirement_confidence(), 'authoritative_truth_store' => false,
		);
	}

	private static function summary_evidence( $value, array $keys ) {
		$out = array(); if ( ! is_array( $value ) ) { return $out; } foreach ( $keys as $key ) { if ( array_key_exists( $key, $value ) ) { $out[ $key ] = $value[ $key ]; } } return $out;
	}

	private static function normalize_text( $value ) {
		$value = str_replace( array( "\r\n", "\r" ), "\n", (string) $value );
		$value = preg_replace( '/[ \t]+/u', ' ', $value );
		return is_string( $value ) ? trim( $value ) : '';
	}

	public static function augment_release_readiness( $readiness ) {
		$readiness = is_array( $readiness ) ? $readiness : array();
		$invariants = self::invariant_guardian();
		$readiness['future18'] = array(
			'version' => self::VERSION, 'feature_count' => count( self::registry() ), 'source_contract_complete' => 18 === count( self::registry() ),
			'invariants_green' => ! empty( $invariants['green'] ), 'external_evidence_required' => true,
			'production_status_inferred_from_source' => false,
		);
		return $readiness;
	}
}
