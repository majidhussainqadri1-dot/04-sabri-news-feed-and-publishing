#!/usr/bin/env python3
from pathlib import Path

def replace_once(path, old, new, label):
    p=Path(path); s=p.read_text(encoding='utf-8')
    if old not in s: raise SystemExit(f'Patch anchor missing: {label}')
    if s.count(old) != 1: raise SystemExit(f'Patch anchor not unique: {label} count={s.count(old)}')
    p.write_text(s.replace(old,new,1),encoding='utf-8')

helper='includes/class-snfla-plan-completion.php'
old="""\t\t$refs = array();
\t\t$attachments = get_children( array( 'post_parent' => $legacy_id, 'post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids', 'numberposts' => -1 ) );
\t\tforeach ( array_map( 'absint', (array) $attachments ) as $attachment_id ) {
\t\t\tif ( $attachment_id <= 0 ) { continue; }
\t\t\t$file = get_attached_file( $attachment_id, true );
\t\t\t$mime = (string) get_post_mime_type( $attachment_id );
\t\t\t$alt  = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
\t\t\t$refs[] = array(
\t\t\t\t'reference_id' => 'attachment:' . $attachment_id,
\t\t\t\t'type'         => 'attachment',
\t\t\t\t'attachment_id'=> $attachment_id,
\t\t\t\t'mime'         => sanitize_mime_type( $mime ),
\t\t\t\t'bytes'        => is_string( $file ) && is_file( $file ) ? (int) filesize( $file ) : 0,
\t\t\t\t'sha256'       => is_string( $file ) && is_file( $file ) ? hash_file( 'sha256', $file ) : '',
\t\t\t\t'alt_present'  => '' !== trim( $alt ),
\t\t\t\t'file_present' => is_string( $file ) && is_file( $file ),
\t\t\t);
\t\t}
"""
new="""\t\t$refs = array();
\t\tglobal $wpdb;
\t\t$cursor = 0;
\t\t$batch_size = 200;
\t\tdo {
\t\t\t$wpdb->last_error = '';
\t\t\t$attachment_ids = $wpdb->get_col(
\t\t\t\t$wpdb->prepare(
\t\t\t\t\t\"SELECT ID FROM {$wpdb->posts} WHERE post_parent=%d AND post_type='attachment' AND post_status='inherit' AND ID>%d ORDER BY ID ASC LIMIT %d\",
\t\t\t\t\t$legacy_id,
\t\t\t\t\t$cursor,
\t\t\t\t\t$batch_size
\t\t\t\t)
\t\t\t);
\t\t\tif ( ! empty( $wpdb->last_error ) || ! is_array( $attachment_ids ) ) {
\t\t\t\treturn new WP_Error( 'snfla_media_attachment_query_failed', 'Legacy attachment references could not be read safely.', array( 'status' => 500, 'legacy_id' => $legacy_id ) );
\t\t\t}
\t\t\tforeach ( array_map( 'absint', $attachment_ids ) as $attachment_id ) {
\t\t\t\tif ( $attachment_id <= $cursor ) { return new WP_Error( 'snfla_media_attachment_cursor_invalid', 'Legacy attachment traversal could not make forward progress.', array( 'status' => 500, 'legacy_id' => $legacy_id ) ); }
\t\t\t\t$cursor = $attachment_id;
\t\t\t\t$file = get_attached_file( $attachment_id, true );
\t\t\t\t$mime = (string) get_post_mime_type( $attachment_id );
\t\t\t\t$alt  = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
\t\t\t\t$refs[] = array(
\t\t\t\t\t'reference_id' => 'attachment:' . $attachment_id,
\t\t\t\t\t'type'         => 'attachment',
\t\t\t\t\t'attachment_id'=> $attachment_id,
\t\t\t\t\t'mime'         => sanitize_mime_type( $mime ),
\t\t\t\t\t'bytes'        => is_string( $file ) && is_file( $file ) ? (int) filesize( $file ) : 0,
\t\t\t\t\t'sha256'       => is_string( $file ) && is_file( $file ) ? hash_file( 'sha256', $file ) : '',
\t\t\t\t\t'alt_present'  => '' !== trim( $alt ),
\t\t\t\t\t'file_present' => is_string( $file ) && is_file( $file ),
\t\t\t\t);
\t\t\t}
\t\t} while ( count( $attachment_ids ) === $batch_size );
"""
replace_once(helper,old,new,'bounded keyset media traversal')
replace_once(helper,"\t\t$source_bytes = array_sum( $parts );\n","\t\t$source_bytes = $parts['publication_bytes'] + $parts['meta_bytes'] + $parts['comment_bytes'] + $parts['attachment_bytes'];\n",'storage bytes exclude attachment count')
print('Final v1.3.0 performance hardening applied.')
