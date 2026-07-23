// ============================================================
// גאיהלנד — מערכת תזרים שנתי 2026 v3
// ============================================================

const MONTHS_HE = ["ינואר","פברואר","מרץ","אפריל","מאי","יוני","יולי","אוגוסט","ספטמבר","אוקטובר","נובמבר","דצמבר"];
const DAYS_IN_MONTH = [31,28,31,30,31,30,31,31,30,31,30,31];
const DAYS_HE = ["ראשון","שני","שלישי","רביעי","חמישי","שישי","שבת"];
const VAT = 0.18;
const MONTHLY_TARGET = 100000;
const MID_TARGET = 80000;
const LOW_TARGET = 60000;

const BUDGET = [
  ["שכירות",         "קבוע",   12700, 29500, "כן",     "קבוע"],
  ["ארנונה",         "קבוע",    2900,  2000, "כן",     "קבוע"],
  ["חשמל",           "קבוע",     400,   600, "כן",     "קבוע"],
  ["תקשורת",         "קבוע",     500,   450, "כן",     "קבוע"],
  ["ביטוח",          "קבוע",     800,   800, "כן",     "קבוע"],
  ["הנהלת חשבונות",  "קבוע",    2400,     0, "כן",     "קבוע"],
  ["בנקאיות",        "קבוע",     400,   400, "כן",     "קבוע"],
  ["ניקיון",         "קבוע",       0,     0, "כן",     "קבוע"],
  ["שכר עדי",        "קבוע",   16000,     0, "לא",     "קבוע"],
  ["משכורות",        "קבוע",   18000, 12000, "לא",     "קבוע"],
  ["הלוואה קרן",     "קבוע",       0,  8975, "לא",     "קבוע"],
  ["הלוואה ריבית",   "קבוע",       0,  4737, "לא",     "קבוע"],
  ["ציוד מחסן",      "משתנה",    250,   200, "כן",     "משתנה"],
  ["ציוד משחק",      "משתנה",      0,     0, "כן",     "משתנה"],
  ["הוצאות משרד",    "משתנה",    600,   300, "כן",     "משתנה"],
  ["פרסום",          "משתנה", 10000,     0, "כן",     "משתנה"],
  ["הפעלות וחוגים",  "משתנה",  5000,     0, "מעורב",  "משתנה"],
  ["ימי הולדת",      "משתנה",  1000,   600, "מעורב",  "משתנה"],
  ["קפיטריה",        "משתנה",  1500,  1500, "כן",     "משתנה"],
  ["החזרי מעמ",      "משתנה",     0,  2000, "לא",     "קבוע"],
  ["ייעוץ עסקי",     "משתנה",     0,     0, "כן",     "חד-פעמי"],
  ["בלתמ",           "משתנה",  1000,  1000, "מעורב",  "חד-פעמי"],
];

const C = {
  darkGreen:"#2D5A3D", green:"#4A7C59", lightGreen:"#E8F5E9",
  darkBlue:"#1A4A6B", blue:"#3D6B8A", lightBlue:"#E3F2FD",
  fixed:"#F3F8FF", variable:"#FFFEF0", total:"#E8F5E9",
  sat:"#FFF3E0", sun:"#FFF8E1", white:"#FFFFFF", stripe:"#F9F9F9",
  warning:"#FFCDD2", vatYes:"#E8F5E9", vatNo:"#FFF9C4", vatMixed:"#E3F2FD",
};

// ── Sync config ────────────────────────────────────────
const SYNC_CONFIG = {
  WC_SITE: 'https://gayaland.co.il',
  WC_USER: 'gayaland',
  WC_PASS: PropertiesService.getScriptProperties().getProperty('WC_PASS') || '',
  NAYAX_FROM: 'retailPOS@nayax.com',
  KW_RISHON: 'ראשון',
  KW_YAVNE: 'יבנה',
  NOTIFY_EMAIL: 'or.sasson@gmail.com',
};

// ============================================================
// BUILD FUNCTIONS
// ============================================================

function buildAll() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  ss.toast("בונה... כ-90 שניות", "גאיהלנד 2026", 120);
  const dashSheet   = getOrCreate(ss, "📊 דשבורד", 0);
  const budgetSheet = getOrCreate(ss, "📋 תקציב", 1);
  const logSheet    = getOrCreate(ss, "📝 יומן הוצאות", 2);
  const revSheet    = getOrCreate(ss, "💰 הכנסות", 3);
  buildBudgetSheet(budgetSheet);
  buildLogSheet(logSheet);
  buildRevenueSheet(revSheet);
  const monthSheets = [];
  for (let i = 0; i < 12; i++) {
    const name = `${String(i+1).padStart(2,'0')} ${MONTHS_HE[i]}`;
    const sh = getOrCreate(ss, name, 4 + i);
    monthSheets.push(sh);
    buildMonthSheet(sh, i, logSheet, revSheet);
  }
  buildDashboard(dashSheet, monthSheets);
  ["Sheet1","גיליון1"].forEach(n => {
    const s = ss.getSheetByName(n);
    if (s && ss.getSheets().length > 1) try { ss.deleteSheet(s); } catch(e) {}
  });
  ss.setActiveSheet(dashSheet);
  ss.toast("✅ נבנה בהצלחה!", "גאיהלנד 2026", 5);
}

function getOrCreate(ss, name, pos) {
  let sh = ss.getSheetByName(name);
  if (!sh) sh = ss.insertSheet(name, pos);
  sh.clear();
  sh.clearConditionalFormatRules();
  return sh;
}

function buildBudgetSheet(sh) {
  sh.setRightToLeft(true);
  title(sh, "A1", "K1", "📋 תקציב בסיסי — גאיהלנד 2026  |  ניתן לעריכה ישירה", C.darkGreen);
  sh.getRange("A2").setValue("💡 ניתן לערוך כל תא בעמודות תקציב, לשנות סכומים ולהוסיף שורות.");
  sh.getRange("A2:K2").merge().setBackground("#FFF9C4").setFontStyle("italic").setFontSize(9);
  const headers = ["סעיף","סיווג","תקציב ראשון\n(סה\"כ כולל)","מעמ ראשון","ללא מעמ ראשון","תקציב יבנה\n(סה\"כ כולל)","מעמ יבנה","ללא מעמ יבנה","סה\"כ משולב","מעמ משולב","כולל מעמ?"];
  sh.getRange(3,1,1,headers.length).setValues([headers]).setBackground(C.darkBlue).setFontColor("white").setFontWeight("bold").setHorizontalAlignment("center").setWrap(true);
  sh.setRowHeight(3,40);
  BUDGET.forEach(([name,type,r,y,vatStatus,classif],i) => {
    const row = i+4;
    sh.getRange(row,1).setValue(name); sh.getRange(row,2).setValue(classif);
    sh.getRange(row,3).setValue(r); sh.getRange(row,6).setValue(y); sh.getRange(row,11).setValue(vatStatus);
    sh.getRange(row,4).setFormula(`=IF(K${row}="כן",ROUND(C${row}*${VAT}/(1+${VAT}),0),0)`);
    sh.getRange(row,5).setFormula(`=IF(K${row}="כן",C${row}-D${row},C${row})`);
    sh.getRange(row,7).setFormula(`=IF(K${row}="כן",ROUND(F${row}*${VAT}/(1+${VAT}),0),0)`);
    sh.getRange(row,8).setFormula(`=IF(K${row}="כן",F${row}-G${row},F${row})`);
    sh.getRange(row,9).setFormula(`=C${row}+F${row}`);
    sh.getRange(row,10).setFormula(`=D${row}+G${row}`);
    sh.getRange(row,1,1,11).setBackground(type==="קבוע" ? C.fixed : C.variable);
    sh.getRange(row,11).setBackground(vatStatus==="כן" ? C.vatYes : vatStatus==="לא" ? C.vatNo : C.vatMixed);
  });
  const vatRule = SpreadsheetApp.newDataValidation().requireValueInList(["כן","לא","מעורב"],true).build();
  sh.getRange(4,11,BUDGET.length,1).setDataValidation(vatRule);
  const typeRule = SpreadsheetApp.newDataValidation().requireValueInList(["קבוע","משתנה","חד-פעמי"],true).build();
  sh.getRange(4,2,BUDGET.length,1).setDataValidation(typeRule);
  const tot = BUDGET.length+4;
  sh.getRange(tot,1).setValue("סה\"כ חודשי").setFontWeight("bold");
  [3,4,5,6,7,8,9,10].forEach(c => { const col=String.fromCharCode(64+c); sh.getRange(tot,c).setFormula(`=SUM(${col}4:${col}${tot-1})`); });
  sh.getRange(tot+1,1).setValue("סה\"כ שנתי (×12)").setFontWeight("bold");
  [3,4,5,6,7,8,9,10].forEach(c => { const col=String.fromCharCode(64+c); sh.getRange(tot+1,c).setFormula(`=${col}${tot}*12`); });
  sh.getRange(tot,1,2,11).setBackground(C.total).setFontWeight("bold");
  sh.getRange(4,3,BUDGET.length+2,8).setNumberFormat('#,##0₪');
  sh.setColumnWidth(1,150); sh.setColumnWidth(2,80);
  [3,4,5,6,7,8].forEach(c=>sh.setColumnWidth(c,120));
  sh.setColumnWidth(9,130); sh.setColumnWidth(10,110); sh.setColumnWidth(11,90);
  sh.setFrozenRows(3);
}

