/**
 * גאיהלנד — API להזמנת ימי הולדת · גרסה 3.0 (כולל אימות צוות)
 * ==================================================================
 * מאוחד ומתוקן:
 *   • מימוש שוברי אורחים מהאתר (voucher_check + redeem) — תואם הגיליון
 *   • אימות תשלום דרך Notification URL V2 של Nayax
 *   • תאריך שלא שולם משתחרר אוטומטית אחרי 24 שעות
 *   • חסימת סבבים בתוסף כשנקבע יום הולדת (הגשר)
 *
 * ⚠️ אפס מחיקות, אפס דריסות. הכול appendRow בלבד.
 * ==================================================================
 *
 * התקנה:
 *  1. הגיליון → Extensions → Apps Script → החלף את כל הקוד בזה → שמור
 *  2. Deploy → Manage deployments → ✏️ → Version: New version → Deploy
 */

const SHEET_ID   = '11xly8yUtbhrRog5_DqTqt2k_RDlHF6ro1vwIpTnvKZo';
const TAB_NAME   = '';
const BLOCK_TAB  = 'חסימות';
const PAY_TAB    = 'תשלומים';
const MODE       = 'day';
const HOLD_HOURS = 24;
const DEPOSIT    = 500;

const NEW_COLS = ['חבילה', 'סה״כ לאירוע', 'מקדמה', 'מקור הזמנה', 'קוד הזמנה'];

/* ── גשר לתוסף וורדפרס ── */
const ALERT_EMAIL = PropertiesService.getScriptProperties().getProperty('ALERT_EMAIL') || 'info@gayaland.co.il';
const BRIDGE_LOG_TAB = 'כשלי גשר';
const BOOKING_PLUGIN_URL = 'https://gayaland.co.il/wp-json/gayaland/v1/birthday-block';
const BOOKING_PLUGIN_KEY = PropertiesService.getScriptProperties().getProperty('BOOKING_PLUGIN_KEY') || '';

/* ── שוברים ── */
const VOUCHER_TAB  = 'שוברים';
const REDEEM_TAB   = 'מימושים';
const VOUCHER_DAYS = 30;

function ss_()   { return SpreadsheetApp.openById(SHEET_ID); }
function sheet_(){ const s = ss_(); return TAB_NAME ? s.getSheetByName(TAB_NAME) : s.getSheets()[0]; }

/* ══════════ קריאה ══════════ */
function doGet(e) {
  if (e && e.parameter && e.parameter.report === '1') return json_(report_());
  if (e && e.parameter && e.parameter.full   === '1') return json_(full_());
  return json_(availability_());
}

/* ══════════ כתיבה ══════════ */
function doPost(e) {
  // פעולות מהדשבורד / מהאתר
  try {
    const b = JSON.parse(e.postData.contents);
    if (b.action === 'block')         return blockAdd_(b);
    if (b.action === 'vouchers')      return vouchersIssue_(b);
    if (b.action === 'voucher_check') return json_(voucherCheck_(String(b.voucher || '').toUpperCase()));
    if (b.action === 'redeem')        return voucherRedeem_(b);
    if (b.action === 'staff_login')   return json_(staffLogin_(b.user, b.pass));
    if (b.action === 'staff_token')   return json_(staffCheckToken_(b.token));
  } catch (err) {}

  // Nayax מסמן את עצמו עם ?nayax=1
  const isNayax = (e.parameter && e.parameter.nayax === '1') || !isBooking_(e);
  return isNayax ? onPayment_(e) : onBooking_(e);
}

