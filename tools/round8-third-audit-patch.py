from pathlib import Path
p=Path('includes/class-snfla-mapping.php')
s=p.read_text(encoding='utf-8')
old="""\t\t$now = gmdate( 'Y-m-d H:i:s' );
\t\t$source_checksum = array_key_exists( 'source_checksum', $data ) ? (string) $data['source_checksum'] : (string) ( $current['source_checksum'] ?? '' );
\t\t$target_checksum = array_key_exists( 'target_checksum', $data ) ? (string) $data['target_checksum'] : (string) ( $current['target_checksum'] ?? '' );
\t\t$row = array(
"""
new="""\t\t$now = gmdate( 'Y-m-d H:i:s' );
\t\t$source_checksum = array_key_exists( 'source_checksum', $data ) ? (string) $data['source_checksum'] : (string) ( $current['source_checksum'] ?? '' );
\t\t$target_checksum = array_key_exists( 'target_checksum', $data ) ? (string) $data['target_checksum'] : (string) ( $current['target_checksum'] ?? '' );
\t\t$interaction_json = $current['interaction_ledger_json'] ?? '{}';
\t\tif ( isset( $data['interaction_ledger'] ) ) {
\t\t\t$interaction_json = wp_json_encode( SNFLA_Audit::redact( (array) $data['interaction_ledger'] ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
\t\t\tif ( ! is_string( $interaction_json ) ) { return false; }
\t\t}
\t\t$row = array(
"""
if old not in s: raise SystemExit('round8 upsert prelude target missing')
s=s.replace(old,new,1)
old2="""\t\t\t'interaction_ledger_json' => isset( $data['interaction_ledger'] ) ? wp_json_encode( SNFLA_Audit::redact( $data['interaction_ledger'] ) ) : ( $current['interaction_ledger_json'] ?? '{}' ),
"""
new2="""\t\t\t'interaction_ledger_json' => $interaction_json,
"""
if old2 not in s: raise SystemExit('round8 upsert json target missing')
s=s.replace(old2,new2,1)
old3="""\t\t$created_by_migration = ! empty( $original['created_by_migration'] );
\t\t$contribution_count = max( 0, absint( $original['source_contribution'] ?? 0 ) );
\t\t$original = SNFLA_Audit::redact( $original );
\t\t$now = gmdate( 'Y-m-d H:i:s' );
\t\t$sql = $wpdb->prepare(
"""
new3="""\t\t$created_by_migration = ! empty( $original['created_by_migration'] );
\t\t$contribution_count = max( 0, absint( $original['source_contribution'] ?? 0 ) );
\t\t$original = SNFLA_Audit::redact( $original );
\t\t$original_json = wp_json_encode( $original, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
\t\tif ( ! is_string( $original_json ) ) { return false; }
\t\t$now = gmdate( 'Y-m-d H:i:s' );
\t\t$sql = $wpdb->prepare(
"""
if old3 not in s: raise SystemExit('round8 interaction json prelude target missing')
s=s.replace(old3,new3,1)
s=s.replace("\t\t\twp_json_encode( $original ),\n","\t\t\t$original_json,\n",1)
p.write_text(s,encoding='utf-8')
Path('tools/round8-third-audit-patch.py').unlink()
Path('.github/workflows/round8-third-audit-patch.yml').unlink()
