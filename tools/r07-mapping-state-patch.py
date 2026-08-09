from pathlib import Path
p=Path('includes/class-snfla-mapping.php')
s=p.read_text(encoding='utf-8')
old="""\t\t$legacy_id = absint( $legacy_id );
\t\t$current = self::get_checked( $legacy_id );"""
new="""\t\t$legacy_id = absint( $legacy_id );
\t\tif ( $legacy_id <= 0 ) { return false; }
\t\t$current = self::get_checked( $legacy_id );"""
if old not in s: raise SystemExit('R07 legacy id target missing')
s=s.replace(old,new,1)
old2="""\t\t$row = array(
\t\t\t'target_id'               => absint( array_key_exists( 'target_id', $data ) ? $data['target_id'] : ( $current['target_id'] ?? 0 ) ),
\t\t\t'target_type'             => sanitize_key( array_key_exists( 'target_type', $data ) ? $data['target_type'] : ( $current['target_type'] ?? '' ) ),
\t\t\t'status'                  => sanitize_key( array_key_exists( 'status', $data ) ? $data['status'] : ( $current['status'] ?? 'pending' ) ),"""
new2="""\t\t$status = sanitize_key( array_key_exists( 'status', $data ) ? $data['status'] : ( $current['status'] ?? 'pending' ) );
\t\t$allowed_statuses = array( 'pending', 'migrating', 'migrated', 'interaction_pending', 'conflict', 'quarantined', 'publication_rolled_back_interactions_pending', 'rollback_conflict', 'rolled_back' );
\t\tif ( ! in_array( $status, $allowed_statuses, true ) ) { return false; }
\t\t$row = array(
\t\t\t'target_id'               => absint( array_key_exists( 'target_id', $data ) ? $data['target_id'] : ( $current['target_id'] ?? 0 ) ),
\t\t\t'target_type'             => sanitize_key( array_key_exists( 'target_type', $data ) ? $data['target_type'] : ( $current['target_type'] ?? '' ) ),
\t\t\t'status'                  => $status,"""
if old2 not in s: raise SystemExit('R07 status target missing')
s=s.replace(old2,new2,1)
p.write_text(s,encoding='utf-8')
Path('tools/r07-mapping-state-patch.py').unlink()
Path('.github/workflows/r07-mapping-state-patch.yml').unlink()