function buildLogSheet(sh) {
  sh.setRightToLeft(true);
  title(sh,"A1","L1","📝 יומן הוצאות — כל העסקאות 2026",C.darkBlue);
  sh.getRange("A2").setValue('💡 בעמודה "כולל מעמ?" — "כן"/"לא"/"אוטו".');
  sh.getRange("A2:L2").merge().setBackground("#FFF9C4").setFontStyle("italic").setFontSize(9);
  const headers=["תאריך","ספק / תיאור","סכום (כולל מעמ)","סניף","סעיף תקציב","שולם?","סיווג","כולל מעמ?","סכום ללא מעמ","מעמ","חודש","ח\"ח","הערות"];
  sh.getRange(3,1,1,headers.length).setValues([headers]).setBackground(C.darkBlue).setFontColor("white").setFontWeight("bold").setHorizontalAlignment("center");
  const cats=BUDGET.map(r=>r[0]);
  sh.getRange("D4:D3000").setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(["ראשון לציון","יבנה"],true).build());
  sh.getRange("E4:E3000").setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(cats,true).build());
  sh.getRange("F4:F3000").setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(["✅ שולם","⏳ ממתין","🔄 קבוע חודשי"],true).build());
  sh.getRange("G4:G3000").setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(["קבוע","משתנה","חד-פעמי"],true).build());
  sh.getRange("H4:H3000").setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(["אוטו","כן","לא"],true).build());
  sh.getRange("G4").setFormula(`=ARRAYFORMULA(IFERROR(IF(E4:E3000="","",VLOOKUP(E4:E3000,'📋 תקציב'!A:B,2,0)),"משתנה"))`);
  sh.getRange("K4").setFormula('=ARRAYFORMULA(IFERROR(IF(A4:A3000="","",TEXT(A4:A3000,"MM")),""))');
  sh.getRange("L4").setFormula('=ARRAYFORMULA(IFERROR(IF(A4:A3000="","","2026-"&TEXT(A4:A3000,"MM")),""))');
  sh.getRange("I4").setFormula(`=ARRAYFORMULA(IFERROR(IF(C4:C3000="","",IF(G4:G3000="לא",C4:C3000,IF(G4:G3000="כן",ROUND(C4:C3000/(1+${VAT}),2),IF(IFERROR(VLOOKUP(E4:E3000,'📋 תקציב'!A:L,12,0),"")="לא",C4:C3000,ROUND(C4:C3000/(1+${VAT}),2))))),""))`);
  sh.getRange("J4").setFormula(`=ARRAYFORMULA(IFERROR(IF(C4:C3000="","",IF(G4:G3000="לא",0,IF(G4:G3000="כן",C4:C3000-H4:H3000,IF(IFERROR(VLOOKUP(E4:E3000,'📋 תקציב'!A:L,12,0),"")="לא",0,C4:C3000-H4:H3000)))),""))`);
  sh.setColumnWidth(1,95); sh.setColumnWidth(2,200); sh.setColumnWidth(3,110);
  sh.setColumnWidth(4,120); sh.setColumnWidth(5,160); sh.setColumnWidth(6,110);
  sh.setColumnWidth(7,90); sh.setColumnWidth(8,80); sh.setColumnWidth(9,110);
  sh.setColumnWidth(10,90); sh.setColumnWidth(11,70); sh.setColumnWidth(12,90); sh.setColumnWidth(13,150);
  sh.getRange("A4:A3000").setNumberFormat("dd/mm/yyyy");
  sh.getRange("C4:C3000").setNumberFormat('#,##0.00₪');
  sh.getRange("I4:J3000").setNumberFormat('#,##0.00₪');
  sh.getRange("K4:L3000").setNumberFormat("@");
  const rules=[];
  rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextEqualTo("✅ שולם").setBackground("#C8E6C9").setRanges([sh.getRange("F4:F3000")]).build());
  rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextEqualTo("⏳ ממתין").setBackground("#FFF9C4").setRanges([sh.getRange("F4:F3000")]).build());
  rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextEqualTo("כן").setBackground(C.vatYes).setRanges([sh.getRange("H4:H3000")]).build());
  rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextEqualTo("לא").setBackground(C.vatNo).setRanges([sh.getRange("H4:H3000")]).build());
  rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextEqualTo("קבוע").setBackground(C.fixed).setRanges([sh.getRange("G4:G3000")]).build());
  rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextEqualTo("משתנה").setBackground(C.variable).setRanges([sh.getRange("G4:G3000")]).build());
  rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextEqualTo("חד-פעמי").setBackground("#FCE4EC").setRanges([sh.getRange("G4:G3000")]).build());
  sh.setConditionalFormatRules(rules);
  sh.setFrozenRows(3);
}

