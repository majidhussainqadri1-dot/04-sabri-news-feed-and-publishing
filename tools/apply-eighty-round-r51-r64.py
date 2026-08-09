from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]

def t(p): return (ROOT/p).read_text(encoding='utf-8')
def w(p,s): (ROOT/p).write_text(s,encoding='utf-8')
def rep(p,a,b,count=1):
    s=t(p); n=s.count(a)
    if n!=count: raise SystemExit(f'{p}: expected {count}, got {n}: {a[:100]!r}')
    w(p,s.replace(a,b,count))
def sub(p,pat,repl,count=1):
    s=t(p); ns,n=re.subn(pat,repl,s,count=count,flags=re.S)
    if n!=count: raise SystemExit(f'{p}: regex expected {count}, got {n}: {pat[:100]!r}')
    w(p,ns)

# R51/R52: every File21 result collection is request-bounded; migrated rows must have valid shape.
sub('includes/class-snfla-plan-completion.php',
    r"\t\t\$requested = SNFLA_Integrity::normalized_ids\( \$legacy_ids, SNFLA_Migration::MAX_BATCH \);.*?\t\tforeach \( \$requested as \$legacy_id \) \{",
    """\t\t$requested = SNFLA_Integrity::normalized_ids( $legacy_ids, SNFLA_Migration::MAX_BATCH );
\t\t$requested_set = array_fill_keys( $requested, true );
\t\t$migrated = (array) ( $result['migrated'] ?? array() );
\t\tforeach ( array( 'migrated', 'skipped', 'warnings' ) as $collection ) {
\t\t\t$collection_rows = (array) ( $result[ $collection ] ?? array() );
\t\t\tforeach ( $collection_rows as $returned_id => $row ) {
\t\t\t\t$valid_id = filter_var( $returned_id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
\t\t\t\tif ( false === $valid_id || ! isset( $requested_set[ (int) $valid_id ] ) ) {
\t\t\t\t\tdo_action( 'snfla_operational_alert_v1', array( 'code' => 'file21_unexpected_migration_result_ids', 'severity' => 'critical', 'collection' => $collection ) );
\t\t\t\t\treturn new WP_Error( 'snfla_file21_unexpected_result_ids', 'Canonical File 21 returned migration result IDs outside the requested batch; File 04 refused to persist them.', array( 'status' => 502, 'collection' => $collection ) );
\t\t\t\t}
\t\t\t\tif ( 'migrated' === $collection && ! is_array( $row ) ) {
\t\t\t\t\treturn new WP_Error( 'snfla_file21_migrated_row_invalid', 'Canonical File 21 returned a malformed migrated result row.', array( 'status' => 502, 'legacy_id' => (int) $valid_id ) );
\t\t\t\t}
\t\t\t}
\t\t}
\t\tforeach ( $requested as $legacy_id ) {""",1)

# R53: emergency quarantine must not guess through a failed mapping-ledger read/write.
rep('includes/class-snfla-migration.php', """\t\tforeach ( SNFLA_Integrity::normalized_ids( $legacy_ids, self::MAX_BATCH ) as $legacy_id ) {
\t\t\t$current = SNFLA_Mapping::get( $legacy_id );
\t\t\tSNFLA_Mapping::upsert(
""", """\t\tforeach ( SNFLA_Integrity::normalized_ids( $legacy_ids, self::MAX_BATCH ) as $legacy_id ) {
\t\t\t$current = SNFLA_Mapping::get_checked( $legacy_id );
\t\t\tif ( is_wp_error( $current ) ) {
\t\t\t\tdo_action( 'snfla_operational_alert_v1', array( 'code' => 'quarantine_mapping_read_failed', 'severity' => 'critical', 'legacy_id' => $legacy_id ) );
\t\t\t\tcontinue;
\t\t\t}
\t\t\t$persisted = SNFLA_Mapping::upsert(
""",1)
rep('includes/class-snfla-migration.php', """\t\t\t\t)
\t\t\t);
\t\t\tSNFLA_Mapping::open_conflict( $legacy_id, $code, 'blocker', array( 'legacy_id' => $legacy_id, 'run_uuid' => $run_uuid ), $run_uuid );
\t\t}
\t}


\tprivate static function safe_count_query""", """\t\t\t\t)
\t\t\t);
\t\t\tif ( ! $persisted ) {
\t\t\t\tdo_action( 'snfla_operational_alert_v1', array( 'code' => 'quarantine_mapping_write_failed', 'severity' => 'critical', 'legacy_id' => $legacy_id ) );
\t\t\t}
\t\t\tSNFLA_Mapping::open_conflict( $legacy_id, $code, 'blocker', array( 'legacy_id' => $legacy_id, 'run_uuid' => $run_uuid ), $run_uuid );
\t\t}
\t}


\tprivate static function safe_count_query""",1)

