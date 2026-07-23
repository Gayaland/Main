#!/usr/bin/env python3
"""
validate-dashboard.py — integrity gate for gayaland-dashboard.html

Run this AFTER EVERY edit, BEFORE presenting the file to Or.
If it prints anything other than "ALL CHECKS PASSED", DO NOT ship the file —
restore from dashboard-backups/ and redo the edit.

Usage:  python3 validate-dashboard.py [path-to-html]
"""
import re, sys, subprocess, json, os

PATH = sys.argv[1] if len(sys.argv) > 1 else "/mnt/user-data/outputs/gayaland-dashboard.html"

# The contract: features that must ALWAYS exist. Add to this list when a feature ships.
# If any of these vanish, an edit deleted working code — that is the #1 failure mode.
REQUIRED_FEATURES = {
    # Login / permissions
    "login overlay":          'id="login-overlay"',
    "doLogin":                'function doLogin',
    "google-only allowlist":  'const GL_ALLOWED',
    "silent auto-login":      'try silent login using the stored day token',
    "logout clears token":    'localStorage.removeItem(STAFF_TOKEN_KEY)',
    "applyPermissions":       'function applyPermissions',
    # Tabs (HTML panels)
    "tab-combined":           'id="tab-combined"',
    "tab-wc":                 'id="tab-wc"',
    "tab-amelia":             'id="tab-amelia"',
    "tab-amelia-analytics":   'id="tab-amelia-analytics"',
    "tab-revenue":            'id="tab-revenue"',
    "tab-budget":             'id="tab-budget"',
    "tab-recon":              'id="tab-recon"',
    "tab-email":              'id="tab-email"',
    "tab-tasks":              'id="tab-tasks"',
    # Revenue tab internals
    "rvLoad":                 'function rvLoad',
    "rvRenderTarget":         'function rvRenderTarget',
    "rvRenderComparison":     'function rvRenderComparison',
    "fmtLocal":               'function fmtLocal',
    "fmtDate":                'function fmtDate',
    "getWeekKey":             'function getWeekKey',
    "yesterday box":          'id="rv-yesterday"',
    "tickets KPI":            'totalTickets',
    # Events tab
    "paid column (שולם)":     '<th>שולם</th>',
    "canceled hidden":        "['canceled','cancelled','rejected','no-show','noshow']",
    "revenue by service":     'svcRev',
    # Budget tab
    "bgLoad":                 'function bgLoad',
    "budget new sheet":       'BG_MONTHS_HE',
    "budget fresh stamp":     "glMarkFresh('bg','bg-status'",
    # Recon tab
    "rcReconcile":            'function rcReconcile',
    "rcSortByDate":           'function rcSortByDate',
    "rc date sort button":    'rc-sort-btn',
    # Email tab
    "emStartAuth":            'function emStartAuth',
    "emSend":                 'function emSend',
    "emSwitchAccount":        'function emSwitchAccount',
    "select_account":         "prompt:'select_account'",
    # Tasks tab
    "tkLoad":                 'function tkLoad',
    "tkAdd":                  'function tkAdd',
    "tkRender":               'function tkRender',
    "TK_SHEET_ID (tasks)":    'TK_SHEET_ID',
    # Inventory tab
    "tab-inventory":          'id="tab-inventory"',
    "invLoad":                'function invLoad',
    "invWriteAll":            'function invWriteAll',
    "invSignIn":              'function invSignIn',
    "INV_SHEET_ID":           'INV_SHEET_ID',
    # New annual sheet (single-tab structure)
    "RV_SHEET_TAB":           "const RV_SHEET_TAB",
    "rvParseRowsNew":         "function rvParseRowsNew",
    "rvFetchTab auth":        "const r = await glAuthFetch(url);",
    "yesterday always loads": "always include yesterday",
    # Token persistence (login once a day)
    "token save":             "function glSaveToken",
    "token restore":          "function glRestoreToken",
    "token silent refresh":   "function glSilentRefresh",
    "token restore on load":  "glRestoreToken(); loadAll();",
    # 401 auto-retry layer
    "auth fetch wrapper":     "async function glAuthFetch",
    "ensure token":           "async function glEnsureToken",
    "refresh promise":        "function glRefreshPromise",
    "tasks ensure token":     "async function tkWriteAll(){\n  await glEnsureToken();",
    "inventory ensure token": "async function invWriteAll(){\n  await glEnsureToken();",
    # Insights (forecast, per-visitor, best/worst, branch trend)
    "insights function":      "function rvRenderInsights",
    "insights container":     'id="rv-insights"',
    "insights in render":     "rvRenderInsights(data);",
    "unfiltered dataset":     "rvAllParsed = allParsed;",
    "month forecast":         "תחזית סוף חודש",
    # Data freshness stamps
    "fresh mark":             "function glMarkFresh",
    "stale mark":             "function glMarkStale",
    "revenue fresh stamp":    "glMarkFresh('rv','rv-status'",
    "tasks fresh stamp":      "glMarkFresh('tk','tk-fresh')",
    "inventory fresh stamp":  "glMarkFresh('inv','inv-fresh')",
    "tasks stale warn":       "glMarkStale('tk','tk-fresh')",
    "inventory stale warn":   "glMarkStale('inv','inv-fresh')",
    # Network timeout + WC proxy layer
    "auth fetch timeout":     "setTimeout(()=>ctrl.abort(),15000)",
    "wc proxy const":         "const WC_PROXY_URL=",
    "wc proxy helper":        "async function wcProxyFetch",
    "fetchWC proxy route":    "if(WC_PROXY_URL){\n      data=await wcProxyFetch(qs);",
    "wc proxy active":        "AKfycbzRwtvkEk20qzMcbeCxsXKr6uEzSVER7f0oOYRnMh2tXbxfzWg_ITk0JD8RaZYa0THk3Q",
    "Google GSI":             'gsi/client',
    # HR (כוח אדם) tab
    "tab-hr":                 'id="tab-hr"',
    "hrLoad":                 'function hrLoad',
    "hrRenderBoard":          'function hrRenderBoard',
    "hrRenderCost":           'function hrRenderCost',
    "hrMonthAgg":             'function hrMonthAgg',
    "hrPayBreakdown":         'function hrPayBreakdown',
    # HR shift-time settings (this session)
    "hr settings pane":       'id="hr-pane-settings"',
    "hr settings subtab":     'data-sub="settings"',
    "hr render settings":     'function hrRenderSettings(',
    "hr save settings":       'async function hrSaveSettings(',
    "hr offset preview":      'function hrUpdateOffsetPreview(',
    "hr recompute hours":     'function hrRecomputeHours(',
    "hr hours between":       'function hrHoursBetween(',
    "hr morning offset":      'morningOffset',
    "hr evening offset":      'eveningOffset',
    "hr cleaning min":        'cleaningMin',
    "hr save uses pushTab":   "await hrPushTab('set')",
    # HR sheet key aligned with webapp (this session)
    "hr shared sheet key":    'gayaland_hr_sheetid',
    # Tomorrow filter (this session)
    "tomorrow option":        '<option value="tomorrow">',
    "tomorrow dateFrom":      "if(v==='tomorrow')",
    # Amelia via proxy + parallel loading (this session)
    "amelia fetchEndpoint":   'const fetchEndpoint=async',
    "amelia parallel":        "Promise.allSettled([\n    fetchEndpoint('appointments'",
    "loadAll parallel":       "Promise.allSettled([fetchWC(), fetchAmelia()])",
    "customers via proxy":    "fetchEndpoint('customers'",
    "services full object":   "svList.forEach(s=>{svcMap[s.id]=s;});",
    # Expense entry from dashboard (this session)
    "expense modal":          'id="ex-modal"',
    "expense open":           'async function exOpenModal(',
    "expense save":           'async function exSave(',
    "expense categories dynamic": 'async function exLoadCategories(',
    "expense invoice toggle": 'function exToggleInvoice(',
    "expense blanks for arrayformula": "const row=[dateStr, desc, amount, branch, category, paid, '', vat, '', '', '', '', fullNotes];",
    "fixed expenses modal":   'id="ex-fixed-modal"',
    "fixed expenses fill":    'async function exFillFixed(',
    "fixed expenses dedup":   "existing.has(f.name+'|'+f.branch)",
    # Edit/delete records (this session)
    "manage expenses modal":  'id="ex-manage-modal"',
    "manage load":            'async function exManageLoad(',
    "edit row":               'async function exOpenModalForEdit(',
    "delete row":             'async function exDeleteRow(',
    "edit mode branch":       'if(exEditRowNum){',
    "delete via batchUpdate": 'deleteDimension:{range:{sheetId:exLogSheetGid',
    # Manage-list bug fixes (NaN amount + modal layering)
    "amount strips currency":  "replace(/[₪,\\s]/g,'')",
    "amount render NaN guard": "(Number(r.amount)||0).toLocaleString()",
    "edit closes manage list": "exReturnToManage=true;",
    "edit returns to manage":  "if(exReturnToManage){",
    "ex-modal above manage":   'id="ex-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10000',
    # Breakeven card + analysis (this session)
    "breakeven fetch":        'async function rvFetchBreakeven(',
    "breakeven card":         'ביחס לנקודת איזון',
    "analysis function":      'function bgRenderAnalysis(',
    "analysis container":     'id="bg-analysis"',
    "analysis in flow":       'bgRender();bgRenderAnalysis();',
    "hr sheet sync":          'async function hrPullAll',
    "hr cell write":          'async function hrPushCell',
    "hr ensure token":        'await glEnsureToken();',
    "hr full-system link":    'id="hr-fulllink"',
    "hr calendar view":       'function hrRenderCalendar',
    "hr branch filter":       'id="hr-branch-filter"',
    "hr calendar subtab":     'data-sub="calendar"',
    "hr print isolation":     'hr-printing',
    "hr print landscape":     'size:landscape',
    "hr print calendar grid": 'grid-template-columns:repeat(7,1fr)!important',
    "hr sat shift 5h":        'hours:5,shift:"שבת א׳"',
    "hr sat shift 3h":        'hours:3,shift:"שבת ב׳"',
    # ── תוסף גאיהלנד (מקור נתונים שלישי) ──
    "GYL state":             "let GYL = {",
    "GYL fetch":             "async function gylFetch",
    "GYL load":              "async function gylLoad",
    "GYL entries helper":    "function gylEntriesForDate",
    "GYL blocks helper":     "function gylBlockedByBirthday",
    "GYL api key field":     'id="gyl-api-key"',
    "GYL key persisted":     "gyl_api_key",
    "birthday-blocks API":   "/birthday-blocks",
    "bday clash panel":      'id="gyl-bday-panel"',
    "vouchers panel":        'id="gyl-vouchers"',
    "arrivals tab":          'id="tab-arrivals"',
    "arrivals load":         "async function arLoad",
    "arrivals render":       "function arRender",
    "arrivals checkin":      "async function arCheckin",
    "checkin endpoint use":  "gylApiUrl('/checkin')",
    "payment verify tab":    "function verLoad",
    "payment verify render": "function verRender",
    "verify-status use":     "gylApiUrl('/verify-status')",
    "mismatch highlight":    "אישור אחד בלבד",
    "dual check indicator":  "אושר פעמיים",
    "staff login":           "async function staffLogin",
    "staff token restore":   "async function staffRestore",
    "role-based perms":      "perms.includes(role)",
    "hr view-only":          "function hrViewOnly",
    "pwa manifest":          "rel=\"manifest\"",
    "pwa standalone":        "apple-mobile-web-app-capable",
    "sw registration":       "serviceWorker.register",
    "sw guarded":            "if(!('serviceWorker' in navigator))",
    "arrivals period":       "function arPeriodChange",
    "arrivals paid-only":    "+b.nayax_ok===1 || b.status==='confirmed'",
    "arrivals grouping":     "קיבוץ לפי סבב",
    "arrivals voucher cell": "🎟️ '+b.voucher",
    "vouchers redeemed":     "extras_at",
    "new apps script":       "AKfycbyG85xJ",
    # ── טאב שוברים + מחולל הזמנות ──
    "vouchers tab":          'id="tab-vouchers"',
    "vouchers employee perm":'switchMain(event,\'tab-vouchers\')',
    "vcLoad":                "async function vcLoad",
    "vcRender":              "function vcRender",
    "vcRedeemCode":          "async function vcRedeemCode",
    "vcRedeemExtra":         "async function vcRedeemExtra",
    "extras redeem API":     "/extras/redeem",
    "invite gender select":  'id="i-sex"',
    "invite time fix":       "function bdTime",
    "invite logo svg":       "<svg width=\"110\" height=\"66\"",
    "invite sex select":     'id="i-sex"',
    "invite date fix":       "function bdTime",
    "invite QR site":        "gayaland-birthday.netlify.app",
    "invite new QR site":    "gayaland-birthday.netlify.app",
}

