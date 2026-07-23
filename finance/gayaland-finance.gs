/**
 * גאיהלנד — מערכת פיננסית מאוחדת (Backend)
 * ==========================================
 * Web App ב-GET בלבד (POST חסום ב-CORS מ-Netlify — לקח מהדשבורד).
 * קורא וכותב לקובץ התקציב השנתי, קורא את קובץ התזרים (xlsm),
 * בלי לשנות שום מבנה קיים.
 *
 * פריסה: Deploy → New deployment → Web app → Execute as: Me → Access: Anyone
 * לאחר כל שינוי קוד חובה New deployment (או גרסה חדשה) — עריכה לבד לא משנה את ה-URL החי.
 */

var CONFIG = {
  BUDGET_ID: '13DGaX3ljrHNcrX7if0YazKB-mAXrdSlMS_UHTfYasCw', // תקציב שנתי 2026
  // קובץ תזרים חי (Google Sheets מקורי — קריא וכתיב ישירות, בלי המרות)
  TAZRIM_LIVE_ID: '1k-OBtfqBPzOJ-0FAucZob99D7ImFrW9tHmE8lpy1Bgo',
  VAT_RATE: 0.18,
  // עמודות מחושבות ביומן ההוצאות (ARRAYFORMULA) — אסור לכתוב אליהן ערכים, רק ''
  // ברירת מחדל לפי הידע הקיים: G, I, J, K, L. יש גם זיהוי דינמי לפי נוסחאות.
  JOURNAL_FORMULA_COLS_FALLBACK: [7, 9, 10, 11, 12],
  CASH_SHEET: 'תנועות בפועל',   // טאב חדש בקובץ התזרים לרישום תנועות עו״ש אמיתיות
  // סוד משותף לסקרייפר האוטומטי — שנה למחרוזת אקראית משלך, ואותה מחרוזת ב-Render
  INGEST_SECRET: 'gyl-CHANGE-ME-to-random-string-2026'
};

/* ============ doPost: קליטה מהסקרייפר האוטומטי ============ */
function doPost(e) {
  var out;
  try {
    var body = JSON.parse(e.postData.contents || '{}');
    if (body.secret !== CONFIG.INGEST_SECRET) throw new Error('unauthorized');
    out = apiIngest({ source: body.source, branch: body.branch, rows: JSON.stringify(body.rows || []) });
  } catch (err) {
    out = { ok: false, error: String(err && err.message || err) };
  }
  return ContentService.createTextOutput(JSON.stringify(out)).setMimeType(ContentService.MimeType.JSON);
}

/* ============ Router ============ */
function doGet(e) {
  var p = (e && e.parameter) || {};
  var action = p.action || 'meta';
  var out;
  try {
    if (action === 'meta') out = apiMeta();
    else if (action === 'journal') out = apiJournal(p);
    else if (action === 'add') out = apiAdd(p);
    else if (action === 'vat') out = apiVat(p);
    else if (action === 'budget') out = apiBudget(p);
    else if (action === 'tazrim') out = apiTazrim(p);
    else if (action === 'insights') out = apiInsights(p);
    else if (action === 'setup') out = apiSetup(p);
    else if (action === 'cash_add') out = apiCashAdd(p);
    else if (action === 'cash_status') out = apiCashStatus(p);
    else if (action === 'set_anchor') out = apiSetAnchor(p);
    else if (action === 'sync_banks') out = apiSyncBanks(p);
    else if (action === 'plan_list') out = apiPlanList(p);
    else if (action === 'plan_add') out = apiPlanAdd(p);
    else if (action === 'annual') out = apiAnnual(p);
    else if (action === 'profit') out = apiProfit(p);
    else if (action === 'profit_add') out = apiProfitAdd(p);
    else if (action === 'profit_update') out = apiProfitUpdate(p);
    else if (action === 'profit_delete') out = apiProfitDelete(p);
    else if (action === 'uncat_list') out = apiUncatList(p);
    else if (action === 'uncat_set') out = apiUncatSet(p);
    else if (action === 'cash_list') out = apiCashList(p);
    else if (action === 'cash_to_budget') out = apiCashToBudget(p);
    else if (action === 'fc_suggest') out = apiForecastSuggest(p);
    else if (action === 'fc_apply') out = apiForecastApply(p);
    else if (action === 'fc_clear') out = apiForecastClear(p);
    else if (action === 'diag') out = apiDiag(p);
    else if (action === 'repair') out = apiRepairAmounts(p);
    else if (action === 'undo_import') out = apiUndoImport(p);
    else if (action === 'dup_check') out = apiDupCheck(p);
    else if (action === 'settings_get') out = apiSettingsGet(p);
    else if (action === 'settings_set') out = apiSettingsSet(p);
    else if (action === 'cat_detail') out = apiCatDetail(p);
    else if (action === 'attrib_set') out = apiAttribSet(p);
    else if (action === 'home') out = apiHome(p);
    else if (action === 'breakeven') out = apiBreakeven(p);
    else if (action === 'set_fixed') out = apiSetFixed(p);
    else if (action === 'edit_cell') out = apiEditCell(p);
    else if (action === 'set_target') out = apiSetTarget(p);
    else if (action === 'set_budget') out = apiSetBudget(p);
    else if (action === 'ingest') out = apiIngest(p);
    else out = { ok: false, error: 'unknown action: ' + action };
  } catch (err) {
    out = { ok: false, error: String(err && err.message || err) };
  }
  return ContentService.createTextOutput(JSON.stringify(out))
    .setMimeType(ContentService.MimeType.JSON);
}

/* ============ איתור גיליונות לפי כותרות (בלי להסתמך על שמות טאבים) ============ */
function ss_() { return SpreadsheetApp.openById(CONFIG.BUDGET_ID); }

function findSheetByHeaders_(mustContain) {
  var sheets = ss_().getSheets();
  var keys = mustContain.map(normHdr_);
  for (var i = 0; i < sheets.length; i++) {
    var sh = sheets[i];
    var rows = Math.min(8, sh.getLastRow());
    if (rows < 1) continue;
    var vals = sh.getRange(1, 1, rows, Math.min(25, Math.max(1, sh.getLastColumn()))).getDisplayValues();
    for (var r = 0; r < vals.length; r++) {
      var line = normHdr_(vals[r].join('|'));
      var all = true;
      for (var k = 0; k < keys.length; k++) {
        if (line.indexOf(keys[k]) === -1) { all = false; break; }
      }
      if (all) return { sheet: sh, headerRow: r + 1, headers: vals[r] };
    }
  }
  return null;
}

function journalSheet_() {
  var f = findSheetByHeaders_(['ספק / תיאור', 'סעיף תקציב']);
  if (!f) throw new Error('לא נמצא גיליון יומן הוצאות (כותרות: ספק / תיאור, סעיף תקציב)');
  return f;
}
function dailyRevenueSheet_() {
  var f = findSheetByHeaders_(['סה"כ יומי', 'ללא מעמ יומי']);
  if (!f) throw new Error('לא נמצא גיליון הכנסות יומיות');
  return f;
}
function baseBudgetSheet_() {
  var f = findSheetByHeaders_(['תקציב ראשון (סה"כ כולל)', 'תקציב יבנה (סה"כ כולל)']);
  if (!f) throw new Error('לא נמצא גיליון תקציב בסיסי');
  return f;
}

function colIndex_(headers, name) {
  var key = normHdr_(name);
  for (var i = 0; i < headers.length; i++) {
    if (normHdr_(headers[i]).indexOf(key) !== -1) return i;
  }
  return -1;
}
// התאמה מדויקת — קריטי לעמודות כמו "מעמ" שמופיעות כתת-מחרוזת בעמודות אחרות ("סכום (כולל מעמ)")
function colIndexExact_(headers, name) {
  var key = normHdr_(name);
  for (var i = 0; i < headers.length; i++) {
    if (normHdr_(headers[i]) === key) return i;
  }
  return -1;
}

/* ============ meta: קטגוריות, ספקים מוכרים, בדיקת חיבור ============ */
function apiMeta() {
  var cache = CacheService.getScriptCache();
  var hit = cache.get('meta_v1');
  if (hit) return JSON.parse(hit);

  var jf = journalSheet_();
  var sh = jf.sheet, hr = jf.headerRow, H = jf.headers;
  var last = sh.getLastRow();
  var res = { ok: true, categories: [], branches: ['ראשון לציון', 'יבנה'], suppliers: {}, journalRows: 0 };

  // קטגוריות מהתקציב הבסיסי
  try {
    var bf = baseBudgetSheet_();
    var bsh = bf.sheet;
    var bvals = bsh.getRange(bf.headerRow + 1, 1, Math.max(1, bsh.getLastRow() - bf.headerRow), 2).getDisplayValues();
    for (var i = 0; i < bvals.length; i++) {
      var name = String(bvals[i][0]).trim();
      if (!name || name.indexOf('סה') === 0 || name.indexOf('נקודת') === 0 || name.indexOf('אחוז') === 0 || name.indexOf('אגדת') === 0 || name.indexOf('✅') === 0 || name.indexOf('⚠') === 0 || name.indexOf('❌') === 0 || name.indexOf('יעד') !== -1) continue;
      if (res.categories.indexOf(name) === -1) res.categories.push(name);
    }
  } catch (e) {}

  // מפת ספקים: ספק → הסיווג/סניף/מעמ הנפוצים ביותר, מתוך היומן
  if (last > hr) {
    var data = sh.getRange(hr + 1, 1, last - hr, H.length).getDisplayValues();
    res.journalRows = data.length;
    var iDesc = colIndex_(H, 'ספק'), iCat = colIndex_(H, 'סעיף'), iBr = colIndex_(H, 'סניף'), iVat = colIndex_(H, 'כולל מעמ');
    var counts = {};
    for (var r = 0; r < data.length; r++) {
      var d = String(data[r][iDesc]).trim();
      if (!d) continue;
      var key = d;
      counts[key] = counts[key] || {};
      var combo = data[r][iCat] + '§' + data[r][iBr] + '§' + data[r][iVat];
      counts[key][combo] = (counts[key][combo] || 0) + 1;
    }
    Object.keys(counts).forEach(function (k) {
      var best = null, bestN = 0;
      Object.keys(counts[k]).forEach(function (c) {
        if (counts[k][c] > bestN) { bestN = counts[k][c]; best = c; }
      });
      var parts = best.split('§');
      res.suppliers[k] = { category: parts[0], branch: parts[1], vat: parts[2] };
    });
  }
  cache.put('meta_v1', JSON.stringify(res), 1800);
  return res;
}

/* ============ journal: קריאת תנועות ============ */
function apiJournal(p) {
  var jf = journalSheet_();
  var sh = jf.sheet, hr = jf.headerRow, H = jf.headers;
  var last = sh.getLastRow();
  if (last <= hr) return { ok: true, headers: H, rows: [] };
  var data = sh.getRange(hr + 1, 1, last - hr, H.length).getDisplayValues();
  var month = p.month || '';   // '2026-07'
  var iHh = colIndex_(H, 'ח"ח'), iJD = colIndex_(H, 'תאריך');
  var rows = [];
  for (var r = 0; r < data.length; r++) {
    if (month && rowMonth_(data[r], iHh, iJD) !== month) continue;
    if (String(data[r].join('')).trim() === '') continue;
    rows.push(data[r]);
  }
  var limit = parseInt(p.limit || '400', 10);
  if (rows.length > limit) rows = rows.slice(rows.length - limit);
  return { ok: true, headers: H, rows: rows };
}

