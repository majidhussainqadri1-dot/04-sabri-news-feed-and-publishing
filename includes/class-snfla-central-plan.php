<?php
defined( 'ABSPATH' ) || exit;

/**
 * Central-plan reconciliation and cross-file contract registry for File 04.
 *
 * File 04 is deliberately NOT a second feed/community/search owner. The modern
 * central-plan requirements that belong to File 21, File 17, File 20, File 25,
 * File 26 or File 24 are represented here as explicit integration-regression
 * obligations. File 04 only enforces the migration, compatibility, security,
 * accessibility and release evidence that belongs to this adapter.
 */
final class SNFLA_Central_Plan {
	const CONTRACT_VERSION = '1.0.0';
	const CENTRAL_PLAN_ID  = 'SSH-CENTRAL-CONSOLIDATED-2026-08-07';
	const FILE_PLAN_ID     = 'SSH-F04-PLAN-2026-v1.0';

	public static function boot() {
		add_filter( 'snfla_central_plan_manifest_v1', array( __CLASS__, 'manifest' ) );
		add_filter( 'snfla_release_readiness_v1', array( __CLASS__, 'release_readiness' ), 10, 1 );
		add_filter( 'sabri_file26_legacy_resolution_v1', array( __CLASS__, 'file26_resolution' ), 10, 2 );
		add_filter( 'sabri_file04_module_manifest_v1', array( __CLASS__, 'module_manifest' ) );
	}

	/** Exactly the 71 File-04-applicable CV requirements in the current plan. */
	public static function cv_ids() {
		$ids = array();
		foreach ( array( array( 37, 49 ), array( 74, 84 ), array( 239, 285 ) ) as $range ) {
			for ( $i = $range[0]; $i <= $range[1]; $i++ ) {
				$ids[] = sprintf( 'CV-%03d', $i );
			}
		}
		return $ids;
	}

	/** File-04-relevant end-to-end acceptance journeys from the modern plan. */
	public static function aj_ids() {
		$ids = array( 7, 10, 24, 25, 28 );
		for ( $i = 31; $i <= 40; $i++ ) { $ids[] = $i; }
		return array_map( static function ( $id ) { return sprintf( 'AJ-%02d', $id ); }, $ids );
	}

	public static function cen_ids() {
		return array( 'F04-CEN-01', 'F04-CEN-02' );
	}

	private static function priority_for( $n ) {
		$p1 = array( 38, 40, 41, 43, 44, 45, 48, 74, 75, 76, 77, 78, 83, 84, 247, 248, 256, 281 );
		$p2 = array( 82 );
		if ( in_array( $n, $p2, true ) ) { return 'P2'; }
		if ( in_array( $n, $p1, true ) ) { return 'P1'; }
		return 'P0';
	}

	private static function owner_for( $n ) {
		if ( $n >= 37 && $n <= 49 ) { return 'File 21 / File 26 canonical discovery owners'; }
		if ( $n >= 74 && $n <= 84 ) { return 'File 17 and canonical community-domain owners'; }
		if ( $n >= 239 && $n <= 249 ) { return 'File 20 / File 25 plus File 04 adapter-surface compliance'; }
		if ( $n >= 250 && $n <= 261 ) { return 'File 21 / File 24 safety owners; File 04 migration guard'; }
		if ( $n >= 262 && $n <= 273 ) { return 'File 04 native enforcement with File 24 assurance'; }
		if ( $n >= 274 && $n <= 280 ) { return 'File 04 release/operations with platform assurance'; }
		return 'Platform operations owner with File 04 evidence obligation';
	}

	private static function mode_for( $n ) {
		if ( ( $n >= 37 && $n <= 49 ) || ( $n >= 74 && $n <= 84 ) ) { return 'integration_regression_only'; }
		if ( $n >= 239 && $n <= 249 ) {
			return in_array( $n, array( 242, 246 ), true ) ? 'integration_regression_only' : 'adapter_surface_guard';
		}
		if ( $n >= 250 && $n <= 261 ) { return 'migration_safety_guard'; }
		if ( $n >= 262 && $n <= 280 ) { return 'native_assurance'; }
		return 'release_operations_evidence';
	}

	private static function contract_for_mode( $mode ) {
		switch ( $mode ) {
			case 'integration_regression_only':
				return 'Versioned canonical-owner projection/route contract; File 04 MUST NOT create a duplicate truth store.';
			case 'adapter_surface_guard':
				return 'File 20 shell + File 25 visual tokens; File 04 admin/redirect surfaces only.';
			case 'migration_safety_guard':
				return 'Quarantine/consent/provenance/fail-closed migration policy; canonical mutation only through File 21.';
			case 'native_assurance':
				return 'Server-side authorization, immutable evidence, privacy-safe audit, bounded migration and release gate.';
			default:
				return 'Evidence/runbook/release-readiness contract; no duplicate domain backend.';
		}
	}

