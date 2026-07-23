<?php
/**
 * ולידציה לתוסף gayaland-booking — חייב לעבור לפני כל גרסה
 * הרצה:  php validate.php gayaland-booking.php
 */
$file = $argv[1] ?? 'gayaland-booking.php';
$src  = file_get_contents( $file );
$fail = 0; $warn = 0;

function ok( $m )   { echo "  ✓ $m\n"; }
function bad( $m )  { global $fail; $fail++; echo "  ✗ $m\n"; }
function warn( $m ) { global $warn; $warn++; echo "  ! $m\n"; }
function head( $m ) { echo "\n== $m ==\n"; }

/* ---------- 1. תחביר ---------- */
head( 'תחביר PHP' );
exec( 'php -l ' . escapeshellarg( $file ) . ' 2>&1', $o, $rc );
$rc === 0 ? ok( 'אין שגיאות תחביר' ) : bad( 'שגיאת תחביר: ' . implode( ' ', $o ) );
if ( 0 !== $rc ) { echo "\nעצירה — קובץ לא תקין.\n"; exit( 1 ); }

/* ---------- 2. כפילויות ---------- */
head( 'כפילויות' );
preg_match_all( '/^function (\w+)/m', $src, $m );
$dupes = array_keys( array_filter( array_count_values( $m[1] ), fn( $c ) => $c > 1 ) );
$dupes ? bad( 'פונקציות כפולות: ' . implode( ', ', $dupes ) ) : ok( 'אין פונקציות כפולות' );

preg_match_all( "/add_submenu_page\(\s*'gyl',\s*'[^']*',\s*'[^']*',\s*'[^']*',\s*'([^']+)'/", $src, $m2 );
$dupSlugs = array_keys( array_filter( array_count_values( $m2[1] ), fn( $c ) => $c > 1 ) );
$dupSlugs ? bad( 'תפריטים כפולים: ' . implode( ', ', $dupSlugs ) ) : ok( 'אין תפריטים כפולים' );

/* ---------- 3. טבלאות — הבאג שתפס אותנו ---------- */
head( 'טבלאות מסד נתונים' );
preg_match_all( '/CREATE TABLE \{\$wpdb->prefix\}(\w+)/', $src, $c );
$created = array_unique( $c[1] );
preg_match_all( '/\{\$wpdb->prefix\}(gyl_\w+)/', $src, $u );
$used = array_unique( $u[1] );
$missing = array_diff( $used, $created );
$missing ? bad( 'טבלאות שנעשה בהן שימוש אך לא נוצרות ב-gyl_activate: ' . implode( ', ', $missing ) )
         : ok( 'כל הטבלאות בשימוש נוצרות (' . implode( ', ', $created ) . ')' );

/* ---------- 4. עמודות ---------- */
head( 'עמודות' );
$cols = array();
foreach ( $created as $t ) {
	if ( preg_match( '/CREATE TABLE \{\$wpdb->prefix\}' . $t . ' \((.*?)\) \$c;/s', $src, $mm ) ) {
		preg_match_all( '/(\w+)\s+(?:BIGINT|INT|SMALLINT|TINYINT|VARCHAR|TEXT|DATE|DATETIME|TIME|DECIMAL)\b/i', $mm[1], $cc );
		$cols[ $t ] = array_map( 'strtolower', $cc[1] );
	}
}

