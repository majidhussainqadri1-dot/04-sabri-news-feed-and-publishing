# File04 — local PHP 8.4 no-mbstring unit-test portability correction — 2026-11-13

## Exact reproduced failure (source test harness only)
Downloaded and checksum-verified File04 v2.0.8 complete-source candidate artifact (GitHub run 43342825728, artifact 13999199049), extracted it in an isolated local environment with PHP CLI 8.4.24 and ext-mbstring **absent** (iconv present). `php tests/run-unit.php` failed at `SNFLA_Audit::redact` with `Call to undefined function wp_check_invalid_utf8()` because `tests/bootstrap.php` did not stub that WordPress core function. The deterministic release `--verify-only` failed transitively because it runs the same unit script. This is *not* evidence that WordPress itself is missing the function or that the deployed site is affected.

## Root-cause fix and fresh verification
- Test-only stub of `wp_check_invalid_utf8($value, $strip)` using UTF-8 validity check; when stripping, prefer iconv; fail closed to empty string if conversion is unavailable.
- Exact stand-alone `php -n tests/run-unit.php` check added to each GitHub PHP matrix job to exercise a runtime without ext-mbstring and ext-iconv.
- Already performed on a temporary extracted source copy: regular `php tests/run-unit.php` PASS (16 checks); `php -n tests/run-unit.php` PASS (16 checks); `python tools/build-release.py --verify-only` PASS, deterministic ZIP outputs consistent; architecture and File04 own-plan tests PASS.
- Must confirm same results on the exact GitHub PR head and merged main. No production/runtime PHP, database schema, WordPress settings, published content or live site changed.

## Acceptance
All four CI jobs green on exact reviewed commit; upload artifact exists and ZIP checksum is independently compared; PHP syntax and source trace remain green. Staging and live acceptance remain blocked by no authorized deployment/DB snapshot and HTTP 530/Jetpack disconnected as recorded in issue #19.
