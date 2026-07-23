// ============================================================
// גאיהלנד — סנכרון הכנסות אוטומטי לגיליון שנתי 2026
// ============================================================

const SYNC_CONFIG = {
  WC_SITE: 'https://gayaland.co.il',
  WC_USER: 'gayaland',
  WC_PASS: PropertiesService.getScriptProperties().getProperty('WC_PASS') || '',
  NAYAX_FROM: 'retailPOS@nayax.com',
  KW_RISHON: 'ראשון',
  KW_YAVNE: 'יבנה',
  NOTIFY_EMAIL: 'or.sasson@gmail.com',
};

// ── נקודות כניסה ──────────────────────────────────────

function syncYesterday() {
  const d = new Date();
  d.setDate(d.getDate() - 1);
  syncDate(d);
}

function syncToday() {
  syncDate(new Date());
}

function testSpecificDate() {
  syncDate(new Date('2026-07-01'));
}

// ── סנכרון לתאריך ─────────────────────────────────────

function syncDate(date) {
  const dateStr  = Utilities.formatDate(date, 'Asia/Jerusalem', 'yyyy-MM-dd');
  const dayLabel = Utilities.formatDate(date, 'Asia/Jerusalem', 'd/M/yyyy');
  const log = ['\n══ סנכרון ' + dayLabel + ' ══'];

  try {
    // 1. WooCommerce
    log.push('[1] WooCommerce...');
    const wc = fetchWC(dateStr);
    log.push('  ✓ ראשון: ₪' + wc.rishon + ' | יבנה: ₪' + wc.yavne + ' (' + wc.count + ' הזמנות)');

    // 2. Nayax
    log.push('[2] Nayax CSV...');
    let nayax = fetchNayax(dateStr);
    if (!nayax.rishon && !nayax.yavne) {
      log.push('  ⚠️ לא נמצא בתאריך, מנסה +1 יום...');
      const nextD = new Date(date);
      nextD.setDate(nextD.getDate() + 1);
      nayax = fetchNayax(Utilities.formatDate(nextD, 'Asia/Jerusalem', 'yyyy-MM-dd'));
    }
    log.push('  ✓ ראשון: כניסה ₪' + (nayax.rishon ? nayax.rishon.entrance : 0) + ' | קפיטריה ₪' + (nayax.rishon ? nayax.rishon.cafe : 0) + ' | כרטיסים ' + (nayax.rishon ? nayax.rishon.tickets : 0));
    log.push('  ✓ יבנה:  כניסה ₪' + (nayax.yavne ? nayax.yavne.entrance : 0) + ' | קפיטריה ₪' + (nayax.yavne ? nayax.yavne.cafe : 0) + ' | כרטיסים ' + (nayax.yavne ? nayax.yavne.tickets : 0));

    // 3. ערכים
    const kartR = (wc.ticketsR || 0) + (nayax.rishon ? nayax.rishon.tickets : 0);
    const kartY = (wc.ticketsY || 0) + (nayax.yavne ? nayax.yavne.tickets : 0);

    const vals = {
      atarR: wc.rishon,
      kafR:  nayax.rishon ? nayax.rishon.cafe     : 0,
      snifR: nayax.rishon ? nayax.rishon.entrance : 0,
      kartR: kartR || '',
      atarY: wc.yavne,
      kafY:  nayax.yavne ? nayax.yavne.cafe     : 0,
      snifY: nayax.yavne ? nayax.yavne.entrance : 0,
      kartY: kartY || '',
      bday:  0,
    };

    const totalR = vals.atarR + vals.kafR + vals.snifR;
    const totalY = vals.atarY + vals.kafY + vals.snifY;
    log.push('  סה"כ: ₪' + (totalR + totalY) + ' (ראשון ₪' + totalR + ' | יבנה ₪' + totalY + ')');

    // 4. כתיבה
    log.push('[3] כותב לגיליון...');
    const result = writeToNewSheet(date, vals);
    log.push('  ✓ ' + result);

    const summary = '✅ ' + dayLabel + ': ₪' + (totalR + totalY).toLocaleString() + ' | ראשון ₪' + totalR + ' | יבנה ₪' + totalY + ' | כרטיסים: ' + ((vals.kartR || 0) + (vals.kartY || 0));
    log.push(summary);
    saveLog(log.join('\n'), 'ok');

    if (SYNC_CONFIG.NOTIFY_EMAIL) {
      MailApp.sendEmail(SYNC_CONFIG.NOTIFY_EMAIL, 'גאיהלנד — סנכרון ' + dayLabel, summary);
    }
    Logger.log(log.join('\n'));
    return summary;

  } catch(e) {
    const err = '❌ שגיאה ' + dayLabel + ': ' + e.message;
    log.push(err);
    saveLog(log.join('\n'), 'error');
    if (SYNC_CONFIG.NOTIFY_EMAIL) {
      MailApp.sendEmail(SYNC_CONFIG.NOTIFY_EMAIL, 'גאיהלנד — שגיאה ' + dayLabel, err + '\n\n' + e.stack);
    }
    Logger.log(err);
    throw e;
  }
}