/** כל הכתיבות: insert/update/replace לטבלה, עם חילוץ מפתחות בסוגריים מאוזנים */
$colFail = 0;
foreach ( $created as $t ) {
	$needle = '{$wpdb->prefix}' . $t . '"';
	$pos = 0;
	while ( false !== ( $pos = strpos( $src, $needle, $pos ) ) ) {
		$arrPos = strpos( $src, 'array(', $pos );
		$nl     = strpos( $src, "\n", $pos );
		$pos   += strlen( $needle );
		if ( false === $arrPos || $arrPos > $nl + 400 ) continue;   // לא כתיבה
		$depth = 0; $i = $arrPos + 5; $len = strlen( $src );
		for ( ; $i < $len; $i++ ) {
			if ( '(' === $src[ $i ] ) $depth++;
			if ( ')' === $src[ $i ] ) { $depth--; if ( 0 === $depth ) break; }
		}
		$blk = substr( $src, $arrPos, $i - $arrPos );
		preg_match_all( "/'(\w+)'\s*=>/", $blk, $k );
		foreach ( $k[1] as $key ) {
			if ( ! in_array( strtolower( $key ), $cols[ $t ] ?? array(), true ) ) {
				bad( "עמודה לא קיימת: $t.$key" ); $colFail++;
			}
		}
	}
}
if ( ! $colFail ) ok( 'כל העמודות בכתיבות קיימות בהגדרת הטבלאות (' . array_sum( array_map( 'count', $cols ) ) . ' עמודות)' );

/** עמודות שנקראות בשאילתות SELECT/WHERE */
foreach ( $created as $t ) {
	preg_match_all( '/(?:SELECT|WHERE|SET|ORDER BY|GROUP BY)[^"]*?\{\$wpdb->prefix\}' . $t . '\b([^"]*)/s', $src, $q );
	foreach ( $q[1] as $tail ) {
		preg_match_all( '/\b(\w+)\s*(?:=|<>|>|<|BETWEEN|LIKE|IN)\s*%[sdf]/i', $tail, $w );
		foreach ( $w[1] as $key ) {
			if ( ! in_array( strtolower( $key ), $cols[ $t ] ?? array(), true ) ) {
				bad( "עמודה בשאילתה לא קיימת: $t.$key" ); $colFail++;
			}
		}
	}
}

/* ---------- 5. פונקציות שנקראות ולא קיימות ---------- */
head( 'פונקציות' );
$defined = $m[1];
$clean = preg_replace( '/\{\$wpdb->prefix\}\w+/', 'TBL', $src );
preg_match_all( '/(?<![\$>\w])(gyl_\w+)\s*\(/', $clean, $calls );
$undef = array();
foreach ( array_unique( $calls[1] ) as $fn ) {
	if ( ! in_array( $fn, $defined, true ) && ! in_array( $fn, array( 'gyl_cron' ), true ) ) $undef[] = $fn;
}
$undef ? bad( 'נקראות ולא מוגדרות: ' . implode( ', ', $undef ) ) : ok( 'כל הפונקציות שנקראות מוגדרות' );

/* ---------- 6. מפתחות הגדרות ---------- */
head( 'הגדרות' );
preg_match( '/function gyl_defaults\(\) \{(.*?)\n\}/s', $src, $dm );
preg_match_all( "/'(\w+)'\s*=>/", $dm[1] ?? '', $dk );
$keys = array_unique( $dk[1] );
preg_match_all( "/\\\$s\[\s*'(\w+)'\s*\]/", $src, $sk );
$badKeys = array_diff( array_unique( $sk[1] ), $keys );
$badKeys = array_diff( $badKeys, array( 'branches', 'weekly', 'weekly_summer' ) );
$badKeys ? warn( 'מפתחות בשימוש שאין להם ברירת מחדל: ' . implode( ', ', $badKeys ) )
         : ok( 'לכל מפתח הגדרות יש ברירת מחדל' );

/* ---------- 6b. מערכות כפולות ---------- */
head( 'מערכות כפולות' );
$dupSys = 0;

// שני מיילים שנשלחים לאותה מטרה

preg_match_all( "/add_action\(\s*'gyl_cron',\s*'(\w+)'/", $src, $crons );
$cronFns = $crons[1];
$dupCron = array_keys( array_filter( array_count_values( $cronFns ), fn( $c ) => $c > 1 ) );
$dupCron ? bad( 'משימת cron רשומה פעמיים: ' . implode( ', ', $dupCron ) ) : ok( 'אין cron כפול' );

