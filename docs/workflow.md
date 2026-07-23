# שיטת עבודה — איך מעדכנים כל רכיב

---

## פעם אחת בלבד: להעלות את הריפו ל-GitHub

```bash
git clone gayaland-repo.bundle gayaland
cd gayaland
git remote add origin https://github.com/<המשתמש-שלך>/gayaland.git
git push -u origin master
```

מרגע זה הריפו הוא **מקור האמת היחיד**. לא "הקובץ האחרון בצ׳אט", לא "מה שבהורדות".

---

## מפת פריסה — מי הולך לאן

| קובץ בריפו | יעד | איך |
|------------|-----|-----|
| `dashboard/gayaland-dashboard.html` | Netlify | גרירה |
| `finance/gayaland-finance.html` | Netlify (אתר נפרד) | גרירה |
| `finance/gayaland-finance.gs` | Apps Script — פרויקט הפיננסים | הדבקה + **New version** |
| `apps-script/annual-sheet-Code.gs` | Apps Script — צמוד לגיליון השנתי | הדבקה + שמירה |
| `apps-script/booking-backend.gs` | Apps Script — `BD_API` (הזמנות + ימי הולדת) | הדבקה + **New version** |
| `wordpress-plugin/gayaland-booking.zip` | WordPress → תוספים | העלאה |
| `web/*.html` | Netlify | גרירה |

**כלל שקל לפספס:** קובץ `.gs` שמשמש כ-Web App (יש לו כתובת `AKfycb…`) —
שמירה **לא מספיקה**. חייבים `Deploy → Manage deployments → ✏️ → New version`,
אחרת הכתובת ממשיכה להגיש את הקוד הישן.

`annual-sheet-Code.gs` צמוד לגיליון ורץ מהתפריט — שם שמירה מספיקה.

---

## תרחיש 1 — לעדכן את הדשבורד

הדשבורד מדבר עם שני backends: `WC_PROXY_URL` (הזמנות מ-WooCommerce)
ו-`BD_API` (ימי הולדת/קופונים). ברוב המקרים משנים **רק את ה-HTML**.

```bash
git checkout -b feature/שם-השינוי
# עורכים dashboard/gayaland-dashboard.html
./validate-all.sh                       # חייב ✅
git add -A && git commit -m "תיאור"
```
→ גוררים את `dashboard/gayaland-dashboard.html` ל-Netlify.

**רק אם שינית גם התנהגות שרת** (למשל שדה חדש שהגיליון צריך לספק) —
עדכן גם את קובץ ה-`.gs` הרלוונטי ועשה לו **New version**.

---

## תרחיש 2 — לעדכן את המרכז הפיננסי

שני חלקים שחייבים להישאר תואמים:

- `finance/gayaland-finance.html` — הממשק (Netlify)
- `finance/gayaland-finance.gs` — 37 פעולות API (Apps Script Web App)

```bash
git checkout -b feature/finance-שם
# עורכים את שניהם
python3 finance/validate-finance.py finance/gayaland-finance.html
./validate-all.sh                       # חייב ✅
git commit -am "תיאור"
```

**סדר העלאה — שרת קודם:**
1. `gayaland-finance.gs` → Apps Script → **New version**
2. `gayaland-finance.html` → Netlify

הפוך = הממשק החדש קורא לפעולה שעדיין לא קיימת בשרת → שגיאות.

---

## תרחיש 3 — לעדכן את התוסף

```bash
# עורכים wordpress-plugin/src/gayaland-booking/gayaland-booking.php
cd wordpress-plugin && rm -f gayaland-booking.zip
cd src && zip -qr ../gayaland-booking.zip gayaland-booking && cd ../..
./validate-all.sh
git commit -am "תיאור"
```
→ WordPress → תוספים → העלאה. **תמיד לארוז מחדש** אחרי עריכת ה-PHP,
אחרת ה-zip נשאר ישן.

---

## הכלל היחיד שאסור לשבור

```bash
./validate-all.sh     # ✅ ALL VALIDATORS PASSED
```

לא עבר → לא מעלים. הכשל החוזר בפרויקט הזה הוא עריכה שמוחקת קוד עובד;
הוולידטור הוא מה שתופס אותה לפני שהיא מגיעה ללקוחות.

**כל פיצ׳ר חדש → שורה חדשה בוולידטור.** אחרת אין מה שיגן עליו בעריכה הבאה.

---

## לעבוד איתי בשיחה חדשה

1. מעלה את `gayaland-repo.zip`
2. אומר מה רוצים לשנות
3. אני עובד, מריץ ולידציה, ומחזיר zip מעודכן + מה להעלות לאן

---

## סודות — הגדרה חד-פעמית

הקוד קורא מפתחות מ-Script Properties, לא מהקובץ.
`Apps Script → Project Settings → Script Properties`:

| מפתח | פרויקט |
|------|--------|
| `BOOKING_PLUGIN_KEY` | booking-backend |
| `ALERT_EMAIL` | booking-backend |
| `WC_PASS` | annual-sheet-Code |

**בלי אלה הגשר לא יעבוד.** להגדיר לפני העלאת הקוד החדש.
