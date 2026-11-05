<?php
// Run with: wp eval-file tests/seed-local.php from the plugin directory.
if (!defined('WP_CLI') || !WP_CLI || !str_contains(wp_normalize_path(ABSPATH), '/Local Sites/agendapro/')) {
    throw new RuntimeException('Este script só pode ser usado no site SvDt do Local.');
}

global $wpdb;
$services = AgendaPro_DB::table('services');
$hours = AgendaPro_DB::table('business_hours');
$legacy_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $services WHERE name = %s", 'Consultoria inicial'));
if ($legacy_id && !$wpdb->get_var($wpdb->prepare("SELECT id FROM $services WHERE name = %s", 'Barba completa'))) {
    $wpdb->update($services, array(
        'name' => 'Barba completa', 'description' => 'Cuidado e acabamento para sua barba.',
        'duration_minutes' => 30, 'buffer_minutes' => 15,
    ), array('id' => $legacy_id));
}
foreach (array(
    array('Barba completa', 'Cuidado e acabamento para sua barba.', 30, 15),
    array('Corte clássico', 'Um atendimento completo, no seu ritmo.', 45, 15),
) as [$name, $description, $duration, $buffer]) {
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $services WHERE name = %s", $name));
    if (!$exists) {
        $wpdb->insert($services, array(
            'name' => $name, 'description' => $description,
            'duration_minutes' => $duration, 'buffer_minutes' => $buffer,
            'active' => 1, 'created_at' => current_time('mysql'),
        ));
    }
}
if (!(int) $wpdb->get_var("SELECT COUNT(*) FROM $hours")) {
    for ($day = 1; $day <= 5; $day++) {
        foreach (array(array('09:00:00', '12:00:00'), array('13:00:00', '18:00:00')) as [$start, $end]) {
            $wpdb->insert($hours, array('weekday' => $day, 'start_time' => $start, 'end_time' => $end, 'active' => 1));
        }
    }
}
$page = get_page_by_path('agendar');
if (!$page) {
    $page_id = wp_insert_post(array(
        'post_title' => 'Reservar horário', 'post_name' => 'agendar',
        'post_content' => '[svdt_booking]', 'post_type' => 'page', 'post_status' => 'publish',
    ));
} else {
    $page_id = $page->ID;
    wp_update_post(array('ID' => $page_id, 'post_title' => 'Reservar horário', 'post_content' => '[svdt_booking]'));
}
update_post_meta($page_id, '_wp_page_template', 'svdt-showcase.php');
update_option('blogname', 'SvDt');
update_option('blogdescription', 'Seu tempo, bem marcado.');
update_option('show_on_front', 'page');
update_option('page_on_front', $page_id);
WP_CLI::success('Serviços, expediente e página SvDt de demonstração prontos.');
