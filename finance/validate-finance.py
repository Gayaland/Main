#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
ולידטור למערכת הפיננסית של גאיהלנד
===================================
מריצים לפני כל מסירה:  python3 validate-finance.py

בודק:
  1. תחביר JS בשני הקבצים (node --check)
  2. שלמות מבנה ה-HTML
  3. חוזה פעולות: כל action שהפרונט קורא לו קיים בראוטר של השרת
  4. אין רישום כפול של פעולות (הבאג המבני החוזר)
  5. כל פעולה בראוטר יש לה פונקציה מוגדרת
  6. חוזה פונקציות ו-tabs בפרונט
  7. רשימת FORBIDDEN — דפוסים שגרמו לבאגים אמיתיים בעבר
"""
import json
import re
import subprocess
import sys
import tempfile
from pathlib import Path

HERE = Path(__file__).parent
HTML = HERE / "gayaland-finance.html"
GS = HERE / "gayaland-finance.gs"

errors, warnings, passed = [], [], []


def ok(msg):
    passed.append(msg)


def err(msg):
    errors.append(msg)


def warn(msg):
    warnings.append(msg)


def node_check(code, label):
    """בדיקת תחביר JS דרך node --check"""
    with tempfile.NamedTemporaryFile("w", suffix=".js", delete=False, encoding="utf-8") as f:
        f.write(code)
        path = f.name
    r = subprocess.run(["node", "--check", path], capture_output=True, text=True)
    if r.returncode != 0:
        err(f"תחביר JS שגוי ב-{label}:\n{r.stderr[:600]}")
        return False
    ok(f"תחביר JS תקין: {label}")
    return True


# ---------- 0. קיום קבצים ----------
if not HTML.exists():
    err(f"קובץ חסר: {HTML.name}")
if not GS.exists():
    err(f"קובץ חסר: {GS.name}")
if errors:
    print("\n".join(errors))
    sys.exit(1)

html = HTML.read_text(encoding="utf-8")
gs = GS.read_text(encoding="utf-8")

# ---------- 1. תחביר ----------
node_check(gs, "gayaland-finance.gs")

blocks = re.findall(r"<script(?! src)[^>]*>(.*?)</script>", html, re.DOTALL)
if not blocks:
    err("לא נמצאו בלוקי JS ב-HTML")
for i, b in enumerate(blocks):
    node_check(b, f"HTML script block #{i}")

# ---------- 2. מבנה HTML ----------
if html.count("<script") != html.count("</script>"):
    err("חוסר איזון בתגי <script>")
else:
    ok("תגי script מאוזנים")

if "</body>" in html and html.rindex("</script>") > html.index("</body>"):
    err("יש <script> אחרי </body>")
else:
    ok("כל הסקריפטים לפני </body>")

if "<nav>" in html and "<main>" in html:
    if html.index("<nav>") > html.index("<main>"):
        err("ה-nav חייב להיות לפני ה-main (טאבים למעלה)")
    else:
        ok("nav ממוקם לפני main")

# ---------- 3. חוזה פעולות (cross-file) ----------
gs_routes = re.findall(r"action === '([a-z_]+)'", gs)
dupes = {a for a in gs_routes if gs_routes.count(a) > 1}
if dupes:
    err(f"רישום כפול של פעולות בראוטר: {', '.join(sorted(dupes))}")
else:
    ok(f"אין רישום כפול ({len(gs_routes)} פעולות)")

# כל פעולה בראוטר -> פונקציה קיימת
handlers = re.findall(r"action === '[a-z_]+'\)\s*out = (api[A-Za-z]+)\(", gs)
missing_fn = [h for h in set(handlers) if not re.search(rf"^function {h}\b", gs, re.M)]
if missing_fn:
    err(f"פעולות ללא פונקציה מוגדרת: {', '.join(missing_fn)}")
else:
    ok("לכל פעולה בראוטר יש פונקציה")

# כל action שהפרונט קורא לו -> קיים בראוטר  (תופס "unknown action")
front_actions = set(re.findall(r"action\s*:\s*['\"]([a-z_]+)['\"]", html))
# גם פעולות שמועברות כארגומנט: writeChunks('cash_add', ...)
front_actions |= set(re.findall(r"writeChunks\(\s*['\"]([a-z_]+)['\"]", html))
BACKEND_ONLY = {"ingest", "edit_cell"}  # נקראות מהסקרייפר בלבד
unknown = sorted(front_actions - set(gs_routes))
if unknown:
    err(f"הפרונט קורא לפעולות שלא קיימות בשרת: {', '.join(unknown)}")
else:
    ok(f"כל {len(front_actions)} הפעולות שהפרונט קורא להן קיימות בשרת")

unused = sorted(set(gs_routes) - front_actions - BACKEND_ONLY)
if unused:
    warn(f"פעולות בשרת שלא בשימוש בפרונט: {', '.join(unused)}")

# ---------- 4. חוזה פונקציות בפרונט ----------
REQUIRED_FUNCS = [
    "api", "switchTab", "loadHome", "renderHomeCharts", "mkChart",
    "loadInsights", "renderBudget", "editBudget",
    "loadCashTab", "planAdd", "renderPlanned",
    "loadCashList", "cashToBudget",
    "fcSuggest", "fcApply", "fcClear",
    "loadAnnual", "editTarget", "loadProfit", "profitAdd",
    "loadBreakeven", "renderBreakeven", "beCard", "beRow", "setFixed", "renderHome",
    "waLoad", "waCalc", "waSave",
    "loadVat", "vatRecon", "vatAddFiles",
    "handleFile", "classifyRows", "renderPreview", "commitRows", "writeChunks",
    "renderInstBox", "setInstMode", "bulkSetBranch", "bulkSetCat",
    "detectInstallment", "setInst", "monthsSince", "bulkSetShift", "applyInst", "setInstBase", "togglePlanned",
    "manualAdd", "loadRecent",
    "loadUncat", "uncatSet", "showCatDetail", "shiftMonth", "moveMonth",
    "runDiag", "runRepair", "repairDelete", "runUndo", "doCommit",
    "saveSettings", "persistUrl", "saveAnchor", "runSetup",
    "loadSchedule", "saveSchedule",
]
for fn in REQUIRED_FUNCS:
    n = len(re.findall(rf"function {fn}\s*\(", html))
    if n == 0:
        err(f"פונקציה חסרה בפרונט: {fn}")
    elif n > 1:
        err(f"פונקציה מוגדרת {n} פעמים: {fn}")
if not any(e.startswith("פונקציה") for e in errors):
    ok(f"כל {len(REQUIRED_FUNCS)} הפונקציות מוגדרות פעם אחת")

# ---------- 5. חוזה טאבים ----------
nav_tabs = re.findall(r'data-tab="([a-z]+)"', html)
tab_divs = re.findall(r'id="tab-([a-z]+)"', html)
missing_tabs = [t for t in nav_tabs if t not in tab_divs]
orphan_tabs = [t for t in tab_divs if t not in nav_tabs]
if missing_tabs:
    err(f"כפתור ניווט בלי מיכל תוכן: {', '.join(missing_tabs)}")
if orphan_tabs:
    warn(f"מיכל תוכן בלי כפתור ניווט: {', '.join(orphan_tabs)}")
if not missing_tabs:
    ok(f"כל {len(nav_tabs)} הטאבים מחוברים")

# ---------- 6. FORBIDDEN — דפוסים שגרמו לבאגים אמיתיים ----------
# 6a. התנגשות עמודות: דגל המע"מ דרס את הסכום ("אוטו" במקום מספר)
if "row[iVat]" in gs and "iVat === iAmt" not in gs:
    err("FORBIDDEN: כתיבה ל-iVat בלי הגנה מהתנגשות עם iAmt "
        "(הבאג שהחליף סכומים ב'אוטו')")
else:
    ok("הגנת התנגשות עמודות מע\"מ/סכום קיימת")

# 6b. עמודת מע"מ בחישוב חייבת התאמה מדויקת
if re.search(r"colIndex_\((?:H|JH), 'מעמ'\)", gs):
    err("FORBIDDEN: colIndex_ עבור 'מעמ' — חייב colIndexExact_ "
        "(אחרת נתפס 'סכום (כולל מעמ)')")
else:
    ok("עמודת מע\"מ נקראת בהתאמה מדויקת")

# 6c. ARRAYFORMULA — חובה לכתוב '' לעמודות מחושבות
if "formulaCols" not in gs or "ARRAY" not in gs.upper():
    err("FORBIDDEN: אין הגנת ARRAYFORMULA בכתיבה ליומן")
else:
    ok("הגנת ARRAYFORMULA קיימת")

# 6d. אורך URL — כתיבה חייבת להתחלק לפי אורך ולא במספר קבוע
if re.search(r"i\s*\+=\s*20\)\s*chunks\.push", html):
    err("FORBIDDEN: חלוקה לקבוצות במספר קבוע — גורם ל-400 Bad Request "
        "(URL ארוך מדי בעברית). יש להשתמש בחלוקה לפי אורך")
elif "MAX=" in html and "writeChunks" in html:
    ok("חלוקת כתיבה לפי אורך URL")
else:
    warn("לא אותרה חלוקה לפי אורך ב-writeChunks")

# 6e. עריכת תאים — חובה הגנה מדריסת נוסחאות (רק לפונקציות שכותבות לגיליון)
edit_fns = ["apiEditCell", "apiSetTarget", "apiSetBudget", "apiUncatSet", "apiProfitUpdate"]
formula_issues = []
for fn in edit_fns:
    m = re.search(rf"function {fn}\(.*?\n\}}", gs, re.DOTALL)
    if not m:
        continue
    body = m.group(0)
    writes_to_sheet = ".setValue(" in body and "getRange(" in body
    if writes_to_sheet and "getFormula()" not in body:
        formula_issues.append(fn)
        err(f"FORBIDDEN: {fn} כותב לתא בלי לבדוק getFormula() — עלול לדרוס נוסחה")
if not formula_issues:
    ok("כל פונקציות העריכה שכותבות לגיליון בודקות נוסחאות")

# 6f. שמירת חיבור — לא רק localStorage (נמחק בנייד)
if "persistUrl" in html and "document.cookie" in html:
    ok("שמירת חיבור עמידה לנייד (cookie + localStorage)")
else:
    err("FORBIDDEN: שמירת ה-API URL ב-localStorage בלבד — נמחק בנייד")

# 6g. סינון חודש — חייב fallback לתאריך
if "rowMonth_" in gs:
    bad = re.findall(r"String\(\w+\[iHh\]\)\.trim\(\)\s*!==", gs)
    if bad:
        err("FORBIDDEN: השוואת ח\"ח ישירה בלי rowMonth_ — מסננת הכל אם הפורמט שונה")
    else:
        ok("זיהוי חודש עם fallback לתאריך")
else:
    err("FORBIDDEN: חסרה פונקציית rowMonth_ לזיהוי חודש עמיד")

# 6h. אין אחסון דפדפן אסור ב-artifacts (sessionStorage)
if "sessionStorage" in html:
    warn("שימוש ב-sessionStorage — לא נתמך בחלק מהסביבות")

# 6i. דה-דופליקציה חייבת לכלול סניף — אחרת הוצאה משותפת מפוצלת נבלעת
m_add = re.search(r"function apiAdd\(.*?\n\}", gs, re.DOTALL)
if m_add and "normKey_(" in m_add.group(0):
    calls = re.findall(r"normKey_\(([^)]*)\)", m_add.group(0))
    if any(c.count(",") < 3 for c in calls):
        err("FORBIDDEN: normKey_ ב-apiAdd בלי סניף — הוצאה משותפת מפוצלת 50/50 "
            "תיחשב כפילות ותיכתב פעם אחת בלבד")
    else:
        ok("דה-דופליקציה כוללת סניף (הוצאות משותפות נכתבות פעמיים)")

# 6j. הנחות על פורמט — שורש רוב הבאגים בפרויקט הזה.
month_writes = re.findall(r"row\[i(?:MonthName|HhCol|HhW|HhAuto)\]\s*=\s*([^;]+);", gs)
bad_month_writes = [w for w in month_writes
                    if "formatLikeExisting_" not in w and "it.chargeMonth" not in w]
if bad_month_writes:
    err("FORBIDDEN: כתיבת חודש בפורמט מונח במקום זיהוי הפורמט הקיים — "
        "חובה formatLikeExisting_ (" + bad_month_writes[0][:40] + "…)")
elif "formatLikeExisting_" in gs:
    ok("כתיבת חודש מזהה את הפורמט הקיים בגיליון")

# שמות טאבים קשיחים — מותרים רק אלה שאומתו מול הקובץ
hardcoded_sheets = set(re.findall(r"getSheetByName\('([^']+)'\)", gs))
allowed_sheets = {"תחזית תנועות", "תחזית יומית", "ריווחיות", "לוח בקרה חדש", "דיסקונט", "פועלים"}
unguarded = [s_ for s_ in hardcoded_sheets if s_ not in allowed_sheets]
if unguarded:
    warn("שמות טאבים קשיחים שלא אומתו: " + ", ".join(unguarded[:4]))
else:
    ok("שמות טאבים קשיחים מוגבלים לרשימה מאומתת")

# ---------- 7. בדיקות איכות נוספות ----------
if "buildAll" in gs:
    err("FORBIDDEN: קריאה ל-buildAll (מוחקת נתוני הכנסות)")

if html.count("<canvas") and "Chart.js" not in html:
    err("יש canvas אבל ספריית Chart.js לא נטענת")
elif html.count("<canvas"):
    ok(f"{html.count('<canvas')} גרפים + ספריית Chart.js נטענת")

if "INGEST_SECRET" in gs and "CHANGE-ME" in gs:
    warn("INGEST_SECRET עדיין ברירת מחדל — יש להחליף לפני שימוש בסקרייפר")

# ---------- דוח ----------
print("=" * 62)
print("  ולידציה — מערכת פיננסית גאיהלנד")
print("=" * 62)
for p in passed:
    print(f"  ✅ {p}")
if warnings:
    print()
    for w in warnings:
        print(f"  ⚠️  {w}")
if errors:
    print()
    for e in errors:
        print(f"  ❌ {e}")
print("=" * 62)
if errors:
    print(f"  נכשל: {len(errors)} שגיאות, {len(warnings)} אזהרות")
    print("  ⛔ אין למסור עד לתיקון")
    sys.exit(1)
print(f"  ✅ עבר: {len(passed)} בדיקות, {len(warnings)} אזהרות")
print("  מוכן למסירה")
sys.exit(0)
