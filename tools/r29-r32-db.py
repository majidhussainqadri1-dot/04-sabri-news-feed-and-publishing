#!/usr/bin/env python3
from pathlib import Path
import sys
r=int(sys.argv[1]); p=Path('includes/class-snfla-database.php'); s=p.read_text(encoding='utf-8')
def rep(old,new):
 global s
 if old not in s: raise SystemExit(f'R{r} target missing: {old[:120]!r}')
 s=s.replace(old,new,1)
if r==29:
    rep("""\t\t$tables = self::tables();
\t\t$errors = array();""",
"""\t\t$column_contracts = array(
\t\t\t'runs' => array(
\t\t\t\t'id'=>array('/^bigint(?:\\(20\\))? unsigned$/','NO'), 'run_uuid'=>array('/^char\\(36\\)$/','NO'), 'operation'=>array('/^varchar\\(32\\)$/','NO'), 'status'=>array('/^varchar\\(24\\)$/','NO'), 'actor_digest'=>array('/^char\\(64\\)$/','NO'), 'idempotency_hash'=>array('/^char\\(64\\)$/','NO'), 'source_signature'=>array('/^char\\(64\\)$/','NO'), 'checkpoint_json'=>array('/^longtext$/','NO'), 'summary_json'=>array('/^longtext$/','NO'), 'started_at'=>array('/^datetime$/','NO'), 'finished_at'=>array('/^datetime$/','YES') ),
\t\t\t'map' => array(
\t\t\t\t'id'=>array('/^bigint(?:\\(20\\))? unsigned$/','NO'), 'legacy_id'=>array('/^bigint(?:\\(20\\))? unsigned$/','NO'), 'target_id'=>array('/^bigint(?:\\(20\\))? unsigned$/','NO','0'), 'target_type'=>array('/^varchar\\(32\\)$/','NO',''), 'status'=>array('/^varchar\\(32\\)$/','NO'), 'source_checksum'=>array('/^char\\(64\\)$/','NO'), 'target_checksum'=>array('/^char\\(64\\)$/','NO',''), 'run_uuid'=>array('/^char\\(36\\)$/','NO'), 'interaction_ledger_json'=>array('/^longtext$/','NO'), 'last_error_code'=>array('/^varchar\\(96\\)$/','NO',''), 'created_at'=>array('/^datetime$/','NO'), 'updated_at'=>array('/^datetime$/','NO') ),
\t\t\t'conflicts' => array(
\t\t\t\t'id'=>array('/^bigint(?:\\(20\\))? unsigned$/','NO'), 'legacy_id'=>array('/^bigint(?:\\(20\\))? unsigned$/','NO','0'), 'conflict_code'=>array('/^varchar\\(96\\)$/','NO'), 'severity'=>array('/^varchar\\(16\\)$/','NO'), 'fingerprint'=>array('/^char\\(64\\)$/','NO'), 'status'=>array('/^varchar\\(20\\)$/','NO','open'), 'redacted_context_json'=>array('/^longtext$/','NO'), 'run_uuid'=>array('/^char\\(36\\)$/','NO',''), 'created_at'=>array('/^datetime$/','NO'), 'resolved_at'=>array('/^datetime$/','YES') ),
\t\t\t'audit' => array(
\t\t\t\t'id'=>array('/^bigint(?:\\(20\\))? unsigned$/','NO'), 'event_uuid'=>array('/^char\\(36\\)$/','NO'), 'actor_digest'=>array('/^char\\(64\\)$/','NO'), 'action'=>array('/^varchar\\(96\\)$/','NO'), 'object_ref'=>array('/^varchar\\(128\\)$/','NO',''), 'context_json'=>array('/^longtext$/','NO'), 'prev_hash'=>array('/^char\\(64\\)$/','NO',''), 'event_hash'=>array('/^char\\(64\\)$/','NO'), 'created_at'=>array('/^datetime$/','NO') ),
\t\t\t'dry_run' => array(
\t\t\t\t'id'=>array('/^bigint(?:\\(20\\))? unsigned$/','NO'), 'run_uuid'=>array('/^char\\(36\\)$/','NO'), 'source_signature'=>array('/^char\\(64\\)$/','NO'), 'legacy_id'=>array('/^bigint(?:\\(20\\))? unsigned$/','NO'), 'source_checksum'=>array('/^char\\(64\\)$/','NO'), 'target_type'=>array('/^varchar\\(32\\)$/','NO','auto'), 'eligible'=>array('/^tinyint(?:\\(1\\))? unsigned$/','NO','0'), 'conflict_codes_json'=>array('/^longtext$/','NO'), 'created_at'=>array('/^datetime$/','NO') ),
\t\t\t'interaction_ledger' => array(
\t\t\t\t'id'=>array('/^bigint(?:\\(20\\))? unsigned$/','NO'), 'legacy_id'=>array('/^bigint(?:\\(20\\))? unsigned$/','NO'), 'target_id'=>array('/^bigint(?:\\(20\\))? unsigned$/','NO'), 'kind'=>array('/^varchar\\(24\\)$/','NO'), 'source_row_id'=>array('/^bigint(?:\\(20\\))? unsigned$/','NO'), 'canonical_row_id'=>array('/^bigint(?:\\(20\\))? unsigned$/','NO'), 'contribution_count'=>array('/^bigint(?:\\(20\\))? unsigned$/','NO','0'), 'original_json'=>array('/^longtext$/','NO'), 'created_by_migration'=>array('/^tinyint(?:\\(1\\))? unsigned$/','NO','0'), 'status'=>array('/^varchar\\(24\\)$/','NO','active'), 'created_at'=>array('/^datetime$/','NO'), 'updated_at'=>array('/^datetime$/','NO') ),
\t\t);
\t\t$tables = self::tables();
\t\t$errors = array();""")
    rep("""\t\t\t$wpdb->last_error = '';
\t\t\t$actual = array_map( 'strtolower', (array) $wpdb->get_col( \"SHOW COLUMNS FROM `{$table}`\", 0 ) );
\t\t\tif ( ! empty( $wpdb->last_error ) ) {
\t\t\t\t$errors[] = $key . '_column_query_failed';
\t\t\t\tcontinue;
\t\t\t}
\t\t\tforeach ( $columns as $column ) {
\t\t\t\tif ( ! in_array( strtolower( $column ), $actual, true ) ) {
\t\t\t\t\t$errors[] = $key . '_' . $column . '_missing';
\t\t\t\t}
\t\t\t}""",
"""\t\t\t$wpdb->last_error = '';
\t\t\t$column_rows = $wpdb->get_results( \"SHOW COLUMNS FROM `{$table}`\", ARRAY_A );
\t\t\tif ( ! is_array( $column_rows ) || ! empty( $wpdb->last_error ) ) {
\t\t\t\t$errors[] = $key . '_column_query_failed';
\t\t\t\tcontinue;
\t\t\t}
\t\t\t$actual = array(); $column_meta = array();
\t\t\tforeach ( $column_rows as $column_row ) { $field=strtolower((string)($column_row['Field']??'')); if(''===$field){continue;} $actual[]=$field; $column_meta[$field]=$column_row; }
\t\t\tforeach ( $columns as $column ) {
\t\t\t\t$column = strtolower( $column );
\t\t\t\tif ( ! in_array( $column, $actual, true ) ) { $errors[] = $key . '_' . $column . '_missing'; continue; }
\t\t\t\t$rule = $column_contracts[$key][$column] ?? array(); $meta=$column_meta[$column];
\t\t\t\t$type=strtolower(trim((string)($meta['Type']??''))); $nullable=strtoupper((string)($meta['Null']??''));
\t\t\t\tif ( empty($rule) || 1 !== preg_match($rule[0],$type) ) { $errors[]=$key.'_'.$column.'_type_mismatch'; }
\t\t\t\tif ( isset($rule[1]) && $rule[1] !== $nullable ) { $errors[]=$key.'_'.$column.'_nullability_mismatch'; }
\t\t\t\tif ( array_key_exists(2,$rule) && (string)$rule[2] !== (string)($meta['Default']??'') ) { $errors[]=$key.'_'.$column.'_default_mismatch'; }
\t\t\t}""")
elif r==30:
    rep("""\t\t\t\tif ( ! isset( $actual_indexes[ $name ] ) ) { $actual_indexes[ $name ] = array( 'unique' => 0 === absint( $index['Non_unique'] ?? 1 ), 'columns' => array() ); }
\t\t\t\t$seq = max( 1, absint( $index['Seq_in_index'] ?? 1 ) );
\t\t\t\t$actual_indexes[ $name ]['columns'][ $seq ] = strtolower( (string) ( $index['Column_name'] ?? '' ) );
\t\t\t}
\t\t\tforeach ( $actual_indexes as &$index ) { ksort( $index['columns'], SORT_NUMERIC ); $index['columns'] = array_values( $index['columns'] ); } unset( $index );""",
"""\t\t\t\tif ( ! isset( $actual_indexes[ $name ] ) ) { $actual_indexes[ $name ] = array( 'unique' => 0 === absint( $index['Non_unique'] ?? 1 ), 'columns' => array(), 'sub_parts' => array() ); }
\t\t\t\t$seq = max( 1, absint( $index['Seq_in_index'] ?? 1 ) );
\t\t\t\t$actual_indexes[ $name ]['columns'][ $seq ] = strtolower( (string) ( $index['Column_name'] ?? '' ) );
\t\t\t\t$actual_indexes[ $name ]['sub_parts'][ $seq ] = isset( $index['Sub_part'] ) ? absint( $index['Sub_part'] ) : 0;
\t\t\t}
\t\t\tforeach ( $actual_indexes as &$index ) { ksort( $index['columns'], SORT_NUMERIC ); ksort( $index['sub_parts'], SORT_NUMERIC ); $index['columns'] = array_values( $index['columns'] ); $index['sub_parts'] = array_values( $index['sub_parts'] ); } unset( $index );""")
    rep("""\t\t\t\telseif ( $required_columns !== $actual_indexes[ $name ]['columns'] ) { $errors[] = $key . '_' . $name . '_columns_mismatch'; }
""",
"""\t\t\t\telseif ( $required_columns !== $actual_indexes[ $name ]['columns'] ) { $errors[] = $key . '_' . $name . '_columns_mismatch'; }
\t\t\t\telseif ( array_filter( $actual_indexes[ $name ]['sub_parts'] ) ) { $errors[] = $key . '_' . $name . '_prefix_index_not_allowed'; }
""")
elif r==31:
    rep("""\tpublic static function acquire_lock( $name, $timeout = 5 ) {
\t\tglobal $wpdb;
\t\t$name = substr( $wpdb->prefix . 'snfla_' . sanitize_key( $name ), 0, 64 );
\t\treturn 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,%d)', $name, max( 0, absint( $timeout ) ) ) );
\t}""",
"""\tpublic static function acquire_lock( $name, $timeout = 5 ) {
\t\tglobal $wpdb;
\t\tif ( ! is_string( $name ) || 1 !== preg_match( '/^[a-z0-9_]{1,40}$/D', $name ) || ! is_int( $timeout ) || $timeout < 0 || $timeout > 30 ) { return false; }
\t\t$lock_name = substr( $wpdb->prefix . 'snfla_' . $name, 0, 64 );
\t\t$wpdb->last_error=''; $result=$wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,%d)', $lock_name, $timeout ) );
\t\treturn empty($wpdb->last_error) && 1 === (int)$result;
\t}""")
    rep("""\tpublic static function release_lock( $name ) {
\t\tglobal $wpdb;
\t\t$name = substr( $wpdb->prefix . 'snfla_' . sanitize_key( $name ), 0, 64 );
\t\t$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
\t}""",
"""\tpublic static function release_lock( $name ) {
\t\tglobal $wpdb;
\t\tif ( ! is_string( $name ) || 1 !== preg_match( '/^[a-z0-9_]{1,40}$/D', $name ) ) { return false; }
\t\t$lock_name = substr( $wpdb->prefix . 'snfla_' . $name, 0, 64 );
\t\t$wpdb->last_error=''; $result=$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
\t\treturn empty($wpdb->last_error) && 1 === (int)$result;
\t}""")
elif r==32:
    rep("""\t\t$wpdb->last_error=''; $result=$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
\t\treturn empty($wpdb->last_error) && 1 === (int)$result;""",
"""\t\t$wpdb->last_error=''; $result=$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
\t\t$released = empty($wpdb->last_error) && 1 === (int)$result;
\t\tif ( ! $released ) { do_action( 'snfla_operational_alert_v1', 'database_lock_release_failed', 'critical', array( 'lock_digest' => hash('sha256',$lock_name) ) ); }
\t\treturn $released;""")
    Path('tools/r29-r32-db.py').unlink()
    Path('.github/workflows/r29-r32-db.yml').unlink()
else: raise SystemExit('unsupported')
p.write_text(s,encoding='utf-8')