/* ============ add: כתיבת תנועות ליומן (בודד או batch) ============ */
function apiAdd(p) {
  var items;
  try { items = JSON.parse(p.rows || '[]'); } catch (e) { throw new Error('rows חייב להיות JSON'); }
  if (!items.length) throw new Error('אין שורות להוספה');
  if (items.length > 25) throw new Error('מקסימום 25 שורות לקריאה אחת');

  var lock = LockService.getScriptLock();
  lock.waitLock(20000);
  try {
    var jf = journalSheet_();
    var sh = jf.sheet, hr = jf.headerRow, H = jf.headers;
    var nCols = H.length;

    // זיהוי עמודות מחושבות: נוסחת ARRAYFORMULA בשורת הנתונים הראשונה
    var formulaCols = [];
    var firstDataRow = hr + 1;
    if (sh.getLastRow() >= firstDataRow) {
      var fx = sh.getRange(firstDataRow, 1, 1, nCols).getFormulas()[0];
      for (var c = 0; c < nCols; c++) {
        if (fx[c] && fx[c].toUpperCase().indexOf('ARRAY') !== -1) formulaCols.push(c + 1);
      }
    }
    if (!formulaCols.length) formulaCols = CONFIG.JOURNAL_FORMULA_COLS_FALLBACK.slice();

    var iDate = colIndex_(H, 'תאריך'), iDesc = colIndex_(H, 'ספק'),
        iAmt = colIndex_(H, 'סכום (כולל'), iBr = colIndex_(H, 'סניף'),
        iCat = colIndex_(H, 'סעיף'), iPaid = colIndex_(H, 'שולם'),
        iVat = colIndex_(H, 'כולל מעמ'), iNote = colIndex_(H, 'הערות');
    if (iAmt === -1) iAmt = colIndex_(H, 'סכום');
    // קריטי: "כולל מעמ" מוכל בתוך "סכום (כולל מעמ)" — בלי ההגנה הזו
    // דגל המע"מ דורס את הסכום עצמו.
    if (iVat === iAmt || iVat === iDate || iVat === iCat) iVat = -1;
    if (iNote === iAmt) iNote = -1;

    // דה-דופליקציה: תאריך+סכום+תיאור קיימים
    var existing = {};
    var lastR = sh.getLastRow();
    if (lastR > hr) {
      var ex = sh.getRange(hr + 1, 1, lastR - hr, nCols).getDisplayValues();
      for (var r = 0; r < ex.length; r++) {
        existing[normKey_(ex[r][iDate], ex[r][iAmt], ex[r][iDesc], ex[r][iBr])] = true;
      }
    }

    var toWrite = [], skipped = 0, pendingAttribs = [];
    items.forEach(function (it) {
      var key = normKey_(it.date, it.amount, it.desc, it.branch);
      if (existing[key] && !p.force) { skipped++; return; }
      existing[key] = true;
      var row = [];
      for (var c = 0; c < nCols; c++) row.push('');
      row[iDate] = it.date || '';
      row[iDesc] = it.desc || '';
      row[iAmt] = Number(it.amount) || 0;
      row[iBr] = it.branch || '';
      row[iCat] = it.category || '';
      row[iPaid] = it.paid || '✅ שולם';
      if (iVat !== -1) row[iVat] = it.vat || 'אוטו';
      if (iNote !== -1 && it.note) row[iNote] = it.note;
      // חודש שיוך (ח"ח) — לתשלומים שמשולמים בחודש העוקב (שכר, מפעילות)
      if (it.chargeMonth) {
        var iHhW = colIndex_(H, 'ח"ח');
        if (iHhW !== -1 && formulaCols.indexOf(iHhW + 1) === -1) {
          row[iHhW] = it.chargeMonth;          // ניתן לכתיבה ישירה
        } else {
          pendingAttribs.push({ key: attribKey_(it.date, it.amount, it.desc), month: it.chargeMonth });
        }
      }
      // אם הגיע סכום מע"מ מפורש ממסמך פייפרלס — נכתוב לעמודת המע"מ (אם קיימת ואינה מחושבת)
      if (it.vatAmount !== undefined && it.vatAmount !== null) {
        var iVatAmt = colIndexExact_(H, 'מעמ');
        if (iVatAmt !== -1 && formulaCols.indexOf(iVatAmt + 1) === -1) row[iVatAmt] = Number(it.vatAmount) || 0;
      }
      // עמודות נוספות — מילוי לפי הפורמט שכבר קיים בשורות הקודמות (לא מניחים)
      var dObj = parseDate_(it.date);
      var isoMonth = it.chargeMonth || (dObj ? dObj.getFullYear() + '-' + ('0' + (dObj.getMonth() + 1)).slice(-2) : '');
      var iMonthName = colIndex_(H, 'חודש');
      var iHhCol = colIndex_(H, 'ח"ח');
      if (iMonthName !== -1 && iMonthName !== iHhCol && formulaCols.indexOf(iMonthName + 1) === -1 && isoMonth) {
        row[iMonthName] = formatLikeExisting_(sh, hr, iMonthName, isoMonth);
      }
      // ח"ח — למלא לפי התאריך אם עדיין ריק וניתן לכתיבה
      if (iHhCol !== -1 && !row[iHhCol] && isoMonth && formulaCols.indexOf(iHhCol + 1) === -1) {
        row[iHhCol] = formatLikeExisting_(sh, hr, iHhCol, isoMonth);
      }
      // שדות טקסט נפוצים נוספים
      var extraCols = [
        { keys: ['אמצעי תשלום', 'אמצעי'], val: it.payMethod || (it.account || '') },
        { keys: ['מקור'], val: it.source || 'מרכז פיננסי' },
        { keys: ['אסמכתא', 'חשבונית'], val: it.ref || '' },
        { keys: ['סוג'], val: it.type || '' }
      ];
      extraCols.forEach(function (ec) {
        if (!ec.val) return;
        for (var q = 0; q < ec.keys.length; q++) {
          var ci = colIndexExact_(H, ec.keys[q]);
          if (ci === -1) ci = colIndex_(H, ec.keys[q]);
          if (ci !== -1 && ci !== iAmt && ci !== iDate && ci !== iCat && ci !== iDesc &&
              ci !== iBr && ci !== iPaid && formulaCols.indexOf(ci + 1) === -1 && !row[ci]) {
            row[ci] = ec.val;
            break;
          }
        }
      });
      // עמודות מחושבות → מחרוזת ריקה תמיד (לא לשבור ARRAYFORMULA)
      formulaCols.forEach(function (fc) { row[fc - 1] = ''; });
      toWrite.push(row);
    });

    if (toWrite.length) {
      var start = sh.getLastRow() + 1;
      sh.getRange(start, 1, toWrite.length, nCols).setValues(toWrite);
    }
    if (pendingAttribs.length) saveAttribs_(pendingAttribs);
    invalidateBudgetCache_();
    return { ok: true, added: toWrite.length, skipped: skipped, attribs: pendingAttribs.length };
  } finally {
    lock.releaseLock();
  }
}

function normKey_(date, amount, desc, branch) {
  var d = String(date).trim();
  var a = String(amount).replace(/[^\d.\-]/g, '');
  a = String(Math.round(parseFloat(a || '0') * 100) / 100);
  var s = String(desc).replace(/\s+/g, '').slice(0, 20);
  // הסניף חלק מהמפתח — אחרת הוצאה משותפת מפוצלת 50/50 נחשבת כפילות
  var b = branch === undefined ? '' : String(branch).replace(/\s+/g, '');
  return d + '|' + a + '|' + s + (b ? '|' + b : '');
}

/* ============ vat: השוואת מע"מ לתקופה ============ */
function apiVat(p) {
  // p.months = '2026-05,2026-06' (תקופה דו-חודשית) או חודש בודד
  var months = String(p.months || '').split(',').filter(String);
  if (!months.length) throw new Error('חסר פרמטר months');

  // מע"מ עסקאות (פלט) — מתוך הכנסות יומיות: סה"כ יומי − ללא מעמ יומי
  var rf = dailyRevenueSheet_();
  var rsh = rf.sheet, rhr = rf.headerRow, RH = rf.headers;
  var iRD = colIndex_(RH, 'תאריך'), iTot = colIndex_(RH, 'סה"כ יומי'), iNet = colIndex_(RH, 'ללא מעמ יומי');
  var rvals = rsh.getRange(rhr + 1, 1, Math.max(1, rsh.getLastRow() - rhr), RH.length).getDisplayValues();
  var perMonth = {};
  months.forEach(function (m) { perMonth[m] = { revenueGross: 0, revenueNet: 0, outputVat: 0, inputVat: 0, expenses: 0 }; });

  rvals.forEach(function (row) {
    var m = monthOf_(row[iRD]);
    if (!perMonth[m]) return;
    var tot = num_(row[iTot]), net = num_(row[iNet]);
    perMonth[m].revenueGross += tot;
    perMonth[m].revenueNet += net;
    perMonth[m].outputVat += (tot - net);
  });

  // מע"מ תשומות — מתוך היומן, עמודת מעמ, לפי ח"ח
  var jf = journalSheet_();
  var jsh = jf.sheet, jhr = jf.headerRow, JH = jf.headers;
  var iHh = colIndex_(JH, 'ח"ח'), iVatAmt = colIndexExact_(JH, 'מעמ'), iAmt = colIndex_(JH, 'סכום (כולל'), iJD = colIndex_(JH, 'תאריך');
  var iVDesc = colIndex_(JH, 'ספק');
  var vatAttribs = getAttribs_();
  var jvals = jsh.getRange(jhr + 1, 1, Math.max(1, jsh.getLastRow() - jhr), JH.length).getDisplayValues();
  jvals.forEach(function (row) {
    var m = effMonth_(row, iHh, iJD, iAmt, iVDesc, vatAttribs);
    if (!perMonth[m]) return;
    perMonth[m].inputVat += num_(row[iVatAmt]);
    perMonth[m].expenses += num_(row[iAmt]);
  });

  var totals = { outputVat: 0, inputVat: 0, revenueGross: 0, expenses: 0 };
  months.forEach(function (m) {
    totals.outputVat += perMonth[m].outputVat;
    totals.inputVat += perMonth[m].inputVat;
    totals.revenueGross += perMonth[m].revenueGross;
    totals.expenses += perMonth[m].expenses;
  });
  totals.netDue = totals.outputVat - totals.inputVat;
  return { ok: true, months: perMonth, totals: totals, vatRate: CONFIG.VAT_RATE };
}

/* ============ budget: ביצוע מול תקציב לחודש ============ */
function apiBudget(p) {
  var month = p.month; // '2026-07'
  if (!month) throw new Error('חסר פרמטר month');
  var cache = CacheService.getScriptCache();
  var ckey = 'budget_' + month;
  if (!p.fresh) {
    var hit = cache.get(ckey);
    if (hit) { var o = JSON.parse(hit); o.cached = true; return o; }
  }

  var bf = baseBudgetSheet_();
  var bsh = bf.sheet, BH = bf.headers;
  var iName = 0, iClass = 1;
  var iR = colIndex_(BH, 'תקציב ראשון'), iY = colIndex_(BH, 'תקציב יבנה');
  var bvals = bsh.getRange(bf.headerRow + 1, 1, Math.max(1, bsh.getLastRow() - bf.headerRow), BH.length).getDisplayValues();
  var budget = {};
  bvals.forEach(function (row) {
    var name = String(row[iName]).trim();
    if (!name || name.indexOf('סה') === 0 || name.indexOf('נקודת') === 0 || name.indexOf('אחוז') === 0 || name.indexOf('אגדת') === 0 || name.indexOf('יעד') !== -1 || name.indexOf('✅') === 0 || name.indexOf('⚠') === 0 || name.indexOf('❌') === 0) return;
    budget[name] = { cls: row[iClass], budgetR: num_(row[iR]), budgetY: num_(row[iY]), actualR: 0, actualY: 0, overridden: false };
  });

  // עקיפות תקציב ספציפיות לחודש (נשמרות בהגדרות הסקריפט)
  var overrides = {};
  try {
    overrides = JSON.parse(PropertiesService.getScriptProperties().getProperty('BUDGET_' + month) || '{}');
  } catch (e) {}
  Object.keys(overrides).forEach(function (k) {
    if (!budget[k]) budget[k] = { cls: '', budgetR: 0, budgetY: 0, actualR: 0, actualY: 0 };
    if (overrides[k].r !== undefined) budget[k].budgetR = overrides[k].r;
    if (overrides[k].y !== undefined) budget[k].budgetY = overrides[k].y;
    budget[k].overridden = true;
  });

  var jf = journalSheet_();
  var JH = jf.headers;
  var iHh = colIndex_(JH, 'ח"ח'), iCat = colIndex_(JH, 'סעיף'), iBr = colIndex_(JH, 'סניף'), iAmt = colIndex_(JH, 'סכום (כולל'), iJD = colIndex_(JH, 'תאריך');
  var iJDesc = colIndex_(JH, 'ספק');
  var attribs = getAttribs_();
  var jvals = jf.sheet.getRange(jf.headerRow + 1, 1, Math.max(1, jf.sheet.getLastRow() - jf.headerRow), JH.length).getDisplayValues();
  var unmapped = 0;
  jvals.forEach(function (row) {
    if (effMonth_(row, iHh, iJD, iAmt, iJDesc, attribs) !== month) return;
    var cat = String(row[iCat]).trim();
    var amt = num_(row[iAmt]);
    if (!budget[cat]) { unmapped += amt; return; }
    if (String(row[iBr]).indexOf('יבנה') !== -1) budget[cat].actualY += amt;
    else budget[cat].actualR += amt;
  });

  // הכנסות החודש
  var rf = dailyRevenueSheet_();
  var RH = rf.headers;
  var iRD = colIndex_(RH, 'תאריך'), iTot = colIndex_(RH, 'סה"כ יומי'), iNet = colIndex_(RH, 'ללא מעמ יומי');
  var iRR = colIndex_(RH, 'סה"כ ראשון'), iRY = colIndex_(RH, 'סה"כ יבנה');
  var rvals = rf.sheet.getRange(rf.headerRow + 1, 1, Math.max(1, rf.sheet.getLastRow() - rf.headerRow), RH.length).getDisplayValues();
  var rev = { gross: 0, net: 0, days: 0, rishon: 0, yavne: 0 };
  rvals.forEach(function (row) {
    if (monthOf_(row[iRD]) !== month) return;
    var t = num_(row[iTot]);
    if (t > 0) rev.days++;
    rev.gross += t; rev.net += num_(row[iNet]);
    if (iRR !== -1) rev.rishon += num_(row[iRR]);
    if (iRY !== -1) rev.yavne += num_(row[iRY]);
  });

  // --- קצב היסטורי: איזה חלק מההוצאות החודשיות כבר הוצא עד יום X ---
  // הקרנה לינארית מנפחת, כי שכירות/משכורות/הלוואות משולמות בתחילת החודש.
  var pace = null;
  try {
    var dayNow = new Date().getDate();
    var monthTotals = {}, cumByMonth = {};
    jvals.forEach(function (r) {
      if (String(r.join('')).trim() === '') return;
      var m = effMonth_(r, iHh, iJD, iAmt, iJDesc, attribs);
      if (!/^\d{4}-\d{2}$/.test(m) || m >= month) return;   // רק חודשים שהסתיימו
      var d = parseDate_(r[iJD]);
      if (!d) return;
      var amt = num_(r[iAmt]);
      if (amt <= 0) return;
      monthTotals[m] = (monthTotals[m] || 0) + amt;
      if (d.getDate() <= dayNow) cumByMonth[m] = (cumByMonth[m] || 0) + amt;
    });
    var shares = [];
    Object.keys(monthTotals).forEach(function (m) {
      if (monthTotals[m] > 0 && cumByMonth[m] > 0) {
        shares.push(cumByMonth[m] / monthTotals[m]);
      }
    });
    if (shares.length >= 2) {
      shares.sort(function (a, b) { return a - b; });
      var mid = Math.floor(shares.length / 2);
      var share = shares.length % 2 ? shares[mid] : (shares[mid - 1] + shares[mid]) / 2;
      pace = { day: dayNow, share: Math.round(share * 1000) / 1000, monthsUsed: shares.length };
    }
  } catch (e) {}

  var result = { ok: true, month: month, budget: budget, unmapped: unmapped, revenue: rev, pace: pace };
  try { cache.put(ckey, JSON.stringify(result), 180); } catch (e) {}  // 3 דקות
  return result;
}

