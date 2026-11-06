<?php
defined('ABSPATH') || exit;

final class SvDt_Schedule
{
    public static function parse_date(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    public static function slots(int $service_id, string $date, int $professional_id = 0): array|WP_Error
    {
        global $wpdb;
        $day = self::parse_date($date);
        if (!$day) {
            return new WP_Error('invalid_date', 'Data inválida.', array('status' => 400));
        }
        $service = SvDt_DB::service($service_id);
        if (!$service) {
            return new WP_Error('invalid_service', 'Serviço indisponível.', array('status' => 404));
        }
        $professional = SvDt_DB::resolve_professional($service_id, $professional_id);
        if (is_wp_error($professional)) return $professional;
        $professional_id = (int) $professional->id;
        $today = current_datetime()->setTime(0, 0);
        if ($day < $today) {
            return array();
        }
        if ($day > $today->modify('+' . SVDT_BOOKING_WINDOW_DAYS . ' days')) {
            return new WP_Error('date_out_of_range', sprintf('Escolha uma data nos próximos %d dias.', SVDT_BOOKING_WINDOW_DAYS), array('status' => 400));
        }
        $blocked = SvDt_DB::table('blocked_dates');
        if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $blocked WHERE blocked_date = %s", $date))) {
            return array();
        }
        $individual_blocks = SvDt_DB::table('professional_blocked_dates');
        if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $individual_blocks WHERE professional_id = %d AND blocked_date = %s", $professional_id, $date))) {
            return array();
        }
        $hours = SvDt_DB::table('professional_hours');
        $weekday = (int) $day->format('w');
        $periods = $wpdb->get_results($wpdb->prepare(
            "SELECT start_time, end_time FROM $hours WHERE professional_id = %d AND weekday = %d AND active = 1 ORDER BY start_time",
            $professional_id, $weekday
        ));
        if (!$periods) {
            return array();
        }
        $bookings = SvDt_DB::table('bookings');
        $existing = $wpdb->get_results($wpdb->prepare(
            "SELECT starts_at, ends_at FROM $bookings WHERE professional_id = %d AND starts_at < %s AND ends_at > %s AND status IN ('pending','confirmed')",
            $professional_id, $day->modify('+1 day')->format('Y-m-d H:i:s'), $day->format('Y-m-d H:i:s')
        ));
        $duration = (int) $service->duration_minutes + (int) $service->buffer_minutes;
        $step = max(5, min(120, (int) get_option('svdt_slot_minutes', 30)));
        $notice = max(0, (int) get_option('svdt_min_notice_minutes', 60));
        $earliest = current_datetime()->modify('+' . $notice . ' minutes');
        $slots = array();
        foreach ($periods as $period) {
            $start = self::at_time($day, $period->start_time);
            $end = self::at_time($day, $period->end_time);
            if (!$start || !$end || $end <= $start) {
                continue;
            }
            for ($candidate = $start; $candidate->modify('+' . $duration . ' minutes') <= $end; $candidate = $candidate->modify('+' . $step . ' minutes')) {
                if ($candidate < $earliest || $candidate->format('Y-m-d') !== $date) {
                    continue;
                }
                $candidate_end = $candidate->modify('+' . $duration . ' minutes');
                $overlap = false;
                foreach ($existing as $booking) {
                    if ($candidate->format('Y-m-d H:i:s') < $booking->ends_at && $candidate_end->format('Y-m-d H:i:s') > $booking->starts_at) {
                        $overlap = true;
                        break;
                    }
                }
                if (!$overlap) {
                    $slots[] = $candidate->format('H:i');
                }
            }
        }
        return array_values(array_unique($slots));
    }

    private static function at_time(DateTimeImmutable $day, string $time): ?DateTimeImmutable
    {
        $hour = substr($time, 0, 2);
        $minute = substr($time, 3, 2);
        if (!ctype_digit($hour . $minute) || (int) $hour > 23 || (int) $minute > 59) {
            return null;
        }
        $date_time = $day->setTime((int) $hour, (int) $minute);
        return $date_time->format('H:i') === "$hour:$minute" ? $date_time : null;
    }
}
