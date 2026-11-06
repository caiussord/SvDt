<?php
defined('ABSPATH') || exit;

final class SvDt_Admin
{
    public function register(): void
    {
        add_action('admin_menu', function () {
            add_menu_page('SvDt', 'SvDt', 'manage_options', 'svdt', array($this, 'bookings_page'), 'dashicons-calendar-alt', 26);
            add_submenu_page('svdt', 'Agendamentos', 'Agendamentos', 'manage_options', 'svdt', array($this, 'bookings_page'));
            add_submenu_page('svdt', 'Serviços', 'Serviços', 'manage_options', 'svdt-services', array($this, 'services_page'));
            add_submenu_page('svdt', 'Profissionais', 'Profissionais', 'manage_options', 'svdt-professionals', array($this, 'professionals_page'));
            add_submenu_page('svdt', 'Disponibilidade', 'Disponibilidade', 'manage_options', 'svdt-schedule', array($this, 'schedule_page'));
        });
        add_action('admin_enqueue_scripts', function ($hook) {
            if (str_contains($hook, 'svdt')) {
                wp_enqueue_style('svdt-admin', SVDT_URL . 'admin/admin.css', array(), SVDT_VERSION);
            }
        });
        foreach (array('save_service', 'delete_service', 'save_professional', 'save_hours', 'add_block', 'delete_block', 'add_professional_block', 'delete_professional_block', 'update_booking') as $action) {
            add_action('admin_post_svdt_' . $action, array($this, $action));
        }
    }

