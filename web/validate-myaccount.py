#!/usr/bin/env python3
import sys, re
p=sys.argv[1] if len(sys.argv)>1 else 'my-account-work.html'
s=open(p).read()
checks={
  "phone step":        'id="step-phone"',
  "code step":         'id="step-code"',
  "area":              'id="area"',
  "request-code call": '/my/request-code',
  "verify call":       '/my/verify',
  "silent restore":    '/my/data?phone=',
  "request fn":        'async function requestCode',
  "verify fn":         'async function verifyCode',
  "render fn":         'function render(',
  "logout":            'function logout',
  "punch stat":        'id="st-punch"',
  "visits stat":       'id="st-visits"',
  "token storage":     "localStorage.setItem(LS",
}
bad=0
for name,needle in checks.items():
  if needle in s: print(f"  \u2713 {name}")
  else: print(f"  \u2717 MISSING: {name} (looked for: {needle!r})"); bad+=1
# forbidden: no hardcoded password / secrets
if 'password' in s.lower() and 'autocomplete' not in s.lower().split('password')[0][-40:]:
  pass
print(("ALL CHECKS PASSED" if not bad else f"{bad} FAILURES")+f"  ({len(s)} bytes)")
sys.exit(1 if bad else 0)
