#!/usr/bin/env python3
import sys
p=sys.argv[1] if len(sys.argv)>1 else 'feedback-work.html'
s=open(p).read()
checks={
  "rate step":'id="step-rate"',"happy step":'id="step-happy"',"sad step":'id="step-sad"',
  "thanks":'id="step-thanks"',"stars":'id="stars"',"mini questions":'data-q="q_clean"',
  "info call":'/feedback/info',"submit call":'/feedback/submit',
  "route fn":'function routeByRating',"submit fn":'async function submitSad',
  "booking param":"qs.get('gyl')","google route":'RATING>=CFG.review_min',
}
bad=0
for n,x in checks.items():
  if x in s: print(f"  \u2713 {n}")
  else: print(f"  \u2717 MISSING: {n} ({x!r})"); bad+=1
print(("ALL CHECKS PASSED" if not bad else f"{bad} FAILURES")+f"  ({len(s)} bytes)")
sys.exit(1 if bad else 0)