    private function guard(string $action): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Acesso negado.', '', array('response' => 403));
        }
        check_admin_referer('svdt_' . $action);
    }

    private function redirect(string $page, string $message, bool $success = true): void
    {
        wp_safe_redirect(admin_url('admin.php?page=' . $page . '&notice=' . rawurlencode($message) . '&notice_type=' . ($success ? 'success' : 'error')));
        exit;
    }

    private function header(string $title, string $description): void
    {
        echo '<div class="wrap svdt-admin"><div class="svdt-admin-head"><div><span class="svdt-eyebrow">SV·DT / GESTÃO</span><h1>' . esc_html($title) . '</h1><p>' . esc_html($description) . '</p></div><img class="svdt-admin-logo" src="' . esc_url(SVDT_URL . 'public/assets/logo-svdt.svg') . '" alt="SvDt" width="57" height="54"></div>';
        if (isset($_GET['notice'])) {
            $notice_type = isset($_GET['notice_type']) && sanitize_key(wp_unslash($_GET['notice_type'])) === 'error' ? 'error' : 'success';
            echo '<div class="notice notice-' . esc_attr($notice_type) . ' is-dismissible"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['notice']))) . '</p></div>';
        }
    }

    public function bookings_page(): void
    {
        if (!current_user_can('manage_options')) return;
        global $wpdb;
        $table = SvDt_DB::table('bookings');
        $services = SvDt_DB::table('services');
        $professionals = SvDt_DB::table('professionals');
        $date = isset($_GET['date']) ? sanitize_text_field(wp_unslash($_GET['date'])) : current_datetime()->format('Y-m-d');
        if (!SvDt_Schedule::parse_date($date)) $date = current_datetime()->format('Y-m-d');
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        $service_id = isset($_GET['service_id']) ? absint($_GET['service_id']) : 0;
        $professional_id = isset($_GET['professional_id']) ? absint($_GET['professional_id']) : 0;
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
        if ($professional_id) {
            $where .= ' AND b.professional_id = %d';
            $args[] = $professional_id;
        }
        $rows = $wpdb->get_results($wpdb->prepare("SELECT b.*, s.name AS service_name, p.name AS professional_name FROM $table b LEFT JOIN $services s ON s.id = b.service_id LEFT JOIN $professionals p ON p.id = b.professional_id WHERE $where ORDER BY b.starts_at ASC LIMIT 200", $args));
        $counts = array('pending' => 0, 'confirmed' => 0, 'cancelled' => 0, 'completed' => 0);
        $status_labels = array('pending' => 'Pendente', 'confirmed' => 'Confirmado', 'cancelled' => 'Cancelado', 'completed' => 'Concluído');
        $count_where = 'DATE(starts_at) = %s' . ($professional_id ? ' AND professional_id = %d' : '');
        $count_args = $professional_id ? array($date, $professional_id) : array($date);
        $daily_counts = $wpdb->get_results($wpdb->prepare("SELECT status, COUNT(*) AS total FROM $table WHERE $count_where GROUP BY status", $count_args));
        foreach ($daily_counts as $item) $counts[$item->status] = (int) $item->total;
        $daily_total = array_sum($counts);
        $this->header('Agendamentos', 'Acompanhe os pedidos e organize seu dia.');
        echo '<div class="svdt-metrics"><div><span>Reservas do dia</span><strong>' . $daily_total . '</strong><small>Visão geral</small></div><div><span>Aguardando decisão</span><strong>' . $counts['pending'] . '</strong><small>Pedidos pendentes</small></div><div><span>Confirmadas</span><strong>' . $counts['confirmed'] . '</strong><small>Horários garantidos</small></div></div>';
        echo '<form class="svdt-filter" method="get"><input type="hidden" name="page" value="svdt"><label>Data <input type="date" name="date" value="' . esc_attr($date) . '"></label><label>Status <select name="status"><option value="">Todos</option>';
        foreach (array('pending' => 'Pendente', 'confirmed' => 'Confirmado', 'cancelled' => 'Cancelado', 'completed' => 'Concluído') as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label><label>Serviço <select name="service_id"><option value="0">Todos</option>';
        foreach (SvDt_DB::services(false) as $service) {
            echo '<option value="' . esc_attr($service->id) . '" ' . selected($service_id, (int) $service->id, false) . '>' . esc_html($service->name) . '</option>';
        }
        echo '</select></label><label>Profissional <select name="professional_id"><option value="0">Todos</option>';
        foreach (SvDt_DB::professionals(false) as $professional) {
            echo '<option value="' . esc_attr($professional->id) . '" ' . selected($professional_id, (int) $professional->id, false) . '>' . esc_html($professional->name) . '</option>';
        }
        echo '</select></label><button class="button button-primary">Filtrar</button></form>';
        $this->booking_calendar($date, $status, $service_id, $professional_id);
        echo '<div class="svdt-panel svdt-agenda-panel"><div class="svdt-agenda-heading"><div><span>LINHA DO TEMPO</span><h2>Reservas de ' . esc_html(SvDt_Schedule::parse_date($date)->format('d/m/Y')) . '</h2></div><strong>' . count($rows) . ' ' . (count($rows) === 1 ? 'registro' : 'registros') . '</strong></div>';
        if (!$rows) echo '<div class="svdt-empty"><strong>Um dia livre por aqui.</strong><p>Nenhum agendamento encontrado para os filtros escolhidos.</p></div>';
        else {
            echo '<div class="svdt-booking-list">';
            foreach ($rows as $row) {
                echo '<article class="svdt-booking-item"><div class="svdt-booking-time"><span>INÍCIO</span><strong>' . esc_html(substr($row->starts_at, 11, 5)) . '</strong><small>#SV-' . esc_html(str_pad((string) $row->id, 6, '0', STR_PAD_LEFT)) . '</small></div><div class="svdt-booking-details"><div class="svdt-booking-main"><span class="svdt-service-pill">' . esc_html($row->service_name ?: 'Serviço removido') . '</span><span class="svdt-professional-pill">' . esc_html($row->professional_name ?: 'Profissional removido') . '</span><span class="svdt-status svdt-status-' . esc_attr($row->status) . '">' . esc_html($status_labels[$row->status] ?? $row->status) . '</span></div><h3>' . esc_html($row->customer_name) . '</h3><div class="svdt-booking-contact"><a href="mailto:' . esc_attr($row->customer_email) . '">' . esc_html($row->customer_email) . '</a><a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $row->customer_phone)) . '">' . esc_html($row->customer_phone) . '</a></div>';
                if ($row->notes) echo '<details class="svdt-booking-notes"><summary>Ver observações</summary><p>' . esc_html($row->notes) . '</p></details>';
                echo '</div><div class="svdt-booking-actions">';
                foreach (array('confirmed' => 'Confirmar', 'cancelled' => 'Cancelar', 'completed' => 'Concluir') as $next => $label) {
                    if (!in_array($row->status, array('pending', 'confirmed'), true) || $row->status === $next || ($next === 'completed' && $row->status !== 'confirmed') || ($next === 'confirmed' && $row->status !== 'pending')) continue;
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="svdt-inline"><input type="hidden" name="action" value="svdt_update_booking"><input type="hidden" name="id" value="' . esc_attr($row->id) . '"><input type="hidden" name="status" value="' . esc_attr($next) . '">';
                    wp_nonce_field('svdt_update_booking');
                    echo '<button class="button button-small ' . ($next === 'confirmed' ? 'svdt-action-primary' : '') . '">' . esc_html($label) . '</button></form> ';
                }
                echo '</div></article>';
            }
            echo '</div>';
        }
        echo '</div></div>';
    }

    private function booking_calendar(string $date, string $status, int $service_id, int $professional_id): void
    {
        global $wpdb;
        $requested_month = isset($_GET['month']) ? sanitize_text_field(wp_unslash($_GET['month'])) : '';
        $month = preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/', $requested_month)
            ? SvDt_Schedule::parse_date($requested_month . '-01') : null;
        $month ??= SvDt_Schedule::parse_date(substr($date, 0, 7) . '-01');
        $next_month = $month->modify('+1 month');
        $table = SvDt_DB::table('bookings');
        $professional_where = $professional_id ? ' AND professional_id = %d' : '';
        $query_args = $professional_id ? array($month->format('Y-m-d H:i:s'), $next_month->format('Y-m-d H:i:s'), $professional_id) : array($month->format('Y-m-d H:i:s'), $next_month->format('Y-m-d H:i:s'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(starts_at) AS day, COUNT(*) AS total FROM $table
             WHERE starts_at >= %s AND starts_at < %s
             AND status IN ('pending', 'confirmed', 'completed')
             $professional_where
             GROUP BY DATE(starts_at)",
            $query_args
        ));
        $booked_days = array();
        foreach ($rows as $row) $booked_days[$row->day] = (int) $row->total;
        $base_query = array('page' => 'svdt', 'date' => $date, 'status' => $status, 'service_id' => $service_id, 'professional_id' => $professional_id);
        $month_url = static fn(string $value): string => add_query_arg(array_merge($base_query, array('date' => $value . '-01', 'month' => $value)), admin_url('admin.php'));

        echo '<section class="svdt-calendar-panel" aria-labelledby="svdt-calendar-title"><div class="svdt-calendar-main">';
        echo '<div class="svdt-calendar-heading"><div><span>AGENDA DO MÊS</span><h2 id="svdt-calendar-title">' . esc_html(ucfirst(wp_date('F Y', $month->getTimestamp(), wp_timezone()))) . '</h2></div><div class="svdt-calendar-nav">';
        echo '<a href="' . esc_url($month_url($month->modify('-1 month')->format('Y-m'))) . '" aria-label="Mês anterior">Anterior</a>';
        echo '<a href="' . esc_url($month_url($next_month->format('Y-m'))) . '" aria-label="Próximo mês">Próximo</a></div></div>';
        echo '<div class="svdt-admin-calendar" aria-label="Dias do mês">';
        foreach (array('Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom') as $weekday) {
            echo '<span class="svdt-admin-calendar-weekday">' . esc_html($weekday) . '</span>';
        }
        for ($blank = 1; $blank < (int) $month->format('N'); $blank++) {
            echo '<span class="svdt-admin-calendar-blank" aria-hidden="true"></span>';
        }
        for ($day = 1, $total = (int) $next_month->modify('-1 day')->format('j'); $day <= $total; $day++) {
            $day_date = $month->modify('+' . ($day - 1) . ' days')->format('Y-m-d');
            $count = $booked_days[$day_date] ?? 0;
            $classes = 'svdt-admin-calendar-day' . ($day_date === $date ? ' is-selected' : '') . ($count ? ' has-bookings' : '');
            $label = $day . ' de ' . wp_date('F', $month->getTimestamp(), wp_timezone());
            if ($count) $label .= ', ' . $count . ' ' . ($count === 1 ? 'reserva' : 'reservas');
            $url = add_query_arg(array_merge($base_query, array('date' => $day_date, 'month' => $month->format('Y-m'))), admin_url('admin.php'));
            echo '<a class="' . esc_attr($classes) . '" href="' . esc_url($url) . '" aria-label="' . esc_attr($label) . '"' . ($day_date === $date ? ' aria-current="date"' : '') . '><span>' . esc_html((string) $day) . '</span>';
            if ($count) echo '<i aria-hidden="true"></i>';
            echo '</a>';
        }
        echo '</div></div><p class="svdt-calendar-help"><span aria-hidden="true"></span> O ponto indica uma reserva registrada no dia. Dias sem reservas não recebem marcação.</p></section>';
    }

    public function update_booking(): void
    {
        $this->guard('update_booking');
        $request = new WP_REST_Request('PATCH');
        $request->set_param('id', absint($_POST['id'] ?? 0));
        $request->set_param('status', sanitize_key(wp_unslash($_POST['status'] ?? '')));
        $result = (new SvDt_REST())->update_booking($request);
        $this->redirect('svdt', is_wp_error($result) ? $result->get_error_message() : 'Agendamento atualizado.', !is_wp_error($result));
    }

    public function services_page(): void
    {
        if (!current_user_can('manage_options')) return;
        $this->header('Serviços', 'Cadastre as opções que seus clientes podem reservar.');
        echo '<div class="svdt-columns"><div class="svdt-panel"><h2>Novo serviço</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="svdt-form"><input type="hidden" name="action" value="svdt_save_service">';
        wp_nonce_field('svdt_save_service');
        echo '<label>Nome<input name="name" maxlength="150" required></label><label>Descrição curta<textarea name="description" rows="3"></textarea></label><div class="svdt-form-row"><label>Duração (min)<input type="number" name="duration" min="5" max="480" value="60" required></label><label>Intervalo após (min)<input type="number" name="buffer" min="0" max="120" value="0" required></label></div><button class="button button-primary">Adicionar serviço</button></form></div><div class="svdt-panel"><h2>Serviços cadastrados</h2>';
        $items = SvDt_DB::services(false);
        if (!$items) echo '<div class="svdt-empty">Comece cadastrando seu primeiro serviço.</div>';
        foreach ($items as $item) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="svdt-service"><input type="hidden" name="action" value="svdt_save_service"><input type="hidden" name="id" value="' . esc_attr($item->id) . '">';
            wp_nonce_field('svdt_save_service');
            echo '<label>Nome<input name="name" value="' . esc_attr($item->name) . '" maxlength="150" required></label><label>Descrição<textarea name="description" rows="2">' . esc_textarea($item->description) . '</textarea></label><div class="svdt-form-row"><label>Duração<input type="number" name="duration" min="5" max="480" value="' . esc_attr($item->duration_minutes) . '" required></label><label>Intervalo<input type="number" name="buffer" min="0" max="120" value="' . esc_attr($item->buffer_minutes) . '" required></label></div><label class="svdt-check"><input type="checkbox" name="active" value="1" ' . checked($item->active, 1, false) . '> Ativo para reservas</label><button class="button">Salvar alterações</button></form>';
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
            $this->redirect('svdt-services', 'Dados inválidos.', false);
        }
        $data = array('name' => $name, 'description' => $description, 'duration_minutes' => $duration, 'buffer_minutes' => $buffer, 'active' => isset($_POST['active']) || !$id ? 1 : 0);
        $table = SvDt_DB::table('services');
        if ($id) {
            $result = $wpdb->update($table, $data, array('id' => $id));
            if ($result === 0 && !$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE id = %d", $id))) {
                $this->redirect('svdt-services', 'Serviço não encontrado.', false);
            }
        } else {
            $result = $wpdb->insert($table, $data + array('created_at' => current_time('mysql')));
            if ($result) {
                $new_service_id = (int) $wpdb->insert_id;
                $team = SvDt_DB::professionals();
                if (count($team) === 1) {
                    $links = SvDt_DB::table('professional_services');
                    if (!$wpdb->insert($links, array('professional_id' => $team[0]->id, 'service_id' => $new_service_id))) {
                        $this->redirect('svdt-services', 'Serviço salvo, mas não foi possível associá-lo ao profissional.', false);
                    }
                }
            }
        }
        if ($result === false) $this->redirect('svdt-services', 'Não foi possível salvar o serviço.', false);
        $this->redirect('svdt-services', 'Serviço salvo.');
    }

    public function delete_service(): void
    {
        $this->guard('delete_service');
        global $wpdb;
        $result = $wpdb->update(SvDt_DB::table('services'), array('active' => 0), array('id' => absint($_POST['id'] ?? 0)));
        if ($result === false) $this->redirect('svdt-services', 'Não foi possível desativar o serviço.', false);
        if ($result === 0) $this->redirect('svdt-services', 'Serviço não encontrado ou já desativado.', false);
        $this->redirect('svdt-services', 'Serviço desativado.');
    }

    public function professionals_page(): void
    {
        if (!current_user_can('manage_options')) return;
        $services = SvDt_DB::services(false);
        $this->header('Profissionais', 'Defina quem atende e quais serviços cada pessoa realiza.');
        echo '<div class="svdt-columns"><div class="svdt-panel"><h2>Novo profissional</h2>';
        $this->professional_form(null, $services);
        echo '</div><div class="svdt-panel"><h2>Equipe</h2>';
        foreach (SvDt_DB::professionals(false) as $professional) {
            $this->professional_form($professional, $services);
        }
        echo '</div></div></div>';
    }

    private function professional_form(?object $professional, array $services): void
    {
        global $wpdb;
        $id = $professional ? (int) $professional->id : 0;
        $links = array();
        if ($id) {
            $table = SvDt_DB::table('professional_services');
            $links = array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT service_id FROM $table WHERE professional_id=%d", $id)));
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="svdt-professional-form"><input type="hidden" name="action" value="svdt_save_professional"><input type="hidden" name="id" value="' . esc_attr($id) . '">';
        wp_nonce_field('svdt_save_professional');
        echo '<label>Nome<input name="name" maxlength="150" required value="' . esc_attr($professional->name ?? '') . '"></label><label>Descrição curta<input name="description" maxlength="255" value="' . esc_attr($professional->description ?? '') . '"></label><div class="svdt-professional-meta"><label>Ordem<input type="number" name="sort_order" value="' . esc_attr($professional->sort_order ?? 0) . '"></label><label class="svdt-check"><input type="checkbox" name="active" value="1" ' . checked($professional->active ?? 1, 1, false) . '> Ativo para reservas</label></div>';
        echo '<fieldset><legend>Serviços habilitados</legend><div class="svdt-service-checks">';
        foreach ($services as $service) {
            echo '<label><input type="checkbox" name="services[]" value="' . esc_attr($service->id) . '" ' . checked(in_array((int) $service->id, $links, true), true, false) . '> ' . esc_html($service->name) . '</label>';
        }
        echo '</div></fieldset><div class="svdt-professional-actions"><button class="button ' . ($id ? '' : 'button-primary') . '">' . ($id ? 'Salvar profissional' : 'Adicionar profissional') . '</button>';
        if ($id) echo '<a href="' . esc_url(add_query_arg(array('page' => 'svdt-schedule', 'professional_id' => $id), admin_url('admin.php'))) . '">Configurar agenda</a>';
        echo '</div></form>';
    }

    public function save_professional(): void
    {
        $this->guard('save_professional');
        global $wpdb;
        $id = absint($_POST['id'] ?? 0);
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $description = sanitize_text_field(wp_unslash($_POST['description'] ?? ''));
        $order = (int) ($_POST['sort_order'] ?? 0);
        $selected = array_values(array_unique(array_filter(array_map('absint', (array) ($_POST['services'] ?? array())))));
        if (!$name || strlen($name) > 150 || strlen($description) > 255) $this->redirect('svdt-professionals', 'Dados do profissional inválidos.', false);
        foreach ($selected as $service_id) {
            if (!SvDt_DB::service($service_id, false)) $this->redirect('svdt-professionals', 'Serviço inválido.', false);
        }
        $table = SvDt_DB::table('professionals');
        $links = SvDt_DB::table('professional_services');
        if ($wpdb->query('START TRANSACTION') === false) $this->redirect('svdt-professionals', 'Não foi possível iniciar a gravação.', false);
        $data = array('name' => $name, 'description' => $description, 'sort_order' => $order, 'active' => isset($_POST['active']) ? 1 : 0);
        if ($id) {
            $result = $wpdb->update($table, $data, array('id' => $id));
            if ($result === 0 && !SvDt_DB::professional($id, false)) $result = false;
        } else {
            $result = $wpdb->insert($table, $data + array('created_at' => current_time('mysql')));
            $id = (int) $wpdb->insert_id;
        }
        if ($result !== false) $result = $wpdb->delete($links, array('professional_id' => $id));
        if ($result !== false) {
            foreach ($selected as $service_id) {
                if ($wpdb->insert($links, array('professional_id' => $id, 'service_id' => $service_id)) === false) {
                    $result = false;
                    break;
                }
            }
        }
        if ($result === false || $wpdb->query('COMMIT') === false) {
            $wpdb->query('ROLLBACK');
            $this->redirect('svdt-professionals', 'Não foi possível salvar o profissional.', false);
        }
        $this->redirect('svdt-professionals', 'Profissional salvo.');
    }

    public function schedule_page(): void
    {
        if (!current_user_can('manage_options')) return;
        global $wpdb;
        $team = SvDt_DB::professionals(false);
        $professional_id = isset($_GET['professional_id']) ? absint($_GET['professional_id']) : (int) get_option('svdt_default_professional_id');
        if (!SvDt_DB::professional($professional_id, false)) $professional_id = $team ? (int) $team[0]->id : 0;
        $hours_table = SvDt_DB::table('professional_hours');
        $blocks_table = SvDt_DB::table('professional_blocked_dates');
        $hours = $professional_id ? $wpdb->get_results($wpdb->prepare("SELECT * FROM $hours_table WHERE professional_id=%d ORDER BY weekday,start_time", $professional_id)) : array();
        $individual_blocks = $professional_id ? $wpdb->get_results($wpdb->prepare("SELECT * FROM $blocks_table WHERE professional_id=%d ORDER BY blocked_date DESC LIMIT 100", $professional_id)) : array();
        $global_blocks = $wpdb->get_results('SELECT * FROM ' . SvDt_DB::table('blocked_dates') . ' ORDER BY blocked_date DESC LIMIT 100');
        $this->header('Disponibilidade', 'Configure o expediente e as folgas de cada profissional.');
        echo '<form class="svdt-professional-switch" method="get"><input type="hidden" name="page" value="svdt-schedule"><label>Agenda de <select name="professional_id">';
        foreach ($team as $person) echo '<option value="' . esc_attr($person->id) . '" ' . selected($professional_id, (int) $person->id, false) . '>' . esc_html($person->name) . '</option>';
        echo '</select></label><button class="button">Visualizar</button></form>';
        echo '<div class="svdt-columns"><div class="svdt-panel"><h2>Expediente individual</h2><p>Adicione intervalos sem sobreposição. Pausas ficam entre os intervalos.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="svdt-form"><input type="hidden" name="action" value="svdt_save_hours"><input type="hidden" name="professional_id" value="' . esc_attr($professional_id) . '">';
        wp_nonce_field('svdt_save_hours');
        echo '<label>Dia da semana<select name="weekday">';
        foreach (array('Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado') as $i => $day) echo '<option value="' . $i . '">' . esc_html($day) . '</option>';
        echo '</select></label><div class="svdt-form-row"><label>Início<input type="time" name="start" required></label><label>Fim<input type="time" name="end" required></label></div><button class="button button-primary">Adicionar intervalo</button></form><div class="svdt-list">';
        foreach ($hours as $row) {
            echo '<div><span>' . esc_html(array('Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb')[(int) $row->weekday]) . ' · ' . esc_html(substr($row->start_time, 0, 5)) . '–' . esc_html(substr($row->end_time, 0, 5)) . '</span><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="svdt_save_hours"><input type="hidden" name="professional_id" value="' . esc_attr($professional_id) . '"><input type="hidden" name="delete_id" value="' . esc_attr($row->id) . '">';
            wp_nonce_field('svdt_save_hours');
            echo '<button class="button button-small">Remover</button></form></div>';
        }
        echo '</div></div><div class="svdt-panel"><h2>Folgas individuais</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="svdt-form"><input type="hidden" name="action" value="svdt_add_professional_block"><input type="hidden" name="professional_id" value="' . esc_attr($professional_id) . '">';
        wp_nonce_field('svdt_add_professional_block');
        echo '<label>Data<input type="date" name="date" required></label><label>Motivo opcional<input name="reason" maxlength="255"></label><button class="button button-primary">Bloquear para este profissional</button></form><div class="svdt-list">';
        foreach ($individual_blocks as $row) {
            echo '<div><span>' . esc_html($row->blocked_date) . ' ' . esc_html($row->reason) . '</span><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="svdt_delete_professional_block"><input type="hidden" name="professional_id" value="' . esc_attr($professional_id) . '"><input type="hidden" name="id" value="' . esc_attr($row->id) . '">';
            wp_nonce_field('svdt_delete_professional_block');
            echo '<button class="button button-small">Liberar</button></form></div>';
        }
        echo '</div></div></div><div class="svdt-panel"><h2>Fechamento do estabelecimento</h2><p>Esta data bloqueia todas as agendas.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="svdt-form svdt-global-block-form"><input type="hidden" name="action" value="svdt_add_block">';
        wp_nonce_field('svdt_add_block');
        echo '<label>Data<input type="date" name="date" required></label><label>Motivo opcional<input name="reason" maxlength="255"></label><button class="button button-primary">Bloquear data</button></form><div class="svdt-list">';
        foreach ($global_blocks as $row) {
            echo '<div><span>' . esc_html($row->blocked_date) . ' ' . esc_html($row->reason) . '</span><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="svdt_delete_block"><input type="hidden" name="id" value="' . esc_attr($row->id) . '">';
            wp_nonce_field('svdt_delete_block');
            echo '<button class="button button-small">Liberar</button></form></div>';
        }
        echo '</div></div></div>';
    }

    public function save_hours(): void
    {
        $this->guard('save_hours');
        global $wpdb;
        $professional_id = absint($_POST['professional_id'] ?? 0);
        $target = 'svdt-schedule&professional_id=' . $professional_id;
        if (!SvDt_DB::professional($professional_id, false)) $this->redirect('svdt-schedule', 'Profissional inválido.', false);
        $table = SvDt_DB::table('professional_hours');
        $delete_id = absint($_POST['delete_id'] ?? 0);
        if (!$delete_id) {
            $day = absint($_POST['weekday'] ?? 9);
            $start = sanitize_text_field(wp_unslash($_POST['start'] ?? ''));
            $end = sanitize_text_field(wp_unslash($_POST['end'] ?? ''));
            if ($day > 6 || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $start) || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $end) || $start >= $end) {
                $this->redirect($target, 'Intervalo inválido.', false);
            }
        }
        $lock_name = SvDt_DB::calendar_lock_name($professional_id);
        if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock_name)) !== '1') {
            $this->redirect($target, 'Agenda ocupada. Tente novamente.', false);
        }
        $overlap = false;
        try {
            if ($delete_id) {
                $result = $wpdb->delete($table, array('id' => $delete_id, 'professional_id' => $professional_id));
            } else {
                $overlap = (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE professional_id=%d AND weekday=%d AND active=1 AND start_time < %s AND end_time > %s LIMIT 1", $professional_id, $day, $end . ':00', $start . ':00'));
                $result = $overlap ? 0 : $wpdb->insert($table, array('professional_id' => $professional_id, 'weekday' => $day, 'start_time' => $start . ':00', 'end_time' => $end . ':00', 'active' => 1));
            }
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
        if ($overlap) $this->redirect($target, 'Este intervalo se sobrepõe ao expediente existente.', false);
        if ($result === false || $result === 0) $this->redirect($target, 'Não foi possível atualizar o expediente.', false);
        $this->redirect($target, 'Expediente atualizado.');
    }

    public function add_block(): void
    {
        $this->guard('add_block');
        global $wpdb;
        $date = sanitize_text_field(wp_unslash($_POST['date'] ?? ''));
        if (!SvDt_Schedule::parse_date($date)) $this->redirect('svdt-schedule', 'Data inválida.', false);
        $result = $wpdb->replace(SvDt_DB::table('blocked_dates'), array('blocked_date' => $date, 'reason' => sanitize_text_field(wp_unslash($_POST['reason'] ?? ''))));
        if ($result === false || $result === 0) $this->redirect('svdt-schedule', 'Não foi possível bloquear a data.', false);
        $this->redirect('svdt-schedule', 'Data bloqueada.');
    }

    public function add_professional_block(): void
    {
        $this->guard('add_professional_block');
        global $wpdb;
        $professional_id = absint($_POST['professional_id'] ?? 0);
        $target = 'svdt-schedule&professional_id=' . $professional_id;
        $date = sanitize_text_field(wp_unslash($_POST['date'] ?? ''));
        if (!SvDt_DB::professional($professional_id, false) || !SvDt_Schedule::parse_date($date)) $this->redirect($target, 'Profissional ou data inválidos.', false);
        $result = $wpdb->replace(SvDt_DB::table('professional_blocked_dates'), array('professional_id' => $professional_id, 'blocked_date' => $date, 'reason' => sanitize_text_field(wp_unslash($_POST['reason'] ?? ''))));
        if (!$result) $this->redirect($target, 'Não foi possível bloquear a data.', false);
        $this->redirect($target, 'Folga registrada.');
    }

    public function delete_professional_block(): void
    {
        $this->guard('delete_professional_block');
        global $wpdb;
        $professional_id = absint($_POST['professional_id'] ?? 0);
        $target = 'svdt-schedule&professional_id=' . $professional_id;
        $result = $wpdb->delete(SvDt_DB::table('professional_blocked_dates'), array('id' => absint($_POST['id'] ?? 0), 'professional_id' => $professional_id));
        if (!$result) $this->redirect($target, 'Não foi possível liberar a data.', false);
        $this->redirect($target, 'Folga removida.');
    }

    public function delete_block(): void
    {
        $this->guard('delete_block');
        global $wpdb;
        $result = $wpdb->delete(SvDt_DB::table('blocked_dates'), array('id' => absint($_POST['id'] ?? 0)));
        if ($result === false || $result === 0) $this->redirect('svdt-schedule', 'Não foi possível liberar a data.', false);
        $this->redirect('svdt-schedule', 'Data liberada.');
    }
}