/* ══════════ דוח מלא לדשבורד ══════════ */
function full_() {
  const sh   = sheet_();
  const rows = sh.getDataRange().getValues();
  const head = rows.shift().map(String);
  const ix = function (needle) { return head.findIndex(h => h.indexOf(needle) > -1); };

  const i = {
    date: ix('תאריך'), time: ix('שעת'), branch: ix('סניף'), name: ix('שם ההורה'),
    phone: ix('טלפון'), child: ix('שם הילד'), kids: ix('מספר ילדים'), notes: ix('הערות'),
    addons: ix('תוספות'),
    tier: head.indexOf('חבילה'), total: head.indexOf('סה״כ לאירוע'),
    code: head.indexOf('קוד הזמנה'), src: head.indexOf('מקור הזמנה')
  };

  const paid = paidCodes_();
  const now  = Date.now();
  const g = function (r, k) { return i[k] > -1 ? r[i[k]] : ''; };

  const list = rows.filter(r => r[i.date]).map(function (r) {
    const code = String(g(r, 'code') || '');
    const t = r[0] instanceof Date ? r[0].getTime() : 0;
    let status;
    if (!code)            status = 'ישן';
    else if (paid[code])  status = 'שולם';
    else if (t && now - t < HOLD_HOURS * 3600e3) status = 'ממתין';
    else                  status = 'פג';

    return {
      code: code, status: status,
      created: t ? Utilities.formatDate(new Date(t), 'Asia/Jerusalem', 'yyyy-MM-dd HH:mm') : '',
      date: norm_(g(r, 'date')), time: String(g(r, 'time') || ''), branch: String(g(r, 'branch') || ''),
      name: String(g(r, 'name') || ''), phone: String(g(r, 'phone') || ''), child: String(g(r, 'child') || ''),
      kids: g(r, 'kids'), tier: String(g(r, 'tier') || ''), total: +g(r, 'total') || 0,
      addons: String(g(r, 'addons') || ''), notes: String(g(r, 'notes') || ''),
      src: String(g(r, 'src') || '')
    };
  });

  return { bookings: list, blocked: blocked_(), deposit: DEPOSIT, holdHours: HOLD_HOURS, vouchers: voucherStats_() };
}

function availability_() {
  const sh = sheet_();
  const rows = sh.getDataRange().getValues();
  const head = rows.shift().map(String);

  const i = {
    stamp : 0,
    date  : head.findIndex(h => h.indexOf('תאריך') > -1),
    time  : head.findIndex(h => h.indexOf('שעת')   > -1),
    branch: head.findIndex(h => h.indexOf('סניף')  > -1),
    code  : head.indexOf('קוד הזמנה')
  };

  const paid = paidCodes_();
  const now  = Date.now();

  const booked = rows.filter(r => r[i.date]).map(function (r) {
    const code    = i.code > -1 ? String(r[i.code] || '') : '';
    const isPaid  = code && paid[code];
    const created = r[i.stamp] instanceof Date ? r[i.stamp].getTime() : 0;
    const fresh   = created && (now - created) < HOLD_HOURS * 3600e3;
    return {
      date  : norm_(r[i.date]),
      branch: String(r[i.branch] || '').trim(),
      time  : String(r[i.time]   || '').trim(),
      holds : !!(isPaid || !code || fresh)
    };
  }).filter(b => b.date && b.holds);

  return { booked: booked, blocked: blocked_(), mode: MODE };
}

function paidCodes_() {
  const sh = ss_().getSheetByName(PAY_TAB);
  if (!sh || sh.getLastRow() < 2) return {};
  const out = {};
  sh.getRange(2, 1, sh.getLastRow() - 1, 4).getValues().forEach(function (r) {
    if (r[1] && String(r[3]).indexOf('אושר') > -1) out[String(r[1])] = true;
  });
  return out;
}

function blocked_() {
  const s = ss_();
  let sh = s.getSheetByName(BLOCK_TAB);
  if (!sh) {
    sh = s.insertSheet(BLOCK_TAB);
    sh.appendRow(['תאריך לחסימה (YYYY-MM-DD)', 'סניף (ריק = הכול)', 'סיבה']);
    return [];
  }
  return sh.getDataRange().getValues().slice(1)
    .filter(r => r[0])
    .map(r => ({ date: norm_(r[0]), branch: String(r[1] || '').trim() }));
}

function norm_(v) {
  if (v instanceof Date) return Utilities.formatDate(v, 'Asia/Jerusalem', 'yyyy-MM-dd');
  const m = String(v).match(/^(\d{1,2})[\/\.](\d{1,2})[\/\.](\d{4})/);
  if (m) return m[3] + '-' + p_(m[2]) + '-' + p_(m[1]);
  const i = String(v).match(/^(\d{4})-(\d{2})-(\d{2})/);
  return i ? i[0] : '';
}
const p_ = n => ('0' + n).slice(-2);

