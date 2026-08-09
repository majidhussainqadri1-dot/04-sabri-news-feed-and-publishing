from pathlib import Path
p=Path('includes/class-snfla-cli.php')
s=p.read_text(encoding='utf-8')
def rep(old,new):
 global s
 if old not in s: raise SystemExit('R67 target missing: '+old[:100])
 s=s.replace(old,new,1)
rep("$result = SNFLA_Migration::dry_run( $actor, $assoc['limit'] ?? 100,", "$limit = $this->integer_arg( $assoc, 'limit', 100, 50, 1000 );\n\t\t$result = SNFLA_Migration::dry_run( $actor, $limit,")
rep("'restored_post_count' => $assoc['restored-post-count'] ?? -1, 'restored_comment_count' => $assoc['restored-comment-count'] ?? -1,", "'restored_post_count' => $this->integer_arg( $assoc, 'restored-post-count', -1, 0, PHP_INT_MAX, true ), 'restored_comment_count' => $this->integer_arg( $assoc, 'restored-comment-count', -1, 0, PHP_INT_MAX, true ),")
rep("SNFLA_Interaction_Provider::resume( $actor, $assoc['legacy-id'] ?? 0, $assoc['max-records'] ?? SNFLA_Interaction_Provider::DEFAULT_RECORD_BUDGET )", "SNFLA_Interaction_Provider::resume( $actor, $this->integer_arg( $assoc, 'legacy-id', 0, 1, PHP_INT_MAX ), $this->integer_arg( $assoc, 'max-records', SNFLA_Interaction_Provider::DEFAULT_RECORD_BUDGET, 1, SNFLA_Interaction_Provider::MAX_RECORD_BUDGET ) )")
rep("SNFLA_Redirects::open_fallback( $actor, $assoc['hours'] ?? 24,", "SNFLA_Redirects::open_fallback( $actor, $this->integer_arg( $assoc, 'hours', 24, 1, 168 ),")
rep("SNFLA_Reconciliation::resolve_conflict( $actor, $assoc['id'] ?? 0,", "SNFLA_Reconciliation::resolve_conflict( $actor, $this->integer_arg( $assoc, 'id', 0, 1, PHP_INT_MAX ),")
needle="\tprivate function expected_version( array $assoc ) {"
helper="""\tprivate function integer_arg( array $assoc, $key, $default, $min, $max, $allow_missing_sentinel = false ) {
\t\tif ( ! array_key_exists( $key, $assoc ) ) {
\t\t\tif ( $allow_missing_sentinel && $default < $min ) { return $default; }
\t\t\treturn $default;
\t\t}
\t\t$raw=(string)$assoc[$key];
\t\tif ( 1!==preg_match('/^(?:0|[1-9][0-9]*)$/D',$raw) ) { WP_CLI::error('snfla_invalid_integer_arg: --'.$key.' must be a canonical decimal integer.'); }
\t\t$value=(int)$raw;
\t\tif ( (string)$value!==$raw || $value<$min || $value>$max ) { WP_CLI::error('snfla_invalid_integer_arg: --'.$key.' is outside the supported range.'); }
\t\treturn $value;
\t}

"""
if needle not in s: raise SystemExit('R67 helper insertion target missing')
s=s.replace(needle,helper+needle,1)
p.write_text(s,encoding='utf-8')
Path('tools/r67-cli-numeric-patch.py').unlink();Path('.github/workflows/r67-cli-numeric-patch.yml').unlink()