def main():
    if not os.path.exists(PATH):
        print(f"FAIL: file not found: {PATH}")
        sys.exit(1)

    with open(PATH, encoding="utf-8") as f:
        c = f.read()

    errors = []

    # ── CHECK 1: every inline <script> block must parse as valid JS ──
    scripts = re.findall(r'<script(?! src)[^>]*>(.*?)</script>', c, re.DOTALL)
    for i, s in enumerate(scripts):
        tmp = f"/tmp/_validate_block{i}.js"
        with open(tmp, "w") as f:
            f.write(s)
        r = subprocess.run(["node", "--check", tmp], capture_output=True, text=True)
        if r.returncode != 0:
            msg = r.stderr.strip().split("\n")
            errors.append(f"JS SYNTAX ERROR in script block {i}: {msg[1] if len(msg)>1 else msg}")

    # ── CHECK 2: HTML skeleton must be intact and in order ──
    # </body> and </html> must each appear as real tags exactly once, AFTER the last </script>.
    # (A </body> inside a JS string is fine; we check the *structural* one on its own line.)
    body_struct = len(re.findall(r'\n</body>', c))
    html_struct = len(re.findall(r'\n</html>', c))
    if body_struct != 1:
        errors.append(f"STRUCTURE: expected exactly 1 structural </body>, found {body_struct}")
    if html_struct != 1:
        errors.append(f"STRUCTURE: expected exactly 1 structural </html>, found {html_struct}")

    last_script_close = c.rfind("</script>")
    body_pos = c.rfind("\n</body>")
    if last_script_close > body_pos:
        errors.append("STRUCTURE: </body> appears BEFORE the last </script> — "
                      "HTML got swallowed into a script block (the classic corruption).")

    # ── CHECK 3: no duplicate function definitions ──
    funcs = re.findall(r'function (\w+)\s*\(', c)
    seen, dupes = set(), set()
    for fn in funcs:
        if fn in seen:
            dupes.add(fn)
        seen.add(fn)
    if dupes:
        errors.append(f"DUPLICATE functions (an edit was injected twice): {sorted(dupes)}")

    # ── CHECK 4: no duplicate tab panels ──
    for tab in ["tab-combined","tab-revenue","tab-budget","tab-recon","tab-email","tab-tasks"]:
        n = len(re.findall(rf'id="{tab}"', c))
        if n > 1:
            errors.append(f"DUPLICATE tab panel id={tab} appears {n} times")

    # ── CHECK 5: every required feature still present ──
    for name, needle in REQUIRED_FEATURES.items():
        if needle not in c:
            errors.append(f"MISSING FEATURE: '{name}' (looked for: {needle!r})")

    # ── CHECK 6: file size sanity (catches truncation / explosion) ──
    size = len(c)
    if size < 120_000:
        errors.append(f"SIZE: file is only {size:,} bytes — likely truncated (expected >150k)")
    if size > 460_000:
        errors.append(f"SIZE: file is {size:,} bytes — likely duplicated content (expected <460k)")


    # ── CHECK 7: forbidden secrets must not appear in the file ──
    FORBIDDEN = [
        # literal split so the secret is never stored verbatim in git
        ("WP Application Password", "2sDt" + " PHhu GjGr jed1 ZOMo IqYb"),
        ("customers fetch using undefined h", "{headers:h});"),
        ("stale svRes reference", "svRes.ok"),
        ("HR pull by blind row index (branch mix-up bug)", "rows.forEach((x,idx)=>{if(hrDb.months[ym][idx])"),
        ("undefined hrPushKind call", "hrPushKind("),
    ]
    for label, secret in FORBIDDEN:
        if secret in c:
            errors.append(f"SECURITY: {label} is hardcoded in the HTML — remove it!")

    # HR pull MUST match shifts by date+branch+shift, not by position
    if "async function hrPullAll" in c:
        if "s.date===date&&s.branch===branch&&s.shift===shift" not in c:
            errors.append("HR REGRESSION: hrPullAll must match shifts by date+branch+shift, not row position")

    # ── Report ──
    if errors:
        print("VALIDATION FAILED — DO NOT SHIP. Restore from dashboard-backups/ and redo.\n")
        for e in errors:
            print("  ✗ " + e)
        sys.exit(1)
    else:
        print(f"ALL CHECKS PASSED  ({size:,} bytes, {len(scripts)} script blocks, "
              f"{len(funcs)} functions, {len(REQUIRED_FEATURES)} features intact)")
        sys.exit(0)

if __name__ == "__main__":
    main()