/* ══════════════════════════════════════════════════════════════════
   שוברי אורחים
   ══════════════════════════════════════════════════════════════════ */
function vouchersIssue_(b) {
  const sh = voucherTab_();
  const existing = sh.getDataRange().getValues().slice(1).filter(r => String(r[2]) === String(b.code));
  if (existing.length) {
    return json_({ ok: true, vouchers: existing.map(r => ({ code: r[1], expires: norm_(r[5]) })), reissued: true });
  }
  const n = Math.max(1, Math.min(60, +b.kids || 20));
  const exp = Utilities.formatDate(new Date(Date.now() + VOUCHER_DAYS * 864e5), 'Asia/Jerusalem', 'yyyy-MM-dd');
  const out = [];
  for (let i = 0; i < n; i++) {
    const vc = 'GY-' + Math.random().toString(36).slice(2, 7).toUpperCase();
    sh.appendRow([new Date(), vc, b.code || '', b.name || '', b.date || '', exp]);
    out.push({ code: vc, expires: exp });
  }
  return json_({ ok: true, vouchers: out });
}

/**
 * בדיקת תקפות שובר — בלי לממש. מחזיר אובייקט (לא json_).
 * מבנה גיליון: A:הונפק B:קוד שובר C:קוד הזמנה D:שם ההורה E:תאריך האירוע F:בתוקף עד
 */
function voucherCheck_(code) {
  code = String(code || '').trim().toUpperCase();
  if (!/^GY-[A-Z0-9]{4,8}$/.test(code)) return { ok: false, error: 'קוד שובר לא תקין' };

  const rows = voucherTab_().getDataRange().getValues().slice(1);
  const v = rows.find(r => String(r[1]).trim().toUpperCase() === code);
  if (!v) return { ok: false, error: 'לא נמצא שובר עם הקוד הזה' };

  const exp = norm_(v[5]);
  const today = Utilities.formatDate(new Date(), 'Asia/Jerusalem', 'yyyy-MM-dd');
  if (exp && today > exp) return { ok: false, error: 'השובר פג תוקף ב־' + exp };

  const used = redeemTab_().getDataRange().getValues().slice(1);
  if (used.some(r => String(r[1]).trim().toUpperCase() === code))
    return { ok: false, error: 'השובר כבר מומש' };

  return { ok: true, from: v[3], event: v[4], expires: exp };
}

/**
 * מימוש שובר — appendRow בלשונית "מימושים", עם מקור.
 * מקבל { voucher, branch, source, date }. מחזיר json_.
 */
function voucherRedeem_(b) {
  const code = String(b.voucher || '').trim().toUpperCase();
  const chk = voucherCheck_(code);
  if (!chk.ok) return json_(chk);

  const source = b.source === 'booking' ? 'הזמנה מראש באתר' : 'קבלה';
  redeemTab_().appendRow([
    new Date(), code, b.branch || '', chk.from || '', chk.event || '', source, b.date || ''
  ]);
  return json_({ ok: true, from: chk.from, event: chk.event, entries: 1 });
}

function voucherTab_() {
  const s = ss_();
  let sh = s.getSheetByName(VOUCHER_TAB);
  if (!sh) { sh = s.insertSheet(VOUCHER_TAB); sh.appendRow(['הונפק', 'קוד שובר', 'קוד הזמנה', 'שם ההורה', 'תאריך האירוע', 'בתוקף עד']); }
  return sh;
}
function redeemTab_() {
  const s = ss_();
  let sh = s.getSheetByName(REDEEM_TAB);
  if (!sh) { sh = s.insertSheet(REDEEM_TAB); sh.appendRow(['מומש', 'קוד שובר', 'סניף', 'מהאירוע של', 'תאריך האירוע', 'מקור', 'תאריך ביקור']); }
  return sh;
}
function voucherStats_() {
  const iss = Math.max(0, voucherTab_().getLastRow() - 1);
  const red = Math.max(0, redeemTab_().getLastRow() - 1);
  return { issued: iss, redeemed: red, rate: iss ? Math.round(red / iss * 100) : 0 };
}

