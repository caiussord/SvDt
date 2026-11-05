<?php
defined('WP_UNINSTALL_PLUGIN') || exit;

// Deliberate opt-in: define AGENDAPRO_DELETE_DATA as true in wp-config.php before uninstalling.
if (!defined('AGENDAPRO_DELETE_DATA') || AGENDAPRO_DELETE_DATA !== true) {
    return;
}

global $wpdb;
foreach (array('bookings', 'blocked_dates', 'business_hours', 'services') as $name) {
    $table = $wpdb->prefix . 'agendapro_' . $name;
    $wpdb->query("DROP TABLE IF EXISTS $table");
}
delete_option('agendapro_db_version');
delete_option('agendapro_min_notice_minutes');
delete_option('agendapro_slot_minutes');
wp_clear_scheduled_hook('agendapro_daily_cleanup');