/* ============ setup: יצירת טאב "תנועות בפועל" בקובץ התזרים ============ */
function apiSetup(p) {
  var ss = SpreadsheetApp.openById(CONFIG.TAZRIM_LIVE_ID);
  var existed = !!ss.getSheetByName(CONFIG.CASH_SHEET);
  ensureCashSheet_(ss);
  CacheService.getScriptCache().remove('tazrim_v1');
  return { ok: true, already: existed, liveId: CONFIG.TAZRIM_LIVE_ID, url: ss.getUrl(),
    note: existed ? 'טאב "תנועות בפועל" כבר קיים.' : 'נוצר טאב "תנועות בפועל" בקובץ התזרים.' };
}

function liveTazrim_() {
  return SpreadsheetApp.openById(CONFIG.TAZRIM_LIVE_ID);
}

function ensureCashSheet_(ss) {
  var sh = ss.getSheetByName(CONFIG.CASH_SHEET);
  if (!sh) {
    sh = ss.insertSheet(CONFIG.CASH_SHEET);
    sh.getRange(1, 1, 1, 8).setValues([[
      'תאריך', 'חשבון', 'תיאור', 'סכום (+ הכנסה / − הוצאה)', 'סוג', 'נכתב לתקציב?', 'מקור', 'הערות'
    ]]).setFontWeight('bold');
    sh.setFrozenRows(1);
    sh.setRightToLeft(true);
  }
  return sh;
}

/* ============ tazrim: קריאה מקובץ ה-LIVE ============ */
function apiTazrim(p) {
  var cache = CacheService.getScriptCache();
  if (!p.fresh) {
    var hit = cache.get('tazrim_v1');
    if (hit) return JSON.parse(hit);
  }
  var tss = liveTazrim_();
  var out = { ok: true, readAt: new Date().toISOString(), control: {}, daily: [], banks: {}, liveUrl: tss.getUrl() };

  var ctrl = tss.getSheetByName('לוח בקרה חדש');
  if (ctrl) {
    var cv = ctrl.getRange(1, 1, Math.min(20, ctrl.getLastRow()), 2).getDisplayValues();
    cv.forEach(function (row) {
      var k = String(row[0]).trim(), v = String(row[1]).trim();
      if (k && v && k !== 'מדד') out.control[k] = v;
    });
  }
  var daily = tss.getSheetByName('תחזית יומית');
  if (daily) {
    var dv = daily.getRange(3, 1, Math.max(1, daily.getLastRow() - 2), 10).getDisplayValues();
    var today = new Date(); today.setHours(0, 0, 0, 0);
    var count = 0;
    for (var r = 1; r < dv.length && count < 45; r++) {
      var d = parseDate_(dv[r][0]);
      if (!d || d < today) continue;
      out.daily.push({
        date: dv[r][0], open: num_(dv[r][1]), inSure: num_(dv[r][2]), inEst: num_(dv[r][3]),
        outAmt: num_(dv[r][4]), bal: num_(dv[r][5]), balCons: num_(dv[r][6]),
        margin: num_(dv[r][7]), poalim: num_(dv[r][8]), discount: num_(dv[r][9])
      });
      count++;
    }
  }
  ['דיסקונט', 'פועלים'].forEach(function (bn) {
    var bs = tss.getSheetByName(bn);
    if (!bs) return;
    var bv = bs.getRange(1, 1, Math.min(6, bs.getLastRow()), Math.min(14, bs.getLastColumn())).getDisplayValues();
    out.banks[bn] = { months: bv[0].slice(1), open: [], income: [] };
    bv.forEach(function (row) {
      var k = String(row[0]).trim();
      if (k === 'פתיחה') out.banks[bn].open = row.slice(1).map(num_);
      if (k === 'הכנסות') out.banks[bn].income = row.slice(1).map(num_);
    });
  });

  cache.put('tazrim_v1', JSON.stringify(out), 600); // 10 דק' — הקובץ חי
  return out;
}

/* ============ cash_add: רישום תנועות עו״ש בפועל ============ */
function apiCashAdd(p) {
  var items;
  try { items = JSON.parse(p.rows || '[]'); } catch (e) { throw new Error('rows חייב להיות JSON'); }
  if (!items.length) throw new Error('אין שורות');
  if (items.length > 25) throw new Error('מקסימום 25 שורות לקריאה אחת');

  var lock = LockService.getScriptLock();
  lock.waitLock(20000);
  try {
    var ss = liveTazrim_();
    var sh = ensureCashSheet_(ss);
    var last = sh.getLastRow();
    var existing = {};
    if (last > 1) {
      var ex = sh.getRange(2, 1, last - 1, 4).getDisplayValues();
      ex.forEach(function (r) { existing[normKey_(r[0], r[3], r[2])] = true; });
    }
    var toWrite = [], skipped = 0;
    items.forEach(function (it) {
      var key = normKey_(it.date, it.amount, it.desc);
      if (existing[key] && !p.force) { skipped++; return; }
      existing[key] = true;
      toWrite.push([
        it.date || '', it.account || '', it.desc || '', Number(it.amount) || 0,
        it.type || 'אחר', it.inBudget ? 'כן' : 'לא', it.source || '', it.note || ''
      ]);
    });
    if (toWrite.length) sh.getRange(sh.getLastRow() + 1, 1, toWrite.length, 8).setValues(toWrite);
    CacheService.getScriptCache().remove('cashstat_v1');
    return { ok: true, added: toWrite.length, skipped: skipped };
  } finally {
    lock.releaseLock();
  }
}

/* ============ set_anchor: עוגן יתרה ידוע לחשבון ============ */
function apiSetAnchor(p) {
  if (!p.account || !p.date || p.balance === undefined) throw new Error('חסר account / date / balance');
  var props = PropertiesService.getScriptProperties();
  var anchors = JSON.parse(props.getProperty('ANCHORS') || '{}');
  anchors[p.account] = { date: p.date, balance: Number(p.balance) };
  props.setProperty('ANCHORS', JSON.stringify(anchors));
  CacheService.getScriptCache().remove('cashstat_v1');
  return { ok: true, anchors: anchors };
}

/* ============ cash_status: יתרה בפועל מול תחזית ============ */
function apiCashStatus(p) {
  var props = PropertiesService.getScriptProperties();
  var anchors = JSON.parse(props.getProperty('ANCHORS') || '{}');
  var out = { ok: true, accounts: {}, anchors: anchors };

  var ss, sh;
  try { ss = liveTazrim_(); sh = ss.getSheetByName(CONFIG.CASH_SHEET); } catch (e) { out.note = e.message; return out; }
  var rows = [];
  if (sh && sh.getLastRow() > 1) {
    rows = sh.getRange(2, 1, sh.getLastRow() - 1, 8).getDisplayValues();
  }

  ['פועלים', 'דיסקונט'].forEach(function (acc) {
    var a = anchors[acc];
    var info = { hasAnchor: !!a, movements: 0, sum: 0, balance: null };
    if (a) {
      var ad = parseDate_(a.date);
      rows.forEach(function (r) {
        if (String(r[1]).trim() !== acc) return;
        var d = parseDate_(r[0]);
        if (!d || !ad || d < ad) return;
        info.movements++;
        info.sum += num_(r[3]);
      });
      info.balance = a.balance + info.sum;
    }
    out.accounts[acc] = info;
  });

  // השוואה לתחזית של היום
  try {
    var tz = apiTazrim({});
    if (tz.ok && tz.daily && tz.daily.length) {
      var d0 = tz.daily[0];
      out.forecast = { date: d0.date, poalim: d0.poalim, discount: d0.discount, total: d0.bal };
      if (out.accounts['פועלים'].balance !== null) out.accounts['פועלים'].vsForecast = Math.round(out.accounts['פועלים'].balance - d0.poalim);
      if (out.accounts['דיסקונט'].balance !== null) out.accounts['דיסקונט'].vsForecast = Math.round(out.accounts['דיסקונט'].balance - d0.discount);
    }
  } catch (e) {}
  return out;
}

/* ============ sync_banks: עדכון לוחות הבנקים בקובץ ה-LIVE מתוך התנועות בפועל ============ */
function apiSyncBanks(p) {
  var ss = liveTazrim_();
  var sh = ss.getSheetByName(CONFIG.CASH_SHEET);
  if (!sh || sh.getLastRow() < 2) return { ok: true, written: [], note: 'אין עדיין תנועות בפועל' };
  var rows = sh.getRange(2, 1, sh.getLastRow() - 1, 8).getDisplayValues();

  // מיפוי סוג תנועה → תווית שורה בלוח הבנק
  var TYPE_TO_LABEL = {
    'כאל': 'כאל', 'ישראכרט': 'ישראכרט', 'אמקס': 'אמר', 'דיינרס': 'דיינרס', 'מקס': 'מקס',
    'מזומן/שיקים': 'מזומן', 'העברות - לקוחות': 'העברות'
  };
  // צבירה: account → label → 'yyyy-mm' → sum (רק הכנסות, ערכים חיוביים)
  var agg = {};
  rows.forEach(function (r) {
    var acc = String(r[1]).trim(), type = String(r[4]).trim();
    var label = TYPE_TO_LABEL[type];
    var amt = num_(r[3]);
    if (!label || amt <= 0) return;
    var m = monthOf_(r[0]);
    if (!m) return;
    agg[acc] = agg[acc] || {};
    agg[acc][label] = agg[acc][label] || {};
    agg[acc][label][m] = (agg[acc][label][m] || 0) + amt;
  });

  var written = [];
  Object.keys(agg).forEach(function (acc) {
    var bs = ss.getSheetByName(acc);
    if (!bs) return;
    var header = bs.getRange(1, 1, 1, bs.getLastColumn()).getValues()[0];
    var labels = bs.getRange(1, 1, Math.min(30, bs.getLastRow()), 1).getDisplayValues().map(function (r) { return String(r[0]).trim(); });
    // מיפוי חודש → עמודה
    var monthCol = {};
    for (var c = 1; c < header.length; c++) {
      var hv = header[c];
      var d = (hv instanceof Date) ? hv : parseDate_(String(hv));
      if (d) monthCol[d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2)] = c + 1;
    }
    Object.keys(agg[acc]).forEach(function (label) {
      var rowIdx = -1;
      for (var r = 0; r < labels.length; r++) {
        if (labels[r] && labels[r].indexOf(label) === 0) { rowIdx = r + 1; break; }
      }
      if (rowIdx === -1) return;
      Object.keys(agg[acc][label]).forEach(function (m) {
        var col = monthCol[m];
        if (!col) return;
        var val = Math.round(agg[acc][label][m] * 100) / 100;
        bs.getRange(rowIdx, col).setValue(val);
        written.push(acc + ' · ' + labels[rowIdx - 1] + ' · ' + m + ' = ' + val);
      });
    });
  });
  CacheService.getScriptCache().remove('tazrim_v1');
  return { ok: true, written: written };
}

/* ============ insights: המלצות ============ */
function apiInsights(p) {
  var month = p.month || currentMonth_();
  var b = apiBudget({ month: month });
  var alerts = [], tips = [];

  var totBudget = 0, totActual = 0;
  Object.keys(b.budget).forEach(function (k) {
    var it = b.budget[k];
    var bud = it.budgetR + it.budgetY, act = it.actualR + it.actualY;
    totBudget += bud; totActual += act;
    if (bud > 0 && act > bud * 1.1) {
      alerts.push({ type: 'over', category: k, budget: bud, actual: act, pct: Math.round(act / bud * 100) });
    }
  });
  if (b.unmapped > 500) alerts.push({ type: 'unmapped', amount: Math.round(b.unmapped) });

  var tz = null;
  try { tz = apiTazrim({}); } catch (e) {}
  if (tz && tz.ok && tz.daily && tz.daily.length) {
    var minBal = null, minDate = '';
    tz.daily.forEach(function (d) {
      if (minBal === null || d.balCons < minBal) { minBal = d.balCons; minDate = d.date; }
    });
    if (minBal !== null && minBal < 0) alerts.push({ type: 'cash_low', date: minDate, balance: Math.round(minBal) });
  }

  var dayOfMonth = new Date().getDate();
  var daysInMonth = new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).getDate();
  if (month === currentMonth_() && b.revenue.gross > 0) {
    var pace = b.revenue.gross / dayOfMonth * daysInMonth;
    tips.push({ type: 'pace', gross: Math.round(b.revenue.gross), projected: Math.round(pace) });
  }

  return { ok: true, month: month, alerts: alerts, tips: tips, totals: { budget: Math.round(totBudget), actual: Math.round(totActual) }, revenue: b.revenue };
}

/* ============ plan: תנועות מתוכננות (תחזית תנועות) ============ */
function planSheet_() {
  var sh = liveTazrim_().getSheetByName('תחזית תנועות');
  if (!sh) throw new Error('לא נמצא גיליון "תחזית תנועות" בקובץ התזרים');
  return sh;
}

function apiPlanList(p) {
  var sh = planSheet_();
  var last = sh.getLastRow();
  if (last < 4) return { ok: true, rows: [] };
  var vals = sh.getRange(4, 1, last - 3, 12).getDisplayValues();
  var today = new Date(); today.setHours(0, 0, 0, 0);
  var horizon = new Date(today); horizon.setDate(horizon.getDate() + (parseInt(p.days || '45', 10)));
  var rows = [];
  vals.forEach(function (r, i) {
    var d = parseDate_(r[0]);
    if (!d || d < today || d > horizon) return;
    rows.push({
      row: i + 4, date: r[0], kind: r[1], category: r[2], desc: r[3],
      account: r[4], amount: num_(r[5]), certainty: r[6], branch: r[9]
    });
  });
  rows.sort(function (a, b) { return parseDate_(a.date) - parseDate_(b.date); });
  return { ok: true, rows: rows.slice(0, 60) };
}