/* חסימת תאריך מהדשבורד */
function blockAdd_(b) {
  const s = ss_();
  let sh = s.getSheetByName(BLOCK_TAB);
  if (!sh) { sh = s.insertSheet(BLOCK_TAB); sh.appendRow(['תאריך לחסימה (YYYY-MM-DD)', 'סניף (ריק = הכול)', 'סיבה']); }
  sh.appendRow([b.date, b.branch || '', b.reason || 'נחסם מהדשבורד']);
  return json_({ ok: true });
}

function isBooking_(e) {
  try { return !!JSON.parse(e.postData.contents).parent; } catch (err) { return false; }
}

/* ---------- הזמנה חדשה מהאתר ---------- */
function onBooking_(e) {
  const lock = LockService.getScriptLock();
  lock.waitLock(20000);
  try {
    const d  = JSON.parse(e.postData.contents);
    const sh = sheet_();

    if (isTaken_(d.date, d.branch, d.time)) return json_({ ok: false, error: 'taken' });

    let head = sh.getRange(1, 1, 1, sh.getLastColumn()).getValues()[0].map(String);
    NEW_COLS.forEach(function (c) {
      if (head.indexOf(c) === -1) { sh.getRange(1, sh.getLastColumn() + 1).setValue(c); head.push(c); }
    });

    const code = 'GL-' + Utilities.formatDate(new Date(), 'Asia/Jerusalem', 'yyMMdd-HHmmss');

    const map = {};
    map[head[0]]                = new Date();
    map['שם ההורה']              = d.parent;
    map['טלפון נייד']            = d.phone;
    map['כתובת מייל']            = d.email;
    map['שם הילד/ה']             = d.child;
    map['תאריך אירוע יום הולדת '] = d.date;
    map['שעת יום הולדת']          = d.time;
    map['סניף']                  = d.branch;
    map['תוספות']                = d.addons;
    map['במידה ובחרתם חבילות הפתעה נא לציין מספר ילדים'] = d.kids;
    map['הערות/בקשות/אלרגיות']    = d.notes;
    map['אני מאשר/ת את פרטי ההסכם'] = 'מאשר/ת';
    map['חבילה']                 = d.tier;
    map['סה״כ לאירוע']           = d.total;
    map['מקדמה']                 = d.deposit;
    map['מקור הזמנה']            = 'הזמנה עצמית מהאתר';
    map['קוד הזמנה']             = code;

    sh.appendRow(head.map(function (h) {
      const k = Object.keys(map).find(x => x.trim() === h.trim());
      return k ? map[k] : '';
    }));

    // Surface bridge status to the caller so a silent double-booking window can't open
    const blocked = blockBirthdayInPlugin(d.branch, d.date, d.time, code, 3);
    return json_({ ok: true, code: code, venue_blocked: blocked === true });
  } catch (err) {
    return json_({ ok: false, error: String(err) });
  } finally {
    lock.releaseLock();
  }
}

/* ---------- התראת תשלום מ-Nayax ---------- */
function onPayment_(e) {
  const lock = LockService.getScriptLock();
  lock.waitLock(20000);
  try {
    const raw = payload_(e);
    const match = matchPending_();

    payTab_().appendRow([
      new Date(),
      match ? match.code : '',
      match ? match.who  : '',
      status_(raw),
      pick_(raw, ['trans_amount','Amount','amount']) || DEPOSIT,
      pick_(raw, ['transaction_id','TransactionId','trans_id']),
      JSON.stringify(raw).slice(0, 4000)
    ]);

    return ContentService.createTextOutput('OK');
  } catch (err) {
    payTab_().appendRow([new Date(), '', '', 'שגיאה', '', '', String(err)]);
    return ContentService.createTextOutput('OK');
  } finally {
    lock.releaseLock();
  }
}

function payTab_() {
  const s = ss_();
  let sh = s.getSheetByName(PAY_TAB);
  if (!sh) { sh = s.insertSheet(PAY_TAB); sh.appendRow(['התקבל', 'קוד הזמנה', 'שם ההורה', 'סטטוס', 'סכום', 'מזהה עסקה', 'מידע גולמי']); }
  return sh;
}