function buildRevenueSheet(sh) {
  sh.setRightToLeft(true);
  title(sh,"A1","R1","💰 הכנסות יומיות — 2026",C.darkGreen);
  ["D2","E2","F2","G2"].forEach(c=>{sh.getRange(c).setValue(c==="D2"?"← ראשון לציון":"").setBackground("#2D5A3D").setFontColor("white").setFontWeight("bold").setHorizontalAlignment("center");});
  ["H2","I2","J2","K2"].forEach(c=>{sh.getRange(c).setValue(c==="H2"?"← יבנה":"").setBackground("#1A4A6B").setFontColor("white").setFontWeight("bold").setHorizontalAlignment("center");});
  ["M2","N2","O2"].forEach(c=>{sh.getRange(c).setValue(c==="M2"?"← סה\"כ כולל מעמ":"").setBackground(C.green).setFontColor("white").setFontWeight("bold").setHorizontalAlignment("center");});
  ["P2","Q2","R2"].forEach(c=>{sh.getRange(c).setValue(c==="P2"?"← ללא מעמ (18%)":"").setBackground(C.blue).setFontColor("white").setFontWeight("bold").setHorizontalAlignment("center");});
  const headers=["תאריך","יום","חודש","אתר ראשון","קפיטריה ראשון","סניף ראשון","כרטיסים ראשון","אתר יבנה","קפיטריה יבנה","סניף יבנה","כרטיסים יבנה","ימי הולדת","סה\"כ ראשון","סה\"כ יבנה","סה\"כ יומי","ללא מעמ ראשון","ללא מעמ יבנה","ללא מעמ יומי","מול נק׳ איזון יומית"];
  hdr(sh,3,1,headers,C.darkGreen);
  sh.getRange("T3").setValue("📊 סטטיסטיקות").setBackground(C.darkBlue).setFontColor("white").setFontWeight("bold");
  sh.getRange("T3:W3").merge().setHorizontalAlignment("center");
  let row=4;
  for (let m=0;m<12;m++) {
    for (let d=1;d<=DAYS_IN_MONTH[m];d++) {
      const date=new Date(2026,m,d); const dow=date.getDay();
      sh.getRange(row,1).setValue(new Date(2026,m,d)).setNumberFormat("dd/mm/yyyy");
      sh.getRange(row,2).setValue(DAYS_HE[dow]); sh.getRange(row,3).setValue(m+1);
      sh.getRange(row,13).setFormula(`=IF(SUM(D${row}:F${row})=0,"",SUM(D${row}:F${row}))`);
      sh.getRange(row,14).setFormula(`=IF(SUM(H${row}:J${row})=0,"",SUM(H${row}:J${row}))`);
      sh.getRange(row,15).setFormula(`=IF(SUM(D${row}:L${row})=0,"",SUM(D${row}:L${row}))`);
      sh.getRange(row,16).setFormula(`=IFERROR(ROUND(M${row}/(1+${VAT}),0),"")`);
      sh.getRange(row,17).setFormula(`=IFERROR(ROUND(N${row}/(1+${VAT}),0),"")`);
      sh.getRange(row,18).setFormula(`=IFERROR(ROUND(O${row}/(1+${VAT}),0),"")`);
      // Daily breakeven status: compare combined daily revenue (col O) to daily breakeven
      // (budget tab row 36: E36=ראשון, H36=יבנה → combined = E36+H36). 90-100% = "קרוב".
      sh.getRange(row,19).setFormula(`=IF(O${row}="","",IF(O${row}=0,"",LET(be,'📋 תקציב'!$E$36+'📋 תקציב'!$H$36,IF(be<=0,"",IF(O${row}>=be,"🟢 עברנו",IF(O${row}>=be*0.9,"🟡 קרוב","🔴 מתחת"))))))`);
      const bg=dow===6?C.sat:dow===0?C.sun:(row%2===0?C.stripe:C.white);
      sh.getRange(row,1,1,19).setBackground(bg); row++;
    }
    const firstRow=row-DAYS_IN_MONTH[m]; const lastRow=row-1; const sumRow=row;
    sh.getRange(sumRow,1).setValue(`סה"כ ${MONTHS_HE[m]}`).setFontWeight("bold");
    [4,5,6,8,9,10,12,13,14,15,16,17,18].forEach(c=>{const col=String.fromCharCode(64+c);sh.getRange(sumRow,c).setFormula(`=SUM(${col}${firstRow}:${col}${lastRow})`);});
    sh.getRange(sumRow,7).setFormula(`=SUM(G${firstRow}:G${lastRow})`);
    sh.getRange(sumRow,11).setFormula(`=SUM(K${firstRow}:K${lastRow})`);
    sh.getRange(sumRow,1,1,19).setBackground(C.lightGreen).setFontWeight("bold");
    sh.getRange(firstRow,20).setValue("יעד חודשי"); sh.getRange(firstRow,21).setValue(MONTHLY_TARGET).setNumberFormat('#,##0₪');
    sh.getRange(firstRow+1,20).setValue("הכנסות נוכחי"); sh.getRange(firstRow+1,21).setFormula(`=SUM(O${firstRow}:O${lastRow})`).setNumberFormat('#,##0₪');
    sh.getRange(firstRow+2,20).setValue("יתרה ליעד"); sh.getRange(firstRow+2,21).setFormula(`=U${firstRow}-U${firstRow+1}`).setNumberFormat('#,##0₪');
    sh.getRange(firstRow+3,20).setValue("ממוצע יומי"); sh.getRange(firstRow+3,21).setFormula(`=IFERROR(U${firstRow+1}/COUNTIF(O${firstRow}:O${lastRow},">"&0),0)`).setNumberFormat('#,##0₪');
    sh.getRange(firstRow+4,20).setValue("ימים עם נתון"); sh.getRange(firstRow+4,21).setFormula(`=COUNTIF(O${firstRow}:O${lastRow},">"&0)`);
    sh.getRange(firstRow+5,20).setValue("ימים <3300"); sh.getRange(firstRow+5,21).setFormula(`=COUNTIFS(O${firstRow}:O${lastRow},">"&0,O${firstRow}:O${lastRow},"<"&3300)`);
    sh.getRange(firstRow+6,20).setValue("ימים 3300-4000"); sh.getRange(firstRow+6,21).setFormula(`=COUNTIFS(O${firstRow}:O${lastRow},">="&3300,O${firstRow}:O${lastRow},"<="&4000)`);
    sh.getRange(firstRow+7,20).setValue("ימים >4000"); sh.getRange(firstRow+7,21).setFormula(`=COUNTIF(O${firstRow}:O${lastRow},">"&4000)`);
    sh.getRange(firstRow+8,20).setValue("ימים שעברו איזון"); sh.getRange(firstRow+8,21).setFormula(`=COUNTIF(S${firstRow}:S${lastRow},"🟢*")`);
    sh.getRange(firstRow,20,9,1).setFontWeight("bold").setBackground("#F5F5F5");
    row++;
  }
  sh.getRange(4,4,row,12).setNumberFormat('#,##0₪');
  sh.getRange(4,13,row,6).setNumberFormat('#,##0₪');
  sh.getRange(4,7,row,1).setNumberFormat('#,##0');
  sh.getRange(4,11,row,1).setNumberFormat('#,##0');
  sh.setColumnWidth(1,100); sh.setColumnWidth(2,65); sh.setColumnWidth(3,55);
  [4,5,6,8,9,10].forEach(c=>sh.setColumnWidth(c,105));
  sh.setColumnWidth(7,95); sh.setColumnWidth(11,95); sh.setColumnWidth(12,100);
  [13,14,15].forEach(c=>sh.setColumnWidth(c,105));
  [16,17,18].forEach(c=>sh.setColumnWidth(c,110));
  sh.setColumnWidth(19,150);
  sh.setColumnWidth(20,150); sh.setColumnWidth(21,110);
  sh.setFrozenRows(3);
}

function buildMonthSheet(sh,mIdx,logSheet,revSheet) {
  sh.setRightToLeft(true);
  const month=MONTHS_HE[mIdx]; const mNum=mIdx+1; const mStr=String(mNum).padStart(2,"0");
  const logName=logSheet.getName(); const revName=revSheet.getName(); const budgetName="📋 תקציב";
  title(sh,"A1","J1",`גאיהלנד — ${month} 2026`,C.darkGreen);
  sh.getRange("A3").setValue("💰 הכנסות חודשיות");
  sh.getRange("A3:J3").merge().setBackground(C.green).setFontColor("white").setFontWeight("bold").setHorizontalAlignment("center");
  hdr(sh,4,1,["","אתר ראשון","קפיטריה ראשון","סניף ראשון","אתר יבנה","קפיטריה יבנה","סניף יבנה","ימי הולדת","כרטיסים ראשון","כרטיסים יבנה","סה\"כ ראשון","סה\"כ יבנה","סה\"כ משולב","ללא מעמ"],C.darkGreen);
  sh.getRange("B5").setFormula(`=SUMIF('${revName}'!C:C,${mNum},'${revName}'!D:D)`);
  sh.getRange("C5").setFormula(`=SUMIF('${revName}'!C:C,${mNum},'${revName}'!E:E)`);
  sh.getRange("D5").setFormula(`=SUMIF('${revName}'!C:C,${mNum},'${revName}'!F:F)`);
  sh.getRange("E5").setFormula(`=SUMIF('${revName}'!C:C,${mNum},'${revName}'!H:H)`);
  sh.getRange("F5").setFormula(`=SUMIF('${revName}'!C:C,${mNum},'${revName}'!I:I)`);
  sh.getRange("G5").setFormula(`=SUMIF('${revName}'!C:C,${mNum},'${revName}'!J:J)`);
  sh.getRange("H5").setFormula(`=SUMIF('${revName}'!C:C,${mNum},'${revName}'!L:L)`);
  sh.getRange("I5").setFormula(`=SUMIF('${revName}'!C:C,${mNum},'${revName}'!G:G)`);
  sh.getRange("J5").setFormula(`=SUMIF('${revName}'!C:C,${mNum},'${revName}'!K:K)`);
  sh.getRange("K5").setFormula(`=SUMIF('${revName}'!C:C,${mNum},'${revName}'!M:M)`);
  sh.getRange("L5").setFormula(`=SUMIF('${revName}'!C:C,${mNum},'${revName}'!N:N)`);
  sh.getRange("M5").setFormula(`=K5+L5+H5`);
  sh.getRange("N5").setFormula(`=SUMIF('${revName}'!C:C,${mNum},'${revName}'!R:R)`);
  sh.getRange("A5").setValue("סה\"כ חודש");
  sh.getRange("A5:J5").setBackground(C.lightGreen).setFontWeight("bold");
  sh.getRange("B5:J5").setNumberFormat('#,##0₪');
  sh.getRange("A6").setValue("% מיעד עליון (100K)"); sh.getRange("J6").setFormula(`=IFERROR(M5/${MONTHLY_TARGET},0)`).setNumberFormat("0%");
  sh.getRange("A7").setValue("% מיעד ממוצע (80K)"); sh.getRange("J7").setFormula(`=IFERROR(M5/${MID_TARGET},0)`).setNumberFormat("0%");
  // Expense/breakeven block: delegate to the single source of truth (rebuildMonthExpenses),
  // which is the SAME code path used by syncBudgetToMonths — no structural drift.
  const expRow=9;
  sh.getRange(expRow,1).setValue("📋 הוצאות vs. תקציב");
  sh.getRange(expRow,1,1,10).merge().setBackground(C.blue).setFontColor("white").setFontSize(11).setFontWeight("bold").setHorizontalAlignment("center");
  hdr(sh,expRow+1,1,["סעיף","סוג","תקציב ראשון","בפועל ראשון\n(כולל מעמ)","מעמ ראשון","תקציב יבנה","בפועל יבנה\n(כולל מעמ)","מעמ יבנה","מאזן כולל","% ביצוע"],C.darkBlue);
  sh.setRowHeight(expRow+1,36);
  // Read budget items and build the expense table + breakeven analysis identically to the sync path
  const ss=sh.getParent();
  const budgetSheet=ss.getSheetByName(budgetName);
  const budgetData=budgetSheet.getRange('A4:K100').getValues();
  const budgetItems=[];
  for(const r of budgetData){
    const nm=(r[0]||'').toString().trim();
    if(!nm)continue;
    if(nm.includes('סה"כ')||nm.includes('סה״כ')||nm.includes('אגדת')||nm.includes('צבעים')||nm.includes('מקרא')||nm.startsWith('✅')||nm.startsWith('⚠️')||nm.startsWith('❌'))break;
    budgetItems.push(r);
  }
  rebuildMonthExpenses(sh, sh.getName(), mIdx, budgetItems, ss);
  sh.setColumnWidth(1,160); sh.setColumnWidth(2,80);
  for (let c=3;c<=10;c++) sh.setColumnWidth(c,115);
  sh.setFrozenRows(expRow+1);
}