// שתי משימות שקוראות לאותה עמודת דגל => שולחות לאותם לקוחות
$flags = array();
foreach ( $cronFns as $fn ) {
	if ( preg_match( '/function ' . $fn . '\(\).*?
\}/s', $src, $body ) ) {
		if ( preg_match_all( '/\'(asked|nudged|upsold|reminded)\'\s*=>\s*1/', $body[0], $f ) ) {
			foreach ( $f[1] as $flag ) $flags[ $flag ][] = $fn;
		}
	}
}
foreach ( $flags as $flag => $fns ) {
	if ( count( $fns ) > 1 ) { bad( "שתי משימות משתמשות באותו דגל '$flag': " . implode( ', ', $fns ) . ' — הלקוח יקבל שני מיילים' ); $dupSys++; }
}
if ( ! $dupSys ) ok( 'כל משימה אוטומטית שולחת מייל אחד בלבד' );

// מפתחות הגדרות שאין להם שימוש בקוד (שאריות)
preg_match_all( "/'(\w+)'\s*=>/", $dm[1] ?? '', $dk2 );
$dead = array();
foreach ( array_unique( $dk2[1] ) as $k ) {
	$uses = preg_match_all( '/\$s\[\s*\'' . $k . '\'\s*\]/', $src );
	$nested = array( 'rishon', 'yavne', 'label', 'capacity', 'address', 'weekly', 'weekly_summer', 'branches',
		'enabled', 'days', 'from', 'to', 'age', 'desc', 'tiers', 'single', 'month1', 'month2',
		'sessions', 'per_week', 'product', 'product_id', 'punch_product', 'price' );
	if ( 0 === $uses && ! in_array( $k, $nested, true ) ) $dead[] = $k;
}
$dead ? warn( 'הגדרות ללא שימוש בקוד: ' . implode( ', ', $dead ) ) : ok( 'אין הגדרות מתות' );

/* ---------- 7. חוזה פיצ'רים ---------- */
head( 'חוזה פיצ׳רים' );
$contract = array(
	'שורטקוד פופ-אפ'        => "add_shortcode( 'gayaland_booking_button'",
	'שורטקוד מוטמע'         => "add_shortcode( 'gayaland_booking'",
	'REST — זמינות'         => "'/availability'",
	'REST — הזמנה'          => "'/book'",
	'REST — הזזת מועד'      => "'/move'",
	'REST — לדשבורד'        => "'/bookings'",
	'עמוד קישור אישי'       => "\$_GET['gyl']",
	'השלמת תשלום'           => "\$_GET['gyl_pay']",
	'דירוג בלחיצה'          => "\$_GET['gyl_rate']",
	'תזכורת 24ש'            => 'gyl_send_reminders',
	'שחזור נטישה'           => 'gyl_send_abandoned',
	'אפסייל כרטיסייה'       => 'gyl_send_upsell',
	'שאלון אחרי ביקור'      => 'gyl_send_review',
	'פיקסל Purchase'        => "'Purchase'",
	'Conversions API'       => 'graph.facebook.com',
	'תקנון חובה (שרת)'      => "empty( \$req['terms'] )",
	'ווקומרס — מחיר דינמי'  => 'woocommerce_before_calculate_totals',
	'ווקומרס — אישור תשלום' => 'woocommerce_order_status_processing',
	'לוח חודשי'             => 'gyl_page_cal',
	'בניית חודש'            => 'gyl_page_month',
	'חגי ישראל'             => 'hebcal.com',
	'שעות ליום ספציפי'      => 'gyl_day_override',
	'תבניות מייל'           => 'gyl_tpl',
	'עורך שעות שבועי'       => 'gyl_page_hours',
	'משובים'                => 'gyl_page_feedback',
	'תאימות HPOS'           => "declare_compatibility( 'custom_order_tables'",
	'תאימות קופת בלוקים'    => "'cart_checkout_blocks'",
	'שחרור מקום בהחזר'      => 'woocommerce_order_status_refunded',
	'רשימת המתנה'           => 'gyl_waitlist_ping',
	'קובץ יומן ics'         => 'BEGIN:VCALENDAR',
	'ייצוא CSV'             => 'gyl_csv',
	'בדיקת תקינות באדמין'   => 'woocommerce_enable_guest_checkout',
	'גן עם אמא'             => 'gyl_rest_mom_book',
	'מוצר לפי סניף/שירות'   => 'gyl_product_for',
	'שדרוג DB אוטומטי'      => "get_option( 'gyl_db_ver' )",
	'הגבלת קצב'             => 'gyl_rate_ok',
	'מלכודת ספאם'           => 'honeypot',
	'לידים ליום הולדת'      => 'gyl_page_leads',
	'תוספות בהזמנה'         => 'gyl_addons_active',
	'הוכחה חברתית'          => "'/stats'",
	'שעון שמירת מקום'       => 'startHold',
	'אפסייל במסך התודה'     => 'לרכישת כרטיסייה',
	'אירוע AddToCart'       => "'AddToCart'",
	'אירוע Lead'            => "fbq('track','Lead'",
	'מימוש תוספות'          => "'extras_at'",
	'תוספות בדף מי מגיע'    => 'לממש:',
	'ביטול מימוש'           => "\$_GET['exu']",
	'תוספות ב-CSV'          => 'תוספות נמסרו',
	'שובר אורח — בדיקה'     => "'/voucher/check'",
	'שובר אורח — מימוש'     => 'gyl_voucher_call',
	'שובר בהזמנה'           => "'voucher' === \$ticket",
	'מסירת תוספת מהדשבורד'  => "'/extras/redeem'",
	'הכנסה בטוחה למסד'      => 'gyl_safe_insert',
);
foreach ( $contract as $name => $needle ) {
	strpos( $src, $needle ) !== false ? ok( $name ) : bad( "פיצ׳ר חסר: $name" );
}

