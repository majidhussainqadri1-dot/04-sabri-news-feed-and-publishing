#!/usr/bin/env python3
"""V12.3 source-document/evidence coverage gate. NEVER a live-acceptance test."""
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
doc = (ROOT / "F04-V12.3-EXECUTION-EVIDENCE.md").read_text(encoding="utf-8")
status = (ROOT / "STATUS.md").read_text(encoding="utf-8")
architecture = (ROOT / "ARCHITECTURE.md").read_text(encoding="utf-8")
central = (ROOT / "includes/class-snfla-central-plan.php").read_text(encoding="utf-8")
workflow = (ROOT / ".github/workflows/file04-legacy-adapter-ci.yml").read_text(encoding="utf-8")
errors = []

def ensure(ok, message):
    if not ok:
        errors.append(message)

def rows(prefix, width):
    separator = "" if prefix == "M" else "-"
    return re.findall(r"^\|\s*(" + re.escape(prefix) + re.escape(separator) + r"[0-9]{" + str(width) + r"})\s*\|", doc, re.MULTILINE)

def exact_rows(name, observed, expected):
    ensure(len(observed) == len(expected), f"{name}: count {len(observed)} != {len(expected)}")
    ensure(len(observed) == len(set(observed)), f"{name}: duplicate table row")
    ensure(set(observed) == set(expected), f"{name}: missing/extra: {sorted(set(expected)^set(observed))}")

cv = [f"CV-{n:03d}" for low, high in ((37,49),(74,84),(239,285)) for n in range(low,high+1)]
aj = [f"AJ-{n:02d}" for n in (7,10,24,25,28,*range(31,41))]
exact_rows("CV", rows("CV",3), cv)
exact_rows("FR", rows("F04-FR",3), [f"F04-FR-{n:03d}" for n in range(1,14)])
exact_rows("NFR", rows("F04-NFR",3), [f"F04-NFR-{n:03d}" for n in range(1,11)])
exact_rows("FUT", rows("F04-FUT",3), [f"F04-FUT-{n:03d}" for n in range(1,19)])
exact_rows("AJ", rows("AJ",2), aj)
exact_rows("QA", rows("QA",2), [f"QA-{n:02d}" for n in range(15,25)])
exact_rows("M", rows("M",2), [f"M{n:02d}" for n in range(1,13)])
for tag in ("F04-CEN-01","F04-CEN-02","CEN-REL-001","CEN-PUB-001"):
    ensure("| " + tag + " |" in doc, f"Missing governing row: {tag}")
for source_string in ("array( 37, 49 )", "array( 74, 84 )", "array( 239, 285 )", "sprintf( 'CV-%03d'", "source_trace_complete"):
    ensure(source_string in central, f"Current source registry differs: {source_string}")
ensure("run-v123-source-evidence.py" in workflow, "V12.3 evidence gate not wired to CI")
ensure("43357015677" in status, "Exact-main CI 43357015677 missing from STATUS")
ensure("1e244e85c793314f8eda6c501af1b61c1dba6cfb" in status, "Frozen original main SHA absent")
ensure("301" in architecture and "v2.0.8" in architecture, "Architecture lacks current 301/v2.0.8 scope")
ensure("HTTP 530" in status and "UNVERIFIED" in status, "Live-evidence uncertainty absent")
ensure("QA-24" in doc and "CEN-REL-001" in doc and "File 21" in doc, "V12.3 release or canonical owner gate absent")
ensure("no untested SHA auto-update" in doc.lower(), "Companion drift safety gate not stated")
ensure("File 04 is a **temporary" in doc, "Canonical owner statement missing")
ensure("live-fixed=0" in status, "No truthful live-fixed defect count")
for label in ("PAR-001", "PAR-002", "PAR-003", "LIVE-001", "DOC-001", "DOC-002"):
    ensure(label in status and label in doc, f"Discrepancy not tracked: {label}")
if errors:
    print("V12.3 source/evidence matrix gate FAILED:", file=sys.stderr)
    for e in errors:
        print(" -", e, file=sys.stderr)
    sys.exit(1)
print("V12.3 source/evidence gate PASS: 71 CV + 13 FR + 10 NFR + 2 CEN + 15 AJ + 18 FUT + M01-12 + QA15-24; live acceptance remains UNVERIFIED.")
