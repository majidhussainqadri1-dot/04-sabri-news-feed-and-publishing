#!/usr/bin/env python3
from pathlib import Path
import sys
ROOT=Path(__file__).resolve().parents[1]
def t(p): return (ROOT/p).read_text(encoding='utf-8')
def need(c,m,f):
    if not c:f.append(m)
f=[]
main=t('sabri-news-feed-legacy-adapter.php'); redirects=t('includes/class-snfla-redirects.php'); checksum=t('includes/class-snfla-checksum.php'); audit=t('includes/class-snfla-audit.php'); rest=t('includes/class-snfla-rest.php'); migration=t('includes/class-snfla-migration.php'); recon=t('includes/class-snfla-reconciliation.php'); retirement=t('includes/class-snfla-retirement.php'); mapping=t('includes/class-snfla-mapping.php'); workflow=t('.github/workflows/file04-legacy-adapter-ci.yml'); build=t('tools/build-release.py'); record=t('THIRD-TEN-ROUND-HARDENING-AUDIT.md')
need('Version: 2.0.4' in main and "SNFLA_VERSION', '2.0.4'" in main,'v2.0.4 runtime metadata missing',f)
need("SNFLA_SCHEMA_VERSION', '1.3.0'" in main,'storage schema must remain 1.3.0',f)
need('legacy_public_fallback_allowed' in redirects and 'public_source_fallback' in redirects and 'X-Sabri-File04-Fallback' in redirects and "'tombstone_only' => false" in redirects,'Round1 read-only fallback implementation missing',f)
need('snfla-ser-v1:' in checksum and 'base64_encode( serialize( $canonical ) )' in checksum and "return '';" not in checksum.split('public static function encode',1)[1].split('public static function hash',1)[0],'Round2 checksum encoding must not collapse to empty input',f)
need('audit_context_json_invalid' in audit and 'if ( ! is_string( $context_json ) )' in audit,'Round2 audit JSON fail-closed check missing',f)
need("preg_match( '/^[!-~]+$/D', $key )" in rest and "sanitize_text_field( (string) $request->get_header( 'Idempotency-Key' )" not in rest,'Round3 exact idempotency-token validation missing',f)
need("$verification_request['request_digest']" in migration and 'provider_bound' in migration and '15 * MINUTE_IN_SECONDS' in migration,'Round4 restore attestation binding missing',f)
need("$context['request_digest']" in recon and 'integration_evidence_valid( $cache_evidence' in recon and 'reconciliation_checksum' in recon and '15 * MINUTE_IN_SECONDS' in recon,'Round5 cutover attestation binding missing',f)
need("$request['request_digest']" in retirement and "$summary['request_digest']" in retirement and 'previous_checksum' in retirement and 'verified_at_utc' in retirement,'Round6 retirement handoff binding missing',f)
need('rawurldecode' in redirects and '$target_path !== $legacy_path' in redirects and '$home_port !== $target_port' in redirects,'Round7 normalized same-origin redirect-loop guard missing',f)
need('$interaction_json = wp_json_encode' in mapping and 'if ( ! is_string( $interaction_json ) )' in mapping and '$original_json = wp_json_encode' in mapping and 'if ( ! is_string( $original_json ) )' in mapping,'Round8 mapping evidence encoding guards missing',f)
need('run-third-ten-round-hardening.py' in workflow and 'run-third-ten-round-hardening.py' in build,'Round9 third audit gate must run in CI and builder',f)
need("VERSION='2.0.4'" in build and 'THIRD_TEN_ROUND_REVIEW_ROUNDS=10' in build,'Round9 builder metadata missing',f)
need("'audit-file-04-*'" in workflow,'Current third-audit branch family must be covered by push CI',f)
# Round 10: fallback may only bridge a real File21 outage for an unchanged record
# that the local ledger proves was already migrated to a canonical target.
need('$file21_ready = SNFLA_Capabilities::file21_ready()' in redirects and '&& ! $file21_ready' in redirects,'Round10 fallback must require an actual File21 provider outage',f)
need("'migrated' !== sanitize_key" in redirects and "absint( $mapping['target_id'] ?? 0 ) <= 0" in redirects,'Round10 fallback must reject missing/unmigrated local mappings',f)
need("SNFLA_Checksum::post( $legacy_id )" in redirects and "hash_equals( (string) $mapping['source_checksum'], $current_checksum )" in redirects,'Round10 fallback must prove the legacy source is unchanged',f)
need('SNFLA_Mapping::open_conflict_codes( $legacy_id )' in redirects,'Round10 fallback must fail closed on mapping conflicts or conflict-ledger read failure',f)
need('Round 10' in record and ('PENDING' in record or 'No new defect' in record or 'Defect.' in record),'Round10 state must be explicit',f)
need('wp_insert_post(' not in redirects and 'SNFLA_Migration::migrate(' not in redirects,'Third audit must not add a publication/migration backend',f)
if f:
 print('Third ten-round hardening gate failed:',file=sys.stderr)
 for item in f: print('-',item,file=sys.stderr)
 sys.exit(1)
print('Third ten-round hardening source gate passed for all ten implemented review controls and audit trace.')
