#!/usr/bin/env python3
from pathlib import Path
import sys
r=int(sys.argv[1]);p=Path('includes/class-snfla-interaction-provider.php');s=p.read_text(encoding='utf-8')
def rep(old,new):
 global s
 if old not in s: raise SystemExit(f'R{r} target missing: {old[:120]!r}')
 s=s.replace(old,new,1)
if r==45:
 rep("""\tpublic static function resume( $actor_id, $legacy_id, $max_records = self::DEFAULT_RECORD_BUDGET ) {
\t\t$legacy_id = absint( $legacy_id );
\t\t$actor_id  = absint( $actor_id );""",
"""\tpublic static function resume( $actor_id, $legacy_id, $max_records = self::DEFAULT_RECORD_BUDGET ) {
\t\t$legacy_id = self::strict_positive_id( $legacy_id );
\t\t$actor_id  = self::strict_positive_id( $actor_id );
\t\tif ( $legacy_id <= 0 || $actor_id <= 0 ) { return new WP_Error( 'snfla_interaction_resume_identity_invalid', 'Canonical positive actor and legacy IDs are required.', array( 'status' => 400 ) ); }""")
elif r==46:
 rep("""\tprivate static function budget( $value ) {
\t\treturn min( self::MAX_RECORD_BUDGET, max( self::PAGE_SIZE, absint( $value ) ) );
\t}""",
"""\tprivate static function budget( $value ) {
\t\tif ( ! is_int( $value ) || $value < 1 || $value > self::MAX_RECORD_BUDGET ) { return 0; }
\t\treturn max( self::PAGE_SIZE, $value );
\t}""")
 rep("""\tpublic static function migrate( array $context ) {
\t\treturn self::run(""",
"""\tpublic static function migrate( array $context ) {
\t\t$budget = self::budget( $context['max_records'] ?? self::DEFAULT_RECORD_BUDGET );
\t\tif ( $budget <= 0 ) { return array( 'status' => 'failed', 'migrated_records' => 0, 'migrated_metrics' => array(), 'skipped_records' => 0, 'processed_records' => 0, 'remaining' => true, 'errors' => array( 'interaction_budget_invalid' ) ); }
\t\treturn self::run(""")
 rep("""\t\t\tself::strict_positive_id( $context['actor_id'] ?? 0 ),
\t\t\tself::budget( $context['max_records'] ?? self::DEFAULT_RECORD_BUDGET )""",
"""\t\t\tself::strict_positive_id( $context['actor_id'] ?? 0 ),
\t\t\t$budget""")
 rep("""\t\t\t$result = self::run( $legacy_id, $target_id, $actor_id, self::budget( $max_records ) );
\t\t\tif ( is_wp_error( $result ) ) {""",
"""\t\t\t$budget = self::budget( $max_records );
\t\t\tif ( $budget <= 0 ) { return new WP_Error( 'snfla_interaction_budget_invalid', 'Interaction record budget must be an integer within the supported bound.', array( 'status' => 400 ) ); }
\t\t\t$result = self::run( $legacy_id, $target_id, $actor_id, $budget );
\t\t\tif ( is_wp_error( $result ) ) {""")
elif r==47:
 rep("""\t\t\t\tforeach ( $rows as $row ) {
\t\t\t\t\t$source_row_id = absint( $row['id'] ?? 0 );""",
"""\t\t\t\tforeach ( $rows as $row ) {
\t\t\t\t\t$source_row_id = self::strict_positive_id( $row['id'] ?? 0 );""")
elif r==48:
 rep("""\t\t$legacy_table = $wpdb->prefix . 'snp_' . $kind;
\t\t$exists       = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $legacy_table ) ) );
\t\t$has_table    = $exists === $legacy_table;
\t\t$state""",
"""\t\t$legacy_table = $wpdb->prefix . 'snp_' . $kind;
\t\t$wpdb->last_error = '';
\t\t$exists       = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $legacy_table ) ) );
\t\tif ( ! empty( $wpdb->last_error ) ) { return array( 'processed' => 0, 'migrated' => 0, 'skipped' => 0, 'remaining' => true, 'errors' => array( 'interaction_source_schema_query_failed_' . $kind ) ); }
\t\t$has_table    = $exists === $legacy_table;
\t\t$state""")
 Path('tools/r45-r48-interactions.py').unlink();Path('.github/workflows/r45-r48-interactions.yml').unlink()
else: raise SystemExit('unsupported')
p.write_text(s,encoding='utf-8')
