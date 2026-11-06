<?php
defined('ABSPATH') || exit;

final class SvDt_DB
{
    public static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . 'svdt_' . $name;
    }

    public static function activate(): void
    {
        global $wpdb;
        self::migrate_legacy();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $services = self::table('services');
        $hours = self::table('business_hours');
        $blocked = self::table('blocked_dates');
        $bookings = self::table('bookings');
        $professionals = self::table('professionals');
        $professional_services = self::table('professional_services');
        $professional_hours = self::table('professional_hours');
        $professional_blocks = self::table('professional_blocked_dates');

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
            professional_id bigint(20) unsigned NOT NULL DEFAULT 0,
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
            KEY service_id (service_id),
            KEY professional_starts (professional_id,starts_at)
        ) $charset;");
        dbDelta("CREATE TABLE $professionals (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(150) NOT NULL,
            description varchar(255) NOT NULL DEFAULT '',
            active tinyint(1) NOT NULL DEFAULT 1,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY active_order (active,sort_order)
        ) $charset;");
        dbDelta("CREATE TABLE $professional_services (
            professional_id bigint(20) unsigned NOT NULL,
            service_id bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (professional_id,service_id),
            KEY service_id (service_id)
        ) $charset;");
        dbDelta("CREATE TABLE $professional_hours (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            professional_id bigint(20) unsigned NOT NULL,
            weekday tinyint(1) unsigned NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            active tinyint(1) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            KEY professional_day (professional_id,weekday)
        ) $charset;");
        dbDelta("CREATE TABLE $professional_blocks (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            professional_id bigint(20) unsigned NOT NULL,
            blocked_date date NOT NULL,
            reason varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            UNIQUE KEY professional_date (professional_id,blocked_date)
        ) $charset;");
        self::migrate_single_calendar();
        add_option('svdt_min_notice_minutes', 60);
        add_option('svdt_slot_minutes', 30);
        update_option('svdt_db_version', SVDT_DB_VERSION);
        if (get_option('svdt_db_version') !== SVDT_DB_VERSION) throw new RuntimeException('Não foi possível registrar a versão do esquema.');
        if (!wp_next_scheduled('svdt_daily_cleanup')) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'svdt_daily_cleanup');
        }
    }

    private static function migrate_single_calendar(): void
    {
        global $wpdb;
        $professionals = self::table('professionals');
        $links = self::table('professional_services');
        $hours = self::table('professional_hours');
        $bookings = self::table('bookings');
        foreach (array($professionals, $links, $hours, self::table('professional_blocked_dates')) as $table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
                throw new RuntimeException('Migração 2.0 interrompida: tabela de profissionais ausente.');
            }
        }
        if (!$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $bookings LIKE %s", 'professional_id'))) {
            throw new RuntimeException('Migração 2.0 interrompida: coluna professional_id ausente.');
        }
        $default_id = (int) get_option('svdt_default_professional_id', 0);
        if (!$default_id || !self::professional($default_id, false)) {
            $default_id = (int) $wpdb->get_var("SELECT id FROM $professionals ORDER BY id ASC LIMIT 1");
            if (!$default_id) {
                $inserted = $wpdb->insert($professionals, array('name' => 'Profissional principal', 'description' => '', 'active' => 1, 'sort_order' => 0, 'created_at' => current_time('mysql')));
                if (!$inserted) throw new RuntimeException('Não foi possível criar o profissional padrão.');
                $default_id = (int) $wpdb->insert_id;
            }
            update_option('svdt_default_professional_id', $default_id);
            if ((int) get_option('svdt_default_professional_id') !== $default_id) throw new RuntimeException('Não foi possível registrar o profissional padrão.');
        }
        if (get_option('svdt_professional_migration_complete')) return;
        $services = self::table('services');
        $legacy_hours = self::table('business_hours');
        if ($wpdb->query($wpdb->prepare("INSERT IGNORE INTO $links (professional_id,service_id) SELECT %d,id FROM $services", $default_id)) === false) {
            throw new RuntimeException('Não foi possível vincular os serviços existentes.');
        }
        if ($wpdb->query($wpdb->prepare("INSERT INTO $hours (professional_id,weekday,start_time,end_time,active)
            SELECT %d,h.weekday,h.start_time,h.end_time,h.active FROM $legacy_hours h
            WHERE NOT EXISTS (SELECT 1 FROM $hours p WHERE p.professional_id=%d AND p.weekday=h.weekday AND p.start_time=h.start_time AND p.end_time=h.end_time)", $default_id, $default_id)) === false) {
            throw new RuntimeException('Não foi possível migrar o expediente existente.');
        }
        self::normalize_professional_hours($default_id);
        if ($wpdb->update($bookings, array('professional_id' => $default_id), array('professional_id' => 0), array('%d'), array('%d')) === false) {
            throw new RuntimeException('Não foi possível atribuir as reservas existentes.');
        }
        if ((int) $wpdb->get_var("SELECT COUNT(*) FROM $bookings WHERE professional_id = 0") !== 0) {
            throw new RuntimeException('Migração 2.0 interrompida: reservas sem profissional.');
        }
        update_option('svdt_professional_migration_complete', 1);
        if ((int) get_option('svdt_professional_migration_complete') !== 1) throw new RuntimeException('Não foi possível registrar a conclusão da migração.');
    }

    private static function normalize_professional_hours(int $professional_id): void
    {
        global $wpdb;
        $table = self::table('professional_hours');
        $periods = $wpdb->get_results($wpdb->prepare("SELECT id,weekday,start_time,end_time FROM $table WHERE professional_id=%d AND active=1 ORDER BY weekday,start_time,end_time", $professional_id));
        $previous = null;
        foreach ($periods as $period) {
            if ($period->start_time >= $period->end_time) throw new RuntimeException('Expediente legado inválido: início não antecede o fim.');
            if ($previous && $previous->weekday === $period->weekday && $period->start_time < $previous->end_time) {
                if ($period->end_time > $previous->end_time) {
                    if ($wpdb->update($table, array('end_time' => $period->end_time), array('id' => $previous->id)) === false) throw new RuntimeException('Não foi possível normalizar o expediente legado.');
                    $previous->end_time = $period->end_time;
                }
                if (!$wpdb->delete($table, array('id' => $period->id))) throw new RuntimeException('Não foi possível remover um intervalo legado sobreposto.');
            } else {
                $previous = $period;
            }
        }
    }

    private static function migrate_legacy(): void
    {
        global $wpdb;
        $renames = array();
        foreach (array('services', 'business_hours', 'blocked_dates', 'bookings') as $name) {
            $old = $wpdb->prefix . 'agendapro_' . $name;
            $new = self::table($name);
            $old_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($old))) === $old;
            $new_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($new))) === $new;
            if ($old_exists && $new_exists) {
                throw new RuntimeException('Migração SvDt interrompida: tabelas antigas e novas coexistem.');
            }
            if ($old_exists) {
                $renames[] = '`' . str_replace('`', '``', $old) . '` TO `' . str_replace('`', '``', $new) . '`';
            }
        }
        if ($renames && $wpdb->query('RENAME TABLE ' . implode(', ', $renames)) === false) {
            throw new RuntimeException('Não foi possível migrar as tabelas para SvDt.');
        }
        foreach (array('min_notice_minutes', 'slot_minutes') as $name) {
            $old = get_option('agendapro_' . $name, null);
            if ($old !== null && get_option('svdt_' . $name, null) === null) {
                add_option('svdt_' . $name, $old);
            }
            delete_option('agendapro_' . $name);
        }
        delete_option('agendapro_db_version');
        wp_clear_scheduled_hook('agendapro_daily_cleanup');
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

    public static function professional(int $id, bool $active_only = true): ?object
    {
        global $wpdb;
        $table = self::table('professionals');
        $where = $active_only ? ' AND active = 1' : '';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d$where", $id)) ?: null;
    }

    public static function professionals(bool $active_only = true, int $service_id = 0): array
    {
        global $wpdb;
        $table = self::table('professionals');
        $links = self::table('professional_services');
        $where = $active_only ? ' WHERE p.active = 1' : ' WHERE 1=1';
        if ($service_id) $where .= $wpdb->prepare(" AND EXISTS (SELECT 1 FROM $links ps WHERE ps.professional_id=p.id AND ps.service_id=%d)", $service_id);
        return $wpdb->get_results("SELECT p.* FROM $table p$where ORDER BY p.sort_order ASC,p.name ASC,p.id ASC") ?: array();
    }

    public static function serves(int $professional_id, int $service_id): bool
    {
        global $wpdb;
        $table = self::table('professional_services');
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT 1 FROM $table WHERE professional_id=%d AND service_id=%d", $professional_id, $service_id));
    }

    public static function calendar_lock_name(int $professional_id): string
    {
        global $wpdb;
        return 'svdt_' . md5($wpdb->prefix . ':professional:' . $professional_id);
    }

    public static function resolve_professional(int $service_id, int $professional_id = 0): stdClass|WP_Error
    {
        if ($professional_id) {
            $professional = self::professional($professional_id);
            if ($professional && self::serves($professional_id, $service_id)) return $professional;
            return new WP_Error('invalid_professional', 'Profissional indisponível para este serviço.', array('status' => 400));
        }
        $eligible = self::professionals(true, $service_id);
        if (count($eligible) === 1) return $eligible[0];
        if (!$eligible) return new WP_Error('invalid_professional', 'Nenhum profissional disponível para este serviço.', array('status' => 400));
        return new WP_Error('professional_required', 'Escolha um profissional para continuar.', array('status' => 400));
    }
}
