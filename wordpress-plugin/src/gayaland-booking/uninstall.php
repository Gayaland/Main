<?php
/**
 * הסרה מלאה — רץ רק כשמוחקים את התוסף (לא בהשבתה).
 * ברירת מחדל: הנתונים נשמרים. מחיקה מלאה רק אם סומן בהגדרות.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

$s = get_option( 'gyl_settings', array() );

wp_clear_scheduled_hook( 'gyl_cron' );

if ( empty( $s['purge_on_uninstall'] ) ) return;   // שומרים נתונים

global $wpdb;
foreach ( array( 'gyl_bookings', 'gyl_blocks', 'gyl_dayhours', 'gyl_credits', 'gyl_waitlist' ) as $t ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}$t" );
}
delete_option( 'gyl_settings' );
delete_option( 'gyl_db_ver' );