function buildDashboard(sh,monthSheets) {
  sh.setRightToLeft(true);
  title(sh,"A1","K1","📊 גאיהלנד — דשבורד שנתי 2026",C.darkGreen);
  sh.getRange("A3:K3").setBackground(C.lightGreen);
  sh.getRange("A3").setValue("🎯 יעד עליון"); sh.getRange("B3").setValue(MONTHLY_TARGET).setNumberFormat('#,##0₪');
  sh.getRange("D3").setValue("📊 יעד ממוצע"); sh.getRange("E3").setValue(MID_TARGET).setNumberFormat('#,##0₪');
  sh.getRange("G3").setValue("📉 יעד נמוך"); sh.getRange("H3").setValue(LOW_TARGET).setNumberFormat('#,##0₪');
  sh.getRange("A3:K3").setFontWeight("bold");
  const tStart=5;
  hdr(sh,tStart,1,["חודש","הכנסות","הכנסות\n(ללא מע״מ)","הוצאות\n(כולל מעמ)","מעמ על\nהוצאות","הוצאות\n(ללא מעמ)","מאזן נטו","נקודת\nאיזון","% מיעד","פער מ\nנ. איזון","סטטוס"],C.darkBlue);
  sh.setRowHeight(tStart,40);
  monthSheets.forEach((msh,i) => {
    const row=tStart+1+i; const msn=msh.getName();
    sh.getRange(row,1).setValue(MONTHS_HE[i]);
    sh.getRange(row,2).setFormula(`='${msn}'!M5`);
    sh.getRange(row,3).setFormula(`='${msn}'!N5`);
    // Total expenses found dynamically — row position varies after budget sync
    sh.getRange(row,4).setFormula(`=IFERROR(SUMIF('${msn}'!A:A,"סה""כ הוצאות",'${msn}'!D:D)+SUMIF('${msn}'!A:A,"סה""כ הוצאות",'${msn}'!G:G),0)`);
    sh.getRange(row,5).setFormula(`=IFERROR(SUMIF('${msn}'!A:A,"סה""כ הוצאות",'${msn}'!E:E)+SUMIF('${msn}'!A:A,"סה""כ הוצאות",'${msn}'!H:H),0)`);
    sh.getRange(row,6).setFormula(`=D${row}-E${row}`);                 // הוצאות ללא מעמ
    sh.getRange(row,7).setFormula(`=C${row}-F${row}`);                 // מאזן נטו (ללא מעמ)
    // נקודת איזון לפי הגדרת הטאב החודשי: הוצאות קבועות בלבד (עם fallback לסך הוצאות ללא מעמ)
    const beLabel='🎯 נקודת איזון (הכנסות > קבועות)';
    sh.getRange(row,8).setFormula(`=IFERROR(IF(SUMIF('${msn}'!A:A,"${beLabel}",'${msn}'!C:C)>0,SUMIF('${msn}'!A:A,"${beLabel}",'${msn}'!C:C),F${row}),F${row})`);
    sh.getRange(row,9).setFormula(`=IFERROR(B${row}/${MONTHLY_TARGET},0)`).setNumberFormat("0%");
    sh.getRange(row,10).setFormula(`=B${row}-H${row}`);                // פער מנקודת איזון
    sh.getRange(row,11).setFormula(`=IF(B${row}=0,"—",IF(B${row}>=${MONTHLY_TARGET},"🟢 מעל יעד",IF(B${row}>=H${row},"🟡 מעל איזון",IF(B${row}>=H${row}*0.8,"🟠 קרוב לאיזון","🔴 מתחת לאיזון"))))`);
    sh.getRange(row,1,1,11).setBackground(i%2===0?C.white:C.stripe);
  });
  const totRow=tStart+13;
  sh.getRange(totRow,1).setValue("סה\"כ שנתי").setFontWeight("bold");
  [2,3,4,5,6,7,10].forEach(c2=>{sh.getRange(totRow,c2).setFormula(`=SUM(${String.fromCharCode(64+c2)}${tStart+1}:${String.fromCharCode(64+c2)}${tStart+12})`);});
  sh.getRange(totRow,9).setFormula(`=IFERROR(B${totRow}/(${MONTHLY_TARGET}*12),0)`).setNumberFormat("0%");
  sh.getRange(totRow,1,1,11).setBackground(C.total).setFontWeight("bold");
  sh.getRange(tStart+1,2,13,7).setNumberFormat('#,##0₪');
  sh.getRange(tStart+1,10,13,1).setNumberFormat('+#,##0₪;-#,##0₪;0₪');
  sh.setColumnWidth(1,90);
  [2,3,4,5,6,7,8,10].forEach(c2=>sh.setColumnWidth(c2,115));
  sh.setColumnWidth(9,75); sh.setColumnWidth(11,130);
  sh.setFrozenRows(tStart);

  // ── השוואת סניפים — ביצוע מול נקודת איזון ──
  const bStart=totRow+2;
  sh.getRange(bStart,1).setValue("📊 השוואת סניפים — ביצוע מול נקודת איזון");
  sh.getRange(bStart,1,1,8).merge().setBackground(C.darkGreen).setFontColor("white").setFontWeight("bold").setHorizontalAlignment("center").setFontSize(11);
  hdr(sh,bStart+1,1,["חודש","הכנסות ראשון","ראשון\n(ללא מע״מ)","הכנסות יבנה","יבנה\n(ללא מע״מ)","הפרש","% יבנה","סטטוס"],C.darkBlue);
  sh.setRowHeight(bStart+1,36);
  monthSheets.forEach((msh,i) => {
    const row=bStart+2+i; const msn=msh.getName();
    sh.getRange(row,1).setValue(MONTHS_HE[i]);
    sh.getRange(row,2).setFormula(`=IFERROR('${msn}'!K5,0)`);
    sh.getRange(row,3).setFormula(`=IFERROR(ROUND(B${row}/(1+${VAT}),0),0)`);
    sh.getRange(row,4).setFormula(`=IFERROR('${msn}'!L5,0)`);
    sh.getRange(row,5).setFormula(`=IFERROR(ROUND(D${row}/(1+${VAT}),0),0)`);
    sh.getRange(row,6).setFormula(`=D${row}-B${row}`);
    sh.getRange(row,7).setFormula(`=IFERROR(D${row}/(B${row}+D${row}),0)`).setNumberFormat("0%");
    sh.getRange(row,8).setFormula(`=IF(B${row}+D${row}=0,"—",IF(G${row}>=0.52,"📍 יבנה מוביל",IF(G${row}<=0.48,"📍 ראשון מוביל","⚖️ מאוזן")))`);
    sh.getRange(row,1,1,8).setBackground(i%2===0?C.white:C.stripe);
  });
  sh.getRange(bStart+2,2,12,5).setNumberFormat('#,##0₪');
  sh.getRange(bStart+2,6,12,1).setNumberFormat('+#,##0₪;-#,##0₪;0₪');
}

