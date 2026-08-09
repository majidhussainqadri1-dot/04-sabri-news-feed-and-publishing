#!/usr/bin/env python3
from pathlib import Path
import sys
r=int(sys.argv[1]); p=Path('includes/class-snfla-database.php'); s=p.read_text(encoding='utf-8')
def rep(old,new):
 global s
 if old not in s: raise SystemExit(f'R{r} target missing: {old[:100]!r}')
 s=s.replace(old,new,1)
if r==21:
 rep("""\tpublic static function schema_healthy() {
\t\tif ( SNFLA_SCHEMA_VERSION !== (string) get_option( 'snfla_schema_version', '' ) ) { return false; }
\t\t$health = get_option( 'snfla_schema_health', array() );
\t\treturn is_array( $health ) && ! empty( $health['ok'] );
\t}""",
"""\tpublic static function schema_healthy() {
\t\tstatic $verified_this_request = null;
\t\tif ( SNFLA_SCHEMA_VERSION !== (string) get_option( 'snfla_schema_version', '' ) ) { return false; }
\t\tif ( null !== $verified_this_request ) { return $verified_this_request; }
\t\t$verified = self::verify_schema();
\t\t$verified_this_request = ! is_wp_error( $verified );
\t\tself::persist_option( 'snfla_schema_health', array( 'ok' => $verified_this_request, 'code' => is_wp_error( $verified ) ? $verified->get_error_code() : '', 'checked_at_utc' => gmdate( 'Y-m-d H:i:s' ) ) );
\t\treturn $verified_this_request;
\t}""")
elif r==22:
 rep("""\t\t$stored_version = (string) get_option( 'snfla_schema_version', '' );
\t\t$health = get_option( 'snfla_schema_health', array() );
\t\tif ( SNFLA_SCHEMA_VERSION === $stored_version && is_array( $health ) && ! empty( $health['ok'] ) ) { return; }""",
"""\t\t$stored_version = (string) get_option( 'snfla_schema_version', '' );
\t\tif ( SNFLA_SCHEMA_VERSION === $stored_version && self::schema_healthy() ) { return; }""")
elif r==23:
 rep("""\t\tself::persist_option( 'snfla_schema_version', SNFLA_SCHEMA_VERSION );
\t\tself::persist_option( 'snfla_plugin_version', SNFLA_VERSION );""",
"""\t\tif ( ! self::persist_option( 'snfla_schema_version', SNFLA_SCHEMA_VERSION ) || ! self::persist_option( 'snfla_plugin_version', SNFLA_VERSION ) || ! self::persist_option( 'snfla_schema_health', array( 'ok' => true, 'code' => '', 'checked_at_utc' => gmdate( 'Y-m-d H:i:s' ) ) ) ) {
\t\t\tself::compensate_or_fail( $snapshot, $actor_id, new WP_Error( 'snfla_activation_version_evidence_failed', 'Activation version/schema-health evidence could not be persisted.' ) );
\t\t}""")
elif r==24:
 rep("""\t\tif ( 'retired' !== (string) get_option( SNFLA_Schema::STATE_OPTION, 'legacy_active' ) && ! wp_next_scheduled( 'snfla_daily_integrity_check' ) ) {
\t\t\twp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'snfla_daily_integrity_check' );
\t\t}""",
"""\t\tif ( 'retired' !== (string) get_option( SNFLA_Schema::STATE_OPTION, 'legacy_active' ) && ! wp_next_scheduled( 'snfla_daily_integrity_check' ) ) {
\t\t\t$scheduled = wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'snfla_daily_integrity_check', array(), true );
\t\t\tif ( is_wp_error( $scheduled ) || false === $scheduled ) {
\t\t\t\tself::compensate_or_fail( $snapshot, $actor_id, new WP_Error( 'snfla_integrity_schedule_failed', 'The mandatory daily integrity check could not be scheduled.' ) );
\t\t\t}
\t\t}""")
elif r==25:
 rep("""\tpublic static function public_page_quarantine_status() {
\t\t$rows = get_option( 'snfla_legacy_page_quarantine', array() );
\t\t$result = array();""",
"""\tpublic static function public_page_quarantine_status() {
\t\t$handover = get_option( self::HANDOVER_OPTION, array() );
\t\tif ( ! SNFLA_Integrity::evidence_valid( $handover ) ) { return array(); }
\t\t$rows = isset( $handover['quarantined_pages'] ) && is_array( $handover['quarantined_pages'] ) ? $handover['quarantined_pages'] : array();
\t\t$result = array();""")
 Path('tools/r21-r25-series.py').unlink();Path('.github/workflows/r21-r25-series.yml').unlink()
else: raise SystemExit('unsupported')
p.write_text(s,encoding='utf-8')
