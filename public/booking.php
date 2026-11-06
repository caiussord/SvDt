<?php defined('ABSPATH') || exit; ?>
<div class="svdt-app" data-svdt id="svdt-flow">
  <header class="svdt-hero">
    <div class="svdt-hero-copy">
      <span class="svdt-overline">SAVE THE DATE · RESERVAS ONLINE</span>
      <h1>Seu tempo,<br><em>bem marcado.</em></h1>
      <p>Escolha o cuidado que combina com você. O resto acontece em poucos passos, no seu ritmo.</p>
      <a href="#svdt-booking" class="svdt-hero-link">Encontrar meu horário</a>
    </div>
    <div class="svdt-hero-art" aria-hidden="true">
      <div class="svdt-orbit svdt-orbit-one"></div><div class="svdt-orbit svdt-orbit-two"></div>
      <div class="svdt-art-center"><img src="<?php echo esc_url(AGENDAPRO_URL . 'public/assets/logo-svdt.svg'); ?>" alt="" width="186" height="175"></div>
    </div>
  </header>

  <div class="svdt-workspace" id="svdt-booking">
    <aside class="svdt-rail" aria-label="Progresso e resumo da reserva">
      <div class="svdt-rail-top"><span class="svdt-rail-caption">SUA RESERVA</span><strong>Vamos começar?</strong><p>Três escolhas rápidas para cuidar do seu tempo.</p></div>
      <ol class="svdt-progress">
        <li><button type="button" data-step-go="1" aria-current="step"><span class="svdt-progress-num">01</span><span><b>Serviço</b><small>O que você precisa</small></span></button></li>
        <li><button type="button" data-step-go="2" disabled><span class="svdt-progress-num">02</span><span><b>Data e horário</b><small>Quando fica melhor</small></span></button></li>
        <li><button type="button" data-step-go="3" disabled><span class="svdt-progress-num">03</span><span><b>Seus dados</b><small>Último detalhe</small></span></button></li>
      </ol>
      <div class="svdt-rail-summary"><span>RESUMO</span><dl><div><dt>Serviço</dt><dd data-summary-service>—</dd></div><div><dt>Data</dt><dd data-summary-date>—</dd></div><div><dt>Horário</dt><dd data-summary-time>—</dd></div></dl><p>A solicitação passa por confirmação.</p></div>
    </aside>

    <div class="svdt-stage" data-stage>
      <div class="svdt-stage-meta"><span data-step-count>ETAPA 01 / 03</span><span class="svdt-stage-meter" aria-hidden="true"><i data-meter></i></span><span>SV·DT</span></div>

      <section class="svdt-panel" data-panel="1" aria-labelledby="svdt-services-title">
        <div class="svdt-panel-heading"><span class="svdt-section-index">01 — O INÍCIO</span><h2 id="svdt-services-title" tabindex="-1">O que vamos reservar?</h2><p>Escolha um serviço para abrir a agenda com horários feitos para ele.</p></div>
        <div class="svdt-services" data-services aria-live="polite"><div class="svdt-loading"><span></span><span></span></div></div>
        <div class="svdt-panel-foot"><span>01 de 03</span><span>Selecione um serviço para continuar</span></div>
      </section>

      <section class="svdt-panel" data-panel="2" aria-labelledby="svdt-date-title" hidden>
        <div class="svdt-panel-heading"><button type="button" class="svdt-back" data-back="1">Voltar aos serviços</button><span class="svdt-section-index">02 — O MOMENTO</span><h2 id="svdt-date-title" tabindex="-1">Encontre seu momento.</h2><p>Escolha um dia e depois toque no horário que funciona para você.</p></div>
        <p class="svdt-time-alert" data-time-alert hidden role="alert"></p><div class="svdt-picker"><div class="svdt-calendar"><div class="svdt-month"><button type="button" data-prev aria-label="Mês anterior"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m14.5 5-7 7 7 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button><strong data-month></strong><button type="button" data-next aria-label="Próximo mês"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m9.5 5 7 7-7 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button></div><div class="svdt-calendar-grid" data-calendar></div></div><div class="svdt-times"><div class="svdt-times-head"><span>HORÁRIOS LIVRES</span><strong data-date-label>Escolha uma data</strong></div><div class="svdt-time-grid" data-times aria-live="polite"><div class="svdt-time-empty">Os horários aparecem aqui depois de escolher um dia.</div></div></div></div>
        <div class="svdt-panel-foot"><span>02 de 03</span><span>Escolha um horário para continuar</span></div>
      </section>

      <section class="svdt-panel" data-panel="3" aria-labelledby="svdt-contact-title" hidden>
        <div class="svdt-panel-heading"><button type="button" class="svdt-back" data-back="2">Alterar horário</button><span class="svdt-section-index">03 — O ÚLTIMO PASSO</span><h2 id="svdt-contact-title" tabindex="-1">Quase tudo pronto.</h2><p>Deixe seus dados para enviarmos sua solicitação ao estabelecimento.</p></div>
        <div class="svdt-selection-strip"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="m5 12 4.5 4.5L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></span><strong data-selection-line></strong><button type="button" data-back="2">Editar</button></div>
        <form data-booking-form class="svdt-contact"><div class="svdt-field-row"><label>Nome completo<input name="customer_name" autocomplete="name" maxlength="150" placeholder="Como podemos chamar você?" required></label><label>E-mail<input type="email" name="customer_email" autocomplete="email" maxlength="190" placeholder="voce@exemplo.com" required></label></div><label>Telefone<input type="tel" name="customer_phone" autocomplete="tel" maxlength="30" placeholder="(11) 99999-9999" required></label><label>Observações <small>opcional</small><textarea name="notes" maxlength="2000" rows="3" placeholder="Alguma preferência ou detalhe importante?"></textarea></label><div class="svdt-trap" aria-hidden="true"><label>Site da empresa<input name="company_website" tabindex="-1" autocomplete="off"></label></div><p class="svdt-privacy">Usaremos seus dados apenas para administrar esta solicitação.</p><div class="svdt-feedback" data-feedback role="status" aria-live="polite"></div><button type="submit" class="svdt-submit" data-submit>Enviar solicitação</button></form>
      </section>

      <section class="svdt-success" data-success hidden role="status" aria-live="polite"><div class="svdt-success-icon" aria-hidden="true">✓</div><span class="svdt-section-index">PEDIDO RECEBIDO</span><h2>Sua hora foi marcada</h2><p>Recebemos sua solicitação. Guarde a referência abaixo para conversar com o estabelecimento.</p><div class="svdt-reference"><span>REFERÊNCIA</span><strong data-reference></strong></div><p class="svdt-success-note">O horário fica reservado provisoriamente até a confirmação.</p><button type="button" data-new>Fazer outra reserva</button></section>
    </div>
  </div>
</div>
