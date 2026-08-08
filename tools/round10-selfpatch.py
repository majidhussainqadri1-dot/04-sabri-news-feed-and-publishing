from pathlib import Path

p = Path('includes/class-snfla-future18.php')
s = p.read_text(encoding='utf-8')

def rep(old, new):
    global s
    if old not in s:
        raise SystemExit('Round10 patch target missing: ' + old[:120])
    s = s.replace(old, new, 1)

rep("""\t\t$twin['twin_checksum'] = SNFLA_Checksum::hash( $twin );
\t\tupdate_option( self::TWIN_OPTION, $twin, false );
\t\t$checkpoint = self::store_checkpoint( 'digital_twin', $twin );
\t\tif ( $actor_id > 0 ) { SNFLA_Audit::record( 'future18_digital_twin_completed', $actor_id, array( 'twin_checksum' => $twin['twin_checksum'], 'count' => count( $rows ), 'checkpoint_id' => $checkpoint['checkpoint_id'] ?? '' ), 'future18-twin:' . $twin['twin_checksum'] ); }
\t\t$twin['checkpoint'] = $checkpoint;
\t\treturn $twin;""",
"""\t\t$twin['twin_checksum'] = SNFLA_Checksum::hash( $twin );
\t\t$previous_twin = get_option( self::TWIN_OPTION, array() );
\t\t$previous_checkpoints = get_option( self::CHECKPOINTS_OPTION, array() );
\t\tif ( ! update_option( self::TWIN_OPTION, $twin, false ) && get_option( self::TWIN_OPTION, array() ) !== $twin ) {
\t\t\treturn new WP_Error( 'snfla_twin_persist_failed', 'Digital Twin evidence could not be persisted; no successful simulation evidence is reported.', array( 'status' => 500 ) );
\t\t}
\t\t$checkpoint = self::store_checkpoint( 'digital_twin', $twin );
\t\tif ( is_wp_error( $checkpoint ) ) {
\t\t\tupdate_option( self::TWIN_OPTION, $previous_twin, false );
\t\t\treturn $checkpoint;
\t\t}
\t\tif ( $actor_id > 0 && ! SNFLA_Audit::record( 'future18_digital_twin_completed', $actor_id, array( 'twin_checksum' => $twin['twin_checksum'], 'count' => count( $rows ), 'checkpoint_id' => $checkpoint['checkpoint_id'] ?? '' ), 'future18-twin:' . $twin['twin_checksum'] ) ) {
\t\t\tupdate_option( self::TWIN_OPTION, $previous_twin, false );
\t\t\tupdate_option( self::CHECKPOINTS_OPTION, $previous_checkpoints, false );
\t\t\treturn new WP_Error( 'snfla_twin_audit_failed', 'Digital Twin evidence was reverted because its audit event could not be persisted.', array( 'status' => 500 ) );
\t\t}
\t\t$twin['checkpoint'] = $checkpoint;
\t\treturn $twin;""")

rep("""\t\t$result = array( 'feature_id' => 'F04-FUT-008', 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'read_only' => true, 'dual_write' => false, 'fidelity' => $fidelity, 'provider' => SNFLA_Audit::redact( is_array( $provider ) ? $provider : array() ), 'checked_at_utc' => gmdate( 'Y-m-d H:i:s' ) );
\t\t$result['shadow_checksum'] = SNFLA_Checksum::hash( $result );
\t\tupdate_option( self::SHADOW_OPTION, $result, false );
\t\tif ( $actor_id > 0 ) { SNFLA_Audit::record( 'future18_shadow_read_completed', $actor_id, array( 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'shadow_checksum' => $result['shadow_checksum'] ), 'future18-shadow:' . $result['shadow_checksum'] ); }
\t\treturn $result;""",
"""\t\t$result = array( 'feature_id' => 'F04-FUT-008', 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'read_only' => true, 'dual_write' => false, 'fidelity' => $fidelity, 'provider' => SNFLA_Audit::redact( is_array( $provider ) ? $provider : array() ), 'checked_at_utc' => gmdate( 'Y-m-d H:i:s' ) );
\t\t$result['shadow_checksum'] = SNFLA_Checksum::hash( $result );
\t\t$previous = get_option( self::SHADOW_OPTION, array() );
\t\tif ( ! update_option( self::SHADOW_OPTION, $result, false ) && get_option( self::SHADOW_OPTION, array() ) !== $result ) {
\t\t\treturn new WP_Error( 'snfla_shadow_persist_failed', 'Shadow-read evidence could not be persisted; no successful evidence is reported.', array( 'status' => 500 ) );
\t\t}
\t\tif ( $actor_id > 0 && ! SNFLA_Audit::record( 'future18_shadow_read_completed', $actor_id, array( 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'shadow_checksum' => $result['shadow_checksum'] ), 'future18-shadow:' . $result['shadow_checksum'] ) ) {
\t\t\tupdate_option( self::SHADOW_OPTION, $previous, false );
\t\t\treturn new WP_Error( 'snfla_shadow_audit_failed', 'Shadow-read evidence was reverted because its audit event could not be persisted.', array( 'status' => 500 ) );
\t\t}
\t\treturn $result;""")