# R54: matching schema-version alone is not health; verify physical schema when health evidence is absent/unhealthy.
rep('includes/class-snfla-database.php', """\tpublic static function maybe_upgrade() {
\t\tif ( SNFLA_SCHEMA_VERSION === (string) get_option( 'snfla_schema_version', '' ) ) { return; }
\t\tif ( ! self::acquire_lock( 'schema_upgrade', 5 ) ) {
""", """\tpublic static function maybe_upgrade() {
\t\t$stored_version = (string) get_option( 'snfla_schema_version', '' );
\t\t$health = get_option( 'snfla_schema_health', array() );
\t\tif ( SNFLA_SCHEMA_VERSION === $stored_version && is_array( $health ) && ! empty( $health['ok'] ) ) { return; }
\t\tif ( ! self::acquire_lock( 'schema_upgrade', 5 ) ) {
""",1)
rep('includes/class-snfla-database.php', """\tpublic static function schema_healthy() {
\t\tif ( SNFLA_SCHEMA_VERSION !== (string) get_option( 'snfla_schema_version', '' ) ) { return false; }
\t\t$health = get_option( 'snfla_schema_health', array() );
\t\treturn ! is_array( $health ) || ! array_key_exists( 'ok', $health ) || ! empty( $health['ok'] );
\t}
""", """\tpublic static function schema_healthy() {
\t\tif ( SNFLA_SCHEMA_VERSION !== (string) get_option( 'snfla_schema_version', '' ) ) { return false; }
\t\t$health = get_option( 'snfla_schema_health', array() );
\t\treturn is_array( $health ) && ! empty( $health['ok'] );
\t}
""",1)

# R55: site-scoped retirement cannot deactivate a network-wide adapter for every site.
rep('includes/class-snfla-retirement.php', """\t\t$plugin  = plugin_basename( SNFLA_FILE );
\t\t$network = function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $plugin );
\t\tif ( ! is_plugin_active( $plugin ) && ! $network ) { return true; }
\t\tdeactivate_plugins( $plugin, true, $network );
\t\treturn $network ? ! is_plugin_active_for_network( $plugin ) : ! is_plugin_active( $plugin );
""", """\t\t$plugin  = plugin_basename( SNFLA_FILE );
\t\t$network = function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $plugin );
\t\tif ( $network ) {
\t\t\tdo_action( 'snfla_operational_alert_v1', array( 'code' => 'network_retirement_requires_network_operator', 'severity' => 'high' ) );
\t\t\treturn false;
\t\t}
\t\tif ( ! is_plugin_active( $plugin ) ) { return true; }
\t\tdeactivate_plugins( $plugin, true, false );
\t\treturn ! is_plugin_active( $plugin );
""",1)

# R56: corrupt lifecycle version/state keeps mutation surface closed.
rep('includes/class-snfla-retirement.php', """\tpublic static function mutations_allowed() {
\t\t$state = SNFLA_Schema::state();
\t\treturn in_array( $state, SNFLA_Schema::states(), true ) && 'retired' !== $state;
\t}
""", """\tpublic static function mutations_allowed() {
\t\t$state = SNFLA_Schema::state();
\t\treturn SNFLA_Schema::state_valid() && 'retired' !== $state;
\t}
""",1)

