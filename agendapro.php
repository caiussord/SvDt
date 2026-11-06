<?php
/**
 * Plugin Name: SvDt
 * Description: Uma experiência de agendamento com serviços, disponibilidade e gestão no WordPress.
 * Version: 0.2.9
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Text Domain: agendapro
 */

defined('ABSPATH') || exit;

define('AGENDAPRO_VERSION', '0.2.9');
define('AGENDAPRO_PATH', plugin_dir_path(__FILE__));
define('AGENDAPRO_URL', plugin_dir_url(__FILE__));

require_once AGENDAPRO_PATH . 'includes/class-agendapro-db.php';
require_once AGENDAPRO_PATH . 'includes/class-agendapro-schedule.php';
require_once AGENDAPRO_PATH . 'includes/class-agendapro-bookings.php';
require_once AGENDAPRO_PATH . 'includes/class-agendapro-rest.php';
require_once AGENDAPRO_PATH . 'includes/class-agendapro-admin.php';

register_activation_hook(__FILE__, array('AgendaPro_DB', 'activate'));
register_deactivation_hook(__FILE__, static function () {
    wp_clear_scheduled_hook('agendapro_daily_cleanup');
});

add_action('agendapro_daily_cleanup', array('AgendaPro_DB', 'cleanup'));

add_action('plugins_loaded', static function () {
    if (get_option('agendapro_db_version') !== AGENDAPRO_VERSION) {
        AgendaPro_DB::activate();
    }
    (new AgendaPro_REST())->register();
    (new AgendaPro_Admin())->register();
    add_shortcode('agendapro_booking', 'agendapro_render_shortcode');
    add_shortcode('svdt_booking', 'agendapro_render_shortcode');
    add_filter('theme_page_templates', static function (array $templates): array {
        $templates['svdt-showcase.php'] = 'SvDt · Página de reservas';
        return $templates;
    });
    add_filter('template_include', static function (string $template): string {
        if (is_page() && get_page_template_slug(get_queried_object_id()) === 'svdt-showcase.php') {
            return AGENDAPRO_PATH . 'public/showcase-template.php';
        }
        return $template;
    });
});

function agendapro_render_shortcode(): string
{
    wp_enqueue_style('agendapro-booking', AGENDAPRO_URL . 'public/booking.css', array(), AGENDAPRO_VERSION);
    wp_enqueue_script('agendapro-booking', AGENDAPRO_URL . 'public/booking.js', array(), AGENDAPRO_VERSION, true);
    wp_add_inline_script('agendapro-booking', 'window.SvDt=' . wp_json_encode(array(
        'api' => esc_url_raw(rest_url('svdt/v1/')),
        'today' => current_datetime()->format('Y-m-d'),
        'serviceIcon' => esc_url_raw(AGENDAPRO_URL . 'public/assets/barber-shop.svg'),
        'serviceIcons' => array(
            'Acabamento de contorno' => esc_url_raw(AGENDAPRO_URL . 'public/assets/razor-barber.svg'),
            'Barba' => esc_url_raw(add_query_arg('ver', AGENDAPRO_VERSION, AGENDAPRO_URL . 'public/assets/beard.svg')),
            'Hidratação capilar' => esc_url_raw(AGENDAPRO_URL . 'public/assets/shampoo.svg'),
            'Lavagem e finalização' => esc_url_raw(AGENDAPRO_URL . 'public/assets/shower.svg'),
            'Sobrancelha' => esc_url_raw(AGENDAPRO_URL . 'public/assets/razor-barber.svg'),
        ),
    )) . ';', 'before');
    ob_start();
    include AGENDAPRO_PATH . 'public/booking.php';
    return (string) ob_get_clean();
}