function payload_(e) {
  if (e.postData && e.postData.contents) {
    try { return JSON.parse(e.postData.contents); } catch (err) {}
    const o = {};
    e.postData.contents.split('&').forEach(function (kv) {
      const q = kv.split('=');
      if (q[0]) o[decodeURIComponent(q[0])] = decodeURIComponent((q[1] || '').replace(/\+/g, ' '));
    });
    if (Object.keys(o).length) return o;
  }
  return e.parameter || {};
}

function pick_(o, keys) {
  for (let i = 0; i < keys.length; i++) if (o[keys[i]] !== undefined) return o[keys[i]];
  return '';
}

function status_(raw) {
  const s = String(pick_(raw, ['status','Status','reply','Reply','trans_status','ResultCode','result'])).toLowerCase();
  const ok = s === '' || s === '0' || s === '000' || /approv|success|ok|paid/.test(s);
  return ok ? 'אושר' : 'נדחה (' + s + ')';
}

function matchPending_() {
  const sh   = sheet_();
  const rows = sh.getDataRange().getValues();
  const head = rows.shift().map(String);
  const iCode = head.indexOf('קוד הזמנה');
  const iName = head.findIndex(h => h.indexOf('שם ההורה') > -1);
  if (iCode === -1) return null;

  const paid = paidCodes_();
  const now  = Date.now();
  let best = null;

  rows.forEach(function (r) {
    const code = String(r[iCode] || '');
    if (!code || paid[code]) return;
    const t = r[0] instanceof Date ? r[0].getTime() : 0;
    if (!t || now - t > 60 * 60e3) return;
    if (!best || t > best.t) best = { t: t, code: code, who: r[iName] };
  });
  return best;
}

function isTaken_(date, branch, time) {
  const a = availability_();
  if (a.blocked.some(b => b.date === date && (!b.branch || b.branch === branch))) return true;
  return a.booked.some(function (b) {
    if (b.date !== date || b.branch !== branch) return false;
    return MODE === 'day' ? true : b.time === time;
  });
}

/* ══════════ דוח מהיר ══════════ */
function report_() {
  const sh = sheet_();
  const rows = sh.getDataRange().getValues();
  const head = rows.shift().map(String);
  const iCode = head.indexOf('קוד הזמנה');
  const iName = head.findIndex(h => h.indexOf('שם ההורה') > -1);
  const iDate = head.findIndex(h => h.indexOf('תאריך')   > -1);
  if (iCode === -1) return { pending: [], paid: [] };

  const paid = paidCodes_();
  const out = { pending: [], paid: [] };
  rows.forEach(function (r) {
    const code = String(r[iCode] || '');
    if (!code) return;
    const item = { code: code, name: r[iName], date: norm_(r[iDate]) };
    (paid[code] ? out.paid : out.pending).push(item);
  });
  return out;
}

function json_(o) {
  return ContentService.createTextOutput(JSON.stringify(o)).setMimeType(ContentService.MimeType.JSON);
}

/* ══════════════════════════════════════════════════════════════════
   הגשר לתוסף — חסימת סבבים כשנקבע יום הולדת
   ══════════════════════════════════════════════════════════════════ */
function blockBirthdayInPlugin(branch, date, time, code, durationHours) {
  if (!BOOKING_PLUGIN_URL || !BOOKING_PLUGIN_KEY) return;
  const branchKey = branch === 'ראשון לציון' ? 'rishon'
                  : branch === 'יבנה'        ? 'yavne'
                  : String(branch).toLowerCase().replace(/\s/g, '_');
  try {
    const payload = JSON.stringify({ branch: branchKey, date: date, time: time || '',
      duration: durationHours || 3, reason: 'יום הולדת', code: code || '', action: 'block' });
    const opts = {
      method:  'post',
      payload: payload,
      headers: { 'Content-Type': 'application/json', 'X-GYL-KEY': BOOKING_PLUGIN_KEY },
      muteHttpExceptions: true
    };
    // Verify the venue was actually blocked. A silent failure here means the
    // birthday exists in Sheets but the venue stays open -> double booking.
    let res = UrlFetchApp.fetch(BOOKING_PLUGIN_URL, opts);
    if (res.getResponseCode() >= 300) {
      Utilities.sleep(1500);                       // one retry for transient errors
      res = UrlFetchApp.fetch(BOOKING_PLUGIN_URL, opts);
    }
    if (res.getResponseCode() >= 300) {
      bridgeAlert_(branchKey, date, code, res.getResponseCode(), res.getContentText());
      return false;
    }
    return true;
  } catch (err) {
    console.error('[bridge]', err);
    bridgeAlert_(branchKey, date, code, 0, String(err));
    return false;
  }
}