// ── כתיבה לגיליון ─────────────────────────────────────

function writeToNewSheet(date, vals) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const sh = ss.getSheetByName('💰 הכנסות');
  if (!sh) throw new Error('לא נמצא טאב 💰 הכנסות');

  const dateTarget = Utilities.formatDate(date, 'Asia/Jerusalem', 'dd/MM/yyyy');
  const colA = sh.getRange('A4:A400').getValues();
  let targetRow = -1;

  for (let i = 0; i < colA.length; i++) {
    const cell = colA[i][0];
    if (!cell) continue;
    const cellStr = cell instanceof Date
      ? Utilities.formatDate(cell, 'Asia/Jerusalem', 'dd/MM/yyyy')
      : String(cell).trim();
    if (cellStr === dateTarget) {
      targetRow = i + 4;
      break;
    }
  }

  if (targetRow === -1) throw new Error('לא נמצאה שורה לתאריך ' + dateTarget);

  // D=אתר ראשון, E=קפיטריה ראשון, F=סניף ראשון, G=כרטיסים ראשון
  // H=אתר יבנה,  I=קפיטריה יבנה,  J=סניף יבנה,  K=כרטיסים יבנה
  // L=ימי הולדת
  sh.getRange(targetRow, 4, 1, 9).setValues([[
    vals.atarR, vals.kafR,  vals.snifR, vals.kartR,
    vals.atarY, vals.kafY,  vals.snifY, vals.kartY,
    vals.bday
  ]]);

  return 'שורה ' + targetRow + ' (' + dateTarget + ') עודכנה';
}

// ── WooCommerce ────────────────────────────────────────

function fetchWC(dateStr) {
  const auth = Utilities.base64Encode(SYNC_CONFIG.WC_USER + ':' + SYNC_CONFIG.WC_PASS);
  const url = SYNC_CONFIG.WC_SITE + '/wp-json/wc/v3/orders?after=' + dateStr + 'T00:00:00&before=' + dateStr + 'T23:59:59&per_page=100&status=completed,processing';
  const resp = UrlFetchApp.fetch(url, {
    headers: { Authorization: 'Basic ' + auth },
    muteHttpExceptions: true
  });
  if (resp.getResponseCode() !== 200)
    throw new Error('WC ' + resp.getResponseCode() + ': ' + resp.getContentText().substring(0, 200));
  const orders = JSON.parse(resp.getContentText());
  let rishon = 0, yavne = 0, ticketsR = 0, ticketsY = 0;
  orders.forEach(function(o) {
    const hasY = (o.line_items || []).some(function(i) { return (i.name || '').includes(SYNC_CONFIG.KW_YAVNE); });
    const total = parseFloat(o.total || 0);
    if (hasY) {
      yavne += total;
      (o.line_items || []).forEach(function(i) {
        if ((i.name || '').includes('כניסה') || (i.name || '').includes('כרטיס')) ticketsY += (i.quantity || 1);
      });
    } else {
      rishon += total;
      (o.line_items || []).forEach(function(i) {
        if ((i.name || '').includes('כניסה') || (i.name || '').includes('כרטיס')) ticketsR += (i.quantity || 1);
      });
    }
  });
  return { rishon: Math.round(rishon), yavne: Math.round(yavne), count: orders.length, ticketsR, ticketsY };
}

// ── Nayax מGmail ───────────────────────────────────────