// בונה מחדש רק את טאב הדשבורד — בלי לגעת בנתונים (בניגוד ל-buildAll שמוחק הכל!)
function rebuildDashboardOnly() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const dashSheet = ss.getSheetByName('📊 דשבורד');
  if (!dashSheet) { SpreadsheetApp.getUi().alert('לא נמצא טאב דשבורד'); return; }
  dashSheet.clear();
  dashSheet.clearConditionalFormatRules();
  const monthSheets = [];
  for (let i = 0; i < 12; i++) {
    const name = `${String(i+1).padStart(2,'0')} ${MONTHS_HE[i]}`;
    const sh = ss.getSheetByName(name);
    if (sh) monthSheets.push(sh);
  }
  buildDashboard(dashSheet, monthSheets);
  ss.toast('✅ טאב הדשבורד נבנה מחדש', 'גאיהלנד', 5);
}

// ── Helper functions ───────────────────────────────────

function title(sh,from,to,text,bg) {
  sh.getRange(from).setValue(text);
  sh.getRange(`${from}:${to}`).merge().setFontSize(13).setFontWeight("bold").setBackground(bg).setFontColor("white").setHorizontalAlignment("center").setVerticalAlignment("middle");
  sh.setRowHeight(parseInt(from.replace(/\D/g,"")),36);
}

function hdr(sh,row,startCol,values,bg) {
  sh.getRange(row,startCol,1,values.length).setValues([values]).setBackground(bg).setFontColor("white").setFontWeight("bold").setHorizontalAlignment("center");
}

// ============================================================
// SYNC FUNCTIONS — Daily auto-sync from WooCommerce + Nayax
// ============================================================

function syncYesterday() {
  const d = new Date();
  d.setDate(d.getDate() - 1);
  syncDate(d);
}

function syncToday() {
  syncDate(new Date());
}

function testSpecificDate() {
  // Change date as needed
  syncDate(new Date('2026-07-02'));
}

function syncDate(date) {
  const dateStr  = Utilities.formatDate(date, 'Asia/Jerusalem', 'yyyy-MM-dd');
  const dayLabel = Utilities.formatDate(date, 'Asia/Jerusalem', 'd/M/yyyy');
  const log = ['\n══ סנכרון ' + dayLabel + ' ══'];

  try {
    log.push('[1] WooCommerce...');
    const wc = fetchWC(dateStr);
    log.push('  ✓ ראשון: ₪' + wc.rishon + ' | יבנה: ₪' + wc.yavne + ' (' + wc.count + ' הזמנות)');

    log.push('[2] Nayax CSV...');
    let nayax = fetchNayax(dateStr);
    if (!nayax.rishon && !nayax.yavne) {
      log.push('  ⚠️ לא נמצא בתאריך, מנסה +1 יום...');
      const nextD = new Date(date);
      nextD.setDate(nextD.getDate() + 1);
      nayax = fetchNayax(Utilities.formatDate(nextD, 'Asia/Jerusalem', 'yyyy-MM-dd'));
    }
    log.push('  ✓ ראשון: כניסה ₪' + (nayax.rishon ? nayax.rishon.entrance : 0) + ' | קפיטריה ₪' + (nayax.rishon ? nayax.rishon.cafe : 0));
    log.push('  ✓ יבנה:  כניסה ₪' + (nayax.yavne ? nayax.yavne.entrance : 0) + ' | קפיטריה ₪' + (nayax.yavne ? nayax.yavne.cafe : 0));

    const kartR = (wc.ticketsR || 0) + (nayax.rishon ? nayax.rishon.tickets : 0);
    const kartY = (wc.ticketsY || 0) + (nayax.yavne ? nayax.yavne.tickets  : 0);

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
    log.push('  סה"כ: ₪' + (totalR+totalY) + ' (ראשון ₪' + totalR + ' | יבנה ₪' + totalY + ')');

    log.push('[3] כותב לגיליון...');
    const result = writeToSheet(date, vals);
    log.push('  ✓ ' + result);

    const summary = '✅ ' + dayLabel + ': ₪' + (totalR+totalY).toLocaleString() + ' | ראשון ₪' + totalR + ' | יבנה ₪' + totalY;
    log.push(summary);
    saveLog(log.join('\n'), 'ok');
    if (SYNC_CONFIG.NOTIFY_EMAIL) MailApp.sendEmail(SYNC_CONFIG.NOTIFY_EMAIL, 'גאיהלנד — סנכרון ' + dayLabel, summary);
    Logger.log(log.join('\n'));
    return summary;

  } catch(e) {
    const err = '❌ שגיאה ' + dayLabel + ': ' + e.message;
    log.push(err);
    saveLog(log.join('\n'), 'error');
    if (SYNC_CONFIG.NOTIFY_EMAIL) MailApp.sendEmail(SYNC_CONFIG.NOTIFY_EMAIL, 'גאיהלנד — שגיאה ' + dayLabel, err + '\n\n' + e.stack);
    Logger.log(err);
    throw e;
  }
}

function writeToSheet(date, vals) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const sh = ss.getSheetByName('💰 הכנסות');
  if (!sh) throw new Error('לא נמצא טאב 💰 הכנסות');
  const dateTarget = Utilities.formatDate(date, 'Asia/Jerusalem', 'dd/MM/yyyy');
  const colA = sh.getRange('A4:A400').getValues();
  let targetRow = -1;
  for (let i = 0; i < colA.length; i++) {
    const cell = colA[i][0];
    if (!cell) continue;
    const cellStr = cell instanceof Date ? Utilities.formatDate(cell, 'Asia/Jerusalem', 'dd/MM/yyyy') : String(cell).trim();
    if (cellStr === dateTarget) { targetRow = i+4; break; }
  }
  if (targetRow === -1) throw new Error('לא נמצאה שורה לתאריך ' + dateTarget);
  sh.getRange(targetRow, 4, 1, 9).setValues([[vals.atarR, vals.kafR, vals.snifR, vals.kartR, vals.atarY, vals.kafY, vals.snifY, vals.kartY, vals.bday]]);
  return 'שורה ' + targetRow + ' (' + dateTarget + ') עודכנה';
}

function fetchWC(dateStr) {
  const auth = Utilities.base64Encode(SYNC_CONFIG.WC_USER + ':' + SYNC_CONFIG.WC_PASS);
  const url = SYNC_CONFIG.WC_SITE + '/wp-json/wc/v3/orders?after=' + dateStr + 'T00:00:00&before=' + dateStr + 'T23:59:59&per_page=100&status=completed,processing';
  const resp = UrlFetchApp.fetch(url, { headers: { Authorization: 'Basic ' + auth }, muteHttpExceptions: true });
  if (resp.getResponseCode() !== 200) throw new Error('WC ' + resp.getResponseCode());
  const orders = JSON.parse(resp.getContentText());
  let rishon=0, yavne=0, ticketsR=0, ticketsY=0;
  orders.forEach(function(o) {
    const hasY = (o.line_items||[]).some(function(i){return (i.name||'').includes(SYNC_CONFIG.KW_YAVNE);});
    const total = parseFloat(o.total||0);
    if (hasY) { yavne+=total; (o.line_items||[]).forEach(function(i){if((i.name||'').includes('כניסה')||(i.name||'').includes('כרטיס'))ticketsY+=(i.quantity||1);}); }
    else { rishon+=total; (o.line_items||[]).forEach(function(i){if((i.name||'').includes('כניסה')||(i.name||'').includes('כרטיס'))ticketsR+=(i.quantity||1);}); }
  });
  return { rishon: Math.round(rishon), yavne: Math.round(yavne), count: orders.length, ticketsR, ticketsY };
}

