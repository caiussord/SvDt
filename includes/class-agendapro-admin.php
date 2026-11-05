<?php
defined('ABSPATH') || exit;

final class AgendaPro_Admin
{
    public function register(): void
    {
        add_action('admin_menu', function () {
            add_menu_page('SvDt', 'SvDt', 'manage_options', 'agendapro', array($this, 'bookings_page'), 'dashicons-calendar-alt', 26);
            add_submenu_page('agendapro', 'Agendamentos', 'Agendamentos', 'manage_options', 'agendapro', array($this, 'bookings_page'));
            add_submenu_page('agendapro', 'Serviços', 'Serviços', 'manage_options', 'agendapro-services', array($this, 'services_page'));
            add_submenu_page('agendapro', 'Disponibilidade', 'Disponibilidade', 'manage_options', 'agendapro-schedule', array($this, 'schedule_page'));
        });
        add_action('admin_enqueue_scripts', function ($hook) {
            if (str_contains($hook, 'agendapro')) {
                wp_enqueue_style('agendapro-admin', AGENDAPRO_URL . 'admin/admin.css', array(), AGENDAPRO_VERSION);
            }
        });
        foreach (array('save_service', 'delete_service', 'save_hours', 'add_block', 'delete_block', 'update_booking') as $action) {
            add_action('admin_post_agendapro_' . $action, array($this, $action));
        }
    }