rep("""\t\t$approved = empty( $blockers );
\t\t$decision = array( 'feature_id' => 'F04-FUT-009', 'requested_percent' => $requested_percent, 'previous_percent' => $current_percent, 'approved' => $approved, 'approved_percent' => $approved ? $requested_percent : $current_percent, 'blockers' => array_values( array_unique( $blockers ) ), 'controller_only' => true, 'migration_invoked' => false, 'decided_at_utc' => gmdate( 'Y-m-d H:i:s' ) );
\t\tif ( $approved ) { update_option( self::CANARY_OPTION, $decision, false ); }
\t\tif ( $actor_id > 0 ) { SNFLA_Audit::record( 'future18_canary_decision', $actor_id, $decision, 'future18-canary:' . $requested_percent . ':' . gmdate( 'YmdHis' ) ); }
\t\treturn $decision;""",
"""\t\t$approved = empty( $blockers );
\t\t$decision = array( 'feature_id' => 'F04-FUT-009', 'requested_percent' => $requested_percent, 'previous_percent' => $current_percent, 'approved' => $approved, 'approved_percent' => $approved ? $requested_percent : $current_percent, 'blockers' => array_values( array_unique( $blockers ) ), 'controller_only' => true, 'migration_invoked' => false, 'decided_at_utc' => gmdate( 'Y-m-d H:i:s' ) );
\t\t$previous = get_option( self::CANARY_OPTION, array( 'approved_percent' => 0 ) );
\t\tif ( $approved && ! update_option( self::CANARY_OPTION, $decision, false ) && get_option( self::CANARY_OPTION, array() ) !== $decision ) {
\t\t\treturn new WP_Error( 'snfla_canary_persist_failed', 'The approved canary decision could not be persisted; rollout remains at the prior phase.', array( 'status' => 500 ) );
\t\t}
\t\tif ( $actor_id > 0 && ! SNFLA_Audit::record( 'future18_canary_decision', $actor_id, $decision, 'future18-canary:' . $requested_percent . ':' . gmdate( 'YmdHis' ) ) ) {
\t\t\tif ( $approved ) { update_option( self::CANARY_OPTION, $previous, false ); }
\t\t\treturn new WP_Error( 'snfla_canary_audit_failed', 'The canary decision was reverted because its audit event could not be persisted.', array( 'status' => 500 ) );
\t\t}
\t\treturn $decision;""")

