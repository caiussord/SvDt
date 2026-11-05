<?php
defined('ABSPATH') || exit;

final class AgendaPro_Bookings
{
    public static function create(array $data): array|WP_Error
    {
        global $wpdb;
        $service_id = absint($data['service_id'] ?? 0);
        $date = sanitize_text_field((string) ($data['date'] ?? ''));
        $time = sanitize_text_field((string) ($data['time'] ?? ''));
        $name = sanitize_text_field((string) ($data['customer_name'] ?? ''));
        $email = sanitize_email((string) ($data['customer_email'] ?? ''));
        $phone = sanitize_text_field((string) ($data['customer_phone'] ?? ''));
        $notes = sanitize_textarea_field((string) ($data['notes'] ?? ''));
        if (!$service_id || !AgendaPro_Schedule::parse_date($date) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $time)
            || !$name || strlen($name) > 150 || !is_email($email) || strlen($email) > 190
            || !preg_match('/^[0-9+() .-]{8,30}$/', $phone) || strlen($notes) > 2000) {
            return new WP_Error('invalid_booking', 'Confira os dados do agendamento.', array('status' => 400));
        }

        // One MySQL advisory lock serializes all writes to the single appointment resource.
        $lock_name = $wpdb->prefix . 'agendapro_single_calendar';
        $locked = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock_name));
        if ((string) $locked !== '1') {
            return new WP_Error('booking_busy', 'Agenda ocupada. Tente novamente.', array('status' => 503));
        }
        try {
            $service = AgendaPro_DB::service($service_id);
            if (!$service) {
                return new WP_Error('invalid_service', 'Serviço indisponível.', array('status' => 404));
            }
            $slots = AgendaPro_Schedule::slots($service_id, $date);
            if (is_wp_error($slots)) {
                return $slots;
            }
            if (!in_array($time, $slots, true)) {
                return new WP_Error('slot_unavailable', 'Este horário não está mais disponível.', array('status' => 409));
            }
            $start = AgendaPro_Schedule::parse_date($date)->setTime((int) substr($time, 0, 2), (int) substr($time, 3, 2));
            $end = $start->modify('+' . ((int) $service->duration_minutes + (int) $service->buffer_minutes) . ' minutes');
            $inserted = $wpdb->insert(AgendaPro_DB::table('bookings'), array(
                'service_id' => $service_id,
                'customer_name' => $name,
                'customer_email' => $email,
                'customer_phone' => $phone,
                'notes' => $notes,
                'starts_at' => $start->format('Y-m-d H:i:s'),
                'ends_at' => $end->format('Y-m-d H:i:s'),
                'status' => 'pending',
                'created_at' => current_time('mysql'),
            ), array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'));
            if (!$inserted) {
                return new WP_Error('booking_failed', 'Não foi possível salvar o agendamento.', array('status' => 500));
            }
            return array('reference' => 'AG-' . str_pad((string) $wpdb->insert_id, 6, '0', STR_PAD_LEFT), 'status' => 'pending');
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
    }
}
