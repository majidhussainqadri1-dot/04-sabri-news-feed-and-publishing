<?php
defined( 'ABSPATH' ) || exit;

/**
 * Canonical cross-file contracts owned by File 04.
 *
 * This class does not take ownership from File 00/01/19/20/21/24/26. It only
 * publishes File 04's bounded migration/compatibility contracts and evidence.
 */
final class SNFLA_Cross_File_Contracts {
	const CONTRACT_VERSION          = '1.0.0';
	const FILE01_MODULE_KEY         = 'file-04';
	const FILE01_CONTRACT_KEY       = 'file04.legacy-migration';
	const FILE01_ROUTE_KEY          = 'file04-legacy-migration-report';
	const REPORT_QUERY_VAR          = 'snfla_legacy_migration_report';
	const REPORT_ROUTE              = '/legacy-migration/report/';
	const FILE19_PRODUCER            = 'sabri-file04';
	const FILE19_OWNER               = 'File 04';
	const FILE19_OUTBOX_OPTION       = 'snfla_file19_event_outbox_v1';
	const FILE19_OUTBOX_MAX          = 100;
	const REWRITE_OPTION              = 'snfla_cross_file_rewrite_version';

	public static function boot() {
		if ( class_exists( 'SNFLA_Schema' ) && SNFLA_Schema::state_valid() && 'retired' === SNFLA_Schema::state() ) {
			// Retired File 04 stays otherwise inert. The only temporary hooks
			// retained are the producer/outbox drain needed to deliver the final
			// retirement fact before safe self-deactivation.
			add_filter( 'sun_registered_producers', array( __CLASS__, 'file19_producers' ), 20, 1 );
			add_action( 'admin_init', array( __CLASS__, 'retry_file19_outbox' ), 60 );
			add_action( 'snfla_retry_cross_file_events', array( __CLASS__, 'retry_file19_outbox' ) );
			return;
		}
		add_action( 'init', array( __CLASS__, 'register_report_rewrite' ), 20 );
		add_action( 'init', array( __CLASS__, 'maybe_refresh_rewrite_rules' ), 99 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'protect_report_route' ), -20 );
		add_filter( 'template_include', array( __CLASS__, 'report_template' ), 99 );
		add_filter( 'sabri_shell_layout_mode', array( __CLASS__, 'file20_layout_mode' ), 20, 2 );

		add_filter( 'spcrc/module_manifests', array( __CLASS__, 'file24_manifests' ), 20, 1 );
		add_filter( 'spcrc/file04_contract_state', array( __CLASS__, 'file24_contract_state' ), 20, 2 );