# R57: observability persistence failures are surfaced, not silent.
rep('includes/class-snfla-plan-completion.php', """\t\tif ( count( $metrics ) > self::MAX_METRICS ) { $metrics = array_slice( $metrics, -1 * self::MAX_METRICS ); }
\t\tupdate_option( self::METRICS_OPTION, $metrics, false );
\t\t$threshold = (float) apply_filters( 'snfla_rest_p95_budget_ms', 2000.0, $route );
""", """\t\tif ( count( $metrics ) > self::MAX_METRICS ) { $metrics = array_slice( $metrics, -1 * self::MAX_METRICS ); }
\t\t$metrics_persisted = update_option( self::METRICS_OPTION, $metrics, false ) || get_option( self::METRICS_OPTION, array() ) === $metrics;
\t\tif ( ! $metrics_persisted ) { do_action( 'snfla_operational_alert_v1', array( 'owner' => 'File 04 release operator', 'severity' => 'high', 'code' => 'metrics_persist_failed', 'route' => $route, 'trace_safe' => true ) ); }
\t\t$threshold = (float) apply_filters( 'snfla_rest_p95_budget_ms', 2000.0, $route );
""",1)

# R58/R59: system check exposes File04 schema health and requires fresh File26 contract evidence.
rep('includes/class-snfla-plan-completion.php', """\t\t$checks = array();
\t\t$checks['runtime'] = array( 'status' => version_compare( PHP_VERSION, '8.1', '>=' ) ? 'pass' : 'blocker', 'php' => PHP_VERSION, 'wordpress' => get_bloginfo( 'version' ), 'required_php' => '>=8.1', 'staging_target' => 'WordPress 7.0.1 / PHP 8.3.x fresh re-verification required' );
""", """\t\t$checks = array();
\t\t$checks['runtime'] = array( 'status' => version_compare( PHP_VERSION, '8.1', '>=' ) ? 'pass' : 'blocker', 'php' => PHP_VERSION, 'wordpress' => get_bloginfo( 'version' ), 'required_php' => '>=8.1', 'staging_target' => 'WordPress 7.0.1 / PHP 8.3.x fresh re-verification required' );
\t\t$checks['schema'] = array( 'status' => SNFLA_Database::schema_healthy() ? 'pass' : 'blocker', 'expected_schema_version' => SNFLA_SCHEMA_VERSION, 'stored_schema_version' => (string) get_option( 'snfla_schema_version', '' ) );
""",1)
rep('includes/class-snfla-plan-completion.php', """\t\t$file26 = apply_filters( 'sabri_file26_accept_file04_contract_v1', array( 'accepted' => false, 'status' => 'unknown' ), $file26_request );
\t\t$file26_bound = is_array( $file26 ) && ! empty( $file26['accepted'] ) && ! empty( $file26['provider_id'] ) && ! empty( $file26['manifest_digest'] ) && hash_equals( (string) $file26_request['manifest_digest'], (string) $file26['manifest_digest'] );
\t\t$checks['file26'] = array( 'status' => $file26_bound ? 'pass' : 'unknown', 'request_digest' => $file26_request['manifest_digest'], 'evidence' => SNFLA_Audit::redact( is_array( $file26 ) ? $file26 : array() ) );
""", """\t\t$file26 = apply_filters( 'sabri_file26_accept_file04_contract_v1', array( 'accepted' => false, 'status' => 'unknown' ), $file26_request );
\t\t$file26_verified_at = is_array( $file26 ) && ! empty( $file26['verified_at_utc'] ) ? strtotime( (string) $file26['verified_at_utc'] . ' UTC' ) : false;
\t\t$file26_bound = is_array( $file26 ) && ! empty( $file26['accepted'] ) && ! empty( $file26['provider_id'] ) && ! empty( $file26['manifest_digest'] ) && hash_equals( (string) $file26_request['manifest_digest'], (string) $file26['manifest_digest'] ) && false !== $file26_verified_at && $file26_verified_at >= time() - 15 * MINUTE_IN_SECONDS && $file26_verified_at <= time() + 300;
\t\t$checks['file26'] = array( 'status' => $file26_bound ? 'pass' : 'unknown', 'request_digest' => $file26_request['manifest_digest'], 'evidence' => SNFLA_Audit::redact( is_array( $file26 ) ? $file26 : array() ) );
""",1)