/* ---------- 8. אבטחה ---------- */
head( 'אבטחה' );
$posts = preg_match_all( "/if \( ! empty\( \\\$_POST\['gyl/", $src );
$nonces = preg_match_all( '/check_admin_referer/', $src );
$nonces >= $posts ? ok( "בדיקת nonce בכל טופס אדמין ($nonces)" ) : bad( 'טופס אדמין בלי בדיקת nonce' );
preg_match_all( '/\$wpdb->get_results\(\s*"SELECT[^"]*\{\$wpdb->prefix\}[^"]*\$/m', $src, $raw );
strpos( $src, '$wpdb->prepare' ) !== false ? ok( 'שימוש ב-prepare לשאילתות' ) : bad( 'אין prepare' );
strpos( $src, "manage_options" ) !== false ? ok( 'הרשאות אדמין נדרשות' ) : bad( 'אין בדיקת הרשאות' );


/* ---------- 5b. עמודות כפולות + שכבת ביטחון ---------- */
head( 'תקינות סכימה' );
preg_match_all( '/CREATE TABLE[^;]+?\(([^;]+?)\)\s*\$c/s', $src, $tbls );
$dup_cols = array();
foreach ( $tbls[1] as $body ) {
	preg_match_all( '/^\s*(\w+)\s+(?:BIGINT|INT|SMALLINT|TINYINT|VARCHAR|DECIMAL|DATE|DATETIME|TIME|TEXT)/mi', $body, $cm );
	$seen = array();
	foreach ( $cm[1] as $col ) {
		$lc = strtolower( $col );
		if ( in_array( $lc, array( 'key', 'primary', 'unique' ), true ) ) continue;
		if ( isset( $seen[ $lc ] ) ) $dup_cols[] = $col;
		$seen[ $lc ] = 1;
	}
}
$dup_cols
	? bad( 'עמודות כפולות בהגדרת טבלה (dbDelta ייכשל): ' . implode( ', ', array_unique( $dup_cols ) ) )
	: ok( 'אין עמודות כפולות בטבלאות' );

preg_match( '/function gyl_ensure_columns/', $src ) ? ok( 'שכבת ביטחון לעמודות (gyl_ensure_columns)' ) : bad( 'אין gyl_ensure_columns — עמודות חסרות יגרמו ל-INSERT שקט להיכשל' );
preg_match( '/gyl_ensure_columns\(\);/', $src ) ? ok( 'gyl_ensure_columns נקראת מ-activate' ) : bad( 'gyl_ensure_columns מוגדרת אך לא נקראת' );

