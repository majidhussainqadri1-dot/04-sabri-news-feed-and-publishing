#!/usr/bin/env python3
"""Regression gate for defects found by the 2026-10-03 exact-head audit."""
from pathlib import Path
import json
import sys

ROOT = Path(__file__).resolve().parents[1]


def source(path):
    return (ROOT / path).read_text(encoding="utf-8")


failures = []


def require(condition, message):
    if not condition:
        failures.append(message)


rest = source("includes/class-snfla-rest.php")
inventory = source("includes/class-snfla-inventory.php")
completion = source("includes/class-snfla-plan-completion.php")
migration = source("includes/class-snfla-migration.php")
adapter = source("includes/class-snfla-file21-adapter.php")
cross = source("includes/class-snfla-cross-file-contracts.php")
workflow = source(".github/workflows/file04-legacy-adapter-ci.yml")
builder = source("tools/build-release.py")
lock = json.loads(source("COMPANION-CONTRACT-LOCK.json"))

# Runtime entry points must call real APIs with their exact signatures.
require("public static function public_summary()" in inventory, "Status inventory summary API is missing")
require("SNFLA_Inventory::public_summary()" in rest, "Status does not use the bounded inventory summary")
require("SNFLA_Inventory::lock( $actor, $request->get_param( 'expected_state' ), $request->get_param( 'expected_version' ) )" in rest, "REST inventory capture does not persist the lock and lifecycle transition")
require("SNFLA_Inventory::capture( $actor" not in rest, "REST inventory capture still calls the read-only capture API with invalid arguments")
require("'limit' => array( 'type' => 'integer', 'default' => 500, 'minimum' => 50, 'maximum' => 1000 )" in rest, "REST dry-run limit contract is missing")
require("SNFLA_Migration::dry_run( $actor, $request->get_param( 'limit' ), $request->get_param( 'expected_state' ), $request->get_param( 'expected_version' ) )" in rest, "REST dry-run argument order does not match the migration API")

# Current File 21 media and metadata contracts must be complete in both directions.
for needle in (
    "sabri_file21_legacy_metadata_preflight_v1",
    "sabri_file21_verify_migrated_legacy_metadata_v1",
    "'relations'",
    "'featured'",
    "'child'",
    "_thumbnail_id",
):
    require(needle in completion, "Current File 21 migration contract missing: " + needle)
require("'legacy_metadata_context'=> $metadata_context" in adapter, "File 21 migration call does not carry metadata evidence")
require("'metadata' => $metadata" in completion, "Migration preflight does not retain metadata evidence")
for obsolete in (
    "legacy_tags_require_mapping",
    "legacy_language_requires_mapping",
    "legacy_featured_flag_requires_mapping",
    "legacy_pinned_flag_requires_mapping",
):
    require(obsolete not in migration, "Mapped File 21 metadata is still unconditionally quarantined: " + obsolete)
require("unmapped_snp_metadata" in migration, "Unknown legacy metadata no longer fails closed")
require("contain_orphan_target" in completion and "metadata_post_migration_unverified" in completion, "Failed metadata verification does not trigger canonical containment")

# File 23 may consume only bounded, read-only diagnostics.
for needle in (
    "spdb/file04_migration_inventory",
    "spdb/file04_migration_mapping",
    "spdb/file04_migration_state",
    "public static function file23_inventory",
    "public static function file23_mapping",
    "public static function file23_state",
):
    require(needle in cross, "File 23 diagnostics provider missing: " + needle)
require("SNFLA_Inventory::public_summary()" in cross, "File 23 inventory bypasses bounded evidence")
require("validate_current_report" in cross and "proof_current" in cross, "File 23 state exposes stale reconciliation or rollback evidence")

# Exact-head CI must freeze every materially related current companion.
expected = {
    "file00", "file01", "file17", "file19", "file20", "file21",
    "file22", "file23", "file24", "file25", "file26", "cf04",
}
require(set(lock.get("companions", {})) == expected, "Companion lock is incomplete or has unexpected entries")
for key, row in lock.get("companions", {}).items():
    require(row.get("ref", "") in workflow, f"CI does not checkout {key} at its locked exact head")
require("run-runtime-contract-regressions.py" in workflow, "CI does not execute this runtime regression gate")
require("run-twenty-round-current-head.py" in workflow, "CI does not execute the unchanged-head twenty-round gate")
for owner in ("'File 17'", "'File 19'", "'File 23'", "'CF-04'"):
    require(owner in builder, "Release manifest omits material companion boundary: " + owner)
require("'exact_companion_lock':'COMPANION-CONTRACT-LOCK.json'" in builder, "Release manifest does not bind the exact companion lock")

# Repository-owned scanners must not treat checked-out companion source as
# File 04 runtime code; companion contracts have their own exact-head gate.
for gate in (
    "tests/run-architecture.py",
    "tests/run-central-reviews.py",
    "tests/run-eighty-round-hardening.py",
    "tests/run-second-ten-round-hardening.py",
    "tests/run-second-eighty-round-hardening.py",
    "tests/run-forty-rounds.py",
):
    body = source(gate)
    require("'companions' not in p.parts" in body, "Repository-owned scanner crosses into checked-out companion source: " + gate)

if failures:
    print("Runtime and current-contract regression checks FAILED:", file=sys.stderr)
    for failure in failures:
        print("-", failure, file=sys.stderr)
    raise SystemExit(1)

print("Runtime and current-contract regression checks passed: REST, inventory, File 21 media/metadata, File 23 diagnostics, and all exact companion locks are coherent.")
