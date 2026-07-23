#!/usr/bin/env bash
# Runs every validator. Nothing ships unless this exits 0.
set -u
cd "$(dirname "$0")"
fail=0
run() { echo "── $1"; shift; "$@" 2>&1 | tail -2; [ ${PIPESTATUS[0]} -ne 0 ] && fail=1; echo; }

run "Dashboard"  python3 dashboard/validate-dashboard.py dashboard/gayaland-dashboard.html
run "Finance"    python3 finance/validate-finance.py     finance/gayaland-finance.html
run "Birthday"   python3 web/validate-birthday.py        web/birthday-index.html
run "Feedback"   python3 web/validate-feedback.py        web/feedback.html
run "My Account" python3 web/validate-myaccount.py       web/my-account.html

echo "── Apps Script syntax"
for f in apps-script/*.gs finance/gayaland-finance.gs; do
  cp "$f" /tmp/_chk.js && node --check /tmp/_chk.js 2>/dev/null \
    && echo "  ok   $(basename $f)" || { echo "  FAIL $(basename $f)"; fail=1; }
done
echo

echo "── WordPress plugin"
if command -v php >/dev/null; then
  php -l wordpress-plugin/src/gayaland-booking/gayaland-booking.php >/dev/null 2>&1 \
    && echo "  ok   php -l" || { echo "  FAIL php -l"; fail=1; }
else echo "  (php not installed — skipped)"; fi
echo

[ $fail -eq 0 ] && echo "✅ ALL VALIDATORS PASSED" || echo "❌ SOMETHING FAILED — DO NOT SHIP"
exit $fail
