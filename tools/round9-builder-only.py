from pathlib import Path

p=Path('tools/build-release.py')
s=p.read_text(encoding='utf-8')

s=s.replace("VERSION='2.0.2'","VERSION='2.0.3'",1)
s=s.replace('2.0.2.zip','2.0.3.zip')
s=s.replace('2.0.2-complete-source.zip','2.0.3-complete-source.zip')

if 'THIRD_TEN_ROUND_REVIEW_ROUNDS=10' not in s:
    anchor='SECOND_TEN_ROUND_REVIEW_ROUNDS=10\nHISTORICAL_REVIEW_ROUNDS=40'
    if anchor not in s: raise SystemExit('builder review constant anchor missing')
    s=s.replace(anchor,'SECOND_TEN_ROUND_REVIEW_ROUNDS=10\nTHIRD_TEN_ROUND_REVIEW_ROUNDS=10\nHISTORICAL_REVIEW_ROUNDS=40',1)

third_gate="subprocess.run([sys.executable,str(ROOT/'tests/run-third-ten-round-hardening.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)"
if third_gate not in s:
    anchor="subprocess.run([sys.executable,str(ROOT/'tests/run-second-ten-round-hardening.py')],check=True,cwd=ROOT,stdout=subprocess.DEVNULL)"
    if anchor not in s: raise SystemExit('builder gate anchor missing')
    s=s.replace(anchor,anchor+'\n    '+third_gate,1)

if "'third_ten_round_hardening'" not in s:
    anchor="'second_ten_round_hardening':{'version':'1.0.0','review_rounds':SECOND_TEN_ROUND_REVIEW_ROUNDS,'gate':'tests/run-second-ten-round-hardening.py','audit':'SECOND-TEN-ROUND-HARDENING-AUDIT.md','scope':'second fresh fail-closed/security/reliability review'},"
    if anchor not in s: raise SystemExit('builder manifest anchor missing')
    s=s.replace(anchor,anchor+"\n          'third_ten_round_hardening':{'version':'1.0.0','review_rounds':THIRD_TEN_ROUND_REVIEW_ROUNDS,'gate':'tests/run-third-ten-round-hardening.py','audit':'THIRD-TEN-ROUND-HARDENING-AUDIT.md','scope':'third fresh plan/anti-replay/serialization/release review'},",1)

# Add third-round count after every second-round count occurrence, without duplicating.
needle="'second_ten_round_review_rounds':SECOND_TEN_ROUND_REVIEW_ROUNDS,\n"
replacement=needle+"          'third_ten_round_review_rounds':THIRD_TEN_ROUND_REVIEW_ROUNDS,\n"
if "'third_ten_round_review_rounds':THIRD_TEN_ROUND_REVIEW_ROUNDS" not in s:
    s=s.replace(needle,replacement)

s=s.replace("'coded':'v2.0.2 second-ten-round hardened candidate'","'coded':'v2.0.3 third-ten-round hardened candidate'")
s=s.replace("'coded':'v2.0.3 second-ten-round hardened candidate'","'coded':'v2.0.3 third-ten-round hardened candidate'")
s=s.replace("'sabri:post-future18-hardening','value':'two-ten-round-audits-v2'","'sabri:post-future18-hardening','value':'three-ten-round-audits-v3'")

required=["VERSION='2.0.3'",'THIRD_TEN_ROUND_REVIEW_ROUNDS=10','run-third-ten-round-hardening.py',"'third_ten_round_hardening'","'third_ten_round_review_rounds':THIRD_TEN_ROUND_REVIEW_ROUNDS"]
missing=[x for x in required if x not in s]
if missing: raise SystemExit('builder integration incomplete: '+repr(missing))

p.write_text(s,encoding='utf-8')
