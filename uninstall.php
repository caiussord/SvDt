<?php
defined('WP_UNINSTALL_PLUGIN') || exit;

// Deliberate opt-in: define SVDT_DELETE_DATA as true in wp-config.php before uninstalling.
if (!defined('SVDT_DELETE_DATA') || SVDT_DELETE_DATA !== true) {
    return;
}

global $wpdb;
foreach (array('bookings', 'blocked_dates', 'business_hours', 'services') as $name) {
    $table = $wpdb->prefix . 'svdt_' . $name;
    $wpdb->query("DROP TABLE IF EXISTS $table");
}
delete_option('svdt_db_version');
delete_option('svdt_min_notice_minutes');
delete_option('svdt_slot_minutes');
wp_clear_scheduled_hook('svdt_daily_cleanup');
