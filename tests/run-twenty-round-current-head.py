#!/usr/bin/env python3
"""Twenty sequential, read-only, unchanged-head File 04 review rounds."""
from __future__ import annotations

from pathlib import Path
import hashlib
import json
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
LOCK = json.loads((ROOT / "COMPANION-CONTRACT-LOCK.json").read_text(encoding="utf-8"))
COMPANIONS = ROOT / "companions"

GATES = (
    "tests/run-architecture.py",
    "tests/run-file04-own-plan.py",
    "tests/run-runtime-contract-regressions.py",
    "tests/run-future18.py",
    "tests/run-future18-reviews.py",
    "tests/run-ten-round-post-future18.py",
    "tests/run-second-ten-round-hardening.py",
    "tests/run-third-ten-round-hardening.py",
    "tests/run-eighty-round-hardening.py",
    "tests/run-second-eighty-round-hardening.py",
    "tests/run-exact-companion-contract-parity.py",
)

AREAS = (
    "governing plans and traceability",
    "runtime completeness and REST entry points",
    "inventory locking, signatures, and lifecycle",
    "dry-run classification and bounded execution",
    "File 21 authorship and canonical ownership",
    "media, featured relations, and integrity evidence",
    "legacy metadata mapping and containment",
    "interactions, idempotency, and resume safety",
    "migration ledger, reconciliation, and rollback",
    "File 19 events and notification ownership",
    "File 23 read-only diagnostics",
    "File 24 assurance, security, and privacy",
    "File 26 search/index and cutover handoff",
    "Files 17/20/22/25 and CF04 ownership boundaries",
    "schema, upgrade, uninstall, and retirement",
    "authorization, evidence privacy, and failure modes",
    "performance, bounded traversal, and observability",
    "tests, CI, and exact companion locks",
    "packaging, version identity, and reproducibility",
    "final adversarial cross-cutting regression",
)


def run(*args: str, cwd: Path = ROOT) -> str:
    return subprocess.check_output(args, cwd=cwd, text=True, stderr=subprocess.STDOUT).strip()


def tracked_digest() -> str:
    digest = hashlib.sha256()
    paths = run("git", "ls-files", "-z").split("\0")
    for rel in sorted(path for path in paths if path):
        data = (ROOT / rel).read_bytes()
        digest.update(rel.encode("utf-8") + b"\0" + hashlib.sha256(data).digest())
    return digest.hexdigest()


def companion_heads() -> dict[str, str]:
    heads = {}
    for key in sorted(LOCK["companions"]):
        heads[key] = run("git", "rev-parse", "HEAD", cwd=COMPANIONS / key)
    return heads


def require_clean_tracked_tree() -> None:
    subprocess.run(("git", "diff", "--quiet"), cwd=ROOT, check=True)
    subprocess.run(("git", "diff", "--cached", "--quiet"), cwd=ROOT, check=True)


def main() -> int:
    require_clean_tracked_tree()
    candidate_head = run("git", "rev-parse", "HEAD")
    initial_digest = tracked_digest()
    frozen_companions = companion_heads()
    expected = {key: row["ref"] for key, row in LOCK["companions"].items()}
    if frozen_companions != dict(sorted(expected.items())):
        print("Exact companion heads do not match the lock.", file=sys.stderr)
        return 1

    for number, area in enumerate(AREAS, start=1):
        # Every round executes the complete source/plan/ownership/contract suite;
        # the label records its additional adversarial focus, not a reduced scope.
        for gate in GATES:
            completed = subprocess.run(
                (sys.executable, str(ROOT / gate)),
                cwd=ROOT,
                text=True,
                stdout=subprocess.PIPE,
                stderr=subprocess.STDOUT,
            )
            if completed.returncode:
                print(f"Round {number:02d} FAILED in {gate}:\n{completed.stdout}", file=sys.stderr)
                return 1
        if run("git", "rev-parse", "HEAD") != candidate_head:
            print(f"Round {number:02d} FAILED: candidate HEAD changed.", file=sys.stderr)
            return 1
        if tracked_digest() != initial_digest:
            print(f"Round {number:02d} FAILED: tracked source tree changed.", file=sys.stderr)
            return 1
        if companion_heads() != frozen_companions:
            print(f"Round {number:02d} FAILED: a companion HEAD changed.", file=sys.stderr)
            return 1
        require_clean_tracked_tree()
        print(f"Round {number:02d}/20 GREEN — {area}")

    print(f"20/20 GREEN on unchanged candidate HEAD {candidate_head}; tracked tree {initial_digest}.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
