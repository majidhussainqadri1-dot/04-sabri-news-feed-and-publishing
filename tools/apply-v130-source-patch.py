#!/usr/bin/env python3
from pathlib import Path

def replace_once(path, old, new, label):
    p=Path(path); s=p.read_text(encoding='utf-8')
    if old not in s: raise SystemExit(f'Patch anchor missing: {label}')
    if s.count(old) != 1: raise SystemExit(f'Patch anchor not unique: {label} count={s.count(old)}')
    p.write_text(s.replace(old,new,1),encoding='utf-8')

migration='includes/class-snfla-migration.php'
replace_once(migration,"\t\t\tif ( is_wp_error( $streamed ) ) { SNFLA_Mapping::clear_dry_run_rows( $run_uuid ); return $streamed; }\n\t\t\t$report = array(\n","\t\t\tif ( is_wp_error( $streamed ) ) { SNFLA_Mapping::clear_dry_run_rows( $run_uuid ); return $streamed; }\n\t\t\t$planning = SNFLA_Plan_Completion::dry_run_estimates( $sample, $totals );\n\t\t\tif ( is_wp_error( $planning ) ) { SNFLA_Mapping::clear_dry_run_rows( $run_uuid ); return $planning; }\n\t\t\t$report = array(\n",'dry-run planning insertion')
replace_once(migration,"\t\t\t\t'interaction_provider' => SNFLA_File21_Adapter::INTERACTION_PROVIDER,\n\t\t\t);\n","\t\t\t\t'interaction_provider' => SNFLA_File21_Adapter::INTERACTION_PROVIDER,\n\t\t\t\t'estimated_dispositions' => $planning['estimated_dispositions'],\n\t\t\t\t'storage_estimate'       => $planning['storage_estimate'],\n\t\t\t\t'time_estimate'          => $planning['time_estimate'],\n\t\t\t\t'sample_diffs'           => $planning['sample_diffs'],\n\t\t\t);\n",'dry-run planning fields')
replace_once(migration,"\t\t$author_id = absint( $post->post_author );\n\t\tif ( $author_id <= 0 || ! get_userdata( $author_id ) ) {\n\t\t\t$codes[] = 'author_missing';\n\t\t} elseif ( in_array( sanitize_key( (string) $post->post_status ), array( 'publish', 'future' ), true ) && ! SNFLA_File21_Adapter::public_author_eligible( $author_id ) ) {\n\t\t\t$codes[] = 'public_author_no_longer_eligible';\n\t\t}\n","\t\t$author_id = absint( $post->post_author );\n\t\t$author_preflight = SNFLA_Plan_Completion::authorship_preflight( $legacy_id, $author_id );\n\t\tif ( is_wp_error( $author_preflight ) ) {\n\t\t\t$codes[] = $author_preflight->get_error_code();\n\t\t} elseif ( in_array( sanitize_key( (string) $post->post_status ), array( 'publish', 'future' ), true ) && ! SNFLA_File21_Adapter::public_author_eligible( absint( $author_preflight['user_id'] ?? 0 ) ) ) {\n\t\t\t$codes[] = 'public_author_no_longer_eligible';\n\t\t}\n",'immutable author preflight')
old_meta="""\t\t$governed_meta = array(
\t\t\t'_snp_video_url'      => 'legacy_video_url_requires_canonical_media_mapping',
\t\t\t'_snp_tags'           => 'legacy_tag_metadata_requires_canonical_mapping',
\t\t\t'_snp_language'       => 'legacy_language_requires_canonical_mapping',
\t\t\t'_snp_featured'       => 'legacy_featured_flag_requires_canonical_policy',
\t\t\t'_snp_pinned'         => 'legacy_pinned_flag_requires_canonical_policy',
\t\t\t'_snp_media_manifest' => 'legacy_media_manifest_requires_canonical_mapping',
\t\t\t'_snp_source_ledger'  => 'legacy_source_ledger_requires_canonical_mapping',
\t\t);
\t\tforeach ( $governed_meta as $meta_key => $conflict_code ) {
\t\t\t$value   = get_post_meta( $legacy_id, $meta_key, true );
\t\t\t$present = is_array( $value ) ? ! empty( $value ) : ( is_object( $value ) || '' !== trim( (string) $value ) );
\t\t\tif ( $present ) {
\t\t\t\t$codes[] = $conflict_code;
\t\t\t}
\t\t}
"""
new_meta="""\t\t$media_preflight = SNFLA_Plan_Completion::media_preflight( $legacy_id );
\t\tif ( is_wp_error( $media_preflight ) ) { $codes[] = $media_preflight->get_error_code(); }
\t\t$governed_meta = array(
\t\t\t'_snp_tags'           => 'legacy_tag_metadata_requires_canonical_mapping',
\t\t\t'_snp_language'       => 'legacy_language_requires_canonical_mapping',
\t\t\t'_snp_featured'       => 'legacy_featured_flag_requires_canonical_policy',
\t\t\t'_snp_pinned'         => 'legacy_pinned_flag_requires_canonical_policy',
\t\t);
\t\tforeach ( $governed_meta as $meta_key => $conflict_code ) {
\t\t\t$value   = get_post_meta( $legacy_id, $meta_key, true );
\t\t\t$present = is_array( $value ) ? ! empty( $value ) : ( is_object( $value ) || '' !== trim( (string) $value ) );
\t\t\tif ( $present ) { $codes[] = $conflict_code; }
\t\t}
"""
replace_once(migration,old_meta,new_meta,'governed media/reference preflight')
replace_once(migration,"\t\t$attachments = self::safe_count_query( $wpdb->prepare( \"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_parent=%d\", $legacy_id ), 'legacy_attachment_count_failed' );\n\t\tif ( is_wp_error( $attachments ) ) { $codes[] = $attachments->get_error_code(); } elseif ( $attachments > 0 ) { $codes[] = 'unmapped_attachment_relationships'; }\n","\t\t$attachments = self::safe_count_query( $wpdb->prepare( \"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_parent=%d\", $legacy_id ), 'legacy_attachment_count_failed' );\n\t\tif ( is_wp_error( $attachments ) ) { $codes[] = $attachments->get_error_code(); } elseif ( $attachments > 0 && is_wp_error( $media_preflight ) ) { $codes[] = 'unmapped_attachment_relationships'; }\n",'attachment conflict governed by canonical media preflight')
helper='includes/class-snfla-plan-completion.php'
replace_once(helper,"""\t\t$attachment_bytes = 0;
\t\t$attachments = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_query' => array(), 'no_found_rows' => true ) );
\t\tforeach ( array_map( 'absint', (array) $attachments ) as $attachment_id ) {
\t\t\t$parent = absint( wp_get_post_parent_id( $attachment_id ) );
\t\t\tif ( $parent <= 0 || SNFLA_Inventory::LEGACY_POST_TYPE !== get_post_type( $parent ) ) { continue; }
\t\t\t$file = get_attached_file( $attachment_id, true );
\t\t\tif ( is_string( $file ) && is_file( $file ) ) { $attachment_bytes += max( 0, (int) filesize( $file ) ); }
\t\t}
\t\t$parts['attachment_bytes'] = $attachment_bytes;
""","""\t\t$wpdb->last_error = '';
\t\t$attachment_count = $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM {$wpdb->posts} a INNER JOIN {$wpdb->posts} p ON p.ID=a.post_parent WHERE a.post_type='attachment' AND p.post_type=%s\", SNFLA_Inventory::LEGACY_POST_TYPE ) );
\t\tif ( ! empty( $wpdb->last_error ) || ! is_numeric( $attachment_count ) ) { return new WP_Error( 'snfla_dry_run_attachment_count_failed', 'Legacy attachment counts could not be measured safely.', array( 'status' => 500 ) ); }
\t\t$attachment_count = max( 0, (int) $attachment_count );
\t\t$attachment_bytes = apply_filters( 'snfla_storage_estimate_media_bytes_v1', null, $attachment_count, SNFLA_Inventory::locked() );
\t\tif ( $attachment_count > 0 && ! is_numeric( $attachment_bytes ) ) { return new WP_Error( 'snfla_media_storage_estimate_provider_required', 'A bounded storage provider estimate is required for legacy attachments; File 04 will not perform an unbounded filesystem scan.', array( 'status' => 412, 'attachment_count' => $attachment_count ) ); }
\t\t$parts['attachment_bytes'] = max( 0, (int) $attachment_bytes );
\t\t$parts['attachment_count'] = $attachment_count;
""",'bounded media storage estimate')
replace_once(helper,"\t\t$status = is_wp_error( $response ) ? (int) ( $response->get_error_data()['status'] ?? 500 ) : ( $response instanceof WP_REST_Response ? $response->get_status() : 200 );\n","\t\tif ( is_wp_error( $response ) ) {\n\t\t\t$error_data = $response->get_error_data();\n\t\t\t$status = is_array( $error_data ) ? (int) ( $error_data['status'] ?? 500 ) : 500;\n\t\t} else {\n\t\t\t$status = $response instanceof WP_REST_Response ? $response->get_status() : 200;\n\t\t}\n",'robust REST error metric status')
replace_once(helper,"\t\tif ( ! is_array( $analysis ) || ! SNFLA_Integrity::report_checksum_valid( array_merge( $analysis, array( 'report_checksum' => $analysis['analysis_checksum'] ?? '' ) ) ) ) {\n\t\t\treturn array();\n\t\t}\n","\t\tif ( ! is_array( $analysis ) || empty( $analysis['analysis_checksum'] ) ) { return array(); }\n\t\t$expected_checksum = strtolower( (string) $analysis['analysis_checksum'] );\n\t\t$checksum_payload = $analysis;\n\t\tunset( $checksum_payload['analysis_checksum'] );\n\t\tif ( ! preg_match( '/^[a-f0-9]{64}$/', $expected_checksum ) || ! hash_equals( $expected_checksum, SNFLA_Checksum::hash( $checksum_payload ) ) ) { return array(); }\n",'dry-run analysis checksum validation')
print('Reviewed v1.3.0 source patch applied.')
