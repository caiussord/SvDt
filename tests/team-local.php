<?php
// Run with wp eval-file tests/team-local.php in the SvDt Local site.
if (!defined('WP_CLI') || !WP_CLI || !str_contains(wp_normalize_path(ABSPATH), '/Local Sites/') || get_option('blogname') !== 'SvDt') {
    throw new RuntimeException('Este teste só pode ser usado no site SvDt do Local.');
}

global $wpdb;
$service = SvDt_DB::services()[0] ?? null;
$default_id = (int) get_option('svdt_default_professional_id');
if (!$service || !SvDt_DB::professional($default_id)) throw new RuntimeException('Agenda padrão indisponível.');
$professionals = SvDt_DB::table('professionals');
$links = SvDt_DB::table('professional_services');
$hours = SvDt_DB::table('professional_hours');
$blocks = SvDt_DB::table('professional_blocked_dates');
$bookings = SvDt_DB::table('bookings');
$second_id = 0;
$booking_ids = array();
$block_id = 0;
$concurrency_marker = 'svdt-team-test-' . bin2hex(random_bytes(8));
try {
    if (!$wpdb->insert($professionals, array('name' => 'Teste equipe temporário', 'description' => '', 'active' => 1, 'sort_order' => 99, 'created_at' => current_time('mysql')))) throw new RuntimeException('Falha ao criar profissional de teste.');
    $second_id = (int) $wpdb->insert_id;
    if (!$wpdb->insert($links, array('professional_id' => $second_id, 'service_id' => $service->id))) throw new RuntimeException('Falha ao vincular serviço de teste.');
    if ($wpdb->query($wpdb->prepare("INSERT INTO $hours (professional_id,weekday,start_time,end_time,active) SELECT %d,weekday,start_time,end_time,active FROM $hours WHERE professional_id=%d", $second_id, $default_id)) === false) throw new RuntimeException('Falha ao copiar expediente de teste.');
    $date = null;
    $time = null;
    for ($offset = 7; $offset <= 20; $offset++) {
        $candidate = current_datetime()->modify('+' . $offset . ' days')->format('Y-m-d');
        $first_slots = SvDt_Schedule::slots((int) $service->id, $candidate, $default_id);
        $second_slots = SvDt_Schedule::slots((int) $service->id, $candidate, $second_id);
        if (is_wp_error($first_slots) || is_wp_error($second_slots)) throw new RuntimeException('Consulta de disponibilidade falhou.');
        $common = array_values(array_intersect($first_slots, $second_slots));
        if ($common) { $date = $candidate; $time = $common[0]; break; }
    }
    if (!$date) throw new RuntimeException('Nenhum horário comum para teste.');
    $legacy = SvDt_DB::resolve_professional((int) $service->id);
    if (!is_wp_error($legacy) || $legacy->get_error_code() !== 'professional_required') throw new RuntimeException('Escolha obrigatória de profissional não foi exigida.');
    $data = array('service_id' => (int) $service->id, 'date' => $date, 'time' => $time, 'customer_name' => 'Cliente Teste Equipe', 'customer_email' => 'teste@example.com', 'customer_phone' => '11999999999', 'notes' => 'Teste temporário');
    $first = SvDt_Bookings::create($data + array('professional_id' => $default_id));
    if (is_wp_error($first)) throw new RuntimeException('Primeira reserva falhou: ' . $first->get_error_message());
    $booking_ids[] = (int) substr($first['reference'], 3);
    if (!in_array($time, SvDt_Schedule::slots((int) $service->id, $date, $second_id), true)) throw new RuntimeException('Reserva de um profissional bloqueou o outro.');
    $second = SvDt_Bookings::create($data + array('professional_id' => $second_id));
    if (is_wp_error($second)) throw new RuntimeException('Reserva paralela de outro profissional falhou: ' . $second->get_error_message());
    $booking_ids[] = (int) substr($second['reference'], 3);
    $admin_user = get_users(array('role' => 'administrator', 'number' => 1))[0];
    wp_set_current_user($admin_user->ID);
    $_GET = array('date' => $date, 'professional_id' => $second_id);
    ob_start();
    (new SvDt_Admin())->bookings_page();
    $filtered_page = ob_get_clean();
    unset($_GET['date'], $_GET['professional_id']);
    if (!str_contains($filtered_page, '#SV-' . str_pad((string) $booking_ids[1], 6, '0', STR_PAD_LEFT)) || str_contains($filtered_page, '#SV-' . str_pad((string) $booking_ids[0], 6, '0', STR_PAD_LEFT))) {
        throw new RuntimeException('Filtro administrativo por profissional falhou.');
    }
    $duplicate = SvDt_Bookings::create($data + array('professional_id' => $second_id));
    if (!is_wp_error($duplicate) || $duplicate->get_error_code() !== 'slot_unavailable') throw new RuntimeException('Sobreposição no mesmo profissional foi aceita.');
    delete_transient('svdt_rate_' . md5('127.0.0.1'));
    foreach (array('SVDT_SERVICE_ID' => $service->id, 'SVDT_FIRST_PROFESSIONAL_ID' => $default_id, 'SVDT_SECOND_PROFESSIONAL_ID' => $second_id, 'SVDT_DATE' => $date, 'SVDT_TEST_MARKER' => $concurrency_marker) as $key => $value) putenv($key . '=' . $value);
    $pipes = array();
    $process = proc_open(array('node', __DIR__ . '/team-concurrency-local.mjs'), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    if (!is_resource($process)) throw new RuntimeException('Não foi possível iniciar o teste HTTP de concorrência.');
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) throw new RuntimeException('Concorrência HTTP falhou: ' . $error);
    $concurrency_result = json_decode($output, true);
    if (($concurrency_result['same'] ?? null) !== array(201, 409) && ($concurrency_result['same'] ?? null) !== array(409, 201)) throw new RuntimeException('Resultado inesperado para a mesma agenda.');
    if (($concurrency_result['different'] ?? null) !== array(201, 201)) throw new RuntimeException('Resultado inesperado para agendas diferentes.');
    $other_service = array_values(array_filter(SvDt_DB::services(), static fn($item) => (int) $item->id !== (int) $service->id))[0] ?? null;
    if ($other_service) {
        $invalid_service = SvDt_Schedule::slots((int) $other_service->id, $date, $second_id);
        if (!is_wp_error($invalid_service) || $invalid_service->get_error_code() !== 'invalid_professional') throw new RuntimeException('Serviço não vinculado foi aceito.');
    }
    if (!$wpdb->insert($blocks, array('professional_id' => $second_id, 'blocked_date' => $date, 'reason' => 'Teste'))) throw new RuntimeException('Falha ao criar folga de teste.');
    $block_id = (int) $wpdb->insert_id;
    if (SvDt_Schedule::slots((int) $service->id, $date, $second_id) !== array()) throw new RuntimeException('Folga individual não bloqueou o profissional.');
    if (!SvDt_Schedule::slots((int) $service->id, $date, $default_id)) throw new RuntimeException('Folga individual bloqueou a outra agenda.');
    $wpdb->update($professionals, array('active' => 0), array('id' => $second_id));
    $inactive = SvDt_Schedule::slots((int) $service->id, $date, $second_id);
    if (!is_wp_error($inactive) || $inactive->get_error_code() !== 'invalid_professional') throw new RuntimeException('Profissional inativo foi aceito.');
    WP_CLI::success('Agenda individual, concorrência HTTP na mesma agenda e em agendas diferentes, filtro administrativo, serviço incompatível, folga e estado inativo passaram.');
} finally {
    $wpdb->query($wpdb->prepare("DELETE FROM $bookings WHERE notes=%s AND customer_email=%s", $concurrency_marker, 'team-concurrency@example.com'));
    delete_transient('svdt_rate_' . md5('127.0.0.1'));
    foreach ($booking_ids as $id) $wpdb->delete($bookings, array('id' => $id));
    if ($block_id) $wpdb->delete($blocks, array('id' => $block_id));
    if ($second_id) {
        $wpdb->delete($hours, array('professional_id' => $second_id));
        $wpdb->delete($links, array('professional_id' => $second_id));
        $wpdb->delete($professionals, array('id' => $second_id));
    }
}