		add_filter( 'sun_registered_producers', array( __CLASS__, 'file19_producers' ), 20, 1 );
		add_action( 'admin_init', array( __CLASS__, 'retry_file19_outbox' ), 60 );
		add_action( 'snfla_retry_cross_file_events', array( __CLASS__, 'retry_file19_outbox' ) );
	}

	public static function register_report_rewrite() {
		add_rewrite_rule( '^legacy-migration/report/?$', 'index.php?' . self::REPORT_QUERY_VAR . '=1', 'top' );
	}

	public static function maybe_refresh_rewrite_rules() {
		if ( self::CONTRACT_VERSION === (string) get_option( self::REWRITE_OPTION, '' ) ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( self::REWRITE_OPTION, self::CONTRACT_VERSION, false );
	}

	public static function query_vars( $vars ) {
		$vars   = is_array( $vars ) ? $vars : array();
		$vars[] = self::REPORT_QUERY_VAR;
		return array_values( array_unique( $vars ) );
	}

	public static function is_report_request() {
		return '1' === (string) get_query_var( self::REPORT_QUERY_VAR, '' );
	}

	public static function protect_report_route() {
		if ( ! self::is_report_request() ) {
			return;
		}

		$actor = SNFLA_Capabilities::current_diagnostic_actor();
		if ( is_wp_error( $actor ) ) {
			status_header( 404 );
			nocache_headers();
			header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
			header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
			exit;
		}

		status_header( 200 );
		global $wp_query;
		if ( isset( $wp_query ) ) {
			$wp_query->is_404 = false;
		}
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
	}

	public static function file20_layout_mode( $mode, $settings = array() ) {
		unset( $settings );
		return self::is_report_request() ? 'minimal' : $mode;
	}

	public static function report_template( $template ) {
		if ( ! self::is_report_request() ) {
			return $template;
		}
		$owned = SNFLA_DIR . 'templates/legacy-migration-report.php';
		return is_readable( $owned ) ? $owned : $template;
	}

	public static function report_data() {
		return array(
			'lifecycle'            => SNFLA_Schema::public_status(),
			'file21'               => SNFLA_File21_Adapter::status(),
			'foundation_registry'  => self::foundation_registry_status(),
			'file24_contract'      => self::file24_contract_state( 'unassessed', array() ),
			'file19_outbox_count'  => count( self::file19_outbox() ),
			'reconciliation'       => SNFLA_Audit::redact( SNFLA_Reconciliation::report() ),
			'release_readiness'    => apply_filters( 'snfla_release_readiness_v1', array() ),
		);
	}

	public static function foundation_manifest() {
		return array(
			'module_key'              => self::FILE01_MODULE_KEY,
			'owner_file'              => '04',
			'owner_name'              => 'File 04 Legacy Foundation Adapter',
			'slug'                    => 'sabri-news-feed-legacy-adapter',
			'namespace_prefix'        => 'SNFLA',
			'software_version'        => defined( 'SNFLA_VERSION' ) ? SNFLA_VERSION : '2.0.5',
			'contract_version'        => self::CONTRACT_VERSION,
			'state'                   => 'active',
			'required'                => array(
				array(
					'module_key'      => 'file-00',
					'minimum_version' => '1.1.2',
					'purpose'         => 'Identity, authorization and current-action assurance',
					'fail_mode'       => 'Privileged mutations fail closed',
				),
				array(
					'module_key'      => 'file-21',
					'minimum_version' => '1.0.3',
					'purpose'         => 'Canonical publication migration and rollback commands',
					'fail_mode'       => 'Migration and rollback fail closed',
				),
			),
			'optional'                => array(
				array(
					'module_key'      => 'file-19',
					'minimum_version' => '1.0.0',
					'purpose'         => 'Versioned notification event delivery',
					'fail_mode'       => 'Events remain in bounded File 04 outbox',
				),
				array(
					'module_key'      => 'file-20',
					'minimum_version' => '1.0.0',
					'purpose'         => 'Canonical shell and minimal restricted-report layout',
					'fail_mode'       => 'Restricted report remains private and noindex',
				),
				array(
					'module_key'      => 'file-24',
					'minimum_version' => '0.99.0',
					'purpose'         => 'Cross-cutting security and assurance coordination',
					'fail_mode'       => 'Native File 04 controls remain active; assurance is unknown',
				),
				array(
					'module_key'      => 'file-26',
					'minimum_version' => '1.0.0',
					'purpose'         => 'Legacy-ID resolution and search handoff',
					'fail_mode'       => 'Unverified legacy content remains non-indexable',
				),
			),
			'capabilities'            => array(
				SNFLA_Capabilities::CAP_RUN,
				SNFLA_Capabilities::CAP_REVIEW,
				SNFLA_Capabilities::CAP_RETIRE,
			),
			'commands'                => array( 'dry_run', 'migrate', 'reconcile', 'cutover', 'rollback', 'retire', 'sync_foundation_contracts' ),
			'queries'                 => array( 'status', 'system_check', 'metrics', 'legacy_resolution' ),
			'events'                  => array(
				'LegacyMigrationBatchCompleted.v1',
				'LegacyRecordQuarantined.v1',
				'LegacyCutoverCompleted.v1',
				'LegacyAdapterRetired.v1',
			),
			'routes'                  => array(
				self::REPORT_ROUTE,
				'/wp-admin/admin.php?page=sabri-legacy-feed',
				'/wp-json/sabri/file04/v1/',
			),
			'data_classes'            => array(
				'legacy_publication_inventory',
				'legacy_to_canonical_mapping',
				'migration_run_evidence',
				'quarantine_conflict_ledger',
				'interaction_contribution_ledger',
				'rollback_retirement_evidence',
			),
			'health'                  => array(
				'provider' => 'SNFLA_Plan_Completion::system_check',
				'mode'     => 'read_only_diagnostics_available_during_dependency_outage',
			),
			'canonical_entities'      => array(),
			'writes'                  => array(
				array(
					'owner_module' => 'file-21',
					'operation'    => 'migrate_legacy_publication',
					'purpose'      => 'Canonical mutation through File 21 command contract only',
				),
				array(
					'owner_module' => 'file-21',
					'operation'    => 'rollback_legacy_publication',
					'purpose'      => 'Canonical rollback through File 21 command contract only',
				),
			),
			'global_shell_owner'      => false,
			'application_shell_owner' => false,
		);
	}

	public static function foundation_contract() {
		return array(
			'contract_key'     => self::FILE01_CONTRACT_KEY,
			'contract_version' => self::CONTRACT_VERSION,
			'owner_module'     => self::FILE01_MODULE_KEY,
			'status'           => 'current',
			'schema'           => array(
				'canonical_public_owner' => 'file-21',
				'canonical_search_owner' => 'file-26',
				'legacy_writes'          => 'forbidden',
				'report_route'           => self::REPORT_ROUTE,
				'event_names'            => array(
					'LegacyMigrationBatchCompleted.v1',
					'LegacyRecordQuarantined.v1',
					'LegacyCutoverCompleted.v1',
					'LegacyAdapterRetired.v1',
				),
				'post_cutover'           => 'read_only_then_redirect_only_then_retired',
			),
			'consumers'        => array( 'file-19', 'file-20', 'file-21', 'file-24', 'file-26' ),
			'deprecation_at'   => null,
		);
	}

	public static function foundation_route() {
		return array(
			'route_key'       => self::FILE01_ROUTE_KEY,
			'route_path'      => self::REPORT_ROUTE,
			'owner_module'    => self::FILE01_MODULE_KEY,
			'page_id'         => null,
			'layout_context'  => 'system_recovery',
			'status'          => 'active',
			'destination'     => '',
			'redirects'       => array(),
		);
	}

	public static function sync_foundation_registry() {
		if ( ! class_exists( 'SPF_Registry' ) ) {
			return new WP_Error( 'snfla_file01_registry_unavailable', 'File 01 registry is unavailable.', array( 'status' => 503 ) );
		}

		$actor = SNFLA_Capabilities::current_actor( SNFLA_Capabilities::CAP_RUN );
		if ( is_wp_error( $actor ) ) {
			return $actor;
		}
		$current_status = self::foundation_registry_status();
		if ( ! empty( $current_status['synced'] ) ) {
			return array( 'synced' => true, 'idempotent' => true, 'actor_id' => (int) $actor, 'status' => $current_status );
		}

		$module = SPF_Registry::get_module( self::FILE01_MODULE_KEY );
		$manifest_context = array( 'purpose' => 'file04_manifest_registration' );
		if ( is_array( $module ) && isset( $module['record_version'] ) ) {
			$manifest_context['expected_version'] = (int) $module['record_version'];
		}
		$manifest_result = SPF_Registry::register_manifest( self::foundation_manifest(), $manifest_context );
		if ( is_wp_error( $manifest_result ) ) {
			return $manifest_result;
		}

		$contract_context = array( 'purpose' => 'file04_contract_registration' );
		$existing_contracts = SPF_Registry::list_contracts( array(
			'owner_module'     => self::FILE01_MODULE_KEY,
			'contract_version' => self::CONTRACT_VERSION,
			'limit'            => 200,
		) );
		if ( is_wp_error( $existing_contracts ) ) {
			return $existing_contracts;
		}
		foreach ( (array) $existing_contracts as $existing_contract ) {
			if ( self::FILE01_CONTRACT_KEY === (string) ( $existing_contract['contract_key'] ?? '' ) ) {
				$contract_context['expected_version'] = (int) ( $existing_contract['record_version'] ?? 0 );
				break;
			}
		}
		$contract_result = SPF_Registry::register_contract( self::foundation_contract(), $contract_context );
		if ( is_wp_error( $contract_result ) ) {
			return $contract_result;
		}

		$route_context = array( 'purpose' => 'file04_route_mapping' );
		foreach ( (array) SPF_Registry::list_routes() as $existing_route ) {
			if ( self::FILE01_ROUTE_KEY === (string) ( $existing_route['route_key'] ?? '' ) ) {
				$route_context['expected_version'] = (int) ( $existing_route['record_version'] ?? 0 );
				break;
			}
		}
		$route_result = SPF_Registry::map_route( self::foundation_route(), $route_context );
		if ( is_wp_error( $route_result ) ) {
			return $route_result;
		}

		return array(
			'synced'   => true,
			'actor_id' => (int) $actor,
			'module'   => $manifest_result,
			'contract' => $contract_result,
			'route'    => $route_result,
		);
	}

	public static function foundation_registry_status() {
		if ( ! class_exists( 'SPF_Registry' ) ) {
			return array( 'available' => false, 'synced' => false, 'status' => 'missing' );
		}
		$module = SPF_Registry::get_module( self::FILE01_MODULE_KEY );
		$route  = null;
		foreach ( (array) SPF_Registry::list_routes() as $row ) {
			if ( self::FILE01_ROUTE_KEY === (string) ( $row['route_key'] ?? '' ) ) {
				$route = $row;
				break;
			}
		}
		$contract = null;
		$contracts = SPF_Registry::list_contracts( array(
			'owner_module'     => self::FILE01_MODULE_KEY,
			'contract_version' => self::CONTRACT_VERSION,
			'limit'            => 200,
		) );
		if ( ! is_wp_error( $contracts ) ) {
			foreach ( (array) $contracts as $row ) {
				if ( self::FILE01_CONTRACT_KEY === (string) ( $row['contract_key'] ?? '' ) ) {
					$contract = $row;
					break;
				}
			}
		}
		$route_ok = is_array( $route )
			&& self::REPORT_ROUTE === (string) ( $route['route_path'] ?? '' )
			&& self::FILE01_MODULE_KEY === (string) ( $route['owner_module'] ?? '' )
			&& 'system_recovery' === (string) ( $route['layout_context'] ?? '' )
			&& in_array( (string) ( $route['status'] ?? '' ), array( 'registered', 'active' ), true );
		$contract_ok = is_array( $contract ) && 'current' === (string) ( $contract['status'] ?? '' );
		$module_ok = is_array( $module )
			&& self::FILE01_MODULE_KEY === (string) ( $module['module_key'] ?? '' )
			&& ( defined( 'SNFLA_VERSION' ) ? SNFLA_VERSION : '2.0.5' ) === (string) ( $module['software_version'] ?? '' )
			&& self::CONTRACT_VERSION === (string) ( $module['contract_version'] ?? '' )
			&& 'active' === (string) ( $module['state'] ?? '' );
		return array(
			'available' => true,
			'synced'    => $module_ok && $route_ok && $contract_ok,
			'status'    => $module_ok && $route_ok && $contract_ok ? 'compatible' : 'pending',
			'module'    => $module_ok,
			'route'     => $route_ok,
			'contract'  => $contract_ok,
		);
	}


	public static function file20_shell_status() {
		$registry = apply_filters( 'sabri_shell_contract_registry', array() );
		$row = is_array( $registry ) && isset( $registry['04'] ) && is_array( $registry['04'] ) ? $registry['04'] : array();
		$contexts = apply_filters( 'sabri_shell_layout_contexts', array() );
		$context = is_array( $contexts ) && isset( $contexts['system_recovery'] ) && is_array( $contexts['system_recovery'] ) ? $contexts['system_recovery'] : array();
		$compatible = ! empty( $row )
			&& 'migration-compatibility' === (string) ( $row['native_scope'] ?? '' )
			&& 'suppress-writes-after-cutover' === (string) ( $row['file20_boundary'] ?? '' )
			&& 'minimal' === (string) ( $context['mode'] ?? '' )
			&& 'file-20' === (string) ( $context['owner'] ?? '' );
		return array(
			'available'      => ! empty( $row ) && ! empty( $context ),
			'compatible'     => $compatible,
			'status'         => $compatible ? 'pass' : ( empty( $row ) || empty( $context ) ? 'unknown' : 'blocker' ),
			'layout_context' => 'system_recovery',
			'layout_mode'    => (string) ( $context['mode'] ?? '' ),
			'route'          => self::REPORT_ROUTE,
		);
	}

	public static function file24_status() {
		$available = class_exists( '\\Sabri\\Platform\\Security\\Registry\\ModuleRegistry' );
		return array(
			'available' => $available,
			'status'    => $available ? 'pass' : 'unknown',
			'module_key'=> 'file-04',
			'contract'  => 'spcrc/module_manifests + spcrc/file04_contract_state',
		);
	}

	public static function file24_manifests( $manifests ) {
		$manifests = is_array( $manifests ) ? $manifests : array();
		$manifests[] = self::file24_manifest();
		return $manifests;
	}

	public static function file24_manifest() {
		return array(
			'module_key'             => 'file-04',
			'name'                   => 'File 04 Legacy Publishing Adapter',
			'version'                => defined( 'SNFLA_VERSION' ) ? SNFLA_VERSION : '2.0.5',
			'owner'                  => 'File 04',
			'posture'                => 'foundation',
			'data_classes'           => array(
				'legacy publication inventory',
				'legacy canonical mapping',
				'migration audit evidence',
				'quarantine conflict ledger',
				'rollback retirement evidence',
			),
			'public_routes'          => array(),
			'private_routes'         => array(
				self::REPORT_ROUTE,
				'/wp-json/sabri/file04/v1/',
			),
			'capabilities'           => array(
				SNFLA_Capabilities::CAP_RUN,
				SNFLA_Capabilities::CAP_REVIEW,
				SNFLA_Capabilities::CAP_RETIRE,
			),
			'external_vendors'       => array(),
			'privacy_operations'     => array(),
			'last_security_test'     => '',
			'contract_version'       => self::CONTRACT_VERSION,
			'canonical_data_owner'   => 'File 21 canonical publications; File 04 retained legacy evidence',
			'canonical_action_owner' => 'File 21 canonical mutation; File 04 migration lifecycle',
			'evidence_source'        => 'file04-source-contract-v2.0.5',
			'degraded_behavior'      => 'Legacy writes remain disabled; privileged mutations fail closed; diagnostics remain read only.',
			'release_gate'           => 'Hostinger staging, restore proof, File 21 and File 26 acceptance, Founder approval and live verification remain external.',
		);
	}

	public static function file24_contract_state( $state, $definition = array() ) {
		unset( $definition );
		if ( ! class_exists( 'SNFLA_Database' ) || ! SNFLA_Database::schema_healthy() ) {
			return 'degraded';
		}
		if ( ! SNFLA_Capabilities::file21_ready() ) {
			return 'degraded';
		}
		return 'compatible';
	}

	public static function file19_producers( $registry ) {
		$registry = is_array( $registry ) ? $registry : array();
		$registry[ self::FILE19_PRODUCER ] = array(
			'owner'           => self::FILE19_OWNER,
			'event_types'     => array(
				'LegacyMigrationBatchCompleted.V1',
				'LegacyRecordQuarantined.V1',
				'LegacyCutoverCompleted.V1',
				'LegacyAdapterRetired.V1',
			),
			'schema_versions' => array( '1.0' ),
			'internal'        => true,
		);
		return $registry;
	}

	public static function emit_event( $plan_event_name, $actor_id, array $data = array() ) {
		$actor_id = self::strict_positive_id( $actor_id );
		if ( $actor_id <= 0 ) {
			return array( 'published' => false, 'queued' => false, 'reason' => 'invalid_actor' );
		}
		$transport_type = self::file19_transport_type( $plan_event_name );
		if ( '' === $transport_type ) {
			return array( 'published' => false, 'queued' => false, 'reason' => 'unsupported_event' );
		}
		$event_key = isset( $data['_event_key'] ) ? sanitize_text_field( (string) $data['_event_key'] ) : '';
		unset( $data['_event_key'] );
		if ( '' === $event_key ) {
			$event_key = SNFLA_Checksum::hash( array( 'event' => $plan_event_name, 'actor' => $actor_id, 'data' => $data ) );
		}
		$event_id = 'file04:' . substr( hash( 'sha256', $plan_event_name . '|' . $event_key ), 0, 48 );
		$summary = self::event_summary( $plan_event_name, $data );
		$event = array(
			'producer'        => self::FILE19_PRODUCER,
			'owner'           => self::FILE19_OWNER,
			'event_id'        => $event_id,
			'event_type'      => $transport_type,
			'schema_version'  => '1.0',
			'occurred_at'     => gmdate( 'c' ),
			'recipients'      => array( array( 'user_id' => $actor_id ) ),
			'actor'           => array( 'type' => 'user', 'id' => (string) $actor_id ),
			'subject'         => array( 'type' => 'file', 'id' => '04' ),
			'category'        => 'system',
			'priority'        => 'normal',
			'sensitivity'     => 'standard',
			'deep_link'       => admin_url( 'admin.php?page=sabri-legacy-feed' ),
			'deep_context'    => 'file04-migration',
			'data'            => array_merge(
				array(
					'action_name'      => $plan_event_name,
					'summary'          => $summary,
					'source_event_name'=> $plan_event_name,
				),
				SNFLA_Audit::redact( $data )
			),
			'idempotency_key' => $event_id,
			'source_version'  => defined( 'SNFLA_VERSION' ) ? SNFLA_VERSION : '2.0.5',
		);

		do_action( 'snfla_domain_event_v1', $plan_event_name, $event );

		if ( function_exists( 'sun_ingest_domain_event' ) ) {
			$result = sun_ingest_domain_event( $event );
			if ( ! is_wp_error( $result ) ) {
				return array( 'published' => true, 'queued' => false, 'provider' => 'File 19', 'result' => SNFLA_Audit::redact( $result ) );
			}
			$queued = self::queue_file19_event( $event, $result->get_error_code() );
			return array( 'published' => false, 'queued' => $queued, 'provider' => 'File 19', 'reason' => $queued ? $result->get_error_code() : 'file19_outbox_full' );
		}

		$queued = self::queue_file19_event( $event, 'file19_unavailable' );
		return array( 'published' => false, 'queued' => $queued, 'provider' => 'File 19', 'reason' => $queued ? 'file19_unavailable' : 'file19_outbox_full' );
	}

	public static function retry_file19_outbox() {
		if ( ! function_exists( 'sun_ingest_domain_event' ) ) {
			return array( 'processed' => 0, 'remaining' => count( self::file19_outbox() ) );
		}
		$outbox = self::file19_outbox();
		$processed = 0;
		foreach ( array_slice( $outbox, 0, 20, true ) as $key => $record ) {
			if ( ! is_array( $record ) || empty( $record['event'] ) || ! is_array( $record['event'] ) ) {
				unset( $outbox[ $key ] );
				continue;
			}
			$result = sun_ingest_domain_event( $record['event'] );
			if ( ! is_wp_error( $result ) ) {
				unset( $outbox[ $key ] );
				$processed++;
			} else {
				$outbox[ $key ]['last_error'] = sanitize_key( $result->get_error_code() );
				$outbox[ $key ]['attempts'] = min( 1000, 1 + absint( $outbox[ $key ]['attempts'] ?? 0 ) );
				$outbox[ $key ]['last_attempt_utc'] = gmdate( 'Y-m-d H:i:s' );
			}
		}
		self::persist_file19_outbox( $outbox );
		return array( 'processed' => $processed, 'remaining' => count( $outbox ) );
	}

	public static function event_stream_ready( $needed = 1 ) {
		return empty( self::file19_outbox() ) && self::event_capacity_available( $needed );
	}

	public static function event_capacity_available( $needed = 1 ) {
		if ( ! is_int( $needed ) || $needed < 1 || $needed > self::FILE19_OUTBOX_MAX ) {
			return false;
		}
		return count( self::file19_outbox() ) + $needed <= self::FILE19_OUTBOX_MAX;
	}

	public static function file19_outbox_status() {
		$outbox = self::file19_outbox();
		return array(
			'count'     => count( $outbox ),
			'available' => function_exists( 'sun_ingest_domain_event' ),
			'status'    => function_exists( 'sun_ingest_domain_event' ) ? ( empty( $outbox ) ? 'pass' : 'pending' ) : 'unknown',
		);
	}

	private static function queue_file19_event( array $event, $reason ) {
		$outbox = self::file19_outbox();
		$key = sanitize_text_field( (string) ( $event['event_id'] ?? '' ) );
		if ( '' === $key ) {
			return false;
		}
		if ( ! isset( $outbox[ $key ] ) && count( $outbox ) >= self::FILE19_OUTBOX_MAX ) {
			do_action( 'snfla_operational_alert_v1', array(
				'owner'    => 'File 04 release operator',
				'severity' => 'critical',
				'code'     => 'file19_event_outbox_full',
				'event_id' => $key,
			) );
			return false;
		}
		$outbox[ $key ] = array(
			'event'          => $event,
			'last_error'     => sanitize_key( (string) $reason ),
			'attempts'       => absint( $outbox[ $key ]['attempts'] ?? 0 ),
			'queued_at_utc'  => (string) ( $outbox[ $key ]['queued_at_utc'] ?? gmdate( 'Y-m-d H:i:s' ) ),
		);
		$persisted = self::persist_file19_outbox( $outbox );
		do_action( 'snfla_operational_alert_v1', array(
			'owner'    => 'File 04 release operator',
			'severity' => 'medium',
			'code'     => 'file19_event_queued',
			'event_id' => $key,
			'reason'   => sanitize_key( (string) $reason ),
		) );
		return $persisted;
	}

	private static function file19_outbox() {
		$outbox = get_option( self::FILE19_OUTBOX_OPTION, array() );
		return is_array( $outbox ) ? $outbox : array();
	}

	private static function persist_file19_outbox( array $outbox ) {
		$ok = update_option( self::FILE19_OUTBOX_OPTION, $outbox, false ) || get_option( self::FILE19_OUTBOX_OPTION, array() ) === $outbox;
		if ( ! $ok ) {
			do_action( 'snfla_operational_alert_v1', array(
				'owner'    => 'File 04 release operator',
				'severity' => 'high',
				'code'     => 'file19_event_outbox_persist_failed',
			) );
		}
		return $ok;
	}

	private static function file19_transport_type( $plan_event_name ) {
		$map = array(
			'LegacyMigrationBatchCompleted.v1' => 'LegacyMigrationBatchCompleted.V1',
			'LegacyRecordQuarantined.v1'       => 'LegacyRecordQuarantined.V1',
			'LegacyCutoverCompleted.v1'        => 'LegacyCutoverCompleted.V1',
			'LegacyAdapterRetired.v1'          => 'LegacyAdapterRetired.V1',
		);
		return isset( $map[ $plan_event_name ] ) ? $map[ $plan_event_name ] : '';
	}

	private static function event_summary( $event_name, array $data ) {
		$summaries = array(
			'LegacyMigrationBatchCompleted.v1' => 'A bounded legacy migration batch completed.',
			'LegacyRecordQuarantined.v1'       => 'A legacy record received an approved source-only quarantine disposition.',
			'LegacyCutoverCompleted.v1'        => 'Legacy publication routing cut over to canonical File 21 routes.',
			'LegacyAdapterRetired.v1'          => 'The File 04 legacy adapter completed retirement.',
		);
		unset( $data );
		return isset( $summaries[ $event_name ] ) ? $summaries[ $event_name ] : 'A File 04 migration lifecycle event occurred.';
	}

	private static function strict_positive_id( $value ) {
		if ( is_int( $value ) ) {
			return $value > 0 ? $value : 0;
		}
		if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) {
			return 0;
		}
		$parsed = (int) $value;
		return $parsed > 0 && (string) $parsed === $value ? $parsed : 0;
	}
}