/* ---------- 8a. data-attributes (הבאג של NaN) ---------- */
head( 'תכונות data ב-JS' );
preg_match_all( '/dataset\.(\w+)/', $src, $ds );
$missing_ds = array();
foreach ( array_unique( $ds[1] ) as $d ) {
	$attr = 'data-' . strtolower( preg_replace( '/([A-Z])/', '-$1', $d ) );
	if ( false === strpos( $src, $attr . '=' ) ) $missing_ds[] = $d;
}
$missing_ds ? bad( 'JS קורא dataset שלא קיים ב-HTML (יגרום ל-NaN/undefined): ' . implode( ', ', $missing_ds ) )
            : ok( 'כל dataset שנקרא ב-JS מוגדר במרקאפ' );

/* ---------- 8b. תקני וורדפרס ---------- */
head( 'תקני וורדפרס' );
preg_match( '/Requires PHP:/', $src ) ? ok( 'כותרת Requires PHP' ) : bad( 'חסר Requires PHP בכותרת' );
preg_match( '/WC tested up to:/', $src ) ? ok( 'כותרת WC tested up to (נדרש לאזהרות HPOS)' ) : bad( 'חסר WC tested up to' );
file_exists( dirname( $file ) . '/uninstall.php' ) ? ok( 'קובץ uninstall.php קיים' ) : warn( 'אין uninstall.php' );
preg_match( "/add_action\( 'plugins_loaded'.*gyl_activate/s", $src ) ? ok( 'שדרוג סכימה רץ גם בעדכון תוסף' ) : bad( 'אין שדרוג DB בעדכון — משתמשים ייאלצו להשבית ולהפעיל' );
preg_match( '/wp_clear_scheduled_hook/', $src ) ? ok( 'ניקוי cron בהשבתה' ) : warn( 'cron לא מנוקה' );
preg_match( '/register_activation_hook/', $src ) ? ok( 'activation hook' ) : bad( 'אין activation hook' );
substr_count( $src, "'permission_callback' => '__return_true'" ) > 0
	? ( preg_match( '/gyl_spam_check/', $src ) ? ok( 'נקודות קצה ציבוריות מוגנות בהגבלת קצב' ) : bad( 'נקודות קצה ציבוריות בלי הגנה' ) )
	: ok( 'אין נקודות קצה ציבוריות' );


/* ---------- 8c. מאזינים לא מוגנים (הבאג ששבר את הפופ-אפ) ---------- */
head( 'מאזיני אירועים בטוחים' );
$js_area = $src;
// כל $('#x').addEventListener חייב שיהיה מוגן ב-if($('#x')) או ?. לפניו
preg_match_all( "/\\\$\\('#[\\w-]+'\\)\\.addEventListener/", $js_area, $unguarded );
$bad_listeners = 0;
foreach ( $unguarded[0] as $m ) {
	// מחפשים אם יש if(...) או guard באותה שורה
	$pos = strpos( $js_area, $m );
	$line_start = strrpos( substr( $js_area, 0, $pos ), "\n" );
	$line = substr( $js_area, $line_start, $pos - $line_start );
	if ( strpos( $line, 'if(' ) === false && strpos( $line, 'if (' ) === false ) $bad_listeners++;
}
$bad_listeners === 0
	? ok( 'כל $(#id).addEventListener מוגן מפני null' )
	: bad( "יש $bad_listeners קריאות addEventListener לא מוגנות — עלולות לשבור את כל הסקריפט אם האלמנט חסר" );

// $(id).classList ישיר — חייב פונקציית show/hide בטוחה
preg_match_all( "/\\\$\\([^)]+\\)\\.classList/", $src, $cl );
$safe_show = preg_match( "/const show=id=>\\{const el=\\\$\\(id\\);if\\(el\\)/", $src );
$safe_show ? ok( 'פונקציית show בטוחה מפני null' ) : bad( 'show/hide עלולים לקרוס על classList של null — לעטוף בבדיקת קיום' );



