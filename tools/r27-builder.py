from pathlib import Path
p=Path('tools/build-release.py')
s=p.read_text(encoding='utf-8')
if 'SECOND_EIGHTY_ROUND_REVIEW_ROUNDS=80' not in s:
    anchor='EIGHTY_ROUND_REVIEW_ROUNDS=80\nHISTORICAL_REVIEW_ROUNDS=40'
    if anchor not in s: raise SystemExit('builder count anchor missing')
    s=s.replace(anchor,'EIGHTY_ROUND_REVIEW_ROUNDS=80\nSECOND_EIGHTY_ROUND_REVIEW_ROUNDS=80\nHISTORICAL_REVIEW_ROUNDS=40',1)
gate="subprocess.run([sys.executable,str(ROOT/'tests/run-second-eighty-round-hardening.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)"
if gate not in s:
    anchor="subprocess.run([sys.executable,str(ROOT/'tests/run-eighty-round-hardening.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)"
    if anchor not in s: raise SystemExit('builder gate anchor missing')
    s=s.replace(anchor,anchor+'\n    '+gate,1)
if "'second_eighty_round_hardening'" not in s:
    anchor="'eighty_round_hardening':{'version':'1.0.0','review_rounds':EIGHTY_ROUND_REVIEW_ROUNDS,'gate':'tests/run-eighty-round-hardening.py','audit':'EIGHTY-ROUND-HARDENING-AUDIT.md','scope':'fresh sequential reliability, security, migration, rollback, evidence, integration and release review'},"
    if anchor not in s: raise SystemExit('builder manifest anchor missing')
    s=s.replace(anchor,anchor+"\n          'second_eighty_round_hardening':{'version':'1.0.0','review_rounds':SECOND_EIGHTY_ROUND_REVIEW_ROUNDS,'gate':'tests/run-second-eighty-round-hardening.py','audit':'SECOND-EIGHTY-ROUND-HARDENING-AUDIT.md','scope':'second independent eighty-round fail-closed source review'},",1)
s=s.replace("'eighty_round_review_rounds':EIGHTY_ROUND_REVIEW_ROUNDS,\n", "'eighty_round_review_rounds':EIGHTY_ROUND_REVIEW_ROUNDS,\n          'second_eighty_round_review_rounds':SECOND_EIGHTY_ROUND_REVIEW_ROUNDS,\n")
s=s.replace("'coded':'v2.0.5 eighty-round hardened candidate'","'coded':'v2.0.5 second-eighty-round hardened candidate'")
p.write_text(s,encoding='utf-8')
Path('tools/r27-builder.py').unlink()