# R60: a storage-estimate provider cannot understate with negative/non-finite numeric evidence.
rep('includes/class-snfla-plan-completion.php', """\t\t$attachment_bytes = apply_filters( 'snfla_storage_estimate_media_bytes_v1', null, $attachment_count, SNFLA_Inventory::locked() );
\t\tif ( $attachment_count > 0 && ! is_numeric( $attachment_bytes ) ) { return new WP_Error( 'snfla_media_storage_estimate_provider_required', 'A bounded storage provider estimate is required for legacy attachments; File 04 will not perform an unbounded filesystem scan.', array( 'status' => 412, 'attachment_count' => $attachment_count ) ); }
\t\t$parts['attachment_bytes'] = max( 0, (int) $attachment_bytes );
""", """\t\t$attachment_bytes = apply_filters( 'snfla_storage_estimate_media_bytes_v1', null, $attachment_count, SNFLA_Inventory::locked() );
\t\t$attachment_bytes_number = is_numeric( $attachment_bytes ) ? (float) $attachment_bytes : null;
\t\tif ( $attachment_count > 0 && ( null === $attachment_bytes_number || ! is_finite( $attachment_bytes_number ) || $attachment_bytes_number < 0 ) ) { return new WP_Error( 'snfla_media_storage_estimate_provider_required', 'A bounded non-negative finite storage provider estimate is required for legacy attachments; File 04 will not guess or perform an unbounded filesystem scan.', array( 'status' => 412, 'attachment_count' => $attachment_count ) ); }
\t\t$parts['attachment_bytes'] = null === $attachment_bytes_number ? 0 : (int) ceil( $attachment_bytes_number );
""",1)

# R61/R62: CLI IDs preserve object identity and CLI never reports blank JSON as success.
rep('includes/class-snfla-cli.php', "\t\t$ids = array_filter( array_map( 'absint', explode( ',', (string) ( $assoc['ids'] ?? '' ) ) ) );\n", "\t\t$ids = $this->parse_ids( $assoc['ids'] ?? '' );\n", 3)
rep('includes/class-snfla-cli.php', """\tprivate function output( $result ) {
\t\tif ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_code() . ': ' . $result->get_error_message() ); }
\t\tWP_CLI::success( wp_json_encode( $result, JSON_UNESCAPED_SLASHES ) );
\t}
""", """\tprivate function parse_ids( $raw ) {
\t\t$parts = explode( ',', (string) $raw );
\t\t$ids = array();
\t\tforeach ( $parts as $part ) {
\t\t\t$part = trim( $part );
\t\t\tif ( 1 !== preg_match( '/^[1-9][0-9]*$/D', $part ) ) { WP_CLI::error( 'snfla_invalid_ids: IDs must be positive decimal integers.' ); }
\t\t\t$id = (int) $part;
\t\t\tif ( isset( $ids[ $id ] ) ) { WP_CLI::error( 'snfla_duplicate_ids: Duplicate legacy IDs are not accepted.' ); }
\t\t\t$ids[ $id ] = $id;
\t\t}
\t\tif ( empty( $ids ) || count( $ids ) > SNFLA_Migration::MAX_BATCH ) { WP_CLI::error( 'snfla_invalid_ids: A non-empty bounded ID list is required.' ); }
\t\treturn array_values( $ids );
\t}

\tprivate function output( $result ) {
\t\tif ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_code() . ': ' . $result->get_error_message() ); }
\t\t$encoded = wp_json_encode( $result, JSON_UNESCAPED_SLASHES );
\t\tif ( ! is_string( $encoded ) ) { WP_CLI::error( 'snfla_output_encoding_failed: Result evidence could not be encoded safely.' ); }
\t\tWP_CLI::success( $encoded );
\t}
""",1)
rep('includes/class-snfla-cli.php', """\t\t$response = SNFLA_REST::status( $request );
\t\tWP_CLI::line( wp_json_encode( $response->get_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
""", """\t\t$response = SNFLA_REST::status( $request );
\t\t$encoded = wp_json_encode( $response->get_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
\t\tif ( ! is_string( $encoded ) ) { WP_CLI::error( 'snfla_output_encoding_failed: Status evidence could not be encoded safely.' ); }
\t\tWP_CLI::line( $encoded );
""",1)