rep("""\tprivate static function store_checkpoint( $kind, array $snapshot ) {
\t\t$checkpoint = array( 'checkpoint_id' => wp_generate_uuid4(), 'kind' => sanitize_key( $kind ), 'created_at_utc' => gmdate( 'Y-m-d H:i:s' ), 'snapshot' => SNFLA_Audit::redact( $snapshot ), 'non_mutating' => true );
\t\t$checkpoint['checkpoint_checksum'] = SNFLA_Checksum::hash( $checkpoint );
\t\t$list = get_option( self::CHECKPOINTS_OPTION, array() ); if ( ! is_array( $list ) ) { $list = array(); }
\t\t$list[] = $checkpoint; if ( count( $list ) > self::MAX_CHECKPOINTS ) { $list = array_slice( $list, -self::MAX_CHECKPOINTS ); }
\t\tupdate_option( self::CHECKPOINTS_OPTION, $list, false ); return array( 'checkpoint_id' => $checkpoint['checkpoint_id'], 'checkpoint_checksum' => $checkpoint['checkpoint_checksum'] );
\t}""",
"""\tprivate static function store_checkpoint( $kind, array $snapshot ) {
\t\t$checkpoint = array( 'checkpoint_id' => wp_generate_uuid4(), 'kind' => sanitize_key( $kind ), 'created_at_utc' => gmdate( 'Y-m-d H:i:s' ), 'snapshot' => SNFLA_Audit::redact( $snapshot ), 'non_mutating' => true );
\t\t$checkpoint['checkpoint_checksum'] = SNFLA_Checksum::hash( $checkpoint );
\t\t$list = get_option( self::CHECKPOINTS_OPTION, array() ); if ( ! is_array( $list ) ) { $list = array(); }
\t\t$list[] = $checkpoint; if ( count( $list ) > self::MAX_CHECKPOINTS ) { $list = array_slice( $list, -self::MAX_CHECKPOINTS ); }
\t\tif ( ! update_option( self::CHECKPOINTS_OPTION, $list, false ) && get_option( self::CHECKPOINTS_OPTION, array() ) !== $list ) {
\t\t\treturn new WP_Error( 'snfla_checkpoint_persist_failed', 'Replay checkpoint evidence could not be persisted.', array( 'status' => 500 ) );
\t\t}
\t\treturn array( 'checkpoint_id' => $checkpoint['checkpoint_id'], 'checkpoint_checksum' => $checkpoint['checkpoint_checksum'] );
\t}""")

p.write_text(s, encoding='utf-8')

# Extend the second-ten-round deterministic gate so this exact final defect class cannot regress.
t = Path('tests/run-second-ten-round-hardening.py')
ts = t.read_text(encoding='utf-8')
needle = "need('persistence_failed' in hard and 'audit_persistence_failed' in hard,'Redirect/citation proof durability must fail closed.',fail)\n"
addition = needle + "need(all(token in future for token in ['snfla_twin_persist_failed','snfla_twin_audit_failed','snfla_checkpoint_persist_failed','snfla_shadow_persist_failed','snfla_shadow_audit_failed','snfla_canary_persist_failed','snfla_canary_audit_failed']),'Round10: remaining Future18 twin/checkpoint/shadow/canary evidence must fail closed on persistence/audit failure.',fail)\n"
if needle not in ts:
    raise SystemExit('Round10 test insertion target missing')
t.write_text(ts.replace(needle, addition, 1), encoding='utf-8')

# Close Round 10 truthfully as a defect round and record the concrete correction.
a = Path('SECOND-TEN-ROUND-HARDENING-AUDIT.md')
asrc = a.read_text(encoding='utf-8')
asrc = asrc.replace('| 10 | Final fresh adversarial regression after Round 9 | **PENDING — not pre-certified.** |', '| 10 | Final fresh adversarial regression after Round 9 | **Defect.** Digital Twin, checkpoint, shadow-read and canary control could still report success after option/audit persistence failure. All remaining Future18 state/evidence writes now fail closed and roll back prior state where applicable. |', 1)
asrc = asrc.replace('**Round 10 status: PENDING.** It must be executed freshly on the fully corrected Round-9 source; it is not counted as passed merely because earlier regression gates are green.', '**Round 10 status: DEFECT FOUND AND CORRECTED.** Fresh final adversarial review found the remaining evidence-durability false-success paths; the affected controls were corrected and must pass exact-head regression before release.', 1)
a.write_text(asrc, encoding='utf-8')

st = Path('STATUS.md')
ss = st.read_text(encoding='utf-8')
ss = ss.replace('`source_status=v2.0.2-second-ten-round-review-in-progress`', '`source_status=v2.0.2-second-ten-round-review-complete-awaiting-exact-head-ci`', 1)
ss = ss.replace('Defects were found and corrected in **Rounds 2, 3, 4, 5, 6, 7, 8 and 9**. **Round 1 found no new defect. Round 10 is intentionally pending until the corrected Round-9 source receives a fresh final adversarial regression.**', 'Defects were found and corrected in **Rounds 2, 3, 4, 5, 6, 7, 8, 9 and 10**. **Round 1 found no new defect.** Round 10 found and corrected the remaining Digital Twin/checkpoint/shadow-read/canary persistence-and-audit false-success paths.', 1)
ss = ss.replace('**Corrective source implemented through Round 9**', '**Corrective source implemented through Round 10**', 1)
st.write_text(ss, encoding='utf-8')

# Remove one-time helper controls from the resulting source tree.
Path('tools/round10-selfpatch.py').unlink()
Path('.github/workflows/round10-selfpatch.yml').unlink()
