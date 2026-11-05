<?php
defined('ABSPATH') || exit;

final class AgendaPro_REST
{
    public function register(): void
    {
        add_action('rest_api_init', function () {
            foreach (array('svdt/v1', 'agendapro/v1') as $namespace) {
            register_rest_route($namespace, '/services', array(
                'methods' => 'GET', 'callback' => array($this, 'services'), 'permission_callback' => '__return_true',
            ));
            register_rest_route($namespace, '/availability', array(
                'methods' => 'GET', 'callback' => array($this, 'availability'), 'permission_callback' => '__return_true',
            ));
            register_rest_route($namespace, '/bookings', array(
                'methods' => 'POST', 'callback' => array($this, 'create'), 'permission_callback' => '__return_true',
            ));
            register_rest_route($namespace, '/admin/bookings', array(
                'methods' => 'GET', 'callback' => array($this, 'admin_bookings'), 'permission_callback' => array($this, 'admin_permission'),
            ));
            register_rest_route($namespace, '/admin/bookings/(?P<id>\d+)', array(
                'methods' => 'PATCH', 'callback' => array($this, 'update_booking'), 'permission_callback' => array($this, 'admin_permission'),
            ));
            }
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
        }, AgendaPro_DB::services());
        return rest_ensure_response($items);
    }

    public function availability(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $slots = AgendaPro_Schedule::slots(absint($request['service_id']), sanitize_text_field((string) $request['date']));
        return is_wp_error($slots) ? $slots : rest_ensure_response(array('slots' => $slots));
    }

    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if ($request->get_param('company_website')) {
            return new WP_Error('invalid_booking', 'Não foi possível enviar.', array('status' => 400));
        }
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = 'agendapro_rate_' . md5($ip);
        $attempts = (int) get_transient($key);
        if ($attempts >= 8) {
            return new WP_Error('rate_limited', 'Aguarde alguns minutos antes de tentar novamente.', array('status' => 429));
        }
        set_transient($key, $attempts + 1, 10 * MINUTE_IN_SECONDS);
        $result = AgendaPro_Bookings::create($request->get_params());
        if (is_wp_error($result)) {
            return $result;
        }
        return new WP_REST_Response($result, 201);
    }

    public function admin_permission(): bool
    {
        return current_user_can('manage_options');
    }

    public function admin_bookings(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $bookings = AgendaPro_DB::table('bookings');
        $services = AgendaPro_DB::table('services');
        $date = sanitize_text_field((string) ($request['date'] ?? ''));
        $status = sanitize_text_field((string) ($request['status'] ?? ''));
        $where = array('1=1');
        $args = array();
        if (AgendaPro_Schedule::parse_date($date)) {
            $where[] = 'DATE(b.starts_at) = %s';
            $args[] = $date;
        }
        if (in_array($status, array('pending', 'confirmed', 'cancelled', 'completed'), true)) {
            $where[] = 'b.status = %s';
            $args[] = $status;
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
        $table = AgendaPro_DB::table('bookings');
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        if (!$booking) {
            return new WP_Error('not_found', 'Agendamento não encontrado.', array('status' => 404));
        }
        $transitions = array('pending' => array('confirmed', 'cancelled'), 'confirmed' => array('completed', 'cancelled'), 'cancelled' => array(), 'completed' => array());
        if ($status !== $booking->status && !in_array($status, $transitions[$booking->status] ?? array(), true)) {
            return new WP_Error('invalid_transition', 'Mudança de status não permitida.', array('status' => 409));
        }
        if ($status !== $booking->status && $wpdb->update($table, array('status' => $status), array('id' => $id), array('%s'), array('%d')) === false) {
            return new WP_Error('update_failed', 'Não foi possível atualizar o agendamento.', array('status' => 500));
        }
        return rest_ensure_response(array('id' => $id, 'status' => $status));
    }
}