function apiPlanAdd(p) {
  if (!p.date || !p.desc || !p.amount) throw new Error('חסר date / desc / amount');
  var lock = LockService.getScriptLock();
  lock.waitLock(20000);
  try {
    var sh = planSheet_();
    var kind = p.kind || 'הוצאה';
    var amt = Math.abs(Number(p.amount) || 0);
    if (kind === 'הוצאה') amt = -amt;
    var branch = p.branch || 'משותף';
    var r = amt, y = amt;
    if (branch === 'ראשון לציון') { y = 0; }
    else if (branch === 'יבנה') { r = 0; }
    else { r = amt / 2; y = amt / 2; }
    sh.appendRow([
      p.date, kind, p.category || (kind === 'הכנסה' ? 'הכנסות' : 'הוצאות קבועות'),
      p.desc, p.account || 'פועלים', amt, p.certainty || 'ודאי',
      'הוזן ידנית', 'מרכז פיננסי', branch, r, y
    ]);
    CacheService.getScriptCache().remove('tazrim_v1');
    return { ok: true, added: 1 };
  } finally {
    lock.releaseLock();
  }
}


/* ============ ingest: קליטת תנועות מהסקרייפר (אותם כללי סיווג כמו האפליקציה) ============ */
function apiIngest(p) {
  var rows;
  try { rows = JSON.parse(p.rows || '[]'); } catch (e) { throw new Error('rows JSON לא תקין'); }
  if (!rows.length) return { ok: true, budgetAdded: 0, cashAdded: 0, note: 'אין תנועות' };

  var source = p.source || 'עו"ש';           // 'דיסקונט' | 'פועלים' | 'אשראי'
  var branch = p.branch || 'ראשון לציון';
  var isCard = (source === 'אשראי' || source === 'credit');
  var account = isCard ? '' : source;

  // מפת ספקים לזיהוי אוטומטי
  var meta = apiMeta();
  var supKeys = Object.keys(meta.suppliers || {});
  var CARD_CO = ['כאל', 'ישראכרט', 'אמריקן', 'אמר.', 'אמקס', 'דיינרס', 'ויזה', 'מקס איט', 'לאומי קארד'];
  var LOAN_KW = ['הלווא', 'פרעון', 'פירעון'];

  var budgetRows = [], cashRows = [];
  rows.forEach(function (r) {
    var desc = String(r.desc || '').trim();
    var debit = Number(r.debit || 0), credit = Number(r.credit || 0);
    // זיהוי ספק
    var match = null, dn = desc.replace(/\s+/g, '');
    for (var i = 0; i < supKeys.length; i++) {
      var kn = supKeys[i].replace(/\s+/g, '');
      if (dn.indexOf(kn) !== -1 || kn.indexOf(dn) !== -1) { match = meta.suppliers[supKeys[i]]; break; }
    }
    var isCardCharge = CARD_CO.some(function (k) { return desc.indexOf(k) !== -1; });
    var isLoan = LOAN_KW.some(function (k) { return desc.indexOf(k) !== -1; });

    // יעד לפי אותם כללים כמו באפליקציה
    var dest;
    if (isCard) dest = 'budget';
    else if (credit > 0) dest = 'cash';
    else if (isCardCharge || isLoan) dest = 'cash';
    else dest = 'both';

    if (debit > 0 && (dest === 'budget' || dest === 'both')) {
      budgetRows.push({
        date: r.date, desc: desc, amount: debit,
        branch: (match && match.branch) || branch,
        category: (match && match.category) || '',
        vat: (match && match.vat) || 'אוטו', paid: '✅ שולם'
      });
    }
    if (!isCard && (dest === 'cash' || dest === 'both')) {
      cashRows.push({
        date: r.date, account: account, desc: desc,
        amount: credit > 0 ? credit : -debit,
        type: isCardCharge ? 'אשראי' : (isLoan ? 'הלוואה' : (credit > 0 ? 'העברות - לקוחות' : 'הו"ק/העברה')),
        inBudget: (dest === 'both'), source: 'סקרייפר'
      });
    }
  });

  var bRes = budgetRows.length ? apiAdd({ rows: JSON.stringify(budgetRows) }) : { added: 0, skipped: 0 };
  var cRes = cashRows.length ? apiCashAdd({ rows: JSON.stringify(cashRows) }) : { added: 0, skipped: 0 };
  var sync = cRes.added > 0 ? apiSyncBanks({}) : { written: [] };

  return {
    ok: true,
    budgetAdded: bRes.added, budgetSkipped: bRes.skipped,
    cashAdded: cRes.added, cashSkipped: cRes.skipped,
    banksUpdated: (sync.written || []).length
  };
}


/* ============ home: כל נתוני מסך הסקירה בקריאה אחת ============ */
/* ============ breakeven: נקודת איזון חודשית ויומית ============ */
var FIXED_KEYWORDS = ['שכיר', 'שכר דירה', 'ארנונה', 'ביטוח', 'הלוואה', 'ריבית', 'עמלה', 'בנקאי',
  'רו"ח', 'רואה חשבון', 'הנהלת חשבונות', 'תקשורת', 'אינטרנט', 'טלפון', 'אבטחה', 'ליסינג', 'מנוי'];
var VARIABLE_KEYWORDS = ['קפיטריה', 'מזון', 'חומרי', 'ניקיון', 'מתנות', 'הפעלות', 'מפעיל',
  'ציוד מתכלה', 'אריזה', 'סליקה', 'פרסום', 'שיווק'];

function classifyExpense_(name, overrides) {
  var n = normHdr_(name);
  if (overrides && overrides[name] !== undefined) return overrides[name] ? 'fixed' : 'variable';
  for (var i = 0; i < FIXED_KEYWORDS.length; i++) {
    if (n.indexOf(FIXED_KEYWORDS[i]) !== -1) return 'fixed';
  }
  for (var j = 0; j < VARIABLE_KEYWORDS.length; j++) {
    if (n.indexOf(VARIABLE_KEYWORDS[j]) !== -1) return 'variable';
  }
  return 'variable';
}

function getFixedMap_() {
  try { return JSON.parse(PropertiesService.getScriptProperties().getProperty('FIXED_MAP') || '{}'); }
  catch (e) { return {}; }
}

function apiSetFixed(p) {
  if (!p.category) throw new Error('חסר category');
  var props = PropertiesService.getScriptProperties();
  var map = getFixedMap_();
  if (p.value === 'auto') delete map[p.category];
  else map[p.category] = (p.value === 'fixed');
  props.setProperty('FIXED_MAP', JSON.stringify(map));
  return { ok: true, category: p.category,
    value: map[p.category] === undefined ? 'auto' : (map[p.category] ? 'fixed' : 'variable') };
}

function apiBreakeven(p) {
  var month = p.month || currentMonth_();
  var b = apiBudget({ month: month });
  var overrides = getFixedMap_();
  var out = { ok: true, month: month, overrides: overrides, branches: {}, items: [] };

  var acc = {
    combined: { fixed: 0, variable: 0, revenue: 0 },
    rishon:   { fixed: 0, variable: 0, revenue: 0 },
    yavne:    { fixed: 0, variable: 0, revenue: 0 }
  };

  Object.keys(b.budget).forEach(function (name) {
    var it = b.budget[name];
    var kind = classifyExpense_(name, overrides);
    var actR = it.actualR || 0, actY = it.actualY || 0;
    var budR = it.budgetR || 0, budY = it.budgetY || 0;
    if (!actR && !actY && !budR && !budY) return;
    out.items.push({
      name: name, kind: kind, auto: overrides[name] === undefined,
      actualR: Math.round(actR), actualY: Math.round(actY),
      actual: Math.round(actR + actY), budget: Math.round(budR + budY)
    });
    if (kind === 'fixed') {
      acc.rishon.fixed += actR; acc.yavne.fixed += actY; acc.combined.fixed += actR + actY;
    } else {
      acc.rishon.variable += actR; acc.yavne.variable += actY; acc.combined.variable += actR + actY;
    }
  });

  acc.combined.revenue = b.revenue.gross || 0;
  acc.rishon.revenue = b.revenue.rishon || 0;
  acc.yavne.revenue = b.revenue.yavne || 0;
  var daysActive = b.revenue.days || 0;
  var mm = month.split('-');
  var daysInMonth = new Date(+mm[0], +mm[1], 0).getDate();

  ['combined', 'rishon', 'yavne'].forEach(function (k) {
    var a = acc[k];
    var rev = a.revenue;
    var cm = rev > 0 ? (rev - a.variable) / rev : 0;      // שיעור תרומה
    var beMonth = cm > 0 ? a.fixed / cm : null;
    var beDay = (beMonth !== null && daysActive > 0) ? beMonth / daysActive : null;
    var beDayCal = (beMonth !== null) ? beMonth / daysInMonth : null;
    out.branches[k] = {
      fixed: Math.round(a.fixed), variable: Math.round(a.variable),
      total: Math.round(a.fixed + a.variable), revenue: Math.round(rev),
      profit: Math.round(rev - a.fixed - a.variable),
      contributionMargin: Math.round(cm * 1000) / 10,
      breakevenMonth: beMonth !== null ? Math.round(beMonth) : null,
      breakevenDayActive: beDay !== null ? Math.round(beDay) : null,
      breakevenDayCalendar: beDayCal !== null ? Math.round(beDayCal) : null,
      safetyMargin: (beMonth !== null && rev > 0) ? Math.round((rev - beMonth) / rev * 100) : null,
      reached: beMonth !== null ? rev >= beMonth : null,
      gap: beMonth !== null ? Math.round(rev - beMonth) : null
    };
  });

  out.daysActive = daysActive;
  out.daysInMonth = daysInMonth;
  out.avgDailyRevenue = daysActive > 0 ? Math.round(acc.combined.revenue / daysActive) : 0;
  out.items.sort(function (x, y) { return y.actual - x.actual; });
  return out;
}

function apiHome(p) {
  var month = p.month || currentMonth_();
  var out = { ok: true, month: month };
  // כל חלק עטוף בנפרד — כשל באחד לא מפיל את השאר
  try { out.budget = apiBudget({ month: month }); } catch (e) { out.budgetErr = e.message; }
  try { out.tazrim = apiTazrim({}); } catch (e) { out.tazrimErr = e.message; }
  try { out.cash = apiCashStatus({}); } catch (e) { out.cashErr = e.message; }
  try { out.annual = apiAnnual({}); } catch (e) { out.annualErr = e.message; }
  return out;
}

function apiAnnual(p) {
  var cache = CacheService.getScriptCache();
  var hit = !p.fresh && cache.get('annual_v2');
  if (hit) return JSON.parse(hit);

  var monthNames = ['ינואר', 'פברואר', 'מרץ', 'אפריל', 'מאי', 'יוני', 'יולי', 'אוגוסט', 'ספטמבר', 'אוקטובר', 'נובמבר', 'דצמבר'];
  var year = parseInt(p.year || String(new Date().getFullYear()), 10);
  var out = { ok: true, year: year, months: [], source: 'מחושב מהנתונים' };

  // --- הכנסות בפועל לפי חודש ---
  var revByMonth = {};
  try {
    var rf = dailyRevenueSheet_();
    var RH = rf.headers;
    var iRD = colIndex_(RH, 'תאריך'), iTot = colIndex_(RH, 'סה"כ יומי');
    var iRR = colIndex_(RH, 'סה"כ ראשון'), iRY = colIndex_(RH, 'סה"כ יבנה');
    var rvals = rf.sheet.getRange(rf.headerRow + 1, 1, Math.max(1, rf.sheet.getLastRow() - rf.headerRow), RH.length).getDisplayValues();
    rvals.forEach(function (row) {
      var d = parseDate_(row[iRD]);
      if (!d || d.getFullYear() !== year) return;
      var mi = d.getMonth();
      revByMonth[mi] = revByMonth[mi] || { total: 0, rishon: 0, yavne: 0, days: 0 };
      var t = num_(row[iTot]);
      if (t > 0) revByMonth[mi].days++;
      revByMonth[mi].total += t;
      if (iRR !== -1) revByMonth[mi].rishon += num_(row[iRR]);
      if (iRY !== -1) revByMonth[mi].yavne += num_(row[iRY]);
    });
  } catch (e) { out.revenueError = e.message; }

  // --- הוצאות בפועל לפי חודש ---
  var expByMonth = {};
  try {
    var jf = journalSheet_();
    var JH = jf.headers;
    var iHh = colIndex_(JH, 'ח"ח'), iJD = colIndex_(JH, 'תאריך'),
        iAmt = colIndex_(JH, 'סכום (כולל');
    if (iAmt === -1) iAmt = colIndex_(JH, 'סכום');
    var jvals = jf.sheet.getLastRow() > jf.headerRow
      ? jf.sheet.getRange(jf.headerRow + 1, 1, jf.sheet.getLastRow() - jf.headerRow, JH.length).getDisplayValues()
      : [];
    jvals.forEach(function (r) {
      if (String(r.join('')).trim() === '') return;
      var m = rowMonth_(r, iHh, iJD);
      var mm = m.match(/^(\d{4})-(\d{2})$/);
      if (!mm || +mm[1] !== year) return;
      var mi = +mm[2] - 1;
      expByMonth[mi] = (expByMonth[mi] || 0) + num_(r[iAmt]);
    });
  } catch (e) { out.expenseError = e.message; }

  // --- יעדים: מ-Script Properties (ניתנים לעריכה מהאפליקציה) ---
  var targets = {};
  try {
    targets = JSON.parse(PropertiesService.getScriptProperties().getProperty('TARGETS_' + year) || '{}');
  } catch (e) {}

  var todayM = (new Date().getFullYear() === year) ? new Date().getMonth() : 11;
  monthNames.forEach(function (mn, mi) {
    var rev = revByMonth[mi] || { total: 0, rishon: 0, yavne: 0, days: 0 };
    var exp = Math.round(expByMonth[mi] || 0);
    var tgt = Math.round(targets[mi + 1] || 0);
    // מציגים רק חודשים שכבר עברו או שיש בהם נתונים/יעד
    if (mi > todayM && !rev.total && !exp && !tgt) return;
    out.months.push({
      month: mn, num: mi + 1,
      target: tgt,
      revenue: Math.round(rev.total),
      rishon: Math.round(rev.rishon), yavne: Math.round(rev.yavne),
      expense: exp,
      profit: Math.round(rev.total) - exp,
      days: rev.days
    });
  });

  cache.put('annual_v2', JSON.stringify(out), 600);
  return out;
}