/* Bridge failure = the venue is NOT blocked. Must never fail silently. */
function bridgeAlert_(branch, date, code, status, body) {
  const msg = '\u26a0\ufe0f \u05d7\u05e1\u05d9\u05de\u05ea \u05d9\u05d5\u05dd \u05d4\u05d5\u05dc\u05d3\u05ea \u05dc\u05d0 \u05e0\u05e8\u05e9\u05de\u05d4 \u05d1\u05ea\u05d5\u05e1\u05e3!\n\n' +
    '\u05e1\u05e0\u05d9\u05e3: ' + branch + '\n\u05ea\u05d0\u05e8\u05d9\u05da: ' + date + '\n\u05e7\u05d5\u05d3: ' + code + '\n' +
    'HTTP: ' + status + '\n' + String(body).substring(0, 300) + '\n\n' +
    '\u05d4\u05de\u05ea\u05d7\u05dd \u05e2\u05d3\u05d9\u05d9\u05df \u05e4\u05ea\u05d5\u05d7 \u05dc\u05d4\u05d6\u05de\u05e0\u05d5\u05ea \u2014 \u05d9\u05e9 \u05dc\u05d7\u05e1\u05d5\u05dd \u05d9\u05d3\u05e0\u05d9\u05ea.';
  try {
    MailApp.sendEmail({ to: ALERT_EMAIL, subject: '\u05d7\u05e1\u05d9\u05de\u05ea \u05d9\u05d5\u05dd \u05d4\u05d5\u05dc\u05d3\u05ea \u05e0\u05db\u05e9\u05dc\u05d4', body: msg });
  } catch (e) { console.error('[bridgeAlert]', e); }
  try {
    ss_().getSheetByName(BRIDGE_LOG_TAB || 'כשלי גשר')
      .appendRow([new Date(), branch, date, code, status, String(body).substring(0, 500)]);
  } catch (e) {}
}

function unblockBirthdayInPlugin(branch, date, code) {
  if (!BOOKING_PLUGIN_KEY) return;
  const branchKey = branch === 'ראשון לציון' ? 'rishon' : 'yavne';
  try {
    UrlFetchApp.fetch(BOOKING_PLUGIN_URL, {
      method:  'post',
      payload: JSON.stringify({ branch: branchKey, date: date, code: code, reason: 'יום הולדת', action: 'unblock' }),
      headers: { 'Content-Type': 'application/json', 'X-GYL-KEY': BOOKING_PLUGIN_KEY },
      muteHttpExceptions: true
    });
  } catch (err) { console.error('[bridge]', err); }
}

/* ══════════════════════════════════════════════════════════════════
   אפסייל אוטומטי — 7 ימים לפני האירוע
   טריגר: Apps Script → ⏰ Triggers → sendUpsells → Time-driven → יומי 09:00
   ══════════════════════════════════════════════════════════════════ */
const UPSELL_TAB = 'אפסייל';