function fetchNayax(dateStr) {
  var result = { rishon: null, yavne: null };
  var allParsed = [];
  var queries = [
    'from:' + SYNC_CONFIG.NAYAX_FROM + ' after:' + dateStr + ' before:' + nextDay(dateStr) + ' has:attachment',
    'from:' + SYNC_CONFIG.NAYAX_FROM + ' after:' + dateStr.replace(/-/g,'/') + ' before:' + nextDay(dateStr).replace(/-/g,'/') + ' has:attachment',
  ];
  for (var qi=0; qi<queries.length; qi++) {
    var threads = GmailApp.search(queries[qi], 0, 10);
    if (threads.length===0) continue;
    threads.forEach(function(thread) {
      thread.getMessages().forEach(function(msg) {
        msg.getAttachments().forEach(function(att) {
          if (att.getName().toLowerCase().indexOf('.csv')===-1) return;
          var parsed = parseNayaxCSV(att.getDataAsString('UTF-8'));
          Logger.log('Nayax parsed: ' + JSON.stringify(parsed));
          allParsed.push(parsed);
        });
      });
    });
    if (allParsed.length > 0) break;
  }
  // ראשון = שם מכיל "ראשון" | יבנה = כל השאר (כולל "גאיהלנד בעמ" ללא עיר)
  allParsed.forEach(function(parsed) {
    if (parsed.businessName.indexOf(SYNC_CONFIG.KW_RISHON) > -1) result.rishon = parsed;
    else result.yavne = parsed;
  });
  return result;
}