/* ============ profit: ריווחיות מוצרים ============ */
function apiProfit(p) {
  var out = { ok: true, items: [], headers: [] };
  try {
    var ps = liveTazrim_().getSheetByName('ריווחיות');
    if (ps && ps.getLastRow() > 1) {
      var H = ps.getRange(1, 1, 1, ps.getLastColumn()).getDisplayValues()[0];
      out.headers = H;
      var iQty = colIndex_(H, 'כמות'),
          iSell = colIndex_(H, 'מחיר מכירה לפני'),
          iCost = colIndex_(H, 'מחיר ספק אחרי'),
          iProfit = colIndex_(H, 'רווח למוצר');
      out.cols = { qty: iQty, sell: iSell, cost: iCost, profit: iProfit };
      var v = ps.getRange(2, 1, ps.getLastRow() - 1, ps.getLastColumn()).getDisplayValues();
      v.forEach(function (row, i) {
        if (!String(row[0]).trim()) return;
        var sell = num_(row[iSell]), cost = num_(row[iCost]);
        out.items.push({
          row: i + 2, name: row[0], qty: num_(row[iQty]),
          sell: sell, cost: cost,
          profit: iProfit !== -1 ? num_(row[iProfit]) : sell - cost
        });
      });
    }
  } catch (e) { out.note = e.message; }
  return out;
}

/* ============ profit_add: הוספת מוצר חדש לריווחיות ============ */
function apiProfitAdd(p) {
  if (!p.name || p.sell === undefined || p.cost === undefined) throw new Error('חסר name / sell / cost');
  var lock = LockService.getScriptLock();
  lock.waitLock(20000);
  try {
    var ss = liveTazrim_();
    var ps = ss.getSheetByName('ריווחיות');
    if (!ps) {
      ps = ss.insertSheet('ריווחיות');
      ps.getRange(1, 1, 1, 5).setValues([['מוצר', 'כמות', 'מחיר מכירה לפני מעמ', 'מחיר ספק אחרי מעמ', 'רווח למוצר']]).setFontWeight('bold');
      ps.setFrozenRows(1); ps.setRightToLeft(true);
    }
    var H = ps.getRange(1, 1, 1, ps.getLastColumn()).getDisplayValues()[0];
    var iQty = colIndex_(H, 'כמות'), iSell = colIndex_(H, 'מחיר מכירה לפני'),
        iCost = colIndex_(H, 'מחיר ספק אחרי'), iProfit = colIndex_(H, 'רווח למוצר');

    // בדיקת כפילות שם
    var last = ps.getLastRow();
    if (last > 1) {
      var names = ps.getRange(2, 1, last - 1, 1).getDisplayValues();
      for (var i = 0; i < names.length; i++) {
        if (normHdr_(names[i][0]) === normHdr_(p.name)) throw new Error('מוצר בשם זה כבר קיים: ' + p.name);
      }
    }

    var row = [];
    for (var c = 0; c < Math.max(5, H.length); c++) row.push('');
    row[0] = p.name;
    if (iQty !== -1) row[iQty] = Number(p.qty) || 0;
    if (iSell !== -1) row[iSell] = Number(p.sell) || 0;
    if (iCost !== -1) row[iCost] = Number(p.cost) || 0;
    if (iProfit !== -1) row[iProfit] = (Number(p.sell) || 0) - (Number(p.cost) || 0);
    ps.appendRow(row);
    return { ok: true, added: 1, name: p.name };
  } finally {
    lock.releaseLock();
  }
}

/* ============ profit_update: עדכון מחיר/עלות/כמות של מוצר קיים ============ */
function apiProfitUpdate(p) {
  if (!p.row) throw new Error('חסר row');
  var ps = liveTazrim_().getSheetByName('ריווחיות');
  if (!ps) throw new Error('גיליון ריווחיות לא נמצא');
  var H = ps.getRange(1, 1, 1, ps.getLastColumn()).getDisplayValues()[0];
  var map = { sell: colIndex_(H, 'מחיר מכירה לפני'), cost: colIndex_(H, 'מחיר ספק אחרי'), qty: colIndex_(H, 'כמות') };
  var r = parseInt(p.row, 10);
  var changed = [];
  ['sell', 'cost', 'qty'].forEach(function (f) {
    if (p[f] === undefined || p[f] === '') return;
    var c = map[f];
    if (c === -1) return;
    var cell = ps.getRange(r, c + 1);
    if (cell.getFormula()) return; // לא דורסים נוסחאות
    cell.setValue(Number(p[f]) || 0);
    changed.push(f);
  });
  // עדכון רווח אם הוא לא נוסחה
  var iProfit = colIndex_(H, 'רווח למוצר');
  if (iProfit !== -1) {
    var pc = ps.getRange(r, iProfit + 1);
    if (!pc.getFormula()) {
      var sell = map.sell !== -1 ? num_(ps.getRange(r, map.sell + 1).getDisplayValue()) : 0;
      var cost = map.cost !== -1 ? num_(ps.getRange(r, map.cost + 1).getDisplayValue()) : 0;
      pc.setValue(sell - cost);
    }
  }
  return { ok: true, row: r, changed: changed };
}

function apiProfitDelete(p) {
  if (!p.row) throw new Error('חסר row');
  var ps = liveTazrim_().getSheetByName('ריווחיות');
  if (!ps) throw new Error('גיליון ריווחיות לא נמצא');
  var r = parseInt(p.row, 10);
  if (r < 2 || r > ps.getLastRow()) throw new Error('שורה לא תקינה');
  var name = ps.getRange(r, 1).getDisplayValue();
  ps.deleteRow(r);
  return { ok: true, deleted: name };
}

/* ============ edit_cell: עריכה גנרית של תא ============ */
function apiEditCell(p) {
  if (!p.file || !p.sheet || !p.a1) throw new Error('חסר file / sheet / a1');
  var id = p.file === 'budget' ? CONFIG.BUDGET_ID : CONFIG.TAZRIM_LIVE_ID;
  var ss = SpreadsheetApp.openById(id);
  var sh = ss.getSheetByName(p.sheet);
  if (!sh) throw new Error('גיליון לא נמצא: ' + p.sheet);
  var rng = sh.getRange(p.a1);
  // הגנה: לא לכתוב על תא עם נוסחה
  if (rng.getFormula()) throw new Error('התא ' + p.a1 + ' מכיל נוסחה — לא ניתן לעריכה ישירה');
  var val = p.value;
  var n = Number(String(val).replace(/[^\d.\-]/g, ''));
  rng.setValue(isNaN(n) || String(val).replace(/[\d.\-,\s₪]/g, '') ? val : n);
  CacheService.getScriptCache().remove('annual_v1');
  CacheService.getScriptCache().remove('tazrim_v1');
  return { ok: true, cell: p.a1, value: val };
}

/* ============ set_target: עדכון יעד הכנסה חודשי ============ */
function apiSetTarget(p) {
  if (!p.month || p.value === undefined) throw new Error('חסר month / value');
  var monthNames = ['ינואר', 'פברואר', 'מרץ', 'אפריל', 'מאי', 'יוני', 'יולי', 'אוגוסט', 'ספטמבר', 'אוקטובר', 'נובמבר', 'דצמבר'];
  var mi = monthNames.indexOf(normHdr_(p.month));
  if (mi === -1) throw new Error('חודש לא תקין: ' + p.month);
  var year = parseInt(p.year || String(new Date().getFullYear()), 10);
  var key = 'TARGETS_' + year;
  var props = PropertiesService.getScriptProperties();
  var targets = JSON.parse(props.getProperty(key) || '{}');
  targets[mi + 1] = Number(p.value) || 0;
  props.setProperty(key, JSON.stringify(targets));
  CacheService.getScriptCache().remove('annual_v2');
  return { ok: true, month: p.month, value: Number(p.value) || 0, year: year };
}

/* ============ set_budget: עדכון תקציב לקטגוריה+סניף ============ */
function apiSetBudget(p) {
  if (!p.category || p.value === undefined) throw new Error('חסר category / value');
  var val = Number(p.value) || 0;
  var branch = p.branch || 'rishon';
  var scope = p.scope || 'month';   // 'month' = חודש זה בלבד | 'all' = קבוע בקובץ

  if (scope === 'month') {
    if (!p.month) throw new Error('חסר month');
    var props = PropertiesService.getScriptProperties();
    var key = 'BUDGET_' + p.month;
    var ov = JSON.parse(props.getProperty(key) || '{}');
    ov[p.category] = ov[p.category] || {};
    if (branch === 'combined') {
      // פיצול לפי היחס הקיים בקובץ; אם אין — חצי-חצי
      var cur = readBaseBudget_(p.category);
      var tot = cur.r + cur.y;
      if (tot > 0) {
        ov[p.category].r = Math.round(val * cur.r / tot);
        ov[p.category].y = val - ov[p.category].r;
      } else {
        ov[p.category].r = Math.round(val / 2);
        ov[p.category].y = val - ov[p.category].r;
      }
    } else if (branch === 'yavne') {
      ov[p.category].y = val;
    } else {
      ov[p.category].r = val;
    }
    props.setProperty(key, JSON.stringify(ov));
    return { ok: true, scope: 'month', month: p.month, category: p.category, value: val };
  }

  // scope = 'all' — כתיבה קבועה לקובץ
  var f = baseBudgetSheet_();
  var sh = f.sheet, BH = f.headers;
  var iR = colIndex_(BH, 'תקציב ראשון'), iY = colIndex_(BH, 'תקציב יבנה');
  var names = sh.getRange(f.headerRow + 1, 1, sh.getLastRow() - f.headerRow, 1).getDisplayValues();
  var row = -1;
  for (var r = 0; r < names.length; r++) {
    if (normHdr_(names[r][0]) === normHdr_(p.category)) { row = f.headerRow + 1 + r; break; }
  }
  if (row === -1) throw new Error('קטגוריה לא נמצאה: ' + p.category);
  var cols = [];
  if (branch === 'combined') {
    var cur2 = { r: num_(sh.getRange(row, iR + 1).getDisplayValue()), y: num_(sh.getRange(row, iY + 1).getDisplayValue()) };
    var tot2 = cur2.r + cur2.y;
    var rv = tot2 > 0 ? Math.round(val * cur2.r / tot2) : Math.round(val / 2);
    cols.push({ c: iR, v: rv }, { c: iY, v: val - rv });
  } else if (branch === 'yavne') { cols.push({ c: iY, v: val }); }
  else { cols.push({ c: iR, v: val }); }

  cols.forEach(function (x) {
    if (x.c === -1) return;
    var rng = sh.getRange(row, x.c + 1);
    if (rng.getFormula()) throw new Error('תא התקציב מכיל נוסחה — לא ניתן לעריכה');
    rng.setValue(x.v);
  });
  return { ok: true, scope: 'all', category: p.category, branch: branch, value: val };
}

function readBaseBudget_(category) {
  try {
    var f = baseBudgetSheet_();
    var sh = f.sheet, BH = f.headers;
    var iR = colIndex_(BH, 'תקציב ראשון'), iY = colIndex_(BH, 'תקציב יבנה');
    var names = sh.getRange(f.headerRow + 1, 1, sh.getLastRow() - f.headerRow, 1).getDisplayValues();
    for (var r = 0; r < names.length; r++) {
      if (normHdr_(names[r][0]) === normHdr_(category)) {
        var row = f.headerRow + 1 + r;
        return { r: num_(sh.getRange(row, iR + 1).getDisplayValue()), y: num_(sh.getRange(row, iY + 1).getDisplayValue()) };
      }
    }
  } catch (e) {}
  return { r: 0, y: 0 };
}

/* ============ uncat: תנועות ביומן ללא סעיף תקציב ============ */
function apiUncatList(p) {
  var jf = journalSheet_();
  var sh = jf.sheet, hr = jf.headerRow, H = jf.headers;
  var last = sh.getLastRow();
  if (last <= hr) return { ok: true, rows: [] };
  var iHh = colIndex_(H, 'ח"ח'), iCat = colIndex_(H, 'סעיף'),
      iDate = colIndex_(H, 'תאריך'), iDesc = colIndex_(H, 'ספק'),
      iAmt = colIndex_(H, 'סכום (כולל'), iBr = colIndex_(H, 'סניף');
  if (iAmt === -1) iAmt = colIndex_(H, 'סכום');
  var data = sh.getRange(hr + 1, 1, last - hr, H.length).getDisplayValues();
  var month = p.month || '';
  var uncatAttribs = getAttribs_();
  var rows = [];
  data.forEach(function (r, i) {
    if (String(r.join('')).trim() === '') return;
    if (String(r[iCat]).trim() !== '') return;          // רק ללא סיווג
    if (month && effMonth_(r, iHh, iDate, iAmt, iDesc, uncatAttribs) !== month) return;
    rows.push({
      row: hr + 1 + i, date: r[iDate], desc: r[iDesc],
      amount: num_(r[iAmt]), branch: r[iBr]
    });
  });
  rows.sort(function (a, b) { return b.amount - a.amount; });
  return { ok: true, rows: rows.slice(0, 60), total: rows.length };
}

function apiUncatSet(p) {
  if (!p.row || !p.category) throw new Error('חסר row / category');
  var jf = journalSheet_();
  var sh = jf.sheet, H = jf.headers;
  var iCat = colIndex_(H, 'סעיף');
  if (iCat === -1) throw new Error('עמודת סעיף תקציב לא נמצאה');
  var r = parseInt(p.row, 10);
  var cell = sh.getRange(r, iCat + 1);
  if (cell.getFormula()) throw new Error('התא מכיל נוסחה');
  cell.setValue(p.category);
  // עדכון סניף אם נשלח
  if (p.branch) {
    var iBr = colIndex_(H, 'סניף');
    if (iBr !== -1) {
      var bc = sh.getRange(r, iBr + 1);
      if (!bc.getFormula()) bc.setValue(p.branch);
    }
  }
  invalidateBudgetCache_();
  return { ok: true, row: r, category: p.category };
}