	public static function requirements() {
		$rows = array();
		foreach ( self::cv_ids() as $id ) {
			$n    = (int) substr( $id, 3 );
			$mode = self::mode_for( $n );
			$rows[ $id ] = array(
				'id'            => $id,
				'priority'      => self::priority_for( $n ),
				'owner'         => self::owner_for( $n ),
				'enforcement'   => $mode,
				'code_location' => 'includes/class-snfla-central-plan.php',
				'contract'      => self::contract_for_mode( $mode ),
				'test_id'       => 'CP-' . $id,
			);
		}
		return $rows;
	}

	public static function module_manifest( $existing = null ) {
		$manifest = array(
			'schema'                  => 1,
			'file_number'             => '04',
			'module'                  => 'News Feed and Publishing — Legacy Foundation Adapter',
			'contract_version'        => self::CONTRACT_VERSION,
			'runtime_version'         => defined( 'SNFLA_VERSION' ) ? SNFLA_VERSION : '1.3.0',
			'canonical_public_owner'  => 'File 21',
			'canonical_search_owner'  => 'File 26',
			'canonical_shell_owner'   => 'File 20',
			'canonical_visual_owner'  => 'File 25',
			'assurance_owner'         => 'File 24',
			'legacy_writes'           => 'forbidden',
			'dual_write'              => 'forbidden except explicit Founder-approved bounded migration evidence',
			'after_cutover'           => 'read_only_then_redirect_only_then_retired',
			'central_plan_id'         => self::CENTRAL_PLAN_ID,
			'file_plan_id'            => self::FILE_PLAN_ID,
			'applicable_cv_count'     => count( self::cv_ids() ),
			'applicable_cv_ids'       => self::cv_ids(),
			'file_specific_ids'       => self::cen_ids(),
			'acceptance_journey_ids'  => self::aj_ids(),
		);
		if ( is_array( $existing ) ) {
			return array_merge( $existing, $manifest );
		}
		return $manifest;
	}

	public static function manifest( $existing = null ) {
		$manifest = self::module_manifest();
		$manifest['requirements'] = self::requirements();
		if ( is_array( $existing ) ) {
			return array_merge( $existing, $manifest );
		}
		return $manifest;
	}

	/**
	 * File 26 may resolve legacy references, but File 04 never contributes ranking,
	 * feed truth or private content to the search index.
	 */
	public static function file26_resolution( $existing, $legacy_id ) {
		if ( ! empty( $existing ) ) { return $existing; }
		$legacy_id = absint( $legacy_id );
		if ( $legacy_id <= 0 || ! SNFLA_Capabilities::file21_ready() ) {
			return array( 'status' => 'unavailable', 'indexable' => false, 'legacy_id' => $legacy_id );
		}
		$target_id = SNFLA_File21_Adapter::target_for( $legacy_id );
		if ( $target_id <= 0 || ! SNFLA_File21_Adapter::migration_target_valid( $legacy_id, $target_id ) ) {
			return array( 'status' => 'unmapped', 'indexable' => false, 'legacy_id' => $legacy_id );
		}
		if ( ! SNFLA_File21_Adapter::target_public( $target_id ) ) {
			return array( 'status' => 'canonical_nonpublic', 'indexable' => false, 'legacy_id' => $legacy_id );
		}
		$url = get_permalink( $target_id );
		if ( ! is_string( $url ) || '' === $url ) {
			return array( 'status' => 'canonical_url_unavailable', 'indexable' => false, 'legacy_id' => $legacy_id );
		}
		return array(
			'status'       => 'canonical',
			'indexable'    => false,
			'legacy_id'    => $legacy_id,
			'canonical_id' => $target_id,
			'canonical_url'=> esc_url_raw( $url ),
			'owner'        => 'File 21',
			'contract'     => self::CONTRACT_VERSION,
		);
	}

	/**
	 * Truthful production-readiness gate. Source completion does not fabricate
	 * staging, accessibility, backup/restore, File 26 or Founder acceptance.
	 */
	public static function release_readiness( $external_evidence = array() ) {
		$external_evidence = is_array( $external_evidence ) ? $external_evidence : array();
		$required_external = array(
			'staging_accepted',
			'backup_restore_verified',
			'rtl_accessibility_accepted',
			'file26_contract_accepted',
			'degraded_provider_drill_passed',
			'founder_approved',
		);
		$blockers = array();
		if ( ! SNFLA_Capabilities::file21_ready() ) { $blockers[] = 'file21_contract_unavailable'; }
		foreach ( $required_external as $key ) {
			if ( empty( $external_evidence[ $key ] ) ) { $blockers[] = $key . '_pending'; }
		}
		return array(
			'schema'              => 1,
			'contract_version'    => self::CONTRACT_VERSION,
			'source_requirements' => count( self::requirements() ),
			'source_trace_complete'=> 71 === count( self::requirements() ),
			'production_ready'    => empty( $blockers ),
			'blockers'            => array_values( array_unique( $blockers ) ),
			'external_evidence'   => $external_evidence,
		);
	}
}
