<?php
/**
 * Plugin Name: Gayaland Booking — תיאום הגעה
 * Description: מערכת תיאום הגעה לגאיהלנד. פופ-אפ, סבבים לפי סניף, חסימת שעות, כרטיסייה עם חיסכון, תשלום דרך WooCommerce.
 * Version: 11.1.0
 * Author: Gayaland
 * Requires at least: 6.0
 * Requires PHP: 7.2
 * WC requires at least: 7.0
 * WC tested up to: 9.9
 */

if ( ! defined( 'ABSPATH' ) ) exit;
define( 'GYL_OPT', 'gyl_settings' );
define( 'GYL_DB_VER', '11.1' );

/**
 * שדרוג מסד נתונים אוטומטי.
 * register_activation_hook לא רץ בעדכון תוסף — לכן משווים גרסה בכל טעינה.
 */
add_action( 'plugins_loaded', function () {
	if ( get_option( 'gyl_db_ver' ) === GYL_DB_VER ) return;
	gyl_activate();
	update_option( 'gyl_db_ver', GYL_DB_VER );
} );

/* תאימות ל-HPOS (טבלאות הזמנות חדשות) ולקופת הבלוקים של ווקומרס */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
} );

/* ===========================================================
 * הגדרות
 * =========================================================== */
function gyl_defaults() {
	$regular = array(
		0 => array( '09:00-13:00', '16:00-19:00' ),
		1 => array( '09:00-13:00', '16:00-19:00' ),
		2 => array( '09:00-13:00', '16:00-19:00' ),
		3 => array( '09:00-13:00', '16:00-19:00' ),
		4 => array( '09:00-13:00', '16:00-19:00' ),
		5 => array(),
		6 => array( '09:00-17:00' ),
	);
	$summer = array(
		0 => array( '09:00-19:00' ), 1 => array( '09:00-19:00' ), 2 => array( '09:00-19:00' ),
		3 => array( '09:00-19:00' ), 4 => array( '09:00-19:00' ), 5 => array(), 6 => array( '09:00-17:00' ),
	);
	return array(
		'branches' => array(
			'rishon' => array( 'label' => 'ראשון לציון', 'capacity' => 20, 'product_id' => 0, 'punch_product' => 0,
				'address' => 'לישנסקי 27, ראשון לציון. חניה בחניון נאפיס חולון, או ברחוב אליהו איתן 6 (כחול-לבן) — 2 דקות הליכה' ),
			'yavne'  => array( 'label' => 'יבנה', 'capacity' => 30, 'product_id' => 0, 'punch_product' => 0,
				'address' => 'הרצל 19, יבנה — מתחם אייקון, קומה 1, מצד שמאל' ),
		),
		'price'          => 50,    // כרטיס רגיל לילד
		'punch_entries'  => 10,    // כניסות בכרטיסייה
		'punch_price'    => 420,   // מחיר כרטיסייה
		'slot_len'       => 120,
		'slot_step'      => 120,
		'hold_minutes'   => 20,
		'move_hours'     => 8,   // עד כמה שעות לפני אפשר להזיז לבד
		'max_moves'      => 2,   // מקסימום הזזות עצמיות להזמנה
		'move_branch'    => 1,   // מותר להזיז גם לסניף אחר
		'remind'         => 1,   // תזכורת אוטומטית
		'remind_hours'   => 24,  // כמה שעות לפני
		'days_ahead'     => 30,
		'min_lead_min'   => 30,
		'max_children'   => 8,
		'weekly'         => array( 'rishon' => $regular, 'yavne' => $regular ),
		'weekly_summer'  => array( 'rishon' => $summer,  'yavne' => $summer ),
		'summer_ranges'  => "2026-07-01..2026-08-31",
		'holidays'       => "",
		'mom' => array(
			'enabled'  => 1,
			'branches' => array( 'rishon' ),
			'days'     => array( 1, 3 ),          // שני, רביעי
			'from'     => '09:00',
			'to'       => '12:00',
			'capacity' => 16,
			'age'      => 'מגיל 10 חודשים עד 3 שנים',
			'desc'     => "פעילות משותפת לאמהות ולקטנטנים, בהנחיית גננת ומלווה התפתחותית מוסמכת.\n\n09:00 — התכנסות, משחק חופשי במתחם, עבודות יצירה ופעילויות לפיתוח מוטוריקה וכישורי חיים\n10:00 — ארוחת בוקר שמוגשת על ידינו\n11:00 — מפגש מוזיקלי וליווי התפתחותי\n11:30 — מנת פרי\n12:00 — סיום",
			'tiers'    => array(
				'single' => array( 'label' => 'מפגש חד־פעמי',           'price' => 100, 'sessions' => 1, 'per_week' => 1, 'product' => 0 ),
				'month1' => array( 'label' => 'חודשי — פעם בשבוע',      'price' => 360, 'sessions' => 4, 'per_week' => 1, 'product' => 0 ),
				'month2' => array( 'label' => 'חודשי — פעמיים בשבוע',   'price' => 700, 'sessions' => 8, 'per_week' => 2, 'product' => 0, 'best' => 1 ),
			),
		),
		'product_id'     => 0,   // כניסה
		'punch_product'  => 0,   // כרטיסייה
		'api_key'        => '',
		'terms'          => 'ההגעה בסבב של שעתיים. ביטול עד 24 שעות לפני מזכה בזיכוי מלא.',
		'terms_url'      => 'https://gayaland.co.il/%d7%aa%d7%a7%d7%a0%d7%95%d7%9f-%d7%94%d7%90%d7%aa%d7%a8/',
		'notify_email'   => '',   // לאן מגיעות התראות על הזמנות (ריק = מייל האתר)
		'reply_to'       => '',   // כתובת תשובה במיילים ללקוח
		'from_name'      => 'גאיהלנד',   // שם השולח במיילים
		'from_email'     => '',   // מייל השולח (ריק = no-reply@דומיין)
		'auto_punch'     => 0,    // ניקוב אוטומטי במערכת. כבוי = הניקוב נעשה בסניף (רימבר)
		'whatsapp_url'   => 'https://chat.whatsapp.com/Kcxy9uy1bdh2wp0G8iCemG?s=cl&p=a&ilr=4',
		'cancel_url'     => 'https://gayaland.co.il/%d7%9e%d7%93%d7%99%d7%a0%d7%99%d7%95%d7%aa-%d7%91%d7%99%d7%98%d7%95%d7%9c/',
		'subj_confirm'   => 'אישור הגעה לגאיהלנד — %date%',
		'tpl_confirm'    => "היי %first_name%,

תודה שקבעתם לגאיהלנד ב%date% בשעה %time%.

אנחנו נמצאים ב%address%

מגיעים: %children% ילדים

שימו לב — השהייה מוגבלת לשעתיים לפי סבבי ההרשמה, כדי למנוע עומס ולייצר חוויה מיטיבה. אנא הקפידו להגיע בזמן.

להוספת המועד ליומן שלכם: %calendar_link%

צריכים להזיז את המועד? עד %move_hours% שעות לפני, בקישור האישי שלכם: %manage_link%

להצטרפות לקבוצת הוואטסאפ השקטה לפעילויות ועדכונים: %whatsapp_link%

לצפייה בנהלי הביטול: %cancel_link%

מחכים לפגוש אתכם!
גאיהלנד",
		'subj_remind'    => 'תזכורת — מחר בגאיהלנד 🌿',
		'pixel_id'       => '480441214461483',
		'capi_token'     => '',
		'test_mode'      => 0,   // מצב בדיקה — כל עסקה 1 ₪
		'test_price'     => 1,
		'fire_purchase'  => 1,
		'voucher_api'    => 'https://script.google.com/macros/s/AKfycbyG85xJ_sltXLdf_gHkB1pRARLuMNDKM0CDeimCYPb5Iu-V0xG9rUVj5HHJlTTDU-wA/exec',
		'voucher_on'     => 1,   // מימוש שוברי אורחים בהזמנה באתר
		'waitlist'       => 1,   // רשימת המתנה לסבב מלא
		'voucher_api'    => 'https://script.google.com/macros/s/AKfycbyG85xJ_sltXLdf_gHkB1pRARLuMNDKM0CDeimCYPb5Iu-V0xG9rUVj5HHJlTTDU-wA/exec',
		'social_proof'   => 1,   // "X משפחות ביקרו החודש" + דירוג
		'hold_timer'     => 1,   // שעון ספירה לאחור לשמירת המקום
		'addons'         => array(
			array( 'label' => 'קפה + מאפה להורה', 'price' => 22, 'product' => 0, 'per_child' => 0, 'on' => 0 ),
			array( 'label' => 'גרביים אנטי-החלקה', 'price' => 15, 'product' => 0, 'per_child' => 1, 'on' => 0 ),
		),
		'birthday' => array(
			'enabled'   => 1,
			'url'       => '',      // עמוד הזמנת ימי הולדת. אם מלא — הכפתור מפנה לשם
			'from'      => 2000,
			'pitch'     => 'חוגגים יום הולדת? המתחם כולו שלכם, עם הפעלה, כיבוד וצוות שדואג להכל.',
			'notify'    => '',
			'design_img'  => '',   // תמונת דוגמה לעיצוב
			'design_video'=> '',   // סרטון דוגמה לעיצוב (YouTube/MP4)
			'gift_img'    => '',   // תמונת דוגמה למתנת ההפתעה
			'gift_price'  => 25,   // מחיר מתנת ההפתעה לילד
			'gift_label'  => 'שקית הפתעה לילד',
			'gift_video'  => '',   // סרטון מתנת הפתעה
		),
		'purge_on_uninstall' => 0,   // מחיקת כל הנתונים בהסרת התוסף   // כבה אם תוסף אחר כבר שולח Purchase (למניעת ספירה כפולה)
		'abandon'        => 1,
		'abandon_hours'  => 2,
		'subj_abandon'   => 'שכחתם משהו? ההזמנה שלכם בגאיהלנד ממתינה',
		'tpl_abandon'    => "היי %first_name%,

שמנו לב שהתחלתם לקבוע הגעה לגאיהלנד ולא סיימתם 🌿

%branch% · %date% · %time%
%children% ילדים · %price%

שמרנו לכם את המקום לזמן קצר. להשלמת ההזמנה: %pay_link%

מחכים לפגוש אתכם!
גאיהלנד",
		'upsell'         => 1,
		'upsell_days'    => 3,
		'subj_upsell'    => 'נהניתם? כרטיסייה חוסכת לכם %discount%',
		'tpl_upsell'     => "היי %first_name%,

שמחנו לארח אתכם בגאיהלנד! 🌿

מקווים שנהניתם. אם אתם מתכננים לחזור — כרטיסייה של %punch_entries% כניסות עולה %punch_price%, כלומר חיסכון של %discount% על כל כניסה.

לקביעת הביקור הבא: %book_link%

נתראה בקרוב!
גאיהלנד",
		'book_url'       => 'https://gayaland.co.il/',
		'review'         => 1,
		'review_days'    => 1,
		'review_min'     => 4,     // מדירוג זה ומעלה — מפנים לביקורת בגוגל
		'google_url'     => 'https://g.page/r/Cbny8crUL1BAEBM/review',
		'feedback_url'   => 'https://docs.google.com/forms/d/e/1FAIpQLSdEVL5it6Q2_BtGU_uDlQ1bBzx0LU57f0lukynMST4z2wuuTw/viewform',    // טופס משוב חיצוני (Google Forms). ריק = טופס פנימי
		'feedback_page'  => '',    // דף משוב מעוצב ב-Netlify. אם מלא — מפנה אליו במקום לטופס/דף הפנימי
		'birthday_page'  => '',    // דף הזמנת ימי הולדת (Netlify) — לכפתור "הזמנת אירוע"
		'subj_review'    => 'איך היה בגאיהלנד?',
		'tpl_review'     => "היי %first_name%,

תודה שהגעתם אלינו 🌿
נשמח לדעת איך הייתה החוויה — לחיצה אחת, זה הכל:

%stars%

התשובה שלכם עוזרת לנו להשתפר.

תודה!
גאיהלנד",
		'tpl_remind'     => "היי %first_name%,

מזכירים שמחר אנחנו נפגשים 🌿

%branch% · %date% · %time%
מגיעים: %children% ילדים

אנחנו נמצאים ב%address%
אנא הקפידו להגיע בזמן — השהייה היא שעתיים לפי סבבי ההרשמה.

צריכים להזיז? עד %move_hours% שעות לפני: %manage_link%

נתראה מחר!
גאיהלנד",
		'terms_label'    => 'קראתי ואני מאשר/ת את התקנון ותנאי השימוש',
	);
}
function gyl_get( $k = null ) {
	$d = gyl_defaults();
	$s = wp_parse_args( (array) get_option( GYL_OPT, array() ), $d );

	// ריפוי: מפתחות מקוננים שנמחקו בטעות חוזרים לברירת המחדל
	foreach ( array( 'branches', 'mom', 'birthday' ) as $grp ) {
		if ( empty( $s[ $grp ] ) || ! is_array( $s[ $grp ] ) ) $s[ $grp ] = $d[ $grp ];
		else $s[ $grp ] = wp_parse_args( $s[ $grp ], $d[ $grp ] );
	}
	foreach ( array( 'tiers', 'cakes', 'bd_addons', 'nudge', 'base_lines', 'slots_by_day' ) as $bk )
		if ( empty( $s['birthday'][ $bk ] ) ) $s['birthday'][ $bk ] = $d['birthday'][ $bk ];
	if ( empty( $s['mom']['tiers'] ) )  $s['mom']['tiers']  = $d['mom']['tiers'];
	if ( empty( $s['addons'] ) )        $s['addons']        = $d['addons'];

	return $k ? ( $s[ $k ] ?? null ) : $s;
}
function gyl_lines( $t ) { return array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) $t ) ) ) ); }

/** מצב בדיקה — מחזיר 1 ₪ במקום המחיר האמיתי */
function gyl_test_on() {
	$s = gyl_get();
	return ! empty( $s['test_mode'] );
}
function gyl_price_final( $price ) {
	$s = gyl_get();
	if ( empty( $s['test_mode'] ) ) return (float) $price;
	return (float) max( 0, $s['test_price'] );   // ברירת מחדל 1 ₪
}

/** אחוז החיסכון בכרטיסייה */
function gyl_punch_discount() {
	$s = gyl_get();
	if ( ! $s['price'] || ! $s['punch_entries'] ) return 0;
	$per = $s['punch_price'] / $s['punch_entries'];
	return max( 0, round( ( 1 - $per / $s['price'] ) * 100 ) );
}

/* ===========================================================
 * CORS — הדשבורד ב-Netlify קורא ל-REST עם X-GYL-KEY.
 * חייבים להתיר את ה-header הזה ולענות ל-preflight (OPTIONS).
 * =========================================================== */
add_action( 'rest_api_init', function () {
	/* אבחון חיבור למערכת השוברים — גולשים לכתובת:
	   https://gayaland.co.il/wp-json/gayaland/v1/voucher/diag */
	register_rest_route( 'gayaland/v1', '/voucher/diag', array(
		'methods' => 'GET', 'permission_callback' => '__return_true',
		'callback' => function () {
			$s = gyl_get();
			$out = array( 'voucher_api_set' => ! empty( $s['voucher_api'] ), 'url' => $s['voucher_api'] ?? '' );
			$t0 = microtime( true );
			$res = gyl_voucher_call( 'voucher_check', 'GY-TEST00' );
			$out['ms'] = round( ( microtime( true ) - $t0 ) * 1000 );
			if ( is_wp_error( $res ) ) {
				$out['connection'] = 'FAILED';
				$out['error'] = $res->get_error_message();
			} else {
				$out['connection'] = 'OK';
				$out['is_valid_json'] = true;
				$out['parsed'] = $res;   // תשובה אמיתית מ-Apps Script
			}
			return rest_ensure_response( $out );
		},
	) );

	remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
	add_filter( 'rest_pre_serve_request', function ( $served, $result, $request ) {
		if ( strpos( $request->get_route(), '/gayaland/v1' ) === 0 ) {
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-GYL-KEY, X-WP-Nonce' );
			header( 'Access-Control-Max-Age: 86400' );
		}
		return $served;
	}, 10, 3 );
}, 15 );

// עונה מיד ל-preflight OPTIONS לפני שוורדפרס בכלל מתחיל לעבד
add_action( 'init', function () {
	if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'OPTIONS'
		&& strpos( $_SERVER['REQUEST_URI'] ?? '', '/wp-json/gayaland/' ) !== false ) {
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-GYL-KEY, X-WP-Nonce' );
		header( 'Access-Control-Max-Age: 86400' );
		status_header( 204 );
		exit;
	}
}, 1 );

/* ===========================================================
 * הפעלה
 * =========================================================== */
register_activation_hook( __FILE__, 'gyl_activate' );
function gyl_activate() {
	global $wpdb; $c = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( "CREATE TABLE {$wpdb->prefix}gyl_bookings (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		branch VARCHAR(32) NOT NULL, slot_date DATE NOT NULL,
		slot_start TIME NOT NULL, slot_end TIME NOT NULL,
		children SMALLINT NOT NULL DEFAULT 1, adults SMALLINT NOT NULL DEFAULT 1,
		ticket VARCHAR(24) NOT NULL DEFAULT 'single',
		service VARCHAR(16) NOT NULL DEFAULT 'entry',
		tier VARCHAR(24) DEFAULT '',
		series VARCHAR(32) DEFAULT '',
		extras VARCHAR(190) DEFAULT '',
		extras_at DATETIME NULL,
		voucher VARCHAR(24) DEFAULT '',
		price DECIMAL(10,2) NOT NULL DEFAULT 0,
		status VARCHAR(24) NOT NULL DEFAULT 'pending',
		name VARCHAR(120) DEFAULT '', phone VARCHAR(40) DEFAULT '', email VARCHAR(120) DEFAULT '',
		notes TEXT, order_id BIGINT UNSIGNED DEFAULT 0, created_at DATETIME NOT NULL,
		token VARCHAR(32) DEFAULT '', moves TINYINT NOT NULL DEFAULT 0, reminded TINYINT NOT NULL DEFAULT 0,
		terms_at DATETIME NULL, nudged TINYINT NOT NULL DEFAULT 0, upsold TINYINT NOT NULL DEFAULT 0,
		asked TINYINT NOT NULL DEFAULT 0, rating TINYINT NOT NULL DEFAULT 0, feedback TEXT, rated_at DATETIME NULL,
		nayax_ok TINYINT NOT NULL DEFAULT 0, nayax_txn VARCHAR(64) DEFAULT '', nayax_at DATETIME NULL,
		PRIMARY KEY (id), KEY slot_idx (branch, slot_date, slot_start), KEY ph (phone), KEY tk (token)
	) $c;" );

	// טבלת יומן התראות תשלום מ-Nayax — מקור אמת עצמאי
	dbDelta( "CREATE TABLE {$wpdb->prefix}gyl_nayax_log (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		received_at DATETIME NOT NULL,
		order_id BIGINT UNSIGNED DEFAULT 0,
		txn_id VARCHAR(64) DEFAULT '',
		amount DECIMAL(10,2) DEFAULT 0,
		status VARCHAR(24) DEFAULT '',
		matched TINYINT NOT NULL DEFAULT 0,
		raw TEXT,
		PRIMARY KEY (id), KEY oid (order_id), KEY txn (txn_id)
	) $c;" );

	dbDelta( "CREATE TABLE {$wpdb->prefix}gyl_blocks (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		branch VARCHAR(32) NOT NULL,
		block_date DATE NOT NULL,
		slot_start TIME NULL,
		kind VARCHAR(24) DEFAULT 'block',
		reason VARCHAR(160) DEFAULT '',
		PRIMARY KEY (id),
		KEY block_idx (branch, block_date)
	) $c;" );

	dbDelta( "CREATE TABLE {$wpdb->prefix}gyl_leads (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		branch VARCHAR(32) DEFAULT '',
		name VARCHAR(120) DEFAULT '',
		phone VARCHAR(40) DEFAULT '',
		email VARCHAR(120) DEFAULT '',
		party_date DATE NULL,
		guests SMALLINT NOT NULL DEFAULT 0,
		notes TEXT,
		status VARCHAR(24) NOT NULL DEFAULT 'new',
		created_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY st (status)
	) $c;" );

	dbDelta( "CREATE TABLE {$wpdb->prefix}gyl_waitlist (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		branch VARCHAR(32) NOT NULL,
		slot_date DATE NOT NULL,
		slot_start TIME NULL,
		name VARCHAR(120) DEFAULT '',
		phone VARCHAR(40) DEFAULT '',
		email VARCHAR(120) DEFAULT '',
		children SMALLINT NOT NULL DEFAULT 1,
		notified TINYINT NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY wl (branch, slot_date, slot_start)
	) $c;" );

	dbDelta( "CREATE TABLE {$wpdb->prefix}gyl_dayhours (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		branch VARCHAR(32) NOT NULL, day_date DATE NOT NULL,
		ranges VARCHAR(190) NOT NULL DEFAULT '',
		PRIMARY KEY (id), UNIQUE KEY bd (branch, day_date)
	) $c;" );

	dbDelta( "CREATE TABLE {$wpdb->prefix}gyl_credits (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		phone VARCHAR(40) NOT NULL, delta SMALLINT NOT NULL,
		note VARCHAR(120) DEFAULT '', created_at DATETIME NOT NULL,
		PRIMARY KEY (id), KEY ph (phone)
	) $c;" );

	// קודי אימות לכניסת הורים (האזור שלי)
	dbDelta( "CREATE TABLE {$wpdb->prefix}gyl_otp (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		phone VARCHAR(40) NOT NULL,
		code VARCHAR(8) NOT NULL,
		expires_at DATETIME NOT NULL,
		tries TINYINT NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL,
		PRIMARY KEY (id), KEY ph (phone)
	) $c;" );

	// משובים מפורטים מדף המשוב המעוצב
	dbDelta( "CREATE TABLE {$wpdb->prefix}gyl_feedback (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		booking_id BIGINT UNSIGNED DEFAULT 0,
		rating TINYINT NOT NULL DEFAULT 0,
		q_clean TINYINT DEFAULT 0, q_staff TINYINT DEFAULT 0, q_fun TINYINT DEFAULT 0, q_value TINYINT DEFAULT 0,
		comment TEXT,
		name VARCHAR(120) DEFAULT '', phone VARCHAR(40) DEFAULT '', branch VARCHAR(20) DEFAULT '',
		created_at DATETIME NOT NULL,
		PRIMARY KEY (id), KEY bk (booking_id)
	) $c;" );

	$s = gyl_get();
	if ( empty( $s['api_key'] ) ) $s['api_key'] = wp_generate_password( 32, false );

	if ( class_exists( 'WooCommerce' ) ) {
		if ( empty( $s['product_id'] ) ) {
			$p = new WC_Product_Simple();
			$p->set_name( 'כניסה לגאיהלנד' ); $p->set_status( 'private' );
			$p->set_catalog_visibility( 'hidden' ); $p->set_virtual( true );
			$p->set_price( 0 ); $p->set_regular_price( 0 );
			$s['product_id'] = $p->save();
		}
		if ( empty( $s['punch_product'] ) ) {
			$p = new WC_Product_Simple();
			$p->set_name( 'כרטיסייה — ' . $s['punch_entries'] . ' כניסות' );
			$p->set_status( 'private' ); $p->set_catalog_visibility( 'hidden' ); $p->set_virtual( true );
			$p->set_price( $s['punch_price'] ); $p->set_regular_price( $s['punch_price'] );
			$s['punch_product'] = $p->save();
		}
	}
	update_option( GYL_OPT, $s );
	update_option( 'gyl_db_ver', GYL_DB_VER );
	if ( ! wp_next_scheduled( 'gyl_cron' ) ) wp_schedule_event( time() + 60, 'hourly', 'gyl_cron' );

	// שכבת ביטחון: dbDelta לפעמים מחמיץ עמודות — מוודאים במפורש
	gyl_ensure_columns();
}

/** מוודא שכל עמודה קריטית קיימת בטבלת ההזמנות — מוסיף אם חסרה */
function gyl_ensure_columns() {
	global $wpdb;
	$t = "{$wpdb->prefix}gyl_bookings";
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$t'" ) !== $t ) return;

	$have = $wpdb->get_col( "SHOW COLUMNS FROM $t", 0 );
	$need = array(
		'service'   => "VARCHAR(16) NOT NULL DEFAULT 'entry'",
		'tier'      => "VARCHAR(24) DEFAULT ''",
		'series'    => "VARCHAR(32) DEFAULT ''",
		'extras'    => "VARCHAR(190) DEFAULT ''",
		'extras_at' => "DATETIME NULL",
		'voucher'   => "VARCHAR(24) DEFAULT ''",
		'rated_at'  => "DATETIME NULL",
		'nudged'    => "TINYINT NOT NULL DEFAULT 0",
		'upsold'    => "TINYINT NOT NULL DEFAULT 0",
		'asked'     => "TINYINT NOT NULL DEFAULT 0",
		'nayax_ok'  => "TINYINT NOT NULL DEFAULT 0",
		'nayax_txn' => "VARCHAR(64) DEFAULT ''",
		'nayax_at'  => "DATETIME NULL",
	);
	foreach ( $need as $col => $def ) {
		if ( ! in_array( $col, $have, true ) ) {
			$wpdb->query( "ALTER TABLE $t ADD COLUMN $col $def" );
		}
	}
}

register_deactivation_hook( __FILE__, function () { wp_clear_scheduled_hook( 'gyl_cron' ); } );

/* ===========================================================
 * תזכורות אוטומטיות — רצות כל שעה
 * =========================================================== */
add_action( 'gyl_cron', 'gyl_send_reminders' );
add_action( 'gyl_cron', 'gyl_send_abandoned' );
add_action( 'gyl_cron', 'gyl_send_upsell' );


/** עמוד הדירוג — /?gyl_rate=TOKEN&stars=N */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['gyl_rate'] ) ) return;
	global $wpdb; $s = gyl_get();
	$b = gyl_booking_by_token( sanitize_text_field( wp_unslash( $_GET['gyl_rate'] ) ) );
	if ( ! $b ) { wp_safe_redirect( home_url() ); exit; }

	$stars = max( 1, min( 5, (int) ( $_GET['stars'] ?? 0 ) ) );
	$saved = false;

	if ( ! empty( $_POST['gyl_fb'] ) && wp_verify_nonce( $_POST['_n'] ?? '', 'gyl_fb_' . $b['id'] ) ) {
		$txt = sanitize_textarea_field( wp_unslash( $_POST['gyl_fb'] ) );
		$wpdb->update( "{$wpdb->prefix}gyl_bookings", array( 'feedback' => $txt ), array( 'id' => $b['id'] ) );
		gyl_mail( gyl_admin_email(), 'גאיהלנד — משוב ' . (int) $b['rating'] . '★ מ' . $b['name'],
			"דירוג: {$b['rating']}\nסניף: " . ( $s['branches'][ $b['branch'] ]['label'] ?? '' ) . "\nתאריך ביקור: {$b['slot_date']}\n{$b['name']} | {$b['phone']} | {$b['email']}\n\n$txt" );
		$saved = true;
	} elseif ( ! (int) $b['rating'] ) {
		$wpdb->update( "{$wpdb->prefix}gyl_bookings",
			array( 'rating' => $stars, 'rated_at' => current_time( 'mysql' ) ), array( 'id' => $b['id'] ) );
		$b['rating'] = $stars;
		if ( $stars <= 3 ) gyl_mail( gyl_admin_email(), "גאיהלנד — דירוג נמוך ({$stars}★) מ{$b['name']}",
			"דירוג: $stars\nסניף: " . ( $s['branches'][ $b['branch'] ]['label'] ?? '' ) . "\n{$b['name']} | {$b['phone']}\nביקור: {$b['slot_date']}" );
	}
	$stars = (int) $b['rating'];

	status_header( 200 ); nocache_headers();
	?><!doctype html><html lang="he" dir="rtl"><head><meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1"><title>תודה — גאיהלנד</title>
	<style>body{margin:0;background:#FAF7F0;color:#3C3A34;font-family:system-ui,Arial,sans-serif;padding:24px}
	.w{max-width:480px;margin:0 auto;background:#fff;border:1px solid #E6DFD1;border-radius:22px;padding:24px;text-align:center}
	h1{color:#7C8C63;font-size:1.3rem;margin:0 0 10px}
	.st{font-size:30px;letter-spacing:5px;margin:8px 0 16px}
	p{color:#A79684;line-height:1.6;font-size:.95rem}
	textarea{width:100%;padding:12px;border:1.5px solid #E0D8C8;border-radius:12px;font:inherit;min-height:110px;margin-top:10px}
	.btn{display:inline-block;width:100%;box-sizing:border-box;background:#7C8C63;color:#fff;border:0;border-radius:14px;padding:14px;font:inherit;font-weight:700;cursor:pointer;text-decoration:none;margin-top:12px}
	.ghost{background:#fff;color:#7C8C63;border:1.5px solid #7C8C63}</style></head><body><div class="w">
	<?php if ( $saved ) : ?>
		<h1>תודה על המשוב 🌿</h1><p>קראנו כל מילה. נשמח לראות אתכם שוב.</p>
		<a class="btn" href="<?php echo esc_url( $s['book_url'] ?: home_url() ); ?>">לקביעת ביקור נוסף</a>
	<?php elseif ( $stars >= 4 ) : ?>
		<h1>תודה! 🌿</h1>
		<div class="st"><?php echo str_repeat( '⭐', $stars ); ?></div>
		<p>שמחנו שנהניתם. ביקורת קצרה בגוגל עוזרת להורים אחרים למצוא אותנו — ולנו המון.</p>
		<?php if ( $s['google_url'] ) : ?>
			<a class="btn" href="<?php echo esc_url( $s['google_url'] ); ?>" target="_blank">כתיבת ביקורת בגוגל</a>
		<?php endif; ?>
		<a class="btn ghost" href="<?php echo esc_url( $s['book_url'] ?: home_url() ); ?>">לקביעת ביקור נוסף</a>
	<?php else : ?>
		<h1>תודה שאמרתם לנו</h1>
		<div class="st"><?php echo str_repeat( '⭐', $stars ); ?></div>
		<p>מצטערים שלא היה מושלם. ספרו לנו מה קרה — זה מגיע ישירות אלינו ולא מתפרסם בשום מקום.</p>
		<form method="post">
			<?php wp_nonce_field( 'gyl_fb_' . $b['id'], '_n' ); ?>
			<textarea name="gyl_fb" placeholder="מה היה חסר?" required></textarea>
			<button class="btn">שליחה</button>
		</form>
	<?php endif; ?>
	</div></body></html><?php
	exit;
} );

/** שחזור הזמנה נטושה — התחילו ולא שילמו */
function gyl_send_abandoned() {
	global $wpdb; $s = gyl_get();
	if ( empty( $s['abandon'] ) ) return;
	$h  = max( 1, (int) $s['abandon_hours'] );
	$lo = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - ( $h + 6 ) * 3600 );
	$hi = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - $h * 3600 );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}gyl_bookings
		 WHERE status='pending' AND nudged=0 AND email<>''
		 AND created_at BETWEEN %s AND %s
		 AND CONCAT(slot_date,' ',slot_start) > NOW()", $lo, $hi ), ARRAY_A );
	foreach ( (array) $rows as $b ) {
		gyl_mail( $b['email'], gyl_tpl( $s['subj_abandon'], $b ), gyl_tpl( $s['tpl_abandon'], $b ), true );
		$wpdb->update( "{$wpdb->prefix}gyl_bookings", array( 'nudged' => 1 ), array( 'id' => $b['id'] ) );
	}
}

/** אפסייל לכרטיסייה — אחרי ביקור בכרטיס רגיל */
function gyl_send_upsell() {
	global $wpdb; $s = gyl_get();
	if ( empty( $s['upsell'] ) ) return;
	$d = max( 1, (int) $s['upsell_days'] );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}gyl_bookings
		 WHERE ticket='single' AND upsold=0 AND email<>''
		 AND status IN ('confirmed','checked_in')
		 AND CONCAT(slot_date,' ',slot_end) < DATE_SUB(NOW(), INTERVAL %d DAY)
		 AND CONCAT(slot_date,' ',slot_end) > DATE_SUB(NOW(), INTERVAL %d DAY)", $d, $d + 7 ), ARRAY_A );
	foreach ( (array) $rows as $b ) {
		// כבר יש לו כרטיסייה? אל תציק
		$has = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}gyl_bookings WHERE phone=%s AND ticket IN ('buy_punch','use_punch')", $b['phone'] ) );
		if ( ! $has ) gyl_mail( $b['email'], gyl_tpl( $s['subj_upsell'], $b ), gyl_tpl( $s['tpl_upsell'], $b ), true );
		$wpdb->update( "{$wpdb->prefix}gyl_bookings", array( 'upsold' => 1 ), array( 'id' => $b['id'] ) );
	}
}

/** שורת כוכבים ללחיצה במייל */
function gyl_stars_html( $token ) {
	$out = '<table role="presentation" style="margin:14px auto"><tr>';
	for ( $i = 1; $i <= 5; $i++ ) {
		$url = home_url( '/?gyl_rate=' . rawurlencode( $token ) . '&amp;stars=' . $i );
		$out .= '<td style="padding:0 4px"><a href="' . $url . '" style="display:block;width:44px;height:44px;line-height:44px;text-align:center;font-size:22px;text-decoration:none;border:1.5px solid #E0D8C8;border-radius:12px;background:#FAF7F0">⭐</a><div style="text-align:center;font-size:11px;color:#A79684">' . $i . '</div></td>';
	}
	return $out . '</tr></table>';
}

/** מייל שאלון אחרי הביקור */
add_action( 'gyl_cron', 'gyl_send_review' );
function gyl_send_review() {
	global $wpdb; $s = gyl_get();
	if ( empty( $s['review'] ) ) return;
	$d = max( 0, (int) $s['review_days'] );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}gyl_bookings
		 WHERE asked=0 AND email<>'' AND status IN ('confirmed','checked_in')
		 AND CONCAT(slot_date,' ',slot_end) < DATE_SUB(NOW(), INTERVAL %d DAY)
		 AND CONCAT(slot_date,' ',slot_end) > DATE_SUB(NOW(), INTERVAL %d DAY)", $d, $d + 5 ), ARRAY_A );
	foreach ( (array) $rows as $b ) {
		gyl_mail( $b['email'], gyl_tpl( $s['subj_review'], $b ), gyl_tpl( $s['tpl_review'], $b ), true );
		$wpdb->update( "{$wpdb->prefix}gyl_bookings", array( 'asked' => 1 ), array( 'id' => $b['id'] ) );
	}
}

/** דירוג בלחיצה — /?gyl_rate=TOKEN&stars=N */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['gyl_rate'] ) ) return;
	global $wpdb; $s = gyl_get();
	$b = gyl_booking_by_token( sanitize_text_field( wp_unslash( $_GET['gyl_rate'] ) ) );
	if ( ! $b ) { wp_safe_redirect( home_url() ); exit; }
	$stars = max( 1, min( 5, (int) ( $_GET['stars'] ?? 0 ) ) );

	// אם הוגדר דף משוב מעוצב (Netlify) — רושמים את הדירוג ומפנים אליו
	if ( ! empty( $s['feedback_page'] ) && empty( $_POST['gyl_fb'] ) ) {
		$wpdb->update( "{$wpdb->prefix}gyl_bookings", array( 'rating' => $stars, 'rated_at' => current_time( 'mysql' ) ), array( 'id' => $b['id'] ) );
		$sep = ( strpos( $s['feedback_page'], '?' ) !== false ) ? '&' : '?';
		wp_redirect( $s['feedback_page'] . $sep . 'gyl=' . (int) $b['id'] . '&stars=' . $stars );
		exit;
	}

	if ( ! empty( $_POST['gyl_fb'] ) && wp_verify_nonce( $_POST['_n'] ?? '', 'gyl_fb' ) ) {
		$txt = sanitize_textarea_field( wp_unslash( $_POST['fb'] ) );
		$wpdb->update( "{$wpdb->prefix}gyl_bookings", array( 'feedback' => $txt ), array( 'id' => $b['id'] ) );
		gyl_mail( gyl_admin_email(), "גאיהלנד — משוב {$b['rating']}★ מ{$b['name']}",
			"דירוג: {$b['rating']}\nלקוח: {$b['name']} ({$b['phone']})\nביקור: {$b['slot_date']}\n\n$txt" );
		$done = true;
	} else {
		$wpdb->update( "{$wpdb->prefix}gyl_bookings", array( 'rating' => $stars ), array( 'id' => $b['id'] ) );
		$b['rating'] = $stars;
		gyl_mail( gyl_admin_email(), "גאיהלנד — דירוג {$stars}★ מ{$b['name']}",
			"דירוג: {$stars}\nלקוח: {$b['name']} ({$b['phone']})\nביקור: {$b['slot_date']} · " . ( $s['branches'][ $b['branch'] ]['label'] ?? '' ) );
		$done = false;
	}

	$happy = ( (int) $b['rating'] >= (int) $s['review_min'] );
	status_header( 200 ); nocache_headers();
	?><!doctype html><html lang="he" dir="rtl"><head><meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1"><title>תודה — גאיהלנד</title>
	<style>body{margin:0;background:#FAF7F0;color:#3C3A34;font-family:system-ui,Arial,sans-serif;padding:26px}
	.w{max-width:480px;margin:0 auto;background:#fff;border:1px solid #E6DFD1;border-radius:22px;padding:22px;text-align:center}
	h1{color:#7C8C63;font-size:1.3rem;margin:0 0 10px}
	p{color:#A79684;line-height:1.6;font-size:.95rem}
	textarea{width:100%;padding:12px;border:1.5px solid #E0D8C8;border-radius:12px;font:inherit;min-height:110px;text-align:right}
	.btn{display:inline-block;width:100%;background:#7C8C63;color:#fff;border:0;border-radius:14px;padding:14px;font:inherit;font-weight:700;cursor:pointer;text-decoration:none;box-sizing:border-box;margin-top:10px}
	.g{background:#D8A24A}</style></head><body><div class="w">
	<?php if ( ! empty( $done ) ) : ?>
		<h1>תודה על המשוב 🌿</h1><p>קראנו כל מילה. נחזור אליכם אם צריך.</p>
	<?php elseif ( $happy ) : ?>
		<h1>איזה כיף! תודה 🌿</h1>
		<p>נשמח מאוד אם תשתפו את זה גם בגוגל — זה עוזר להורים אחרים למצוא אותנו.</p>
		<?php if ( $s['google_url'] ) : ?>
			<a class="btn g" href="<?php echo esc_url( $s['google_url'] ); ?>" target="_blank">כתיבת ביקורת בגוגל ⭐</a>
		<?php endif; ?>
		<a class="btn" href="<?php echo esc_url( $s['book_url'] ?: home_url() ); ?>">לקביעת הביקור הבא</a>
	<?php elseif ( ! empty( $s['feedback_url'] ) ) : ?>
		<h1>תודה שסיפרתם לנו</h1>
		<p>ממש חשוב לנו להשתפר. תוכלו לספר לנו קצת יותר?</p>
		<a class="btn" href="<?php echo esc_url( add_query_arg( array( 'gyl' => $b['id'] ), $s['feedback_url'] ) ); ?>" target="_blank">למילוי השאלון</a>
	<?php else : ?>
		<h1>תודה שסיפרתם לנו</h1>
		<p>ממש חשוב לנו להשתפר. מה לא עבד?</p>
		<form method="post"><?php wp_nonce_field( 'gyl_fb', '_n' ); ?><input type="hidden" name="gyl_fb" value="1">
			<textarea name="fb" placeholder="ספרו לנו..." required></textarea>
			<button class="btn">שליחה</button></form>
	<?php endif; ?>
	</div></body></html><?php
	exit;
} );

/** קישור להשלמת תשלום — /?gyl_pay=TOKEN */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['gyl_pay'] ) || ! class_exists( 'WooCommerce' ) ) return;
	global $wpdb; $s = gyl_get();
	$b = gyl_booking_by_token( sanitize_text_field( wp_unslash( $_GET['gyl_pay'] ) ) );
	if ( ! $b || 'pending' !== $b['status'] ) { wp_safe_redirect( home_url() ); exit; }

	$slot = null;
	foreach ( gyl_availability( $b['branch'], $b['slot_date'] )['slots'] as $sl )
		if ( $sl['start'] === substr( $b['slot_start'], 0, 5 ) ) $slot = $sl;
	if ( ! $slot || 'open' !== $slot['status'] ) {
		wp_die( '<div dir="rtl" style="font-family:sans-serif;text-align:center;padding:40px">הסבב הזה כבר לא זמין. <a href="' . esc_url( $s['book_url'] ?: home_url() ) . '">לקביעת מועד חדש</a></div>' );
	}
	if ( is_null( WC()->cart ) ) wc_load_cart();
	WC()->cart->empty_cart();
	$pid = ( 'buy_punch' === $b['ticket'] ) ? (int) $s['punch_product'] : (int) $s['product_id'];
	WC()->cart->add_to_cart( $pid, 1, 0, array(), array( 'gyl' => array(
		'booking_id' => (int) $b['id'], 'branch' => $s['branches'][ $b['branch'] ]['label'] ?? '',
		'date' => $b['slot_date'], 'slot' => substr( $b['slot_start'], 0, 5 ) . '–' . substr( $b['slot_end'], 0, 5 ),
		'children' => (int) $b['children'], 'price' => (float) $b['price'], 'ticket' => $b['ticket'], 'phone' => $b['phone'],
	) ) );
	wp_safe_redirect( wc_get_checkout_url() ); exit;
} );

/* ===========================================================
 * פיקסל מטא — Purchase (דפדפן + Conversions API)
 * =========================================================== */
add_action( 'woocommerce_thankyou', function ( $order_id ) {
	$s = gyl_get(); if ( empty( $s['pixel_id'] ) || empty( $s['fire_purchase'] ) ) return;
	$o = wc_get_order( $order_id ); if ( ! $o ) return;
	$bid = 0;
	foreach ( $o->get_items() as $i ) if ( $i->get_meta( '_gyl_booking_id' ) ) $bid = (int) $i->get_meta( '_gyl_booking_id' );
	if ( ! $bid || $o->get_meta( '_gyl_tracked' ) ) return;

	$eid = 'gyl_' . $order_id;
	$val = (float) $o->get_total();
	?><script>if(typeof fbq==='function'){fbq('track','Purchase',{value:<?php echo esc_js( $val ); ?>,currency:'ILS',content_type:'product',content_name:'תיאום הגעה'},{eventID:'<?php echo esc_js( $eid ); ?>'});}</script><?php

	// Conversions API — עוקף חוסמי פרסומות
	if ( ! empty( $s['capi_token'] ) ) {
		$h = function ( $v ) { return $v ? hash( 'sha256', strtolower( trim( $v ) ) ) : null; };
		$phone = preg_replace( '/\D/', '', $o->get_billing_phone() );
		if ( $phone && '0' === $phone[0] ) $phone = '972' . substr( $phone, 1 );
		wp_remote_post( 'https://graph.facebook.com/v21.0/' . $s['pixel_id'] . '/events', array(
			'timeout' => 8, 'blocking' => false,
			'body' => array(
				'access_token' => $s['capi_token'],
				'data' => wp_json_encode( array( array(
					'event_name'       => 'Purchase',
					'event_time'       => time(),
					'event_id'         => $eid,
					'action_source'    => 'website',
					'event_source_url' => $o->get_checkout_order_received_url(),
					'user_data'        => array_filter( array(
						'em' => $h( $o->get_billing_email() ),
						'ph' => $phone ? hash( 'sha256', $phone ) : null,
						'client_ip_address' => $o->get_customer_ip_address(),
						'client_user_agent' => $o->get_customer_user_agent(),
						'fbp' => $_COOKIE['_fbp'] ?? null,
						'fbc' => $_COOKIE['_fbc'] ?? null,
					) ),
					'custom_data' => array( 'value' => $val, 'currency' => 'ILS' ),
				) ) ),
			),
		) );
	}
	$o->update_meta_data( '_gyl_tracked', 1 ); $o->save();
} );
function gyl_send_reminders() {
	global $wpdb; $s = gyl_get();
	if ( empty( $s['remind'] ) ) return;
	$h    = (int) $s['remind_hours'];
	$from = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $h * 3600 );
	$to   = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + ( $h + 1 ) * 3600 );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}gyl_bookings
		 WHERE status='confirmed' AND reminded=0
		 AND CONCAT(slot_date,' ',slot_start) BETWEEN %s AND %s", $from, $to ), ARRAY_A );

	foreach ( (array) $rows as $b ) {
		$msg = gyl_tpl( $s['tpl_remind'], $b );
		if ( $b['email'] ) gyl_mail( $b['email'], gyl_tpl( $s['subj_remind'], $b ), $msg, true );
		do_action( 'gyl_reminder', $b, $msg );   // וו לוואטסאפ (Twilio) בעתיד
		$wpdb->update( "{$wpdb->prefix}gyl_bookings", array( 'reminded' => 1 ), array( 'id' => $b['id'] ) );
	}
}

/* ===========================================================
 * מנוע לוח זמנים
 * =========================================================== */
function gyl_is_special_day( $d ) {
	$s = gyl_get();
	if ( in_array( $d, gyl_lines( $s['holidays'] ), true ) ) return true;
	foreach ( gyl_lines( $s['summer_ranges'] ) as $r ) {
		$p = array_map( 'trim', explode( '..', $r ) );
		if ( count( $p ) === 2 && $d >= $p[0] && $d <= $p[1] ) return true;
	}
	return false;
}
/** שעות חריגות ליום ספציפי (מחזיר null אם אין) */
function gyl_day_override( $branch, $date ) {
	global $wpdb;
	static $cache = array();
	$k = "$branch|$date";
	if ( ! array_key_exists( $k, $cache ) ) {
		$cache[ $k ] = $wpdb->get_var( $wpdb->prepare(
			"SELECT ranges FROM {$wpdb->prefix}gyl_dayhours WHERE branch=%s AND day_date=%s", $branch, $date ) );
	}
	return $cache[ $k ];
}

/** השעות מהתבנית השבועית (בלי חריגות) */
function gyl_template_ranges( $branch, $date ) {
	$s   = gyl_get();
	$dow = (int) date( 'w', strtotime( $date ) );
	$key = gyl_is_special_day( $date ) ? 'weekly_summer' : 'weekly';
	return (array) ( $s[ $key ][ $branch ][ $dow ] ?? array() );
}

/** סבבים מתוך רשימת טווחים נתונה */
function gyl_slots_for_ranges( $ranges, $date ) {
	$s = gyl_get();
	$len = max( 30, (int) $s['slot_len'] ); $step = max( 30, (int) $s['slot_step'] );
	$out = array();
	foreach ( (array) $ranges as $r ) {
		$p = array_map( 'trim', explode( '-', $r ) );
		if ( count( $p ) !== 2 ) continue;
		$o = strtotime( "$date {$p[0]}" ); $c = strtotime( "$date {$p[1]}" );
		for ( $t = $o; $t + $len * 60 <= $c; $t += $step * 60 )
			$out[ date( 'H:i', $t ) ] = array( 'start' => date( 'H:i', $t ), 'end' => date( 'H:i', $t + $len * 60 ) );
	}
	ksort( $out );
	return array_values( $out );
}

function gyl_slots_for( $branch, $date ) {
	$s = gyl_get();
	$ov = gyl_day_override( $branch, $date );
	if ( null !== $ov ) {
		$ranges = array_values( array_filter( array_map( 'trim', explode( ',', $ov ) ) ) );
	} else {
		$ranges = gyl_template_ranges( $branch, $date );
	}
	$len = max( 30, (int) $s['slot_len'] ); $step = max( 30, (int) $s['slot_step'] );
	$out = array();
	foreach ( $ranges as $r ) {
		$p = array_map( 'trim', explode( '-', $r ) );
		if ( count( $p ) !== 2 ) continue;
		$o = strtotime( "$date {$p[0]}" ); $c = strtotime( "$date {$p[1]}" );
		for ( $t = $o; $t + $len * 60 <= $c; $t += $step * 60 )
			$out[ date( 'H:i', $t ) ] = array( 'start' => date( 'H:i', $t ), 'end' => date( 'H:i', $t + $len * 60 ) );
	}
	ksort( $out );
	return array_values( $out );
}
function gyl_taken( $b, $d, $st ) {
	global $wpdb;
	$cut = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - (int) gyl_get( 'hold_minutes' ) * 60 );
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COALESCE(SUM(children),0) FROM {$wpdb->prefix}gyl_bookings
		 WHERE branch=%s AND slot_date=%s AND slot_start=%s
		 AND ( status IN ('confirmed','checked_in') OR ( status='pending' AND created_at > %s ) )",
		$b, $d, $st . ':00', $cut ) );
}
function gyl_blocked( $b, $d, $st = null ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT slot_start, reason FROM {$wpdb->prefix}gyl_blocks WHERE branch=%s AND block_date=%s", $b, $d ), ARRAY_A );
	foreach ( (array) $rows as $r ) {
		if ( empty( $r['slot_start'] ) ) return $r['reason'] ?: 'סגור';
		if ( $st && substr( $r['slot_start'], 0, 5 ) === $st ) return $r['reason'] ?: 'סגור';
	}
	return false;
}
function gyl_availability( $branch, $date ) {
	global $wpdb;
	$s = gyl_get();
	$cap = (int) ( $s['branches'][ $branch ]['capacity'] ?? 0 );
	$now = current_time( 'timestamp' ); $out = array();
	foreach ( gyl_slots_for( $branch, $date ) as $sl ) {
		$bl = gyl_blocked( $branch, $date, $sl['start'] );
		$left = max( 0, $cap - gyl_taken( $branch, $date, $sl['start'] ) );
		$past = strtotime( "$date {$sl['start']}" ) < ( $now + (int) $s['min_lead_min'] * 60 );
		$out[] = array( 'start' => $sl['start'], 'end' => $sl['end'], 'left' => $bl ? 0 : $left,
			'status' => $bl ? 'blocked' : ( $past ? 'past' : ( $left <= 0 ? 'full' : 'open' ) ),
			'reason' => $bl ?: '' );
	}
	$m = gyl_mom(); $mom = null;
	if ( ! empty( $m['enabled'] ) && in_array( $branch, (array) $m['branches'], true )
		&& in_array( (int) date( 'w', strtotime( $date ) ), array_map( 'intval', (array) $m['days'] ), true )
		&& $date >= date( 'Y-m-d' ) ) {
		$ovh = gyl_day_override( $branch, $date );
		$off = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}gyl_blocks WHERE branch=%s AND block_date=%s AND reason LIKE %s",
			$branch, $date, '%ללא גן עם אמא%' ) );
		if ( ! $off && ! ( '' === $ovh && null !== $ovh ) ) {
			$mom = array( 'from' => $m['from'], 'to' => $m['to'], 'left' => gyl_mom_left( $branch, $date ),
				'capacity' => (int) $m['capacity'], 'age' => $m['age'] );
		}
	}

	return array( 'branch' => $branch, 'date' => $date, 'price' => (float) $s['price'],
		'day_block' => gyl_blocked( $branch, $date ) ?: '', 'slots' => $out, 'mom' => $mom );
}

/* ===========================================================
 * יתרת כרטיסייה — עם ווים לחיבור עתידי לרימבר
 * =========================================================== */
function gyl_norm_phone( $p ) { return preg_replace( '/\D/', '', $p ); }

function gyl_punch_balance( $phone ) {
	global $wpdb;
	$phone = gyl_norm_phone( $phone );
	$local = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COALESCE(SUM(delta),0) FROM {$wpdb->prefix}gyl_credits WHERE phone=%s", $phone ) );
	// חיבור עתידי לרימבר: add_filter('gyl_punch_balance', fn($bal,$phone)=>remember_api_balance($phone), 10, 2);
	return (int) apply_filters( 'gyl_punch_balance', $local, $phone );
}
function gyl_punch_add( $phone, $n, $note = '' ) {
	global $wpdb;
	$phone = gyl_norm_phone( $phone );
	$wpdb->insert( "{$wpdb->prefix}gyl_credits", array(
		'phone' => $phone, 'delta' => (int) $n, 'note' => $note, 'created_at' => current_time( 'mysql', true ) ) );
	do_action( 'gyl_punch_changed', $phone, (int) $n, $note ); // רימבר: סנכרון ניקוב/טעינה
}


/** IP אמיתי של המבקר — גם מאחורי Cloudflare/פרוקסי */
function gyl_client_ip() {
	// Cloudflare שולח את ה-IP האמיתי בכותרת ייעודית
	foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $h ) {
		if ( ! empty( $_SERVER[ $h ] ) ) {
			$ip = explode( ',', $_SERVER[ $h ] )[0];
			return trim( $ip );
		}
	}
	return '0';
}

/** הגבלת קצב + מלכודת ספאם לנקודות קצה ציבוריות */
/* Rate limit scoped to a phone number ONLY (no IP in the key) — this is what
   actually prevents SMS bombing, since an attacker can rotate IPs freely. */
function gyl_rate_phone_ok( $phone_suffix, $max = 3, $window = 600 ) {
	$k = 'gyl_rlp_' . md5( (string) $phone_suffix );
	$n = (int) get_transient( $k );
	if ( $n >= $max ) return false;
	set_transient( $k, $n + 1, $window );
	return true;
}

function gyl_rate_ok( $key = 'book', $max = 6, $window = 600 ) {
	$ip = gyl_client_ip();
	$k  = 'gyl_rl_' . $key . '_' . md5( $ip );
	$n  = (int) get_transient( $k );
	if ( $n >= $max ) return false;
	set_transient( $k, $n + 1, $window );
	return true;
}

function gyl_spam_check( $req ) {
	if ( ! empty( $req['website'] ) )                       // honeypot — בני אדם לא ממלאים אותו
		return new WP_Error( 'spam', 'שגיאה', array( 'status' => 400 ) );
	if ( ! gyl_rate_ok() )
		return new WP_Error( 'rate', 'יותר מדי ניסיונות. נסו שוב בעוד כמה דקות.', array( 'status' => 429 ) );
	return true;
}


/** הכנסה בטוחה — אם העמודות חסרות, מריץ שדרוג סכימה ומנסה שוב */
function gyl_safe_insert( $table, $data ) {
	global $wpdb;
	$wpdb->suppress_errors( true );
	$ok = $wpdb->insert( $table, $data );
	if ( false === $ok ) {
		$err = $wpdb->last_error;
		gyl_activate();                       // יוצר טבלאות/עמודות חסרות
		$ok = $wpdb->insert( $table, $data );  // ניסיון שני
		if ( false === $ok ) {
			$wpdb->suppress_errors( false );
			error_log( '[gayaland] INSERT failed: ' . $wpdb->last_error . ' | first: ' . $err );
			return new WP_Error( 'db', 'שגיאת מסד נתונים: ' . $wpdb->last_error, array( 'status' => 500 ) );
		}
	}
	$wpdb->suppress_errors( false );
	return (int) $wpdb->insert_id;
}

/* ===========================================================
 * REST
 * =========================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'gayaland/v1', '/availability', array(
		'methods' => 'GET', 'permission_callback' => '__return_true',
		'callback' => function ( $r ) {
			$b = sanitize_key( $r['branch'] ); $d = sanitize_text_field( $r['date'] );
			if ( ! isset( gyl_get( 'branches' )[ $b ] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) )
				return new WP_Error( 'bad', 'פרמטרים שגויים', array( 'status' => 400 ) );
			return rest_ensure_response( gyl_availability( $b, $d ) );
		} ) );

	register_rest_route( 'gayaland/v1', '/balance', array(
		'methods' => 'GET', 'permission_callback' => '__return_true',
		'callback' => function ( $r ) {
			return rest_ensure_response( array( 'balance' => gyl_punch_balance( sanitize_text_field( $r['phone'] ) ) ) );
		} ) );

	register_rest_route( 'gayaland/v1', '/book', array(
		'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'gyl_rest_book' ) );
} );

function gyl_rest_book( $req ) {
	global $wpdb; $s = gyl_get();

	$guard = gyl_spam_check( $req );
	if ( is_wp_error( $guard ) ) return $guard;
	$branch   = sanitize_key( $req['branch'] );
	$date     = sanitize_text_field( $req['date'] );
	$start    = sanitize_text_field( $req['start'] );
	$children = max( 1, min( (int) $s['max_children'], (int) $req['children'] ) );
	$adults   = max( 0, min( 10, (int) $req['adults'] ) );
	$ticket   = in_array( $req['ticket'], array( 'single', 'buy_punch', 'use_punch', 'voucher' ), true ) ? $req['ticket'] : 'single';
	$vcode    = strtoupper( sanitize_text_field( $req['voucher'] ?? '' ) );
	$name     = sanitize_text_field( $req['name'] );
	$phone    = sanitize_text_field( $req['phone'] );
	$email    = sanitize_email( $req['email'] );
	$notes    = sanitize_textarea_field( $req['notes'] );

	if ( ! isset( $s['branches'][ $branch ] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! preg_match( '/^\d{2}:\d{2}$/', $start ) )
		return new WP_Error( 'bad', 'פרטי הזמנה שגויים', array( 'status' => 400 ) );
	if ( empty( $req['terms'] ) )
		return new WP_Error( 'bad', 'יש לאשר את התקנון ותנאי השימוש', array( 'status' => 400 ) );
	if ( mb_strlen( $name ) < 2 )
		return new WP_Error( 'bad', 'נא למלא שם מלא', array( 'status' => 400 ) );
	if ( ! preg_match( '/^0\d{8,9}$/', gyl_norm_phone( $phone ) ) )
		return new WP_Error( 'bad', 'מספר טלפון לא תקין', array( 'status' => 400 ) );
	if ( ! is_email( $email ) )
		return new WP_Error( 'bad', 'כתובת אימייל לא תקינה', array( 'status' => 400 ) );

	$avail = gyl_availability( $branch, $date ); $slot = null;
	foreach ( $avail['slots'] as $sl ) if ( $sl['start'] === $start ) $slot = $sl;
	if ( ! $slot || 'open' !== $slot['status'] || $slot['left'] < $children )
		return new WP_Error( 'full', 'הסבב כבר מלא. בחרו סבב אחר.', array( 'status' => 409 ) );

	// מימוש כרטיסייה קיימת — בדיקת יתרה רק אם הניקוב האוטומטי פעיל
	if ( 'use_punch' === $ticket && ! empty( $s['auto_punch'] ) ) {
		$bal = gyl_punch_balance( $phone );
		if ( $bal < $children )
			return new WP_Error( 'nocredit', "לא נמצאו מספיק כניסות בכרטיסייה (יתרה: $bal). בדקו את מספר הטלפון או בחרו כרטיס רגיל.", array( 'status' => 402 ) );
	}

	// שובר אורח — אימות ומימוש
	if ( 'voucher' === $ticket ) {
		if ( empty( $s['voucher_on'] ) )
			return new WP_Error( 'off', 'מימוש שוברים אינו זמין', array( 'status' => 400 ) );
		if ( ! preg_match( '/^GY-?[A-Z0-9]{4,10}$/i', $vcode ) )
			return new WP_Error( 'bad', 'קוד שובר לא תקין', array( 'status' => 400 ) );
		$dup = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}gyl_bookings WHERE voucher=%s AND status<>'cancelled'", $vcode ) );
		if ( $dup ) return new WP_Error( 'used', 'השובר כבר נוצל', array( 'status' => 409 ) );
		$vres = gyl_voucher_call( 'redeem', $vcode, $s['branches'][ $branch ]['label'] ?? '' );
		if ( is_wp_error( $vres ) ) {
			// מערכת השוברים לא זמינה — לא מאבדים את ההזמנה, מסמנים לאימות בקבלה
			$GLOBALS['gyl_voucher_deferred'] = true;
		} elseif ( empty( $vres['ok'] ) ) {
			// השרת ענה במפורש שהשובר לא תקף — חוסמים
			return new WP_Error( 'invalid', $vres['error'] ?? 'השובר אינו תקף', array( 'status' => 409 ) );
		}
	}

	$price = 0;
	if ( 'single' === $ticket )    $price = (float) $s['price'] * $children;
	if ( 'buy_punch' === $ticket ) $price = (float) $s['punch_price'];
	$price = gyl_price_final( $price );   // מצב בדיקה → 1 ₪
	if ( 'voucher' === $ticket )   $price = 0;

	$bid = gyl_safe_insert( "{$wpdb->prefix}gyl_bookings", array(
		'branch' => $branch, 'slot_date' => $date, 'slot_start' => $start . ':00', 'slot_end' => $slot['end'] . ':00',
		'children' => $children, 'adults' => $adults, 'ticket' => $ticket, 'price' => $price,
		'service' => 'entry', 'voucher' => ( 'voucher' === $ticket ) ? $vcode : '',
		'status' => in_array( $ticket, array( 'use_punch', 'voucher' ), true ) ? 'confirmed' : 'pending',
		'name' => $name, 'phone' => gyl_norm_phone( $phone ), 'email' => $email, 'notes' => $notes,
		'token' => wp_generate_password( 24, false ), 'terms_at' => current_time( 'mysql' ),
		'created_at' => current_time( 'mysql', true ),
	) );
	if ( is_wp_error( $bid ) ) return $bid;   // לא ממשיכים לתשלום בלי הזמנה!

	/* הגנה מפני מרוץ (race): שני לקוחות על המקומות האחרונים באותה שנייה.
	   אחרי ההכנסה בודקים שוב את התפוסה — אם חרגנו, מבטלים את ההזמנה שלנו. */
	$cap_now = (int) ( $s['branches'][ $branch ]['capacity'] ?? 0 );
	if ( gyl_taken( $branch, $date, $start ) > $cap_now ) {
		$wpdb->delete( "{$wpdb->prefix}gyl_bookings", array( 'id' => $bid ) );
		return new WP_Error( 'full', 'הסבב התמלא ממש עכשיו. בחרו סבב אחר.', array( 'status' => 409 ) );
	}

	if ( 'voucher' === $ticket ) {
		gyl_notify( $bid );
		$vmsg = ! empty( $GLOBALS['gyl_voucher_deferred'] )
			? 'ההגעה נקבעה! השובר יאומת בקבלה בכניסה. נתראה!'
			: 'מעולה! השובר מומש וההגעה נקבעה. הכניסה ל-' . $children . ' ילדים ללא תשלום.';
		if ( ! empty( $GLOBALS['gyl_voucher_deferred'] ) ) {
			$wpdb->update( "{$wpdb->prefix}gyl_bookings",
				array( 'notes' => trim( ( $notes ? $notes . "\n" : '' ) . '[שובr ' . $vcode . ' — לאימות בקבלה]' ) ),
				array( 'id' => $bid ) );
		}
		return rest_ensure_response( array( 'ok' => true, 'mode' => 'done', 'message' => $vmsg ) );
	}

	if ( 'use_punch' === $ticket ) {
		if ( ! empty( $s['auto_punch'] ) ) {
			gyl_punch_add( $phone, -$children, "ניקוב — הזמנה #$bid" );
			$msg = 'ההגעה נקבעה! נוקבו ' . $children . ' כניסות. יתרה: ' . gyl_punch_balance( $phone ) . ' כניסות.';
		} else {
			$msg = 'ההגעה נקבעה! הכרטיסייה תנוקב בקבלה בכניסה (' . $children . ' ' . ( 1 === $children ? 'כניסה' : 'כניסות' ) . ').';
		}
		gyl_notify( $bid );
		return rest_ensure_response( array( 'ok' => true, 'mode' => 'done', 'message' => $msg ) );
	}

	if ( ! class_exists( 'WooCommerce' ) ) return new WP_Error( 'woo', 'חסר חיבור לחנות', array( 'status' => 500 ) );
	if ( is_null( WC()->cart ) ) wc_load_cart();
	WC()->cart->empty_cart();

	$pid = gyl_product_for( 'entry', $branch, $ticket );
	if ( ! $pid ) return new WP_Error( 'woo', 'לא הוגדר מוצר ווקומרס לסניף הזה', array( 'status' => 500 ) );
	if ( WC()->session ) WC()->session->set( 'gyl_booking_id', $bid );

	$picked = array_map( 'intval', (array) ( $req['addons'] ?? array() ) );
	WC()->cart->add_to_cart( $pid, 1, 0, array(), array( 'gyl' => array(
		'booking_id' => $bid, 'branch' => $s['branches'][ $branch ]['label'], 'date' => $date,
		'slot' => $start . '–' . $slot['end'], 'children' => $children, 'price' => $price,
		'ticket' => $ticket, 'phone' => gyl_norm_phone( $phone ),
	) ) );

	// תוספות (גרביים, קפה) — כל אחת שורה נפרדת בהזמנה
	foreach ( gyl_addons_active() as $a ) {
		if ( gyl_test_on() ) break;   // מצב בדיקה — בלי תוספות
		if ( ! in_array( (int) $a['i'], $picked, true ) || empty( $a['product'] ) ) continue;
		$qty = ! empty( $a['per_child'] ) ? $children : 1;
		WC()->cart->add_to_cart( (int) $a['product'], $qty );
	}

	return rest_ensure_response( array( 'ok' => true, 'mode' => 'pay', 'redirect' => wc_get_checkout_url() ) );
}

/* ===========================================================
 * הזזת מועד עצמאית — קישור אישי
 * =========================================================== */
function gyl_manage_link( $token ) { return home_url( '/?gyl=' . rawurlencode( $token ) ); }

function gyl_booking_by_token( $t ) {
	global $wpdb;
	if ( ! $t ) return null;
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gyl_bookings WHERE token=%s", $t ), ARRAY_A );
}

/** האם מותר להזיז, ואם לא — למה */
function gyl_can_move( $b ) {
	$s = gyl_get();
	if ( ! $b ) return 'ההזמנה לא נמצאה.';
	if ( 'confirmed' !== $b['status'] ) return 'לא ניתן להזיז הזמנה במצב זה. צרו איתנו קשר.';
	if ( (int) $b['moves'] >= (int) $s['max_moves'] ) return 'ניצלתם את מכסת ההזזות. צרו איתנו קשר ונשמח לעזור.';
	$start = strtotime( $b['slot_date'] . ' ' . $b['slot_start'] );
	if ( $start - current_time( 'timestamp' ) < (int) $s['move_hours'] * 3600 )
		return 'ניתן להזיז עד ' . (int) $s['move_hours'] . ' שעות לפני ההגעה. צרו איתנו קשר.';
	return true;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'gayaland/v1', '/manage', array(
		'methods' => 'GET', 'permission_callback' => '__return_true',
		'callback' => function ( $r ) {
			$b = gyl_booking_by_token( sanitize_text_field( $r['token'] ) );
			if ( ! $b ) return new WP_Error( 'nf', 'ההזמנה לא נמצאה', array( 'status' => 404 ) );
			$s = gyl_get(); $can = gyl_can_move( $b );
			return rest_ensure_response( array(
				'branch'      => $b['branch'],
				'branch_label'=> $s['branches'][ $b['branch'] ]['label'] ?? $b['branch'],
				'date'        => $b['slot_date'],
				'start'       => substr( $b['slot_start'], 0, 5 ),
				'end'         => substr( $b['slot_end'], 0, 5 ),
				'children'    => (int) $b['children'],
				'name'        => $b['name'],
				'status'      => $b['status'],
				'moves_left'  => max( 0, (int) $s['max_moves'] - (int) $b['moves'] ),
				'can_move'    => ( true === $can ),
				'reason'      => ( true === $can ) ? '' : $can,
				'move_branch' => (int) $s['move_branch'],
				'branches'    => array_map( function ( $k, $v ) { return array( 'key' => $k, 'label' => $v['label'] ); },
					array_keys( $s['branches'] ), $s['branches'] ),
			) );
		} ) );

	register_rest_route( 'gayaland/v1', '/move', array(
		'methods' => 'POST', 'permission_callback' => '__return_true',
		'callback' => function ( $r ) {
			global $wpdb; $s = gyl_get();
			$b = gyl_booking_by_token( sanitize_text_field( $r['token'] ) );
			$can = gyl_can_move( $b );
			if ( true !== $can ) return new WP_Error( 'no', $can, array( 'status' => 403 ) );

			$branch = sanitize_key( $r['branch'] );
			if ( ! $s['move_branch'] ) $branch = $b['branch'];
			$date  = sanitize_text_field( $r['date'] );
			$start = sanitize_text_field( $r['start'] );
			if ( ! isset( $s['branches'][ $branch ] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! preg_match( '/^\d{2}:\d{2}$/', $start ) )
				return new WP_Error( 'bad', 'מועד לא תקין', array( 'status' => 400 ) );
			if ( strtotime( "$date $start" ) - current_time( 'timestamp' ) < (int) $s['move_hours'] * 3600 )
				return new WP_Error( 'bad', 'המועד החדש קרוב מדי.', array( 'status' => 400 ) );

			$slot = null;
			foreach ( gyl_availability( $branch, $date )['slots'] as $sl ) if ( $sl['start'] === $start ) $slot = $sl;
			if ( ! $slot || 'open' !== $slot['status'] || $slot['left'] < (int) $b['children'] )
				return new WP_Error( 'full', 'הסבב הזה מלא. בחרו סבב אחר.', array( 'status' => 409 ) );

			$old = ( $s['branches'][ $b['branch'] ]['label'] ?? '' ) . ' ' . date_i18n( 'j/n', strtotime( $b['slot_date'] ) ) . ' ' . substr( $b['slot_start'], 0, 5 );
			$wpdb->update( "{$wpdb->prefix}gyl_bookings", array(
				'branch' => $branch, 'slot_date' => $date,
				'slot_start' => $start . ':00', 'slot_end' => $slot['end'] . ':00',
				'moves' => (int) $b['moves'] + 1, 'reminded' => 0,
				'notes' => trim( $b['notes'] . "\n[הוזז ע\"י הלקוח מ־$old]" ),
			), array( 'id' => $b['id'] ) );

			// הגנת מרוץ: אם ההזזה גרמה לחריגה — מחזירים את ההזמנה למקומה
			$cap_mv = (int) ( $s['branches'][ $branch ]['capacity'] ?? 0 );
			if ( gyl_taken( $branch, $date, $start ) > $cap_mv ) {
				$wpdb->update( "{$wpdb->prefix}gyl_bookings", array(
					'branch' => $b['branch'], 'slot_date' => $b['slot_date'],
					'slot_start' => $b['slot_start'], 'slot_end' => $b['slot_end'],
					'moves' => (int) $b['moves'], 'notes' => $b['notes'],
				), array( 'id' => $b['id'] ) );
				return new WP_Error( 'full', 'הסבב התמלא ממש עכשיו. בחרו סבב אחר.', array( 'status' => 409 ) );
			}

			gyl_notify( (int) $b['id'], 'הזמנה הוזזה' );
			return rest_ensure_response( array( 'ok' => true,
				'message' => 'המועד עודכן! ' . ( $s['branches'][ $branch ]['label'] ) . ' · ' .
					date_i18n( 'j/n/Y', strtotime( $date ) ) . ' · ' . $start . '–' . $slot['end'],
				'moves_left' => max( 0, (int) $s['max_moves'] - ( (int) $b['moves'] + 1 ) ) ) );
		} ) );
} );

/** עמוד הקישור האישי — home_url('/?gyl=TOKEN') */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['gyl'] ) ) return;
	$s = gyl_get();
	$token = sanitize_text_field( wp_unslash( $_GET['gyl'] ) );
	$b = gyl_booking_by_token( $token );
	status_header( 200 ); nocache_headers();
	?><!doctype html><html lang="he" dir="rtl"><head><meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1"><title>ההזמנה שלי — גאיהלנד</title>
	<style>
	:root{--sage:#7C8C63;--amber:#D8A24A;--taupe:#A79684;--cream:#FAF7F0;--ink:#3C3A34}
	body{margin:0;background:var(--cream);color:var(--ink);font-family:system-ui,'Assistant',Arial,sans-serif;padding:20px}
	.w{max-width:520px;margin:0 auto}
	.c{background:#fff;border:1px solid #E6DFD1;border-radius:20px;padding:18px;margin-bottom:12px}
	h1{font-size:1.25rem;color:var(--sage);margin:0 0 16px}
	.k{font-size:.8rem;color:var(--taupe)}.v{font-weight:700;font-size:1.05rem;margin-bottom:10px}
	.hid{display:none}
	.row{display:flex;gap:8px;flex-wrap:wrap}
	.chip{background:#fff;border:1.5px solid #E0D8C8;border-radius:999px;padding:9px 18px;cursor:pointer;font:inherit}
	.chip.on{background:var(--sage);color:#fff;border-color:var(--sage)}
	.dates{display:flex;gap:8px;overflow-x:auto;padding-bottom:8px}
	.d{min-width:58px;text-align:center;background:#fff;border:1.5px solid #E0D8C8;border-radius:14px;padding:8px 4px;cursor:pointer;flex:0 0 auto}
	.d.on{background:var(--amber);color:#fff;border-color:var(--amber)}
	.d b{display:block}.d span{font-size:.7rem}
	.slots{display:grid;grid-template-columns:repeat(auto-fill,minmax(118px,1fr));gap:8px;margin-top:10px}
	.s{background:#fff;border:1.5px solid #E0D8C8;border-radius:14px;padding:9px;text-align:center;cursor:pointer;font-size:.9rem}
	.s.on{background:var(--sage);color:#fff;border-color:var(--sage)}
	.s.off{opacity:.42;cursor:not-allowed;background:#F1EEE7}
	.s small{display:block;font-size:.7rem}
	button.go{width:100%;background:var(--sage);color:#fff;border:0;border-radius:14px;padding:14px;font:inherit;font-weight:700;cursor:pointer;margin-top:12px}
	.note{font-size:.82rem;color:var(--taupe);line-height:1.6}
	.ok{color:var(--sage);font-weight:700;text-align:center;margin-top:10px}
	.err{color:#B4553F;text-align:center;margin-top:10px}
	</style></head><body><div class="w">
	<?php if ( ! $b ) : ?>
		<div class="c"><h1>ההזמנה לא נמצאה</h1><p class="note">הקישור אינו תקין. צרו איתנו קשר ונשמח לעזור.</p></div>
	<?php else : ?>
		<div class="c">
			<h1>היי <?php echo esc_html( $b['name'] ); ?> 🌿</h1>
			<div class="k">סניף</div><div class="v" id="c-br"><?php echo esc_html( $s['branches'][ $b['branch'] ]['label'] ?? '' ); ?></div>
			<div class="k">מועד</div><div class="v" id="c-dt"><?php echo esc_html( date_i18n( 'l j/n/Y', strtotime( $b['slot_date'] ) ) . ' · ' . substr( $b['slot_start'], 0, 5 ) . '–' . substr( $b['slot_end'], 0, 5 ) ); ?></div>
			<div class="k">ילדים</div><div class="v"><?php echo (int) $b['children']; ?></div>
		</div>
		<div class="c" id="box">
			<?php $can = gyl_can_move( $b ); ?>
			<?php if ( true === $can ) : ?>
				<h1>הזזת מועד</h1>
				<p class="note">אפשר להזיז עד <?php echo (int) $s['move_hours']; ?> שעות לפני ההגעה. נותרו לכם <?php echo (int) $s['max_moves'] - (int) $b['moves']; ?> הזזות. לביטול — צרו איתנו קשר.</p>
				<?php if ( $s['move_branch'] ) : ?>
					<div class="row" id="brs" style="margin:12px 0">
						<?php foreach ( $s['branches'] as $k => $bb ) : ?>
							<button class="chip<?php echo $k === $b['branch'] ? ' on' : ''; ?>" data-b="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $bb['label'] ); ?></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<div class="dates" id="dates"></div>
				<div class="slots" id="slots"></div>
				<button class="go hid" id="go">אישור המועד החדש</button>
				<div id="msg"></div>
			<?php else : ?>
				<h1>הזזת מועד</h1><p class="note"><?php echo esc_html( $can ); ?></p>
			<?php endif; ?>
		</div>

		<script>
		const API='<?php echo esc_url_raw( rest_url( 'gayaland/v1' ) ); ?>',TOKEN='<?php echo esc_js( $token ); ?>';
		const DAYS=<?php echo (int) $s['days_ahead']; ?>,KIDS=<?php echo (int) $b['children']; ?>;
		const HE=['א','ב','ג','ד','ה','ו','ש'];
		let br='<?php echo esc_js( $b['branch'] ); ?>',dt=null,sl=null;
		const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
		const iso=d=>new Date(d.getTime()-d.getTimezoneOffset()*6e4).toISOString().slice(0,10);
		if($('#brs'))$$('#brs .chip').forEach(c=>c.onclick=()=>{$$('#brs .chip').forEach(x=>x.classList.remove('on'));c.classList.add('on');br=c.dataset.b;sl=null;$('#go').classList.add('hid');if(dt)slots();});
		function dates(){const w=$('#dates');const t=new Date();t.setHours(12,0,0,0);
			for(let i=0;i<DAYS;i++){const d=new Date(t);d.setDate(t.getDate()+i);const e=document.createElement('div');
			e.className='d';e.innerHTML='<b>'+d.getDate()+'</b><span>'+HE[d.getDay()]+'׳ '+(d.getMonth()+1)+'</span>';
			e.onclick=()=>{$$('.d').forEach(x=>x.classList.remove('on'));e.classList.add('on');dt=iso(d);slots();};w.appendChild(e);}}
		async function slots(){const w=$('#slots');w.innerHTML='טוען…';sl=null;$('#go').classList.add('hid');
			const r=await(await fetch(API+'/availability?branch='+br+'&date='+dt)).json();w.innerHTML='';
			if(r.day_block){w.innerHTML='<div class="note">סגור — '+r.day_block+'</div>';return;}
			if(!r.slots.length){w.innerHTML='<div class="note">אין סבבים ביום זה.</div>';return;}
			r.slots.forEach(s=>{const e=document.createElement('div');const open=s.status==='open'&&s.left>=KIDS;
				e.className='s'+(open?'':' off');
				const n=s.status==='blocked'?(s.reason||'סגור'):s.status==='past'?'עבר':s.left<KIDS?'מלא':'נותרו '+s.left;
				e.innerHTML=s.start+'–'+s.end+'<small>'+n+'</small>';
				if(open)e.onclick=()=>{$$('.s').forEach(x=>x.classList.remove('on'));e.classList.add('on');sl=s.start;$('#go').classList.remove('hid');};
				w.appendChild(e);});}
		if($('#dates'))dates();
		if($('#go'))$('#go').onclick=async()=>{const g=$('#go'),m=$('#msg');m.textContent='';g.disabled=true;g.textContent='רגע…';
			try{const r=await fetch(API+'/move',{method:'POST',headers:{'Content-Type':'application/json'},
				body:JSON.stringify({token:TOKEN,branch:br,date:dt,start:sl})});
				const d=await r.json();if(!r.ok)throw new Error(d.message||'שגיאה');
				$('#box').innerHTML='<h1>עודכן ✓</h1><p class="ok">'+d.message+'</p><p class="note">נשלח אליכם מייל מעודכן. נתראה!</p>';
			}catch(e){m.className='err';m.textContent=e.message;g.disabled=false;g.textContent='נסו שוב';}};
		</script>
	<?php endif; ?>
	</div></body></html><?php
	exit;
} );




/** מסך התודה — מוכר את הביקור הבא */
add_action( 'woocommerce_thankyou', function ( $order_id ) {
	global $wpdb; $s = gyl_get();
	$o = wc_get_order( $order_id ); if ( ! $o ) return;
	$bid = 0;
	foreach ( $o->get_items() as $i ) if ( $i->get_meta( '_gyl_booking_id' ) ) $bid = (int) $i->get_meta( '_gyl_booking_id' );
	if ( ! $bid ) return;
	$b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gyl_bookings WHERE id=%d", $bid ), ARRAY_A );
	if ( ! $b ) return;

	$disc = gyl_punch_discount();
	$per  = round( $s['punch_price'] / max( 1, $s['punch_entries'] ) );
	?>
	<div dir="rtl" style="max-width:520px;margin:22px auto;font-family:system-ui,Arial,sans-serif">
		<div style="background:#FAF7F0;border:1px solid #EBE4D6;border-radius:18px;padding:16px;text-align:center">
			<div style="font-weight:800;color:#7C8C63;font-size:1.05rem;margin-bottom:6px">נתראה! 🌿</div>
			<div style="font-size:.9rem;color:#3C3A34;line-height:1.7">
				<?php echo esc_html( $s['branches'][ $b['branch'] ]['label'] ?? '' ); ?> ·
				<?php echo esc_html( date_i18n( 'j/n/Y', strtotime( $b['slot_date'] ) ) ); ?> ·
				<?php echo esc_html( substr( $b['slot_start'], 0, 5 ) . '–' . substr( $b['slot_end'], 0, 5 ) ); ?>
			</div>
			<a href="<?php echo esc_url( home_url( '/?gyl_ics=' . rawurlencode( $b['token'] ) ) ); ?>"
				style="display:inline-block;margin-top:10px;background:#7C8C63;color:#fff;border-radius:12px;
				padding:10px 20px;text-decoration:none;font-weight:700;font-size:.9rem">📅 הוספה ליומן</a>
		</div>

		<?php if ( 'single' === $b['ticket'] && 'entry' === $b['service'] ) : ?>
		<div style="background:#FFFDF8;border:1.5px solid #D8A24A;border-radius:18px;padding:16px;margin-top:12px;text-align:center">
			<div style="font-weight:800;margin-bottom:4px">מתכננים לחזור? 🎟️</div>
			<div style="font-size:.88rem;color:#A79684;line-height:1.6">
				כרטיסייה של <?php echo (int) $s['punch_entries']; ?> כניסות עולה <?php echo (int) $s['punch_price']; ?> ₪ —
				<b style="color:#D8A24A"><?php echo $per; ?> ₪ לכניסה במקום <?php echo (int) $s['price']; ?> ₪</b>,
				חיסכון של <?php echo $disc; ?>%.
			</div>
			<a href="<?php echo esc_url( $s['book_url'] ?: home_url() ); ?>"
				style="display:inline-block;margin-top:10px;background:#D8A24A;color:#fff;border-radius:12px;
				padding:10px 22px;text-decoration:none;font-weight:700;font-size:.9rem">לרכישת כרטיסייה</a>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $s['birthday']['enabled'] ) ) : ?>
		<div style="border:1.5px dashed #D8A24A;border-radius:16px;padding:13px;margin-top:12px;text-align:center;font-size:.86rem">
			🎂 <b>חוגגים יום הולדת?</b> החל מ-<?php echo (int) $s['birthday']['from']; ?> ₪ —
			<a href="<?php echo esc_url( $s['book_url'] ?: home_url() ); ?>" style="color:#7C8C63;font-weight:700">לפרטים</a>
		</div>
		<?php endif; ?>
	</div>
	<?php
}, 20 );


/* ===========================================================
 * קישור ההזמנה להזמנת ווקומרס — עובד גם בקופת הבלוקים
 * (הוק line_item לא תמיד רץ ב-Store API — לכן גם דרך ה-session)
 * =========================================================== */
function gyl_attach_booking_to_order( $order ) {
	global $wpdb;
	if ( is_numeric( $order ) ) $order = wc_get_order( $order );
	if ( ! $order ) return;

	$bid = (int) $order->get_meta( '_gyl_booking_id' );

	// 1. מהשורות בהזמנה
	if ( ! $bid ) {
		foreach ( $order->get_items() as $item ) {
			$x = (int) $item->get_meta( '_gyl_booking_id' );
			if ( $x ) { $bid = $x; break; }
		}
	}
	// 2. מה-session (הגיבוי — עובד גם בבלוקים)
	if ( ! $bid && function_exists( 'WC' ) && WC()->session ) {
		$bid = (int) WC()->session->get( 'gyl_booking_id' );
	}
	if ( ! $bid ) return;

	if ( ! $order->get_meta( '_gyl_booking_id' ) ) {
		$order->update_meta_data( '_gyl_booking_id', $bid );
		$order->save();
	}
	$wpdb->update( "{$wpdb->prefix}gyl_bookings", array( 'order_id' => $order->get_id() ), array( 'id' => $bid ) );
	if ( WC()->session ) WC()->session->set( 'gyl_booking_id', null );
}
add_action( 'woocommerce_checkout_order_processed', 'gyl_attach_booking_to_order', 10, 1 );
add_action( 'woocommerce_store_api_checkout_order_processed', 'gyl_attach_booking_to_order', 10, 1 );
add_action( 'woocommerce_new_order', 'gyl_attach_booking_to_order', 20, 1 );

/** אישור בכל מסלול תשלום אפשרי */
add_action( 'woocommerce_payment_complete', 'gyl_order_paid', 20 );
add_action( 'woocommerce_order_status_on-hold', 'gyl_order_paid', 20 );   // העברה בנקאית / בדיקה


/* ===========================================================
 * שוברי אורחים (GY-XXXXX) — אימות ומימוש מול Apps Script
 * =========================================================== */
function gyl_voucher_call( $action, $code, $branch = '' ) {
	$s = gyl_get();
	if ( empty( $s['voucher_api'] ) ) return new WP_Error( 'cfg', 'לא הוגדרה כתובת מערכת השוברים' );
	// התאמת שם הפעולה למה ש-Apps Script מצפה לו
	if ( 'check' === $action ) $action = 'voucher_check';

	$payload = wp_json_encode( array(
		'action' => $action, 'voucher' => $code, 'branch' => $branch,
		'source' => 'booking', 'date' => date( 'Y-m-d' ),
	) );

	// שלב 1: POST בלי לעקוב אוטומטית — Apps Script מחזיר 302 לכתובת התוצאה
	$r = wp_remote_post( $s['voucher_api'], array(
		'timeout'     => 10,
		'redirection' => 0,          // לא עוקבים אוטומטית — נעשה זאת ידנית
		'headers'     => array( 'Content-Type' => 'text/plain;charset=utf-8' ),
		'body'        => $payload,
	) );
	if ( is_wp_error( $r ) ) return $r;

	$httpcode = wp_remote_retrieve_response_code( $r );
	// אם קיבלנו הפניה (302) — עוקבים אחריה עם GET (כך Apps Script מגיש את התוצאה)
	if ( in_array( (int) $httpcode, array( 301, 302, 303, 307 ), true ) ) {
		$loc = wp_remote_retrieve_header( $r, 'location' );
		if ( $loc ) {
			$r = wp_remote_get( $loc, array( 'timeout' => 10, 'redirection' => 3 ) );
			if ( is_wp_error( $r ) ) return $r;
		}
	}

	$body = wp_remote_retrieve_body( $r );
	$j = json_decode( $body, true );
	return is_array( $j ) ? $j : new WP_Error( 'bad', 'תשובה לא תקינה ממערכת השוברים' );
}

add_action( 'rest_api_init', function () {
	/* בדיקת שובר — לפני שהלקוח משלים הזמנה */
	register_rest_route( 'gayaland/v1', '/voucher/check', array(
		'methods' => array( 'GET', 'POST' ), 'permission_callback' => '__return_true',
		'callback' => function ( $r ) {
			$s = gyl_get();
			if ( empty( $s['voucher_on'] ) ) return new WP_Error( 'off', 'מימוש שוברים כבוי', array( 'status' => 400 ) );
			if ( ! gyl_rate_ok( 'voucher', 12, 600 ) )
				return new WP_Error( 'rate', 'יותר מדי ניסיונות. נסו שוב בעוד כמה דקות.', array( 'status' => 429 ) );

			$code = strtoupper( sanitize_text_field( $r['code'] ) );
			if ( ! preg_match( '/^GY-?[A-Z0-9]{4,10}$/i', $code ) )
				return new WP_Error( 'bad', 'קוד שובר לא תקין', array( 'status' => 400 ) );

			global $wpdb;
			$used = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}gyl_bookings WHERE voucher=%s AND status<>'cancelled'", $code ) );
			if ( $used ) return new WP_Error( 'used', 'השובר כבר נוצל בהזמנה קיימת', array( 'status' => 409 ) );

			$res = gyl_voucher_call( 'voucher_check', $code );
			if ( is_wp_error( $res ) ) {
				// מערכת השוברים לא זמינה כרגע — לא חוסמים את הלקוח, מאשרים לאימות בקבלה
				return rest_ensure_response( array(
					'ok' => true, 'code' => $code, 'deferred' => true,
					'message' => 'נרשם! נאמת את השובר בקבלה בכניסה.',
				) );
			}
			if ( empty( $res['ok'] ) )
				return new WP_Error( 'invalid', $res['error'] ?? 'השובר אינו תקף', array( 'status' => 404 ) );

			return rest_ensure_response( array(
				'ok' => true, 'code' => $code,
				'from' => $res['from'] ?? '',
				'message' => 'השובר תקף! הכניסה ללא תשלום.',
			) );
		} ) );

	/* מסירת תוספת (קפה) — מהדשבורד */
} );

/* ===========================================================
 * מנועי מכירה — הוכחה חברתית, תוספות, לידים ליום הולדת
 * =========================================================== */
function gyl_addons_active() {
	$out = array();
	foreach ( (array) gyl_get( 'addons' ) as $i => $a )
		if ( ! empty( $a['on'] ) ) { $a['i'] = $i; $out[] = $a; }
	return $out;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'gayaland/v1', '/stats', array(
		'methods' => 'GET', 'permission_callback' => '__return_true',
		'callback' => function () {
			global $wpdb;
			$k = 'gyl_socialproof';
			$c = get_transient( $k );
			if ( false === $c ) {
				$visits = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COALESCE(SUM(children),0) FROM {$wpdb->prefix}gyl_bookings
					 WHERE status IN ('confirmed','checked_in') AND slot_date >= %s", date( 'Y-m-01' ) ) );
				$avg = $wpdb->get_var( "SELECT ROUND(AVG(rating),1) FROM {$wpdb->prefix}gyl_bookings WHERE rating>0" );
				$n   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}gyl_bookings WHERE rating>0" );
				$c = array( 'visits' => $visits, 'rating' => (float) $avg, 'reviews' => $n );
				set_transient( $k, $c, HOUR_IN_SECONDS );
			}
			return rest_ensure_response( $c );
		} ) );

	register_rest_route( 'gayaland/v1', '/lead', array(
		'methods' => 'POST', 'permission_callback' => '__return_true',
		'callback' => function ( $req ) {
			global $wpdb; $s = gyl_get();
			$g = gyl_spam_check( $req ); if ( is_wp_error( $g ) ) return $g;
			$name  = sanitize_text_field( $req['name'] );
			$phone = sanitize_text_field( $req['phone'] );
			if ( mb_strlen( $name ) < 2 || ! preg_match( '/^0\d{8,9}$/', gyl_norm_phone( $phone ) ) )
				return new WP_Error( 'bad', 'נא למלא שם וטלפון תקין', array( 'status' => 400 ) );

			$wpdb->insert( "{$wpdb->prefix}gyl_leads", array(
				'branch' => sanitize_key( $req['branch'] ),
				'name' => $name, 'phone' => gyl_norm_phone( $phone ),
				'email' => sanitize_email( $req['email'] ),
				'party_date' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $req['date'] ) ? $req['date'] : null,
				'guests' => max( 0, (int) $req['guests'] ),
				'notes' => sanitize_textarea_field( $req['notes'] ),
				'created_at' => current_time( 'mysql', true ),
			) );

			$to = ! empty( $s['birthday']['notify'] ) ? $s['birthday']['notify'] : gyl_admin_email();
			gyl_mail( $to, '🎂 ליד חדש ליום הולדת — ' . $name,
				"שם: $name\nטלפון: " . gyl_norm_phone( $phone ) . "\nסניף: " . ( $s['branches'][ sanitize_key( $req['branch'] ) ]['label'] ?? '' ) .
				"\nתאריך מבוקש: " . sanitize_text_field( $req['date'] ) . "\nאורחים: " . (int) $req['guests'] .
				"\nהערות: " . sanitize_textarea_field( $req['notes'] ) . "\n\nלחזור אליו מהר — לידים ליום הולדת מתקררים תוך שעות." );

			if ( ! empty( $s['pixel_id'] ) ) { /* Lead event נשלח מהדפדפן */ }
			return rest_ensure_response( array( 'ok' => true,
				'message' => 'קיבלנו! נחזור אליכם היום עם כל הפרטים 🎂' ) );
		} ) );
} );

/* ===========================================================
 * גן עם אמא — מנוע
 * =========================================================== */
function gyl_mom() { return gyl_get( 'mom' ); }

/** מועדי מפגשים קרובים לסניף */
function gyl_mom_sessions( $branch, $days_ahead = 56 ) {
	global $wpdb;
	$m = gyl_mom();
	if ( empty( $m['enabled'] ) || ! in_array( $branch, (array) $m['branches'], true ) ) return array();
	$out = array();
	for ( $i = 0; $i <= $days_ahead; $i++ ) {
		$d   = date( 'Y-m-d', strtotime( "+$i days" ) );
		$dow = (int) date( 'w', strtotime( $d ) );
		if ( ! in_array( $dow, array_map( 'intval', (array) $m['days'] ), true ) ) continue;
		$ov = gyl_day_override( $branch, $d );
		if ( '' === $ov && null !== $ov ) continue;              // היום סגור (חג)
		$off = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}gyl_blocks WHERE branch=%s AND block_date=%s AND reason LIKE %s",
			$branch, $d, '%ללא גן עם אמא%' ) );
		if ( $off ) continue;                                    // ביטול חד-פעמי
		$out[] = $d;
	}
	return $out;
}

/** סדרת מפגשים לפי מסלול */
function gyl_mom_series( $branch, $start, $tier ) {
	$m = gyl_mom();
	$t = $m['tiers'][ $tier ] ?? null;
	if ( ! $t ) return array();
	$all = gyl_mom_sessions( $branch, 90 );
	$all = array_values( array_filter( $all, function ( $d ) use ( $start ) { return $d >= $start; } ) );
	if ( 1 === (int) $t['per_week'] ) {                          // אותו יום בשבוע בלבד
		$dow = (int) date( 'w', strtotime( $start ) );
		$all = array_values( array_filter( $all, function ( $d ) use ( $dow ) {
			return (int) date( 'w', strtotime( $d ) ) === $dow; } ) );
	}
	return array_slice( $all, 0, (int) $t['sessions'] );
}

/** כמה מקומות תפוסים במפגש */
function gyl_mom_taken( $branch, $date ) {
	global $wpdb;
	$hold = (int) gyl_get( 'hold_minutes' );
	$cut  = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - $hold * 60 );
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COALESCE(SUM(children),0) FROM {$wpdb->prefix}gyl_bookings
		 WHERE service='mom' AND branch=%s AND slot_date=%s
		 AND ( status IN ('confirmed','checked_in') OR ( status='pending' AND created_at > %s ) )",
		$branch, $date, $cut ) );
}

function gyl_mom_left( $branch, $date ) {
	$m = gyl_mom();
	return max( 0, (int) $m['capacity'] - gyl_mom_taken( $branch, $date ) );
}

/** מוצר ווקומרס לפי שירות, סניף ומסלול */
function gyl_product_for( $service, $branch, $ticket = 'single', $tier = '' ) {
	$s = gyl_get();
	if ( 'mom' === $service ) {
		$p = (int) ( $s['mom']['tiers'][ $tier ]['product'] ?? 0 );
		return $p;
	}
	if ( 'buy_punch' === $ticket )
		return (int) ( $s['branches'][ $branch ]['punch_product'] ?? 0 ) ?: (int) $s['punch_product'];
	return (int) ( $s['branches'][ $branch ]['product_id'] ?? 0 ) ?: (int) $s['product_id'];
}

/* ---------- REST ---------- */
add_action( 'rest_api_init', function () {
	register_rest_route( 'gayaland/v1', '/mom', array(
		'methods' => 'GET', 'permission_callback' => '__return_true',
		'callback' => function ( $r ) {
			$m = gyl_mom();
			$branch = sanitize_key( $r['branch'] ?: ( $m['branches'][0] ?? '' ) );
			$out = array();
			foreach ( gyl_mom_sessions( $branch ) as $d ) {
				$out[] = array( 'date' => $d, 'left' => gyl_mom_left( $branch, $d ) );
			}
			return rest_ensure_response( array(
				'branch'   => $branch,
				'capacity' => (int) $m['capacity'],
				'from'     => $m['from'], 'to' => $m['to'],
				'tiers'    => $m['tiers'],
				'sessions' => $out,
			) );
		} ) );

	register_rest_route( 'gayaland/v1', '/mom/book', array(
		'methods' => 'POST', 'permission_callback' => '__return_true',
		'callback' => 'gyl_rest_mom_book' ) );
} );

function gyl_rest_mom_book( $req ) {
	global $wpdb; $s = gyl_get(); $m = gyl_mom();

	$guard = gyl_spam_check( $req );
	if ( is_wp_error( $guard ) ) return $guard;

	$branch = sanitize_key( $req['branch'] );
	$start  = sanitize_text_field( $req['start'] );
	$tier   = sanitize_key( $req['tier'] );
	$kids   = max( 1, min( 3, (int) $req['children'] ) );
	$name   = sanitize_text_field( $req['name'] );
	$phone  = sanitize_text_field( $req['phone'] );
	$email  = sanitize_email( $req['email'] );
	$notes  = sanitize_textarea_field( $req['notes'] );

	if ( empty( $m['enabled'] ) || ! in_array( $branch, (array) $m['branches'], true ) )
		return new WP_Error( 'off', 'גן עם אמא לא פעיל בסניף זה', array( 'status' => 400 ) );
	if ( ! isset( $m['tiers'][ $tier ] ) )
		return new WP_Error( 'bad', 'מסלול לא תקין', array( 'status' => 400 ) );
	if ( empty( $req['terms'] ) )
		return new WP_Error( 'bad', 'יש לאשר את התקנון ותנאי השימוש', array( 'status' => 400 ) );
	if ( mb_strlen( $name ) < 2 || ! preg_match( '/^0\d{8,9}$/', gyl_norm_phone( $phone ) ) || ! is_email( $email ) )
		return new WP_Error( 'bad', 'נא למלא שם, טלפון ואימייל תקינים', array( 'status' => 400 ) );

	$dates = gyl_mom_series( $branch, $start, $tier );
	if ( ! $dates ) return new WP_Error( 'bad', 'לא נמצאו מפגשים זמינים מהתאריך הזה', array( 'status' => 400 ) );

	$full = array();
	foreach ( $dates as $d ) if ( gyl_mom_left( $branch, $d ) < $kids ) $full[] = date_i18n( 'j/n', strtotime( $d ) );
	if ( $full )
		return new WP_Error( 'full', 'המפגשים הבאים מלאים: ' . implode( ', ', $full ) . '. בחרו מועד התחלה אחר.', array( 'status' => 409 ) );

	$price  = gyl_price_final( (float) $m['tiers'][ $tier ]['price'] );
	$series = 'M' . wp_generate_password( 10, false );
	$first  = 0;

	foreach ( $dates as $i => $d ) {
		$ins = gyl_safe_insert( "{$wpdb->prefix}gyl_bookings", array(
			'branch' => $branch, 'slot_date' => $d,
			'slot_start' => $m['from'] . ':00', 'slot_end' => $m['to'] . ':00',
			'children' => $kids, 'adults' => 1,
			'service' => 'mom', 'tier' => $tier, 'series' => $series,
			'ticket' => 'single', 'price' => ( 0 === $i ? $price : 0 ),
			'status' => 'pending', 'name' => $name, 'phone' => gyl_norm_phone( $phone ),
			'email' => $email, 'notes' => $notes,
			'token' => wp_generate_password( 24, false ), 'terms_at' => current_time( 'mysql' ),
			'created_at' => current_time( 'mysql', true ),
		) );
		if ( is_wp_error( $ins ) ) return $ins;
		if ( 0 === $i ) $first = (int) $ins;
	}

	if ( ! class_exists( 'WooCommerce' ) ) return new WP_Error( 'woo', 'חסר חיבור לחנות', array( 'status' => 500 ) );
	$pid = gyl_product_for( 'mom', $branch, 'single', $tier );
	if ( ! $pid ) return new WP_Error( 'woo', 'לא הוגדר מוצר ווקומרס למסלול הזה', array( 'status' => 500 ) );

	if ( is_null( WC()->cart ) ) wc_load_cart();
	WC()->cart->empty_cart();
	if ( WC()->session ) WC()->session->set( 'gyl_booking_id', $first );
	WC()->cart->add_to_cart( $pid, 1, 0, array(), array( 'gyl' => array(
		'booking_id' => $first, 'series' => $series,
		'branch' => $s['branches'][ $branch ]['label'], 'date' => $dates[0],
		'slot' => $m['from'] . '–' . $m['to'], 'children' => $kids,
		'price' => $price, 'ticket' => 'mom_' . $tier, 'phone' => gyl_norm_phone( $phone ),
		'sessions' => count( $dates ),
	) ) );

	return rest_ensure_response( array( 'ok' => true, 'mode' => 'pay', 'redirect' => wc_get_checkout_url(),
		'sessions' => count( $dates ) ) );
}

/* ===========================================================
 * WooCommerce
 * =========================================================== */
add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
	foreach ( $cart->get_cart() as $i ) if ( isset( $i['gyl']['price'] ) ) $i['data']->set_price( (float) $i['gyl']['price'] );
}, 20 );

add_filter( 'woocommerce_get_item_data', function ( $data, $item ) {
	if ( empty( $item['gyl'] ) ) return $data;
	$g = $item['gyl'];
	$data[] = array( 'name' => 'סניף',  'value' => $g['branch'] );
	$data[] = array( 'name' => 'תאריך', 'value' => date_i18n( 'j/n/Y', strtotime( $g['date'] ) ) );
	$data[] = array( 'name' => 'סבב',   'value' => $g['slot'] );
	$data[] = array( 'name' => 'ילדים', 'value' => $g['children'] );
	return $data;
}, 10, 2 );

add_action( 'woocommerce_checkout_create_order_line_item', function ( $item, $k, $v ) {
	if ( empty( $v['gyl'] ) ) return;
	$g = $v['gyl'];
	$item->add_meta_data( '_gyl_booking_id', $g['booking_id'], true );
	$item->add_meta_data( '_gyl_ticket', $g['ticket'], true );
	$item->add_meta_data( '_gyl_phone', $g['phone'], true );
	$item->add_meta_data( 'סניף', $g['branch'] );
	$item->add_meta_data( 'תאריך', $g['date'] );
	$item->add_meta_data( 'סבב', $g['slot'] );
	$item->add_meta_data( 'ילדים', $g['children'] );
}, 10, 3 );

/** מילוי אוטומטי של פרטי הקופה מתוך ההזמנה */
add_filter( 'woocommerce_checkout_get_value', function ( $val, $key ) {
	if ( $val || is_null( WC()->cart ) ) return $val;
	global $wpdb;
	foreach ( WC()->cart->get_cart() as $i ) {
		if ( empty( $i['gyl']['booking_id'] ) ) continue;
		$b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gyl_bookings WHERE id=%d", (int) $i['gyl']['booking_id'] ), ARRAY_A );
		if ( ! $b ) continue;
		$parts = explode( ' ', trim( $b['name'] ), 2 );
		$map = array(
			'billing_first_name' => $parts[0] ?? '',
			'billing_last_name'  => $parts[1] ?? '-',
			'billing_phone'      => $b['phone'],
			'billing_email'      => $b['email'],
		);
		if ( isset( $map[ $key ] ) ) return $map[ $key ];
	}
	return $val;
}, 10, 2 );

function gyl_order_paid( $order_id ) {
	global $wpdb; $s = gyl_get();
	$o = wc_get_order( $order_id ); if ( ! $o ) return;

	gyl_attach_booking_to_order( $o );          // ודא שהקישור קיים

	$ids = array();
	foreach ( $o->get_items() as $item )
		if ( $item->get_meta( '_gyl_booking_id' ) ) $ids[] = (int) $item->get_meta( '_gyl_booking_id' );
	if ( ! $ids && $o->get_meta( '_gyl_booking_id' ) ) $ids[] = (int) $o->get_meta( '_gyl_booking_id' );

	foreach ( array_unique( $ids ) as $bid ) {
		if ( ! $bid ) continue;
		$b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gyl_bookings WHERE id=%d", $bid ), ARRAY_A );
		if ( ! $b || 'confirmed' === $b['status'] ) continue;
		if ( ! empty( $b['series'] ) ) {   // גן עם אמא — כל הסדרה
			$wpdb->update( "{$wpdb->prefix}gyl_bookings", array( 'status' => 'confirmed', 'order_id' => $order_id ), array( 'series' => $b['series'] ) );
		} else {
			$wpdb->update( "{$wpdb->prefix}gyl_bookings", array( 'status' => 'confirmed', 'order_id' => $order_id ), array( 'id' => $bid ) );
		}
		// תוספות ששולמו (קפה, גרביים) — נרשמות על ההזמנה כדי שהקבלה תדע
		$extras = array();
		foreach ( $o->get_items() as $li ) {
			if ( $li->get_meta( '_gyl_booking_id' ) ) continue;   // זו שורת הכניסה עצמה
			$extras[] = $li->get_name() . ( $li->get_quantity() > 1 ? ' ×' . $li->get_quantity() : '' );
		}
		if ( $extras )
			$wpdb->update( "{$wpdb->prefix}gyl_bookings",
				array( 'extras' => mb_substr( implode( ', ', $extras ), 0, 180 ) ), array( 'id' => $bid ) );

		if ( 'buy_punch' === $b['ticket'] && ! empty( $s['auto_punch'] ) ) {   // ניקוב אוטומטי — רק אם הופעל
			gyl_punch_add( $b['phone'], (int) $s['punch_entries'], "רכישת כרטיסייה — הזמנה #$bid" );
			gyl_punch_add( $b['phone'], -(int) $b['children'], "ניקוב — הזמנה #$bid" );
		}
		gyl_notify( $bid );
	}
}
add_action( 'woocommerce_order_status_processing', 'gyl_order_paid' );
add_action( 'woocommerce_order_status_completed', 'gyl_order_paid' );

function gyl_order_released( $id ) {
	global $wpdb;
	$o = wc_get_order( $id ); if ( ! $o ) return;
	foreach ( $o->get_items() as $i ) {
		$bid = (int) $i->get_meta( '_gyl_booking_id' );
		if ( ! $bid ) continue;
		$b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gyl_bookings WHERE id=%d", $bid ), ARRAY_A );
		if ( ! $b ) continue;
		if ( ! empty( $b['series'] ) )
			$wpdb->update( "{$wpdb->prefix}gyl_bookings", array( 'status' => 'cancelled' ), array( 'series' => $b['series'] ) );
		else
			$wpdb->update( "{$wpdb->prefix}gyl_bookings", array( 'status' => 'cancelled' ), array( 'id' => $bid ) );
		gyl_waitlist_ping( $b['branch'], $b['slot_date'], substr( $b['slot_start'], 0, 5 ) );
	}
}
add_action( 'woocommerce_order_status_cancelled', 'gyl_order_released' );
add_action( 'woocommerce_order_status_refunded',  'gyl_order_released' );
add_action( 'woocommerce_order_status_failed',    'gyl_order_released' );

/* שם ומייל השולח — כל המיילים יוצאים בשם "גאיהלנד" במקום "WordPress" */
add_filter( 'wp_mail_from_name', function ( $name ) {
	$s = function_exists( 'gyl_get' ) ? gyl_get() : array();
	return ! empty( $s['from_name'] ) ? $s['from_name'] : 'גאיהלנד';
}, 99 );
add_filter( 'wp_mail_from', function ( $email ) {
	$s = function_exists( 'gyl_get' ) ? gyl_get() : array();
	if ( ! empty( $s['from_email'] ) && is_email( $s['from_email'] ) ) return $s['from_email'];
	// ברירת מחדל: no-reply בדומיין של האתר (לא משנה את wordpress@)
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$host = preg_replace( '/^www\./', '', (string) $host );
	return $host ? ( 'no-reply@' . $host ) : $email;
}, 99 );

function gyl_mail( $to, $subj, $msg, $html = false ) {
	$s = gyl_get();
	$h = array( 'Content-Type: text/' . ( $html ? 'html' : 'plain' ) . '; charset=UTF-8' );
	if ( ! empty( $s['reply_to'] ) ) $h[] = 'Reply-To: גאיהלנד <' . $s['reply_to'] . '>';
	if ( $html ) $msg = '<div dir="rtl" style="font-family:system-ui,Arial,sans-serif;font-size:15px;line-height:1.7;color:#3C3A34">' . wpautop( $msg ) . '</div>';
	return wp_mail( $to, $subj, $msg, $h );
}

/** חמישה כוכבים לחיצים למייל המשוב */
function gyl_rating_links( $token ) {
	$out = '<div style="font-size:30px;letter-spacing:6px;text-align:center;margin:18px 0">';
	for ( $i = 1; $i <= 5; $i++ )
		$out .= '<a href="' . esc_url( home_url( '/?gyl_rate=' . rawurlencode( $token ) . '&stars=' . $i ) ) . '" style="text-decoration:none">⭐</a>';
	$out .= '</div><div style="text-align:center;font-size:13px;color:#A79684">לחצו על מספר הכוכבים שמתאים</div>';
	return $out;
}

/** החלפת תגיות בתבנית מייל */
function gyl_tpl( $text, $b ) {
	$s  = gyl_get();
	$br = $s['branches'][ $b['branch'] ] ?? array();
	$parts = explode( ' ', trim( $b['name'] ), 2 );
	$lk = function ( $url, $txt ) { return $url ? '<a href="' . esc_url( $url ) . '">' . $txt . '</a>' : $txt; };
	$map = array(
		'%first_name%'    => $parts[0] ?? '',
		'%name%'          => $b['name'],
		'%branch%'        => $br['label'] ?? $b['branch'],
		'%address%'       => $br['address'] ?? '',
		'%date%'          => date_i18n( 'l, j/n/Y', strtotime( $b['slot_date'] ) ),
		'%time%'          => substr( $b['slot_start'], 0, 5 ) . '–' . substr( $b['slot_end'], 0, 5 ),
		'%children%'      => (int) $b['children'],
		'%price%'         => (int) $b['price'] . ' ₪',
		'%move_hours%'    => (int) $s['move_hours'],
		'%manage_link%'   => $lk( gyl_manage_link( $b['token'] ), 'לחצו כאן' ),
		'%whatsapp_link%' => $lk( $s['whatsapp_url'], 'לחצו כאן' ),
		'%cancel_link%'   => $lk( $s['cancel_url'], 'לחצו כאן' ),
		'%terms_link%'    => $lk( $s['terms_url'], 'לחצו כאן' ),
		'%pay_link%'      => $lk( home_url( '/?gyl_pay=' . rawurlencode( $b['token'] ) ), 'להשלמת ההזמנה' ),
		'%calendar_link%' => $lk( home_url( '/?gyl_ics=' . rawurlencode( $b['token'] ) ), 'הוספה ליומן' ),
		'%book_link%'     => $lk( $s['book_url'] ?: home_url(), 'לקביעת הגעה' ),
		'%punch_entries%' => (int) $s['punch_entries'],
		'%punch_price%'   => (int) $s['punch_price'] . ' ₪',
		'%discount%'      => gyl_punch_discount() . '%',
		'%stars%'         => gyl_stars_html( $b['token'] ),
		'%rating_links%'  => gyl_rating_links( $b['token'] ),
	);
	return strtr( $text, $map );
}
function gyl_admin_email() {
	$s = gyl_get();
	return ! empty( $s['notify_email'] ) ? $s['notify_email'] : get_option( 'admin_email' );
}

function gyl_notify( $bid, $subject = 'הזמנה' ) {
	global $wpdb; $s = gyl_get();
	$b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gyl_bookings WHERE id=%d", $bid ), ARRAY_A );
	if ( ! $b ) return;
	$br = gyl_get( 'branches' );
	$lbl = $br[ $b['branch'] ]['label'] ?? $b['branch'];
	$slot = substr( $b['slot_start'], 0, 5 ) . '–' . substr( $b['slot_end'], 0, 5 );
	gyl_mail( gyl_admin_email(), "גאיהלנד — $subject #{$b['id']} ($lbl)",
		"סניף: $lbl\nתאריך: {$b['slot_date']}\nסבב: $slot\nילדים: {$b['children']}\nסוג: {$b['ticket']}\n" .
		( $b['extras'] ? "תוספות ששולמו: {$b['extras']}\n" : '' ) .
		"סכום: {$b['price']} ₪\n{$b['name']} | {$b['phone']}\n{$b['notes']}" );
	if ( $b['email'] )
		gyl_mail( $b['email'], gyl_tpl( $s['subj_confirm'], $b ), gyl_tpl( $s['tpl_confirm'], $b ), true );
}


/* ===========================================================
 * פרונט — כפתור + פופ-אפ  [gayaland_booking_button]
 * שורטקוד מוטמע רגיל — [gayaland_booking]
 * =========================================================== */
/* בונה סגנון inline משותף לשני הכפתורים */
function gyl_btn_style( $a ) {
	$style = '';
	if ( ! empty( $a['bg'] ) )     $style .= 'background:' . esc_attr( $a['bg'] ) . '!important;';
	if ( ! empty( $a['color'] ) )  $style .= 'color:' . esc_attr( $a['color'] ) . '!important;';
	if ( ! empty( $a['radius'] ) ) $style .= 'border-radius:' . esc_attr( $a['radius'] ) . '!important;';
	if ( ! empty( $a['width'] ) && 'full' === $a['width'] ) $style .= 'display:flex!important;width:100%!important;';
	if ( ! empty( $a['size'] ) ) {
		if ( 'small' === $a['size'] )      $style .= 'padding:11px 26px!important;font-size:.95rem!important;min-height:46px!important;';
		elseif ( 'large' === $a['size'] )  $style .= 'padding:19px 50px!important;font-size:1.2rem!important;min-height:60px!important;';
		else                               $style .= 'font-size:' . esc_attr( $a['size'] ) . '!important;';
	}
	return $style;
}

/* כפתור "תיאום הגעה" — פותח את טופס ההזמנה */
add_shortcode( 'gayaland_booking_button', function ( $a ) {
	$a = shortcode_atts( array(
		'text' => 'תיאום הגעה', 'bg' => '', 'color' => '', 'size' => '', 'radius' => '', 'width' => '', 'variant' => '',
	), $a );
	$GLOBALS['gyl_need_modal'] = true;
	$cls = 'gyl-open-btn' . ( 'amber' === $a['variant'] ? ' gyl-btn-amber' : '' );
	$style = gyl_btn_style( $a );
	return '<button type="button" class="' . esc_attr( $cls ) . '" onclick="gylOpen()"' .
		( $style ? ' style="' . $style . '"' : '' ) . '>' . esc_html( $a['text'] ) . '</button>';
} );

/* כפתור "הזמנת אירוע" — קישור לדף ימי ההולדת, עיצוב זהה */
add_shortcode( 'gayaland_event_button', function ( $a ) {
	$s = gyl_get();
	$default_url = ! empty( $s['birthday_page'] ) ? $s['birthday_page'] : ( ! empty( $s['event_url'] ) ? $s['event_url'] : '#' );
	$a = shortcode_atts( array(
		'text' => 'להזמנת אירוע', 'url' => $default_url, 'bg' => '', 'color' => '', 'size' => '', 'radius' => '', 'width' => '', 'variant' => 'amber', 'target' => '',
	), $a );
	$cls = 'gyl-link-btn' . ( 'amber' === $a['variant'] ? ' gyl-btn-amber' : '' );
	$style = gyl_btn_style( $a );
	$GLOBALS['gyl_need_modal'] = true;   // כדי שה-CSS ייטען גם אם אין טופס בדף
	$target = $a['target'] === 'blank' ? ' target="_blank" rel="noopener"' : '';
	return '<a href="' . esc_url( $a['url'] ) . '" class="' . esc_attr( $cls ) . '"' . $target .
		( $style ? ' style="' . $style . '"' : '' ) . '>' . esc_html( $a['text'] ) . '</a>';
} );

add_shortcode( 'gayaland_booking', function () { $GLOBALS['gyl_inline_used'] = true; return gyl_render( false ); } );

/* הפופ-אפ מרונדר ב-footer — כך אלמנטור לא יכול לבלוע אותו */
add_action( 'wp_footer', function () {
	if ( ! empty( $GLOBALS['gyl_need_modal'] ) ) echo gyl_render( true );
}, 6 );

function gyl_render( $modal ) {
	static $done = false; if ( $done ) return ''; $done = true;
	$s = gyl_get(); $disc = gyl_punch_discount();
	ob_start(); ?>
	<div class="gyl-overlay <?php echo $modal ? 'gyl-modal' : 'gyl-inline'; ?>" id="gyl-overlay" <?php if ( $modal ) echo 'style="display:none"'; ?>>
	<div class="gyl-wrap" dir="rtl">
		<?php if ( $modal ) : ?><button class="gyl-x" onclick="gylClose()">✕</button><?php endif; ?>

		<div class="gyl-head">
			<div class="gyl-logo">🌿</div>
			<div>
				<div class="gyl-title">תיאום הגעה</div>
				<div class="gyl-sub">גילאי 1–5 · סבב של שעתיים</div>
			</div>
		</div>
		<?php if ( gyl_test_on() ) : ?>
			<div style="background:#FBEDE9;border:1.5px solid #B4553F;border-radius:12px;padding:9px 12px;margin-bottom:10px;font-size:.8rem;font-weight:700;color:#B4553F;text-align:center">🧪 מצב בדיקה — התשלום יהיה <?php echo (int) $s['test_price']; ?> ₪ בלבד</div>
		<?php endif; ?>
		<div class="gyl-proof" id="gyl-proof"></div>
		<div class="gyl-prog"><i class="on" data-p="1"></i><i data-p="2"></i><i data-p="3"></i><i data-p="4"></i></div>
		<div class="gyl-recap" id="gyl-recap"></div>

		<?php $mom = gyl_mom(); ?>

		<div class="gyl-card" id="s-branch">
			<div class="gyl-h"><span class="gyl-n">1</span> איפה נתראה?</div>
			<div class="gyl-row">
				<?php foreach ( $s['branches'] as $k => $b ) : ?>
					<button class="gyl-chip gyl-b" data-branch="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $b['label'] ); ?></button>
				<?php endforeach; ?>
			</div>
		</div>


		<?php $bd = $s['birthday']; if ( ! empty( $bd['enabled'] ) ) : ?>
		<?php if ( ! empty( $bd['url'] ) ) : ?>
			<a class="gyl-bday" href="<?php echo esc_url( $bd['url'] ); ?>" target="_blank" rel="noopener"
				onclick="if(typeof fbq==='function')fbq('track','Lead',{content_name:'יום הולדת'});">
				<b>🎂 חוגגים יום הולדת?</b>
				<span><?php echo esc_html( $bd['pitch'] ); ?> החל מ-<?php echo (int) $bd['from']; ?> ₪ · להזמנה ←</span>
			</a>
		<?php else : ?>
			<div class="gyl-bday" id="gyl-bday-cta" onclick="gylBday()">
				<b>🎂 חוגגים יום הולדת?</b>
				<span><?php echo esc_html( $bd['pitch'] ); ?> החל מ-<?php echo (int) $bd['from']; ?> ₪ · לחצו לפרטים</span>
			</div>
		<?php endif; ?>
		<?php endif; /* סוף באנר יום הולדת — רק ה-CTA מותנה */ ?>

		<div class="gyl-card gyl-flow gyl-entry gyl-hidden" id="s-date">
			<div class="gyl-h"><span class="gyl-n">2</span> מתי? ומה בא לכם?</div>
			<div class="gyl-dates" id="gyl-dates"></div>
			<div class="gyl-slots" id="gyl-slots"></div>
		</div>

		<div class="gyl-card gyl-hidden" id="s-wait">
			<div class="gyl-h">🔔 הסבב מלא — להודיע לכם אם יתפנה?</div>
			<div class="gyl-terms" id="w-slot" style="text-align:right;margin-bottom:8px"></div>
			<label>שם <input type="text" id="w-name"></label>
			<label>אימייל <input type="email" id="w-email"></label>
			<label>טלפון <input type="tel" id="w-phone"></label>
			<button class="gyl-btn" id="w-go">שמרו לי מקום ברשימה</button>
			<div class="gyl-msg" id="w-msg"></div>
		</div>

		<div class="gyl-card gyl-flow gyl-entry gyl-hidden" id="s-kids">
			<div class="gyl-h"><span class="gyl-n">3</span> כמה ילדים?</div>
			<div class="gyl-count">
				<button class="gyl-cbtn" data-d="-1">−</button>
				<span id="gyl-kids">1</span>
				<button class="gyl-cbtn" data-d="1">+</button>
			</div>
			<label class="gyl-mini">מלווים <input type="number" id="gyl-adults" min="0" max="10" value="1"></label>
		</div>

		<div class="gyl-card gyl-flow gyl-entry gyl-hidden" id="s-ticket">
			<div class="gyl-h"><span class="gyl-n">4</span> איך משלמים?</div>
			<div class="gyl-opts">
				<div class="gyl-opt" data-ticket="single">
					<b>כרטיס רגיל</b>
					<span id="p-single"></span>
				</div>
				<div class="gyl-opt gyl-best" data-ticket="buy_punch">
					<span class="gyl-badge">חוסך <?php echo (int) $disc; ?>%</span>
					<b>כרטיסייה — <?php echo (int) $s['punch_entries']; ?> כניסות</b>
					<span><?php echo (int) $s['punch_price']; ?> ₪ · <?php echo round( $s['punch_price'] / max( 1, $s['punch_entries'] ) ); ?> ₪ לכניסה</span>
					<small id="gyl-upsell"></small>
					<small style="margin-top:3px;opacity:.85">הניקוב נעשה בקבלה בכניסה.</small>
				</div>
				<?php if ( ! empty( $s['voucher_on'] ) ) : ?>
				<div class="gyl-opt" data-ticket="voucher">
					<b>🎁 יש לי שובר מיום הולדת</b>
					<span>כניסה חינם — ללא תשלום</span>
					<small>הקוד מתחיל ב-GY. נאמת אותו מיד.</small>
				</div>
				<?php endif; ?>
				<div class="gyl-opt" data-ticket="use_punch">
					<b>יש לי כרטיסייה</b>
					<span>ללא תשלום מראש</span>
					<small id="gyl-bal">הכרטיסייה תנוקב בקבלה בכניסה</small>
				</div>
			</div>
		</div>

		<div class="gyl-card gyl-flow gyl-entry gyl-hidden" id="s-details">
			<label>שם מלא <span class="gyl-req">*</span><input type="text" id="gyl-name" required></label>
			<label>טלפון <span class="gyl-req">*</span><input type="tel" id="gyl-phone" placeholder="050-0000000" required></label>
			<label>אימייל <span class="gyl-req">*</span><input type="email" id="gyl-email" required></label>
			<input type="text" id="gyl-website" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
			<?php $ad = gyl_addons_active(); if ( $ad ) : ?>
			<div class="gyl-addons">
				<div class="gyl-addh">להוסיף להזמנה?</div>
				<?php foreach ( $ad as $a ) : ?>
					<label class="gyl-addon">
						<input type="checkbox" class="gyl-ad" value="<?php echo (int) $a['i']; ?>"
							data-price="<?php echo (int) $a['price']; ?>" data-perchild="<?php echo (int) ! empty( $a['per_child'] ); ?>">
						<span><?php echo esc_html( $a['label'] ); ?></span>
						<b><?php echo (int) $a['price']; ?> ₪<?php echo ! empty( $a['per_child'] ) ? ' לילד' : ''; ?></b>
					</label>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<div class="gyl-vc gyl-hidden" id="gyl-vc">
				<label>קוד השובר <span class="gyl-req">*</span>
					<input type="text" id="gyl-vcode" placeholder="GY-12345" autocomplete="off"></label>
				<button type="button" class="gyl-vbtn" id="gyl-vcheck">בדיקת השובר</button>
				<div class="gyl-msg" id="gyl-vmsg"></div>
			</div>

			<div class="gyl-total" id="gyl-total"></div>
			<div class="gyl-hold" id="gyl-hold"></div>
			<label class="gyl-check"><input type="checkbox" id="gyl-terms">
				<span><?php echo esc_html( $s['terms_label'] ); ?>
				<?php if ( $s['terms_url'] ) : ?> — <a href="<?php echo esc_url( $s['terms_url'] ); ?>" target="_blank">לצפייה בתקנון</a><?php endif; ?>
				<span class="gyl-req">*</span></span></label>
			<p class="gyl-terms"><?php echo esc_html( $s['terms'] ); ?></p>
			<button class="gyl-btn" id="gyl-go">אישור</button>
			<div class="gyl-msg" id="gyl-msg"></div>
		</div>


		<?php if ( ! empty( $bd['enabled'] ) ) : ?>
		<div class="gyl-card gyl-hidden" id="s-bday">
			<div class="gyl-h">🎂 יום הולדת בגאיהלנד</div>
			<div class="gyl-terms" style="text-align:right;margin-bottom:10px"><?php echo esc_html( $bd['pitch'] ); ?></div>
			<label>שם <span class="gyl-req">*</span><input type="text" id="b-name" required></label>
			<label>טלפון <span class="gyl-req">*</span><input type="tel" id="b-phone" placeholder="050-0000000" required></label>
			<label>תאריך מבוקש <input type="date" id="b-date"></label>
			<label>כמה ילדים בערך? <input type="number" id="b-guests" min="0" max="60" value="15"></label>
			<input type="text" id="b-website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
			<button class="gyl-btn gyl-btn-amber" id="b-go">שלחו לי הצעה</button>
			<button class="gyl-back" onclick="gylBdayBack()">← חזרה לתיאום הגעה</button>
			<div class="gyl-msg" id="b-msg"></div>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $mom['enabled'] ) ) :
			$mbranch = $mom['branches'][0] ?? 'rishon';
			$heb = array( 'ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת' );
			$mdays = implode( ' ו', array_map( function ( $d ) use ( $heb ) { return $heb[ (int) $d ]; }, (array) $mom['days'] ) );
		?>
		<div class="gyl-card gyl-mom gyl-hidden gyl-about" id="m-about">
			<div class="gyl-h">👶 גן עם אמא</div>
			<?php echo wpautop( esc_html( $mom['desc'] ) ); ?>
			<div class="gyl-note" id="m-when"></div>
		</div>

		<div class="gyl-card gyl-mom gyl-hidden" id="m-tier">
			<div class="gyl-h"><span class="gyl-n">3</span> איזה מסלול?</div>
			<div class="gyl-opts">
				<?php foreach ( $mom['tiers'] as $k => $t ) : ?>
					<div class="gyl-opt gyl-mt<?php echo ! empty( $t['best'] ) ? ' gyl-best' : ''; ?>" data-tier="<?php echo esc_attr( $k ); ?>" data-sessions="<?php echo (int) $t['sessions']; ?>" data-per="<?php echo (int) $t['per_week']; ?>" data-price="<?php echo (int) $t['price']; ?>">
						<?php if ( ! empty( $t['best'] ) ) : ?><span class="gyl-badge">הכי משתלם</span><?php endif; ?>
						<b><?php echo esc_html( $t['label'] ); ?></b>
						<span><?php echo (int) $t['price']; ?> ₪<?php if ( $t['sessions'] > 1 ) echo ' · ' . (int) $t['sessions'] . ' מפגשים'; ?></span>
						<?php if ( $t['sessions'] > 1 ) : ?><small><?php echo round( $t['price'] / max( 1, $t['sessions'] ) ); ?> ₪ למפגש</small><?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="gyl-terms" id="m-series"></div>
		</div>

		<div class="gyl-card gyl-mom gyl-hidden" id="m-details">
			<div class="gyl-h"><span class="gyl-n">4</span> פרטים</div>
			<label>שם ההורה <span class="gyl-req">*</span><input type="text" id="m-name" required></label>
			<label>טלפון <span class="gyl-req">*</span><input type="tel" id="m-phone" placeholder="050-0000000" required></label>
			<label>אימייל <span class="gyl-req">*</span><input type="email" id="m-email" required></label>
			<label>גיל הילד/ה והערות <input type="text" id="m-notes" placeholder="למשל: בת 18 חודשים"></label>
			<div class="gyl-total" id="m-total"></div>
			<label class="gyl-check"><input type="checkbox" id="m-terms">
				<span><?php echo esc_html( $s['terms_label'] ); ?>
				<?php if ( $s['terms_url'] ) : ?> — <a href="<?php echo esc_url( $s['terms_url'] ); ?>" target="_blank">לצפייה בתקנון</a><?php endif; ?>
				<span class="gyl-req">*</span></span></label>
			<button class="gyl-btn" id="m-go">המשך לתשלום</button>
			<div class="gyl-msg" id="m-msg"></div>
		</div>
		<?php endif; ?>
	</div></div>
	<?php
	return ob_get_clean();
}

/* CSS + JS של הטופס — נטענים תמיד ב-footer, פעם אחת, ללא תלות בשורטקוד */
add_action( 'wp_footer', 'gyl_assets', 20 );
function gyl_assets() {
	// טוען רק אם יש כפתור/טופס בדף
	if ( empty( $GLOBALS['gyl_need_modal'] ) && empty( $GLOBALS['gyl_inline_used'] ) ) return;
	static $once = false; if ( $once ) return; $once = true;
	$s = gyl_get(); $disc = gyl_punch_discount();   // ה-JS למטה תלוי בהם!
	?>
	<style>
	.gyl-overlay{--sage:#7C8C63;--sage-d:#68764F;--amber:#D8A24A;--taupe:#A79684;--cream:#FAF7F0;--line:#EBE4D6;--ink:#3C3A34;
		font-family:inherit;-webkit-font-smoothing:antialiased}
	.gyl-modal{position:fixed;inset:0;background:rgba(60,58,52,.5);z-index:99999;overflow-y:auto;padding:20px 12px;
		animation:gylFade .2s ease;-webkit-overflow-scrolling:touch;overscroll-behavior:contain}
	/* שכבת הבלר נפרדת וקבועה — לא מתרנדרת מחדש בזמן גלילה (מונע גליץ') */
	.gyl-modal::before{content:"";position:fixed;inset:0;backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);
		z-index:-1;pointer-events:none}
	@keyframes gylFade{from{opacity:0}to{opacity:1}}
	@keyframes gylUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
	.gyl-wrap{max-width:500px;margin:0 auto;color:var(--ink);background:#fff;border-radius:26px;padding:20px;
		position:relative;box-shadow:0 24px 70px rgba(60,58,52,.22);animation:gylUp .28s cubic-bezier(.2,.8,.3,1);
		transform:translateZ(0)}
	.gyl-inline{width:100%!important;max-width:100%!important;display:block}
	.gyl-inline .gyl-wrap{box-shadow:none;padding:0;background:transparent;animation:none;max-width:520px;width:100%}
	/* מובייל: הכל לרוחב מלא, כפתורים וגריד נערמים */
	@media(max-width:600px){
		.gyl-inline,.gyl-inline .gyl-wrap{max-width:100%!important;width:100%!important}
		.gyl-slots{grid-template-columns:1fr 1fr!important}
		.gyl-opt{width:100%!important}
		.gyl-branches,.gyl-days{flex-wrap:wrap!important}
	}
	.gyl-x{position:absolute;top:16px;left:16px;border:0;background:#F2EEE6;border-radius:50%;width:34px;height:34px;
		cursor:pointer;font-size:14px;color:var(--taupe);transition:.15s;line-height:1}
	.gyl-x:hover{background:#E6DFD1;color:var(--ink)}
	/* ===== כפתורי גאיהלנד — חום, ובמעבר עכבר הופך לירוק ===== */
	.gyl-open-btn,.gyl-link-btn{
		display:inline-flex!important;align-items:center;justify-content:center;gap:8px;
		background:#B08A5B!important;color:#fff!important;border:0!important;border-radius:999px!important;
		padding:18px 46px!important;font-family:inherit!important;font-weight:700!important;
		font-size:1.18rem!important;line-height:1.15!important;cursor:pointer!important;
		box-shadow:0 8px 22px rgba(176,138,91,.30)!important;transition:transform .18s ease,box-shadow .18s ease,background .2s ease!important;
		text-decoration:none!important;text-align:center;letter-spacing:.2px;
		-webkit-appearance:none!important;appearance:none!important;min-height:60px!important;
		width:auto!important;max-width:100%;opacity:1!important;visibility:visible!important;
		-webkit-tap-highlight-color:transparent}
	/* hover — הופך לירוק (מנטרל גם hover של אלמנטור) */
	.gyl-open-btn:hover,.gyl-link-btn:hover,.gyl-open-btn:focus-visible,.gyl-link-btn:focus-visible,
	.elementor-widget-container .gyl-open-btn:hover,.elementor-widget-container .gyl-link-btn:hover{
		background:#7C8C63!important;transform:translateY(-2px);
		box-shadow:0 14px 30px rgba(124,140,99,.42)!important;color:#fff!important;filter:none!important}
	.gyl-open-btn:active,.gyl-link-btn:active{transform:translateY(0) scale(.99);box-shadow:0 6px 16px rgba(124,140,99,.3)!important}
	/* וריאנט אופציונלי — התחלה בירוק (אם תרצה בעתיד) */
	.gyl-btn-amber{background:#B08A5B!important}
	.gyl-btn-amber:hover{background:#7C8C63!important}
	/* מובייל — רוחב מלא */
	@media(max-width:600px){
		.gyl-open-btn,.gyl-link-btn{width:100%!important;padding:17px 26px!important;font-size:1.1rem!important}
	}
	.elementor-shortcode .gyl-open-btn,.elementor-widget-container .gyl-open-btn,
	.elementor-shortcode .gyl-link-btn,.elementor-widget-container .gyl-link-btn{background:#B08A5B!important;color:#fff!important}

	.gyl-head{display:flex;align-items:center;gap:12px;margin-bottom:14px}
	.gyl-logo{width:44px;height:44px;border-radius:14px;background:var(--cream);display:flex;align-items:center;
		justify-content:center;font-size:22px;flex:0 0 auto}
	.gyl-title{font-weight:800;font-size:1.12rem;line-height:1.2}
	.gyl-sub{font-size:.8rem;color:var(--taupe);margin-top:2px}

	.gyl-prog{display:flex;gap:5px;margin-bottom:14px}
	.gyl-prog i{flex:1;height:4px;border-radius:999px;background:var(--line);transition:.3s}
	.gyl-prog i.on{background:var(--sage)}

	.gyl-recap{display:none;background:var(--cream);border:1px solid var(--line);border-radius:14px;padding:9px 13px;
		margin-bottom:12px;font-size:.84rem;color:var(--ink);line-height:1.6}
	.gyl-recap.on{display:block;animation:gylUp .2s ease}
	.gyl-recap b{color:var(--sage)}

	.gyl-card{background:#fff;border:1px solid var(--line);border-radius:20px;padding:16px;margin-bottom:12px;
		animation:gylUp .3s ease}
	.gyl-h{font-weight:700;margin-bottom:13px;display:flex;align-items:center;gap:8px;font-size:.98rem}
	.gyl-n{width:22px;height:22px;border-radius:50%;background:var(--sage);color:#fff;font-size:.72rem;
		display:inline-flex;align-items:center;justify-content:center;font-weight:700;flex:0 0 auto}
	.gyl-hidden{display:none}

	.gyl-row{display:flex;gap:9px;flex-wrap:wrap}
	.gyl-chip{flex:1;min-width:120px;background:#fff;border:1.5px solid var(--line);border-radius:14px;padding:13px;
		cursor:pointer;font:inherit;font-weight:600;color:var(--ink);transition:.16s;text-align:center}
	.gyl-chip:hover{border-color:var(--sage);background:var(--cream)}
	.gyl-chip.active{background:var(--sage);color:#fff;border-color:var(--sage);box-shadow:0 6px 16px rgba(124,140,99,.25)}

	.gyl-dates{display:flex;gap:8px;overflow-x:auto;padding:2px 2px 10px;scrollbar-width:thin;-webkit-overflow-scrolling:touch}
	.gyl-dates::-webkit-scrollbar{height:4px}
	.gyl-dates::-webkit-scrollbar-thumb{background:var(--line);border-radius:9px}
	.gyl-date{min-width:56px;text-align:center;background:#fff;border:1.5px solid var(--line);border-radius:15px;
		padding:9px 5px;cursor:pointer;flex:0 0 auto;transition:.16s}
	.gyl-date:hover{border-color:var(--amber)}
	.gyl-date.active{background:var(--amber);color:#fff;border-color:var(--amber);box-shadow:0 6px 16px rgba(216,162,74,.3)}
	.gyl-date b{display:block;font-size:1.05rem;line-height:1.25}
	.gyl-date span{font-size:.68rem;opacity:.85}

	.gyl-slots{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px}
	.gyl-slot{background:#fff;border:1.5px solid var(--line);border-radius:14px;padding:11px 8px;text-align:center;
		cursor:pointer;font-size:.92rem;font-weight:600;transition:.16s}
	.gyl-slot:hover{border-color:var(--sage);background:var(--cream)}
	.gyl-slot.active{background:var(--sage);color:#fff;border-color:var(--sage)}
	.gyl-slot.off{opacity:.4;cursor:not-allowed;background:#F6F4EF;border-color:var(--line)}
	.gyl-slot.off:hover{border-color:var(--line);background:#F6F4EF}
	.gyl-slot small{display:block;font-size:.7rem;font-weight:400;opacity:.8;margin-top:2px}

	.gyl-count{display:flex;align-items:center;justify-content:center;gap:24px;font-size:1.7rem;font-weight:800}
	.gyl-cbtn{width:44px;height:44px;border-radius:50%;border:1.5px solid var(--sage);background:#fff;color:var(--sage);
		font-size:1.3rem;cursor:pointer;transition:.16s;line-height:1}
	.gyl-cbtn:hover{background:var(--sage);color:#fff}
	.gyl-mini{display:block;text-align:center;font-size:.82rem;margin-top:14px;color:var(--taupe)}
	.gyl-mini input{width:62px;padding:7px;border:1.5px solid var(--line);border-radius:10px;text-align:center;margin-right:6px}

	.gyl-opts{display:grid;gap:10px}
	.gyl-opt{position:relative;background:#fff;border:1.5px solid var(--line);border-radius:16px;padding:14px;
		cursor:pointer;transition:.16s}
	.gyl-opt:hover{border-color:var(--sage)}
	.gyl-opt b{display:block;margin-bottom:2px}
	.gyl-opt span{font-size:.86rem;color:var(--taupe)}
	.gyl-opt b{color:var(--ink)}
	/* טקסט הכרטיסייה (מחיר/הנחה) בצבע כהה וקריא */
	.gyl-best span{color:var(--ink)!important;font-weight:600}
	.gyl-best b{color:var(--ink)!important}
	.gyl-opt small{display:block;font-size:.75rem;color:var(--taupe);margin-top:5px;line-height:1.45}
	.gyl-opt.active{border-color:var(--sage);background:var(--cream);box-shadow:0 0 0 3px rgba(124,140,99,.14)}
	.gyl-best{border-color:var(--amber);background:#FFFDF8}
	.gyl-best:hover{border-color:var(--amber)}
	.gyl-badge{position:absolute;top:-10px;left:14px;background:var(--amber);color:#fff;font-size:.7rem;font-weight:700;
		padding:3px 10px;border-radius:999px;box-shadow:0 3px 8px rgba(216,162,74,.35)}

	.gyl-overlay label{display:block;margin:11px 0;font-size:.84rem;color:var(--taupe);font-weight:600}
	.gyl-overlay input[type=text],.gyl-overlay input[type=tel],.gyl-overlay input[type=email],.gyl-overlay input[type=number]{
		width:100%;padding:12px;border:1.5px solid var(--line);border-radius:12px;font:inherit;margin-top:5px;background:#fff;
		color:var(--ink);transition:.16s}
	.gyl-overlay input:focus{outline:0;border-color:var(--sage);box-shadow:0 0 0 3px rgba(124,140,99,.12)}
	.gyl-req{color:#B4553F;font-weight:700}
	.gyl-bad{border-color:#B4553F !important;background:#FDF6F4}

	.gyl-check{display:flex;gap:10px;align-items:flex-start;font-size:.8rem;line-height:1.5;background:var(--cream);
		border:1.5px solid var(--line);border-radius:12px;padding:12px;margin:12px 0;font-weight:400;color:var(--ink)}
	.gyl-check input{width:auto;margin:1px 0 0;flex:0 0 auto;transform:scale(1.2);accent-color:var(--sage)}
	.gyl-check a{color:var(--sage);font-weight:600}

	.gyl-total{text-align:center;font-weight:800;font-size:1.2rem;margin:14px 0 4px;color:var(--sage)}
	.gyl-terms{font-size:.74rem;color:var(--taupe);line-height:1.5;text-align:center}
	.gyl-btn{width:100%;background:var(--sage);color:#fff;border:0;border-radius:15px;padding:15px;font:inherit;
		font-weight:700;font-size:1rem;cursor:pointer;margin-top:12px;transition:.18s;
		box-shadow:0 8px 20px rgba(124,140,99,.26)}
	.gyl-btn:hover{background:var(--sage-d);box-shadow:0 11px 24px rgba(124,140,99,.32)}
	.gyl-btn:disabled{opacity:.5;box-shadow:none;cursor:default}
	.gyl-msg{margin-top:11px;font-size:.88rem;text-align:center}
	.gyl-msg.err{color:#B4553F;font-weight:600}
	.gyl-msg.ok{color:var(--sage);font-weight:700}
	.gyl-about{background:var(--cream);font-size:.87rem;line-height:1.65}
	.gyl-about p{margin:0 0 8px}
	.gyl-note{margin-top:10px;font-size:.8rem;color:var(--sage);font-weight:700;line-height:1.5}
	.gyl-proof{display:none;font-size:.78rem;color:var(--taupe);margin-bottom:10px;text-align:center}
	.gyl-proof.on{display:block}
	.gyl-proof b{color:var(--sage)}
	.gyl-addons{background:var(--cream);border:1px solid var(--line);border-radius:14px;padding:12px;margin:12px 0}
	.gyl-addh{font-size:.82rem;font-weight:700;color:var(--sage);margin-bottom:8px}
	.gyl-addon{display:flex;align-items:center;gap:9px;margin:0 0 6px;font-size:.86rem;font-weight:400;color:var(--ink);cursor:pointer}
	.gyl-addon input{width:auto;margin:0;flex:0 0 auto;transform:scale(1.15);accent-color:var(--sage)}
	.gyl-addon span{flex:1}
	.gyl-addon b{color:var(--sage);font-size:.85rem}
	.gyl-hold{display:none;text-align:center;font-size:.78rem;color:#B4553F;font-weight:700;margin-bottom:6px}
	.gyl-hold.on{display:block}
	.gyl-bday{display:block;background:linear-gradient(135deg,#FFF6E6,#FFF9F0);border:1.5px dashed var(--amber);
		border-radius:16px;padding:13px 15px;margin-bottom:12px;cursor:pointer;transition:.16s;
		text-decoration:none;color:var(--ink)}
	.gyl-bday:hover{background:#FFF3DC;transform:translateY(-1px)}
	.gyl-bday b{display:block;font-size:.96rem;margin-bottom:2px}
	.gyl-bday span{font-size:.79rem;color:var(--taupe);line-height:1.5}
	.gyl-btn-amber{background:var(--amber);box-shadow:0 8px 20px rgba(216,162,74,.3)}
	.gyl-btn-amber:hover{background:#C08F38;box-shadow:0 11px 24px rgba(216,162,74,.36)}
	.gyl-save{background:var(--amber);color:#fff;font-size:.7rem;font-weight:700;border-radius:8px;padding:1px 7px;margin-right:5px;white-space:nowrap}
	.gyl-upline{display:block;line-height:1.9}
	.gyl-vc{background:var(--cream);border:1.5px solid var(--amber);border-radius:14px;padding:13px;margin:12px 0}
	.gyl-vc input{text-transform:uppercase;letter-spacing:1px;font-weight:700;text-align:center}
	.gyl-vbtn{width:100%;background:var(--amber);color:#fff;border:0;border-radius:11px;padding:11px;font:inherit;
		font-weight:700;cursor:pointer;margin-top:8px}
	.gyl-vbtn:disabled{opacity:.5}
	.gyl-vok{border-color:var(--sage);background:#F1F5EC}
	.gyl-low{color:#B4553F !important;font-weight:700}
	.gyl-vch{border-color:#8A6A4A;background:#FBF7F2}
	.gyl-vch input{border:1.5px solid #E0D8C8;border-radius:10px;padding:9px;width:100%;font:inherit;text-align:center;letter-spacing:1px;font-weight:700}
	.gyl-momslot{grid-column:1/-1;border-color:var(--amber);background:#FFFDF8;font-weight:700}
	.gyl-momslot:hover{border-color:var(--amber);background:#FFF8EA}
	.gyl-momslot.active{background:var(--amber);color:#fff;border-color:var(--amber)}
	.gyl-back{background:none;border:0;color:var(--taupe);font:inherit;font-size:.82rem;cursor:pointer;
		margin-top:6px;padding:6px;width:100%;text-align:center}
	.gyl-back:hover{color:var(--sage)}
	@media(max-width:420px){.gyl-wrap{padding:16px;border-radius:22px}.gyl-slots{grid-template-columns:1fr}}
	</style>

	<script>
	function gylOpen(){
		var ov=document.getElementById('gyl-overlay');
		if(!ov){ return; }
		ov.style.display='block';                 // הכי חשוב — פותחים קודם
		try{
			window.__gylScrollY = window.scrollY || window.pageYOffset || 0;
			document.body.style.position='fixed';
			document.body.style.top=(-window.__gylScrollY)+'px';
			document.body.style.left='0';
			document.body.style.right='0';
			document.body.style.width='100%';
			document.body.style.overflow='hidden';
		}catch(e){ /* אם נעילת הגלילה נכשלה — לא נורא, הפופ-אפ עדיין פתוח */ }
	}
	window.gylOpen=gylOpen;
	function gylClose(){
		var ov=document.getElementById('gyl-overlay');
		if(ov) ov.style.display='none';
		try{
			document.body.style.position='';
			document.body.style.top='';
			document.body.style.left='';
			document.body.style.right='';
			document.body.style.width='';
			document.body.style.overflow='';
			window.scrollTo(0, window.__gylScrollY||0);
		}catch(e){}
	}
	window.gylClose=gylClose;
	// מאזין גיבוי — תופס לחיצות על הכפתור גם אם ה-onclick נכשל (ריבוי כפתורים/סקופ)
	document.addEventListener('click',function(e){
		var b=e.target.closest && e.target.closest('.gyl-open-btn');
		if(b){ e.preventDefault(); gylOpen(); }
	});
	function gylInit(){
	 if(!document.getElementById('gyl-overlay')){ return setTimeout(gylInit,120); }  // מחכה שהפופ-אפ יהיה ב-DOM
	 if(window.__gylInited) return; window.__gylInited=true;
	 const API='<?php echo esc_url_raw( rest_url( 'gayaland/v1' ) ); ?>', NONCE='<?php echo wp_create_nonce( 'wp_rest' ); ?>';
	 const DAYS=<?php echo (int) $s['days_ahead']; ?>, PUNCH_PRICE=<?php echo (int) $s['punch_price']; ?>, MAXK=<?php echo (int) $s['max_children']; ?>;
	 const HE=['א','ב','ג','ד','ה','ו','ש'];
	 let st={branch:null,date:null,slot:null,kids:1,price:0,ticket:null};
	 const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
	 const iso=d=>new Date(d.getTime()-d.getTimezoneOffset()*6e4).toISOString().slice(0,10);
	 const show=id=>{const el=$(id);if(el)el.classList.remove('gyl-hidden');};
	 const BRL={<?php $o=array(); foreach($s['branches'] as $k=>$b) $o[]="'".esc_js($k)."':'".esc_js($b['label'])."'"; echo implode(',', $o); ?>};
	 function prog(n){ $$('.gyl-prog i').forEach(function(i){ i.classList.toggle('on', +i.dataset.p<=n); }); }
	 function recap(){
		const r=$('#gyl-recap'); const bits=[];
		if(st.branch) bits.push('<b>'+BRL[st.branch]+'</b>');
		if(st.date){ const d=new Date(st.date+'T12:00'); bits.push(d.getDate()+'/'+(d.getMonth()+1)); }
		if(st.slot) bits.push(st.slot);
		if(st.slot) bits.push(st.kids+' ילדים');
		if(!bits.length){ r.classList.remove('on'); return; }
		r.innerHTML=bits.join('  ·  '); r.classList.add('on');
	 }

	 $$('.gyl-b').forEach(b=>b.onclick=()=>{
		$$('.gyl-b').forEach(x=>x.classList.remove('active'));b.classList.add('active');
		st.branch=b.dataset.branch;show('#s-date');dates();prog(2);recap();
	 });

	 function dates(){
		const w=$('#gyl-dates');w.innerHTML='';$('#gyl-slots').innerHTML='';
		const t=new Date();t.setHours(12,0,0,0);
		for(let i=0;i<DAYS;i++){const d=new Date(t);d.setDate(t.getDate()+i);
			const e=document.createElement('div');e.className='gyl-date';
			e.innerHTML='<b>'+d.getDate()+'</b><span>'+HE[d.getDay()]+'׳ '+(d.getMonth()+1)+'</span>';
			e.onclick=()=>{$$('.gyl-date').forEach(x=>x.classList.remove('active'));e.classList.add('active');st.date=iso(d);slots();recap();};
			w.appendChild(e);}
	 }

	 async function slots(){
		const w=$('#gyl-slots');w.innerHTML='טוען…';hideMom();hideEntry();
		const d=await(await fetch(API+'/availability?branch='+st.branch+'&date='+st.date)).json();
		st.price=d.price;MOMD=d.mom;w.innerHTML='';
		if(d.day_block){w.innerHTML='<div class="gyl-terms">סגור — '+d.day_block+'</div>';return;}
		if(d.mom){
			const e=document.createElement('div');
			const open=d.mom.left>0;
			e.className='gyl-slot gyl-momslot'+(open?'':' off'); e.id='m-slot';
			e.innerHTML='👶 גן עם אמא<small>'+d.mom.from+'–'+d.mom.to+' · '+(open?'נותרו '+d.mom.left:'מלא')+'</small>';
			if(open) e.onclick=pickMom;
			w.appendChild(e);
		}
		if(!d.slots.length && !d.mom){w.innerHTML='<div class="gyl-terms">אין סבבים ביום זה.</div>';return;}
		d.slots.forEach(s=>{const e=document.createElement('div');const open=s.status==='open';
			e.className='gyl-slot'+(open?'':' off');
			const n=s.status==='blocked'?(s.reason||'סגור'):s.status==='full'?'מלא':s.status==='past'?'עבר':'נותרו '+s.left;
			e.innerHTML='🎪 '+s.start+'–'+s.end+'<small>'+n+'</small>';
			if(open)e.onclick=()=>{hideMom();$('#s-wait').classList.add('gyl-hidden');
				$$('.gyl-slot').forEach(x=>x.classList.remove('active'));e.classList.add('active');
				st.slot=s.start;show('#s-kids');show('#s-ticket');price();prog(3);recap();
				$('#s-kids').scrollIntoView({behavior:'smooth',block:'center'});};
			else if(s.status==='full' && WAITLIST) e.onclick=()=>{hideMom();hideEntry();
				$$('.gyl-slot').forEach(x=>x.classList.remove('active'));e.classList.add('active');
				st.slot=s.start;
				$('#w-slot').textContent='הסבב '+s.start+'–'+s.end+' ביום שנבחר מלא. נעדכן אתכם ראשונים אם יתפנה מקום.';
				$('#s-wait').classList.remove('gyl-hidden');
				$('#s-wait').scrollIntoView({behavior:'smooth',block:'center'});};
			w.appendChild(e);});
	 }

	 $$('.gyl-cbtn').forEach(b=>b.onclick=()=>{
		st.kids=Math.max(1,Math.min(MAXK,st.kids+ +b.dataset.d));$('#gyl-kids').textContent=st.kids;price();recap();});

	 $$('.gyl-opt').forEach(o=>o.onclick=()=>{
		$$('.gyl-opt').forEach(x=>x.classList.remove('active'));o.classList.add('active');
		st.ticket=o.dataset.ticket;show('#s-details');vcToggle();price();prog(4);startHold();
		if(typeof fbq==='function')fbq('track','AddToCart',{value:st.ticket==='buy_punch'?PUNCH_PRICE:st.price*st.kids,currency:'ILS'});
		$('#gyl-go').textContent = (st.ticket==='use_punch'||st.ticket==='voucher') ? 'אישור הגעה' : 'המשך לתשלום';
		$('#s-details').scrollIntoView({behavior:'smooth',block:'center'});});

	 let VCH=null;   // שובר שאומת
	 async function checkVoucher(){
		const el=$('#gyl-vcode'), m=$('#gyl-vmsg'); if(!el) return false;
		const code=(el.value||'').trim().toUpperCase();
		if(!code){ m.className='gyl-msg err'; m.textContent='הזינו את קוד השובר'; return false; }
		m.className='gyl-msg'; m.textContent='בודק…';
		try{
			const r=await fetch(API+'/voucher/check',{method:'POST',
				headers:{'Content-Type':'application/json','X-WP-Nonce':NONCE},body:JSON.stringify({code:code})});
			if(!(r.headers.get('content-type')||'').includes('json')) throw new Error('השרת אינו זמין כרגע. נסו שוב בעוד רגע.');
			const d=await r.json();
			if(!r.ok) throw new Error(d.message||'השובר לא תקף');
			VCH=code; m.className='gyl-msg ok'; m.textContent=d.message; price(); return true;
		}catch(e){ VCH=null; m.className='gyl-msg err'; m.textContent=e.message; price(); return false; }
	 }
	 if($('#gyl-vcode')) $('#gyl-vcode').addEventListener('blur',checkVoucher);

	 function price(){
		const single = st.price*st.kids;
		$('#p-single').textContent = st.price+' ₪ × '+st.kids+' = '+single+' ₪';

		// המתמטיקה שמוכרת את הכרטיסייה — מחיר לביקור הזה + מה שנשאר
		const perEntry = Math.round(PUNCH_PRICE/ENTRIES);
		const thisVisit = perEntry*st.kids;
		const saveNow = single - thisVisit;
		const leftAfter = ENTRIES - st.kids;
		const up=$('#gyl-upsell');
		if(up) up.innerHTML =
			'<span class="gyl-upline">הביקור הזה: <b>'+thisVisit+' ₪</b> במקום '+single+' ₪'+
			(saveNow>0?' <span class="gyl-save">חוסכים '+saveNow+' ₪ היום</span>':'')+'</span>'+
			'<span class="gyl-upline">נשארות <b>'+leftAfter+' כניסות</b> לביקורים הבאים</span>';

		if(!st.ticket)return;
		const ad = addonsTotal();
		const adTxt = ad? ' + '+ad+' ₪ תוספות' : '';
		const base = st.ticket==='single' ? single : st.ticket==='buy_punch' ? PUNCH_PRICE : 0;
		if(st.ticket==='voucher'){
			$('#gyl-total').textContent = VOK ? '🎟️ כניסה חינם עם השובר' : 'הזינו את קוד השובר ובדקו אותו';
		}else{
			$('#gyl-total').textContent = st.ticket==='use_punch'
				? 'ללא תשלום מראש · ניקוב '+st.kids+' בקבלה' + (ad? ' · תוספות: '+ad+' ₪':'')
				: 'לתשלום: '+(base+ad)+' ₪'+(ad?' ('+base+' ₪'+adTxt+')':'');
		}
	 }

	 if($('#gyl-phone')) $('#gyl-phone').addEventListener('blur',async e=>{
		if(st.ticket!=='use_punch'||!e.target.value||!<?php echo (int) $s['auto_punch']; ?>)return;
		try{ const d=await(await fetch(API+'/balance?phone='+encodeURIComponent(e.target.value))).json();
		const bal=$('#gyl-bal'); if(bal) bal.textContent='יתרה: '+d.balance+' כניסות'; }catch(_){}});

	 /* ---------- הוכחה חברתית ---------- */
	 const PROOF = <?php echo empty( $s['social_proof'] ) ? 0 : 1; ?>;
	 const HOLDMIN = <?php echo (int) $s['hold_minutes']; ?>;
	 const HOLDON = <?php echo empty( $s['hold_timer'] ) ? 0 : 1; ?>;
	 const ENTRIES = <?php echo (int) $s['punch_entries']; ?>;
	 if(PROOF) fetch(API+'/stats').then(r=>r.json()).then(function(d){
		const bits=[];
		if(d.visits>20) bits.push('<b>'+d.visits+'</b> ילדים ביקרו אצלנו החודש');
		if(d.reviews>=5) bits.push('⭐ <b>'+d.rating+'</b> מתוך 5 ('+d.reviews+' משפחות)');
		if(bits.length){ const p=$('#gyl-proof'); p.innerHTML=bits.join(' · '); p.classList.add('on'); }
	 });

	 /* ---------- שעון שמירת מקום ---------- */
	 let holdT=null;
	 function startHold(){
		if(!HOLDON) return;
		clearInterval(holdT);
		let left=HOLDMIN*60;
		const el=$('#gyl-hold'); el.classList.add('on');
		holdT=setInterval(function(){
			left--;
			if(left<=0){ clearInterval(holdT); el.textContent='⏱ שמירת המקום פגה — רעננו את העמוד'; return; }
			const m=Math.floor(left/60), sec=('0'+(left%60)).slice(-2);
			el.textContent='⏱ שמרנו לכם את המקום ל-'+m+':'+sec+' דקות';
		},1000);
	 }

	 /* ---------- תוספות ---------- */
	 function addonsTotal(){
		let sum=0;
		$$('.gyl-ad').forEach(function(a){ if(a.checked) sum += (+a.dataset.price) * (+a.dataset.perchild ? st.kids : 1); });
		return sum;
	 }
	 function addonIds(){
		const ids=[]; $$('.gyl-ad').forEach(function(a){ if(a.checked) ids.push(+a.value); }); return ids;
	 }
	 $$('.gyl-ad').forEach(function(a){ a.onchange=price; });

	 /* ---------- יום הולדת ---------- */
	 function gylBdayOpen(){
		$$('.gyl-card').forEach(c=>c.classList.add('gyl-hidden'));
		const bd=$('#s-bday');if(bd){bd.classList.remove('gyl-hidden');bd.scrollIntoView({behavior:'smooth',block:'center'});}
	 }
	 window.gylBday=gylBdayOpen;
	 window.gylBdayBack=function(){
		const bd=$('#s-bday');if(bd)bd.classList.add('gyl-hidden');
		const br=$('#s-branch');if(br){br.classList.remove('gyl-hidden');br.scrollIntoView({behavior:'smooth',block:'center'});}
		if(st.branch){const sd=$('#s-date');if(sd)sd.classList.remove('gyl-hidden');}
	 };
	 if($('#b-go')) $('#b-go').onclick=async()=>{
		const b=$('#b-go'), m=$('#b-msg'); m.className='gyl-msg'; m.textContent='';
		const body={branch:st.branch||'',name:$('#b-name').value.trim(),phone:$('#b-phone').value.trim(),
			email:'',date:$('#b-date').value,guests:+$('#b-guests').value,notes:'',website:$('#b-website').value};
		if(body.name.length<2||!/^0\d{8,9}$/.test(body.phone.replace(/\D/g,''))){
			m.className='gyl-msg err'; m.textContent='נא למלא שם וטלפון תקין'; return; }
		b.disabled=true;b.textContent='רגע…';
		try{
			const r=await fetch(API+'/lead',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':NONCE},body:JSON.stringify(body)});
			const d=await r.json(); if(!r.ok) throw new Error(d.message||'שגיאה');
			if(typeof fbq==='function') fbq('track','Lead',{content_name:'יום הולדת'});
			m.className='gyl-msg ok'; m.textContent=d.message; b.style.display='none';
		}catch(e){ m.className='gyl-msg err'; m.textContent=e.message; b.disabled=false; b.textContent='נסו שוב'; }
	 };

	 /* ---------- שובר אורח ---------- */
	 let VOK=false;
	 function vcToggle(){
		const box=$('#gyl-vc'); if(!box) return;
		if(st.ticket==='voucher'){ box.classList.remove('gyl-hidden'); }
		else { box.classList.add('gyl-hidden'); VOK=false; }
	 }
	 if($('#gyl-vcheck')) $('#gyl-vcheck').onclick=async()=>{
		const b=$('#gyl-vcheck'), m=$('#gyl-vmsg'), code=$('#gyl-vcode').value.trim().toUpperCase();
		m.className='gyl-msg'; m.textContent='';
		if(!/^GY-?[A-Z0-9]{4,10}$/.test(code)){ m.className='gyl-msg err'; m.textContent='קוד לא תקין'; return; }
		b.disabled=true; b.textContent='בודק…';
		try{
			const r=await fetch(API+'/voucher/check?code='+encodeURIComponent(code));
			const ct=(r.headers.get('content-type')||'');
			if(!ct.includes('json')){ throw new Error('השרת אינו זמין כרגע. נסו שוב בעוד רגע.'); }
			const d=await r.json();
			if(!r.ok) throw new Error(d.message||'השובר אינו תקף');
			VOK=true; $('#gyl-vc').classList.add('gyl-vok');
			m.className='gyl-msg ok'; m.textContent='✓ '+d.message+(d.from?' (מהאירוע של '+d.from+')':'');
			b.style.display='none'; price();
		}catch(e){ VOK=false; m.className='gyl-msg err'; m.textContent=e.message; b.disabled=false; b.textContent='בדיקה שוב'; }
	 };

	 /* ---------- רשימת המתנה ---------- */
	 const WAITLIST = <?php echo empty( $s['waitlist'] ) ? 0 : 1; ?>;
	 if($('#w-go')) $('#w-go').onclick=async()=>{
		const b=$('#w-go'), m=$('#w-msg'); m.className='gyl-msg'; m.textContent='';
		const body={branch:st.branch,date:st.date,start:st.slot,children:st.kids||1,
			name:$('#w-name').value.trim(),phone:$('#w-phone').value.trim(),email:$('#w-email').value.trim()};
		if(body.name.length<2||!/^\S+@\S+\.\S+$/.test(body.email)){m.className='gyl-msg err';m.textContent='נא למלא שם ואימייל תקין';return;}
		b.disabled=true;b.textContent='רגע…';
		try{
			const r=await fetch(API+'/waitlist',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':NONCE},body:JSON.stringify(body)});
			const d=await r.json(); if(!r.ok) throw new Error(d.message||'שגיאה');
			m.className='gyl-msg ok'; m.textContent=d.message; b.style.display='none';
		}catch(e){ m.className='gyl-msg err'; m.textContent=e.message; b.disabled=false; b.textContent='נסו שוב'; }
	 };

	 /* ---------- גן עם אמא — נבחר מתוך היום ---------- */
	 let MOMD=null, MOMALL=null, mst={tier:null,sessions:1,per:1,price:0};
	 const hideMom=()=>$$('.gyl-mom').forEach(c=>c.classList.add('gyl-hidden'));
	 const hideEntry=()=>['#s-kids','#s-ticket','#s-details','#s-wait'].forEach(id=>{const el=$(id);if(el)el.classList.add('gyl-hidden');});

	 function pickMom(){
		st.slot=null; hideEntry();
		$$('.gyl-slot').forEach(x=>x.classList.remove('active'));
		const ms=$('#m-slot'); if(ms) ms.classList.add('active');
		$('#m-about').classList.remove('gyl-hidden');
		$('#m-tier').classList.remove('gyl-hidden');
		const d=new Date(st.date+'T12:00');
		$('#m-when').textContent='📅 '+d.getDate()+'/'+(d.getMonth()+1)+' · '+MOMD.from+'–'+MOMD.to+' · נותרו '+MOMD.left+' מקומות מתוך '+MOMD.capacity+' · '+MOMD.age;
		prog(3); recap();
		if(!MOMALL) fetch(API+'/mom').then(r=>r.json()).then(d=>{MOMALL=d;momSeries();});
		$('#m-about').scrollIntoView({behavior:'smooth',block:'center'});
	 }

	 $$('.gyl-mt').forEach(o=>o.onclick=()=>{
		$$('.gyl-mt').forEach(x=>x.classList.remove('active')); o.classList.add('active');
		mst.tier=o.dataset.tier; mst.sessions=+o.dataset.sessions; mst.per=+o.dataset.per; mst.price=+o.dataset.price;
		momSeries(); momTotal(); $('#m-details').classList.remove('gyl-hidden'); prog(4);
		$('#m-details').scrollIntoView({behavior:'smooth',block:'center'});
	 });

	 function momSeries(){
		const el=$('#m-series'); if(!el||!mst.tier||!st.date) return;
		if(mst.sessions<=1){ const d=new Date(st.date+'T12:00');
			el.textContent='מפגש אחד — '+d.getDate()+'/'+(d.getMonth()+1); return; }
		if(!MOMALL){ el.textContent=''; return; }
		let all=MOMALL.sessions.map(x=>x.date).filter(d=>d>=st.date);
		if(mst.per===1){ const dow=new Date(st.date+'T12:00').getDay();
			all=all.filter(d=>new Date(d+'T12:00').getDay()===dow); }
		el.innerHTML='המפגשים שלכם: '+all.slice(0,mst.sessions).map(d=>{const x=new Date(d+'T12:00');return x.getDate()+'/'+(x.getMonth()+1);}).join(' · ');
	 }
	 function momTotal(){ if(mst.tier) $('#m-total').textContent='לתשלום: '+mst.price+' ₪'+(mst.sessions>1?' · '+mst.sessions+' מפגשים':''); }

	 if($('#m-go')) $('#m-go').onclick=async()=>{
		const b=$('#m-go'), msg=$('#m-msg'); msg.className='gyl-msg'; msg.textContent='';
		$$('.gyl-bad').forEach(x=>x.classList.remove('gyl-bad'));
		const body={branch:st.branch,tier:mst.tier,start:st.date,children:1,
			name:$('#m-name').value.trim(),phone:$('#m-phone').value.trim(),
			email:$('#m-email').value.trim(),notes:$('#m-notes').value.trim(),
			terms:$('#m-terms').checked?1:0};
		const bad=(sel,t)=>{$(sel).classList.add('gyl-bad');msg.className='gyl-msg err';msg.textContent=t;};
		if(!mst.tier){msg.className='gyl-msg err';msg.textContent='בחרו מסלול';return;}
		if(body.name.length<2){bad('#m-name','נא למלא שם');return;}
		if(!/^0\d{8,9}$/.test(body.phone.replace(/\D/g,''))){bad('#m-phone','טלפון לא תקין');return;}
		if(!/^\S+@\S+\.\S+$/.test(body.email)){bad('#m-email','אימייל לא תקין');return;}
		if(!body.terms){bad('.gyl-check','יש לאשר את התקנון');return;}
		b.disabled=true;b.textContent='רגע…';
		try{
			const r=await fetch(API+'/mom/book',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':NONCE},body:JSON.stringify(body)});
			const d=await r.json(); if(!r.ok) throw new Error(d.message||'שגיאה');
			if(typeof fbq==='function') fbq('track','InitiateCheckout',{value:mst.price,currency:'ILS',content_name:'גן עם אמא'});
			location=d.redirect;
		}catch(e){ msg.className='gyl-msg err'; msg.textContent=e.message; b.disabled=false; b.textContent='נסו שוב'; }
	 };

	 $('#gyl-go').onclick=async()=>{
		const b=$('#gyl-go'),m=$('#gyl-msg');m.className='gyl-msg';m.textContent='';
		const body={branch:st.branch,date:st.date,start:st.slot,ticket:st.ticket,children:st.kids,
			adults:+$('#gyl-adults').value,name:$('#gyl-name').value.trim(),phone:$('#gyl-phone').value.trim(),
			email:$('#gyl-email').value.trim(),terms:$('#gyl-terms').checked?1:0,
			website:$('#gyl-website')?$('#gyl-website').value:'',addons:addonIds(),
			voucher:$('#gyl-vcode')?$('#gyl-vcode').value.trim().toUpperCase():'',notes:''};
		if(st.ticket==='voucher' && !VOK){ msg.className='gyl-msg err'; msg.textContent='בדקו את השובר לפני האישור'; return; }
		$$('.gyl-bad').forEach(x=>x.classList.remove('gyl-bad'));
		const bad=(sel,txt)=>{$(sel).classList.add('gyl-bad');m.className='gyl-msg err';m.textContent=txt;return false;};
		if(body.name.length<2)return bad('#gyl-name','נא למלא שם מלא')&&0;
		if(!/^0\d{8,9}$/.test(body.phone.replace(/\D/g,'')))return bad('#gyl-phone','מספר טלפון לא תקין')&&0;
		if(!/^\S+@\S+\.\S+$/.test(body.email))return bad('#gyl-email','כתובת אימייל לא תקינה')&&0;
		if(!body.terms)return bad('.gyl-check','יש לאשר את התקנון ותנאי השימוש')&&0;
		b.disabled=true;b.textContent='רגע…';
		try{const r=await fetch(API+'/book',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':NONCE},body:JSON.stringify(body)});
			const d=await r.json(); if(!r.ok)throw new Error(d.message||'שגיאה');
			if(d.mode==='pay'){
				if(typeof fbq==='function')fbq('track','InitiateCheckout',{value:st.ticket==='buy_punch'?PUNCH_PRICE:st.price*st.kids,currency:'ILS',num_items:st.kids});
				location=d.redirect;
			}
			else{m.className='gyl-msg ok';m.textContent=d.message;b.style.display='none';}
		}catch(e){m.className='gyl-msg err';m.textContent=e.message;b.disabled=false;b.textContent='נסו שוב';}
	 };
	}
	if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',gylInit);}else{gylInit();}
	</script>
	<?php
}

/** בדיקת תקינות — מוצג בדפי גאיהלנד בלבד */
add_action( 'admin_notices', function () {
	$scr = get_current_screen();
	if ( ! $scr || false === strpos( $scr->id, 'gyl' ) ) return;
	$s = gyl_get(); $bad = array();

	if ( ! class_exists( 'WooCommerce' ) ) {
		$bad[] = 'ווקומרס לא פעיל — לא ניתן לגבות תשלום.';
	} else {
		foreach ( $s['branches'] as $k => $b ) {
			$p = gyl_product_for( 'entry', $k );
			if ( ! $p || ! wc_get_product( $p ) ) $bad[] = 'לא הוגדר מוצר ווקומרס לכניסה — ' . $b['label'] . '.';
		}
		if ( ! empty( $s['mom']['enabled'] ) ) {
			foreach ( $s['mom']['tiers'] as $tk => $tt )
				if ( ! (int) $tt['product'] ) $bad[] = 'גן עם אמא — לא הוגדר מוצר למסלול "' . $tt['label'] . '".';
		}
		if ( 'yes' !== get_option( 'woocommerce_enable_guest_checkout' ) )
			$bad[] = 'רכישה כאורח כבויה בווקומרס — לקוחות ייאלצו לפתוח חשבון. מומלץ להפעיל (ווקומרס ← הגדרות ← חשבונות).';
	}
	if ( ! wp_next_scheduled( 'gyl_cron' ) )
		$bad[] = 'משימת התזכורות האוטומטיות לא רצה — כבו והפעילו מחדש את התוסף.';

	if ( ! $bad ) return;
	echo '<div class="notice notice-warning"><p><b>גאיהלנד — דברים שדורשים תשומת לב:</b></p><ul style="margin-right:18px;list-style:disc">';
	foreach ( $bad as $x ) echo '<li>' . esc_html( $x ) . '</li>';
	echo '</ul></div>';
} );

/* ===========================================================
 * עיצוב אחיד לכל דפי הניהול
 * =========================================================== */
add_action( 'admin_head', function () {
	$scr = get_current_screen();
	if ( ! $scr || false === strpos( $scr->id, 'gyl' ) ) return;
	?><style>
	.gyl-adm{--sage:#7C8C63;--sage-d:#68764F;--amber:#D8A24A;--taupe:#A79684;--cream:#FAF7F0;--line:#EBE4D6;--ink:#3C3A34;--red:#B4553F}
	.gyl-adm h1{display:flex;align-items:center;gap:10px;font-weight:700}
	.gyl-stats{display:flex;gap:12px;flex-wrap:wrap;margin:16px 0}
	.gyl-stat{background:#fff;border:1px solid var(--line);border-radius:16px;padding:14px 18px;min-width:130px;flex:1}
	.gyl-stat .n{font-size:1.7rem;font-weight:800;color:var(--sage);line-height:1.2}
	.gyl-stat .l{font-size:.78rem;color:var(--taupe);margin-top:2px}
	.gyl-stat.amber .n{color:var(--amber)}
	.gyl-nav{display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:#fff;border:1px solid var(--line);
		border-radius:14px;padding:10px 12px;margin-bottom:14px}
	.gyl-nav a,.gyl-nav button{background:#fff;border:1.5px solid var(--line);border-radius:10px;padding:7px 14px;
		text-decoration:none;color:var(--ink);font-weight:600;cursor:pointer;font-size:13px}
	.gyl-nav a:hover{border-color:var(--sage);color:var(--sage)}
	.gyl-nav a.on{background:var(--sage);color:#fff;border-color:var(--sage)}
	.gyl-nav .today{font-weight:700;font-size:15px;margin:0 6px}
	.gyl-round{background:#fff;border:1px solid var(--line);border-radius:16px;margin-bottom:12px;overflow:hidden}
	.gyl-round-h{background:var(--cream);padding:10px 14px;display:flex;justify-content:space-between;align-items:center;
		border-bottom:1px solid var(--line)}
	.gyl-round-h b{font-size:15px}
	.gyl-cap{font-size:12px;color:var(--taupe)}
	.gyl-bar{height:6px;background:var(--line);border-radius:9px;width:110px;overflow:hidden;display:inline-block;
		vertical-align:middle;margin-right:6px}
	.gyl-bar i{display:block;height:100%;background:var(--sage)}
	.gyl-bar.full i{background:var(--red)}
	.gyl-guest{display:flex;align-items:center;gap:10px;padding:9px 14px;border-bottom:1px solid #F4F1E8;font-size:13px}
	.gyl-guest:last-child{border:0}
	.gyl-guest .nm{font-weight:600;min-width:130px}
	.gyl-guest .ph{color:var(--taupe);min-width:110px}
	.gyl-guest .kids{background:var(--cream);border-radius:8px;padding:2px 8px;font-weight:700}
	.gyl-guest .sp{flex:1}
	.gyl-tag{font-size:11px;border-radius:8px;padding:2px 8px;font-weight:600}
	.gyl-tag.ok{background:#EAF0E2;color:var(--sage-d)}
	.gyl-tag.in{background:var(--sage);color:#fff}
	.gyl-tag.pend{background:#FBF0DC;color:#8A6520}
	.gyl-tag.punch{background:#F3EDE4;color:var(--taupe)}
	.gyl-tag.extras{background:#FBF0DC;color:#8A6520;font-weight:700}
	.gyl-tag.redeem{text-decoration:none;cursor:pointer;border:1.5px dashed #D8A24A}
	.gyl-tag.redeem:hover{background:#D8A24A;color:#fff}
	.gyl-tag.given{background:#EAF0E2;color:#68764F;font-weight:700}
	.undo{color:#A79684;text-decoration:none;font-size:12px}
	.gyl-empty{padding:12px 14px;color:var(--taupe);font-size:13px}
	.gyl-blocked{background:#FBEDE9;color:var(--red);padding:10px 14px;font-weight:600;font-size:13px}
	.gyl-btn-s{background:var(--sage);color:#fff;border:0;border-radius:9px;padding:5px 12px;font-size:12px;
		text-decoration:none;cursor:pointer;font-weight:600}
	.gyl-btn-s:hover{background:var(--sage-d);color:#fff}
	.gyl-btn-o{background:#fff;border:1.5px solid var(--line);color:var(--taupe);border-radius:9px;padding:4px 10px;
		font-size:12px;text-decoration:none}
	.gyl-btn-o:hover{border-color:var(--red);color:var(--red)}
	.gyl-help{background:#FFFDF6;border:1px solid #F0E4C8;border-radius:12px;padding:12px 14px;font-size:13px;
		color:#6B5A2E;margin-bottom:14px;line-height:1.6}
	@media print{.gyl-nav,.gyl-stats,#adminmenumain,#wpadminbar,#wpfooter,.gyl-btn-s,.gyl-btn-o{display:none!important}
		#wpcontent{margin:0!important;padding:0!important}}
	</style><?php
} );



/* ===========================================================
 * רשימת המתנה + קובץ יומן (.ics) + ייצוא CSV
 * =========================================================== */
function gyl_waitlist_ping( $branch, $date, $start ) {
	global $wpdb; $s = gyl_get();
	if ( empty( $s['waitlist'] ) ) return;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}gyl_waitlist
		 WHERE branch=%s AND slot_date=%s AND slot_start=%s AND notified=0 ORDER BY created_at LIMIT 20",
		$branch, $date, $start . ':00' ), ARRAY_A );
	if ( ! $rows ) return;

	$avail = gyl_availability( $branch, $date );
	$left = 0;
	foreach ( $avail['slots'] as $sl ) if ( $sl['start'] === $start ) $left = (int) $sl['left'];
	if ( $left < 1 ) return;

	$lbl = $s['branches'][ $branch ]['label'] ?? $branch;
	foreach ( $rows as $r ) {
		if ( $left < (int) $r['children'] ) continue;
		gyl_mail( $r['email'], 'התפנה מקום בגאיהלנד! 🌿',
			"היי {$r['name']},\n\nהתפנה מקום בסבב שחיכיתם לו:\n$lbl · " .
			date_i18n( 'j/n/Y', strtotime( $date ) ) . " · $start\n\n" .
			"המקום נתפס לפי סדר הזמנה — כדאי למהר:\n" . ( $s['book_url'] ?: home_url() ) . "\n\nגאיהלנד", true );
		$wpdb->update( "{$wpdb->prefix}gyl_waitlist", array( 'notified' => 1 ), array( 'id' => $r['id'] ) );
	}
}

add_action( 'rest_api_init', function () {
/* --- מסירת תוספת (קפה/גרביים) מהדשבורד --- */
	register_rest_route( 'gayaland/v1', '/extras/redeem', array(
		'methods' => 'POST',
		'permission_callback' => function ( $r ) {
			$k = $r->get_header( 'x-gyl-key' );
			return $k && hash_equals( (string) gyl_get( 'api_key' ), (string) $k );
		},
		'callback' => function ( $r ) {
			global $wpdb;
			$bid = (int) $r['booking_id'];
			$b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gyl_bookings WHERE id=%d", $bid ), ARRAY_A );
			if ( ! $b )              return new WP_Error( 'nf', 'הזמנה לא נמצאה', array( 'status' => 404 ) );
			if ( empty( $b['extras'] ) ) return new WP_Error( 'no', 'אין תוספות בהזמנה הזו', array( 'status' => 400 ) );
			if ( ! empty( $b['extras_at'] ) )
				return new WP_Error( 'dup', 'כבר נמסר ב-' . date_i18n( 'j/n H:i', strtotime( $b['extras_at'] ) ), array( 'status' => 409 ) );

			$undo = ! empty( $r['undo'] );
			$wpdb->update( "{$wpdb->prefix}gyl_bookings",
				array( 'extras_at' => $undo ? null : current_time( 'mysql' ) ), array( 'id' => $bid ) );
			return rest_ensure_response( array( 'ok' => true, 'extras' => $b['extras'],
				'at' => $undo ? null : current_time( 'mysql' ) ) );
		},
	) );

	register_rest_route( 'gayaland/v1', '/waitlist', array(
		'methods' => 'POST', 'permission_callback' => '__return_true',
		'callback' => function ( $req ) {
			global $wpdb; $s = gyl_get();
			if ( empty( $s['waitlist'] ) ) return new WP_Error( 'off', 'רשימת ההמתנה כבויה', array( 'status' => 400 ) );
			$branch = sanitize_key( $req['branch'] );
			$date   = sanitize_text_field( $req['date'] );
			$start  = sanitize_text_field( $req['start'] );
			$name   = sanitize_text_field( $req['name'] );
			$phone  = sanitize_text_field( $req['phone'] );
			$email  = sanitize_email( $req['email'] );
			$kids   = max( 1, min( 8, (int) $req['children'] ) );
			if ( ! isset( $s['branches'][ $branch ] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! preg_match( '/^\d{2}:\d{2}$/', $start ) )
				return new WP_Error( 'bad', 'פרטים שגויים', array( 'status' => 400 ) );
			if ( mb_strlen( $name ) < 2 || ! is_email( $email ) )
				return new WP_Error( 'bad', 'נא למלא שם ואימייל תקין', array( 'status' => 400 ) );
			$wpdb->insert( "{$wpdb->prefix}gyl_waitlist", array(
				'branch' => $branch, 'slot_date' => $date, 'slot_start' => $start . ':00',
				'name' => $name, 'phone' => gyl_norm_phone( $phone ), 'email' => $email,
				'children' => $kids, 'created_at' => current_time( 'mysql', true ) ) );
			return rest_ensure_response( array( 'ok' => true,
				'message' => 'נרשמתם לרשימת ההמתנה. נעדכן במייל ברגע שיתפנה מקום.' ) );
		} ) );
} );

/** קובץ יומן להזמנה — /?gyl_ics=TOKEN */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['gyl_ics'] ) ) return;
	$s = gyl_get();
	$b = gyl_booking_by_token( sanitize_text_field( wp_unslash( $_GET['gyl_ics'] ) ) );
	if ( ! $b ) { wp_safe_redirect( home_url() ); exit; }
	$br  = $s['branches'][ $b['branch'] ] ?? array();
	$st  = gmdate( 'Ymd\THis\Z', strtotime( $b['slot_date'] . ' ' . $b['slot_start'] ) - 3 * 3600 );
	$en  = gmdate( 'Ymd\THis\Z', strtotime( $b['slot_date'] . ' ' . $b['slot_end'] ) - 3 * 3600 );
	$ttl = ( 'mom' === $b['service'] ) ? 'גן עם אמא — גאיהלנד' : 'גאיהלנד — ' . ( $br['label'] ?? '' );

	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="gayaland.ics"' );
	echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Gayaland//Booking//HE\r\nBEGIN:VEVENT\r\n";
	echo 'UID:gyl-' . $b['id'] . "@gayaland.co.il\r\n";
	echo "DTSTAMP:" . gmdate( 'Ymd\THis\Z' ) . "\r\nDTSTART:$st\r\nDTEND:$en\r\n";
	echo 'SUMMARY:' . $ttl . "\r\n";
	echo 'LOCATION:' . str_replace( array( "\r", "\n", ',' ), array( '', ' ', '\,' ), $br['address'] ?? '' ) . "\r\n";
	echo 'DESCRIPTION:' . $b['children'] . ' ילדים. שינוי מועד: ' . gyl_manage_link( $b['token'] ) . "\r\n";
	echo "BEGIN:VALARM\r\nTRIGGER:-PT2H\r\nACTION:DISPLAY\r\nDESCRIPTION:גאיהלנד בעוד שעתיים\r\nEND:VALARM\r\n";
	echo "END:VEVENT\r\nEND:VCALENDAR\r\n";
	exit;
} );

/** ייצוא CSV — כפתור בדף ההזמנות */
add_action( 'admin_init', function () {
	if ( empty( $_GET['gyl_csv'] ) || ! current_user_can( 'manage_options' ) ) return;
	check_admin_referer( 'gyl_csv' );
	global $wpdb; $s = gyl_get();
	$from = sanitize_text_field( $_GET['from'] ?? date( 'Y-m-01' ) );
	$to   = sanitize_text_field( $_GET['to'] ?? date( 'Y-m-t' ) );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}gyl_bookings WHERE slot_date BETWEEN %s AND %s ORDER BY slot_date, slot_start", $from, $to ), ARRAY_A );

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="gayaland-' . $from . '_' . $to . '.csv"' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" );   // BOM לאקסל בעברית
	fputcsv( $out, array( 'מספר', 'תאריך', 'שעה', 'סניף', 'שירות', 'שם', 'טלפון', 'אימייל', 'ילדים', 'סוג כרטיס', 'מסלול', 'תוספות', 'תוספות נמסרו', 'סכום', 'סטטוס', 'הזמנת ווקומרס', 'דירוג', 'הערות' ) );
	foreach ( $rows as $r ) {
		fputcsv( $out, array(
			$r['id'], $r['slot_date'], substr( $r['slot_start'], 0, 5 ) . '-' . substr( $r['slot_end'], 0, 5 ),
			$s['branches'][ $r['branch'] ]['label'] ?? $r['branch'],
			'mom' === $r['service'] ? 'גן עם אמא' : 'משחקייה',
			$r['name'], $r['phone'], $r['email'], $r['children'],
			$r['ticket'], $r['tier'], $r['extras'], ( $r['extras_at'] ? 'כן ' . $r['extras_at'] : ( $r['extras'] ? 'לא' : '' ) ), $r['price'], $r['status'], $r['order_id'], $r['rating'], $r['notes'],
		) );
	}
	fclose( $out );
	exit;
} );


/* ===========================================================
 * מנוע יום הולדת → חסימת הסבבים
 * ═══════════════════════════════════════════════════════════
 * כשמערכת ימי ההולדת (Apps Script) שומרת הזמנה,
 * היא קוראת ל-POST /gayaland/v1/birthday-block כדי לחסום
 * את הסבבים הרגילים באותו תאריך.
 * בלי זה — ייתכנו הזמנות רגילות בזמן יום הולדת → כאוס.
 * =========================================================== */
add_action( 'rest_api_init', function () {
	// placeholder already present
	register_rest_route( 'gayaland/v1', '/birthday-block', array(
		'methods'             => array( 'POST', 'DELETE' ),
		'permission_callback' => function ( $r ) {
			$k = $r->get_header( 'x-gyl-key' );
			return $k && hash_equals( (string) gyl_get( 'api_key' ), (string) $k );
		},
		'callback' => function ( $req ) {
			global $wpdb; $s = gyl_get();
			$branch = sanitize_key( $req['branch'] );
			$date   = sanitize_text_field( $req['date'] );
			$code   = sanitize_text_field( $req['code'] );
			$from   = sanitize_text_field( $req['from'] ?? '' );   // שעת התחלה, למשל 16:00
			$to     = sanitize_text_field( $req['to']   ?? '' );   // שעת סיום האירוע, למשל 19:00

			if ( ! isset( $s['branches'][ $branch ] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) )
				return new WP_Error( 'bad', 'פרטים שגויים', array( 'status' => 400 ) );

			$tag = 'יום-הולדת:' . $code;

			if ( 'DELETE' === $req->get_method() ) {
				// ביטול הזמנה — שחרור החסימה
				$wpdb->query( $wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}gyl_blocks WHERE branch=%s AND block_date=%s AND reason LIKE %s",
					$branch, $date, 'אוטומטי: יום הולדת%' ) );
				return rest_ensure_response( array( 'ok' => true, 'action' => 'released' ) );
			}

			// מחיקת חסימות קודמות של אותו קוד (עדכון)
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}gyl_blocks WHERE branch=%s AND block_date=%s AND reason=%s",
				$branch, $date, 'אוטומטי: ' . $tag ) );

			if ( $from && $to ) {
				// חסימת סבבים שחופפים לשעות האירוע בלבד
				$slots = gyl_slots_for( $branch, $date );
				$n = 0;
				foreach ( $slots as $sl ) {
					if ( $sl['start'] < $to && $sl['end'] > $from ) {
						$wpdb->insert( "{$wpdb->prefix}gyl_blocks", array(
							'branch'     => $branch,
							'block_date' => $date,
							'slot_start' => $sl['start'] . ':00',
							'reason'     => 'אוטומטי: ' . $tag,
						) );
						$n++;
					}
				}
				return rest_ensure_response( array( 'ok' => true, 'slots_blocked' => $n ) );
			}

			// ברירת מחדל: חסימת היום כולו
			$wpdb->insert( "{$wpdb->prefix}gyl_blocks", array(
				'branch'     => $branch,
				'block_date' => $date,
				'slot_start' => null,
				'reason'     => 'אוטומטי: ' . $tag,
			) );
			return rest_ensure_response( array( 'ok' => true, 'slots_blocked' => 'all' ) );
		},
	) );

	// קריאה מהדשבורד — מחזירה את כל הזמנות הפלאגין
	register_rest_route( 'gayaland/v1', '/bookings', array(
		'methods'  => 'GET',
		'permission_callback' => function ( $r ) {
			$k = $r->get_header( 'x-gyl-key' );
			return $k && hash_equals( (string) gyl_get( 'api_key' ), (string) $k );
		},
		'callback' => function ( $r ) {
			global $wpdb;
			$from = sanitize_text_field( $r->get_param( 'from' ) ?: date( 'Y-m-d' ) );
			$to   = sanitize_text_field( $r->get_param( 'to' )   ?: date( 'Y-m-d', strtotime( '+30 days' ) ) );
			return rest_ensure_response( $wpdb->get_results( $wpdb->prepare(
				"SELECT id, branch, slot_date, slot_start, slot_end, children, adults,
				        service, tier, ticket, price, status, name, phone, email,
				        rating, extras, extras_at, voucher, nayax_ok, order_id, created_at
				 FROM {$wpdb->prefix}gyl_bookings
				 WHERE slot_date BETWEEN %s AND %s ORDER BY slot_date, slot_start",
				$from, $to ), ARRAY_A ) );
		},
	) );

	/* --- שוברי תוספות (קפה/עוגיות) לדשבורד: רשימה + סטטוס מימוש --- */
	register_rest_route( 'gayaland/v1', '/redeemables', array(
		'methods'  => 'GET',
		'permission_callback' => function ( $r ) {
			$k = $r->get_header( 'x-gyl-key' );
			return $k && hash_equals( (string) gyl_get( 'api_key' ), (string) $k );
		},
		'callback' => function ( $r ) {
			global $wpdb;
			$from = sanitize_text_field( $r->get_param( 'from' ) ?: date( 'Y-m-d', strtotime( '-7 days' ) ) );
			$to   = sanitize_text_field( $r->get_param( 'to' )   ?: date( 'Y-m-d', strtotime( '+60 days' ) ) );
			// כל הזמנה שיש בה תוספת (extras לא ריק) — שובר שצריך למסור בקופה
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, branch, slot_date, slot_start, name, phone, extras, extras_at, status, created_at
				 FROM {$wpdb->prefix}gyl_bookings
				 WHERE extras <> '' AND extras IS NOT NULL AND status <> 'cancelled'
				   AND slot_date BETWEEN %s AND %s
				 ORDER BY extras_at IS NOT NULL, slot_date, slot_start",
				$from, $to ), ARRAY_A );
			$open = 0; $done = 0;
			foreach ( $rows as $row ) { empty( $row['extras_at'] ) ? $open++ : $done++; }
			return rest_ensure_response( array(
				'items' => $rows, 'open' => $open, 'done' => $done, 'total' => count( $rows ),
			) );
		},
	) );
	/* --- Nayax Notification_URL: אישור תשלום עצמאי (מקור אמת שני) --- */
	register_rest_route( 'gayaland/v1', '/nayax-notify', array(
		'methods'  => array( 'GET', 'POST' ),
		'permission_callback' => '__return_true',   // Nayax אנונימי; מאמתים ב-body
		'callback' => 'gyl_nayax_notify',
	) );

	/* --- סטטוס אימות כפול לדשבורד --- */
	register_rest_route( 'gayaland/v1', '/verify-status', array(
		'methods'  => 'GET',
		'permission_callback' => function ( $r ) {
			$k = $r->get_header( 'x-gyl-key' );
			return $k && hash_equals( (string) gyl_get( 'api_key' ), (string) $k );
		},
		'callback' => function ( $r ) {
			global $wpdb;
			$from = sanitize_text_field( $r->get_param( 'from' ) ?: date( 'Y-m-d', strtotime( '-7 days' ) ) );
			$to   = sanitize_text_field( $r->get_param( 'to' )   ?: date( 'Y-m-d', strtotime( '+30 days' ) ) );
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, order_id, name, phone, slot_date, slot_start, price, status, nayax_ok, nayax_txn, nayax_at
				 FROM {$wpdb->prefix}gyl_bookings
				 WHERE slot_date BETWEEN %s AND %s ORDER BY slot_date, slot_start", $from, $to ), ARRAY_A );
			foreach ( $rows as &$b ) {
				$wc_ok = 0;
				if ( $b['order_id'] && function_exists( 'wc_get_order' ) ) {
					$o = wc_get_order( $b['order_id'] );
					$wc_ok = ( $o && in_array( $o->get_status(), array( 'processing', 'completed' ), true ) ) ? 1 : 0;
				}
				$b['wc_ok'] = $wc_ok;
				$b['verified'] = ( $wc_ok && (int) $b['nayax_ok'] ) ? 1 : 0;
				// דגל אזהרה: Nayax אישר אבל WooCommerce לא — בדיוק הבעיה של "נכשל למרות ששולם"
				$b['mismatch'] = ( (int) $b['nayax_ok'] && ! $wc_ok ) ? 1 : 0;
			}
			return rest_ensure_response( $rows );
		},
	) );
} );

/**
 * מקבל התראת תשלום מ-Nayax, רושם ביומן, מאתר הזמנה ומסמן nayax_ok.
 * Nayax שולח פרמטרים משתנים — קולטים בכל פורמט.
 */
function gyl_nayax_notify( $req ) {
	global $wpdb;
	$p = $req->get_params();
	if ( empty( $p ) ) $p = $_REQUEST;

	$pick = function ( $keys ) use ( $p ) {
		foreach ( (array) $keys as $k ) {
			foreach ( $p as $pk => $pv ) {
				if ( strcasecmp( $pk, $k ) === 0 && $pv !== '' ) return $pv;
			}
		}
		return '';
	};

	$txn    = (string) $pick( array( 'transaction_id', 'TransactionId', 'trans_id', 'transactionID' ) );
	$amount = (float) $pick( array( 'trans_amount', 'Amount', 'amount' ) );
	$oid    = (int) $pick( array( 'trans_refNum', 'refNum', 'order_id', 'reference' ) );
	$stat   = strtolower( (string) $pick( array( 'reply', 'Reply', 'status', 'Status', 'ResultCode', 'result' ) ) );
	$ok     = ( $stat === '' || $stat === '0' || $stat === '000' || preg_match( '/approv|success|ok|paid/', $stat ) );

	// אם אין order_id ישיר — מנסים להתאים לפי סכום + הזמנה אחרונה שממתינה
	$booking = null;
	if ( $oid ) {
		$booking = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}gyl_bookings WHERE order_id=%d LIMIT 1", $oid ), ARRAY_A );
	}
	if ( ! $booking && $amount > 0 ) {
		$booking = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}gyl_bookings
			 WHERE nayax_ok=0 AND price=%f AND created_at > (NOW() - INTERVAL 2 HOUR)
			 ORDER BY created_at DESC LIMIT 1", $amount ), ARRAY_A );
	}

	$wpdb->insert( "{$wpdb->prefix}gyl_nayax_log", array(
		'received_at' => current_time( 'mysql' ),
		'order_id'    => $booking ? (int) $booking['order_id'] : $oid,
		'txn_id'      => $txn,
		'amount'      => $amount,
		'status'      => $ok ? 'אושר' : ( 'נדחה:' . $stat ),
		'matched'     => $booking ? 1 : 0,
		'raw'         => wp_json_encode( $p ),
	) );

	if ( $booking && $ok ) {
		$wpdb->update( "{$wpdb->prefix}gyl_bookings", array(
			'nayax_ok'  => 1,
			'nayax_txn' => $txn,
			'nayax_at'  => current_time( 'mysql' ),
		), array( 'id' => $booking['id'] ) );
	}

	// Nayax מצפה ל-200 פשוט
	return new WP_REST_Response( 'OK', 200 );
}

add_action( 'rest_api_init', function () {
	/* ══ משוב מדף מעוצב — שמירה + שליפת פרטי הזמנה ══ */
	register_rest_route( 'gayaland/v1', '/feedback/info', array(
		'methods' => array( 'GET', 'OPTIONS' ),
		'permission_callback' => '__return_true',
		'callback' => function ( $r ) {
			header( 'Access-Control-Allow-Origin: *' );
			global $wpdb;
			$id = (int) $r->get_param( 'booking' );
			$b = $id ? $wpdb->get_row( $wpdb->prepare(
				"SELECT name, branch, slot_date FROM {$wpdb->prefix}gyl_bookings WHERE id=%d", $id ), ARRAY_A ) : null;
			$s = gyl_get();
			return rest_ensure_response( array(
				'ok' => true,
				'name' => $b['name'] ?? '', 'branch' => $b['branch'] ?? '',
				'google_url' => $s['google_url'] ?? '', 'review_min' => (int) ( $s['review_min'] ?? 4 ),
			) );
		},
	) );

	register_rest_route( 'gayaland/v1', '/feedback/submit', array(
		'methods' => array( 'POST', 'OPTIONS' ),
		'permission_callback' => '__return_true',
		'callback' => function ( $r ) {
			header( 'Access-Control-Allow-Origin: *' );
			global $wpdb;
			$id = (int) $r->get_param( 'booking' );
			$b  = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gyl_bookings WHERE id=%d", $id ), ARRAY_A ) : null;
			$rating = max( 0, min( 5, (int) $r->get_param( 'rating' ) ) );
			$wpdb->insert( "{$wpdb->prefix}gyl_feedback", array(
				'booking_id' => $id, 'rating' => $rating,
				'q_clean' => (int) $r->get_param( 'q_clean' ), 'q_staff' => (int) $r->get_param( 'q_staff' ),
				'q_fun'   => (int) $r->get_param( 'q_fun' ),   'q_value' => (int) $r->get_param( 'q_value' ),
				'comment' => sanitize_textarea_field( (string) $r->get_param( 'comment' ) ),
				'name'    => $b['name'] ?? sanitize_text_field( (string) $r->get_param( 'name' ) ),
				'phone'   => $b['phone'] ?? '', 'branch' => $b['branch'] ?? '',
				'created_at' => current_time( 'mysql' ),
			) );
			if ( $b ) $wpdb->update( "{$wpdb->prefix}gyl_bookings", array( 'rating' => $rating, 'rated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) );

			// מייל התראה למנהל על משוב נמוך
			$s = gyl_get();
			if ( $rating > 0 && $rating < (int) ( $s['review_min'] ?? 4 ) && ! empty( $s['notify_email'] ) ) {
				gyl_mail( $s['notify_email'], 'משוב נמוך התקבל (' . $rating . '★)',
					'לקוח: ' . ( $b['name'] ?? '' ) . "\nדירוג: " . $rating . "\nהערה: " . $r->get_param( 'comment' ) . "\nהזמנה: #" . $id );
			}
			return rest_ensure_response( array( 'ok' => true, 'google_url' => $s['google_url'] ?? '' ) );
		},
	) );
} );

add_action( 'rest_api_init', function () {
	/* ══ האזור שלי — כניסת הורים לפי טלפון + קוד וואטסאפ ══ */

	// שלב 1: בקשת קוד — יוצר קוד, שומר, ומחזיר קישור וואטסאפ לשליחה
	register_rest_route( 'gayaland/v1', '/my/request-code', array(
		'methods' => array( 'POST', 'OPTIONS' ),
		'permission_callback' => '__return_true',
		'callback' => function ( $r ) {
			header( 'Access-Control-Allow-Origin: *' );
			global $wpdb;
			$phone = gyl_norm_phone( $r->get_param( 'phone' ) );
			// פורמט בינלאומי לוואטסאפ
			if ( strpos( $phone, '972' ) !== 0 ) {
				$phone = '972' . ltrim( $phone, '0' );
			}
			if ( strlen( $phone ) < 11 ) return new WP_Error( 'bad', 'מספר טלפון לא תקין', array( 'status' => 400 ) );

			// הגבלת קצב — כל הודעה עולה כסף ומגיעה לטלפון של לקוח אמיתי.
			// שכבה 1: לפי טלפון בלבד, ללא תלות ב-IP — עוצר הצפה של אדם ספציפי
			// גם כשהתוקף מחליף כתובות. 3 קודים ל-10 דקות.
			if ( ! gyl_rate_phone_ok( substr( $phone, -9 ), 3, 600 ) )
				return new WP_Error( 'rate', 'נשלחו כבר מספר קודים. נסו שוב בעוד כמה דקות.', array( 'status' => 429 ) );
			// שכבה 2: לפי IP (gyl_rate_ok מערבב IP פנימית) — עוצר סריקה על מספרים רבים.
			if ( ! gyl_rate_ok( 'otp', 10, 3600 ) )
				return new WP_Error( 'rate', 'יותר מדי בקשות. נסו שוב מאוחר יותר.', array( 'status' => 429 ) );

			// יש בכלל הזמנות עם הטלפון הזה? (אחרת אין טעם)
			$has = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}gyl_bookings WHERE phone LIKE %s", '%' . substr( $phone, -9 ) ) );

			$code = (string) wp_rand( 1000, 9999 );
			$wpdb->insert( "{$wpdb->prefix}gyl_otp", array(
				'phone' => $phone, 'code' => $code,
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + 600 ),   // 10 דקות
				'created_at' => current_time( 'mysql' ),
			) );

			// שליחה: אם הוגדר טוקן WhatsApp API שולחים אוטומטית; אחרת מחזירים קישור
			$s = gyl_get();
			$sent = false;
			if ( ! empty( $s['wa_token'] ) && ! empty( $s['wa_phone_id'] ) ) {
				$sent = gyl_send_whatsapp( $phone, $code, $s );
			}
			return rest_ensure_response( array(
				'ok' => true, 'sent' => $sent, 'has_data' => $has > 0,
				// fallback ידני אם אין API: קישור שההורה לוחץ כדי לקבל את הקוד לעצמו
				'wa_link' => $sent ? '' : 'https://wa.me/' . $phone . '?text=' . rawurlencode( 'קוד הכניסה שלי לגאיהלנד: ' . $code ),
			) );
		},
	) );

	// שלב 2: אימות קוד + החזרת כל נתוני ההורה
	register_rest_route( 'gayaland/v1', '/my/verify', array(
		'methods' => array( 'POST', 'OPTIONS' ),
		'permission_callback' => '__return_true',
		'callback' => function ( $r ) {
			header( 'Access-Control-Allow-Origin: *' );
			global $wpdb;
			$phone = gyl_norm_phone( $r->get_param( 'phone' ) );
			if ( strpos( $phone, '972' ) !== 0 ) { $phone = '972' . ltrim( $phone, '0' ); }
			$code  = preg_replace( '/\D/', '', (string) $r->get_param( 'code' ) );
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gyl_otp WHERE phone=%s ORDER BY id DESC LIMIT 1", $phone ), ARRAY_A );
			if ( ! $row ) return new WP_Error( 'no', 'לא נמצא קוד. בקשו קוד חדש.', array( 'status' => 400 ) );
			if ( (int) $row['tries'] >= 5 ) return new WP_Error( 'lock', 'יותר מדי ניסיונות. בקשו קוד חדש.', array( 'status' => 429 ) );
			$wpdb->update( "{$wpdb->prefix}gyl_otp", array( 'tries' => (int) $row['tries'] + 1 ), array( 'id' => $row['id'] ) );
			if ( strtotime( $row['expires_at'] ) < time() ) return new WP_Error( 'exp', 'הקוד פג. בקשו קוד חדש.', array( 'status' => 400 ) );
			if ( ! hash_equals( $row['code'], $code ) ) return new WP_Error( 'wrong', 'קוד שגוי', array( 'status' => 400 ) );

			// אימות עבר — מנקים את הקוד ומחזירים טוקן פשוט ל-30 יום + הנתונים
			$wpdb->delete( "{$wpdb->prefix}gyl_otp", array( 'phone' => $phone ) );
			$token = gyl_my_token( $phone );
			return rest_ensure_response( array_merge(
				array( 'ok' => true, 'token' => $token, 'phone' => $phone ),
				gyl_my_data( $phone )
			) );
		},
	) );

	// שליפת נתונים עם טוקן (כניסה חוזרת בלי קוד, עד 30 יום)
	register_rest_route( 'gayaland/v1', '/my/data', array(
		'methods' => array( 'GET', 'OPTIONS' ),
		'permission_callback' => '__return_true',
		'callback' => function ( $r ) {
			header( 'Access-Control-Allow-Origin: *' );
			$phone = gyl_norm_phone( $r->get_param( 'phone' ) );
			if ( strpos( $phone, '972' ) !== 0 ) { $phone = '972' . ltrim( $phone, '0' ); }
			$token = (string) $r->get_param( 'token' );
			if ( ! hash_equals( gyl_my_token( $phone ), $token ) )
				return new WP_Error( 'auth', 'התחברות נדרשת מחדש', array( 'status' => 401 ) );
			return rest_ensure_response( array_merge( array( 'ok' => true, 'phone' => $phone ), gyl_my_data( $phone ) ) );
		},
	) );
} );

/* טוקן פשוט חתום לכניסה חוזרת (30 יום) */
function gyl_my_token( $phone ) {
	$s = gyl_get();
	return substr( hash_hmac( 'sha256', $phone, $s['api_key'] . '|my' ), 0, 32 );
}

/* כל נתוני ההורה: הזמנות, יתרת כרטיסייה, שוברים */
function gyl_my_data( $phone ) {
	global $wpdb;
	$like = '%' . substr( $phone, -9 );
	$bookings = $wpdb->get_results( $wpdb->prepare(
		"SELECT branch, slot_date, slot_start, children, ticket, status, extras, nayax_ok
		 FROM {$wpdb->prefix}gyl_bookings
		 WHERE phone LIKE %s AND status <> 'cancelled'
		 ORDER BY slot_date DESC, slot_start DESC LIMIT 40", $like ), ARRAY_A );

	$punch = function_exists( 'gyl_punch_balance' ) ? (int) gyl_punch_balance( $phone )
		: (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(delta),0) FROM {$wpdb->prefix}gyl_credits WHERE phone LIKE %s", $like ) );

	$today = date( 'Y-m-d' );
	$upcoming = array(); $past = array();
	foreach ( $bookings as $b ) {
		( $b['slot_date'] >= $today ) ? $upcoming[] = $b : $past[] = $b;
	}
	return array(
		'upcoming' => array_reverse( $upcoming ),   // הקרוב ביותר ראשון
		'past'     => $past,
		'punch_left' => $punch,
		'visits'   => count( $past ),
	);
}

/* שליחת קוד בוואטסאפ (אם הוגדר WhatsApp Cloud API) */
function gyl_send_whatsapp( $phone, $code, $s ) {
	$res = wp_remote_post( 'https://graph.facebook.com/v19.0/' . $s['wa_phone_id'] . '/messages', array(
		'timeout' => 12,
		'headers' => array( 'Authorization' => 'Bearer ' . $s['wa_token'], 'Content-Type' => 'application/json' ),
		'body' => wp_json_encode( array(
			'messaging_product' => 'whatsapp', 'to' => $phone, 'type' => 'text',
			'text' => array( 'body' => 'קוד הכניסה שלך לאזור האישי בגאיהלנד: ' . $code ),
		) ),
	) );
	return ! is_wp_error( $res ) && (int) wp_remote_retrieve_response_code( $res ) < 300;
}

add_action( 'rest_api_init', function () {
	/* --- סימון הגעה (check-in) מהדשבורד --- */
	register_rest_route( 'gayaland/v1', '/checkin', array(
		'methods'  => 'POST',
		'permission_callback' => function ( $r ) {
			$k = $r->get_header( 'x-gyl-key' );
			return $k && hash_equals( (string) gyl_get( 'api_key' ), (string) $k );
		},
		'callback' => function ( $r ) {
			global $wpdb;
			$id   = (int) $r['booking_id'];
			$undo = ! empty( $r['undo'] );
			$b = $wpdb->get_row( $wpdb->prepare( "SELECT id, status FROM {$wpdb->prefix}gyl_bookings WHERE id=%d", $id ), ARRAY_A );
			if ( ! $b ) return new WP_Error( 'nf', 'הזמנה לא נמצאה', array( 'status' => 404 ) );
			$new = $undo ? 'confirmed' : 'checked_in';
			$wpdb->update( "{$wpdb->prefix}gyl_bookings", array( 'status' => $new ), array( 'id' => $id ) );
			return rest_ensure_response( array( 'ok' => true, 'status' => $new ) );
		},
	) );
} );

/* birthday-config — קונפיגורציה ציבורית לדף ב-Netlify */
add_action( 'rest_api_init', function () {
	register_rest_route( 'gayaland/v1', '/birthday-config', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			// CORS — הדף ב-Netlify חייב להיות מסוגל לקרוא cross-origin
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
			$s  = gyl_get();
			$bd = $s['birthday'];
			return rest_ensure_response( array(
				'test_mode'    => gyl_test_on() ? 1 : 0,
				'test_price'   => (int) ( $s['test_price'] ?? 1 ),
				'nayax_test'   => $bd['nayax_test'] ?? '',
				'nayax'        => gyl_test_on() ? ( $bd['nayax_test'] ?? '' ) : ( $bd['nayax'] ?? '' ),
				'design_img'   => $bd['design_img'] ?? '',
				'design_video' => $bd['design_video'] ?? '',
				'gift_img'     => $bd['gift_img'] ?? '',
				'gift_price'   => (int) ( $bd['gift_price'] ?? 25 ),
				'deposit'      => gyl_test_on() ? (int) ( $s['test_price'] ?? 1 ) : (int) ( $bd['deposit'] ?? 500 ),
				'duration'     => (int) ( $bd['duration'] ?? 3 ),
				'max_kids'     => (int) ( $bd['max_kids'] ?? 60 ),
				'weekend_fee'  => (int) ( $bd['weekend_fee'] ?? 499 ),
				'gift_label'   => $bd['gift_label'] ?? 'שקית הפתעה לילד',
				'gift_video'   => $bd['gift_video'] ?? '',
				'bags_incl'    => (int) ( $bd['bags_incl'] ?? 30 ),
				'scarcity_at'  => (int) ( $bd['scarcity_at'] ?? 12 ),
				'hold_hours'   => (int) ( $bd['hold_hours'] ?? 24 ),
				'holidays'     => (array) ( $bd['holidays'] ?? array() ),
				'slots_by_day' => $bd['slots_by_day'] ?? array(),
				'tiers'        => $bd['tiers'] ?? array(),
				'bd_addons'    => $bd['bd_addons'] ?? array(),
				'cakes'        => $bd['cakes'] ?? array(),
				'nudge'        => $bd['nudge'] ?? array(),
				'whatsapp'     => $bd['whatsapp'] ?? '',
				'cancel_url'   => $bd['cancel_url'] ?? '',
				'base_lines'   => $bd['base_lines'] ?? array(),
				'branches'     => array_map( function ( $k, $v ) {
					return array( 'key' => $k, 'label' => $v['label'] );
				}, array_keys( $s['branches'] ), $s['branches'] ),
			) );
		},
	) );
} );


/* ===========================================================
 * ניהול
 * =========================================================== */
add_action( 'admin_menu', function () {
	add_menu_page( 'גאיהלנד', 'גאיהלנד', 'manage_options', 'gyl', 'gyl_page_bookings', 'dashicons-calendar-alt', 26 );
	add_submenu_page( 'gyl', 'מי מגיע', '📋 מי מגיע', 'manage_options', 'gyl', 'gyl_page_bookings' );
	add_submenu_page( 'gyl', 'שעות פתיחה', '🕘 שעות פתיחה', 'manage_options', 'gyl-hours', 'gyl_page_hours' );
	add_submenu_page( 'gyl', 'לוח חודשי', '📅 לוח חודשי', 'manage_options', 'gyl-cal', 'gyl_page_cal' );
	add_submenu_page( 'gyl', 'בניית חודש', '✨ בניית חודש', 'manage_options', 'gyl-month', 'gyl_page_month' );
	add_submenu_page( 'gyl', 'אירועים', '🎂 אירועים וחסימות', 'manage_options', 'gyl-blocks', 'gyl_page_blocks' );
	add_submenu_page( 'gyl', 'גן עם אמא', '👶 גן עם אמא', 'manage_options', 'gyl-mom', 'gyl_page_mom' );
	add_submenu_page( 'gyl', 'לידים', '🎂 לידים ליום הולדת', 'manage_options', 'gyl-leads', 'gyl_page_leads' );
	add_submenu_page( 'gyl', 'משובים', '⭐ משובים', 'manage_options', 'gyl-feedback', 'gyl_page_feedback' );
	add_submenu_page( 'gyl', 'כרטיסיות', '🎟️ כרטיסיות', 'manage_options', 'gyl-credits', 'gyl_page_credits' );
	add_submenu_page( 'gyl', 'הגדרות', '⚙️ הגדרות', 'manage_options', 'gyl-settings', 'gyl_page_settings' );
} );

function gyl_page_bookings() {
	global $wpdb; $s = gyl_get(); $br = $s['branches'];

	if ( isset( $_GET['ci'] ) && check_admin_referer( 'gyl_a' ) ) $wpdb->update( "{$wpdb->prefix}gyl_bookings", array( 'status' => 'checked_in' ), array( 'id' => (int) $_GET['ci'] ) );
	if ( isset( $_GET['cx'] ) && check_admin_referer( 'gyl_a' ) ) $wpdb->update( "{$wpdb->prefix}gyl_bookings", array( 'status' => 'cancelled' ), array( 'id' => (int) $_GET['cx'] ) );
	if ( isset( $_GET['ex'] ) && check_admin_referer( 'gyl_a' ) ) $wpdb->update( "{$wpdb->prefix}gyl_bookings", array( 'extras_at' => current_time( 'mysql' ) ), array( 'id' => (int) $_GET['ex'] ) );
	if ( isset( $_GET['exu'] ) && check_admin_referer( 'gyl_a' ) ) $wpdb->update( "{$wpdb->prefix}gyl_bookings", array( 'extras_at' => null ), array( 'id' => (int) $_GET['exu'] ) );

	/* הוספה ידנית של לקוח לסבב */
	if ( ! empty( $_POST['gyl_manual'] ) && check_admin_referer( 'gyl_manual' ) ) {
		$m_branch = sanitize_key( $_POST['m_branch'] );
		$m_date   = sanitize_text_field( $_POST['m_date'] );
		$m_start  = sanitize_text_field( $_POST['m_start'] );
		$m_end    = sanitize_text_field( $_POST['m_end'] );
		$m_name   = sanitize_text_field( $_POST['m_name'] );
		$m_phone  = sanitize_text_field( $_POST['m_phone'] );
		$m_kids   = max( 1, (int) $_POST['m_children'] );
		$m_note   = sanitize_textarea_field( $_POST['m_notes'] ?? '' );
		$m_paid   = ! empty( $_POST['m_paid'] );
		if ( $m_name && $m_start ) {
			$wpdb->insert( "{$wpdb->prefix}gyl_bookings", array(
				'branch' => $m_branch, 'slot_date' => $m_date,
				'slot_start' => $m_start . ':00', 'slot_end' => $m_end . ':00',
				'children' => $m_kids, 'adults' => 1,
				'service' => 'entry', 'ticket' => 'manual', 'price' => 0,
				'status' => $m_paid ? 'confirmed' : 'pending',
				'name' => $m_name, 'phone' => $m_phone, 'email' => '',
				'notes' => trim( '[נוסף ידנית ע"י המנהל] ' . $m_note ),
				'token' => wp_generate_password( 20, false ),
				'created_at' => current_time( 'mysql' ),
			) );
			echo '<div class="notice notice-success is-dismissible"><p>✅ ' . esc_html( $m_name ) . ' נוסף לסבב ' . esc_html( $m_start ) . '</p></div>';
		}
	}

	$date   = sanitize_text_field( $_GET['d'] ?? date( 'Y-m-d' ) );
	$branch = sanitize_key( $_GET['b'] ?? array_key_first( $br ) );
	if ( ! isset( $br[ $branch ] ) ) $branch = array_key_first( $br );
	$q = sanitize_text_field( $_GET['q'] ?? '' );

	$prev = date( 'Y-m-d', strtotime( $date . ' -1 day' ) );
	$next = date( 'Y-m-d', strtotime( $date . ' +1 day' ) );
	$url  = function ( $d, $b ) { return admin_url( 'admin.php?page=gyl&d=' . $d . '&b=' . $b ); };

	/* חיפוש חוצה־תאריכים */
	if ( $q ) {
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}gyl_bookings WHERE (name LIKE %s OR phone LIKE %s OR email LIKE %s)
			 AND status<>'cancelled' ORDER BY slot_date DESC LIMIT 50", $like, $like, $like ), ARRAY_A );
		echo '<div class="wrap gyl-adm"><h1>🔍 תוצאות חיפוש: ' . esc_html( $q ) . '</h1>';
		echo '<p><a href="' . esc_url( $url( date( 'Y-m-d' ), $branch ) ) . '">← חזרה להיום</a></p>';
		if ( ! $rows ) echo '<div class="gyl-empty">לא נמצאו הזמנות.</div>';
		foreach ( $rows as $r ) {
			echo '<div class="gyl-guest" style="background:#fff;border:1px solid #EBE4D6;border-radius:12px;margin-bottom:6px">';
			echo '<span class="nm">' . esc_html( $r['name'] ) . '</span><span class="ph">' . esc_html( $r['phone'] ) . '</span>';
			echo '<span>' . date_i18n( 'j/n/Y', strtotime( $r['slot_date'] ) ) . ' · ' . substr( $r['slot_start'], 0, 5 ) . '</span>';
			echo '<span>' . esc_html( $br[ $r['branch'] ]['label'] ?? '' ) . '</span>';
			echo '<span class="kids">' . (int) $r['children'] . '</span><span class="sp"></span>';
			echo '<a class="gyl-btn-o" target="_blank" href="' . esc_url( gyl_manage_link( $r['token'] ) ) . '">קישור אישי</a></div>';
		}
		echo '</div>'; return;
	}

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}gyl_bookings WHERE slot_date=%s AND branch=%s AND status<>'cancelled'
		 ORDER BY slot_start, id", $date, $branch ), ARRAY_A );

	$byslot = array(); $kids = 0; $money = 0;
	foreach ( $rows as $r ) {
		$byslot[ substr( $r['slot_start'], 0, 5 ) ][] = $r;
		$kids += (int) $r['children'];
		$money += (float) $r['price'];
	}
	$cap   = (int) $br[ $branch ]['capacity'];
	$slots = gyl_slots_for( $branch, $date );
	$occ   = ( $slots && $cap ) ? round( $kids / ( count( $slots ) * $cap ) * 100 ) : 0;

	echo '<div class="wrap gyl-adm"><h1>📋 מי מגיע</h1>';

	/* ניווט */
	echo '<div class="gyl-nav">';
	foreach ( $br as $k => $b )
		echo '<a href="' . esc_url( $url( $date, $k ) ) . '" class="' . ( $branch === $k ? 'on' : '' ) . '">' . esc_html( $b['label'] ) . '</a>';
	echo '<span style="flex:1"></span>';
	echo '<a href="' . esc_url( $url( $prev, $branch ) ) . '">›</a>';
	echo '<span class="today">' . date_i18n( 'l, j/n/Y', strtotime( $date ) ) . '</span>';
	echo '<a href="' . esc_url( $url( $next, $branch ) ) . '">‹</a>';
	echo '<a href="' . esc_url( $url( date( 'Y-m-d' ), $branch ) ) . '">היום</a>';
	echo '<form method="get" style="display:flex;gap:6px;margin-right:8px"><input type="hidden" name="page" value="gyl">';
	echo '<input type="search" name="q" placeholder="חיפוש שם / טלפון" style="border-radius:10px;border:1.5px solid #EBE4D6;padding:6px 10px">';
	echo '<button class="gyl-btn-s">חיפוש</button></form>';
	echo '<button class="gyl-btn-s" onclick="window.print()">הדפסה</button>';
	$csv = wp_nonce_url( admin_url( 'admin.php?page=gyl&gyl_csv=1&from=' . date( 'Y-m-01', strtotime( $date ) ) . '&to=' . date( 'Y-m-t', strtotime( $date ) ) ), 'gyl_csv' );
	echo '<a class="gyl-btn-s" href="' . esc_url( $csv ) . '">ייצוא CSV לחודש</a>';
	echo '</div>';

	/* נתונים */
	echo '<div class="gyl-stats">';
	echo '<div class="gyl-stat"><div class="n">' . count( $rows ) . '</div><div class="l">הזמנות</div></div>';
	echo '<div class="gyl-stat"><div class="n">' . (int) $kids . '</div><div class="l">ילדים</div></div>';
	echo '<div class="gyl-stat amber"><div class="n">' . (int) $money . ' ₪</div><div class="l">שולם מראש</div></div>';
	echo '<div class="gyl-stat"><div class="n">' . (int) $occ . '%</div><div class="l">תפוסה ביום</div></div>';
	echo '</div>';

	if ( ! $slots ) {
		echo '<div class="gyl-help">אין סבבים ביום זה — היום סגור בלוח. אפשר לפתוח אותו ב<b>לוח חודשי</b>.</div>';
	}

	/* סבבים */
	foreach ( $slots as $sl ) {
		$t    = $sl['start'];
		$list = $byslot[ $t ] ?? array();
		$k    = array_sum( array_map( function ( $x ) { return (int) $x['children']; }, $list ) );
		$blk  = gyl_blocked( $branch, $date, $t );
		$pct  = $cap ? min( 100, round( $k / $cap * 100 ) ) : 0;

		echo '<div class="gyl-round"><div class="gyl-round-h"><b>' . $t . ' – ' . $sl['end'] . '</b>';
		echo '<span class="gyl-cap"><span class="gyl-bar' . ( $k >= $cap ? ' full' : '' ) . '"><i style="width:' . $pct . '%"></i></span>' . $k . ' / ' . $cap . ' ילדים</span></div>';

		if ( $blk ) {
			echo '<div class="gyl-blocked">🔒 ' . esc_html( str_replace( 'אוטומטי: ', '', $blk ) ) . '</div>';
		}
		if ( ! $list ) {
			echo '<div class="gyl-empty">אין הזמנות בסבב הזה.</div>';
		}
		foreach ( $list as $r ) {
			$tag = 'checked_in' === $r['status'] ? '<span class="gyl-tag in">✓ הגיעו</span>'
				: ( 'pending' === $r['status'] ? '<span class="gyl-tag pend">ממתין לתשלום</span>' : '<span class="gyl-tag ok">שולם</span>' );
			$tk  = 'use_punch' === $r['ticket'] ? '<span class="gyl-tag punch">כרטיסייה — לנקב</span>'
				: ( 'buy_punch' === $r['ticket'] ? '<span class="gyl-tag punch">רכשו כרטיסייה</span>' : '' );
			echo '<div class="gyl-guest"><span class="nm">' . esc_html( $r['name'] ) . '</span>';
			echo '<a class="ph" href="tel:' . esc_attr( $r['phone'] ) . '">' . esc_html( $r['phone'] ) . '</a>';
			echo '<span class="kids">' . (int) $r['children'] . ' ילדים</span>' . $tag . ' ' . $tk;
			if ( ! empty( $r['extras'] ) ) {
				if ( empty( $r['extras_at'] ) ) {
					$exu = wp_nonce_url( admin_url( 'admin.php?page=gyl&d=' . $date . '&b=' . $branch . '&ex=' . $r['id'] ), 'gyl_a' );
					echo ' <a class="gyl-tag extras redeem" href="' . esc_url( $exu ) . '" title="לחצו כשמסרתם">☕ לממש: ' . esc_html( $r['extras'] ) . '</a>';
				} else {
					$und = wp_nonce_url( admin_url( 'admin.php?page=gyl&d=' . $date . '&b=' . $branch . '&exu=' . $r['id'] ), 'gyl_a' );
					echo ' <span class="gyl-tag given">✓ נמסר ' . esc_html( date_i18n( 'H:i', strtotime( $r['extras_at'] ) ) ) . ' — ' . esc_html( $r['extras'] ) . '</span>';
					echo ' <a class="undo" href="' . esc_url( $und ) . '" title="ביטול מסירה">↺</a>';
				}
			}
			if ( (int) $r['moves'] ) echo ' <span class="gyl-tag punch">הוזז ' . (int) $r['moves'] . '×</span>';
			if ( $r['notes'] ) echo ' <span class="gyl-cap">' . esc_html( wp_trim_words( $r['notes'], 8 ) ) . '</span>';
			echo '<span class="sp"></span>';
			if ( 'checked_in' !== $r['status'] )
				echo '<a class="gyl-btn-s" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=gyl&d=' . $date . '&b=' . $branch . '&ci=' . $r['id'] ), 'gyl_a' ) ) . '">צ׳ק-אין</a> ';
			echo '<a class="gyl-btn-o" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=gyl&d=' . $date . '&b=' . $branch . '&cx=' . $r['id'] ), 'gyl_a' ) ) . '" onclick="return confirm(\'לבטל את ההזמנה?\')">ביטול</a>';
			echo '</div>';
		}

		/* טופס הוספה ידנית לסבב הזה */
		$fid = 'mf' . preg_replace( '/\D/', '', $t );
		echo '<div style="padding:4px 10px 10px">';
		echo '<a href="#" class="gyl-btn-s" onclick="var e=document.getElementById(\'' . $fid . '\');e.style.display=(e.style.display==\'none\'?\'block\':\'none\');return false;">➕ הוספה ידנית</a>';
		echo '<form id="' . $fid . '" method="post" style="display:none;margin-top:8px;background:#FAF7F0;border:1px solid #EBE4D6;border-radius:10px;padding:10px">';
		wp_nonce_field( 'gyl_manual' );
		echo '<input type="hidden" name="gyl_manual" value="1">';
		echo '<input type="hidden" name="m_branch" value="' . esc_attr( $branch ) . '">';
		echo '<input type="hidden" name="m_date" value="' . esc_attr( $date ) . '">';
		echo '<input type="hidden" name="m_start" value="' . esc_attr( $t ) . '">';
		echo '<input type="hidden" name="m_end" value="' . esc_attr( $sl['end'] ) . '">';
		echo '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">';
		echo '<input type="text" name="m_name" placeholder="שם *" required style="border-radius:8px;border:1.5px solid #EBE4D6;padding:6px 10px">';
		echo '<input type="tel" name="m_phone" placeholder="טלפון" style="border-radius:8px;border:1.5px solid #EBE4D6;padding:6px 10px;width:130px">';
		echo '<input type="number" name="m_children" value="1" min="1" max="20" title="מספר ילדים" style="border-radius:8px;border:1.5px solid #EBE4D6;padding:6px 10px;width:70px">';
		echo '<input type="text" name="m_notes" placeholder="הערה (למשל: שילם במקום)" style="border-radius:8px;border:1.5px solid #EBE4D6;padding:6px 10px;flex:1;min-width:150px">';
		echo '<label style="font-size:12px;white-space:nowrap"><input type="checkbox" name="m_paid" value="1" checked> שולם</label>';
		echo '<button class="gyl-btn-s" type="submit">הוספה</button>';
		echo '</div></form></div>';

		echo '</div>';
	}
	echo '</div>';
}

/** עורך שעות — הוספה/מחיקה של טווחים בלחיצה */
function gyl_page_hours() {
	$s = gyl_get();
	$he = array( 'ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת' );
	if ( ! empty( $_POST['gyl_hours'] ) && check_admin_referer( 'gyl_h' ) ) {
		foreach ( $s['branches'] as $k => $b ) for ( $d = 0; $d <= 6; $d++ ) foreach ( array( 'weekly' => 'reg', 'weekly_summer' => 'sum' ) as $opt => $f ) {
			$rows = array();
			foreach ( (array) ( $_POST[ $f ][ $k ][ $d ]['from'] ?? array() ) as $i => $from ) {
				$to = $_POST[ $f ][ $k ][ $d ]['to'][ $i ] ?? '';
				if ( $from && $to ) $rows[] = sanitize_text_field( $from ) . '-' . sanitize_text_field( $to );
			}
			$s[ $opt ][ $k ][ $d ] = $rows;
		}
		update_option( GYL_OPT, $s ); echo '<div class="notice notice-success"><p>נשמר.</p></div>'; $s = gyl_get();
	}
	echo '<div class="wrap gyl-adm"><h1>🕘 שעות פתיחה</h1><p>כל שורה = טווח פתיחה. המערכת מפצלת אותו לסבבים של ' . (int) $s['slot_len'] . ' דק׳. מחיקת כל השורות = יום סגור.</p>';
	echo '<form method="post">' . wp_nonce_field( 'gyl_h', '_wpnonce', true, false ) . '<input type="hidden" name="gyl_hours" value="1">';
	foreach ( $s['branches'] as $k => $b ) {
		echo '<h2>' . esc_html( $b['label'] ) . '</h2><table class="widefat striped"><thead><tr><th style="width:80px">יום</th><th>שגרה</th><th>קיץ / חג</th></tr></thead><tbody>';
		for ( $d = 0; $d <= 6; $d++ ) {
			echo '<tr><td><b>' . $he[ $d ] . '</b></td>';
			foreach ( array( 'weekly' => 'reg', 'weekly_summer' => 'sum' ) as $opt => $f ) {
				echo '<td><div class="gylrows" data-f="' . $f . '" data-k="' . $k . '" data-d="' . $d . '">';
				foreach ( (array) ( $s[ $opt ][ $k ][ $d ] ?? array() ) as $r ) {
					$p = explode( '-', $r );
					echo gyl_hour_row( $f, $k, $d, $p[0] ?? '', $p[1] ?? '' );
				}
				echo '</div><button type="button" class="button button-small gyladd">+ טווח</button></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}
	echo '<p><button class="button button-primary">שמירה</button></p></form>
	<script>
	function gylRow(f,k,d){return `<div class="gylrow"><input type="time" step="1800" name="${f}[${k}][${d}][from][]"> עד <input type="time" step="1800" name="${f}[${k}][${d}][to][]"> <a href="#" class="gyldel">✕</a></div>`;}
	document.addEventListener("click",e=>{
		if(e.target.classList.contains("gyladd")){e.preventDefault();
			const w=e.target.previousElementSibling;w.insertAdjacentHTML("beforeend",gylRow(w.dataset.f,w.dataset.k,w.dataset.d));}
		if(e.target.classList.contains("gyldel")){e.preventDefault();e.target.closest(".gylrow").remove();}
	});
	</script>
	<style>.gylrow{margin-bottom:4px}.gyldel{color:#b4553f;text-decoration:none;margin-right:6px}</style></div>';
}
function gyl_hour_row( $f, $k, $d, $from, $to ) {
	return '<div class="gylrow"><input type="time" step="1800" name="' . $f . '[' . $k . '][' . $d . '][from][]" value="' . esc_attr( $from ) . '"> עד <input type="time" step="1800" name="' . $f . '[' . $k . '][' . $d . '][to][]" value="' . esc_attr( $to ) . '"> <a href="#" class="gyldel">✕</a></div>';
}




/* ===========================================================
 * לוח חודשי — חסימה/פתיחה של כל סבב בלחיצה
 * =========================================================== */
function gyl_reasons() {
	return array( 'גן עם אמא', 'ללא גן עם אמא (ביטול חד-פעמי)', 'יום הולדת', 'אירוע פרטי', 'סגור', 'תחזוקה' );
}

/** אפשרויות לתיבת בחירה — תמיד כוללות את הסיבה הקיימת, גם אם נוצרה אוטומטית */
function gyl_reason_options( $cur, $suffix = '' ) {
	$list = gyl_reasons();
	if ( '' !== $cur && ! in_array( $cur, $list, true ) ) array_unshift( $list, $cur );
	$out = '';
	foreach ( $list as $r ) {
		$lbl = ( 0 === strpos( $r, 'אוטומטי: ' ) ) ? '🔒 ' . substr( $r, strlen( 'אוטומטי: ' ) ) : $r . $suffix;
		$out .= '<option value="' . esc_attr( $r ) . '"' . selected( $cur, $r, false ) . '>' . esc_html( $lbl ) . '</option>';
	}
	return $out;
}

function gyl_page_cal() {
	global $wpdb; $s = gyl_get();
	$he = array( 'ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת' );

	$branch = sanitize_key( $_REQUEST['b'] ?? array_key_first( $s['branches'] ) );
	$month  = sanitize_text_field( $_REQUEST['m'] ?? date( 'Y-m' ) );
	if ( ! isset( $s['branches'][ $branch ] ) ) $branch = array_key_first( $s['branches'] );
	list( $Y, $M ) = array_map( 'intval', explode( '-', $month ) );
	$days  = (int) date( 't', mktime( 0, 0, 0, $M, 1, $Y ) );
	$first = sprintf( '%04d-%02d-01', $Y, $M );
	$last  = sprintf( '%04d-%02d-%02d', $Y, $M, $days );

	if ( ! empty( $_POST['gyl_cal'] ) && check_admin_referer( 'gyl_cal' ) ) {
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}gyl_blocks WHERE branch=%s AND block_date BETWEEN %s AND %s",
			$branch, $first, $last ) );
		// שעות היום — נשמרות לפני החסימות
		$len = max( 30, (int) $s['slot_len'] );
		foreach ( (array) ( $_POST['day'] ?? array() ) as $date => $cfg ) {
			$date = sanitize_text_field( $date );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) continue;

			$typed = trim( sanitize_text_field( $cfg['hours'] ?? '' ) );
			$orig  = trim( sanitize_text_field( $cfg['hours_orig'] ?? '' ) );

			if ( $typed !== $orig ) {
				// ערכו את שדה השעות למעלה — הוא הקובע
				$norm = implode( ', ', array_filter( array_map( 'trim', explode( ',', $typed ) ) ) );
			} else {
				// בונים מחדש מזמני הסבבים שנערכו בצד
				$starts = array();
				foreach ( (array) ( $cfg['slots'] ?? array() ) as $row ) {
					$st = trim( sanitize_text_field( $row['start'] ?? '' ) );
					if ( preg_match( '/^\d{2}:\d{2}$/', $st ) ) $starts[] = $st;
				}
				sort( $starts );
				$ranges = array(); $open = null; $close = null;
				foreach ( $starts as $st ) {
					$e = date( 'H:i', strtotime( "2000-01-01 $st" ) + $len * 60 );
					if ( null !== $close && $st === $close ) { $close = $e; continue; }   // סבב רצוף — מאחדים
					if ( null !== $open ) $ranges[] = "$open-$close";
					$open = $st; $close = $e;
				}
				if ( null !== $open ) $ranges[] = "$open-$close";
				$norm = implode( ', ', $ranges );
			}
			$wpdb->replace( "{$wpdb->prefix}gyl_dayhours",
				array( 'branch' => $branch, 'day_date' => $date, 'ranges' => $norm ) );
		}

		$n = 0;
		foreach ( (array) ( $_POST['day'] ?? array() ) as $date => $cfg ) {
			$date = sanitize_text_field( $date );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) continue;
			if ( ! empty( $cfg['all'] ) ) {
				$wpdb->insert( "{$wpdb->prefix}gyl_blocks", array( 'branch' => $branch, 'block_date' => $date,
					'slot_start' => null, 'reason' => sanitize_text_field( $cfg['all'] ) ) );
				$n++; continue;
			}
			foreach ( (array) ( $cfg['slots'] ?? array() ) as $row ) {
				$st     = trim( sanitize_text_field( $row['start'] ?? '' ) );
				$reason = sanitize_text_field( $row['reason'] ?? '' );
				if ( '' === $reason || ! preg_match( '/^\d{2}:\d{2}$/', $st ) ) continue;
				$wpdb->insert( "{$wpdb->prefix}gyl_blocks", array( 'branch' => $branch, 'block_date' => $date,
					'slot_start' => $st . ':00', 'reason' => $reason ) );
				$n++;
			}
		}
		echo '<div class="notice notice-success"><p>נשמר. ' . (int) $n . ' חסימות בחודש זה.</p></div>';
	}

	// חסימות קיימות + הזמנות (לתצוגה)
	$blocks = array();
	foreach ( (array) $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}gyl_blocks WHERE branch=%s AND block_date BETWEEN %s AND %s", $branch, $first, $last ), ARRAY_A ) as $b ) {
		$k = $b['slot_start'] ? substr( $b['slot_start'], 0, 5 ) : 'all';
		$blocks[ $b['block_date'] ][ $k ] = $b['reason'];
	}
	$booked = array();
	foreach ( (array) $wpdb->get_results( $wpdb->prepare(
		"SELECT slot_date, slot_start, SUM(children) k FROM {$wpdb->prefix}gyl_bookings
		 WHERE branch=%s AND slot_date BETWEEN %s AND %s AND status IN ('confirmed','checked_in')
		 GROUP BY slot_date, slot_start", $branch, $first, $last ), ARRAY_A ) as $r ) {
		$booked[ $r['slot_date'] ][ substr( $r['slot_start'], 0, 5 ) ] = (int) $r['k'];
	}

	echo '<div class="wrap gyl-adm"><h1>📅 לוח חודשי</h1>';
	echo '<form method="get" style="margin-bottom:12px"><input type="hidden" name="page" value="gyl-cal">';
	echo '<select name="b">';
	foreach ( $s['branches'] as $k => $b ) echo '<option value="' . esc_attr( $k ) . '"' . selected( $branch, $k, false ) . '>' . esc_html( $b['label'] ) . '</option>';
	echo '</select> <input type="month" name="m" value="' . esc_attr( $month ) . '"> <button class="button">הצג</button></form>';

	$nb = 0; foreach ( $blocks as $x ) $nb += count( $x );
	echo '<p class="description"><b>' . (int) $nb . ' חסימות בחודש זה.</b> חסימות שנוצרו ב"בניית חודש" מסומנות ב-🔒. <b>שינוי שעות:</b> אפשר לערוך את שדה השעות בראש היום (<code>09:00-13:00, 16:00-19:00</code>), <b>או</b> לערוך ישירות את שעת ההתחלה של כל סבב בצד — ולהוסיף/למחוק סבבים עם <b>+ סבב</b> ו-<b>✕</b>. שדה שעות ריק = היום סגור. לחצו על סבב כדי לחסום אותו ובחרו סיבה. סבב עם הזמנות קיימות מסומן במספר ירוק — חסימה לא מבטלת אותן, רק מונעת הזמנות חדשות.</p>';
	echo '<form method="post">' . wp_nonce_field( 'gyl_cal', '_wpnonce', true, false ) . '<input type="hidden" name="gyl_cal" value="1">';

	echo '<table class="gylcal"><thead><tr>';
	foreach ( $he as $d ) echo '<th>' . $d . '</th>';
	echo '</tr></thead><tbody><tr>';

	$pad = (int) date( 'w', strtotime( $first ) );
	for ( $i = 0; $i < $pad; $i++ ) echo '<td class="off"></td>';
	$col = $pad;

	for ( $d = 1; $d <= $days; $d++ ) {
		$date  = sprintf( '%04d-%02d-%02d', $Y, $M, $d );
		$slots = gyl_slots_for( $branch, $date );
		$dayAll = $blocks[ $date ]['all'] ?? '';
		$past  = $date < date( 'Y-m-d' );

		$ov   = gyl_day_override( $branch, $date );
		$tpl  = implode( ', ', gyl_template_ranges( $branch, $date ) );
		$val  = ( null !== $ov ) ? $ov : $tpl;
		$diff = ( null !== $ov && $ov !== $tpl );
		$shut = ( null !== $ov && '' === $ov );
		echo '<td class="' . ( $past ? 'pastday' : '' ) . ( $diff ? ' custom' : '' ) . '"><div class="dnum">' . $d . ( $shut ? ' <span class="ovtag closed">סגור</span>' : ( $diff ? ' <span class="ovtag">שונה</span>' : '' ) ) . '</div>';
		echo '<input type="hidden" name="day[' . $date . '][hours_orig]" value="' . esc_attr( $val ) . '">';
		echo '<input class="hrs' . ( $diff ? ' isov' : '' ) . '" name="day[' . $date . '][hours]" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $tpl ?: 'סגור' ) . '" title="טווחי שעות מופרדים בפסיק. ריק = סגור">';
		echo '<select class="allsel' . ( $dayAll ? ' isblocked' : '' ) . '" name="day[' . $date . '][all]"><option value="">— פתוח —</option>';
		echo gyl_reason_options( $dayAll, ' (כל היום)' );
		echo '</select>';

		echo '<div class="rows" data-date="' . esc_attr( $date ) . '">';
		if ( ! $slots ) echo '<div class="none">אין סבבים</div>';
		$i = 0;
		foreach ( $slots as $sl ) {
			$cur = $blocks[ $date ][ $sl['start'] ] ?? '';
			$k   = $booked[ $date ][ $sl['start'] ] ?? 0;
			echo '<div class="slotrow' . ( $cur ? ' blocked' : '' ) . '">';
			echo '<input type="time" step="1800" class="st" name="day[' . $date . '][slots][' . $i . '][start]" value="' . esc_attr( $sl['start'] ) . '" title="שעת התחלת הסבב — ניתן לערוך">';
			echo '<select name="day[' . $date . '][slots][' . $i . '][reason]"><option value="">פתוח</option>';
			echo gyl_reason_options( $cur );
			echo '</select>';
			if ( $k ) echo '<span class="bk" title="ילדים מוזמנים">' . $k . '</span>';
			echo '<a href="#" class="rm" title="מחיקת סבב">✕</a></div>';
			$i++;
		}
		echo '</div><a href="#" class="addrow" data-date="' . esc_attr( $date ) . '">+ סבב</a>';
		echo '</td>';

		$col++;
		if ( 0 === $col % 7 && $d < $days ) echo '</tr><tr>';
	}
	while ( 0 !== $col % 7 ) { echo '<td class="off"></td>'; $col++; }
	echo '</tr></tbody></table>';
	echo '<p><button class="button button-primary button-large">שמירת החודש</button></p></form>';

	echo '<style>
	.gylcal{width:100%;border-collapse:collapse;table-layout:fixed;direction:rtl}
	.gylcal th{background:#7C8C63;color:#fff;padding:7px;font-size:12px}
	.gylcal td{border:1px solid #E6DFD1;vertical-align:top;padding:5px;background:#fff;height:120px}
	.gylcal td.off{background:#F4F1E8}
	.gylcal td.pastday{background:#FAF9F6;opacity:.6}
	.dnum{font-weight:700;color:#3C3A34;margin-bottom:3px}
	.allsel{width:100%;font-size:11px;margin-bottom:4px;border-color:#E0D8C8}
	.slotrow{display:flex;align-items:center;gap:3px;margin-bottom:2px}
	.slotrow .t{font-size:11px;color:#A79684;min-width:32px}
	.slotrow select{font-size:11px;flex:1;min-height:24px;border-color:#E0D8C8}
	.slotrow.blocked select{background:#FBEDE9;border-color:#B4553F;color:#B4553F}
	.allsel.isblocked{background:#FBEDE9;border-color:#B4553F;color:#B4553F;font-weight:700}
	.slotrow .bk{font-size:10px;background:#7C8C63;color:#fff;border-radius:8px;padding:1px 5px}
	.none{font-size:11px;color:#A79684}
	.hrs{width:100%;font-size:11px;margin-bottom:4px;padding:2px 4px;border:1px solid #E0D8C8;border-radius:4px;direction:ltr;text-align:center}
	.hrs.isov{border-color:#D8A24A;background:#FFFAF0;font-weight:700}
	.gylcal td.custom{background:#FFFDF6}
	.ovtag{font-size:9px;background:#D8A24A;color:#fff;border-radius:6px;padding:1px 4px;font-weight:400}
	.ovtag.closed{background:#B4553F}
	.slotrow .st{width:62px;font-size:11px;padding:1px 2px;border:1px solid #E0D8C8;border-radius:4px;direction:ltr}
	.slotrow .rm{color:#B4553F;text-decoration:none;font-size:11px}
	.addrow{font-size:11px;color:#7C8C63;text-decoration:none;display:inline-block;margin-top:2px}
	</style>
	<script>
	document.addEventListener("click", function (e) {
		if (e.target.classList.contains("addrow")) { e.preventDefault();
			var d = e.target.dataset.date, w = e.target.previousElementSibling;
			var i = w.querySelectorAll(".slotrow").length + 90;
			var html = "<div class=\"slotrow\"><input type=\"time\" step=\"1800\" class=\"st\" name=\"day[" + d + "][slots][" + i + "][start]\" value=\"\">" +
				"<select name=\"day[" + d + "][slots][" + i + "][reason]\"><option value=\"\">פתוח</option></select>" +
				"<a href=\"#\" class=\"rm\">✕</a></div>";
			var n = w.querySelector(".none"); if (n) n.remove();
			w.insertAdjacentHTML("beforeend", html);
		}
		if (e.target.classList.contains("rm")) { e.preventDefault(); e.target.closest(".slotrow").remove(); }
	});
	</script>
	<style>
	</style></div>';
}

/* ===========================================================
 * בניית חודש מתבנית + חגי ישראל (Hebcal)
 * =========================================================== */
function gyl_fetch_holidays( $year, $month ) {
	$key = "gyl_hol_{$year}_{$month}";
	$hit = get_transient( $key );
	if ( false !== $hit ) return $hit;

	$url = add_query_arg( array(
		'v' => 1, 'cfg' => 'json', 'maj' => 'on', 'min' => 'off', 'mod' => 'off',
		'nx' => 'off', 'ss' => 'off', 'mf' => 'off', 'c' => 'off',
		'geo' => 'none', 'i' => 'on', 'year' => $year, 'month' => $month,
	), 'https://www.hebcal.com/hebcal' );

	$r = wp_remote_get( $url, array( 'timeout' => 12 ) );
	if ( is_wp_error( $r ) ) return array();
	$j = json_decode( wp_remote_retrieve_body( $r ), true );
	$out = array();

	foreach ( (array) ( $j['items'] ?? array() ) as $it ) {
		if ( 'holiday' !== ( $it['category'] ?? '' ) ) continue;
		$d    = substr( $it['date'], 0, 10 );
		$name = $it['hebrew'] ?? $it['title'];
		$t    = $it['title'];

		if ( ! empty( $it['yomtov'] ) )                              $type = 'yomtov';
		elseif ( 0 === stripos( $t, 'Erev' ) )                       $type = 'erev';
		elseif ( false !== stripos( $t, 'CH\'\'M' ) || false !== stripos( $t, 'Chol haMoed' ) ) $type = 'cholhamoed';
		else                                                         continue;

		// חג גובר על ערב חג באותו תאריך
		if ( isset( $out[ $d ] ) && 'yomtov' === $out[ $d ]['type'] ) continue;
		$out[ $d ] = array( 'type' => $type, 'name' => $name );
	}
	set_transient( $key, $out, DAY_IN_SECONDS );
	return $out;
}

function gyl_page_month() {
	global $wpdb; $s = gyl_get();
	$he_days = array( 'ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת' );

	$month    = sanitize_text_field( $_POST['month'] ?? date( 'Y-m' ) );
	$mom_days = array_map( 'intval', (array) ( $_POST['mom_days'] ?? array() ) );
	$mom_from = sanitize_text_field( $_POST['mom_from'] ?? '09:00' );
	$mom_to   = sanitize_text_field( $_POST['mom_to'] ?? '11:00' );
	$erev_to  = sanitize_text_field( $_POST['erev_to'] ?? '13:00' );
	$brs      = array_map( 'sanitize_key', (array) ( $_POST['brs'] ?? array_keys( $s['branches'] ) ) );

	list( $Y, $M ) = array_map( 'intval', explode( '-', $month ) );
	$build   = ! empty( $_POST['build'] ) && check_admin_referer( 'gyl_m' );
	$preview = ! empty( $_POST['preview'] ) && check_admin_referer( 'gyl_m' );
	$plan = array(); $note = '';

	if ( ( $build || $preview ) && ! $brs ) {
		echo '<div class="notice notice-error"><p><b>בחרו לפחות סניף אחד.</b></p></div>';
		$build = $preview = false;
	}

	if ( $build || $preview ) {
		$hol  = gyl_fetch_holidays( $Y, $M );
		$days = (int) date( 't', mktime( 0, 0, 0, $M, 1, $Y ) );
		$note = $hol ? 'נמצאו ' . count( $hol ) . ' ימי חג / ערב חג / חול המועד' : 'אין חגים בחודש זה';

		for ( $d = 1; $d <= $days; $d++ ) {
			$date = sprintf( '%04d-%02d-%02d', $Y, $M, $d );
			$dow  = (int) date( 'w', strtotime( $date ) );
			$h    = $hol[ $date ] ?? null;
			$tpl  = gyl_template_ranges( $brs[0] ?? 'rishon', $date );

			foreach ( $brs as $b ) {
				$ranges = gyl_template_ranges( $b, $date );
				$why    = '';

				if ( $h && 'yomtov' === $h['type'] ) {
					$ranges = array();                       // חג — סגור
					$why    = $h['name'] . ' — חג, סגור';
				} elseif ( $h && 'erev' === $h['type'] ) {   // ערב חג — קיצור
					$cut = array();
					foreach ( $ranges as $r ) {
						$p = array_map( 'trim', explode( '-', $r ) );
						if ( count( $p ) !== 2 || $p[0] >= $erev_to ) continue;
						$cut[] = $p[0] . '-' . ( $p[1] > $erev_to ? $erev_to : $p[1] );
					}
					$ranges = $cut;
					$why    = $h['name'] . " — סגירה ב$erev_to";
				} elseif ( $h && 'cholhamoed' === $h['type'] ) {
					$why = $h['name'];
				}

				$mom_here = in_array( $dow, $mom_days, true )
					&& ( empty( $s['mom']['enabled'] ) || in_array( $b, (array) $s['mom']['branches'], true ) );
				$plan[ $date ][ $b ] = array( 'ranges' => $ranges, 'why' => $why, 'mom' => $mom_here );
			}
		}
	}

	if ( $build ) {
		$first = sprintf( '%04d-%02d-01', $Y, $M );
		$last  = sprintf( '%04d-%02d-%02d', $Y, $M, (int) date( 't', mktime( 0, 0, 0, $M, 1, $Y ) ) );
		$nh = $nb = 0;
		foreach ( $brs as $b ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}gyl_dayhours WHERE branch=%s AND day_date BETWEEN %s AND %s", $b, $first, $last ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}gyl_blocks WHERE branch=%s AND reason LIKE %s AND block_date BETWEEN %s AND %s", $b, 'אוטומטי:%', $first, $last ) );
		}
		foreach ( $plan as $date => $per ) {
			foreach ( $per as $b => $p ) {
				$wpdb->replace( "{$wpdb->prefix}gyl_dayhours", array(
					'branch' => $b, 'day_date' => $date, 'ranges' => implode( ', ', $p['ranges'] ) ) );
				$nh++;
				if ( $p['mom'] && $p['ranges'] ) {          // חסימת סבבי "גן עם אמא"
					foreach ( gyl_slots_for_ranges( $p['ranges'], $date ) as $sl ) {
						if ( $sl['start'] < $mom_to && $sl['end'] > $mom_from ) {
							$wpdb->insert( "{$wpdb->prefix}gyl_blocks", array( 'branch' => $b, 'block_date' => $date,
								'slot_start' => $sl['start'] . ':00', 'reason' => 'אוטומטי: גן עם אמא' ) );
							$nb++;
						}
					}
				}
			}
		}
		echo '<div class="notice notice-success"><p><b>החודש נבנה.</b> ' . (int) $nh . ' ימים נכתבו · ' . (int) $nb . ' סבבי "גן עם אמא" נחסמו. ' . esc_html( $note ) . '</p><p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=gyl-cal&m=' . $month . '&b=' . ( $brs[0] ?? '' ) ) ) . '">פתיחת הלוח החודשי ←</a></p></div>';
	} elseif ( $preview ) {
		echo '<div class="notice notice-info"><p>' . esc_html( $note ) . ' — זו תצוגה מקדימה, עדיין לא נשמר.</p></div>';
	}

	echo '<div class="wrap gyl-adm"><h1>✨ בניית חודש</h1>';
	echo '<p>בונה את החודש כולו לפי התבנית השבועית: כל יום מקבל את השעות שלו, חגים נסגרים, ערבי חג מתקצרים, וסבבי "גן עם אמא" נחסמים. אחר כך עוברים ללוח החודשי ומשנים ימים ספציפיים.</p>';

	echo '<form method="post">' . wp_nonce_field( 'gyl_m', '_wpnonce', true, false ) . '<table class="form-table">';
	echo '<tr><th>חודש</th><td><input type="month" name="month" value="' . esc_attr( $month ) . '"></td></tr>';
	echo '<tr><th>סניפים</th><td>';
	foreach ( $s['branches'] as $k => $b )
		echo '<label style="margin-left:14px"><input type="checkbox" name="brs[]" value="' . esc_attr( $k ) . '" ' . checked( in_array( $k, $brs, true ), true, false ) . '> ' . esc_html( $b['label'] ) . '</label>';
	echo '</td></tr>';
	$momset = gyl_mom();
	$mom_lbls = array();
	foreach ( (array) $momset['branches'] as $mb ) $mom_lbls[] = $s['branches'][ $mb ]['label'] ?? $mb;
	echo '<tr><th>גן עם אמא — באילו ימים?</th><td>';
	for ( $i = 0; $i <= 6; $i++ )
		echo '<label style="margin-left:12px"><input type="checkbox" name="mom_days[]" value="' . $i . '" ' . checked( in_array( $i, $mom_days, true ), true, false ) . '> ' . $he_days[ $i ] . '</label>';
	echo '<br><br>שעות: <input type="time" step="1800" name="mom_from" value="' . esc_attr( $mom_from ) . '"> עד <input type="time" step="1800" name="mom_to" value="' . esc_attr( $mom_to ) . '">';
	echo '<br><span class="description">הסבבים החופפים ייחסמו — <b>רק בסניפים שבהם גן עם אמא פעיל</b> (' . esc_html( implode( ', ', $mom_lbls ) ?: 'אף סניף' ) . '). לשינוי — הגדרות ← 👶 גן עם אמא</span></td></tr>';
	echo '<tr><th>ערב חג — סגירה בשעה</th><td><input type="time" step="1800" name="erev_to" value="' . esc_attr( $erev_to ) . '"></td></tr>';
	echo '</table>';
	echo '<p><button class="button button-secondary" name="preview" value="1">תצוגה מקדימה</button> ';
	echo '<button class="button button-primary" name="build" value="1" onclick="return confirm(\'לבנות את החודש? שעות וחסימות אוטומטיות קיימות בחודש זה יוחלפו.\')">בנה חודש</button></p></form>';

	if ( $plan ) {
		echo '<h2>' . ( $build ? 'מה נבנה' : 'תצוגה מקדימה' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>תאריך</th><th>יום</th>';
		foreach ( $brs as $b ) echo '<th>' . esc_html( $s['branches'][ $b ]['label'] ?? $b ) . '</th>';
		echo '<th>הערה</th></tr></thead><tbody>';
		foreach ( $plan as $date => $per ) {
			$dow = (int) date( 'w', strtotime( $date ) );
			echo '<tr><td>' . date_i18n( 'j/n', strtotime( $date ) ) . '</td><td>' . $he_days[ $dow ] . '</td>';
			$why = '';
			foreach ( $brs as $b ) {
				$p = $per[ $b ];
				$txt = $p['ranges'] ? implode( ', ', $p['ranges'] ) : '<span style="color:#B4553F">סגור</span>';
				if ( $p['mom'] && $p['ranges'] ) $txt .= ' <span style="color:#D8A24A">· גן עם אמא</span>';
				echo '<td>' . $txt . '</td>';
				if ( $p['why'] ) $why = $p['why'];
			}
			echo '<td><b>' . esc_html( $why ) . '</b></td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '</div>';
}

function gyl_kinds() {
	return array(
		'birthday' => '🎂 יום הולדת',
		'private'  => '🎉 אירוע פרטי',
		'mom'      => '👶 גן עם אמא',
		'closed'   => '🚫 סגור',
		'other'    => '🔧 אחר',
	);
}



function gyl_page_leads() {
	global $wpdb; $s = gyl_get(); $br = $s['branches'];
	if ( isset( $_GET['done'] ) && check_admin_referer( 'gyl_ld' ) )
		$wpdb->update( "{$wpdb->prefix}gyl_leads", array( 'status' => 'closed' ), array( 'id' => (int) $_GET['done'] ) );
	if ( isset( $_GET['lost'] ) && check_admin_referer( 'gyl_ld' ) )
		$wpdb->update( "{$wpdb->prefix}gyl_leads", array( 'status' => 'lost' ), array( 'id' => (int) $_GET['lost'] ) );

	$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}gyl_leads ORDER BY created_at DESC LIMIT 200", ARRAY_A );
	$new  = 0; foreach ( $rows as $r ) if ( 'new' === $r['status'] ) $new++;

	echo '<div class="wrap gyl-adm"><h1>🎂 לידים ליום הולדת</h1>';
	echo '<div class="gyl-stats"><div class="gyl-stat amber"><div class="n">' . (int) $new . '</div><div class="l">לידים חדשים</div></div>';
	echo '<div class="gyl-stat"><div class="n">' . count( $rows ) . '</div><div class="l">סה״כ</div></div>';
	echo '<div class="gyl-stat"><div class="n">' . number_format( $new * (int) $s['birthday']['from'] ) . ' ₪</div><div class="l">פוטנציאל בלידים הפתוחים</div></div></div>';
	echo '<div class="gyl-help">ליד ליום הולדת מתקרר תוך שעות. כדאי לחזור באותו יום — זו העסקה הגדולה ביותר שלכם.</div>';

	if ( ! $rows ) echo '<div class="gyl-empty">אין לידים עדיין.</div>';
	echo '<table class="widefat striped" style="max-width:1000px"><thead><tr><th>התקבל</th><th>שם</th><th>טלפון</th><th>סניף</th><th>תאריך מבוקש</th><th>ילדים</th><th>סטטוס</th><th></th></tr></thead><tbody>';
	foreach ( (array) $rows as $r ) {
		$tag = 'new' === $r['status'] ? '<span class="gyl-tag pend">חדש</span>'
			: ( 'closed' === $r['status'] ? '<span class="gyl-tag ok">נסגר ✓</span>' : '<span class="gyl-tag punch">לא רלוונטי</span>' );
		echo '<tr><td>' . date_i18n( 'j/n H:i', strtotime( $r['created_at'] ) ) . '</td>';
		echo '<td><b>' . esc_html( $r['name'] ) . '</b></td>';
		echo '<td><a href="tel:' . esc_attr( $r['phone'] ) . '">' . esc_html( $r['phone'] ) . '</a></td>';
		echo '<td>' . esc_html( $br[ $r['branch'] ]['label'] ?? '—' ) . '</td>';
		echo '<td>' . ( $r['party_date'] ? date_i18n( 'j/n/Y', strtotime( $r['party_date'] ) ) : '—' ) . '</td>';
		echo '<td>' . (int) $r['guests'] . '</td><td>' . $tag . '</td><td>';
		if ( 'new' === $r['status'] ) {
			echo '<a class="gyl-btn-s" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=gyl-leads&done=' . $r['id'] ), 'gyl_ld' ) ) . '">נסגר</a> ';
			echo '<a class="gyl-btn-o" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=gyl-leads&lost=' . $r['id'] ), 'gyl_ld' ) ) . '">לא רלוונטי</a>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table></div>';
}

function gyl_page_mom() {
	global $wpdb; $s = gyl_get(); $m = gyl_mom();
	$branch = $m['branches'][0] ?? 'rishon';

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}gyl_bookings WHERE service='mom' AND status<>'cancelled'
		 AND slot_date >= CURDATE() ORDER BY slot_date, name", ), ARRAY_A );

	$bysession = array();
	foreach ( $rows as $r ) $bysession[ $r['slot_date'] ][] = $r;

	$enrolled = $wpdb->get_results(
		"SELECT series, name, phone, tier, MIN(slot_date) first_d, COUNT(*) n, MAX(status) st
		 FROM {$wpdb->prefix}gyl_bookings WHERE service='mom' AND status<>'cancelled'
		 GROUP BY series ORDER BY first_d DESC LIMIT 60", ARRAY_A );

	echo '<div class="wrap gyl-adm"><h1>👶 גן עם אמא</h1>';
	if ( empty( $m['enabled'] ) ) echo '<div class="gyl-help">התוכנית כבויה. אפשר להפעיל בהגדרות ← לשונית "גן עם אמא".</div>';

	$he = array( 'ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת' );
	$days = implode( ', ', array_map( function ( $d ) use ( $he ) { return $he[ (int) $d ]; }, (array) $m['days'] ) );
	echo '<div class="gyl-help">' . esc_html( $s['branches'][ $branch ]['label'] ?? '' ) . ' · ימי ' . esc_html( $days ) . ' · ' .
		esc_html( $m['from'] . '–' . $m['to'] ) . ' · עד ' . (int) $m['capacity'] . ' ילדים · ' . esc_html( $m['age'] ) .
		'<br>שורטקוד להטמעה: <code>[gayaland_mom_button text="הרשמה לגן עם אמא"]</code></div>';

	echo '<h2>מפגשים קרובים</h2>';
	$sessions = gyl_mom_sessions( $branch, 42 );
	if ( ! $sessions ) echo '<div class="gyl-empty">אין מפגשים מתוכננים.</div>';
	foreach ( $sessions as $d ) {
		$list = $bysession[ $d ] ?? array();
		$k    = array_sum( array_map( function ( $x ) { return (int) $x['children']; }, $list ) );
		$cap  = (int) $m['capacity'];
		$pct  = $cap ? min( 100, round( $k / $cap * 100 ) ) : 0;
		echo '<div class="gyl-round"><div class="gyl-round-h"><b>' . date_i18n( 'l, j/n', strtotime( $d ) ) . '</b>';
		echo '<span class="gyl-cap"><span class="gyl-bar' . ( $k >= $cap ? ' full' : '' ) . '"><i style="width:' . $pct . '%"></i></span>' . $k . ' / ' . $cap . '</span></div>';
		if ( ! $list ) echo '<div class="gyl-empty">אין נרשמות.</div>';
		foreach ( $list as $r ) {
			$tag = 'pending' === $r['status'] ? '<span class="gyl-tag pend">ממתין לתשלום</span>'
				: ( 'checked_in' === $r['status'] ? '<span class="gyl-tag in">✓ הגיעו</span>' : '<span class="gyl-tag ok">שולם</span>' );
			$tl = $m['tiers'][ $r['tier'] ]['label'] ?? $r['tier'];
			echo '<div class="gyl-guest"><span class="nm">' . esc_html( $r['name'] ) . '</span>';
			echo '<a class="ph" href="tel:' . esc_attr( $r['phone'] ) . '">' . esc_html( $r['phone'] ) . '</a>';
			echo '<span class="gyl-tag punch">' . esc_html( $tl ) . '</span> ' . $tag;
			if ( $r['notes'] ) echo ' <span class="gyl-cap">' . esc_html( $r['notes'] ) . '</span>';
			echo '</div>';
		}
		echo '</div>';
	}

	echo '<h2>נרשמות (לפי הרשמה)</h2>';
	echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>שם</th><th>טלפון</th><th>מסלול</th><th>מפגש ראשון</th><th>מפגשים</th><th>סטטוס</th></tr></thead><tbody>';
	foreach ( (array) $enrolled as $e ) {
		$tl = $m['tiers'][ $e['tier'] ]['label'] ?? $e['tier'];
		echo '<tr><td><b>' . esc_html( $e['name'] ) . '</b></td><td>' . esc_html( $e['phone'] ) . '</td>';
		echo '<td>' . esc_html( $tl ) . '</td><td>' . date_i18n( 'j/n/Y', strtotime( $e['first_d'] ) ) . '</td>';
		echo '<td>' . (int) $e['n'] . '</td><td>' . ( 'pending' === $e['st'] ? '<span class="gyl-tag pend">ממתין</span>' : '<span class="gyl-tag ok">שולם</span>' ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
}

function gyl_page_blocks() {
	global $wpdb; $s = gyl_get(); $br = $s['branches']; $kinds = gyl_kinds();

	/* הוספה */
	if ( ! empty( $_POST['gyl_add'] ) && check_admin_referer( 'gyl_bl' ) ) {
		$branch = sanitize_key( $_POST['branch'] );
		$date   = sanitize_text_field( $_POST['date'] );
		$kind   = isset( $kinds[ $_POST['kind'] ] ) ? $_POST['kind'] : 'closed';
		$note   = sanitize_text_field( $_POST['note'] );
		$rounds = (array) ( $_POST['rounds'] ?? array() );
		$allday = ! empty( $_POST['allday'] );
		$label  = $note ?: preg_replace( '/^\S+\s/u', '', $kinds[ $kind ] );

		if ( ! isset( $br[ $branch ] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			echo '<div class="notice notice-error"><p>סניף או תאריך לא תקינים.</p></div>';
		} elseif ( ! $allday && ! $rounds ) {
			echo '<div class="notice notice-error"><p>בחרו לפחות סבב אחד, או סמנו "כל היום".</p></div>';
		} else {
			if ( $allday ) {
				$wpdb->insert( "{$wpdb->prefix}gyl_blocks", array( 'branch' => $branch, 'block_date' => $date,
					'slot_start' => null, 'kind' => $kind, 'reason' => $label ) );
				$n = 1;
			} else {
				$n = 0;
				foreach ( $rounds as $t ) {
					if ( ! preg_match( '/^\d{2}:\d{2}$/', $t ) ) continue;
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}gyl_blocks WHERE branch=%s AND block_date=%s AND slot_start=%s",
						$branch, $date, $t . ':00' ) );
					$wpdb->insert( "{$wpdb->prefix}gyl_blocks", array( 'branch' => $branch, 'block_date' => $date,
						'slot_start' => $t . ':00', 'kind' => $kind, 'reason' => $label ) );
					$n++;
				}
			}
			echo '<div class="notice notice-success"><p><b>נוסף.</b> ' . (int) $n . ' חסימות ב' . esc_html( $br[ $branch ]['label'] ) .
				' · ' . esc_html( date_i18n( 'j/n/Y', strtotime( $date ) ) ) . ' — ' . esc_html( $label ) . '</p></div>';
		}
	}

	if ( isset( $_GET['del'] ) && check_admin_referer( 'gyl_bd' ) ) {
		$wpdb->delete( "{$wpdb->prefix}gyl_blocks", array( 'id' => (int) $_GET['del'] ) );
		echo '<div class="notice notice-success"><p>נמחק.</p></div>';
	}

	echo '<div class="wrap gyl-adm"><h1>🚫 אירועים וחסימות</h1>';
	echo '<p class="description">כאן חוסמים סבבים לאירועים — יום הולדת, גן עם אמא, אירוע פרטי. סבב חסום לא ניתן להזמנה באתר. לעריכה של חודש שלם השתמשו ב<b>לוח חודשי</b>.</p>';

	/* --- טופס הוספה --- */
	echo '<div class="gylbox"><h2 style="margin-top:0">➕ חסימה חדשה</h2>';
	echo '<form method="post" id="gylf">' . wp_nonce_field( 'gyl_bl', '_wpnonce', true, false ) . '<input type="hidden" name="gyl_add" value="1">';
	echo '<table class="form-table" style="margin:0"><tr><th style="width:120px">סניף</th><td><select name="branch" id="f_branch">';
	foreach ( $br as $k => $b ) echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $b['label'] ) . '</option>';
	echo '</select></td></tr>';
	echo '<tr><th>תאריך</th><td><input type="date" name="date" id="f_date" value="' . esc_attr( date( 'Y-m-d' ) ) . '" min="' . esc_attr( date( 'Y-m-d' ) ) . '" required></td></tr>';
	echo '<tr><th>אילו סבבים?</th><td><div id="f_rounds" class="rounds">בחרו תאריך…</div>';
	echo '<label style="display:block;margin-top:8px"><input type="checkbox" name="allday" id="f_all"> <b>כל היום סגור</b></label></td></tr>';
	echo '<tr><th>סוג</th><td><select name="kind">';
	foreach ( $kinds as $k => $lbl ) echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $lbl ) . '</option>';
	echo '</select></td></tr>';
	echo '<tr><th>פרטים</th><td><input type="text" name="note" style="width:380px" placeholder="למשל: יום הולדת ליהלי — 15 ילדים"><br><span class="description">אופציונלי. יופיע לכם בלוח, לא ללקוחות.</span></td></tr>';
	echo '</table><p><button class="button button-primary button-large">חסימה</button></p></form></div>';

	/* --- רשימת אירועים קרובים --- */
	$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}gyl_blocks WHERE block_date >= CURDATE() ORDER BY block_date, slot_start LIMIT 300", ARRAY_A );
	echo '<h2>אירועים קרובים <span style="color:#7C8C63">(' . count( $rows ) . ')</span></h2>';
	if ( ! $rows ) {
		echo '<p><i>אין חסימות עתידיות.</i></p>';
	} else {
		echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>תאריך</th><th>יום</th><th>סניף</th><th>סבב</th><th>סוג</th><th>פרטים</th><th></th></tr></thead><tbody>';
		$he = array( 'א', 'ב', 'ג', 'ד', 'ה', 'ו', 'ש' );
		$prev = '';
		foreach ( $rows as $r ) {
			$auto = ( 0 === strpos( $r['reason'], 'אוטומטי: ' ) );
			$kind = $r['kind'] ?: 'closed';
			$sep  = ( $prev && $prev !== $r['block_date'] ) ? ' style="border-top:2px solid #E6DFD1"' : '';
			$prev = $r['block_date'];
			echo '<tr' . $sep . '><td><b>' . date_i18n( 'j/n', strtotime( $r['block_date'] ) ) . '</b></td>';
			echo '<td>' . $he[ (int) date( 'w', strtotime( $r['block_date'] ) ) ] . '׳</td>';
			echo '<td>' . esc_html( $br[ $r['branch'] ]['label'] ?? $r['branch'] ) . '</td>';
			echo '<td>' . ( $r['slot_start'] ? substr( $r['slot_start'], 0, 5 ) : '<b style="color:#B4553F">כל היום</b>' ) . '</td>';
			echo '<td>' . esc_html( $kinds[ $kind ] ?? '' ) . '</td>';
			echo '<td>' . esc_html( str_replace( 'אוטומטי: ', '', $r['reason'] ) ) . ( $auto ? ' <span class="pill">אוטומטי</span>' : '' ) . '</td>';
			echo '<td><a class="button button-small" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=gyl-blocks&del=' . $r['id'] ), 'gyl_bd' ) ) . '">מחיקה</a></td></tr>';
		}
		echo '</tbody></table>';
	}

	echo '<style>
	.gylbox{background:#fff;border:1px solid #E6DFD1;border-radius:14px;padding:16px;max-width:900px;margin:14px 0}
	.rounds{display:flex;flex-wrap:wrap;gap:6px}
	.rounds label{background:#FAF7F0;border:1.5px solid #E0D8C8;border-radius:10px;padding:6px 10px;cursor:pointer;font-size:13px}
	.rounds label.taken{border-color:#B4553F;background:#FBEDE9;color:#B4553F}
	.rounds label input{margin-left:4px}
	.pill{background:#D8A24A;color:#fff;font-size:10px;border-radius:8px;padding:1px 6px}
	</style>
	<script>
	(function(){
		var API = "' . esc_url_raw( rest_url( 'gayaland/v1/availability' ) ) . '";
		var br = document.getElementById("f_branch"), dt = document.getElementById("f_date"),
		    box = document.getElementById("f_rounds"), all = document.getElementById("f_all");
		if(!br || !box){ return; }   // הטופס הזה לא במסך — לא ממשיכים
		function load(){
			if(!dt.value){ box.textContent = "בחרו תאריך…"; return; }
			box.textContent = "טוען…";
			fetch(API + "?branch=" + br.value + "&date=" + dt.value)
				.then(function(r){ return r.json(); })
				.then(function(d){
					box.innerHTML = "";
					if(!d.slots || !d.slots.length){ box.innerHTML = "<i>אין סבבים ביום זה — היום סגור.</i>"; return; }
					d.slots.forEach(function(s){
						var taken = (s.status === "blocked");
						var lb = document.createElement("label");
						if(taken) lb.className = "taken";
						lb.innerHTML = "<input type=\"checkbox\" name=\"rounds[]\" value=\"" + s.start + "\">" +
							s.start + "–" + s.end + (taken ? " · " + (s.reason || "חסום") : "");
						box.appendChild(lb);
					});
				})
				.catch(function(){ box.innerHTML = "<i>שגיאה בטעינת הסבבים.</i>"; });
		}
		if(br) br.addEventListener("change", load);
		if(dt) dt.addEventListener("change", load);
		if(all) all.addEventListener("change", function(){ box.style.opacity = all.checked ? .35 : 1; });
		if(br&&dt&&box) load();
	})();
	</script>';
	echo '</div>';
}

function gyl_page_feedback() {
	global $wpdb; $br = gyl_get( 'branches' );
	$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}gyl_bookings WHERE rating>0 ORDER BY slot_date DESC LIMIT 200", ARRAY_A );
	$avg  = $wpdb->get_var( "SELECT ROUND(AVG(rating),2) FROM {$wpdb->prefix}gyl_bookings WHERE rating>0" );
	echo '<div class="wrap gyl-adm"><h1>⭐ משובים</h1><p>דירוג ממוצע: <b style="font-size:1.3em">' . ( $avg ?: '—' ) . '</b> ★ · ' . count( $rows ) . ' תשובות</p>';
	echo '<table class="widefat striped"><thead><tr><th>דירוג</th><th>תאריך ביקור</th><th>סניף</th><th>לקוח</th><th>טלפון</th><th>מה כתבו</th></tr></thead><tbody>';
	foreach ( (array) $rows as $r ) {
		$c = $r['rating'] >= 4 ? '#7C8C63' : ( $r['rating'] <= 2 ? '#B4553F' : '#D8A24A' );
		echo '<tr><td style="color:' . $c . ';font-weight:700">' . str_repeat( '★', (int) $r['rating'] ) . '</td><td>' . $r['slot_date'] . '</td><td>' . esc_html( $br[ $r['branch'] ]['label'] ?? '' ) . '</td><td>' . esc_html( $r['name'] ) . '</td><td>' . esc_html( $r['phone'] ) . '</td><td>' . esc_html( $r['feedback'] ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
}

function gyl_page_credits() {
	global $wpdb;
	if ( ! empty( $_POST['gyl_c'] ) && check_admin_referer( 'gyl_cr' ) )
		gyl_punch_add( $_POST['phone'], (int) $_POST['delta'], sanitize_text_field( $_POST['note'] ) ?: 'עדכון ידני' );
	echo '<div class="wrap gyl-adm"><h1>🎟️ כרטיסיות</h1>';
	echo '<form method="post">' . wp_nonce_field( 'gyl_cr', '_wpnonce', true, false ) . '<input type="hidden" name="gyl_c" value="1">';
	echo 'טלפון <input type="tel" name="phone" required> כניסות (+/-) <input type="number" name="delta" value="10" style="width:70px"> הערה <input type="text" name="note"> <button class="button button-primary">עדכן</button></form><hr>';
	$rows = $wpdb->get_results( "SELECT phone, SUM(delta) bal, MAX(created_at) last FROM {$wpdb->prefix}gyl_credits GROUP BY phone HAVING bal<>0 ORDER BY last DESC LIMIT 200", ARRAY_A );
	echo '<table class="widefat striped"><thead><tr><th>טלפון</th><th>יתרת כניסות</th><th>עדכון אחרון</th></tr></thead><tbody>';
	foreach ( (array) $rows as $r ) echo '<tr><td>' . esc_html( $r['phone'] ) . '</td><td><b>' . (int) $r['bal'] . '</b></td><td>' . $r['last'] . '</td></tr>';
	echo '</tbody></table></div>';
}

function gyl_page_settings() {
	$s = gyl_get();
	wp_enqueue_media();   // בורר המדיה של וורדפרס לתמונות/סרטון
	if ( ! empty( $_POST['gyl_s'] ) && check_admin_referer( 'gyl_st' ) ) {
		foreach ( $s['branches'] as $k => $b ) $s['branches'][ $k ]['capacity'] = (int) $_POST['cap'][ $k ];
		foreach ( array( 'price', 'punch_price', 'punch_entries', 'slot_len', 'slot_step', 'max_children', 'days_ahead', 'hold_minutes', 'min_lead_min', 'move_hours', 'max_moves' ) as $f )
			$s[ $f ] = (float) $_POST[ $f ];
		$s['move_branch']   = empty( $_POST['move_branch'] ) ? 0 : 1;
		$s['remind']        = empty( $_POST['remind'] ) ? 0 : 1;
		$s['remind_hours']  = (int) $_POST['remind_hours'];
		$s['summer_ranges'] = sanitize_textarea_field( $_POST['summer_ranges'] );
		$s['holidays']      = sanitize_textarea_field( $_POST['holidays'] );
		$s['terms']         = sanitize_textarea_field( $_POST['terms'] );
		$s['terms_url']     = esc_url_raw( $_POST['terms_url'] );
		$s['terms_label']   = sanitize_text_field( $_POST['terms_label'] );
		$s['notify_email']  = sanitize_email( $_POST['notify_email'] );
		$s['reply_to']      = sanitize_email( $_POST['reply_to'] );
		$s['from_name']     = sanitize_text_field( $_POST['from_name'] ?? '' );
		$s['from_email']    = sanitize_email( $_POST['from_email'] ?? '' );
		$s['auto_punch']    = empty( $_POST['auto_punch'] ) ? 0 : 1;
		$s['whatsapp_url']  = esc_url_raw( $_POST['whatsapp_url'] );
		$s['cancel_url']    = esc_url_raw( $_POST['cancel_url'] );
		$s['subj_confirm']  = sanitize_text_field( $_POST['subj_confirm'] );
		$s['subj_remind']   = sanitize_text_field( $_POST['subj_remind'] );
		$s['tpl_confirm']   = wp_kses_post( wp_unslash( $_POST['tpl_confirm'] ) );
		$s['tpl_remind']    = wp_kses_post( wp_unslash( $_POST['tpl_remind'] ) );
		$s['pixel_id']      = sanitize_text_field( $_POST['pixel_id'] );
		$s['capi_token']    = sanitize_text_field( $_POST['capi_token'] );
		$s['fire_purchase'] = empty( $_POST['fire_purchase'] ) ? 0 : 1;
		$s['test_mode']     = empty( $_POST['test_mode'] ) ? 0 : 1;
		$s['test_price']    = max( 1, (int) ( $_POST['test_price'] ?? 1 ) );
		$s['waitlist']      = empty( $_POST['waitlist'] ) ? 0 : 1;
		if ( isset( $_POST['voucher_api'] ) ) $s['voucher_api'] = esc_url_raw( $_POST['voucher_api'] );
		$s['voucher_on']    = empty( $_POST['voucher_on'] ) ? 0 : 1;
		if ( isset( $_POST['voucher_api'] ) ) $s['voucher_api'] = esc_url_raw( $_POST['voucher_api'] );
		$s['purge_on_uninstall'] = empty( $_POST['purge'] ) ? 0 : 1;
		$s['social_proof']  = empty( $_POST['social_proof'] ) ? 0 : 1;
		$s['hold_timer']    = empty( $_POST['hold_timer'] ) ? 0 : 1;
		$s['birthday']['enabled'] = empty( $_POST['bd_on'] ) ? 0 : 1;
		$s['birthday']['from']    = (int) $_POST['bd_from'];
		$s['birthday']['url']     = esc_url_raw( $_POST['bd_url'] );
		if ( isset( $_POST['bd_deposit'] ) ) {
		$s['birthday']['deposit']     = (int) ( $_POST['bd_deposit'] ?? 500 );
		$s['birthday']['duration']    = (int) ( $_POST['bd_duration'] ?? 3 );
		$s['birthday']['max_kids']    = (int) ( $_POST['bd_max_kids'] ?? 60 );
		$s['birthday']['weekend_fee'] = (int) ( $_POST['bd_weekend_fee'] ?? 499 );
		$s['birthday']['bags_incl']   = (int) ( $_POST['bd_bags_incl'] ?? 30 );
		$s['birthday']['hold_hours']  = (int) ( $_POST['bd_hold_hours'] ?? 24 );
		$s['birthday']['whatsapp']    = sanitize_text_field( $_POST['bd_whatsapp'] ?? '' );
		if ( ! empty( $_POST['bd_nayax_test'] ) ) $s['birthday']['nayax_test'] = esc_url_raw( wp_unslash( $_POST['bd_nayax_test'] ) );
		if ( ! empty( $_POST['bd_nayax'] ) ) $s['birthday']['nayax'] = esc_url_raw( $_POST['bd_nayax'] );
		if ( isset( $_POST['bd_design_img'] ) )   $s['birthday']['design_img']   = esc_url_raw( $_POST['bd_design_img'] );
		if ( isset( $_POST['bd_design_video'] ) ) $s['birthday']['design_video'] = esc_url_raw( $_POST['bd_design_video'] );
		if ( isset( $_POST['bd_gift_video'] ) )   $s['birthday']['gift_video']   = esc_url_raw( $_POST['bd_gift_video'] );
		if ( isset( $_POST['bd_gift_img'] ) )     $s['birthday']['gift_img']     = esc_url_raw( $_POST['bd_gift_img'] );
		if ( isset( $_POST['bd_gift_price'] ) )   $s['birthday']['gift_price']   = (int) $_POST['bd_gift_price'];
		$s['birthday']['holidays']    = gyl_lines( wp_unslash( $_POST['bd_holidays'] ?? '' ) );
		for ( $d = 0; $d <= 6; $d++ )
			if ( isset( $_POST['bd_slots'][$d] ) )
				$s['birthday']['slots_by_day'][$d] = array_values( array_filter( array_map( 'trim', explode( ',', sanitize_text_field( $_POST['bd_slots'][$d] ) ) ) ) );
		foreach ( (array) ( $s['birthday']['tiers'] ?? array() ) as $i => $t ) {
			if ( ! isset( $_POST['bd_tier_name'][$i] ) ) continue;
			$s['birthday']['tiers'][$i]['name']  = sanitize_text_field( $_POST['bd_tier_name'][$i] ?? '' );
			$s['birthday']['tiers'][$i]['price'] = (int) ( $_POST['bd_tier_price'][$i] ?? 0 );
			$s['birthday']['tiers'][$i]['value'] = (int) ( $_POST['bd_tier_value'][$i] ?? 0 );
			$s['birthday']['tiers'][$i]['badge'] = sanitize_text_field( $_POST['bd_tier_badge'][$i] ?? '' );
		}
		foreach ( (array) ( $s['birthday']['cakes'] ?? array() ) as $i => $c )
			$s['birthday']['cakes'][$i]['price'] = (int) ( $_POST['bd_cake_price'][$i] ?? 0 );
		foreach ( array( 'design', 'coffee', 'bags' ) as $ak )
			if ( isset( $s['birthday']['bd_addons'][$ak] ) && isset( $_POST['bd_addon_price'][$ak] ) )
				$s['birthday']['bd_addons'][$ak]['price'] = (int) $_POST['bd_addon_price'][$ak];
		}   // סוף isset( bd_deposit )
		$s['birthday']['pitch']   = sanitize_text_field( $_POST['bd_pitch'] );
		$s['birthday']['notify']  = sanitize_email( $_POST['bd_notify'] );
		$new_addons = array();
		foreach ( (array) ( $_POST['ad_l'] ?? array() ) as $i => $lbl ) {
			$lbl = sanitize_text_field( $lbl );
			if ( '' === trim( $lbl ) ) continue;          // שורה ריקה = נמחקה
			$new_addons[] = array(
				'label'     => $lbl,
				'price'     => (float) ( $_POST['ad_p'][ $i ] ?? 0 ),
				'product'   => (int) ( $_POST['ad_prod'][ $i ] ?? 0 ),
				'per_child' => empty( $_POST['ad_pc'][ $i ] ) ? 0 : 1,
				'on'        => empty( $_POST['ad_on'][ $i ] ) ? 0 : 1,
			);
		}
		$s['addons'] = $new_addons;

		// מוצרי ווקומרס לכל סניף
		foreach ( $s['branches'] as $k => $bb ) {
			$s['branches'][ $k ]['product_id']    = (int) ( $_POST['prod'][ $k ] ?? 0 );
			$s['branches'][ $k ]['punch_product'] = (int) ( $_POST['prodp'][ $k ] ?? 0 );
		}

		// גן עם אמא
		$s['mom']['enabled']  = empty( $_POST['mom_on'] ) ? 0 : 1;
		$s['mom']['branches'] = array_map( 'sanitize_key', (array) ( $_POST['mom_br'] ?? array() ) );
		$s['mom']['days']     = array_map( 'intval', (array) ( $_POST['mom_days'] ?? array() ) );
		$s['mom']['from']     = sanitize_text_field( $_POST['mom_from'] );
		$s['mom']['to']       = sanitize_text_field( $_POST['mom_to'] );
		$s['mom']['capacity'] = (int) $_POST['mom_cap'];
		$s['mom']['age']      = sanitize_text_field( $_POST['mom_age'] );
		$s['mom']['desc']     = sanitize_textarea_field( wp_unslash( $_POST['mom_desc'] ) );
		// מסלולים — כל מספר, הוספה/מחיקה חופשית
		$tiers = array();
		foreach ( (array) ( $_POST['tier_l'] ?? array() ) as $tk => $lbl ) {
			$lbl = sanitize_text_field( $lbl );
			if ( '' === trim( $lbl ) ) continue;                       // שם ריק = נמחק
			$key = sanitize_key( $_POST['tier_k'][ $tk ] ?? '' );
			if ( '' === $key ) $key = 'tier' . substr( md5( $lbl . microtime() ), 0, 6 );
			$tiers[ $key ] = array(
				'label'    => $lbl,
				'price'    => (float) ( $_POST['tier_p'][ $tk ] ?? 0 ),
				'sessions' => max( 1, (int) ( $_POST['tier_s'][ $tk ] ?? 1 ) ),
				'per_week' => max( 1, (int) ( $_POST['tier_w'][ $tk ] ?? 1 ) ),
				'product'  => (int) ( $_POST['tier_prod'][ $tk ] ?? 0 ),
				'best'     => ( ( $_POST['tier_best'] ?? '' ) === (string) $tk ) ? 1 : 0,
			);
		}
		if ( $tiers ) $s['mom']['tiers'] = $tiers;
		$s['book_url']      = esc_url_raw( $_POST['book_url'] );
		$s['abandon']       = empty( $_POST['abandon'] ) ? 0 : 1;
		$s['abandon_hours'] = (int) $_POST['abandon_hours'];
		$s['subj_abandon']  = sanitize_text_field( $_POST['subj_abandon'] );
		$s['tpl_abandon']   = wp_kses_post( wp_unslash( $_POST['tpl_abandon'] ) );
		$s['upsell']        = empty( $_POST['upsell'] ) ? 0 : 1;
		$s['upsell_days']   = (int) $_POST['upsell_days'];
		$s['subj_upsell']   = sanitize_text_field( $_POST['subj_upsell'] );
		$s['tpl_upsell']    = wp_kses_post( wp_unslash( $_POST['tpl_upsell'] ) );
		$s['review']        = empty( $_POST['review'] ) ? 0 : 1;
		$s['review_days']   = (int) $_POST['review_days'];
		$s['review_min']    = (int) $_POST['review_min'];
		$s['google_url']    = esc_url_raw( $_POST['google_url'] );
		$s['feedback_url']  = esc_url_raw( $_POST['feedback_url'] );
		$s['feedback_page'] = esc_url_raw( $_POST['feedback_page'] ?? '' );
		$s['birthday_page'] = esc_url_raw( $_POST['birthday_page'] ?? '' );
		$s['subj_review']   = sanitize_text_field( $_POST['subj_review'] );
		$s['tpl_review']    = wp_kses_post( wp_unslash( $_POST['tpl_review'] ) );
		foreach ( $s['branches'] as $k => $bb ) $s['branches'][ $k ]['address'] = sanitize_text_field( $_POST['addr'][ $k ] );
		update_option( GYL_OPT, $s );
		if ( class_exists( 'WooCommerce' ) && $s['punch_product'] && ( $p = wc_get_product( $s['punch_product'] ) ) ) {
			$p->set_price( $s['punch_price'] ); $p->set_regular_price( $s['punch_price'] ); $p->save();
		}
		echo '<div class="notice notice-success"><p>נשמר.</p></div>'; $s = gyl_get();
	}
	$tabs = array(
		'general'  => '⚙️ כללי',
		'prices'   => '💰 מחירים וכרטיסיות',
		'products' => '🛒 מוצרי ווקומרס',
		'mom'      => '👶 גן עם אמא',
		'booking'  => '📅 כללי הזמנה',
		'emails'   => '✉️ מיילים ללקוח',
		'marketing'=> '📣 שיווק ומדידה',
		'sales'    => '🚀 מנועי מכירה',
	);
	$tab = isset( $_GET['tab'] ) && isset( $tabs[ $_GET['tab'] ] ) ? $_GET['tab'] : 'general';

	echo '<div class="wrap gyl-adm"><h1>⚙️ הגדרות</h1><h2 class="nav-tab-wrapper">';
	foreach ( $tabs as $k => $lbl )
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=gyl-settings&tab=' . $k ) ) . '" class="nav-tab' . ( $tab === $k ? ' nav-tab-active' : '' ) . '">' . esc_html( $lbl ) . '</a>';
	echo '</h2>';
	echo '<form method="post">' . wp_nonce_field( 'gyl_st', '_wpnonce', true, false ) . '<input type="hidden" name="gyl_s" value="1">';
	echo '<p class="description" style="margin:12px 0">כל הלשוניות נשמרות יחד — אפשר לערוך בכמה מהן ולשמור פעם אחת.</p>';

	echo '<div class="gyltab" data-tab="general">';
	if ( gyl_test_on() )
		echo '<div style="background:#FBEDE9;border:2px solid #B4553F;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-weight:700;color:#B4553F">⚠️ מצב בדיקה פעיל — כל עסקה נגבית ב-' . (int) $s['test_price'] . ' ₪ בלבד. אל תשכחו לכבות לפני העלייה לאוויר.</div>';
	echo '<table class="form-table">';
	echo '<tr><th style="color:#B4553F">🧪 מצב בדיקה</th><td><label><input type="checkbox" name="test_mode" ' . checked( $s['test_mode'], 1, false ) . '> <b>כל עסקה תיגבה ב-</b></label> <input type="number" min="1" name="test_price" value="' . (int) ( $s['test_price'] ?? 1 ) . '" style="width:70px"> ₪<br><span class="description">משפיע על: כניסה רגילה, כרטיסייה, גן עם אמא, ומקדמת יום הולדת. תוספות (קפה/גרביים) לא נוספות בכלל במצב בדיקה.<br><b style="color:#B4553F">חובה לכבות לפני שהמערכת עולה לאוויר.</b></span></td></tr>';
	foreach ( $s['branches'] as $k => $b )
		echo '<tr><th>קיבולת — ' . esc_html( $b['label'] ) . '</th><td><input type="number" name="cap[' . $k . ']" value="' . (int) $b['capacity'] . '"> ילדים בסבב</td></tr>';
	foreach ( $s['branches'] as $k => $b )
		echo '<tr><th>כתובת — ' . esc_html( $b['label'] ) . '</th><td><input type="text" name="addr[' . $k . ']" style="width:340px" value="' . esc_attr( $b['address'] ?? '' ) . '"></td></tr>';
	echo '</table></div><div class="gyltab" data-tab="prices"><table class="form-table">';
	echo '<tr><th>כרטיס רגיל</th><td><input type="number" step="0.5" name="price" value="' . esc_attr( $s['price'] ) . '"> ₪ לילד</td></tr>';
	echo '<tr><th>כרטיסייה</th><td><input type="number" name="punch_entries" value="' . (int) $s['punch_entries'] . '" style="width:70px"> כניסות במחיר <input type="number" name="punch_price" value="' . (int) $s['punch_price'] . '" style="width:90px"> ₪ — <b>חיסכון ' . gyl_punch_discount() . '%</b></td></tr>';
	echo '</table></div><div class="gyltab" data-tab="booking"><table class="form-table">';
	echo '<tr><th>אורך סבב</th><td><input type="number" name="slot_len" value="' . (int) $s['slot_len'] . '"> דק׳</td></tr>';
	echo '<tr><th>מרווח בין סבבים</th><td><input type="number" name="slot_step" value="' . (int) $s['slot_step'] . '"> דק׳ <span class="description">120 = רצוף · 60 = חופף</span></td></tr>';
	echo '<tr><th>מקס׳ ילדים בהזמנה</th><td><input type="number" name="max_children" value="' . (int) $s['max_children'] . '"></td></tr>';
	echo '<tr><th>ימים קדימה</th><td><input type="number" name="days_ahead" value="' . (int) $s['days_ahead'] . '"></td></tr>';
	echo '<tr><th>שמירת מקום ללא תשלום</th><td><input type="number" name="hold_minutes" value="' . (int) $s['hold_minutes'] . '"> דק׳</td></tr>';
	echo '<tr><th>מינימום מראש</th><td><input type="number" name="min_lead_min" value="' . (int) $s['min_lead_min'] . '"> דק׳</td></tr>';
	echo '<tr><th>טווחי קיץ</th><td><textarea name="summer_ranges" rows="3" style="width:320px">' . esc_textarea( $s['summer_ranges'] ) . '</textarea><br><span class="description">2026-07-01..2026-08-31</span></td></tr>';
	echo '<tr><th>תאריכי חג</th><td><textarea name="holidays" rows="4" style="width:320px">' . esc_textarea( $s['holidays'] ) . '</textarea></td></tr>';
	echo '<tr><th>הזזת מועד ע״י הלקוח</th><td>עד <input type="number" name="move_hours" value="' . (int) $s['move_hours'] . '" style="width:70px"> שעות לפני · מקסימום <input type="number" name="max_moves" value="' . (int) $s['max_moves'] . '" style="width:60px"> הזזות<br><label><input type="checkbox" name="move_branch" ' . checked( $s['move_branch'], 1, false ) . '> מותר להזיז גם לסניף השני</label><br><span class="description">ביטול לעולם אינו אפשרי ללקוח — רק דרככם</span></td></tr>';
	echo '<tr><th>ניקוב כרטיסייה</th><td><label><input type="checkbox" name="auto_punch" ' . checked( $s['auto_punch'], 1, false ) . '> ניקוב אוטומטי במערכת</label><br><span class="description">כבוי (מומלץ כרגע) = הניקוב מתבצע בסניף ברימבר. המערכת רק רושמת כמה ילדים מגיעים.</span></td></tr>';
	echo '<tr><th>תזכורת אוטומטית</th><td><label><input type="checkbox" name="remind" ' . checked( $s['remind'], 1, false ) . '> שלח תזכורת </label><input type="number" name="remind_hours" value="' . (int) $s['remind_hours'] . '" style="width:70px"> שעות לפני ההגעה<br><span class="description">כולל קישור אישי להזזה. רץ אוטומטית כל שעה.</span></td></tr>';
	echo '<tr><th>טקסט מתחת לסכום</th><td><textarea name="terms" rows="2" style="width:520px">' . esc_textarea( $s['terms'] ) . '</textarea></td></tr>';
	echo '<tr><th>אישור תקנון (חובה)</th><td><input type="text" name="terms_label" style="width:520px" value="' . esc_attr( $s['terms_label'] ) . '"><br><input type="url" name="terms_url" style="width:520px" placeholder="https://gayaland.co.il/terms" value="' . esc_attr( $s['terms_url'] ) . '"><br><span class="description">קישור לעמוד התקנון. תיבת הסימון חובה — בלעדיה ההזמנה נדחית גם בשרת. מועד האישור נשמר בכל הזמנה.</span></td></tr>';
	echo '</table></div><div class="gyltab" data-tab="emails"><table class="form-table">';
	echo '<tr><th>מייל להתראות</th><td><input type="email" name="notify_email" style="width:340px" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '" value="' . esc_attr( $s['notify_email'] ) . '"><br><span class="description">לכאן מגיעות ההזמנות החדשות וההזזות</span></td></tr>';
	echo '<tr><th>שם השולח במיילים</th><td><input type="text" name="from_name" style="width:340px" placeholder="גאיהלנד" value="' . esc_attr( $s['from_name'] ?? '' ) . '"><br><span class="description">השם שמופיע כשולח (במקום "WordPress"). ריק = גאיהלנד</span></td></tr>';
	echo '<tr><th>מייל השולח (From)</th><td><input type="email" name="from_email" style="width:340px" placeholder="no-reply@gayaland.co.il" value="' . esc_attr( $s['from_email'] ?? '' ) . '"><br><span class="description">כתובת השולח. ריק = no-reply בדומיין שלכם. אם המיילים לא מגיעים — השאירו ריק</span></td></tr>';
	echo '<tr><th>מייל תשובה (Reply-To)</th><td><input type="email" name="reply_to" style="width:340px" placeholder="info@gayaland.co.il" value="' . esc_attr( $s['reply_to'] ) . '"><br><span class="description">לקוח שילחץ "השב" על מייל האישור — יגיע לכאן</span></td></tr>';
	echo '<tr><th>קישור לקבוצת וואטסאפ</th><td><input type="url" name="whatsapp_url" style="width:520px" value="' . esc_attr( $s['whatsapp_url'] ) . '"></td></tr>';
	echo '<tr><th>קישור לנהלי ביטול</th><td><input type="url" name="cancel_url" style="width:520px" value="' . esc_attr( $s['cancel_url'] ) . '"></td></tr>';
	echo '<tr><th>🎟️ שוברי אורחים</th><td><label><input type="checkbox" name="voucher_on" ' . checked( $s['voucher_on'], 1, false ) . '> אפשר מימוש שובר מיום הולדת בהזמנה באתר</label><br><input type="url" name="voucher_api" style="width:100%;max-width:600px;margin-top:6px" value="' . esc_attr( $s['voucher_api'] ) . '" placeholder="כתובת Apps Script של מערכת ימי ההולדת"><br><span class="description">כך אורחים נרשמים <b>מראש</b> במקום להגיע בהפתעה. השובר מסומן כמומש אוטומטית.</span>';
	echo '<br><span class="description" style="margin-top:8px;display:block">🔌 <b>בדיקת חיבור:</b> גלשו לכתובת <code>' . esc_html( home_url( '/wp-json/gayaland/v1/voucher/diag' ) ) . '</code> — אם רואים <code>"connection":"OK"</code> החיבור תקין. אם <code>"FAILED"</code> — תראו שם את השגיאה המדויקת.</span>';
	echo '</td></tr>';
	echo '<tr><th>רשימת המתנה</th><td><label><input type="checkbox" name="waitlist" ' . checked( $s['waitlist'], 1, false ) . '> אפשר הרשמה לרשימת המתנה בסבב מלא</label><br><span class="description">כשמתפנה מקום (ביטול או החזר) — נשלח מייל אוטומטי לממתינים, לפי סדר ההרשמה</span></td></tr>';
	echo '<tr><th>תגיות זמינות</th><td><code>%first_name%</code> <code>%calendar_link%</code> <code>%name%</code> <code>%branch%</code> <code>%address%</code> <code>%date%</code> <code>%time%</code> <code>%children%</code> <code>%price%</code> <code>%move_hours%</code> <code>%manage_link%</code> <code>%whatsapp_link%</code> <code>%cancel_link%</code> <code>%terms_link%</code></td></tr>';
	echo '<tr><th>מייל אישור — נושא</th><td><input type="text" name="subj_confirm" style="width:520px" value="' . esc_attr( $s['subj_confirm'] ) . '"></td></tr>';
	echo '<tr><th>מייל אישור — תוכן</th><td><textarea name="tpl_confirm" rows="15" style="width:100%;max-width:640px;direction:rtl">' . esc_textarea( $s['tpl_confirm'] ) . '</textarea></td></tr>';
	echo '<tr><th>תזכורת — נושא</th><td><input type="text" name="subj_remind" style="width:520px" value="' . esc_attr( $s['subj_remind'] ) . '"></td></tr>';
	echo '<tr><th>תזכורת — תוכן</th><td><textarea name="tpl_remind" rows="12" style="width:100%;max-width:640px;direction:rtl">' . esc_textarea( $s['tpl_remind'] ) . '</textarea></td></tr>';
	echo '<tr><th>עמוד ההזמנות</th><td><input type="url" name="book_url" style="width:520px" placeholder="https://gayaland.co.il/booking" value="' . esc_attr( $s['book_url'] ) . '"><br><span class="description">אליו מפנים הקישורים במיילי השיווק</span></td></tr>';
	echo '</table><h3>שחזור הזמנה נטושה</h3><table class="form-table">';
	echo '<tr><th>הפעלה</th><td><label><input type="checkbox" name="abandon" ' . checked( $s['abandon'], 1, false ) . '> שלח מייל למי שהתחיל ולא שילם, אחרי </label><input type="number" name="abandon_hours" value="' . (int) $s['abandon_hours'] . '" style="width:60px"> שעות</td></tr>';
	echo '<tr><th>נושא</th><td><input type="text" name="subj_abandon" style="width:520px" value="' . esc_attr( $s['subj_abandon'] ) . '"></td></tr>';
	echo '<tr><th>תוכן</th><td><textarea name="tpl_abandon" rows="10" style="width:100%;max-width:640px;direction:rtl">' . esc_textarea( $s['tpl_abandon'] ) . '</textarea><br><span class="description">התגית <code>%pay_link%</code> מחזירה אותו ישירות לקופה עם ההזמנה שלו</span></td></tr>';
	echo '</table><h3>שאלון אחרי הביקור</h3><table class="form-table">';
	echo '<tr><th>הפעלה</th><td><label><input type="checkbox" name="review" ' . checked( $s['review'], 1, false ) . '> שלח שאלון דירוג </label><input type="number" name="review_days" value="' . (int) $s['review_days'] . '" style="width:60px"> ימים אחרי הביקור</td></tr>';
	echo '<tr><th>סף ביקורת בגוגל</th><td>מדירוג <input type="number" min="1" max="5" name="review_min" value="' . (int) $s['review_min'] . '" style="width:60px"> כוכבים ומעלה — מפנים לגוגל. מתחת לזה — נפתח טופס משוב פרטי שמגיע רק אליכם</td></tr>';
	echo '<tr><th>קישור לביקורת בגוגל</th><td><input type="url" name="google_url" style="width:520px" placeholder="https://g.page/r/..." value="' . esc_attr( $s['google_url'] ) . '"></td></tr>';
	echo '<tr><th>דף משוב מעוצב</th><td><input type="url" name="feedback_page" style="width:520px" placeholder="https://gayaland-feedback.netlify.app/" value="' . esc_attr( $s['feedback_page'] ?? '' ) . '"><br><span class="description"><b>מומלץ.</b> דף המשוב המעוצב שלנו (Netlify). אם מלא — מחליף גם את טופס הגוגל וגם את הדף הפנימי. משוב גבוה→גוגל, נמוך→שאלות ששמורות בתוסף</span></td></tr>';
	echo '<tr><th>טופס משוב חיצוני</th><td><input type="url" name="feedback_url" style="width:520px" placeholder="https://docs.google.com/forms/d/e/.../viewform" value="' . esc_attr( $s['feedback_url'] ) . '"><br><span class="description">אופציונלי. משמש רק אם "דף משוב מעוצב" ריק</span></td></tr>';
	echo '<tr><th>נושא</th><td><input type="text" name="subj_review" style="width:520px" value="' . esc_attr( $s['subj_review'] ) . '"></td></tr>';
	echo '<tr><th>תוכן</th><td><textarea name="tpl_review" rows="9" style="width:100%;max-width:640px;direction:rtl">' . esc_textarea( $s['tpl_review'] ) . '</textarea><br><span class="description">התגית <code>%stars%</code> מוסיפה את שורת הכוכבים הלחיצה</span></td></tr>';
	echo '</table></div><div class="gyltab" data-tab="marketing"><h3>אפסייל לכרטיסייה</h3><table class="form-table">';
	echo '<tr><th>הפעלה</th><td><label><input type="checkbox" name="upsell" ' . checked( $s['upsell'], 1, false ) . '> שלח הצעת כרטיסייה </label><input type="number" name="upsell_days" value="' . (int) $s['upsell_days'] . '" style="width:60px"> ימים אחרי ביקור בכרטיס רגיל<br><span class="description">נשלח רק למי שאין לו כרטיסייה</span></td></tr>';
	echo '<tr><th>נושא</th><td><input type="text" name="subj_upsell" style="width:520px" value="' . esc_attr( $s['subj_upsell'] ) . '"></td></tr>';
	echo '<tr><th>תוכן</th><td><textarea name="tpl_upsell" rows="10" style="width:100%;max-width:640px;direction:rtl">' . esc_textarea( $s['tpl_upsell'] ) . '</textarea></td></tr>';
	echo '</table><h3>מטא — מדידת המרות</h3><table class="form-table">';
	echo '<tr><th>Pixel ID</th><td><input type="text" name="pixel_id" style="width:280px" value="' . esc_attr( $s['pixel_id'] ) . '"><br><span class="description">התוסף לא טוען פיקסל חדש — הוא רק שולח אירועי Purchase ו-InitiateCheckout לפיקסל שכבר מותקן אצלך</span></td></tr>';
	echo '<tr><th>דיווח רכישה</th><td><label><input type="checkbox" name="fire_purchase" ' . checked( $s['fire_purchase'], 1, false ) . '> שלח אירוע Purchase</label><br><span class="description"><b>שים לב:</b> הפיקסל שלך כבר שולח Purchase מגורם אחר. אחרי הזמנת בדיקה — בדוק ב-Events Manager ← Test Events. אם אתה רואה <b>שני</b> Purchase לאותה הזמנה, כבה את המתג הזה.</span></td></tr>';
	echo '<tr><th>Conversions API Token</th><td><input type="text" name="capi_token" style="width:520px" value="' . esc_attr( $s['capi_token'] ) . '"><br><span class="description">אופציונלי אך מומלץ — שולח את ההמרה גם מהשרת, עוקף חוסמי פרסומות. Events Manager ← הגדרות ← יצירת אסימון גישה</span></td></tr>';
	echo '</table><table class="form-table">';
	echo '<tr><th>כתובת דף ימי הולדת</th><td><input type="url" name="birthday_page" style="width:100%;max-width:600px" value="' . esc_attr( $s['birthday_page'] ?? '' ) . '" placeholder="https://gayaland-birthday.netlify.app/"><br><span class="description">לכפתור "הזמנת אירוע" — לאן הוא מקשר</span></td></tr>';
	echo '<tr><th>שורטקודים — כפתורים</th><td>'
		. '<b>כפתור תיאום הגעה</b> (פותח את הטופס):<br><code>[gayaland_booking_button]</code><br><br>'
		. '<b>כפתור הזמנת אירוע</b> (מקשר לדף ימי ההולדת):<br><code>[gayaland_event_button]</code><br><br>'
		. '<b>טופס מוטמע</b> בעמוד:<br><code>[gayaland_booking]</code><br><br>'
		. '<b>עיצוב (עובד בשני הכפתורים):</b><br>'
		. '<code>text="..."</code> טקסט · <code>variant="amber"</code> זהב / (ריק=ירוק) · <code>bg="#C79A5B"</code> צבע מותאם · <code>color="#fff"</code> צבע טקסט · <code>size="large"</code> (small/large/1.2rem) · <code>radius="14px"</code> · <code>width="full"</code> רוחב מלא<br>'
		. '<span class="description">דוגמה לשני כפתורים זהים: <code>[gayaland_booking_button text="תיאום הגעה"]</code> ו-<code>[gayaland_event_button text="להזמנת אירוע"]</code></span>'
		. '</td></tr>';
	echo '<tr><th>מחיקת נתונים</th><td><label><input type="checkbox" name="purge" ' . checked( $s['purge_on_uninstall'], 1, false ) . '> למחוק את כל ההזמנות והנתונים אם התוסף יוסר</label><br><span class="description">כבוי (מומלץ) — הנתונים נשמרים גם אם תמחקו את התוסף</span></td></tr>';
	$bdurl = get_option( 'siteurl' ) . '/wp-json/gayaland/v1/birthday-block';
	echo '<tr><th>Endpoint לימי הולדת</th><td><code style="font-size:12px">' . esc_html( $bdurl ) . '</code><br>';
	echo '<span class="description">הדביקו כתובת זו ב-Apps Script (שדה <code>BOOKING_PLUGIN_URL</code>). יחד עם מפתח ה-API למטה — כל הזמנת יום הולדת תחסום אוטומטית את הסבבים הרגילים ב-wp_gyl_blocks.</span></td></tr>';
	echo '<tr><th>מפתח API</th><td><code>' . esc_html( $s['api_key'] ) . '</code></td></tr>';
	echo '</table></div>';

	/* ---- מוצרי ווקומרס ---- */
	$prods = array( 0 => '— לא הוגדר —' );
	if ( class_exists( 'WooCommerce' ) ) {
		foreach ( wc_get_products( array( 'limit' => 200, 'status' => array( 'publish', 'private' ) ) ) as $pp )
			$prods[ $pp->get_id() ] = $pp->get_name() . ' (#' . $pp->get_id() . ' · ' . (int) $pp->get_price() . ' ₪)';
	}
	$sel = function ( $name, $cur ) use ( $prods ) {
		$o = '<select name="' . $name . '" style="min-width:340px">';
		foreach ( $prods as $id => $lbl ) $o .= '<option value="' . (int) $id . '"' . selected( (int) $cur, (int) $id, false ) . '>' . esc_html( $lbl ) . '</option>';
		return $o . '</select>';
	};
	echo '<div class="gyltab" data-tab="products">';
	echo '<p class="description">כל שירות מחובר למוצר ווקומרס נפרד — כך הדוחות והאנליטיקס מפרידים בין כניסה לראשון, כניסה ליבנה, וגן עם אמא. אם לא בחרתם מוצר, המערכת משתמשת במוצר הכללי שנוצר אוטומטית.</p>';
	echo '<table class="form-table">';
	foreach ( $s['branches'] as $k => $bb ) {
		echo '<tr><th>כניסה — ' . esc_html( $bb['label'] ) . '</th><td>' . $sel( 'prod[' . $k . ']', $bb['product_id'] ?? 0 ) . '</td></tr>';
		echo '<tr><th>כרטיסייה — ' . esc_html( $bb['label'] ) . '</th><td>' . $sel( 'prodp[' . $k . ']', $bb['punch_product'] ?? 0 ) . '</td></tr>';
	}
	foreach ( $s['mom']['tiers'] as $tk => $tt )
		echo '<tr><th>גן עם אמא — ' . esc_html( $tt['label'] ) . '</th><td>' . $sel( 'tier_prod[' . $tk . ']', $tt['product'] ) . '</td></tr>';
	echo '</table></div>';

	/* ---- גן עם אמא ---- */
	$heb = array( 'ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת' );
	echo '<div class="gyltab" data-tab="mom"><table class="form-table">';
	echo '<tr><th>הפעלה</th><td><label><input type="checkbox" name="mom_on" ' . checked( $s['mom']['enabled'], 1, false ) . '> התוכנית פעילה ומוצגת באתר</label></td></tr>';
	echo '<tr><th>סניפים</th><td>';
	foreach ( $s['branches'] as $k => $bb )
		echo '<label style="margin-left:14px"><input type="checkbox" name="mom_br[]" value="' . esc_attr( $k ) . '" ' . checked( in_array( $k, (array) $s['mom']['branches'], true ), true, false ) . '> ' . esc_html( $bb['label'] ) . '</label>';
	echo '<br><span class="description">כשתפתחו גם ביבנה — פשוט סמנו כאן</span></td></tr>';
	echo '<tr><th>ימים</th><td>';
	for ( $i = 0; $i <= 6; $i++ )
		echo '<label style="margin-left:12px"><input type="checkbox" name="mom_days[]" value="' . $i . '" ' . checked( in_array( $i, array_map( 'intval', (array) $s['mom']['days'] ), true ), true, false ) . '> ' . $heb[ $i ] . '</label>';
	echo '</td></tr>';
	echo '<tr><th>שעות</th><td><input type="time" step="1800" name="mom_from" value="' . esc_attr( $s['mom']['from'] ) . '"> עד <input type="time" step="1800" name="mom_to" value="' . esc_attr( $s['mom']['to'] ) . '"></td></tr>';
	echo '<tr><th>מקסימום ילדים</th><td><input type="number" name="mom_cap" value="' . (int) $s['mom']['capacity'] . '" style="width:80px"></td></tr>';
	echo '<tr><th>גילאים</th><td><input type="text" name="mom_age" style="width:340px" value="' . esc_attr( $s['mom']['age'] ) . '"></td></tr>';
	echo '<tr><th>תיאור התוכנית</th><td><textarea name="mom_desc" rows="9" style="width:100%;max-width:640px;direction:rtl">' . esc_textarea( $s['mom']['desc'] ) . '</textarea><br><span class="description">מוצג ללקוחה בטופס ההרשמה</span></td></tr>';
	echo '</table><h3>מסלולים ומחירים</h3>';
	echo '<table class="widefat striped" style="max-width:1000px"><thead><tr><th>שם ללקוח</th><th>מחיר</th><th>מפגשים</th><th>בשבוע</th><th>מוצר ווקומרס</th><th>מודגש</th></tr></thead><tbody>';
	$tl = (array) $s['mom']['tiers'];
	$keys = array_keys( $tl );
	for ( $i = 0; $i < count( $tl ) + 3; $i++ ) {                   // 3 שורות ריקות להוספה
		$k  = $keys[ $i ] ?? '';
		$tt = $k ? $tl[ $k ] : array( 'label' => '', 'price' => 0, 'sessions' => 1, 'per_week' => 1, 'product' => 0, 'best' => 0 );
		echo '<tr><td><input type="hidden" name="tier_k[' . $i . ']" value="' . esc_attr( $k ) . '">';
		echo '<input type="text" name="tier_l[' . $i . ']" style="width:100%" placeholder="שם המסלול" value="' . esc_attr( $tt['label'] ) . '"></td>';
		echo '<td><input type="number" name="tier_p[' . $i . ']" style="width:80px" value="' . (int) $tt['price'] . '"> ₪</td>';
		echo '<td><input type="number" name="tier_s[' . $i . ']" style="width:60px" value="' . (int) $tt['sessions'] . '"></td>';
		echo '<td><input type="number" name="tier_w[' . $i . ']" style="width:60px" value="' . (int) $tt['per_week'] . '"></td>';
		echo '<td>' . $sel( 'tier_prod[' . $i . ']', $tt['product'] ) . '</td>';
		echo '<td><input type="radio" name="tier_best" value="' . $i . '" ' . checked( ! empty( $tt['best'] ), true, false ) . '></td></tr>';
	}
	echo '</tbody></table>';
	echo '<p class="description"><b>הוספת מסלול:</b> מלאו שורה ריקה. <b>מחיקה:</b> רוקנו את שם המסלול ושמרו.<br>';
	echo '"מפגשים" = כמה כלולים במחיר · "בשבוע" = 1 → כל המפגשים באותו יום בשבוע · 2 → בשני הימים · "מודגש" = מקבל תגית "הכי משתלם".</p></div>';

	/* ---- מנועי מכירה ---- */
	echo '<div class="gyltab" data-tab="sales">';
	$bdcfg_url = get_option('siteurl').'/wp-json/gayaland/v1/birthday-config';
	echo '<h3>🎂 יום הולדת — הגדרות</h3>';
	echo '<div style="background:#eef7f1;border:1px solid #7C8C63;border-radius:8px;padding:10px 14px;margin-bottom:14px"><b>כתובת הקונפיגורציה:</b><br><code style="font-size:12px">' . esc_html($bdcfg_url) . '</code><br><span style="font-size:12px">הדביקו ב-<code>CFG_URL</code> בתחילת index.html — שמירה כאן = עדכון הדף מיידית.</span></div>';
	echo '<table class="form-table">';
	echo '<tr><th>עמוד הזמנת ימי הולדת</th><td><input type="url" name="bd_url" style="width:100%;max-width:600px" value="' . esc_attr( $s['birthday']['url'] ) . '" placeholder="https://..."><br><span class="description">הבאנר בפופ-אפ יפנה לכאן</span></td></tr>';
	echo '<tr><th>מקדמה</th><td><input type="number" name="bd_deposit" value="' . (int)($s['birthday']['deposit']??500) . '" style="width:90px"> ₪</td></tr>';
	echo '<tr><th>אורך אירוע</th><td><input type="number" name="bd_duration" value="' . (int)($s['birthday']['duration']??3) . '" style="width:70px"> שעות</td></tr>';
	echo '<tr><th>מקסימום ילדים</th><td><input type="number" name="bd_max_kids" value="' . (int)($s['birthday']['max_kids']??30) . '" style="width:70px"></td></tr>';
	echo '<tr><th>תוספת שבת/חג</th><td><input type="number" name="bd_weekend_fee" value="' . (int)($s['birthday']['weekend_fee']??499) . '" style="width:90px"> ₪</td></tr>';
	echo '<tr><th>שקיות כלולות ב-VIP</th><td><input type="number" name="bd_bags_incl" value="' . (int)($s['birthday']['bags_incl']??30) . '" style="width:70px"></td></tr>';
	echo '<tr><th>החזקת תאריך</th><td><input type="number" name="bd_hold_hours" value="' . (int)($s['birthday']['hold_hours']??24) . '" style="width:70px"> שעות ← לאחר מכן התאריך משתחרר</td></tr>';
	echo '<tr><th>וואטסאפ</th><td><input type="text" name="bd_whatsapp" value="' . esc_attr($s['birthday']['whatsapp']??'') . '" style="width:200px" placeholder="972547801818"></td></tr>';
	echo '</table>';

	// ── דוגמאות עיצוב ומתנה ──
	echo '<h3>🎨 דוגמאות שהלקוח רואה</h3><table class="form-table">';
	$mediarow = function ( $label, $name, $val, $hint ) {
		echo '<tr><th>' . esc_html( $label ) . '</th><td>';
		echo '<input type="url" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '" style="width:100%;max-width:520px" placeholder="https://...">';
		echo ' <button type="button" class="button gyl-media" data-target="' . esc_attr( $name ) . '">בחירה מהמדיה</button>';
		if ( $val ) echo '<br><img src="' . esc_url( $val ) . '" style="max-height:90px;margin-top:8px;border-radius:8px">';
		echo '<br><span class="description">' . esc_html( $hint ) . '</span></td></tr>';
	};
	$mediarow( 'תמונת דוגמה לעיצוב', 'bd_design_img', $s['birthday']['design_img'] ?? '', 'תמונה של שולחן/עיצוב שהלקוח יראה בעמוד' );
	echo '<tr><th>סרטון דוגמה לעיצוב</th><td><input type="url" id="bd_design_video" name="bd_design_video" value="' . esc_attr($s['birthday']['design_video']??'') . '" style="width:100%;max-width:520px" placeholder="https://youtube.com/... או קישור לקובץ mp4"> <button type="button" class="button gyl-media" data-target="bd_design_video">בחירה מהמדיה</button><br><span class="description">קישור YouTube או קובץ וידאו מהמדיה. יוצג ליד תוספת העיצוב</span></td></tr>';
	$mediarow( 'תמונת מתנת הפתעה', 'bd_gift_img', $s['birthday']['gift_img'] ?? '', 'תמונה של שקית/מתנת ההפתעה' );
	echo '<tr><th>סרטון מתנת הפתעה</th><td><input type="url" id="bd_gift_video" name="bd_gift_video" value="' . esc_attr($s['birthday']['gift_video']??'') . '" style="width:100%;max-width:520px" placeholder="https://youtube.com/... או קישור לקובץ mp4"> <button type="button" class="button gyl-media" data-target="bd_gift_video">בחירה מהמדיה</button><br><span class="description">קישור YouTube או קובץ וידאו מהמדיה. אם מלא — יוצג במקום התמונה</span></td></tr>';
	echo '<tr><th>מחיר מתנת הפתעה</th><td><input type="number" name="bd_gift_price" value="' . (int)($s['birthday']['gift_price']??25) . '" style="width:80px"> ₪ לילד</td></tr>';
	echo '</table>';

	// סקריפט בורר המדיה של וורדפרס
	echo '<script>jQuery(function($){$(".gyl-media").on("click",function(e){e.preventDefault();var t=$(this).data("target");var f=wp.media({title:"בחירת מדיה",multiple:false});f.on("select",function(){var u=f.state().get("selection").first().toJSON().url;$("#"+t).val(u);});f.open();});});</script>';
	echo '<table class="form-table" style="display:none"><tr><td></td></tr></table><table class="form-table">';
	echo '<tr><th style="color:#7C8C63">🔐 כתובת התראה ל-Nayax</th><td><input type="text" readonly value="' . esc_attr( rest_url( 'gayaland/v1/nayax-notify' ) ) . '" onclick="this.select()" style="width:100%;max-width:640px;direction:ltr;font-size:11px;background:#F6F3EC"><br><span class="description">העתיקו את הכתובת הזו והדביקו ב-Nayax תחת <b>Notification_URL</b>. כך כל תשלום מאומת אוטומטית מול WooCommerce, וההזמנות ה"נכשלות" יזוהו בדשבורד.</span></td></tr>';
	echo '<tr><th>💳 לינק נייקס — מקדמה</th><td><textarea name="bd_nayax" rows="3" style="width:100%;max-width:640px;direction:ltr;font-size:11px">' . esc_textarea($s['birthday']['nayax']??'') . '</textarea><br><span class="description">לינק התשלום האמיתי (500 ₪). כשמצב הבדיקה כבוי — זה מה שהלקוח רואה.</span></td></tr>';
	echo '<tr><th style="color:#B4553F">🧪 לינק נייקס לבדיקה</th><td><textarea name="bd_nayax_test" rows="3" style="width:100%;max-width:640px;direction:ltr;font-size:11px">' . esc_textarea($s['birthday']['nayax_test']??'') . '</textarea><br><span class="description">צרו בנייקס לינק תשלום נוסף בסכום 1 ₪ והדביקו כאן. כשמצב הבדיקה פעיל — דף ימי ההולדת ישתמש בלינק הזה במקום בלינק המקדמה הרגיל.</span></td></tr>';
	echo '<tr><th>חגים</th><td><textarea name="bd_holidays" rows="4" style="width:320px">' . esc_textarea(implode("\n",(array)($s['birthday']['holidays']??[]))) . '</textarea><br><span class="description">YYYY-MM-DD שורה לכל חג — יקבלו תוספת שבת/חג</span></td></tr>';
	echo '</table>';
	$heb7=array("ראשון","שני","שלישי","רביעי","חמישי","שישי","שבת");
	echo '<h3>שעות התחלה לפי יום</h3><table class="widefat striped" style="max-width:680px"><thead><tr><th>יום</th><th>שעות (מופרדות בפסיק)</th></tr></thead><tbody>';
	for($d=0;$d<=6;$d++){$cur=implode(', ',(array)($s['birthday']['slots_by_day'][$d]??[]));echo '<tr><td>'.$heb7[$d].'</td><td><input type="text" name="bd_slots['.$d.']" style="width:100%" value="'.esc_attr($cur).'"></td></tr>';}
	echo '</tbody></table>';
	echo '<h3>חבילות</h3><table class="widefat striped" style="max-width:920px"><thead><tr><th>שם</th><th>מחיר</th><th>שווי</th><th>תגית</th></tr></thead><tbody>';
	foreach((array)($s['birthday']['tiers']??[]) as $i=>$t){echo '<tr><td><input type="text" name="bd_tier_name['.$i.']" style="width:100%" value="'.esc_attr($t['name']??'').'"></td><td><input type="number" name="bd_tier_price['.$i.']" style="width:90px" value="'.(int)($t['price']??0).'"> ₪</td><td><input type="number" name="bd_tier_value['.$i.']" style="width:90px" value="'.(int)($t['value']??0).'"> ₪</td><td><input type="text" name="bd_tier_badge['.$i.']" style="width:200px" value="'.esc_attr($t['badge']??'').'"></td></tr>';}
	echo '</tbody></table>';
	echo '<h3>תוספות ועוגות</h3><table class="form-table">';
	foreach((array)($s['birthday']['bd_addons']??[]) as $ak=>$a){if($ak==='cake')continue;echo '<tr><th>'.esc_html($a['name']??$ak).'</th><td><input type="number" name="bd_addon_price['.$ak.']" style="width:90px" value="'.(int)($a['price']??0).'"> ₪</td></tr>';}
	foreach((array)($s['birthday']['cakes']??[]) as $i=>$c){echo '<tr><th>'.esc_html($c['name']??'עוגה '.($i+1)).'</th><td><input type="number" name="bd_cake_price['.$i.']" style="width:90px" value="'.(int)($c['price']??0).'"> ₪</td></tr>';}
	echo '</table>';
	echo '<tr><th>מייל להתראות לידים</th><td><input type="email" name="bd_notify" style="width:340px" value="' . esc_attr( $s['birthday']['notify'] ) . '" placeholder="ברירת מחדל — מייל ההתראות הכללי"></td></tr>';
	echo '</table><h3>🛍️ תוספות בהזמנה (Order Bump)</h3>';
	echo '<p class="description">מוצגות ממש לפני התשלום — הדרך הזולה ביותר להעלות את הסל. חייבות מוצר ווקומרס כדי להיגבות.</p>';
	echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>פעיל</th><th>שם</th><th>מחיר</th><th>לפי ילד</th><th>מוצר ווקומרס</th></tr></thead><tbody>';
	$rows_ad = (array) $s['addons'];
	for ( $i = 0; $i < count( $rows_ad ) + 3; $i++ ) {           // 3 שורות ריקות להוספה
		$a = $rows_ad[ $i ] ?? array( 'label' => '', 'price' => 0, 'product' => 0, 'per_child' => 0, 'on' => 0 );
		echo '<tr><td><input type="checkbox" name="ad_on[' . $i . ']" ' . checked( ! empty( $a['on'] ), true, false ) . '></td>';
		echo '<td><input type="text" name="ad_l[' . $i . ']" style="width:100%" placeholder="שם התוספת" value="' . esc_attr( $a['label'] ) . '"></td>';
		echo '<td><input type="number" name="ad_p[' . $i . ']" style="width:80px" value="' . (int) $a['price'] . '"> ₪</td>';
		echo '<td><input type="checkbox" name="ad_pc[' . $i . ']" ' . checked( ! empty( $a['per_child'] ), true, false ) . '></td>';
		echo '<td>' . $sel( 'ad_prod[' . $i . ']', $a['product'] ) . '</td></tr>';
	}
	echo '</tbody></table><p class="description">הוספה: מלאו שורה ריקה. מחיקה: רוקנו את שדה השם ושמרו.</p>';
	echo '<h3>⚡ המרה</h3><table class="form-table">';
	echo '<tr><th>הוכחה חברתית</th><td><label><input type="checkbox" name="social_proof" ' . checked( $s['social_proof'], 1, false ) . '> הצג "X ילדים ביקרו החודש" ודירוג ממוצע</label><br><span class="description">מוצג רק כשיש מספיק נתונים (20+ ביקורים, 5+ דירוגים)</span></td></tr>';
	echo '<tr><th>שעון שמירת מקום</th><td><label><input type="checkbox" name="hold_timer" ' . checked( $s['hold_timer'], 1, false ) . '> הצג ספירה לאחור של ' . (int) $s['hold_minutes'] . ' דקות לפני התשלום</label></td></tr>';
	echo '</table></div>';

	echo '<p><button class="button button-primary button-large">שמירת כל ההגדרות</button></p></form>';
	echo '<style>.gyltab{display:none}.gyltab.on{display:block}.gyltab h3{margin:18px 0 4px}</style>
	<script>
	(function(){
		var t = "' . esc_js( $tab ) . '";
		document.querySelectorAll(".gyltab").forEach(function(d){
			if(d.dataset.tab === t) d.classList.add("on");
		});
		// בורר המדיה של וורדפרס
		document.querySelectorAll(".gyl-media").forEach(function(btn){
			btn.addEventListener("click", function(e){
				e.preventDefault();
				var target = document.getElementById(btn.dataset.target);
				var frame = wp.media({ title: "בחירת קובץ", multiple: false });
				frame.on("select", function(){
					var a = frame.state().get("selection").first().toJSON();
					if(target) target.value = a.url;
				});
				frame.open();
			});
		});
	})();
	</script></div>';
}
