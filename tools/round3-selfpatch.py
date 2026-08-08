from pathlib import Path

p = Path('includes/class-snfla-retirement.php')
s = p.read_text()
old = "\tpublic static function mutations_allowed() { return 'retired' !== SNFLA_Schema::state(); }"
new = "\tpublic static function mutations_allowed() {\n\t\t$state = SNFLA_Schema::state();\n\t\treturn in_array( $state, SNFLA_Schema::states(), true ) && 'retired' !== $state;\n\t}"
if old not in s:
    raise SystemExit('round3 retirement patch target missing')
p.write_text(s.replace(old, new, 1))
Path('tools/round3-selfpatch.py').unlink()
Path('.github/workflows/round3-selfpatch.yml').unlink()
