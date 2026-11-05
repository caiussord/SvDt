<?php
// Integration smoke test. Run with wp eval-file tests/smoke-local.php on agendapro.local.
if (!defined('WP_CLI') || !WP_CLI || !str_contains(wp_normalize_path(ABSPATH), '/Local Sites/agendapro/')) {
    throw new RuntimeException('Este teste só pode ser usado no site AgendaPro do Local.');
}

global $wpdb;
$service = AgendaPro_DB::services()[0] ?? null;
if (!$service) throw new RuntimeException('Cadastre um serviço primeiro.');
$day = current_datetime()->modify('+7 days');
while ((int) $day->format('w') === 0 || (int) $day->format('w') === 6) $day = $day->modify('+1 day');
$date = $day->format('Y-m-d');
$slots = AgendaPro_Schedule::slots((int) $service->id, $date);
if (is_wp_error($slots) || !$slots) throw new RuntimeException('Sem horários disponíveis na data de teste.');
$time = $slots[0];
$data = array(
    'service_id' => (int) $service->id, 'date' => $date, 'time' => $time,
    'customer_name' => 'Cliente Teste', 'customer_email' => 'teste@example.com',
    'customer_phone' => '11999999999', 'notes' => '',
);
$booking_id = null;
$block_id = null;
$original_active = (int) $service->active;
try {
    $created = AgendaPro_Bookings::create($data);
    if (is_wp_error($created)) throw new RuntimeException($created->get_error_message());
    $booking_id = (int) substr($created['reference'], 3);
    $after = AgendaPro_Schedule::slots((int) $service->id, $date);
    if (in_array($time, $after, true)) throw new RuntimeException('Reserva pendente não bloqueou o horário.');
    $duplicate = AgendaPro_Bookings::create($data);
    if (!is_wp_error($duplicate) || $duplicate->get_error_code() !== 'slot_unavailable') throw new RuntimeException('Reserva duplicada foi aceita.');
    $controller = new AgendaPro_REST();
    $change = new WP_REST_Request('PATCH');
    $change->set_param('id', $booking_id);
    $change->set_param('status', 'confirmed');
    if (is_wp_error($controller->update_booking($change))) throw new RuntimeException('Confirmação falhou.');
    $change->set_param('status', 'cancelled');
    if (is_wp_error($controller->update_booking($change))) throw new RuntimeException('Cancelamento falhou.');
    $released = AgendaPro_Schedule::slots((int) $service->id, $date);
    if (!in_array($time, $released, true)) throw new RuntimeException('Cancelamento não liberou o horário.');
    $change->set_param('status', 'confirmed');
    if (!is_wp_error($controller->update_booking($change))) throw new RuntimeException('Reserva cancelada foi reativada.');
    $invalid = AgendaPro_Bookings::create(array_replace($data, array('customer_email' => 'invalido')));
    if (!is_wp_error($invalid)) throw new RuntimeException('Entrada inválida foi aceita.');
    $wpdb->insert(AgendaPro_DB::table('blocked_dates'), array('blocked_date' => $date, 'reason' => 'Teste'));
    $block_id = (int) $wpdb->insert_id;
    if (AgendaPro_Schedule::slots((int) $service->id, $date) !== array()) throw new RuntimeException('Data bloqueada retornou horários.');
    $wpdb->delete(AgendaPro_DB::table('blocked_dates'), array('id' => $block_id));
    $block_id = null;
    $wpdb->update(AgendaPro_DB::table('services'), array('active' => 0), array('id' => $service->id));
    if (!is_wp_error(AgendaPro_Schedule::slots((int) $service->id, $date))) throw new RuntimeException('Serviço inativo retornou horários.');
    $wpdb->update(AgendaPro_DB::table('services'), array('active' => $original_active), array('id' => $service->id));
    if (AgendaPro_Schedule::slots((int) $service->id, '2020-01-01') !== array()) throw new RuntimeException('Data passada retornou horários.');
    WP_CLI::success('Reserva, conflito, estados, cancelamento, bloqueio, serviço inativo e entradas inválidas passaram.');
} finally {
    if ($booking_id) $wpdb->delete(AgendaPro_DB::table('bookings'), array('id' => $booking_id));
    if ($block_id) $wpdb->delete(AgendaPro_DB::table('blocked_dates'), array('id' => $block_id));
    $wpdb->update(AgendaPro_DB::table('services'), array('active' => $original_active), array('id' => $service->id));
}
