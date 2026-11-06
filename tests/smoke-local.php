<?php
// Integration smoke test. Run with wp eval-file tests/smoke-local.php in Local.
if (!defined('WP_CLI') || !WP_CLI || !str_contains(wp_normalize_path(ABSPATH), '/Local Sites/') || get_option('blogname') !== 'SvDt') {
    throw new RuntimeException('Este teste só pode ser usado no site SvDt do Local.');
}

global $wpdb;
$service = SvDt_DB::services()[0] ?? null;
if (!$service) throw new RuntimeException('Cadastre um serviço primeiro.');
$professional = SvDt_DB::professionals(true, (int) $service->id)[0] ?? null;
if (!$professional) throw new RuntimeException('Nenhum profissional ativo atende o serviço de teste.');
$professional_id = (int) $professional->id;
$day = current_datetime()->modify('+7 days');
while ((int) $day->format('w') === 0 || (int) $day->format('w') === 6) $day = $day->modify('+1 day');
$date = $day->format('Y-m-d');
$slots = SvDt_Schedule::slots((int) $service->id, $date, $professional_id);
if (is_wp_error($slots) || !$slots) throw new RuntimeException('Sem horários disponíveis na data de teste.');
$time = $slots[0];
$data = array(
    'service_id' => (int) $service->id, 'professional_id' => $professional_id, 'date' => $date, 'time' => $time,
    'customer_name' => 'Cliente Teste', 'customer_email' => 'teste@example.com',
    'customer_phone' => '11999999999', 'notes' => '',
);
$booking_id = null;
$block_id = null;
$original_active = (int) $service->active;
try {
    $created = SvDt_Bookings::create($data);
    if (is_wp_error($created)) throw new RuntimeException($created->get_error_message());
    $booking_id = (int) substr($created['reference'], 3);
    $after = SvDt_Schedule::slots((int) $service->id, $date, $professional_id);
    if (in_array($time, $after, true)) throw new RuntimeException('Reserva pendente não bloqueou o horário.');
    $duplicate = SvDt_Bookings::create($data);
    if (!is_wp_error($duplicate) || $duplicate->get_error_code() !== 'slot_unavailable') throw new RuntimeException('Reserva duplicada foi aceita.');
    $controller = new SvDt_REST();
    $change = new WP_REST_Request('PATCH');
    $change->set_param('id', $booking_id);
    $change->set_param('status', 'confirmed');
    if (is_wp_error($controller->update_booking($change))) throw new RuntimeException('Confirmação falhou.');
    $stale = $wpdb->update(SvDt_DB::table('bookings'), array('status' => 'cancelled'), array('id' => $booking_id, 'status' => 'pending'), array('%s'), array('%d', '%s'));
    if ($stale !== 0 || $wpdb->get_var($wpdb->prepare('SELECT status FROM ' . SvDt_DB::table('bookings') . ' WHERE id = %d', $booking_id)) !== 'confirmed') {
        throw new RuntimeException('Atualização condicional não protegeu o status confirmado.');
    }
    $change->set_param('status', 'cancelled');
    if (is_wp_error($controller->update_booking($change))) throw new RuntimeException('Cancelamento falhou.');
    $released = SvDt_Schedule::slots((int) $service->id, $date, $professional_id);
    if (!in_array($time, $released, true)) throw new RuntimeException('Cancelamento não liberou o horário.');
    $change->set_param('status', 'confirmed');
    if (!is_wp_error($controller->update_booking($change))) throw new RuntimeException('Reserva cancelada foi reativada.');
    $invalid = SvDt_Bookings::create(array_replace($data, array('customer_email' => 'invalido')));
    if (!is_wp_error($invalid)) throw new RuntimeException('Entrada inválida foi aceita.');
    $wpdb->insert(SvDt_DB::table('blocked_dates'), array('blocked_date' => $date, 'reason' => 'Teste'));
    $block_id = (int) $wpdb->insert_id;
    if (SvDt_Schedule::slots((int) $service->id, $date, $professional_id) !== array()) throw new RuntimeException('Data bloqueada retornou horários.');
    $wpdb->delete(SvDt_DB::table('blocked_dates'), array('id' => $block_id));
    $block_id = null;
    $wpdb->update(SvDt_DB::table('services'), array('active' => 0), array('id' => $service->id));
    if (!is_wp_error(SvDt_Schedule::slots((int) $service->id, $date, $professional_id))) throw new RuntimeException('Serviço inativo retornou horários.');
    $wpdb->update(SvDt_DB::table('services'), array('active' => $original_active), array('id' => $service->id));
    if (SvDt_Schedule::slots((int) $service->id, '2020-01-01', $professional_id) !== array()) throw new RuntimeException('Data passada retornou horários.');
    $last_allowed = current_datetime()->modify('+90 days')->format('Y-m-d');
    if (is_wp_error(SvDt_Schedule::slots((int) $service->id, $last_allowed, $professional_id))) throw new RuntimeException('O último dia da janela foi rejeitado.');
    $beyond = current_datetime()->modify('+91 days')->format('Y-m-d');
    $outside = SvDt_Schedule::slots((int) $service->id, $beyond, $professional_id);
    if (!is_wp_error($outside) || $outside->get_error_code() !== 'date_out_of_range') throw new RuntimeException('Data além de 90 dias foi aceita na disponibilidade.');
    $outside_booking = SvDt_Bookings::create(array_replace($data, array('date' => $beyond)));
    if (!is_wp_error($outside_booking) || $outside_booking->get_error_code() !== 'date_out_of_range') throw new RuntimeException('Data além de 90 dias foi aceita na reserva.');
    WP_CLI::success('Reserva, conflito, estados, atualização condicional, limite de 90 dias, cancelamento, bloqueio, serviço inativo e entradas inválidas passaram.');
} finally {
    if ($booking_id) $wpdb->delete(SvDt_DB::table('bookings'), array('id' => $booking_id));
    if ($block_id) $wpdb->delete(SvDt_DB::table('blocked_dates'), array('id' => $block_id));
    $wpdb->update(SvDt_DB::table('services'), array('active' => $original_active), array('id' => $service->id));
}
