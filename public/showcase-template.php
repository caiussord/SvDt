<?php
defined('ABSPATH') || exit;
wp_enqueue_style('agendapro-booking', AGENDAPRO_URL . 'public/booking.css', array(), AGENDAPRO_VERSION);
?><!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class('svdt-showcase-page'); ?>>
<?php wp_body_open(); ?>
<div class="svdt-site-shell">
  <header class="svdt-site-header"><a class="svdt-logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="SvDt, início"><span class="svdt-logo-mark">S<span>.</span></span><span>SvDt</span></a><span class="svdt-site-label">TEMPO BEM VIVIDO, HORÁRIO BEM ESCOLHIDO</span><a class="svdt-site-cta" href="#svdt-booking">Agendar agora <span aria-hidden="true">↗</span></a></header>
  <main><?php while (have_posts()) : the_post(); the_content(); endwhile; ?></main>
  <footer class="svdt-site-footer"><span class="svdt-footer-brand">SvDt<span>.</span></span><p>Uma forma melhor de reservar seu tempo.</p><small>© <?php echo esc_html(wp_date('Y')); ?> SvDt</small></footer>
</div>
<?php wp_footer(); ?>
</body></html>