/* ============ cash_list: תנועות בפועל שנקלטו ============ */
function apiCashList(p) {
  var ss = liveTazrim_();
  var sh = ss.getSheetByName(CONFIG.CASH_SHEET);
  if (!sh || sh.getLastRow() < 2) return { ok: true, rows: [], anchors: {} };
  var vals = sh.getRange(2, 1, sh.getLastRow() - 1, 8).getDisplayValues();
  var acc = p.account || '';
  var rows = [];
  vals.forEach(function (r, i) {
    if (String(r.join('')).trim() === '') return;
    if (acc && String(r[1]).trim() !== acc) return;
    rows.push({
      row: i + 2, date: r[0], account: r[1], desc: r[2],
      amount: num_(r[3]), type: r[4], inBudget: String(r[5]).trim(), source: r[6]
    });
  });
  rows.sort(function (a, b) {
    var da = parseDate_(a.date), db = parseDate_(b.date);
    return (db ? db.getTime() : 0) - (da ? da.getTime() : 0);
  });
  var anchors = JSON.parse(PropertiesService.getScriptProperties().getProperty('ANCHORS') || '{}');
  return { ok: true, rows: rows.slice(0, 80), total: rows.length, anchors: anchors };
}

/* ============ cash_to_budget: העברת תנועות בפועל אל יומן התקציב ============ */
function apiCashToBudget(p) {
  var ss = liveTazrim_();
  var sh = ss.getSheetByName(CONFIG.CASH_SHEET);
  if (!sh || sh.getLastRow() < 2) return { ok: true, moved: 0 };

  var wanted = null;
  if (p.rows) { try { wanted = JSON.parse(p.rows); } catch (e) {} }

  var vals = sh.getRange(2, 1, sh.getLastRow() - 1, 8).getDisplayValues();
  var meta = apiMeta();
  var supKeys = Object.keys(meta.suppliers || {});
  var SKIP_TYPES = ['אשראי', 'כאל', 'ישראכרט', 'אמקס', 'אמר', 'דיינרס', 'מקס', 'הלוואה'];

  var toAdd = [], markRows = [];
  vals.forEach(function (r, i) {
    var rowNum = i + 2;
    if (wanted && wanted.indexOf(rowNum) === -1) return;
    if (String(r[5]).trim() === 'כן') return;         // כבר בתקציב
    var amt = num_(r[3]);
    if (amt >= 0) return;                              // הכנסות לא נכנסות לתקציב
    var type = String(r[4]).trim();
    if (!wanted && SKIP_TYPES.some(function (t) { return type.indexOf(t) !== -1; })) return; // חיובי אשראי/הלוואות
    var desc = String(r[2]).trim();
    var match = null, dn = desc.replace(/\s+/g, '');
    for (var k = 0; k < supKeys.length; k++) {
      var kn = supKeys[k].replace(/\s+/g, '');
      if (dn.indexOf(kn) !== -1 || kn.indexOf(dn) !== -1) { match = meta.suppliers[supKeys[k]]; break; }
    }
    toAdd.push({
      date: r[0], desc: desc, amount: Math.abs(amt),
      branch: (match && match.branch) || (String(r[1]).indexOf('פועלים') !== -1 ? 'יבנה' : 'ראשון לציון'),
      category: (match && match.category) || '',
      vat: (match && match.vat) || 'אוטו', paid: '✅ שולם'
    });
    markRows.push(rowNum);
  });

  if (!toAdd.length) return { ok: true, moved: 0, note: 'אין תנועות להעברה' };

  // כתיבה במנות כדי לא לחרוג ממגבלות
  var added = 0, skipped = 0;
  for (var s = 0; s < toAdd.length; s += 20) {
    var res = apiAdd({ rows: JSON.stringify(toAdd.slice(s, s + 20)) });
    added += res.added; skipped += res.skipped;
  }
  // סימון שהועברו
  markRows.forEach(function (rn) { sh.getRange(rn, 6).setValue('כן'); });

  return { ok: true, moved: added, skipped: skipped, marked: markRows.length };
}


/* ============ settings: לוח קבלת הכנסות ותצורה ============ */
var DEFAULT_SETTINGS = {
  income: {
    weeklyDay: 4,        // 0=ראשון … 4=חמישי — יום הסליקה השבועי
    siteDays: [2, 8],    // ימים בחודש שבהם נכנסות הכנסות מהאתר
    sitePct: 0,          // אחוז ההכנסות שמגיע דרך האתר (0 = סכום קבוע)
    siteAmount: 0        // סכום חודשי קבוע מהאתר
  }
};

function getSettings_() {
  var raw = PropertiesService.getScriptProperties().getProperty('SETTINGS');
  var s = raw ? JSON.parse(raw) : {};
  var out = JSON.parse(JSON.stringify(DEFAULT_SETTINGS));
  if (s.income) Object.keys(s.income).forEach(function (k) { out.income[k] = s.income[k]; });
  return out;
}

function apiSettingsGet(p) {
  return { ok: true, settings: getSettings_() };
}

function apiSettingsSet(p) {
  var cur = getSettings_();
  if (p.income) {
    var inc;
    try { inc = JSON.parse(p.income); } catch (e) { throw new Error('income JSON לא תקין'); }
    Object.keys(inc).forEach(function (k) { cur.income[k] = inc[k]; });
  }
  PropertiesService.getScriptProperties().setProperty('SETTINGS', JSON.stringify(cur));
  CacheService.getScriptCache().remove('tazrim_v1');
  return { ok: true, settings: cur };
}

/* ============ תחזית אוטומטית: לומדת מההיסטוריה ============ */
var FC_MARK = 'תחזית אוטומטית';

function fcKey_(desc) {
  // נרמול תיאור: הסרת מספרים, תאריכים וסימנים כדי לזהות את אותו ספק בין חודשים
  return String(desc).replace(/[0-9]/g, '').replace(/[^\u0590-\u05FFa-zA-Z ]/g, ' ')
    .replace(/\s+/g, ' ').trim().slice(0, 24);
}
function median_(arr) {
  if (!arr.length) return 0;
  var a = arr.slice().sort(function (x, y) { return x - y; });
  var m = Math.floor(a.length / 2);
  return a.length % 2 ? a[m] : (a[m - 1] + a[m]) / 2;
}

function apiForecastSuggest(p) {
  var days = parseInt(p.days || '60', 10);
  var source = p.source || 'bank';   // 'bank' = תנועות עו"ש בפועל | 'journal' = יומן ההוצאות
  var today = new Date(); today.setHours(0, 0, 0, 0);
  var horizon = new Date(today); horizon.setDate(horizon.getDate() + days);
  var expenses = [];
  var CARD_TYPES = ['אשראי', 'כאל', 'ישראכרט', 'אמקס', 'אמר', 'דיינרס', 'מקס'];

  /* --- מקור א': תנועות עו"ש בפועל (מומלץ לתזרים) --- */
  var bankOK = false;
  if (source === 'bank') {
    try {
      var cs = liveTazrim_().getSheetByName(CONFIG.CASH_SHEET);
      if (cs && cs.getLastRow() > 1) {
        var cv = cs.getRange(2, 1, cs.getLastRow() - 1, 8).getDisplayValues();
        var direct = {}, cards = {};
        var monthsSeenAll = {};
        cv.forEach(function (r) {
          var d = parseDate_(r[0]);
          if (!d) return;
          var amt = num_(r[3]);
          if (amt >= 0) return;                       // רק הוצאות
          amt = Math.abs(amt);
          var mk = d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2);
          monthsSeenAll[mk] = 1;
          var type = String(r[4]).trim();
          var isCard = CARD_TYPES.some(function (t) { return type.indexOf(t) !== -1; });
          if (isCard) {
            // חיוב אשראי מרוכז — מקבצים לפי חשבון+חברה
            var ck = String(r[1]).trim() + '§' + type;
            cards[ck] = cards[ck] || { account: r[1], type: type, amounts: [], days: [], months: {} };
            cards[ck].amounts.push(amt);
            cards[ck].days.push(d.getDate());
            cards[ck].months[mk] = 1;
          } else {
            var key = fcKey_(r[2]);
            if (key.length < 3) return;
            direct[key] = direct[key] || { desc: String(r[2]).trim(), amounts: [], days: [], months: {}, account: r[1] };
            direct[key].amounts.push(amt);
            direct[key].days.push(d.getDate());
            direct[key].months[mk] = 1;
          }
        });

        var monthsTotal = Object.keys(monthsSeenAll).length;
        bankOK = monthsTotal >= 2;

        if (bankOK) {
          // חיובי אשראי מרוכזים
          Object.keys(cards).forEach(function (k) {
            var c = cards[k];
            var amt = Math.round(median_(c.amounts));
            var dom = Math.round(median_(c.days));
            var ms = Object.keys(c.months).length;
            if (amt <= 0) return;
            var cur = new Date(today.getFullYear(), today.getMonth(), Math.min(dom, 28));
            for (var i = 0; i < 4; i++) {
              if (cur > today && cur <= horizon) {
                expenses.push({
                  date: ('0' + cur.getDate()).slice(-2) + '/' + ('0' + (cur.getMonth() + 1)).slice(-2) + '/' + cur.getFullYear(),
                  desc: 'חיוב אשראי — ' + c.type, amount: amt,
                  branch: String(c.account).indexOf('פועלים') !== -1 ? 'יבנה' : 'ראשון לציון',
                  category: 'אשראי', months: ms, confidence: Math.min(95, 50 + ms * 15),
                  kind: 'הוצאה', account: c.account, isCardCharge: true
                });
              }
              cur = new Date(cur.getFullYear(), cur.getMonth() + 1, Math.min(dom, 28));
            }
          });
          // הוצאות ישירות מהעו"ש
          Object.keys(direct).forEach(function (k) {
            var g = direct[k];
            var ms = Object.keys(g.months).length;
            if (ms < 2) return;
            var amt = Math.round(median_(g.amounts));
            var dom = Math.round(median_(g.days));
            if (amt <= 0) return;
            var avg = g.amounts.reduce(function (s, x) { return s + x; }, 0) / g.amounts.length;
            var dev = g.amounts.reduce(function (s, x) { return s + Math.abs(x - avg); }, 0) / g.amounts.length;
            var stable = avg > 0 ? (1 - Math.min(1, dev / avg)) : 0;
            var cur2 = new Date(today.getFullYear(), today.getMonth(), Math.min(dom, 28));
            for (var j = 0; j < 4; j++) {
              if (cur2 > today && cur2 <= horizon) {
                expenses.push({
                  date: ('0' + cur2.getDate()).slice(-2) + '/' + ('0' + (cur2.getMonth() + 1)).slice(-2) + '/' + cur2.getFullYear(),
                  desc: g.desc, amount: amt,
                  branch: String(g.account).indexOf('פועלים') !== -1 ? 'יבנה' : 'ראשון לציון',
                  category: '', months: ms,
                  confidence: Math.round(Math.min(97, ms * 18 + stable * 40)),
                  kind: 'הוצאה', account: g.account
                });
              }
              cur2 = new Date(cur2.getFullYear(), cur2.getMonth() + 1, Math.min(dom, 28));
            }
          });
        }
      }
    } catch (e) { /* אין טאב תנועות בפועל */ }
  }

  /* --- מקור ב': יומן ההוצאות (גיבוי, או לפי בקשה) --- */
  if (!bankOK) {
    var jf = journalSheet_();
    var JH = jf.headers;
    var iDate = colIndex_(JH, 'תאריך'), iDesc = colIndex_(JH, 'ספק'),
        iAmt = colIndex_(JH, 'סכום (כולל'), iBr = colIndex_(JH, 'סניף'),
        iCat = colIndex_(JH, 'סעיף');
    if (iAmt === -1) iAmt = colIndex_(JH, 'סכום');
    var jvals = jf.sheet.getLastRow() > jf.headerRow
      ? jf.sheet.getRange(jf.headerRow + 1, 1, jf.sheet.getLastRow() - jf.headerRow, JH.length).getDisplayValues()
      : [];

    var groups = {};
    jvals.forEach(function (r) {
      var d = parseDate_(r[iDate]);
      if (!d) return;
      var amt = num_(r[iAmt]);
      if (amt <= 0) return;
      var key = fcKey_(r[iDesc]);
      if (key.length < 3) return;
      groups[key] = groups[key] || { key: key, desc: String(r[iDesc]).trim(), amounts: [], daysOfMonth: [], months: {}, branch: r[iBr], category: r[iCat], last: null };
      var g = groups[key];
      g.amounts.push(amt);
      g.daysOfMonth.push(d.getDate());
      g.months[d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2)] = 1;
      if (!g.last || d > g.last) g.last = d;
      if (r[iCat]) g.category = r[iCat];
      if (r[iBr]) g.branch = r[iBr];
    });

    var cutoff = new Date(today); cutoff.setDate(cutoff.getDate() - 70);
    Object.keys(groups).forEach(function (k) {
      var g = groups[k];
      var monthsSeen = Object.keys(g.months).length;
      if (monthsSeen < 3) return;
      if (!g.last || g.last < cutoff) return;
      var amt = Math.round(median_(g.amounts));
      var dom = Math.round(median_(g.daysOfMonth));
      if (amt <= 0) return;
      var avg = g.amounts.reduce(function (s, x) { return s + x; }, 0) / g.amounts.length;
      var dev = g.amounts.reduce(function (s, x) { return s + Math.abs(x - avg); }, 0) / g.amounts.length;
      var stable = avg > 0 ? (1 - Math.min(1, dev / avg)) : 0;
      var confidence = Math.round(Math.min(99, (monthsSeen * 12) + (stable * 45)));
      var cur3 = new Date(today.getFullYear(), today.getMonth(), Math.min(dom, 28));
      for (var i2 = 0; i2 < 4; i2++) {
        if (cur3 > today && cur3 <= horizon) {
          expenses.push({
            date: ('0' + cur3.getDate()).slice(-2) + '/' + ('0' + (cur3.getMonth() + 1)).slice(-2) + '/' + cur3.getFullYear(),
            desc: g.desc, amount: amt, branch: g.branch || 'משותף',
            category: g.category || '', months: monthsSeen, confidence: confidence, kind: 'הוצאה'
          });
        }
        cur3 = new Date(cur3.getFullYear(), cur3.getMonth() + 1, Math.min(dom, 28));
      }
    });
  }
  expenses.sort(function (a, b) { return parseDate_(a.date) - parseDate_(b.date); });

  /* --- 2. הכנסות צפויות — מרוכזות בימי הקבלה בפועל --- */
  var income = [];
  var incomeMeta = null;
  try {
    var sched = getSettings_().income;
    var rf = dailyRevenueSheet_();
    var RH = rf.headers;
    var iRD = colIndex_(RH, 'תאריך'), iTot = colIndex_(RH, 'סה"כ יומי');
    var rvals = rf.sheet.getRange(rf.headerRow + 1, 1, Math.max(1, rf.sheet.getLastRow() - rf.headerRow), RH.length).getDisplayValues();
    var back = new Date(today); back.setDate(back.getDate() - 84); // 12 שבועות

    // סכימה לפי שבוע ולפי חודש
    var weekSums = {}, monthSums = {};
    rvals.forEach(function (row) {
      var d = parseDate_(row[iRD]);
      if (!d || d < back || d > today) return;
      var t = num_(row[iTot]);
      if (t <= 0) return;
      var wk = Math.floor((d - back) / (7 * 86400000));
      weekSums[wk] = (weekSums[wk] || 0) + t;
      var mk = d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2);
      monthSums[mk] = (monthSums[mk] || 0) + t;
    });

    var weekVals = Object.keys(weekSums).map(function (k) { return weekSums[k]; })
      .filter(function (v) { return v > 0; });
    var monthVals = Object.keys(monthSums).map(function (k) { return monthSums[k]; })
      .filter(function (v) { return v > 0; });

    var weeklyTotal = Math.round(median_(weekVals));
    var monthlyTotal = Math.round(median_(monthVals));

    // הפרדת הכנסות האתר מהכנסות המקום
    var siteMonthly = 0;
    if (sched.sitePct > 0) siteMonthly = Math.round(monthlyTotal * sched.sitePct / 100);
    else if (sched.siteAmount > 0) siteMonthly = Math.round(sched.siteAmount);
    var siteShareOfWeek = monthlyTotal > 0 ? (siteMonthly / monthlyTotal) : 0;
    var venueWeekly = Math.round(weeklyTotal * (1 - siteShareOfWeek));

    var dowNames = ['ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת'];
    var siteDays = sched.siteDays || [];
    var perSiteDay = siteDays.length ? Math.round(siteMonthly / siteDays.length) : 0;

    var cur2 = new Date(today); cur2.setDate(cur2.getDate() + 1);
    while (cur2 <= horizon) {
      // סליקה שבועית ביום שנקבע
      if (cur2.getDay() === sched.weeklyDay && venueWeekly > 0 && weekVals.length >= 2) {
        income.push({
          date: ('0' + cur2.getDate()).slice(-2) + '/' + ('0' + (cur2.getMonth() + 1)).slice(-2) + '/' + cur2.getFullYear(),
          desc: 'סליקה שבועית (' + dowNames[sched.weeklyDay] + ')',
          amount: venueWeekly, branch: 'משותף', category: 'הכנסות',
          confidence: Math.min(92, 55 + weekVals.length * 4), kind: 'הכנסה'
        });
      }
      // הכנסות האתר בימים הקבועים בחודש
      if (perSiteDay > 0 && siteDays.indexOf(cur2.getDate()) !== -1) {
        income.push({
          date: ('0' + cur2.getDate()).slice(-2) + '/' + ('0' + (cur2.getMonth() + 1)).slice(-2) + '/' + cur2.getFullYear(),
          desc: 'הכנסות מהאתר (' + cur2.getDate() + ' לחודש)',
          amount: perSiteDay, branch: 'משותף', category: 'הכנסות',
          confidence: 80, kind: 'הכנסה'
        });
      }
      cur2 = new Date(cur2.getTime() + 86400000);
    }
    incomeMeta = { weeklyTotal: weeklyTotal, monthlyTotal: monthlyTotal, venueWeekly: venueWeekly, siteMonthly: siteMonthly };
  } catch (e) { /* אין טבלת הכנסות — ממשיכים בלי */ }

  /* --- 3. סינון כפילויות מול תחזית קיימת --- */
  var existing = {};
  try {
    var ps = planSheet_();
    if (ps.getLastRow() >= 4) {
      var pv = ps.getRange(4, 1, ps.getLastRow() - 3, 8).getDisplayValues();
      pv.forEach(function (r) {
        var d = parseDate_(r[0]);
        if (!d || d < today || d > horizon) return;
        existing[fcKey_(r[3]) + '|' + d.getMonth() + '-' + d.getDate()] = true;
      });
    }
  } catch (e) {}
  function notDup(x) {
    var d = parseDate_(x.date);
    return !existing[fcKey_(x.desc) + '|' + d.getMonth() + '-' + d.getDate()];
  }

  return {
    ok: true, days: days,
    expenses: expenses.filter(notDup).slice(0, 80),
    income: income.filter(notDup).slice(0, 80),
    totalExpense: expenses.filter(notDup).reduce(function (s, x) { return s + x.amount; }, 0),
    totalIncome: income.filter(notDup).reduce(function (s, x) { return s + x.amount; }, 0),
    incomeMeta: incomeMeta, schedule: getSettings_().income,
    expenseSource: bankOK ? 'תנועות עו"ש בפועל (אשראי מרוכז)' : 'יומן ההוצאות'
  };
}

