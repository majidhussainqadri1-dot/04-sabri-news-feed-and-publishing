from pathlib import Path

p = Path('includes/class-snfla-future18.php')
s = p.read_text()
old = """\t\t$all = get_option( self::RECEIPTS_OPTION, array() ); if ( ! is_array( $all ) ) { $all = array(); }
\t\t$all[] = $receipt; if ( count( $all ) > self::MAX_RECEIPTS ) { $all = array_slice( $all, -self::MAX_RECEIPTS ); }
\t\tupdate_option( self::RECEIPTS_OPTION, $all, false );
\t\tSNFLA_Audit::record( 'future18_receipt_created', $actor_id, array( 'receipt_id' => $receipt['receipt_id'], 'receipt_checksum' => $receipt['receipt_checksum'], 'operation' => $operation ), 'future18-receipt:' . $receipt['receipt_id'] );
\t\treturn $receipt;"""
new = """\t\t$previous = get_option( self::RECEIPTS_OPTION, array() );
\t\t$all = is_array( $previous ) ? $previous : array();
\t\t$all[] = $receipt; if ( count( $all ) > self::MAX_RECEIPTS ) { $all = array_slice( $all, -self::MAX_RECEIPTS ); }
\t\tif ( ! update_option( self::RECEIPTS_OPTION, $all, false ) && get_option( self::RECEIPTS_OPTION, array() ) !== $all ) {
\t\t\treturn new WP_Error( 'snfla_receipt_persist_failed', 'The cryptographic receipt could not be persisted; no success is reported.', array( 'status' => 500 ) );
\t\t}
\t\tif ( ! SNFLA_Audit::record( 'future18_receipt_created', $actor_id, array( 'receipt_id' => $receipt['receipt_id'], 'receipt_checksum' => $receipt['receipt_checksum'], 'operation' => $operation ), 'future18-receipt:' . $receipt['receipt_id'] ) ) {
\t\t\tupdate_option( self::RECEIPTS_OPTION, $previous, false );
\t\t\treturn new WP_Error( 'snfla_receipt_audit_failed', 'The cryptographic receipt was reverted because its audit event could not be persisted.', array( 'status' => 500 ) );
\t\t}
\t\treturn $receipt;"""
if old not in s:
    raise SystemExit('round6 receipt target missing')
p.write_text(s.replace(old, new, 1))
Path('tools/round6-selfpatch.py').unlink()
Path('.github/workflows/round6-selfpatch.yml').unlink()