/* ---------- 8d. שלב התאריך לא נעול מאחורי יום הולדת ---------- */
head( 'מבנה הפופ-אפ' );
$bd_open  = strpos( $src, "\$bd = \$s['birthday']; if ( ! empty( \$bd['enabled'] ) ) :" );
$sdate    = strpos( $src, 'id="s-date"' );
if ( $bd_open !== false && $sdate !== false ) {
	$between = substr( $src, $bd_open, $sdate - $bd_open );
	// חייב להיות endif שסוגר את בלוק יום ההולדת לפני s-date
	substr_count( $between, 'endif' ) >= 1
		? ok( 'שלב התאריך (#s-date) מחוץ לבלוק יום הולדת' )
		: bad( 'שלב התאריך נעול בתוך if(birthday) — יקרוס אם יום הולדת מופעל/כבוי' );
} else { ok( 'מבנה פופ-אפ' ); }


/* ---------- 8e. IDs כפולים ב-HTML ---------- */
head( 'IDs ייחודיים' );
preg_match_all( '/id="(gyl-[\w-]+|s-[\w-]+|[bfmw]-[\w-]+)"/', $src, $ids );
$id_counts = array_count_values( $ids[1] );
$dup_ids = array_filter( $id_counts, function ( $n ) { return $n > 1; } );
$dup_ids
	? bad( 'IDs כפולים (שוברים אלמנטים ומבלבלים את הדפדפן): ' . implode( ', ', array_keys( $dup_ids ) ) )
	: ok( 'כל ה-IDs ייחודיים' );


/* ---------- 8f. CORS + מפתחות כפולים ב-config ---------- */
head( 'endpoint birthday-config' );
$has_cors = strpos( $src, "Access-Control-Allow-Origin" ) !== false;
$has_cors ? ok( 'כותרת CORS קיימת (הדף ב-Netlify יכול לקרוא)' ) : bad( 'חסרה כותרת CORS ב-birthday-config — הדף ייחסם cross-origin' );
// duplicate settings-UI fields (bd_design_img appearing >1 as an input name)
preg_match_all( '/name="(bd_design_img|bd_design_video|bd_gift_img|bd_gift_price)"/', $src, $gm );
$gc = array_count_values( $gm[1] );
$gdup = array_filter( $gc, function ( $n ) { return $n > 1; } );
$gdup ? bad( 'שדות גלריה כפולים בהגדרות: ' . implode( ', ', array_keys( $gdup ) ) ) : ok( 'אין שדות גלריה כפולים' );


/* ---------- 8g. routes כפולים ב-REST ---------- */
head( 'routes ייחודיים' );
preg_match_all( "/register_rest_route\(\s*'gayaland\/v1',\s*'([^']+)'/", $src, $rt );
$rt_counts = array_count_values( $rt[1] );
$dup_rt = array_filter( $rt_counts, function ( $n ) { return $n > 1; } );
$dup_rt
	? bad( 'routes כפולים ב-REST (השני דורס את הראשון): ' . implode( ', ', array_keys( $dup_rt ) ) )
	: ok( 'כל ה-REST routes ייחודיים' );


/* ---------- 8h. CORS ל-header X-GYL-KEY (preflight דשבורד) ---------- */
head( 'CORS ל-X-GYL-KEY' );
$has_allow_headers = ( strpos( $src, 'Access-Control-Allow-Headers' ) !== false )
	&& ( stripos( $src, 'X-GYL-KEY' ) !== false );
$has_preflight = strpos( $src, "'OPTIONS'" ) !== false || strpos( $src, '"OPTIONS"' ) !== false || strpos( $src, "=== 'OPTIONS'" ) !== false;
$has_allow_headers
	? ok( 'X-GYL-KEY מותר ב-Allow-Headers (הדשבורד יכול לקרוא)' )
	: bad( 'חסר Access-Control-Allow-Headers עם X-GYL-KEY — הדשבורד ייחסם ב-preflight' );
$has_preflight ? ok( 'preflight OPTIONS מטופל' ) : bad( 'אין טיפול ב-OPTIONS preflight' );


