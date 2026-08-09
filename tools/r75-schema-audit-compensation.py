from pathlib import Path
p=Path('includes/class-snfla-schema.php')
s=p.read_text(encoding='utf-8')
old="""\t\t\tif ( ! $recorded ) {
\t\t\t\tupdate_option( self::STATE_OPTION, $current, false );
\t\t\t\tupdate_option( self::STATE_VERSION_OPTION, $version, false );
\t\t\t\treturn new WP_Error( 'snfla_lifecycle_audit_failed', 'The lifecycle transition was rolled back because audit evidence could not be written.', array( 'status' => 500 ) );
\t\t\t}"""
new="""\t\t\tif ( ! $recorded ) {
\t\t\t\tupdate_option( self::STATE_OPTION, $current, false );
\t\t\t\tupdate_option( self::STATE_VERSION_OPTION, $version, false );
\t\t\t\t$restored = self::state() === $current && self::version() === $version;
\t\t\t\tif ( ! $restored ) { do_action( 'snfla_operational_alert_v1', 'lifecycle_audit_compensation_failed', 'critical', array( 'expected_state' => $current, 'expected_version' => $version ) ); }
\t\t\t\treturn new WP_Error( $restored ? 'snfla_lifecycle_audit_failed' : 'snfla_lifecycle_compensation_failed', $restored ? 'The lifecycle transition was rolled back because audit evidence could not be written.' : 'Audit persistence failed and the previous lifecycle state/version could not be restored exactly.', array( 'status' => 500, 'manual_recovery_required' => ! $restored ) );
\t\t\t}"""
if old not in s: raise SystemExit('R75 transition audit target missing')
s=s.replace(old,new,1)
old2="""\t\t\tif ( ! SNFLA_Audit::record( 'lifecycle_recovered_to_batch', $actor_id, $payload ) ) {
\t\t\t\tupdate_option( self::STATE_OPTION, $current, false );
\t\t\t\tupdate_option( self::STATE_VERSION_OPTION, $version, false );
\t\t\t\treturn new WP_Error( 'snfla_lifecycle_audit_failed', 'Rollback recovery was reverted because audit evidence could not be written.', array( 'status' => 500 ) );
\t\t\t}"""
new2="""\t\t\tif ( ! SNFLA_Audit::record( 'lifecycle_recovered_to_batch', $actor_id, $payload ) ) {
\t\t\t\tupdate_option( self::STATE_OPTION, $current, false );
\t\t\t\tupdate_option( self::STATE_VERSION_OPTION, $version, false );
\t\t\t\t$restored = self::state() === $current && self::version() === $version;
\t\t\t\tif ( ! $restored ) { do_action( 'snfla_operational_alert_v1', 'lifecycle_recovery_audit_compensation_failed', 'critical', array( 'expected_state' => $current, 'expected_version' => $version ) ); }
\t\t\t\treturn new WP_Error( $restored ? 'snfla_lifecycle_audit_failed' : 'snfla_lifecycle_compensation_failed', $restored ? 'Rollback recovery was reverted because audit evidence could not be written.' : 'Rollback-recovery audit failed and the prior lifecycle pair could not be restored exactly.', array( 'status' => 500, 'manual_recovery_required' => ! $restored ) );
\t\t\t}"""
if old2 not in s: raise SystemExit('R75 recovery audit target missing')
p.write_text(s.replace(old2,new2,1),encoding='utf-8')
Path('tools/r75-schema-audit-compensation.py').unlink();Path('.github/workflows/r75-schema-audit-compensation.yml').unlink()