function fetchNayax(dateStr) {
  var result = { rishon: null, yavne: null };
  var allParsed = [];

  var queries = [
    'from:' + SYNC_CONFIG.NAYAX_FROM + ' after:' + dateStr + ' before:' + nextDay(dateStr) + ' has:attachment',
    'from:' + SYNC_CONFIG.NAYAX_FROM + ' after:' + dateStr.replace(/-/g, '/') + ' before:' + nextDay(dateStr).replace(/-/g, '/') + ' has:attachment',
  ];

  for (var qi = 0; qi < queries.length; qi++) {
    var threads = GmailApp.search(queries[qi], 0, 10);
    if (threads.length === 0) continue;

    threads.forEach(function(thread) {
      thread.getMessages().forEach(function(msg) {
        msg.getAttachments().forEach(function(att) {
          var name = att.getName().toLowerCase();
          if (name.indexOf('.csv') === -1) return;
          var parsed = parseNayaxCSV(att.getDataAsString('UTF-8'));
          Logger.log('Nayax parsed: ' + JSON.stringify(parsed));
          allParsed.push(parsed);
        });
      });
    });

    if (allParsed.length > 0) break;
  }

  // ── הקצאה: ראשון = שם מכיל "ראשון", יבנה = כל השאר (כולל "גאיהלנד בעמ" ללא עיר) ──
  allParsed.forEach(function(parsed) {
    if (parsed.businessName.indexOf(SYNC_CONFIG.KW_RISHON) > -1) {
      result.rishon = parsed;
    } else {
      result.yavne = parsed;
    }
  });

  return result;
}

function parseNayaxCSV(text) {
  const lines = text.split('\n').map(function(l) { return l.trim(); }).filter(Boolean);
  let businessName = '', entrance = 0, cafe = 0, tickets = 0;

  lines.forEach(function(line) {
    const cols = line.replace(/^\uFEFF/, '').split(',').map(function(c) { return c.replace(/"/g, '').trim(); });
    if (!cols.length) return;
    const type = cols[0];
    const amt = parseFloat((cols[5] || '0').replace(/[^\d.]/g, '')) || 0;
    const qty = parseFloat((cols[4] || '0').replace(/[^\d.]/g, '')) || 0;
    if (type === 'שם עסק')    businessName = cols[1] || '';
    if (type === 'כניסה')     { entrance += amt; tickets += qty; }
    if (type === 'רשת כללית') { entrance += amt; tickets += qty; }
    if (type === 'קפיטריה')   cafe = amt;
  });

  return { businessName, entrance: Math.round(entrance), cafe: Math.round(cafe), tickets };
}

// ── עזרים ──────────────────────────────────────────────

function nextDay(dateStr) {
  const d = new Date(dateStr + 'T12:00:00');
  d.setDate(d.getDate() + 1);
  return Utilities.formatDate(d, 'Asia/Jerusalem', 'yyyy-MM-dd');
}

function saveLog(text, status) {
  try {
    const ss = SpreadsheetApp.getActiveSpreadsheet();
    let log = ss.getSheetByName('לוג-סנכרון');
    if (!log) {
      log = ss.insertSheet('לוג-סנכרון');
      log.getRange(1, 1, 1, 3).setValues([['תאריך', 'סטטוס', 'פרטים']]);
    }
    log.insertRowAfter(1);
    log.getRange(2, 1, 1, 3).setValues([[new Date(), status, text.substring(0, 2000)]]);
  } catch(e) { Logger.log('Log error: ' + e.message); }
}

function debugNayaxEmails() {
  const dateStr = '2026-07-01';
  const queries = [
    'from:' + SYNC_CONFIG.NAYAX_FROM + ' after:' + dateStr + ' before:' + nextDay(dateStr) + ' has:attachment',
    'from:' + SYNC_CONFIG.NAYAX_FROM + ' has:attachment',
  ];
  queries.forEach(function(q, i) {
    const threads = GmailApp.search(q, 0, 5);
    Logger.log('Q' + (i+1) + ': ' + threads.length + ' threads | ' + q);
    threads.forEach(function(t) {
      t.getMessages().forEach(function(msg) {
        Logger.log('  Subject: ' + msg.getSubject() + ' | Date: ' + msg.getDate());
        msg.getAttachments().forEach(function(att) {
          Logger.log('  File: ' + att.getName());
          if (att.getName().toLowerCase().indexOf('.csv') > -1) {
            Logger.log('  CSV:\n' + att.getDataAsString('UTF-8').split('\n').slice(0, 10).join('\n'));
          }
        });
      });
    });
  });
}
