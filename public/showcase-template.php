<?php
defined('ABSPATH') || exit;
wp_enqueue_style('agendapro-booking', AGENDAPRO_URL . 'public/booking.css', array(), AGENDAPRO_VERSION);
?><!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="icon" type="image/svg+xml" href="<?php echo esc_url(AGENDAPRO_URL . 'public/assets/logo-svdt.svg'); ?>"><?php wp_head(); ?></head>
<body <?php body_class('svdt-showcase-page'); ?>>
<?php wp_body_open(); ?>
<div class="svdt-site-shell">
  <header class="svdt-site-header"><a class="svdt-logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="SvDt, início"><img src="<?php echo esc_url(AGENDAPRO_URL . 'public/assets/logo-svdt.svg'); ?>" alt="" width="38" height="36"><span>SvDt</span></a><span class="svdt-site-label">SAVE THE DATE · SEU TEMPO BEM MARCADO</span><a class="svdt-site-cta" href="#svdt-booking">Agendar agora</a></header>
  <main><?php while (have_posts()) : the_post(); the_content(); endwhile; ?></main>
  <footer class="svdt-site-footer"><span class="svdt-footer-brand"><img src="<?php echo esc_url(AGENDAPRO_URL . 'public/assets/logo-svdt.svg'); ?>" alt="" width="30" height="28"> SvDt</span><p>Save the Date. Uma forma melhor de reservar seu tempo.</p><small>© <?php echo esc_html(wp_date('Y')); ?> SvDt</small></footer>
</div>
<?php wp_footer(); ?>
</body></html>
