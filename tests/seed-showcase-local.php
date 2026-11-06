<?php
// Seed fictional clients with natural names for the Local portfolio demo.
// Run only after intentionally clearing SvDt bookings.
if (!defined('WP_CLI') || !WP_CLI || !str_contains(wp_normalize_path(ABSPATH), '/Local Sites/') || get_option('blogname') !== 'SvDt') {
    throw new RuntimeException('Este script só pode ser usado no site SvDt do Local.');
}

global $wpdb;
$bookings = SvDt_DB::table('bookings');
$services = SvDt_DB::table('services');
$professionals = SvDt_DB::table('professionals');
if ((int) $wpdb->get_var("SELECT COUNT(*) FROM $bookings") !== 0) {
    throw new RuntimeException('A agenda precisa estar vazia antes de criar os dados de demonstração.');
}

$monday = current_datetime()->setTime(0, 0)->modify('next monday');
$people = array(
    array('Mariana Costa', 'Jorge', 'Corte de cabelo', 0, '09:00', 'pending', 'mariana.costa@example.test', '11900000001'),
    array('Camila Ferreira', 'Guilherme', 'Hidratação capilar', 0, '09:00', 'pending', 'camila.ferreira@example.test', '11900000002'),
    array('André Ribeiro', 'Jorge', 'Barba', 1, '14:00', 'confirmed', 'andre.ribeiro@example.test', '11900000004'),
    array('Rafael Souza', 'Guilherme', 'Lavagem e finalização', 2, '15:00', 'confirmed', 'rafael.souza@example.test', '11900000005'),
    array('Lucas Martins', 'João', 'Acabamento de contorno', 3, '11:00', 'confirmed', 'lucas.martins@example.test', '11900000006'),
);

$created_ids = array();
try {
    foreach ($people as [$name, $professional_name, $service_name, $offset, $preferred_time, $status, $email, $phone]) {
        $professional_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $professionals WHERE name = %s AND active = 1", $professional_name));
        $service_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $services WHERE name = %s AND active = 1", $service_name));
        if (!$professional_id || !$service_id || !SvDt_DB::serves($professional_id, $service_id)) {
            throw new RuntimeException("Serviço ou profissional indisponível para $name.");
        }
        $date = $monday->modify('+' . $offset . ' days')->format('Y-m-d');
        $slots = SvDt_Schedule::slots($service_id, $date, $professional_id);
        if (is_wp_error($slots) || !$slots) throw new RuntimeException("Sem horário disponível para $name.");
        $time = in_array($preferred_time, $slots, true) ? $preferred_time : $slots[0];
        $created = SvDt_Bookings::create(array(
            'service_id' => $service_id, 'professional_id' => $professional_id,
            'date' => $date, 'time' => $time, 'customer_name' => $name,
            'customer_email' => $email, 'customer_phone' => $phone, 'notes' => '',
        ));
        if (is_wp_error($created)) throw new RuntimeException("Reserva falhou para $name: " . $created->get_error_message());
        $id = (int) substr($created['reference'], 3);
        $created_ids[] = $id;
        if ($status === 'confirmed') {
            $request = new WP_REST_Request('PATCH');
            $request->set_param('id', $id);
            $request->set_param('status', 'confirmed');
            $updated = (new SvDt_REST())->update_booking($request);
            if (is_wp_error($updated)) throw new RuntimeException("Confirmação falhou para $name.");
        }
        WP_CLI::log("$name — $professional_name — $date $time — $status");
    }
    WP_CLI::success(count($created_ids) . ' reservas fictícias criadas para a demonstração.');
} catch (Throwable $error) {
    foreach ($created_ids as $id) $wpdb->delete($bookings, array('id' => $id));
    throw $error;
}
