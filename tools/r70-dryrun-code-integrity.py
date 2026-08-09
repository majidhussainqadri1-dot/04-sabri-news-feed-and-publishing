from pathlib import Path
p=Path('includes/class-snfla-mapping.php')
s=p.read_text(encoding='utf-8')
old="""\t\tif ( ! is_array( $codes ) || JSON_ERROR_NONE !== json_last_error() || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $source_checksum ) || ! in_array( $eligible, array( '0', '1' ), true ) ) { return new WP_Error( 'snfla_dry_run_candidate_corrupt' ); }
\t\treturn array( 'source_checksum' => $source_checksum, 'eligible' => '1' === $eligible, 'conflict_codes' => array_values( array_filter( array_map( 'sanitize_key', $codes ) ) ) );"""
new="""\t\tif ( ! is_array( $codes ) || JSON_ERROR_NONE !== json_last_error() || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $source_checksum ) || ! in_array( $eligible, array( '0', '1' ), true ) ) { return new WP_Error( 'snfla_dry_run_candidate_corrupt' ); }
\t\t$validated_codes=array(); $seen=array();
\t\tforeach ( $codes as $code ) {
\t\t\tif ( ! is_string( $code ) || '' === $code || sanitize_key( $code ) !== $code || isset( $seen[ $code ] ) ) { return new WP_Error( 'snfla_dry_run_candidate_corrupt' ); }
\t\t\t$seen[$code]=true; $validated_codes[]=$code;
\t\t}
\t\treturn array( 'source_checksum' => $source_checksum, 'eligible' => '1' === $eligible, 'conflict_codes' => $validated_codes );"""
if old not in s: raise SystemExit('R70 target missing')
p.write_text(s.replace(old,new,1),encoding='utf-8')
Path('tools/r70-dryrun-code-integrity.py').unlink();Path('.github/workflows/r70-dryrun-code-integrity.yml').unlink()
