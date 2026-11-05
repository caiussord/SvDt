<?php
defined('ABSPATH') || exit;

final class AgendaPro_DB
{
    public static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . 'agendapro_' . $name;
    }

    public static function activate(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $services = self::table('services');
        $hours = self::table('business_hours');
        $blocked = self::table('blocked_dates');
        $bookings = self::table('bookings');

        dbDelta("CREATE TABLE $services (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(150) NOT NULL,
            description text NOT NULL,
            duration_minutes smallint(5) unsigned NOT NULL,
            buffer_minutes smallint(5) unsigned NOT NULL DEFAULT 0,
            active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY active (active)
        ) $charset;");
        dbDelta("CREATE TABLE $hours (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            weekday tinyint(1) unsigned NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            active tinyint(1) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            KEY weekday (weekday)
        ) $charset;");
        dbDelta("CREATE TABLE $blocked (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            blocked_date date NOT NULL,
            reason varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            UNIQUE KEY blocked_date (blocked_date)
        ) $charset;");
        dbDelta("CREATE TABLE $bookings (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            service_id bigint(20) unsigned NOT NULL,
            customer_name varchar(150) NOT NULL,
            customer_email varchar(190) NOT NULL,
            customer_phone varchar(30) NOT NULL,
            notes text NOT NULL,
            starts_at datetime NOT NULL,
            ends_at datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY starts_at (starts_at),
            KEY status_starts (status,starts_at),
            KEY service_id (service_id)
        ) $charset;");
        add_option('agendapro_min_notice_minutes', 60);
        add_option('agendapro_slot_minutes', 30);
        update_option('agendapro_db_version', AGENDAPRO_VERSION);
        if (!wp_next_scheduled('agendapro_daily_cleanup')) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'agendapro_daily_cleanup');
        }
    }

    public static function cleanup(): void
    {
        global $wpdb;
        $table = self::table('bookings');
        $cutoff = current_datetime()->modify('-12 months')->format('Y-m-d H:i:s');
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE status IN ('cancelled','completed') AND starts_at < %s",
            $cutoff
        ));
    }

    public static function service(int $id, bool $active_only = true): ?object
    {
        global $wpdb;
        $table = self::table('services');
        $sql = $active_only
            ? $wpdb->prepare("SELECT * FROM $table WHERE id = %d AND active = 1", $id)
            : $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id);
        return $wpdb->get_row($sql) ?: null;
    }

    public static function services(bool $active_only = true): array
    {
        global $wpdb;
        $table = self::table('services');
        return $wpdb->get_results("SELECT * FROM $table" . ($active_only ? ' WHERE active = 1' : '') . ' ORDER BY name ASC') ?: array();
    }
}
