<?php
// Run with: wp eval-file tests/seed-local.php from the plugin directory.
if (!defined('WP_CLI') || !WP_CLI || !str_contains(wp_normalize_path(ABSPATH), '/Local Sites/') || get_option('blogname') !== 'SvDt') {
    throw new RuntimeException('Este script só pode ser usado no site SvDt do Local.');
}

global $wpdb;
$services = SvDt_DB::table('services');
$hours = SvDt_DB::table('business_hours');
$bookings = SvDt_DB::table('bookings');
foreach (array(
    'Corte clássico' => 'Corte de cabelo',
    'Barba completa' => 'Barba',
    'Consultoria inicial' => 'Barba',
) as $old_name => $new_name) {
    $old_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $services WHERE name = %s", $old_name));
    $new_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $services WHERE name = %s", $new_name));
    if ($old_id && !$new_id) {
        $wpdb->update($services, array('name' => $new_name), array('id' => $old_id));
    }
}
foreach (array('Corte degradê', 'Corte social', 'Barba com toalha quente') as $old_name) {
    $old_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $services WHERE name = %s", $old_name));
    if (!$old_id) continue;
    $has_bookings = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $bookings WHERE service_id = %d", $old_id));
    if ($has_bookings) $wpdb->update($services, array('active' => 0), array('id' => $old_id));
    else $wpdb->delete($services, array('id' => $old_id));
}
foreach (array(
    array('Barba', 'Modelagem e acabamento para sua barba.', 30, 15),
    array('Corte de cabelo', 'Clássico, degradê ou social. Diga o estilo desejado nas observações.', 60, 15),
    array('Corte e barba', 'Cabelo e barba em uma única visita.', 90, 15),
    array('Acabamento de contorno', 'Ajuste das linhas do cabelo e da barba.', 20, 10),
    array('Sobrancelha', 'Alinhamento discreto para finalizar o visual.', 15, 5),
    array('Hidratação capilar', 'Tratamento para fios mais macios e bem cuidados.', 30, 10),
    array('Lavagem e finalização', 'Lavagem e styling para sair pronto.', 25, 5),
) as [$name, $description, $duration, $buffer]) {
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $services WHERE name = %s", $name));
    if ($exists) {
        $wpdb->update($services, array(
            'description' => $description, 'duration_minutes' => $duration,
            'buffer_minutes' => $buffer, 'active' => 1,
        ), array('id' => $exists));
    } else {
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