function sendUpsells() {
  const target = Utilities.formatDate(new Date(Date.now() + 7 * 864e5), 'Asia/Jerusalem', 'yyyy-MM-dd');
  const sent = sentCodes_();

  full_().bookings.forEach(function (b) {
    if (b.date !== target) return;
    if (b.status !== 'שולם' && b.status !== 'ישן') return;
    if (!b.code || sent[b.code]) return;

    const email = emailOf_(b.code);
    if (!email) return;

    const missing = [];
    const tier = b.tier || '';
    if (tier.indexOf('VIP') === -1 && tier.indexOf('החגיגה') === -1) missing.push('design');
    if (tier.indexOf('VIP') === -1) missing.push('bags');
    if (String(b.addons || '').indexOf('עוג') === -1)                missing.push('cake');
    if (!missing.length) return;

    try {
      MailApp.sendEmail({ to: email, subject: '🎈 עוד שבוע ליום ההולדת של ' + (b.child || 'הילד/ה') + '!',
        htmlBody: upsellHtml_(b, missing), name: 'גאיהלנד' });
      upsellTab_().appendRow([new Date(), b.code, b.name, email, missing.join(', ')]);
    } catch (err) {
      upsellTab_().appendRow([new Date(), b.code, b.name, email, 'שגיאה: ' + err]);
    }
  });
}

function upsellHtml_(b, missing) {
  const blocks = {
    design: '<li style="margin-bottom:10px"><b>חבילת עיצוב — 900 ₪</b><br>בלונים, שולחן מעוצב ופינת צילום בקונספט שתבחרו.</li>',
    bags:   '<li style="margin-bottom:10px"><b>שקיות הפתעה — 25 ₪ לילד</b><br>כל ילד הולך הביתה עם משהו.</li>',
    cake:   '<li style="margin-bottom:10px"><b>עוגה — מ־250 ₪</b><br>בלי לרוץ למאפייה בבוקר האירוע.</li>'
  };
  return '<div dir="rtl" style="font-family:Arial,sans-serif;font-size:15px;color:#3A3730;max-width:520px">' +
    '<p>היי ' + b.name + ',</p>' +
    '<p>עוד <b>שבוע בדיוק</b> חוגגים אצלנו — ' + b.date + ' בשעה ' + b.time + ', סניף ' + b.branch + '. הכול מוכן מצידנו 🎈</p>' +
    '<p>לפני שסוגרים סופית — רוב ההורים מוסיפים בשלב הזה את הדברים האלה:</p>' +
    '<ul style="padding-inline-start:18px">' + missing.map(m => blocks[m]).join('') + '</ul>' +
    '<p><b>וגם — כמה ילדים סופית מגיעים?</b></p>' +
    '<p style="margin:22px 0"><a href="https://wa.me/972547801818?text=' +
      encodeURIComponent('היי, לגבי יום ההולדת ב-' + b.date + ' (' + b.code + ')') +
      '" style="background:#7C8B5E;color:#fff;padding:13px 26px;border-radius:10px;text-decoration:none;font-weight:bold">להוספת תוספות בוואטסאפ</a></p>' +
    '<p style="color:#8A8375;font-size:13px">מתרגשים לקראתכם,<br>צוות גאיהלנד</p></div>';
}

function upsellTab_() {
  const s = ss_();
  let sh = s.getSheetByName(UPSELL_TAB);
  if (!sh) { sh = s.insertSheet(UPSELL_TAB); sh.appendRow(['נשלח', 'קוד הזמנה', 'שם', 'מייל', 'מה הוצע']); }
  return sh;
}
function sentCodes_() {
  const sh = ss_().getSheetByName(UPSELL_TAB);
  if (!sh || sh.getLastRow() < 2) return {};
  const out = {};
  sh.getRange(2, 2, sh.getLastRow() - 1, 1).getValues().forEach(r => { if (r[0]) out[String(r[0])] = true; });
  return out;
}
function emailOf_(code) {
  const sh = sheet_();
  const rows = sh.getDataRange().getValues();
  const head = rows.shift().map(String);
  const iC = head.indexOf('קוד הזמנה');
  const iE = head.findIndex(h => h.indexOf('מייל') > -1);
  if (iC === -1 || iE === -1) return '';
  const row = rows.find(r => String(r[iC]) === code);
  return row ? String(row[iE] || '').trim() : '';
}

/* ═══════════════════════════════════════════════════════════
   אימות צוות (משתמש + סיסמה) — משולב מ-gayaland-staff-auth
   ═══════════════════════════════════════════════════════════ */
const STAFF_TAB = 'צוות';