function apiForecastApply(p) {
  var items;
  try { items = JSON.parse(p.rows || '[]'); } catch (e) { throw new Error('rows JSON לא תקין'); }
  if (!items.length) throw new Error('אין שורות');
  var lock = LockService.getScriptLock();
  lock.waitLock(25000);
  try {
    var sh = planSheet_();
    var out = [];
    items.forEach(function (it) {
      var kind = it.kind || 'הוצאה';
      var amt = Math.abs(Number(it.amount) || 0);
      if (kind === 'הוצאה') amt = -amt;
      var branch = it.branch || 'משותף';
      var r = amt, y = amt;
      if (branch.indexOf('ראשון') !== -1) { y = 0; }
      else if (branch.indexOf('יבנה') !== -1) { r = 0; }
      else { r = amt / 2; y = amt / 2; }
      out.push([
        it.date, kind, it.category || (kind === 'הכנסה' ? 'הכנסות' : 'הוצאות קבועות'),
        it.desc, it.account || (branch.indexOf('יבנה') !== -1 ? 'פועלים' : 'דיסקונט'),
        amt, it.confidence >= 70 ? 'ודאי' : 'משוער',
        FC_MARK, 'מרכז פיננסי', branch, r, y
      ]);
    });
    if (out.length) {
      sh.getRange(sh.getLastRow() + 1, 1, out.length, 12).setValues(out);
    }
    CacheService.getScriptCache().remove('tazrim_v1');
    return { ok: true, added: out.length };
  } finally {
    lock.releaseLock();
  }
}

function apiForecastClear(p) {
  var sh = planSheet_();
  if (sh.getLastRow() < 4) return { ok: true, removed: 0 };
  var today = new Date(); today.setHours(0, 0, 0, 0);
  var vals = sh.getRange(4, 1, sh.getLastRow() - 3, 8).getDisplayValues();
  var toDelete = [];
  vals.forEach(function (r, i) {
    if (String(r[7]).trim() !== FC_MARK) return;
    var d = parseDate_(r[0]);
    if (!d || d < today) return;   // לא נוגעים בעבר
    toDelete.push(i + 4);
  });
  // מחיקה מלמטה למעלה כדי לא לשבש אינדקסים
  toDelete.sort(function (a, b) { return b - a; });
  toDelete.forEach(function (rn) { sh.deleteRow(rn); });
  CacheService.getScriptCache().remove('tazrim_v1');
  return { ok: true, removed: toDelete.length };
}


/* ============ diag: אבחון — מה המערכת רואה ביומן ============ */
function apiDiag(p) {
  var month = p.month || currentMonth_();
  var out = { ok: true, month: month };

  // רשימת כל הגיליונות בשני הקבצים — לזהות שמות בפועל
  try {
    out.budgetSheets = ss_().getSheets().map(function (s) {
      return s.getName() + ' [' + s.getLastRow() + 'x' + s.getLastColumn() + ']';
    });
  } catch (e) { out.budgetFileError = e.message; }
  try {
    out.tazrimSheets = liveTazrim_().getSheets().map(function (s) {
      return s.getName() + ' [' + s.getLastRow() + 'x' + s.getLastColumn() + ']';
    });
  } catch (e) { out.tazrimFileError = e.message; }

  try {
    var jf = journalSheet_();
    var H = jf.headers;
    out.journalSheet = jf.sheet.getName();
    out.headerRow = jf.headerRow;
    out.headers = H;
    out.lastRow = jf.sheet.getLastRow();
    var iHh = colIndex_(H, 'ח"ח'), iDate = colIndex_(H, 'תאריך'),
        iCat = colIndex_(H, 'סעיף'), iAmt = colIndex_(H, 'סכום (כולל');
    if (iAmt === -1) iAmt = colIndex_(H, 'סכום');
    out.cols = { date: iDate, amount: iAmt, category: iCat, chargeMonth: iHh };
    if (iHh !== -1 && jf.sheet.getLastRow() > jf.headerRow) {
      var f = jf.sheet.getRange(jf.headerRow + 1, iHh + 1).getFormula();
      out.chargeMonthIsFormula = !!f;
    }
    // דגימת הפורמטים שכבר קיימים בעמודות החודש — כדי לוודא התאמה בכתיבה
    out.monthFormats = {};
    [['ח"ח', iHh], ['חודש', colIndex_(H, 'חודש')]].forEach(function (pair) {
      var ci = pair[1];
      if (ci === -1 || jf.sheet.getLastRow() <= jf.headerRow) return;
      var n = Math.min(6, jf.sheet.getLastRow() - jf.headerRow);
      var sample = jf.sheet.getRange(jf.sheet.getLastRow() - n + 1, ci + 1, n, 1)
        .getDisplayValues().map(function (r) { return String(r[0]).trim(); })
        .filter(function (v) { return v; });
      out.monthFormats[pair[0]] = {
        column: ci,
        samples: sample.slice(-4),
        willWrite: formatLikeExisting_(jf.sheet, jf.headerRow, ci, currentMonth_())
      };
    });
    if (jf.sheet.getLastRow() > jf.headerRow) {
      var n = jf.sheet.getLastRow() - jf.headerRow;
      var all = jf.sheet.getRange(jf.headerRow + 1, 1, n, H.length).getDisplayValues();
      out.totalRows = all.length;
      var monthCount = 0, uncatCount = 0, monthsFound = {};
      all.forEach(function (r) {
        if (String(r.join('')).trim() === '') return;
        var m = rowMonth_(r, iHh, iDate);
        monthsFound[m] = (monthsFound[m] || 0) + 1;
        if (m === month) {
          monthCount++;
          if (!String(r[iCat]).trim()) uncatCount++;
        }
      });
      out.rowsInMonth = monthCount;
      out.uncategorizedInMonth = uncatCount;
      out.monthsFound = monthsFound;
      out.lastRows = all.slice(-5).map(function (r) {
        return { date: r[iDate], desc: r[colIndex_(H, 'ספק')], amount: r[iAmt],
                 category: r[iCat], chargeMonthRaw: iHh !== -1 ? r[iHh] : '(אין עמודה)',
                 resolvedMonth: rowMonth_(r, iHh, iDate) };
      });
    }
  } catch (e) { out.journalError = e.message; }
  return out;
}


// חודש של שורה: קודם עמודת ח"ח, ואם חסרה/בפורמט אחר — נגזר מהתאריך.
/* ============ repair: שחזור סכומים שנהרסו על ידי דגל המע"מ ============ */
function apiRepairAmounts(p) {
  var jf = journalSheet_();
  var sh = jf.sheet, hr = jf.headerRow, H = jf.headers;
  var iDate = colIndex_(H, 'תאריך'), iDesc = colIndex_(H, 'ספק'),
      iAmt = colIndex_(H, 'סכום (כולל');
  if (iAmt === -1) iAmt = colIndex_(H, 'סכום');
  if (iAmt === -1) throw new Error('לא נמצאה עמודת סכום');

  var last = sh.getLastRow();
  if (last <= hr) return { ok: true, damaged: 0 };
  var vals = sh.getRange(hr + 1, 1, last - hr, H.length).getDisplayValues();

  // איתור שורות פגומות: עמודת הסכום מכילה טקסט לא-מספרי
  var damaged = [];
  vals.forEach(function (r, i) {
    if (String(r.join('')).trim() === '') return;
    var raw = String(r[iAmt]).trim();
    if (!raw) return;
    var isNum = /^[\(\-−]?[\d,]+(\.\d+)?\)?\s*₪?$/.test(raw);
    if (isNum) return;
    damaged.push({ row: hr + 1 + i, date: r[iDate], desc: String(r[iDesc]).trim(), raw: raw });
  });

  if (!damaged.length) return { ok: true, damaged: 0, note: 'לא נמצאו שורות פגומות' };
  if (p.scan) return { ok: true, damaged: damaged.length, sample: damaged.slice(0, 10) };

  // מקור שחזור: "תנועות בפועל" בקובץ התזרים (תאריך + תיאור → סכום)
  var lookup = {};
  try {
    var cs = liveTazrim_().getSheetByName(CONFIG.CASH_SHEET);
    if (cs && cs.getLastRow() > 1) {
      var cv = cs.getRange(2, 1, cs.getLastRow() - 1, 4).getDisplayValues();
      cv.forEach(function (r) {
        var key = String(r[0]).trim() + '|' + String(r[2]).replace(/\s+/g, '').slice(0, 20);
        lookup[key] = Math.abs(num_(r[3]));
      });
    }
  } catch (e) {}

  var fixed = 0, unresolved = [];
  damaged.forEach(function (d) {
    var key = String(d.date).trim() + '|' + d.desc.replace(/\s+/g, '').slice(0, 20);
    var amt = lookup[key];
    if (amt && amt > 0) {
      sh.getRange(d.row, iAmt + 1).setValue(amt);
      fixed++;
    } else {
      unresolved.push(d);
    }
  });

  // מחיקת שורות שלא ניתן לשחזר (רק אם התבקש במפורש)
  var removed = 0;
  if (p.deleteUnresolved === 'true' && unresolved.length) {
    unresolved.map(function (u) { return u.row; })
      .sort(function (a, b) { return b - a; })
      .forEach(function (rn) { sh.deleteRow(rn); removed++; });
  }

  CacheService.getScriptCache().remove('meta_v1');
  return {
    ok: true, damaged: damaged.length, fixed: fixed,
    unresolved: unresolved.length, removed: removed,
    unresolvedSample: unresolved.slice(0, 10)
  };
}


