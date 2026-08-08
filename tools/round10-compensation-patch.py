from pathlib import Path

future = Path('includes/class-snfla-future18.php')
s = future.read_text(encoding='utf-8')

def rep(old,new):
    global s
    if old not in s:
        raise SystemExit('Round10 compensation target missing: '+old[:140])
    s=s.replace(old,new,1)

rep("""\t\t$checkpoint = self::store_checkpoint( 'digital_twin', $twin );
\t\tif ( is_wp_error( $checkpoint ) ) {
\t\t\tupdate_option( self::TWIN_OPTION, $previous_twin, false );
\t\t\treturn $checkpoint;
\t\t}
\t\tif ( $actor_id > 0 && ! SNFLA_Audit::record( 'future18_digital_twin_completed', $actor_id, array( 'twin_checksum' => $twin['twin_checksum'], 'count' => count( $rows ), 'checkpoint_id' => $checkpoint['checkpoint_id'] ?? '' ), 'future18-twin:' . $twin['twin_checksum'] ) ) {
\t\t\tupdate_option( self::TWIN_OPTION, $previous_twin, false );
\t\t\tupdate_option( self::CHECKPOINTS_OPTION, $previous_checkpoints, false );
\t\t\treturn new WP_Error( 'snfla_twin_audit_failed', 'Digital Twin evidence was reverted because its audit event could not be persisted.', array( 'status' => 500 ) );
\t\t}""",
"""\t\t$checkpoint = self::store_checkpoint( 'digital_twin', $twin );
\t\tif ( is_wp_error( $checkpoint ) ) {
\t\t\t$restored = update_option( self::TWIN_OPTION, $previous_twin, false ) || get_option( self::TWIN_OPTION, array() ) === $previous_twin;
\t\t\tif ( ! $restored ) {
\t\t\t\tdo_action( 'snfla_operational_alert_v1', 'future18_twin_compensation_failed', 'blocker', array( 'original_error' => $checkpoint->get_error_code() ) );
\t\t\t\treturn new WP_Error( 'snfla_twin_compensation_failed', 'Digital Twin checkpoint failed and prior evidence could not be restored; manual repair is required.', array( 'status' => 500, 'original_error' => $checkpoint->get_error_code() ) );
\t\t\t}
\t\t\treturn $checkpoint;
\t\t}
\t\tif ( $actor_id > 0 && ! SNFLA_Audit::record( 'future18_digital_twin_completed', $actor_id, array( 'twin_checksum' => $twin['twin_checksum'], 'count' => count( $rows ), 'checkpoint_id' => $checkpoint['checkpoint_id'] ?? '' ), 'future18-twin:' . $twin['twin_checksum'] ) ) {
\t\t\t$twin_restored = update_option( self::TWIN_OPTION, $previous_twin, false ) || get_option( self::TWIN_OPTION, array() ) === $previous_twin;
\t\t\t$checkpoints_restored = update_option( self::CHECKPOINTS_OPTION, $previous_checkpoints, false ) || get_option( self::CHECKPOINTS_OPTION, array() ) === $previous_checkpoints;
\t\t\tif ( ! $twin_restored || ! $checkpoints_restored ) {
\t\t\t\tdo_action( 'snfla_operational_alert_v1', 'future18_twin_compensation_failed', 'blocker', array( 'twin_restored' => $twin_restored, 'checkpoints_restored' => $checkpoints_restored ) );
\t\t\t\treturn new WP_Error( 'snfla_twin_compensation_failed', 'Digital Twin audit failed and prior evidence could not be fully restored; manual repair is required.', array( 'status' => 500 ) );
\t\t\t}
\t\t\treturn new WP_Error( 'snfla_twin_audit_failed', 'Digital Twin evidence was reverted because its audit event could not be persisted.', array( 'status' => 500 ) );
\t\t}""")

rep("""\t\tif ( $actor_id > 0 && ! SNFLA_Audit::record( 'future18_shadow_read_completed', $actor_id, array( 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'shadow_checksum' => $result['shadow_checksum'] ), 'future18-shadow:' . $result['shadow_checksum'] ) ) {
\t\t\tupdate_option( self::SHADOW_OPTION, $previous, false );
\t\t\treturn new WP_Error( 'snfla_shadow_audit_failed', 'Shadow-read evidence was reverted because its audit event could not be persisted.', array( 'status' => 500 ) );
\t\t}""",
"""\t\tif ( $actor_id > 0 && ! SNFLA_Audit::record( 'future18_shadow_read_completed', $actor_id, array( 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'shadow_checksum' => $result['shadow_checksum'] ), 'future18-shadow:' . $result['shadow_checksum'] ) ) {
\t\t\t$restored = update_option( self::SHADOW_OPTION, $previous, false ) || get_option( self::SHADOW_OPTION, array() ) === $previous;
\t\t\tif ( ! $restored ) {
\t\t\t\tdo_action( 'snfla_operational_alert_v1', 'future18_shadow_compensation_failed', 'blocker', array( 'legacy_id' => $legacy_id, 'target_id' => $target_id ) );
\t\t\t\treturn new WP_Error( 'snfla_shadow_compensation_failed', 'Shadow-read audit failed and prior evidence could not be restored; manual repair is required.', array( 'status' => 500 ) );
\t\t\t}
\t\t\treturn new WP_Error( 'snfla_shadow_audit_failed', 'Shadow-read evidence was reverted because its audit event could not be persisted.', array( 'status' => 500 ) );
\t\t}""")

