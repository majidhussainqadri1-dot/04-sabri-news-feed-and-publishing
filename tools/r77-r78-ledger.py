#!/usr/bin/env python3
from pathlib import Path
import sys
r=int(sys.argv[1]);p=Path('includes/class-snfla-mapping.php');s=p.read_text(encoding='utf-8')
def rep(old,new):
 global s
 if old not in s: raise SystemExit(f'R{r} target missing: {old[:120]!r}')
 s=s.replace(old,new,1)
if r==77:
 rep("""\t\t$legacy_id = absint( $legacy_id );
\t\t$target_id = absint( $target_id );
\t\t$source_row_id = absint( $source_row_id );
\t\t$canonical_row_id = absint( $canonical_row_id );
\t\t$kind = sanitize_key( $kind );
\t\t$synthetic_view = 'views' === $kind && 0 === $source_row_id;""",
"""\t\t$legacy_id = self::strict_positive_id( $legacy_id );
\t\t$target_id = self::strict_positive_id( $target_id );
\t\t$kind = sanitize_key( $kind );
\t\t$synthetic_view = 'views' === $kind && ( 0 === $source_row_id || '0' === $source_row_id );
\t\t$source_row_id = $synthetic_view ? 0 : self::strict_positive_id( $source_row_id );
\t\t$canonical_row_id = self::strict_positive_id( $canonical_row_id );""")
 rep("""\t\t$created_by_migration = ! empty( $original['created_by_migration'] );
\t\t$contribution_count = max( 0, absint( $original['source_contribution'] ?? 0 ) );""",
"""\t\tif ( isset( $original['created_by_migration'] ) && ! is_bool( $original['created_by_migration'] ) ) { return false; }
\t\t$created_by_migration = ! empty( $original['created_by_migration'] );
\t\t$contribution_count = self::strict_nonnegative_id( $original['source_contribution'] ?? 0 );
\t\tif ( null === $contribution_count ) { return false; }""")
elif r==78:
 rep("""\tpublic static function interaction_source_recorded( $legacy_id, $kind, $source_row_id ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$wpdb->last_error = '';
\t\t$value = $wpdb->get_var( $wpdb->prepare( \"SELECT id FROM {$t['interaction_ledger']} WHERE legacy_id=%d AND kind=%s AND source_row_id=%d AND status='active' LIMIT 1\", absint( $legacy_id ), sanitize_key( $kind ), absint( $source_row_id ) ) );""",
"""\tpublic static function interaction_source_recorded( $legacy_id, $kind, $source_row_id ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$legacy_id=self::strict_positive_id($legacy_id); $source_row_id=self::strict_positive_id($source_row_id); $kind=sanitize_key($kind);
\t\tif($legacy_id<=0||$source_row_id<=0||!in_array($kind,array('reactions','saves','views','reports'),true)){return new WP_Error('snfla_interaction_identity_invalid');}
\t\t$wpdb->last_error = '';
\t\t$value = $wpdb->get_var( $wpdb->prepare( \"SELECT id FROM {$t['interaction_ledger']} WHERE legacy_id=%d AND kind=%s AND source_row_id=%d AND status='active' LIMIT 1\", $legacy_id, $kind, $source_row_id ) );""")
 rep("""\tpublic static function interaction_original_by_canonical( $legacy_id, $kind, $canonical_row_id ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$wpdb->last_error = '';
\t\t$row = $wpdb->get_row( $wpdb->prepare( \"SELECT original_json,created_by_migration FROM {$t['interaction_ledger']} WHERE legacy_id=%d AND kind=%s AND canonical_row_id=%d AND status='active' ORDER BY id ASC LIMIT 1\", absint( $legacy_id ), sanitize_key( $kind ), absint( $canonical_row_id ) ), ARRAY_A );""",
"""\tpublic static function interaction_original_by_canonical( $legacy_id, $kind, $canonical_row_id ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$legacy_id=self::strict_positive_id($legacy_id); $canonical_row_id=self::strict_positive_id($canonical_row_id); $kind=sanitize_key($kind);
\t\tif($legacy_id<=0||$canonical_row_id<=0||!in_array($kind,array('reactions','saves','views','reports'),true)){return new WP_Error('snfla_interaction_identity_invalid');}
\t\t$wpdb->last_error = '';
\t\t$row = $wpdb->get_row( $wpdb->prepare( \"SELECT original_json,created_by_migration FROM {$t['interaction_ledger']} WHERE legacy_id=%d AND kind=%s AND canonical_row_id=%d AND status='active' ORDER BY id ASC LIMIT 1\", $legacy_id, $kind, $canonical_row_id ), ARRAY_A );""")
 rep("""\t\t$legacy_id = absint( $legacy_id );
\t\t$after_id = absint( $after_id );
\t\t$limit = min( 2000, max( 1, absint( $limit ) ) );
\t\t$kind = sanitize_key( $kind );
\t\t$status = sanitize_key( $status );""",
"""\t\t$legacy_id = self::strict_positive_id( $legacy_id );
\t\t$after_id = self::strict_nonnegative_id( $after_id );
\t\t$kind = sanitize_key( $kind );
\t\t$status = sanitize_key( $status );
\t\tif ( $legacy_id<=0 || null===$after_id || !is_int($limit) || $limit<1 || $limit>2000 || (''!==$kind&&!in_array($kind,array('reactions','saves','views','reports'),true)) || (''!==$status&&!in_array($status,array('active','rolled_back'),true)) ) { return new WP_Error('snfla_interaction_query_identity_invalid'); }""")
 rep("""\t\t$value = $wpdb->get_var(
\t\t\t$wpdb->prepare(
\t\t\t\t\"SELECT COALESCE(SUM(contribution_count),0) FROM {$t['interaction_ledger']} WHERE legacy_id=%d AND kind=%s AND canonical_row_id=%d AND status='active'\",
\t\t\t\tabsint( $legacy_id ),
\t\t\t\tsanitize_key( $kind ),
\t\t\t\tabsint( $canonical_row_id )
\t\t\t)
\t\t);""",
"""\t\t$legacy_id=self::strict_positive_id($legacy_id); $canonical_row_id=self::strict_positive_id($canonical_row_id); $kind=sanitize_key($kind);
\t\tif($legacy_id<=0||$canonical_row_id<=0||!in_array($kind,array('reactions','saves','views','reports'),true)){return new WP_Error('snfla_interaction_identity_invalid');}
\t\t$value = $wpdb->get_var(
\t\t\t$wpdb->prepare(
\t\t\t\t\"SELECT COALESCE(SUM(contribution_count),0) FROM {$t['interaction_ledger']} WHERE legacy_id=%d AND kind=%s AND canonical_row_id=%d AND status='active'\",
\t\t\t\t$legacy_id, $kind, $canonical_row_id
\t\t\t)
\t\t);""")
 rep("""\t\t$limit = min( 2000, max( 1, absint( $limit ) ) );
\t\t$sql = $wpdb->prepare(""",
"""\t\t$legacy_id=self::strict_positive_id($legacy_id); $after_first_id=self::strict_nonnegative_id($after_first_id);
\t\tif($legacy_id<=0||null===$after_first_id||!is_int($limit)||$limit<1||$limit>2000){return new WP_Error('snfla_interaction_query_identity_invalid');}
\t\t$sql = $wpdb->prepare(""")
 s=s.replace("\t\t\tabsint( $legacy_id ),\n\t\t\tabsint( $after_first_id ),\n\t\t\t$limit","\t\t\t$legacy_id,\n\t\t\t$after_first_id,\n\t\t\t$limit",1)
 rep("""\tpublic static function mark_interaction_ledger_rolled_back( $legacy_id ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\treturn false !== $wpdb->query( $wpdb->prepare( \"UPDATE {$t['interaction_ledger']} SET status='rolled_back',updated_at=%s WHERE legacy_id=%d AND status='active'\", gmdate( 'Y-m-d H:i:s' ), absint( $legacy_id ) ) );
\t}""",
"""\tpublic static function mark_interaction_ledger_rolled_back( $legacy_id ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$legacy_id=self::strict_positive_id($legacy_id); if($legacy_id<=0){return false;}
\t\t$wpdb->last_error=''; $result=$wpdb->query( $wpdb->prepare( \"UPDATE {$t['interaction_ledger']} SET status='rolled_back',updated_at=%s WHERE legacy_id=%d AND status='active'\", gmdate( 'Y-m-d H:i:s' ), $legacy_id ) );
\t\treturn false!==$result && empty($wpdb->last_error);
\t}""")
 Path('tools/r77-r78-ledger.py').unlink();Path('.github/workflows/r77-r78-ledger.yml').unlink()
else: raise SystemExit('unsupported')
p.write_text(s,encoding='utf-8')