/* ============ undo_import: ביטול קליטת בנק מהיומן ============ */
function apiUndoImport(p) {
  var jf = journalSheet_();
  var sh = jf.sheet, hr = jf.headerRow, H = jf.headers;
  var iDate = colIndex_(H, 'תאריך'), iDesc = colIndex_(H, 'ספק'),
      iAmt = colIndex_(H, 'סכום (כולל');
  if (iAmt === -1) iAmt = colIndex_(H, 'סכום');

  // מזהים שורות שמקורן בקליטת בנק לפי התאמה ל"תנועות בפועל"
  var lookup = {};
  try {
    var cs = liveTazrim_().getSheetByName(CONFIG.CASH_SHEET);
    if (cs && cs.getLastRow() > 1) {
      var cv = cs.getRange(2, 1, cs.getLastRow() - 1, 4).getDisplayValues();
      cv.forEach(function (r) {
        var k = String(r[0]).trim() + '|' + String(r[2]).replace(/\s+/g, '').slice(0, 20);
        lookup[k] = true;
      });
    }
  } catch (e) {}

  var last = sh.getLastRow();
  if (last <= hr) return { ok: true, found: 0 };
  var vals = sh.getRange(hr + 1, 1, last - hr, H.length).getDisplayValues();

  var hits = [];
  vals.forEach(function (r, i) {
    if (String(r.join('')).trim() === '') return;
    var k = String(r[iDate]).trim() + '|' + String(r[iDesc]).replace(/\s+/g, '').slice(0, 20);
    if (lookup[k]) {
      hits.push({ row: hr + 1 + i, date: r[iDate], desc: String(r[iDesc]).trim(), amount: r[iAmt] });
    }
  });

  if (p.scan) return { ok: true, found: hits.length, sample: hits.slice(0, 12) };
  if (!hits.length) return { ok: true, found: 0, removed: 0, note: 'לא נמצאו שורות מקליטת בנק' };

  var removed = 0;
  hits.map(function (h) { return h.row; })
    .sort(function (a, b) { return b - a; })
    .forEach(function (rn) { sh.deleteRow(rn); removed++; });

  invalidateBudgetCache_();
  return { ok: true, found: hits.length, removed: removed };
}


/* ============ dup_check: איתור כפילויות לפי תאריך+סכום (מתעלם מהתיאור) ============ */
function apiDupCheck(p) {
  var items;
  try { items = JSON.parse(p.rows || '[]'); } catch (e) { throw new Error('rows JSON לא תקין'); }
  if (!items.length) return { ok: true, dups: [] };

  var jf = journalSheet_();
  var sh = jf.sheet, hr = jf.headerRow, H = jf.headers;
  var iDate = colIndex_(H, 'תאריך'), iDesc = colIndex_(H, 'ספק'),
      iAmt = colIndex_(H, 'סכום (כולל');
  if (iAmt === -1) iAmt = colIndex_(H, 'סכום');
  var last = sh.getLastRow();
  if (last <= hr) return { ok: true, dups: [] };
  var vals = sh.getRange(hr + 1, 1, last - hr, H.length).getDisplayValues();

  var idx = {};
  vals.forEach(function (r) {
    var d = parseDate_(r[iDate]);
    if (!d) return;
    var a = Math.round(num_(r[iAmt]));
    if (!a) return;
    var k = d.getTime() + '|' + a;
    if (!idx[k]) idx[k] = String(r[iDesc]).trim();
  });

  var dups = [];
  items.forEach(function (it, i) {
    var d = parseDate_(it.date);
    if (!d) return;
    var a = Math.round(Number(it.amount) || 0);
    var k = d.getTime() + '|' + a;
    if (idx[k]) dups.push({ i: i, date: it.date, desc: it.desc, amount: a, existing: idx[k] });
  });
  return { ok: true, dups: dups, checked: items.length };
}

/* ============ cat_detail: כל ההוצאות תחת קטגוריה ============ */
function apiCatDetail(p) {
  if (!p.month) throw new Error('חסר month');
  var wantCat = p.category || '';
  var branch = p.branch || 'combined';
  var jf = journalSheet_();
  var sh = jf.sheet, hr = jf.headerRow, H = jf.headers;
  var iHh = colIndex_(H, 'ח"ח'), iJD = colIndex_(H, 'תאריך'),
      iDesc = colIndex_(H, 'ספק'), iAmt = colIndex_(H, 'סכום (כולל'),
      iBr = colIndex_(H, 'סניף'), iCat = colIndex_(H, 'סעיף');
  if (iAmt === -1) iAmt = colIndex_(H, 'סכום');
  var last = sh.getLastRow();
  if (last <= hr) return { ok: true, rows: [] };
  var vals = sh.getRange(hr + 1, 1, last - hr, H.length).getDisplayValues();

  var catAttribs = getAttribs_();
  var rows = [], total = 0;
  vals.forEach(function (r, i) {
    if (String(r.join('')).trim() === '') return;
    if (effMonth_(r, iHh, iJD, iAmt, iDesc, catAttribs) !== p.month) return;
    var cat = String(r[iCat]).trim();
    if (wantCat === '__none__') { if (cat) return; }
    else if (normHdr_(cat) !== normHdr_(wantCat)) return;
    var isY = String(r[iBr]).indexOf('יבנה') !== -1;
    if (branch === 'rishon' && isY) return;
    if (branch === 'yavne' && !isY) return;
    var amt = num_(r[iAmt]);
    total += amt;
    rows.push({ row: hr + 1 + i, date: r[iJD], desc: String(r[iDesc]).trim(), amount: amt, branch: r[iBr] });
  });
  rows.sort(function (a, b) { return b.amount - a.amount; });
  return { ok: true, category: wantCat, month: p.month, rows: rows.slice(0, 80), total: Math.round(total), count: rows.length };
}

/* ============ attrib_set: שיוך תנועה קיימת לחודש אחר ============ */
function apiAttribSet(p) {
  if (!p.row || !p.month) throw new Error('חסר row / month');
  var jf = journalSheet_();
  var sh = jf.sheet, H = jf.headers;
  var iDate = colIndex_(H, 'תאריך'), iDesc = colIndex_(H, 'ספק'),
      iAmt = colIndex_(H, 'סכום (כולל'), iHh = colIndex_(H, 'ח"ח');
  if (iAmt === -1) iAmt = colIndex_(H, 'סכום');
  var r = parseInt(p.row, 10);
  var vals = sh.getRange(r, 1, 1, H.length).getDisplayValues()[0];

  var wroteDirect = false;
  if (iHh !== -1) {
    var cell = sh.getRange(r, iHh + 1);
    if (!cell.getFormula()) { cell.setValue(p.month); wroteDirect = true; }
  }
  if (!wroteDirect) {
    saveAttribs_([{ key: attribKey_(vals[iDate], vals[iAmt], vals[iDesc]), month: p.month }]);
  }
  return { ok: true, row: r, month: p.month, method: wroteDirect ? 'ח"ח' : 'מפת שיוכים' };
}

function invalidateBudgetCache_() {
  var c = CacheService.getScriptCache();
  var keys = [];
  var d = new Date();
  for (var i = -2; i <= 1; i++) {
    var m = new Date(d.getFullYear(), d.getMonth() + i, 1);
    keys.push('budget_' + m.getFullYear() + '-' + ('0' + (m.getMonth() + 1)).slice(-2));
  }
  keys.push('annual_v2', 'meta_v1');
  try { c.removeAll(keys); } catch (e) {}
}

/* ============ Utils ============ */
// מפת שיוכי חודש — לשימוש כשעמודת ח"ח היא נוסחה ולא ניתנת לכתיבה
// מזהה את הפורמט שכבר נהוג בעמודה ומחזיר את החודש באותו פורמט.
// מונע הנחות שגויות (למשל "יולי 2026" כשהמערכות מצפות ל-2026-07).
var MONTHS_HE = ['ינואר','פברואר','מרץ','אפריל','מאי','יוני','יולי','אוגוסט','ספטמבר','אוקטובר','נובמבר','דצמבר'];
function formatLikeExisting_(sh, headerRow, colIdx, isoMonth) {
  var mp = String(isoMonth).split('-');
  if (mp.length < 2) return isoMonth;
  var y = mp[0], mNum = mp[1], mi = parseInt(mNum, 10) - 1;

  var cacheKey = 'fmt_' + sh.getSheetId() + '_' + colIdx;
  var cache = CacheService.getScriptCache();
  var style = cache.get(cacheKey);

  if (!style) {
    style = 'iso';
    var last = sh.getLastRow();
    if (last > headerRow) {
      var n = Math.min(40, last - headerRow);
      var vals = sh.getRange(last - n + 1, colIdx + 1, n, 1).getDisplayValues();
      var counts = {};
      vals.forEach(function (r) {
        var v = String(r[0]).trim();
        if (!v) return;
        var s = null;
        if (/^\d{4}-\d{1,2}$/.test(v)) s = 'iso';              // 2026-07
        else if (/^\d{1,2}\/\d{4}$/.test(v)) s = 'slash';      // 07/2026
        else if (/^\d{1,2}\.\d{4}$/.test(v)) s = 'dot';        // 07.2026
        else if (/^\d{1,2}$/.test(v)) s = 'num';               // 7
        else {
          for (var q = 0; q < MONTHS_HE.length; q++) {
            if (v.indexOf(MONTHS_HE[q]) === 0) {
              s = /\d{4}/.test(v) ? 'heYear' : 'heOnly';       // יולי 2026 / יולי
              break;
            }
          }
        }
        if (s) counts[s] = (counts[s] || 0) + 1;
      });
      var best = null, bestN = 0;
      Object.keys(counts).forEach(function (k) { if (counts[k] > bestN) { bestN = counts[k]; best = k; } });
      if (best) style = best;
    }
    cache.put(cacheKey, style, 600);
  }

  switch (style) {
    case 'slash':  return mNum + '/' + y;
    case 'dot':    return mNum + '.' + y;
    case 'num':    return String(parseInt(mNum, 10));
    case 'heYear': return MONTHS_HE[mi] + ' ' + y;
    case 'heOnly': return MONTHS_HE[mi];
    default:       return y + '-' + mNum;
  }
}

function attribKey_(date, amount, desc) {
  var a = Math.round(Math.abs(Number(String(amount).replace(/[^\d.\-]/g, '')) || 0));
  var d = String(date).trim();
  var s = String(desc).replace(/\s+/g, '').slice(0, 16);
  return d + '|' + a + '|' + s;
}
function getAttribs_() {
  try { return JSON.parse(PropertiesService.getScriptProperties().getProperty('ATTRIBS') || '{}'); }
  catch (e) { return {}; }
}
function saveAttribs_(list) {
  var props = PropertiesService.getScriptProperties();
  var cur = getAttribs_();
  list.forEach(function (x) { cur[x.key] = x.month; });
  // שמירה על גודל סביר — 800 רשומות אחרונות
  var keys = Object.keys(cur);
  if (keys.length > 800) {
    var trimmed = {};
    keys.slice(keys.length - 800).forEach(function (k) { trimmed[k] = cur[k]; });
    cur = trimmed;
  }
  props.setProperty('ATTRIBS', JSON.stringify(cur));
}
// החודש האפקטיבי של שורה: קודם שיוך ידני, אחר כך ח"ח, ולבסוף התאריך
function effMonth_(row, iHh, iDate, iAmt, iDesc, attribs) {
  if (attribs && iAmt !== -1 && iDesc !== -1) {
    var k = attribKey_(row[iDate], row[iAmt], row[iDesc]);
    if (attribs[k]) return attribs[k];
  }
  return rowMonth_(row, iHh, iDate);
}
function rowMonth_(row, iHh, iDate) {
  if (iHh !== undefined && iHh !== -1) {
    var v = String(row[iHh] || '').trim();
    if (/^\d{4}-\d{2}$/.test(v)) return v;
    var m2 = v.match(/^(\d{1,2})\/(\d{4})$/);            // 07/2026
    if (m2) return m2[2] + '-' + ('0' + m2[1]).slice(-2);
    var d2 = parseDate_(v);
    if (d2) return d2.getFullYear() + '-' + ('0' + (d2.getMonth() + 1)).slice(-2);
  }
  if (iDate !== undefined && iDate !== -1) return monthOf_(row[iDate]);
  return '';
}
function num_(v) {
  var s = String(v).trim();
  if (!s) return 0;
  // פורמט חשבונאי: (1,234) = שלילי. וגם מינוס רגיל/יוניקוד/מודגש
  var neg = /^\(.*\)$/.test(s) || /[-−﹣－]/.test(s.replace(/\\-/g, '-'));
  var n = parseFloat(s.replace(/[^\d.]/g, ''));
  if (isNaN(n)) return 0;
  return neg ? -n : n;
}
// נרמול כותרות: מסיר גרשיים/גרש בכל הצורות (״ " ' ׳) כדי ש"סה"כ" ו"סה״כ" יתאימו
function normHdr_(s) {
  return String(s).replace(/["״'׳]/g, '').replace(/\s+/g, ' ').trim();
}
function monthOf_(dstr) {
  var d = parseDate_(dstr);
  if (!d) return '';
  return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2);
}
function parseDate_(s) {
  s = String(s).trim();
  var m = s.match(/^(\d{1,2})[\/.](\d{1,2})[\/.](\d{4})/);
  if (m) return new Date(+m[3], +m[2] - 1, +m[1]);
  var m2 = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (m2) return new Date(+m2[1], +m2[2] - 1, +m2[3]);
  return null;
}
function currentMonth_() {
  var d = new Date();
  return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2);
}
