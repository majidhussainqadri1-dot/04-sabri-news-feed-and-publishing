#!/usr/bin/env python3
from pathlib import Path
import sys
r=int(sys.argv[1])
def patch(path,old,new):
 p=Path(path);s=p.read_text(encoding='utf-8')
 if old not in s: raise SystemExit(f'R{r} target missing in {path}: {old[:120]!r}')
 p.write_text(s.replace(old,new,1),encoding='utf-8')
if r==71:
 path='includes/class-snfla-migration.php';p=Path(path);s=p.read_text(encoding='utf-8')
 old="""\tpublic static function migrate( $actor_id, array $legacy_ids, $idempotency_key, $expected_state, $expected_version, $with_interactions = true ) {
\t\t$legacy_ids = SNFLA_Integrity::strict_positive_ids( $legacy_ids, self::MAX_BATCH );"""
 new="""\tpublic static function migrate( $actor_id, array $legacy_ids, $idempotency_key, $expected_state, $expected_version, $with_interactions = true ) {
\t\tif ( ! is_int( $actor_id ) || $actor_id <= 0 || ! is_int( $expected_version ) || $expected_version < 1 ) { return new WP_Error( 'snfla_migration_identity_or_version_invalid', 'Migration actor and lifecycle version must be canonical positive integers.', array( 'status' => 400 ) ); }
\t\t$legacy_ids = SNFLA_Integrity::strict_positive_ids( $legacy_ids, self::MAX_BATCH );"""
 if old not in s: raise SystemExit('R71 start target missing')
 s=s.replace(old,new,1)
 s=s.replace("absint( $authorized_actor ) !== absint( $actor_id )","$authorized_actor !== $actor_id")
 s=s.replace("SNFLA_Schema::assert_current( sanitize_key( $expected_state ), absint( $expected_version ) )","SNFLA_Schema::assert_current( sanitize_key( $expected_state ), $expected_version )")
 s=s.replace("SNFLA_Schema::transition( 'batch_migration', sanitize_key( $expected_state ), absint( $expected_version ), $actor_id","SNFLA_Schema::transition( 'batch_migration', sanitize_key( $expected_state ), $expected_version, $actor_id")
 p.write_text(s,encoding='utf-8')
elif r==72:
 path='includes/class-snfla-rollback.php';p=Path(path);s=p.read_text(encoding='utf-8')
 old="""\tpublic static function execute( $actor_id, array $legacy_ids, $idempotency_key, $expected_state, $expected_version, $restore_handover = false, $handover_confirmation = '' ) {
\t\t$legacy_ids = SNFLA_Integrity::strict_positive_ids( $legacy_ids, self::MAX_BATCH );"""
 new="""\tpublic static function execute( $actor_id, array $legacy_ids, $idempotency_key, $expected_state, $expected_version, $restore_handover = false, $handover_confirmation = '' ) {
\t\tif ( ! is_int( $actor_id ) || $actor_id <= 0 || ! is_int( $expected_version ) || $expected_version < 1 ) { return new WP_Error( 'snfla_rollback_identity_or_version_invalid', 'Rollback actor and lifecycle version must be canonical positive integers.', array( 'status' => 400 ) ); }
\t\t$legacy_ids = SNFLA_Integrity::strict_positive_ids( $legacy_ids, self::MAX_BATCH );"""
 if old not in s: raise SystemExit('R72 start target missing')
 s=s.replace(old,new,1)
 s=s.replace("absint( $authorized_actor ) !== absint( $actor_id )","$authorized_actor !== $actor_id")
 p.write_text(s,encoding='utf-8')
elif r==73:
 path='includes/class-snfla-capabilities.php'
 patch(path,"""\tpublic static function revalidate_actor( $expected_actor_id, $capability = self::CAP_RUN ) {
\t\t$expected_actor_id = absint( $expected_actor_id );
\t\t$actor_id = self::current_actor( $capability );""",
"""\tpublic static function revalidate_actor( $expected_actor_id, $capability = self::CAP_RUN ) {
\t\tif ( ! is_int( $expected_actor_id ) || $expected_actor_id <= 0 ) { return new WP_Error( 'snfla_actor_identity_invalid', 'The protected operation requires a canonical positive actor identity.', array( 'status' => 400 ) ); }
\t\t$actor_id = self::current_actor( $capability );""")
 patch(path,"""\t\tif ( $expected_actor_id <= 0 || absint( $actor_id ) !== $expected_actor_id ) {""","""\t\tif ( ! is_int( $actor_id ) || $actor_id !== $expected_actor_id ) {""")
elif r==74:
 path='includes/class-snfla-schema.php';p=Path(path);s=p.read_text(encoding='utf-8')
 s=s.replace("\t\t\t$expected_version = absint( $expected_version );","\t\t\tif ( ! is_int( $expected_version ) || $expected_version < 1 || ! is_int( $actor_id ) || $actor_id <= 0 ) { return new WP_Error( 'snfla_lifecycle_identity_or_version_invalid', 'Lifecycle actor/version must be canonical positive integers.', array( 'status' => 400 ) ); }",1)
 s=s.replace("\t\t$expected_version = absint( $expected_version );\n\t\t$current = self::state();","\t\tif ( ! is_int( $expected_version ) || $expected_version < 1 ) { return new WP_Error( 'snfla_lifecycle_version_invalid', 'Lifecycle version must be a canonical positive integer.', array( 'status' => 400 ) ); }\n\t\t$current = self::state();",1)
 s=s.replace("\t\t$expected_version = absint( $expected_version );\n\t\tif ( ! SNFLA_Database::acquire_lock( 'lifecycle', 5 ) )","\t\tif ( ! is_int( $expected_version ) || $expected_version < 1 || ! is_int( $actor_id ) || $actor_id <= 0 ) { return new WP_Error( 'snfla_lifecycle_identity_or_version_invalid', 'Lifecycle recovery actor/version must be canonical positive integers.', array( 'status' => 400 ) ); }\n\t\tif ( ! SNFLA_Database::acquire_lock( 'lifecycle', 5 ) )",1)
 p.write_text(s,encoding='utf-8')
 Path('tools/r71-r74-core.py').unlink();Path('.github/workflows/r71-r74-core.yml').unlink()
else: raise SystemExit('unsupported')
