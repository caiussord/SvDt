(() => {
  document.querySelectorAll('[data-svdt]').forEach(root => {
    const $ = selector => root.querySelector(selector);
    const $$ = selector => [...root.querySelectorAll(selector)];
    const config = window.SvDt;
    if (!config?.api) return;
    const today = config.today;
    const lastDate = config.lastDate;
    const todayDate = new Date(`${today}T12:00:00`);
    const lastDay = new Date(`${lastDate}T12:00:00`);
    const state = { step: 1, service: null, date: null, time: null, month: new Date(todayDate.getFullYear(), todayDate.getMonth(), 1) };
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const ymd = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    const longDate = value => new Date(`${value}T12:00:00`).toLocaleDateString('pt-BR', { day: 'numeric', month: 'long', year: 'numeric' });

    async function request(path, options) {
      const response = await fetch(config.api + path, options);
      const data = await response.json();
      if (!response.ok) {
        const error = new Error(data.message || 'Não foi possível concluir. Tente novamente.');
        error.code = data.code;
        throw error;
      }
      return data;
    }

    function updateSummary() {
      $('[data-summary-service]').textContent = state.service?.name || '—';
      $('[data-summary-date]').textContent = state.date ? longDate(state.date) : '—';
      $('[data-summary-time]').textContent = state.time || '—';
      $('[data-selection-line]').textContent = state.service && state.date && state.time
        ? `${state.service.name} · ${longDate(state.date)} às ${state.time}` : '';
      $$('[data-step-go]').forEach(button => {
        const target = Number(button.dataset.stepGo);
        button.disabled = target === 2 ? !state.service : target === 3 ? !state.time : false;
        button.toggleAttribute('aria-current', target === state.step);
        button.classList.toggle('is-complete', target < state.step);
      });
      const title = $('.svdt-rail-top strong');
      const desc = $('.svdt-rail-top p');
      title.textContent = state.step === 1 ? 'Vamos começar?' : state.step === 2 ? 'Boa escolha.' : 'Último detalhe.';
      desc.hidden = state.step === 1;
      desc.textContent = state.step === 1 ? '' : state.step === 2 ? 'Agora encontre o melhor momento.' : 'Revise o horário e envie sua solicitação.';
    }

    function setStep(step, focus = true) {
      if (step === 2 && !state.service) return;
      if (step === 3 && !state.time) return;
      state.step = step;
      $$('[data-panel]').forEach(panel => { panel.hidden = Number(panel.dataset.panel) !== step; });
      $('[data-step-count]').textContent = `ETAPA 0${step} / 03`;
      $('[data-meter]').style.width = `${(step / 3) * 100}%`;
      updateSummary();
      if (focus) {
        const heading = $(`[data-panel="${step}"] h2`);
        heading.focus({ preventScroll: true });
        $('[data-stage]').scrollIntoView({ behavior: reduceMotion ? 'instant' : 'smooth', block: 'start' });
      }
    }

    async function loadServices() {
      const box = $('[data-services]');
      try {
        const services = await request('services');
        box.replaceChildren();
        if (!services.length) { box.innerHTML = '<p class="svdt-empty">Nenhum serviço disponível no momento. Volte em breve.</p>'; return; }
        services.forEach((service, index) => {
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'svdt-service-card';
          button.innerHTML = '<span class="svdt-service-top"><span class="svdt-service-icon" aria-hidden="true"><img alt="" width="26" height="26"></span><span class="svdt-service-number"></span></span><span class="svdt-service-name"></span><span class="svdt-service-description"></span><span class="svdt-service-bottom"><span class="svdt-service-duration"></span><span class="svdt-service-action">Escolher</span></span>';
          button.querySelector('.svdt-service-icon img').src = config.serviceIcons?.[service.name] || config.serviceIcon;
          button.querySelector('.svdt-service-number').textContent = `${String(index + 1).padStart(2, '0')} / SERVIÇO`;
          button.querySelector('.svdt-service-name').textContent = service.name;
          button.querySelector('.svdt-service-description').textContent = service.description || 'Atendimento com hora marcada.';
          button.querySelector('.svdt-service-duration').textContent = `${service.duration_minutes} min`;
          button.addEventListener('click', () => {
            state.service = service;
            state.time = null;
            updateSummary();
            setStep(2);
            loadTimes();
          });
          box.append(button);
        });
      } catch (error) {
        box.innerHTML = '';
        const message = document.createElement('p'); message.className = 'svdt-empty'; message.textContent = error.message; box.append(message);
      }
    }

    function renderCalendar() {
      const month = state.month;
      const label = month.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
      $('[data-month]').textContent = label.charAt(0).toUpperCase() + label.slice(1);
      const grid = $('[data-calendar]'); grid.replaceChildren();
      ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'].forEach(day => {
        const cell = document.createElement('span'); cell.className = 'svdt-weekday'; cell.textContent = day; grid.append(cell);
      });
      const first = new Date(month.getFullYear(), month.getMonth(), 1);
      for (let i = 0; i < first.getDay(); i++) grid.append(document.createElement('span'));
      const total = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();
      for (let day = 1; day <= total; day++) {
        const date = ymd(new Date(month.getFullYear(), month.getMonth(), day));
        const button = document.createElement('button'); button.type = 'button'; button.textContent = day;
        button.disabled = date < today || date > lastDate;
        button.classList.toggle('is-selected', date === state.date);
        button.setAttribute('aria-label', longDate(date));
        button.setAttribute('aria-pressed', String(date === state.date));
        button.addEventListener('click', () => {
          state.date = date; state.time = null; renderCalendar(); updateSummary(); loadTimes();
          $('[data-time-alert]').hidden = true;
        });
        grid.append(button);
      }
      $('[data-prev]').disabled = month.getFullYear() === todayDate.getFullYear() && month.getMonth() === todayDate.getMonth();
      $('[data-next]').disabled = month.getFullYear() === lastDay.getFullYear() && month.getMonth() === lastDay.getMonth();
    }

    async function loadTimes() {
      const box = $('[data-times]');
      $('[data-date-label]').textContent = state.date ? longDate(state.date) : 'Escolha uma data';
      box.replaceChildren();
      if (!state.service || !state.date) {
        box.innerHTML = '<div class="svdt-time-empty">Os horários aparecem aqui depois de escolher um dia.</div>';
        return;
      }
      box.innerHTML = '<div class="svdt-time-loading" role="status">Consultando horários disponíveis…</div>';
      const serviceId = state.service.id, date = state.date;
      try {
        const data = await request(`availability?service_id=${encodeURIComponent(serviceId)}&date=${encodeURIComponent(date)}`);
        if (serviceId !== state.service?.id || date !== state.date) return;
        box.replaceChildren();
        if (!data.slots.length) { box.innerHTML = '<div class="svdt-time-empty">Nenhum horário livre neste dia. Experimente outra data.</div>'; return; }
        data.slots.forEach(time => {
          const button = document.createElement('button'); button.type = 'button'; button.className = 'svdt-time-button'; button.textContent = time;
          button.setAttribute('aria-pressed', String(time === state.time));
          button.classList.toggle('is-selected', time === state.time);
          button.addEventListener('click', () => {
            state.time = time; updateSummary();
            $('[data-time-alert]').hidden = true;
            box.querySelectorAll('button').forEach(item => {
              item.classList.toggle('is-selected', item === button);
              item.setAttribute('aria-pressed', String(item === button));
            });
            window.setTimeout(() => setStep(3), reduceMotion ? 0 : 180);
          });
          box.append(button);
        });
      } catch (error) {
        box.replaceChildren();
        const message = document.createElement('div'); message.className = 'svdt-time-empty'; message.textContent = error.message; box.append(message);
      }
    }

    $$('[data-step-go]').forEach(button => button.addEventListener('click', () => setStep(Number(button.dataset.stepGo))));
    $$('[data-back]').forEach(button => button.addEventListener('click', () => setStep(Number(button.dataset.back))));
    $('[data-prev]').addEventListener('click', () => { state.month = new Date(state.month.getFullYear(), state.month.getMonth() - 1, 1); renderCalendar(); });
    $('[data-next]').addEventListener('click', () => { state.month = new Date(state.month.getFullYear(), state.month.getMonth() + 1, 1); renderCalendar(); });
    $('[data-booking-form]').addEventListener('submit', async event => {
      event.preventDefault();
      const form = event.currentTarget;
      if (!form.reportValidity() || !state.service || !state.date || !state.time) return;
      const submit = $('[data-submit]'); submit.disabled = true;
      $('[data-feedback]').textContent = 'Enviando sua solicitação…';
      const payload = Object.fromEntries(new FormData(form).entries());
      Object.assign(payload, { service_id: state.service.id, date: state.date, time: state.time });
      try {
        const data = await request('bookings', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        $('[data-reference]').textContent = data.reference;
        $$('[data-panel]').forEach(panel => { panel.hidden = true; });
        $('[data-success]').hidden = false;
        root.classList.add('is-complete');
        $('[data-success]').scrollIntoView({ behavior: reduceMotion ? 'instant' : 'smooth', block: 'center' });
      } catch (error) {
        $('[data-feedback]').textContent = error.message;
        submit.disabled = false;
        if (error.code === 'slot_unavailable') {
          state.time = null; setStep(2); $('[data-time-alert]').textContent = 'Este horário acabou de ser reservado. Escolha outro.'; $('[data-time-alert]').hidden = false; loadTimes();
        }
      }
    });
    $('[data-new]').addEventListener('click', () => window.location.reload());
    renderCalendar(); updateSummary(); loadServices();
  });
})();
