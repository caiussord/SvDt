<?php
/**
 * Plugin Name: SvDt
 * Description: Uma experiência de agendamento com serviços, disponibilidade e gestão no WordPress.
 * Version: 0.3.2
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Text Domain: svdt
 */

defined('ABSPATH') || exit;

define('SVDT_VERSION', '0.3.2');
define('SVDT_BOOKING_WINDOW_DAYS', 90);
define('SVDT_PATH', plugin_dir_path(__FILE__));
define('SVDT_URL', plugin_dir_url(__FILE__));

require_once SVDT_PATH . 'includes/class-svdt-db.php';
require_once SVDT_PATH . 'includes/class-svdt-schedule.php';
require_once SVDT_PATH . 'includes/class-svdt-bookings.php';
require_once SVDT_PATH . 'includes/class-svdt-rest.php';
require_once SVDT_PATH . 'includes/class-svdt-admin.php';

register_activation_hook(__FILE__, array('SvDt_DB', 'activate'));
register_deactivation_hook(__FILE__, static function () {
    wp_clear_scheduled_hook('svdt_daily_cleanup');
});

add_action('svdt_daily_cleanup', array('SvDt_DB', 'cleanup'));

add_action('plugins_loaded', static function () {
    if (get_option('svdt_db_version') !== SVDT_VERSION) {
        SvDt_DB::activate();
    }
    (new SvDt_REST())->register();
    (new SvDt_Admin())->register();
    add_shortcode('svdt_booking', 'svdt_render_shortcode');
    add_filter('theme_page_templates', static function (array $templates): array {
        $templates['svdt-showcase.php'] = 'SvDt · Página de reservas';
        return $templates;
    });
    add_filter('template_include', static function (string $template): string {
        if (is_page() && get_page_template_slug(get_queried_object_id()) === 'svdt-showcase.php') {
            return SVDT_PATH . 'public/showcase-template.php';
        }
        return $template;
    });
});

function svdt_render_shortcode(): string
{
    wp_enqueue_style('svdt-booking', SVDT_URL . 'public/booking.css', array(), SVDT_VERSION);
    wp_enqueue_script('svdt-booking', SVDT_URL . 'public/booking.js', array(), SVDT_VERSION, true);
    wp_add_inline_script('svdt-booking', 'window.SvDt=' . wp_json_encode(array(
        'api' => esc_url_raw(rest_url('svdt/v1/')),
        'today' => current_datetime()->format('Y-m-d'),
        'lastDate' => current_datetime()->setTime(0, 0)->modify('+' . SVDT_BOOKING_WINDOW_DAYS . ' days')->format('Y-m-d'),
        'serviceIcon' => esc_url_raw(SVDT_URL . 'public/assets/barber-shop.svg'),
        'serviceIcons' => array(
            'Acabamento de contorno' => esc_url_raw(SVDT_URL . 'public/assets/razor-barber.svg'),
            'Barba' => esc_url_raw(add_query_arg('ver', SVDT_VERSION, SVDT_URL . 'public/assets/beard.svg')),
            'Hidratação capilar' => esc_url_raw(SVDT_URL . 'public/assets/shampoo.svg'),
            'Lavagem e finalização' => esc_url_raw(SVDT_URL . 'public/assets/shower.svg'),
            'Sobrancelha' => esc_url_raw(SVDT_URL . 'public/assets/razor-barber.svg'),
        ),
    )) . ';', 'before');
    ob_start();
    include SVDT_PATH . 'public/booking.php';
    return (string) ob_get_clean();
}