rep("""\t\t$current = get_option( self::CANARY_OPTION, array( 'approved_percent' => 0 ) );
\t\t$current_percent = absint( $current['approved_percent'] ?? 0 );
\t\t$current_index = array_search( $current_percent, array_merge( array( 0 ), $allowed ), true );""",
"""\t\t$current = get_option( self::CANARY_OPTION, array( 'approved_percent' => 0 ) );
\t\t$current_percent = absint( $current['approved_percent'] ?? 0 );
\t\tif ( ! in_array( $current_percent, array_merge( array( 0 ), $allowed ), true ) ) {
\t\t\t$blockers[] = 'canary_state_invalid';
\t\t\t$current_percent = 0;
\t\t}
\t\t$current_index = array_search( $current_percent, array_merge( array( 0 ), $allowed ), true );""")

rep("""\t\tif ( $actor_id > 0 && ! SNFLA_Audit::record( 'future18_canary_decision', $actor_id, $decision, 'future18-canary:' . $requested_percent . ':' . gmdate( 'YmdHis' ) ) ) {
\t\t\tif ( $approved ) { update_option( self::CANARY_OPTION, $previous, false ); }
\t\t\treturn new WP_Error( 'snfla_canary_audit_failed', 'The canary decision was reverted because its audit event could not be persisted.', array( 'status' => 500 ) );
\t\t}""",
"""\t\tif ( $actor_id > 0 && ! SNFLA_Audit::record( 'future18_canary_decision', $actor_id, $decision, 'future18-canary:' . $requested_percent . ':' . gmdate( 'YmdHis' ) ) ) {
\t\t\tif ( $approved ) {
\t\t\t\t$restored = update_option( self::CANARY_OPTION, $previous, false ) || get_option( self::CANARY_OPTION, array() ) === $previous;
\t\t\t\tif ( ! $restored ) {
\t\t\t\t\tdo_action( 'snfla_operational_alert_v1', 'future18_canary_compensation_failed', 'blocker', array( 'requested_percent' => $requested_percent, 'previous_percent' => $current_percent ) );
\t\t\t\t\treturn new WP_Error( 'snfla_canary_compensation_failed', 'Canary audit failed and the prior rollout state could not be restored; rollout must be treated as blocked pending manual repair.', array( 'status' => 500 ) );
\t\t\t\t}
\t\t\t}
\t\t\treturn new WP_Error( 'snfla_canary_audit_failed', 'The canary decision was reverted because its audit event could not be persisted.', array( 'status' => 500 ) );
\t\t}""")

future.write_text(s,encoding='utf-8')

hard=Path('includes/class-snfla-post-audit-hardening.php')
hs=hard.read_text(encoding='utf-8')
old="false !== strpos( $code, 'containment_failed' ) || false !== strpos( $code, 'run_finish_failed' )"
new="false !== strpos( $code, 'containment_failed' ) || false !== strpos( $code, 'compensation_failed' ) || false !== strpos( $code, 'run_finish_failed' )"
if old not in hs: raise SystemExit('Round10 HTTP compensation target missing')
hard.write_text(hs.replace(old,new,1),encoding='utf-8')

test=Path('tests/run-second-ten-round-hardening.py')
ts=test.read_text(encoding='utf-8')
needle="need(all(token in future for token in ['snfla_twin_persist_failed','snfla_twin_audit_failed','snfla_checkpoint_persist_failed','snfla_shadow_persist_failed','snfla_shadow_audit_failed','snfla_canary_persist_failed','snfla_canary_audit_failed']),'Round10: remaining Future18 twin/checkpoint/shadow/canary evidence must fail closed on persistence/audit failure.',fail)\n"
addition=needle+"need(all(token in future for token in ['snfla_twin_compensation_failed','snfla_shadow_compensation_failed','snfla_canary_compensation_failed','canary_state_invalid']) and \"compensation_failed\" in hard,'Round10: failed compensation and corrupted canary state must remain explicit HTTP-500/blocking conditions.',fail)\n"
if needle not in ts: raise SystemExit('Round10 compensation test target missing')
test.write_text(ts.replace(needle,addition,1),encoding='utf-8')

Path('tools/round10-compensation-patch.py').unlink()
Path('.github/workflows/round10-compensation-patch.yml').unlink()