    private function guard(string $action): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Acesso negado.', '', array('response' => 403));
        }
        check_admin_referer('agendapro_' . $action);
    }

    private function redirect(string $page, string $message): void
    {
        wp_safe_redirect(admin_url('admin.php?page=' . $page . '&notice=' . rawurlencode($message)));
        exit;
    }

    private function header(string $title, string $description): void
    {
        echo '<div class="wrap ag-admin"><div class="ag-admin-head"><div><span class="ag-eyebrow">SV·DT / GESTÃO</span><h1>' . esc_html($title) . '</h1><p>' . esc_html($description) . '</p></div><span class="ag-admin-brand">Sv<span>Dt.</span></span></div>';
        if (isset($_GET['notice'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['notice']))) . '</p></div>';
        }
    }

    public function bookings_page(): void
    {
        if (!current_user_can('manage_options')) return;
        global $wpdb;
        $table = AgendaPro_DB::table('bookings');
        $services = AgendaPro_DB::table('services');
        $date = isset($_GET['date']) ? sanitize_text_field(wp_unslash($_GET['date'])) : current_datetime()->format('Y-m-d');
        if (!AgendaPro_Schedule::parse_date($date)) $date = current_datetime()->format('Y-m-d');
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        $service_id = isset($_GET['service_id']) ? absint($_GET['service_id']) : 0;
        $where = 'DATE(b.starts_at) = %s';
        $args = array($date);
        if (in_array($status, array('pending', 'confirmed', 'cancelled', 'completed'), true)) {
            $where .= ' AND b.status = %s';
            $args[] = $status;
        }
        if ($service_id) {
            $where .= ' AND b.service_id = %d';
            $args[] = $service_id;
        }
        $rows = $wpdb->get_results($wpdb->prepare("SELECT b.*, s.name AS service_name FROM $table b LEFT JOIN $services s ON s.id = b.service_id WHERE $where ORDER BY b.starts_at ASC LIMIT 200", $args));
        $counts = array('pending' => 0, 'confirmed' => 0, 'cancelled' => 0, 'completed' => 0);
        $status_labels = array('pending' => 'Pendente', 'confirmed' => 'Confirmado', 'cancelled' => 'Cancelado', 'completed' => 'Concluído');
        $daily_counts = $wpdb->get_results($wpdb->prepare("SELECT status, COUNT(*) AS total FROM $table WHERE DATE(starts_at) = %s GROUP BY status", $date));
        foreach ($daily_counts as $item) $counts[$item->status] = (int) $item->total;
        $daily_total = array_sum($counts);
        $this->header('Agendamentos', 'Acompanhe os pedidos e organize seu dia.');
        echo '<div class="ag-metrics"><div><span>Reservas do dia</span><strong>' . $daily_total . '</strong><small>Visão geral</small></div><div><span>Aguardando decisão</span><strong>' . $counts['pending'] . '</strong><small>Pedidos pendentes</small></div><div><span>Confirmadas</span><strong>' . $counts['confirmed'] . '</strong><small>Horários garantidos</small></div></div>';
        echo '<form class="ag-filter" method="get"><input type="hidden" name="page" value="agendapro"><label>Data <input type="date" name="date" value="' . esc_attr($date) . '"></label><label>Status <select name="status"><option value="">Todos</option>';
        foreach (array('pending' => 'Pendente', 'confirmed' => 'Confirmado', 'cancelled' => 'Cancelado', 'completed' => 'Concluído') as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label><label>Serviço <select name="service_id"><option value="0">Todos</option>';
        foreach (AgendaPro_DB::services(false) as $service) {
            echo '<option value="' . esc_attr($service->id) . '" ' . selected($service_id, (int) $service->id, false) . '>' . esc_html($service->name) . '</option>';
        }
        echo '</select></label><button class="button button-primary">Filtrar</button></form>';
        echo '<div class="ag-panel ag-agenda-panel"><div class="ag-agenda-heading"><div><span>LINHA DO TEMPO</span><h2>Reservas de ' . esc_html(AgendaPro_Schedule::parse_date($date)->format('d/m/Y')) . '</h2></div><strong>' . count($rows) . ' ' . (count($rows) === 1 ? 'registro' : 'registros') . '</strong></div>';
        if (!$rows) echo '<div class="ag-empty"><span aria-hidden="true">✦</span><strong>Um dia livre por aqui.</strong><p>Nenhum agendamento encontrado para os filtros escolhidos.</p></div>';
        else {
            echo '<div class="ag-booking-list">';
            foreach ($rows as $row) {
                echo '<article class="ag-booking-item"><div class="ag-booking-time"><span>INÍCIO</span><strong>' . esc_html(substr($row->starts_at, 11, 5)) . '</strong><small>#AG-' . esc_html(str_pad((string) $row->id, 6, '0', STR_PAD_LEFT)) . '</small></div><div class="ag-booking-details"><div class="ag-booking-main"><span class="ag-service-pill">' . esc_html($row->service_name ?: 'Serviço removido') . '</span><span class="ag-status ag-status-' . esc_attr($row->status) . '">' . esc_html($status_labels[$row->status] ?? $row->status) . '</span></div><h3>' . esc_html($row->customer_name) . '</h3><div class="ag-booking-contact"><a href="mailto:' . esc_attr($row->customer_email) . '">' . esc_html($row->customer_email) . '</a><a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $row->customer_phone)) . '">' . esc_html($row->customer_phone) . '</a></div>';
                if ($row->notes) echo '<details class="ag-booking-notes"><summary>Ver observações</summary><p>' . esc_html($row->notes) . '</p></details>';
                echo '</div><div class="ag-booking-actions">';
                foreach (array('confirmed' => 'Confirmar', 'cancelled' => 'Cancelar', 'completed' => 'Concluir') as $next => $label) {
                    if (!in_array($row->status, array('pending', 'confirmed'), true) || $row->status === $next || ($next === 'completed' && $row->status !== 'confirmed') || ($next === 'confirmed' && $row->status !== 'pending')) continue;
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="ag-inline"><input type="hidden" name="action" value="agendapro_update_booking"><input type="hidden" name="id" value="' . esc_attr($row->id) . '"><input type="hidden" name="status" value="' . esc_attr($next) . '">';
                    wp_nonce_field('agendapro_update_booking');
                    echo '<button class="button button-small ' . ($next === 'confirmed' ? 'ag-action-primary' : '') . '">' . esc_html($label) . '</button></form> ';
                }
                echo '</div></article>';
            }
            echo '</div>';
        }
        echo '</div></div>';
    }

    public function update_booking(): void
    {
        $this->guard('update_booking');
        $request = new WP_REST_Request('PATCH');
        $request->set_param('id', absint($_POST['id'] ?? 0));
        $request->set_param('status', sanitize_key(wp_unslash($_POST['status'] ?? '')));
        $result = (new AgendaPro_REST())->update_booking($request);
        $this->redirect('agendapro', is_wp_error($result) ? $result->get_error_message() : 'Agendamento atualizado.');
    }

    public function services_page(): void
    {
        if (!current_user_can('manage_options')) return;
        $this->header('Serviços', 'Cadastre as opções que seus clientes podem reservar.');
        echo '<div class="ag-columns"><div class="ag-panel"><h2>Novo serviço</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="ag-form"><input type="hidden" name="action" value="agendapro_save_service">';
        wp_nonce_field('agendapro_save_service');
        echo '<label>Nome<input name="name" maxlength="150" required></label><label>Descrição curta<textarea name="description" rows="3"></textarea></label><div class="ag-form-row"><label>Duração (min)<input type="number" name="duration" min="5" max="480" value="60" required></label><label>Intervalo após (min)<input type="number" name="buffer" min="0" max="120" value="0" required></label></div><button class="button button-primary">Adicionar serviço</button></form></div><div class="ag-panel"><h2>Serviços cadastrados</h2>';
        $items = AgendaPro_DB::services(false);
        if (!$items) echo '<div class="ag-empty">Comece cadastrando seu primeiro serviço.</div>';
        foreach ($items as $item) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="ag-service"><input type="hidden" name="action" value="agendapro_save_service"><input type="hidden" name="id" value="' . esc_attr($item->id) . '">';
            wp_nonce_field('agendapro_save_service');
            echo '<label>Nome<input name="name" value="' . esc_attr($item->name) . '" maxlength="150" required></label><label>Descrição<textarea name="description" rows="2">' . esc_textarea($item->description) . '</textarea></label><div class="ag-form-row"><label>Duração<input type="number" name="duration" min="5" max="480" value="' . esc_attr($item->duration_minutes) . '" required></label><label>Intervalo<input type="number" name="buffer" min="0" max="120" value="' . esc_attr($item->buffer_minutes) . '" required></label></div><label class="ag-check"><input type="checkbox" name="active" value="1" ' . checked($item->active, 1, false) . '> Ativo para reservas</label><button class="button">Salvar alterações</button></form>';
        }
        echo '</div></div></div>';
    }

    public function save_service(): void
    {
        $this->guard('save_service');
        global $wpdb;
        $id = absint($_POST['id'] ?? 0);
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $description = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
        $duration = absint($_POST['duration'] ?? 0);
        $buffer = absint($_POST['buffer'] ?? 0);
        if (!$name || strlen($name) > 150 || $duration < 5 || $duration > 480 || $buffer > 120) {
            $this->redirect('agendapro-services', 'Dados inválidos.');
        }
        $data = array('name' => $name, 'description' => $description, 'duration_minutes' => $duration, 'buffer_minutes' => $buffer, 'active' => isset($_POST['active']) || !$id ? 1 : 0);
        if ($id) $wpdb->update(AgendaPro_DB::table('services'), $data, array('id' => $id));
        else $wpdb->insert(AgendaPro_DB::table('services'), $data + array('created_at' => current_time('mysql')));
        $this->redirect('agendapro-services', 'Serviço salvo.');
    }

    public function delete_service(): void
    {
        $this->guard('delete_service');
        global $wpdb;
        $wpdb->update(AgendaPro_DB::table('services'), array('active' => 0), array('id' => absint($_POST['id'] ?? 0)));
        $this->redirect('agendapro-services', 'Serviço desativado.');
    }

    public function schedule_page(): void
    {
        if (!current_user_can('manage_options')) return;
        global $wpdb;
        $hours = $wpdb->get_results('SELECT * FROM ' . AgendaPro_DB::table('business_hours') . ' ORDER BY weekday, start_time');
        $blocks = $wpdb->get_results('SELECT * FROM ' . AgendaPro_DB::table('blocked_dates') . ' ORDER BY blocked_date DESC LIMIT 100');
        $this->header('Disponibilidade', 'Defina o expediente semanal e bloqueie dias sem atendimento.');
        echo '<div class="ag-columns"><div class="ag-panel"><h2>Expediente</h2><p>Adicione um intervalo por vez. Intervalos do mesmo dia podem representar pausas.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="ag-form"><input type="hidden" name="action" value="agendapro_save_hours">';
        wp_nonce_field('agendapro_save_hours');
        echo '<label>Dia da semana<select name="weekday">';
        foreach (array('Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado') as $i => $day) echo '<option value="' . $i . '">' . esc_html($day) . '</option>';
        echo '</select></label><div class="ag-form-row"><label>Início<input type="time" name="start" required></label><label>Fim<input type="time" name="end" required></label></div><button class="button button-primary">Adicionar intervalo</button></form><div class="ag-list">';
        foreach ($hours as $row) {
            echo '<div><span>' . esc_html(array('Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb')[(int) $row->weekday]) . ' · ' . esc_html(substr($row->start_time, 0, 5)) . '–' . esc_html(substr($row->end_time, 0, 5)) . '</span><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="agendapro_save_hours"><input type="hidden" name="delete_id" value="' . esc_attr($row->id) . '">';
            wp_nonce_field('agendapro_save_hours');
            echo '<button class="button button-small">Remover</button></form></div>';
        }
        echo '</div></div><div class="ag-panel"><h2>Datas bloqueadas</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="ag-form"><input type="hidden" name="action" value="agendapro_add_block">';
        wp_nonce_field('agendapro_add_block');
        echo '<label>Data<input type="date" name="date" required></label><label>Motivo opcional<input name="reason" maxlength="255"></label><button class="button button-primary">Bloquear data</button></form><div class="ag-list">';
        foreach ($blocks as $row) {
            echo '<div><span>' . esc_html($row->blocked_date) . ' ' . esc_html($row->reason) . '</span><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="agendapro_delete_block"><input type="hidden" name="id" value="' . esc_attr($row->id) . '">';
            wp_nonce_field('agendapro_delete_block');
            echo '<button class="button button-small">Liberar</button></form></div>';
        }
        echo '</div></div></div></div>';
    }

    public function save_hours(): void
    {
        $this->guard('save_hours');
        global $wpdb;
        $table = AgendaPro_DB::table('business_hours');
        if (!empty($_POST['delete_id'])) {
            $wpdb->delete($table, array('id' => absint($_POST['delete_id'])));
        } else {
            $day = absint($_POST['weekday'] ?? 9);
            $start = sanitize_text_field(wp_unslash($_POST['start'] ?? ''));
            $end = sanitize_text_field(wp_unslash($_POST['end'] ?? ''));
            if ($day > 6 || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $start) || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $end) || $start >= $end) {
                $this->redirect('agendapro-schedule', 'Intervalo inválido.');
            }
            $wpdb->insert($table, array('weekday' => $day, 'start_time' => $start . ':00', 'end_time' => $end . ':00', 'active' => 1));
        }
        $this->redirect('agendapro-schedule', 'Expediente atualizado.');
    }

    public function add_block(): void
    {
        $this->guard('add_block');
        global $wpdb;
        $date = sanitize_text_field(wp_unslash($_POST['date'] ?? ''));
        if (!AgendaPro_Schedule::parse_date($date)) $this->redirect('agendapro-schedule', 'Data inválida.');
        $wpdb->replace(AgendaPro_DB::table('blocked_dates'), array('blocked_date' => $date, 'reason' => sanitize_text_field(wp_unslash($_POST['reason'] ?? ''))));
        $this->redirect('agendapro-schedule', 'Data bloqueada.');
    }

    public function delete_block(): void
    {
        $this->guard('delete_block');
        global $wpdb;
        $wpdb->delete(AgendaPro_DB::table('blocked_dates'), array('id' => absint($_POST['id'] ?? 0)));
        $this->redirect('agendapro-schedule', 'Data liberada.');
    }
}
