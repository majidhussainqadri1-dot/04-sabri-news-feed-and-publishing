from pathlib import Path
p=Path('includes/class-snfla-mapping.php')
lines=p.read_text(encoding='utf-8').splitlines(True)
out=[]
changed=0
for line in lines:
    while line.startswith('\\t'):
        line='\t'+line[2:]
        changed+=1
    out.append(line)
if changed < 20:
    raise SystemExit(f'Expected conflict block escaped indentation; changed only {changed} lines')
p.write_text(''.join(out),encoding='utf-8')
Path('tools/r66-mapping-syntax-fix.py').unlink()
Path('.github/workflows/r66-mapping-syntax-fix.yml').unlink()
