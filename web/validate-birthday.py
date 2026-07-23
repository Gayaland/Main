#!/usr/bin/env python3
"""ולידטור לדף ימי ההולדת — חוזה פיצ'רים שאסור שייעלמו"""
import re,sys,subprocess,os
P=sys.argv[1] if len(sys.argv)>1 else "/mnt/user-data/outputs/birthday-index.html"
REQ={
 "CFG_URL מוורדפרס":"CFG_URL",
 "applyWpConfig":"function applyWpConfig",
 "Apps Script API חדש":"AKfycbyG85xJ",
 "מצב בדיקה":"CFG.testMode",
 "3 חבילות":"CFG.tiers",
 "אפסייל לחבילה גבוהה (nudge)":"CFG.nudge",
 "רינדור האפסייל":'const n = CFG.nudge[S.tier.id]',
 "טקסט אפסייל מלא":"שקית הפתעה",
 "שקיות הפתעה":"bags",
 "חישוב שקיות מעל הכלול":"bagsIncluded",
 "מחיר לשקית":"per:true",
 "עוגות":"CFG.cakes",
 "תוספת שבת/חג":"weekendFee",
 "סקרסיטי":"scarcityAt",
 "בדיקת זמינות":"loadAvail",
 "שמירה לפני תשלום":"CFG.api",
 "לינק נייקס":"nayaxDeposit",
 "גיבוי פופ-אפ חסום":"showPayFallback",
 "כפתור התחלה מחדש":"התחלה מחדש",
 "אישור חזרה מתשלום":'paid")==="1"',
 "וואטסאפ":"waLink",
 "כפתור ראו דוגמה":"see-example",
 "פונקציית מדיה":"function mediaBtn",
 "מתנת הפתעה":"giftPrice",
 "סרטון עיצוב":"designVideo",
 "תמונת עיצוב":"designImg",
 "סרטון עיצוב":"designVideo",
 "תמונת מתנה":"giftImg",
 "מציג מדיה":"function mediaThumb",
 "פותח מדיה":"window.openMedia",
}
c=open(P,encoding="utf-8").read()
errs=[]
# JS syntax
for i,b in enumerate(re.findall(r'<script(?![^>]*src)[^>]*>([\s\S]*?)</script>',c)):
    open(f"/tmp/_bd{i}.js","w").write(b)
    r=subprocess.run(["node","--check",f"/tmp/_bd{i}.js"],capture_output=True,text=True)
    if r.returncode: errs.append(f"JS SYNTAX block {i}: {r.stderr.strip().splitlines()[1] if len(r.stderr.strip().splitlines())>1 else r.stderr}")
for n,needle in REQ.items():
    if needle not in c: errs.append(f"MISSING: {n}  (חיפשתי: {needle!r})")
# duplicate funcs
fns=re.findall(r'function (\w+)\s*\(',c); dup={f for f in fns if fns.count(f)>1}
if dup: errs.append(f"DUPLICATE functions: {sorted(dup)}")
if len(c)<30000: errs.append(f"SIZE: {len(c):,} bytes — נראה קטוע")
if errs:
    print("VALIDATION FAILED\n"); [print("  ✗ "+e) for e in errs]; sys.exit(1)
print(f"ALL CHECKS PASSED  ({len(c):,} bytes, {len(fns)} functions, {len(REQ)} features intact)")