# R63/R64: admin never presents unauthenticated lifecycle evidence as trusted and DB read errors are explicit.
rep('includes/class-snfla-admin.php', """\t\t\t\tcase 'mapping': self::table_block( $wpdb->get_results( \"SELECT legacy_id,target_id,target_type,status,source_checksum,target_checksum,run_uuid,last_error_code,updated_at FROM {$t['map']} ORDER BY legacy_id ASC LIMIT 500\", ARRAY_A ) ); break;
\t\t\t\tcase 'conflicts': self::table_block( $wpdb->get_results( \"SELECT id,legacy_id,conflict_code,severity,status,run_uuid,created_at,resolved_at FROM {$t['conflicts']} ORDER BY status ASC,severity DESC,id DESC LIMIT 500\", ARRAY_A ) ); break;
\t\t\t\tcase 'redirects': self::json_block( array( 'state' => $status['state'], 'fallback_window' => get_option( SNFLA_Schema::FALLBACK_OPTION, array() ), 'policy' => 'Same-origin 302 redirects only during cutover/fallback; permanent redirects or gone responses must be handed to a verified canonical route owner before retirement.' ) ); break;
""", """\t\t\t\tcase 'mapping': $wpdb->last_error=''; $rows=$wpdb->get_results( \"SELECT legacy_id,target_id,target_type,status,source_checksum,target_checksum,run_uuid,last_error_code,updated_at FROM {$t['map']} ORDER BY legacy_id ASC LIMIT 500\", ARRAY_A ); self::table_block( empty( $wpdb->last_error ) ? $rows : new WP_Error( 'snfla_admin_mapping_query_failed', 'Mapping ledger could not be read safely.' ) ); break;
\t\t\t\tcase 'conflicts': $wpdb->last_error=''; $rows=$wpdb->get_results( \"SELECT id,legacy_id,conflict_code,severity,status,run_uuid,created_at,resolved_at FROM {$t['conflicts']} ORDER BY status ASC,severity DESC,id DESC LIMIT 500\", ARRAY_A ); self::table_block( empty( $wpdb->last_error ) ? $rows : new WP_Error( 'snfla_admin_conflict_query_failed', 'Conflict ledger could not be read safely.' ) ); break;
\t\t\t\tcase 'redirects': $fallback=get_option( SNFLA_Schema::FALLBACK_OPTION, array() ); self::json_block( array( 'state' => $status['state'], 'fallback_evidence_valid' => SNFLA_Integrity::evidence_valid( $fallback ), 'fallback_window' => SNFLA_Integrity::evidence_valid( $fallback ) ? $fallback : array( 'invalid_evidence' => true ), 'policy' => 'Same-origin 302 redirects only during cutover/fallback; permanent redirects or gone responses must be handed to a verified canonical route owner before retirement.' ) ); break;
""",1)
rep('includes/class-snfla-admin.php', """\t\t\t\tcase 'retirement': self::json_block( array( 'required_confirmation' => SNFLA_Retirement::CONFIRMATION, 'evidence' => get_option( SNFLA_Schema::RETIREMENT_OPTION, array() ), 'source_deletion' => 'Never automatic' ) ); break;
""", """\t\t\t\tcase 'retirement': $retirement=get_option( SNFLA_Schema::RETIREMENT_OPTION, array() ); self::json_block( array( 'required_confirmation' => SNFLA_Retirement::CONFIRMATION, 'evidence_valid' => SNFLA_Integrity::evidence_valid( $retirement ), 'evidence' => SNFLA_Integrity::evidence_valid( $retirement ) ? $retirement : array( 'invalid_evidence' => true ), 'source_deletion' => 'Never automatic' ) ); break;
""",1)
rep('includes/class-snfla-admin.php', """\tprivate static function table_block( $rows ) {
\t\t$rows = is_array( $rows ) ? $rows : array();
""", """\tprivate static function table_block( $rows ) {
\t\tif ( is_wp_error( $rows ) ) { echo '<p class=\"notice notice-error\">' . esc_html( $rows->get_error_message() ) . '</p>'; return; }
\t\t$rows = is_array( $rows ) ? $rows : array();
""",1)

print('Applied sequential File 04 corrections for audit rounds 51-64.')