/* ---------- בדיקה: gyl_render חוזר במצב PHP (מונע דליפת קוד לדף) ---------- */
head( 'gyl_render תקין' );
preg_match( '/function gyl_render.*?ob_get_clean/s', $src, $rr );
( ! empty( $rr ) && preg_match( '/<\?php\s*\n\s*return ob_get_clean/', $src ) )
	? ok( 'gyl_render חוזר במצב PHP' )
	: bad( 'gyl_render עלול להדפיס קוד גולמי — חסר <?php לפני return ob_get_clean' );


/* ---------- בדיקות מוכנות לעומס (אוגוסט) ---------- */
head( 'מוכנות לעומס' );
strpos( $src, 'HTTP_CF_CONNECTING_IP' ) !== false
	? ok( 'rate-limit לפי IP אמיתי (Cloudflare)' )
	: bad( 'rate-limit לפי REMOTE_ADDR — יחסום לקוחות אמיתיים מאחורי Cloudflare!' );
strpos( $src, 'הסבב התמלא ממש עכשיו' ) !== false
	? ok( 'הגנת מרוץ על תפוסה (post-insert recheck)' )
	: bad( 'אין הגנת מרוץ — שני לקוחות יכולים לתפוס את המקום האחרון יחד' );
substr_count( $src, "'voucher' === \$ticket ) {" ) <= 2
	? ok( 'אין כפילות בלוקים של שובר' )
	: bad( 'בלוקים כפולים של מימוש שובר' );
strpos( $src, '$voucher )' ) === false
	? ok( 'אין הפניה למשתנה לא מוגדר \$voucher' )
	: bad( 'הפניה למשתנה לא מוגדר \$voucher' );


head( 'gyl_assets תקין' );
preg_match( '/function gyl_assets\(\).*?\?>/s', $src, $ga );
( ! empty( $ga[0] ) && strpos( $ga[0], '$s = gyl_get()' ) !== false )
	? ok( 'gyl_assets מגדיר $s לפני השימוש' )
	: bad( 'gyl_assets לא מגדיר $s — BRL ריק, DAYS=0, הטופס שבור!' );


head( 'שם פעולת שובר' );
strpos( $src, "'check' === \$action ) \$action = 'voucher_check'" ) !== false || strpos( $src, 'voucher_check' ) !== false
	? ok( 'שם פעולת השובר תואם ל-Apps Script (voucher_check)' )
	: bad( 'שם פעולת השובר לא תואם — Apps Script מצפה voucher_check' );


head( 'כפתורים אחידים' );
strpos( $src, "add_shortcode( 'gayaland_event_button'" ) !== false
	? ok( 'קיים כפתור הזמנת אירוע (gayaland_event_button)' )
	: bad( 'חסר שורטקוד gayaland_event_button' );
strpos( $src, '.gyl-open-btn,.gyl-link-btn' ) !== false
	? ok( 'שני הכפתורים חולקים עיצוב אחיד' )
	: bad( 'הכפתורים לא חולקים class אחיד' );

/* ---------- 9. תאימות PHP ---------- */
head( 'תאימות' );
preg_match( '/\bfn\s*\(/', $src ) ? warn( 'arrow function — דורש PHP 7.4+' ) : ok( 'אין תחביר שדורש PHP חדש' );
preg_match( '/\bmatch\s*\(/', $src ) ? bad( 'match() — דורש PHP 8' ) : ok( 'אין match()' );
preg_match( '/str_contains|str_starts_with/', $src ) ? bad( 'פונקציות PHP 8' ) : ok( 'אין פונקציות PHP 8' );

/* ---------- סיכום ---------- */
echo "\n" . str_repeat( '=', 46 ) . "\n";
if ( $fail ) { echo "❌ נכשל — $fail שגיאות, $warn אזהרות. אין להפיץ.\n"; exit( 1 ); }
echo "✅ ALL CHECKS PASSED" . ( $warn ? " ($warn אזהרות)" : '' ) . " — מוכן להפצה.\n";
exit( 0 );