function parseNayaxCSV(text) {
  const lines = text.split('\n').map(function(l){return l.trim();}).filter(Boolean);
  let businessName='', entrance=0, cafe=0, tickets=0;
  lines.forEach(function(line) {
    const cols = line.replace(/^\uFEFF/,'').split(',').map(function(c){return c.replace(/"/g,'').trim();});
    if (!cols.length) return;
    const type=cols[0];
    // Preserve minus sign for credits/refunds
    const amt=parseFloat((cols[5]||'0').replace(/[^\d.\-]/g,''))||0;
    const qty=parseFloat((cols[4]||'0').replace(/[^\d.\-]/g,''))||0;
    if (type==='שם עסק')    businessName=cols[1]||'';
    if (type==='כניסה')     { entrance+=amt; tickets+=(qty>0?qty:0); }
    if (type==='רשת כללית') { entrance+=amt; tickets+=(qty>0?qty:0); }
    if (type==='קפיטריה')   cafe+=amt;
    // Credit/refund: subtract from entrance (Nayax shows as separate זיכוי line)
    if (type==='זיכוי'||type==='החזר'||type==='ביטול') {
      entrance-=Math.abs(amt); // always subtract regardless of sign
      Logger.log('Nayax credit detected: type=' + type + ' amt=' + amt);
    }
  });
  return { businessName, entrance: Math.round(entrance), cafe: Math.round(cafe), tickets };
}

function nextDay(dateStr) {
  const d = new Date(dateStr + 'T12:00:00');
  d.setDate(d.getDate()+1);
  return Utilities.formatDate(d, 'Asia/Jerusalem', 'yyyy-MM-dd');
}

function saveLog(text, status) {
  try {
    const ss = SpreadsheetApp.getActiveSpreadsheet();
    let log = ss.getSheetByName('לוג-סנכרון');
    if (!log) { log=ss.insertSheet('לוג-סנכרון'); log.getRange(1,1,1,3).setValues([['תאריך','סטטוס','פרטים']]); }
    log.insertRowAfter(1);
    log.getRange(2,1,1,3).setValues([[new Date(), status, text.substring(0,2000)]]);
  } catch(e) { Logger.log('Log error: '+e.message); }
}

// ============================================================
// WC PROXY — Web App שמסתיר את סיסמת WooCommerce מהדשבורד
// פריסה: Deploy → New deployment → Web app →
//   Execute as: Me | Who has access: Anyone
// את כתובת ה-/exec מדביקים בדשבורד ב-WC_PROXY_URL
// ============================================================

function doGet(e) {
  try {
    const p = (e && e.parameter) || {};
    const auth = Utilities.base64Encode(SYNC_CONFIG.WC_USER + ':' + SYNC_CONFIG.WC_PASS);

    // ── Manual sync action: ?action=sync&date=YYYY-MM-DD ──
    if (p.action === 'sync') {
      const dateStr = p.date || Utilities.formatDate(new Date(), 'Asia/Jerusalem', 'yyyy-MM-dd');
      const d = new Date(dateStr + 'T12:00:00');
      const result = syncDate(d);
      return ContentService.createTextOutput(JSON.stringify({ ok: true, message: result }))
        .setMimeType(ContentService.MimeType.JSON);
    }
    const endpoint = p.endpoint || 'wc'; // 'wc' or 'amelia'
    let url, qs;

    if (endpoint === 'amelia') {
      // Amelia Bridge: /wp-json/gayaland/v1/amelia/{resource}
      const resource = p.resource || 'appointments';
      const allowed = ['after','before','limit','status'];
      qs = allowed.filter(k=>p[k]).map(k=>k+'='+encodeURIComponent(p[k])).join('&');
      url = SYNC_CONFIG.WC_SITE + '/wp-json/gayaland/v1/amelia/' + resource + (qs?'?'+qs:'');
    } else {
      // WooCommerce orders
      const allowed = ['after','before','per_page','page','status','orderby','order'];
      qs = allowed.filter(k=>p[k]).map(k=>k+'='+encodeURIComponent(p[k])).join('&');
      url = SYNC_CONFIG.WC_SITE + '/wp-json/wc/v3/orders?' + qs;
    }

    const resp = UrlFetchApp.fetch(url, {
      headers: { Authorization: 'Basic ' + auth },
      muteHttpExceptions: true
    });
    if (resp.getResponseCode() !== 200) {
      return ContentService.createTextOutput(JSON.stringify({ error: endpoint + ' ' + resp.getResponseCode() }))
        .setMimeType(ContentService.MimeType.JSON);
    }
    return ContentService.createTextOutput(resp.getContentText())
      .setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({ error: err.message }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}

// ============================================================
// גיבוי שבועי אוטומטי של הגיליון
// ============================================================

function weeklyBackup() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const file = DriveApp.getFileById(ss.getId());
  const stamp = Utilities.formatDate(new Date(), 'Asia/Jerusalem', 'yyyy-MM-dd');
  // Keep backups in a dedicated folder
  let folder;
  const it = DriveApp.getFoldersByName('גיבויים-גאיהלנד');
  folder = it.hasNext() ? it.next() : DriveApp.createFolder('גיבויים-גאיהלנד');
  file.makeCopy('גיבוי ' + ss.getName() + ' ' + stamp, folder);
  // Keep only the last 8 backups
  const backups = [];
  const bit = folder.getFiles();
  while (bit.hasNext()) backups.push(bit.next());
  backups.sort((a, b) => b.getDateCreated() - a.getDateCreated());
  backups.slice(8).forEach(f => f.setTrashed(true));
  Logger.log('Backup created: ' + stamp + ' (' + backups.length + ' total)');
}

// מריצים פעם אחת כדי להתקין את שני הטריגרים (סנכרון יומי + גיבוי שבועי)
function installTriggers() {
  // Remove existing triggers for these functions to avoid duplicates
  ScriptApp.getProjectTriggers().forEach(t => {
    if (['syncYesterday', 'weeklyBackup'].includes(t.getHandlerFunction())) {
      ScriptApp.deleteTrigger(t);
    }
  });
  // Daily sync at 07:00
  ScriptApp.newTrigger('syncYesterday').timeBased().everyDays(1).atHour(1).create();
  // Weekly backup on Sunday at 05:00
  ScriptApp.newTrigger('weeklyBackup').timeBased().onWeekDay(ScriptApp.WeekDay.SUNDAY).atHour(5).create();
  Logger.log('Triggers installed: daily sync 07:00, weekly backup Sunday 05:00');
}

// ============================================================
// סנכרון תקציב לכל החודשים
// הפעלה: תפריט "🏠 גאיהלנד" → "סנכרן תקציב לחודשים"
// ============================================================

function onOpen() {
  SpreadsheetApp.getUi()
    .createMenu('🏠 גאיהלנד')
    .addItem('🔄 סנכרן תקציב לכל החודשים', 'syncBudgetToMonths')
    .addItem('📊 רענן נוסחאות חודשיות', 'refreshMonthFormulas')
    .addItem('🖥️ בנה מחדש טאב דשבורד בלבד', 'rebuildDashboardOnly')
    .addSeparator()
    .addItem('💸 הזן הוצאות קבועות לחודש...', 'fillFixedExpenses')
    .addItem('📅 הוסף עמודת נק׳ איזון יומית', 'addDailyBreakevenColumn')
    .addSeparator()
    .addItem('🌙 סנכרן הכנסות אתמול', 'syncYesterday')
    .addToUi();
}

// ═══ הוספה בטוחה של עמודת "מול נק׳ איזון יומית" בלבד — לא מוחק שום נתון ═══
function addDailyBreakevenColumn() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const ui = SpreadsheetApp.getUi();
  const sh = ss.getSheetByName('💰 הכנסות');
  if (!sh) { ui.alert('❌ לא נמצא טאב הכנסות'); return; }

  const resp = ui.alert('📅 הוספת עמודת נק׳ איזון יומית',
    'תתווסף עמודה S שמסמנת לכל יום אם עברנו את נקודת האיזון היומית (🟢/🟡/🔴).\nלא נמחק שום נתון קיים. להמשיך?',
    ui.ButtonSet.YES_NO);
  if (resp !== ui.Button.YES) return;

  ss.toast('מוסיף עמודה...', 'גאיהלנד', 30);

  // Header for column S (19)
  sh.getRange(3, 19).setValue('מול נק׳ איזון יומית')
    .setBackground('#2D5A3D').setFontColor('white').setFontWeight('bold')
    .setHorizontalAlignment('center').setWrap(true);
  sh.setColumnWidth(19, 150);

  // Read column A (dates) to find every real day-row (skip blank + "סה"כ" summary rows)
  const lastRow = sh.getLastRow();
  const colA = sh.getRange(4, 1, lastRow - 3, 1).getValues();
  const colO = sh.getRange(4, 15, lastRow - 3, 1).getValues(); // daily total to know if row has data

  let added = 0;
  for (let i = 0; i < colA.length; i++) {
    const row = i + 4;
    const cell = colA[i][0];
    if (!cell) continue;
    // Skip summary rows ("סה"כ ...") and anything that isn't a date
    if (!(cell instanceof Date)) {
      const s = String(cell);
      if (s.includes('סה"כ') || s.includes('סה״כ') || s.includes('יעד') || s.includes('תאריך')) continue;
      // not a date and not a known label — skip to be safe
      continue;
    }
    // Add the status formula (same logic as buildRevenueSheet)
    sh.getRange(row, 19).setFormula(
      `=IF(O${row}="","",IF(O${row}=0,"",LET(be,'📋 תקציב'!$E$36+'📋 תקציב'!$H$36,IF(be<=0,"",IF(O${row}>=be,"🟢 עברנו",IF(O${row}>=be*0.9,"🟡 קרוב","🔴 מתחת"))))))`);
    added++;
  }

  ss.toast(`נוספה עמודה ל-${added} ימים`, 'גאיהלנד ✅', 5);
  ui.alert('✅ הושלם', `עמודת נק׳ איזון יומית נוספה ל-${added} ימים.\nלא נמחק שום נתון.`, ui.ButtonSet.OK);
}
function fillFixedExpenses() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const ui = SpreadsheetApp.getUi();

  // Ask which month
  const resp = ui.prompt(
    '💸 הזנת הוצאות קבועות',
    'לאיזה חודש? הזן מספר 1-12:',
    ui.ButtonSet.OK_CANCEL
  );
  if (resp.getSelectedButton() !== ui.Button.OK) return;
  const mNum = parseInt(resp.getResponseText().trim());
  if (isNaN(mNum) || mNum < 1 || mNum > 12) { ui.alert('❌ מספר חודש לא תקין (1-12)'); return; }
  const mStr = String(mNum).padStart(2, '0');

  const budgetSheet = ss.getSheetByName('📋 תקציב');
  const logSheet = ss.getSheetByName('📝 יומן הוצאות');
  if (!budgetSheet || !logSheet) { ui.alert('❌ לא נמצאו הטאבים הנדרשים'); return; }

  // 1. Collect fixed budget items (stop at summary/legend rows)
  const budgetData = budgetSheet.getRange('A4:F100').getValues();
  const fixed = [];
  for (const row of budgetData) {
    const name = (row[0] || '').toString().trim();
    if (!name) continue; // skip empty rows — don't stop (budget may have gaps)
    if (name.includes('סה"כ') || name.includes('סה״כ') || name.includes('אגדת') ||
        name.includes('צבעים') || name.includes('מקרא') ||
        name.startsWith('✅') || name.startsWith('⚠️') || name.startsWith('❌')) break;
    if ((row[1] || '').toString().trim() !== 'קבוע') continue;
    const rAmt = parseFloat(row[2]) || 0;
    const yAmt = parseFloat(row[5]) || 0;
    if (rAmt > 0) fixed.push({ name, branch: 'ראשון לציון', amount: rAmt });
    if (yAmt > 0) fixed.push({ name, branch: 'יבנה', amount: yAmt });
  }
  if (!fixed.length) { ui.alert('⚠️ לא נמצאו סעיפים קבועים עם סכום בתקציב'); return; }

  // 2. Dedup against existing log rows for that month (category+branch)
  const logData = logSheet.getRange('A4:E' + logSheet.getLastRow()).getValues();
  const existing = new Set();
  logData.forEach(row => {
    const cell = row[0];
    if (!cell) return;
    let mm = '';
    if (cell instanceof Date) mm = String(cell.getMonth() + 1).padStart(2, '0');
    else { const m1 = String(cell).match(/^\d{1,2}\/(\d{1,2})\/\d{4}/); if (m1) mm = String(parseInt(m1[1])).padStart(2, '0'); }
    if (mm !== mStr) return;
    existing.add(((row[4] || '').toString().trim()) + '|' + ((row[3] || '').toString().trim()));
  });
  const toAdd = fixed.filter(f => !existing.has(f.name + '|' + f.branch));
  const skipped = fixed.length - toAdd.length;
  if (!toAdd.length) { ui.alert('הכל כבר קיים ביומן לחודש ' + mStr + ' (' + skipped + ' סעיפים) — לא נוסף דבר'); return; }

  // 3. Append after the last used row. Blanks for ARRAYFORMULA cols (G,I,J,K,L).
  const startRow = Math.max(logSheet.getLastRow() + 1, 4);
  const dateVal = new Date(2026, mNum - 1, 1);
  const rows = toAdd.map(f => [dateVal, f.name, f.amount, f.branch, f.name, '🔄 קבוע חודשי', '', 'אוטו', '', '', '', '', '📄 הוזן אוטומטית — קבועות ' + mStr + '/2026']);
  logSheet.getRange(startRow, 1, rows.length, 13).setValues(rows);
  logSheet.getRange(startRow, 1, rows.length, 1).setNumberFormat('dd/mm/yyyy');

  ui.alert('✅ נוספו ' + toAdd.length + ' הוצאות קבועות לחודש ' + mStr +
           (skipped ? '\n(דולגו ' + skipped + ' שכבר קיימות)' : ''));
}

function syncBudgetToMonths() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const ui = SpreadsheetApp.getUi();

  const resp = ui.alert(
    '🔄 סנכרון תקציב',
    'פעולה זו תעדכן את הנוסחאות בכל 12 הטאבים החודשיים לפי הטאב "📋 תקציב".\n\nהאם להמשיך?',
    ui.ButtonSet.YES_NO
  );
  if (resp !== ui.Button.YES) return;

  const budgetSheet = ss.getSheetByName('📋 תקציב');
  if (!budgetSheet) {
    ui.alert('❌ לא נמצא טאב תקציב');
    return;
  }

  ss.toast('מסנכרן...', 'גאיהלנד', 60);

  const budgetData = budgetSheet.getRange('A4:K100').getValues();
  // Read only real budget items — stop at totals / legend / empty rows
  const rows = [];
  for (const r of budgetData) {
    const name = (r[0] || '').toString().trim();
    if (!name) continue; // skip empty rows — stop only at summary/legend
    // Stop at any summary or legend row
    if (name.includes('סה"כ') || name.includes('סה״כ') ||
        name.includes('אגדת') || name.includes('צבעים') ||
        name.includes('מקרא') || name.startsWith('✅') ||
        name.startsWith('⚠️') || name.startsWith('❌')) break;
    rows.push(r);
  }

  const log = [];
  const MONTHS_HE = ["ינואר","פברואר","מרץ","אפריל","מאי","יוני","יולי","אוגוסט","ספטמבר","אוקטובר","נובמבר","דצמבר"];

  let updatedSheets = 0;

  for (let mIdx = 0; mIdx < 12; mIdx++) {
    const sheetName = `${String(mIdx+1).padStart(2,'0')} ${MONTHS_HE[mIdx]}`;
    const sh = ss.getSheetByName(sheetName);
    if (!sh) {
      log.push(`⚠️ לא נמצא: ${sheetName}`);
      continue;
    }

    try {
      rebuildMonthExpenses(sh, sheetName, mIdx, rows, ss);
      log.push(`✅ ${sheetName}`);
      updatedSheets++;
    } catch(e) {
      log.push(`❌ ${sheetName}: ${e.message}`);
    }
  }

  ss.toast(`עודכנו ${updatedSheets} חודשים`, 'גאיהלנד ✅', 5);
  ui.alert('✅ סנכרון הושלם', log.join('\n'), ui.ButtonSet.OK);
}

function rebuildMonthExpenses(sh, sheetName, mIdx, budgetRows, ss) {
  const mNum = mIdx + 1;
  const mStr = String(mNum).padStart(2, '0');
  const logName = '📝 יומן הוצאות';
  const budgetName = '📋 תקציב';

  const colA = sh.getRange('A1:A80').getValues();
  let expHeaderRow = -1;
  for (let i = 0; i < colA.length; i++) {
    if (String(colA[i][0]).includes('הוצאות vs')) {
      expHeaderRow = i + 1;
      break;
    }
  }
  if (expHeaderRow === -1) throw new Error('לא נמצאה כותרת הוצאות');

  const expDataStart = expHeaderRow + 2;

  const existingRows = sh.getRange(`A${expDataStart}:A${expDataStart+50}`).getValues();
  let existingCount = 0;
  for (let i = 0; i < existingRows.length; i++) {
    const v = String(existingRows[i][0]);
    if (v.includes('סה"כ') || v.includes('סה״כ') || v.includes('נקודת') || v === '') break;
    existingCount++;
  }

  if (existingCount > 0) {
    // Clear both content AND formatting (old budget rows left stale colors/borders)
    const clearRange = sh.getRange(expDataStart, 1, existingCount + 35, 10);
    clearRange.clearContent();
    clearRange.clearFormat();
    clearRange.setBackground(null);
  }

  budgetRows.forEach((budgetRow, i) => {
    const name    = budgetRow[0];
    const classif = budgetRow[1];
    const budgetRowNum = i + 4;

    const r = expDataStart + i;

    sh.getRange(r, 1).setValue(name);
    sh.getRange(r, 2).setValue(classif);

    sh.getRange(r, 3).setFormula(`='${budgetName}'!C${budgetRowNum}`);
    sh.getRange(r, 6).setFormula(`='${budgetName}'!F${budgetRowNum}`);

    sh.getRange(r, 4).setFormula(
      `=IFERROR(SUMIFS('${logName}'!C:C,'${logName}'!E:E,"${name}",'${logName}'!D:D,"ראשון לציון",'${logName}'!K:K,"${mStr}"),0)`);
    sh.getRange(r, 5).setFormula(
      `=IFERROR(SUMIFS('${logName}'!J:J,'${logName}'!E:E,"${name}",'${logName}'!D:D,"ראשון לציון",'${logName}'!K:K,"${mStr}"),0)`);
    sh.getRange(r, 7).setFormula(
      `=IFERROR(SUMIFS('${logName}'!C:C,'${logName}'!E:E,"${name}",'${logName}'!D:D,"יבנה",'${logName}'!K:K,"${mStr}"),0)`);
    sh.getRange(r, 8).setFormula(
      `=IFERROR(SUMIFS('${logName}'!J:J,'${logName}'!E:E,"${name}",'${logName}'!D:D,"יבנה",'${logName}'!K:K,"${mStr}"),0)`);

    sh.getRange(r, 9).setFormula(`=(C${r}-D${r})+(F${r}-G${r})`);
    sh.getRange(r, 10).setFormula(`=IFERROR((D${r}+G${r})/(C${r}+F${r}),0)`);
    sh.getRange(r, 10).setNumberFormat('0%');

    const C = { fixed: '#F3F8FF', variable: '#FFFEF0', oneTime: '#FCE4EC' };
    const bg = classif === 'קבוע' ? C.fixed : classif === 'חד-פעמי' ? C.oneTime : C.variable;
    sh.getRange(r, 1, 1, 10).setBackground(bg);
  });

  const totRow = expDataStart + budgetRows.length;
  sh.getRange(totRow, 1).setValue('סה"כ הוצאות').setFontWeight('bold');
  ['C','D','E','F','G','H','I'].forEach((col, idx) => {
    sh.getRange(totRow, idx+3).setFormula(
      `=SUM(${col}${expDataStart}:${col}${totRow-1})`);
  });
  sh.getRange(totRow, 10).setFormula(
    `=IFERROR((D${totRow}+G${totRow})/(C${totRow}+F${totRow}),0)`);
  sh.getRange(totRow, 10).setNumberFormat('0%');
  sh.getRange(totRow, 1, 1, 10).setBackground('#E8F5E9').setFontWeight('bold');

  sh.getRange(expDataStart, 3, budgetRows.length+1, 7).setNumberFormat('#,##0₪');

  const beRow = totRow + 1;

  sh.getRange(beRow, 1).setValue('📊 ניתוח נקודת איזון');
  sh.getRange(beRow, 1, 1, 10).merge()
    .setBackground('#E8EAF6').setFontColor('#1A4A6B')
    .setFontSize(11).setFontWeight('bold').setHorizontalAlignment('center');

  sh.getRange(beRow+1, 1).setValue('🔒 הוצאות קבועות (ללא מעמ)');
  sh.getRange(beRow+1, 3).setFormula(
    `=IFERROR(SUMIFS('${logName}'!I:I,'${logName}'!K:K,"${mStr}",'${logName}'!G:G,"קבוע"),0)`);

  sh.getRange(beRow+2, 1).setValue('📦 הוצאות משתנות (ללא מעמ)');
  sh.getRange(beRow+2, 3).setFormula(
    `=IFERROR(SUMIFS('${logName}'!I:I,'${logName}'!K:K,"${mStr}",'${logName}'!G:G,"משתנה"),0)`);

  sh.getRange(beRow+3, 1).setValue('⚡ הוצאות חד-פעמיות (ללא מעמ)');
  sh.getRange(beRow+3, 3).setFormula(
    `=IFERROR(SUMIFS('${logName}'!I:I,'${logName}'!K:K,"${mStr}",'${logName}'!G:G,"חד-פעמי"),0)`);

  sh.getRange(beRow+4, 1).setValue('🎯 נקודת איזון (הכנסות > קבועות)');
  sh.getRange(beRow+4, 3).setFormula(`=C${beRow+1}`);
  sh.getRange(beRow+4, 1, 1, 10).setBackground('#EDE7F6').setFontWeight('bold');

  sh.getRange(beRow+5, 1).setValue('📈 פער מנקודת איזון');
  sh.getRange(beRow+5, 3).setFormula(`=M5-C${beRow+1}`);
  sh.getRange(beRow+5, 3).setNumberFormat('+#,##0₪;-#,##0₪;0₪').setFontWeight('bold');

  const balRow = beRow + 6;
  sh.getRange(balRow, 1).setValue('💰 מאזן נטו מלא');
  sh.getRange(balRow, 3).setFormula(`=M5-(C${beRow+1}+C${beRow+2}+C${beRow+3})`);
  sh.getRange(balRow, 3).setNumberFormat('+#,##0₪;-#,##0₪;0₪').setFontWeight('bold');
  sh.getRange(balRow, 1, 1, 10).setBackground('#E8F5E9').setFontWeight('bold');

  [beRow+1, beRow+2, beRow+3, beRow+4, beRow+5].forEach(r => {
    sh.getRange(r, 3).setNumberFormat('#,##0₪');
    sh.getRange(r, 1, 1, 10).setBackground(r === beRow+4 ? '#EDE7F6' : '#FAFAFA');
  });
}

function refreshMonthFormulas() {
  syncBudgetToMonths();
}