function staffTab_() {
  const ss = SpreadsheetApp.getActive();
  let sh = ss.getSheetByName(STAFF_TAB);
  if (!sh) {
    sh = ss.insertSheet(STAFF_TAB);
    sh.appendRow(['שם משתמש', 'סיסמה', 'שם העובד', 'הרשאות', 'פעיל']);
    sh.appendRow(['dana', '1234', 'דנה (לדוגמה)', 'events,arrivals,coupons,hr', 'כן']);
    sh.getRange(1, 1, 1, 5).setFontWeight('bold');
  }
  return sh;
}

/**
 * מאמת משתמש+סיסמה מול גיליון "צוות".
 * מחזיר { ok, name, perms:[...], token } או { ok:false, error }
 */
function staffLogin_(user, pass) {
  user = String(user || '').trim().toLowerCase();
  pass = String(pass || '').trim();
  if (!user || !pass) return { ok: false, error: 'חסר שם משתמש או סיסמה' };

  const rows = staffTab_().getDataRange().getValues().slice(1);
  for (let i = 0; i < rows.length; i++) {
    const r = rows[i];
    const u = String(r[0] || '').trim().toLowerCase();
    const p = String(r[1] || '').trim();
    const active = String(r[4] || '').trim();
    if (u !== user) continue;
    if (active !== 'כן' && active.toLowerCase() !== 'yes' && active !== '1')
      return { ok: false, error: 'המשתמש מושבת. פנו למנהל.' };
    if (p !== pass) return { ok: false, error: 'סיסמה שגויה' };

    const perms = String(r[3] || '')
      .split(/[,\s]+/).map(x => x.trim()).filter(Boolean);

    // טוקן פשוט ל-24 שעות: user|expiry|חתימה
    const exp = Date.now() + 24 * 3600 * 1000;
    const token = Utilities.base64EncodeWebSafe(user + '|' + exp + '|' + staffSig_(user, exp));

    return { ok: true, name: String(r[2] || user), perms: perms, token: token, exp: exp };
  }
  return { ok: false, error: 'משתמש לא נמצא' };
}

/** אימות טוקן קיים (לכניסה חוזרת באותו יום, בלי סיסמה) */
function staffCheckToken_(token) {
  try {
    const raw = Utilities.newBlob(Utilities.base64DecodeWebSafe(token)).getDataAsString();
    const parts = raw.split('|');
    const user = parts[0], exp = +parts[1], sig = parts[2];
    if (Date.now() > exp) return { ok: false, error: 'פג תוקף' };
    if (staffSig_(user, exp) !== sig) return { ok: false, error: 'טוקן לא תקין' };
    // שולפים מחדש את ההרשאות מהגיליון (אולי השתנו)
    const rows = staffTab_().getDataRange().getValues().slice(1);
    const row = rows.find(r => String(r[0] || '').trim().toLowerCase() === user);
    if (!row) return { ok: false, error: 'משתמש לא נמצא' };
    if (String(row[4] || '').trim() !== 'כן') return { ok: false, error: 'המשתמש מושבת' };
    const perms = String(row[3] || '').split(/[,\s]+/).map(x => x.trim()).filter(Boolean);
    return { ok: true, name: String(row[2] || user), perms: perms };
  } catch (e) {
    return { ok: false, error: 'טוקן פגום' };
  }
}

/** חתימה — מונעת זיוף טוקן. שנו את הסוד למחרוזת אקראית משלכם. */
function staffSig_(user, exp) {
  const SECRET = 'GYL-staff-secret-CHANGE-ME-7c3f9';
  const bytes = Utilities.computeHmacSha256Signature(user + '|' + exp, SECRET);
  return Utilities.base64EncodeWebSafe(bytes).slice(0, 16);
}

/**
 * ─────────────────────────────────────────────────────────
 * חיבור ל-doPost הקיים — הוסיפו בתוך ה-try, לפני ה-return:
 *
 *   if (b.action === 'staff_login')
 *     return json_(staffLogin_(b.user, b.pass));
 *   if (b.action === 'staff_token')
 *     return json_(staffCheckToken_(b.token));
 * ─────────────────────────────────────────────────────────
 */
