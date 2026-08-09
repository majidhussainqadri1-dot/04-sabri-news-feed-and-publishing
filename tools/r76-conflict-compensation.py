from pathlib import Path
p=Path('includes/class-snfla-mapping.php')
s=p.read_text(encoding='utf-8')
old="""\t\t\t$reverted = $wpdb->update( $t['conflicts'], array( 'status' => 'open', 'resolved_at' => null ), array( 'id' => $conflict_id, 'status' => 'resolved' ), array( '%s', null ), array( '%d', '%s' ) );
\t\t\treturn false !== $reverted && empty( $wpdb->last_error ) && 1 === (int) $reverted ? false : false;"""
new="""\t\t\t$reverted = $wpdb->update( $t['conflicts'], array( 'status' => 'open', 'resolved_at' => null ), array( 'id' => $conflict_id, 'status' => 'resolved' ), array( '%s', null ), array( '%d', '%s' ) );
\t\t\t$restored = false !== $reverted && empty( $wpdb->last_error ) && 1 === (int) $reverted;
\t\t\tif ( ! $restored ) { do_action( 'snfla_operational_alert_v1', 'conflict_resolution_compensation_failed', 'critical', array( 'conflict_id' => $conflict_id ) ); }
\t\t\treturn false;"""
if old not in s: raise SystemExit('R76 resolve compensation target missing')
s=s.replace(old,new,1)
old2="""\t\t\t$compensated = $wpdb->query( $wpdb->prepare( \"UPDATE {$t['conflicts']} SET status='open',resolved_at=NULL WHERE resolved_at=%s AND id IN ({$placeholders})\", $args ) );
\t\t\treturn false !== $compensated && empty( $wpdb->last_error ) && (int) $compensated === count( $ids ) ? false : false;"""
new2="""\t\t\t$compensated = $wpdb->query( $wpdb->prepare( \"UPDATE {$t['conflicts']} SET status='open',resolved_at=NULL WHERE resolved_at=%s AND id IN ({$placeholders})\", $args ) );
\t\t\t$restored = false !== $compensated && empty( $wpdb->last_error ) && (int) $compensated === count( $ids );
\t\t\tif ( ! $restored ) { do_action( 'snfla_operational_alert_v1', 'conflict_supersession_compensation_failed', 'critical', array( 'run_uuid_digest' => hash( 'sha256', $run_uuid ), 'affected_count' => count( $ids ) ) ); }
\t\t\treturn false;"""
if old2 not in s: raise SystemExit('R76 supersede compensation target missing')
s=s.replace(old2,new2,1)
old3="""\t\t\t$compensated = $wpdb->query( $wpdb->prepare( \"UPDATE {$t['conflicts']} SET status='open',resolved_at=NULL WHERE resolved_at=%s AND id IN ({$revert_placeholders})\", $revert_args ) );
\t\t\treturn false !== $compensated && empty( $wpdb->last_error ) && (int) $compensated === count( $ids ) ? false : false;"""
new3="""\t\t\t$compensated = $wpdb->query( $wpdb->prepare( \"UPDATE {$t['conflicts']} SET status='open',resolved_at=NULL WHERE resolved_at=%s AND id IN ({$revert_placeholders})\", $revert_args ) );
\t\t\t$restored = false !== $compensated && empty( $wpdb->last_error ) && (int) $compensated === count( $ids );
\t\t\tif ( ! $restored ) { do_action( 'snfla_operational_alert_v1', 'system_conflict_compensation_failed', 'critical', array( 'legacy_id' => $legacy_id, 'affected_count' => count( $ids ) ) ); }
\t\t\treturn false;"""
if old3 not in s: raise SystemExit('R76 system compensation target missing')
s=s.replace(old3,new3,1)
p.write_text(s,encoding='utf-8')
Path('tools/r76-conflict-compensation.py').unlink();Path('.github/workflows/r76-conflict-compensation.yml').unlink()
