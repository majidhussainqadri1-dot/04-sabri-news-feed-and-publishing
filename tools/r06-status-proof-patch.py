from pathlib import Path
p=Path('includes/class-snfla-rest.php')
s=p.read_text(encoding='utf-8')
old="""\t\t$inventory = SNFLA_Inventory::locked();
\t\t$dry = get_option( SNFLA_Schema::DRY_RUN_OPTION, array() );
\t\t$reconciliation = SNFLA_Reconciliation::report();
\t\t$data = array("""
new="""\t\t$inventory = SNFLA_Inventory::locked();
\t\t$dry = get_option( SNFLA_Schema::DRY_RUN_OPTION, array() );
\t\t$reconciliation = SNFLA_Reconciliation::report();
\t\t$dry_trusted = SNFLA_Integrity::report_checksum_valid( $dry )
\t\t\t&& ! empty( $dry['report_checksum'] )
\t\t\t&& SNFLA_Audit::has_event( 'lifecycle_transitioned', '', 'report_checksum', (string) $dry['report_checksum'] );
\t\t$reconciliation_uuid = sanitize_text_field( (string) ( $reconciliation['report_uuid'] ?? '' ) );
\t\t$reconciliation_trusted = SNFLA_Integrity::report_checksum_valid( $reconciliation )
\t\t\t&& '' !== $reconciliation_uuid
\t\t\t&& SNFLA_Audit::has_event( 'reconciliation_completed', 'reconciliation:' . $reconciliation_uuid, 'report_checksum', (string) ( $reconciliation['report_checksum'] ?? '' ) );
\t\t$data = array("""
if old not in s: raise SystemExit('R06 status prelude target missing')
s=s.replace(old,new,1)
old2="""\t\t\t'dry_run'         => empty( $dry ) ? array() : array( 'created_at_utc' => $dry['created_at_utc'] ?? '', 'candidate_count' => $dry['candidate_count'] ?? 0, 'conflict_count' => absint( $dry['conflict_count'] ?? 0 ), 'eligible_count' => absint( $dry['eligible_count'] ?? 0 ), 'complete_scan' => ! empty( $dry['complete_scan'] ), 'report_checksum' => $dry['report_checksum'] ?? '' ),
\t\t\t'reconciliation'  => empty( $reconciliation ) ? array() : array( 'green' => ! empty( $reconciliation['green'] ), 'source_total' => $reconciliation['source_total'] ?? 0, 'verified_mappings' => $reconciliation['verified_mappings'] ?? 0, 'open_conflicts' => $reconciliation['open_conflicts'] ?? 0, 'report_checksum' => $reconciliation['report_checksum'] ?? '' ),"""
new2="""\t\t\t'dry_run'         => $dry_trusted ? array( 'evidence_valid' => true, 'created_at_utc' => $dry['created_at_utc'] ?? '', 'candidate_count' => $dry['candidate_count'] ?? 0, 'conflict_count' => absint( $dry['conflict_count'] ?? 0 ), 'eligible_count' => absint( $dry['eligible_count'] ?? 0 ), 'complete_scan' => ! empty( $dry['complete_scan'] ), 'report_checksum' => $dry['report_checksum'] ?? '' ) : array( 'evidence_valid' => false ),
\t\t\t'reconciliation'  => $reconciliation_trusted ? array( 'evidence_valid' => true, 'green' => ! empty( $reconciliation['green'] ), 'source_total' => $reconciliation['source_total'] ?? 0, 'verified_mappings' => $reconciliation['verified_mappings'] ?? 0, 'open_conflicts' => $reconciliation['open_conflicts'] ?? 0, 'report_checksum' => $reconciliation['report_checksum'] ?? '' ) : array( 'evidence_valid' => false ),"""
if old2 not in s: raise SystemExit('R06 status evidence target missing')
s=s.replace(old2,new2,1)
p.write_text(s,encoding='utf-8')
Path('tools/r06-status-proof-patch.py').unlink()
Path('.github/workflows/r06-status-proof-patch.yml').unlink()
