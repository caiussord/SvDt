<?php
defined('ABSPATH') || exit;

final class SvDt_REST
{
    public function register(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route('svdt/v1', '/services', array(
                'methods' => 'GET', 'callback' => array($this, 'services'), 'permission_callback' => '__return_true',
            ));
            register_rest_route('svdt/v1', '/professionals', array(
                'methods' => 'GET', 'callback' => array($this, 'professionals'), 'permission_callback' => '__return_true',
            ));
            register_rest_route('svdt/v1', '/availability', array(
                'methods' => 'GET', 'callback' => array($this, 'availability'), 'permission_callback' => '__return_true',
            ));
            register_rest_route('svdt/v1', '/bookings', array(
                'methods' => 'POST', 'callback' => array($this, 'create'), 'permission_callback' => '__return_true',
            ));
            register_rest_route('svdt/v1', '/admin/bookings', array(
                'methods' => 'GET', 'callback' => array($this, 'admin_bookings'), 'permission_callback' => array($this, 'admin_permission'),
            ));
            register_rest_route('svdt/v1', '/admin/professionals', array(
                'methods' => 'GET', 'callback' => array($this, 'admin_professionals'), 'permission_callback' => array($this, 'admin_permission'),
            ));
            register_rest_route('svdt/v1', '/admin/bookings/(?P<id>\d+)', array(
                'methods' => 'PATCH', 'callback' => array($this, 'update_booking'), 'permission_callback' => array($this, 'admin_permission'),
            ));
        });
    }

    public function services(): WP_REST_Response
    {
        $items = array_map(static function ($row) {
            return array(
                'id' => (int) $row->id,
                'name' => $row->name,
                'description' => $row->description,
                'duration_minutes' => (int) $row->duration_minutes,
            );
        }, array_values(array_filter(SvDt_DB::services(), static fn($service) => (bool) SvDt_DB::professionals(true, (int) $service->id))));
        return rest_ensure_response($items);
    }

    public function availability(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $slots = SvDt_Schedule::slots(absint($request['service_id']), sanitize_text_field((string) $request['date']), absint($request['professional_id']));
        return is_wp_error($slots) ? $slots : rest_ensure_response(array('slots' => $slots));
    }

    public function professionals(WP_REST_Request $request): WP_REST_Response
    {
        $service_id = absint($request['service_id']);
        $items = array_map(static fn($row) => array('id' => (int) $row->id, 'name' => $row->name, 'description' => $row->description), SvDt_DB::professionals(true, $service_id));
        return rest_ensure_response($items);
    }

    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if ($request->get_param('company_website')) {
            return new WP_Error('invalid_booking', 'Não foi possível enviar.', array('status' => 400));
        }
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = 'svdt_rate_' . md5($ip);
        $attempts = (int) get_transient($key);
        if ($attempts >= 8) {
            return new WP_Error('rate_limited', 'Aguarde alguns minutos antes de tentar novamente.', array('status' => 429));
        }
        set_transient($key, $attempts + 1, 10 * MINUTE_IN_SECONDS);
        $result = SvDt_Bookings::create($request->get_params());
        if (is_wp_error($result)) {
            return $result;
        }
        return new WP_REST_Response($result, 201);
    }

    public function admin_permission(): bool
    {
        return current_user_can('manage_options');
    }

    public function admin_professionals(): WP_REST_Response
    {
        global $wpdb;
        $links = SvDt_DB::table('professional_services');
        $items = array_map(static function ($row) use ($wpdb, $links) {
            return array('id' => (int) $row->id, 'name' => $row->name, 'description' => $row->description, 'active' => (bool) $row->active, 'sort_order' => (int) $row->sort_order,
                'service_ids' => array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT service_id FROM $links WHERE professional_id=%d", $row->id))));
        }, SvDt_DB::professionals(false));
        return rest_ensure_response($items);
    }

    public function admin_bookings(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $bookings = SvDt_DB::table('bookings');
        $services = SvDt_DB::table('services');
        $date = sanitize_text_field((string) ($request['date'] ?? ''));
        $status = sanitize_text_field((string) ($request['status'] ?? ''));
        $professional_id = absint($request['professional_id']);
        $where = array('1=1');
        $args = array();
        if (SvDt_Schedule::parse_date($date)) {
            $where[] = 'DATE(b.starts_at) = %s';
            $args[] = $date;
        }
        if (in_array($status, array('pending', 'confirmed', 'cancelled', 'completed'), true)) {
            $where[] = 'b.status = %s';
            $args[] = $status;
        }
        if ($professional_id) {
            $where[] = 'b.professional_id = %d';
            $args[] = $professional_id;
        }
        $sql = "SELECT b.*, s.name AS service_name FROM $bookings b LEFT JOIN $services s ON s.id = b.service_id WHERE " . implode(' AND ', $where) . ' ORDER BY b.starts_at DESC LIMIT 200';
        return rest_ensure_response($wpdb->get_results($args ? $wpdb->prepare($sql, $args) : $sql));
    }

    public function update_booking(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;
        $status = sanitize_key((string) $request['status']);
        if (!in_array($status, array('confirmed', 'cancelled', 'completed'), true)) {
            return new WP_Error('invalid_status', 'Status inválido.', array('status' => 400));
        }
        $id = absint($request['id']);
        $table = SvDt_DB::table('bookings');
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        if (!$booking) {
            return new WP_Error('not_found', 'Agendamento não encontrado.', array('status' => 404));
        }
        $transitions = array('pending' => array('confirmed', 'cancelled'), 'confirmed' => array('completed', 'cancelled'), 'cancelled' => array(), 'completed' => array());
        if ($status !== $booking->status && !in_array($status, $transitions[$booking->status] ?? array(), true)) {
            return new WP_Error('invalid_transition', 'Mudança de status não permitida.', array('status' => 409));
        }
        if ($status !== $booking->status) {
            $updated = $wpdb->update($table, array('status' => $status), array('id' => $id, 'status' => $booking->status), array('%s'), array('%d', '%s'));
            if ($updated === false) {
                return new WP_Error('update_failed', 'Não foi possível atualizar o agendamento.', array('status' => 500));
            }
            if ($updated === 0) {
                return new WP_Error('booking_changed', 'O status foi alterado por outra operação. Atualize a página e tente novamente.', array('status' => 409));
            }
        }
        return rest_ensure_response(array('id' => $id, 'status' => $status));
    }
}
