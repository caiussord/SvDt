(() => {
  document.querySelectorAll('[data-agendapro]').forEach(root => {
    const $ = selector => root.querySelector(selector);
    const state = { service: null, date: null, time: null, month: new Date(), services: [] };
    const api = window.AgendaPro.api;
    const today = window.AgendaPro.today;
    const ymd = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    const longDate = value => new Date(`${value}T12:00:00`).toLocaleDateString('pt-BR', { day: 'numeric', month: 'long', year: 'numeric' });
    const request = async (path, options) => {
      const response = await fetch(api + path, options);
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Não foi possível carregar os dados.');
      return data;
    };
    const updateSummary = () => {
      $('[data-summary-service]').textContent = state.service?.name || 'Selecione um serviço';
      $('[data-summary-date]').textContent = state.date ? longDate(state.date) : 'Escolha uma data';
      $('[data-summary-time]').textContent = state.time || 'Escolha um horário';
      $('[data-submit]').disabled = !(state.service && state.date && state.time);
      root.querySelectorAll('.ag-steps li').forEach((step, index) => step.classList.toggle('is-active', index === (state.time ? 2 : state.service ? 1 : 0)));
    };
    const loadServices = async () => {
      try {
        state.services = await request('services');
        const box = $('[data-services]'); box.replaceChildren();
        if (!state.services.length) { box.textContent = 'Nenhum serviço disponível no momento.'; return; }
        state.services.forEach(service => {
          const button = document.createElement('button'); button.type = 'button'; button.className = 'ag-service-card';
          button.innerHTML = '<span class="ag-service-symbol" aria-hidden="true">✦</span><span class="ag-service-copy"><strong></strong><small></small></span><span class="ag-duration"></span>';
          button.querySelector('strong').textContent = service.name;
          button.querySelector('small').textContent = service.description || 'Atendimento com hora marcada';
          button.querySelector('.ag-duration').textContent = `${service.duration_minutes} min`;
          button.addEventListener('click', () => { state.service = service; state.time = null; box.querySelectorAll('button').forEach(b => b.classList.toggle('is-selected', b === button)); updateSummary(); loadTimes(); });
          box.append(button);
        });
      } catch (error) { $('[data-services]').textContent = error.message; }
    };
    const renderCalendar = () => {
      const month = state.month;
      const monthLabel = month.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
      $('[data-month]').textContent = monthLabel.charAt(0).toUpperCase() + monthLabel.slice(1);
      const grid = $('[data-calendar]'); grid.replaceChildren();
      ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'].forEach(label => { const el = document.createElement('span'); el.className = 'ag-weekday'; el.textContent = label; grid.append(el); });
      const first = new Date(month.getFullYear(), month.getMonth(), 1);
      for (let i = 0; i < first.getDay(); i++) grid.append(document.createElement('span'));
      const days = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();
      for (let day = 1; day <= days; day++) {
        const date = ymd(new Date(month.getFullYear(), month.getMonth(), day));
        const button = document.createElement('button'); button.type = 'button'; button.textContent = day;
        button.disabled = date < today; button.classList.toggle('is-selected', date === state.date);
        button.setAttribute('aria-label', longDate(date));
        button.addEventListener('click', () => { state.date = date; state.time = null; renderCalendar(); updateSummary(); loadTimes(); });
        grid.append(button);
      }
      const now = new Date(`${today}T12:00:00`);
      $('[data-prev]').disabled = month.getFullYear() === now.getFullYear() && month.getMonth() === now.getMonth();
    };
    const loadTimes = async () => {
      const box = $('[data-times]'); box.replaceChildren();
      $('[data-date-label]').textContent = state.date ? longDate(state.date) : 'Selecione uma data para ver os horários.';
      if (!state.service || !state.date) return;
      box.textContent = 'Carregando horários…';
      const currentService = state.service.id, currentDate = state.date;
      try {
        const data = await request(`availability?service_id=${encodeURIComponent(currentService)}&date=${encodeURIComponent(currentDate)}`);
        if (currentService !== state.service.id || currentDate !== state.date) return;
        box.replaceChildren();
        if (!data.slots.length) { box.textContent = 'Sem horários livres nesta data.'; return; }
        data.slots.forEach(time => {
          const button = document.createElement('button'); button.type = 'button'; button.textContent = time;
          button.classList.toggle('is-selected', time === state.time);
          button.addEventListener('click', () => { state.time = time; box.querySelectorAll('button').forEach(b => b.classList.toggle('is-selected', b === button)); updateSummary(); });
          box.append(button);
        });
      } catch (error) { box.textContent = error.message; }
    };
    $('[data-prev]').addEventListener('click', () => { state.month = new Date(state.month.getFullYear(), state.month.getMonth() - 1, 1); renderCalendar(); });
    $('[data-next]').addEventListener('click', () => { state.month = new Date(state.month.getFullYear(), state.month.getMonth() + 1, 1); renderCalendar(); });
    $('[data-booking-form]').addEventListener('submit', async event => {
      event.preventDefault();
      const form = event.currentTarget;
      if (!form.reportValidity() || !state.service || !state.date || !state.time) return;
      $('[data-submit]').disabled = true; $('[data-feedback]').textContent = 'Enviando solicitação…';
      const payload = Object.fromEntries(new FormData(form).entries());
      Object.assign(payload, { service_id: state.service.id, date: state.date, time: state.time });
      try {
        const data = await request('bookings', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        $('[data-reference]').textContent = data.reference;
        $('.ag-layout').hidden = true; $('.ag-steps').hidden = true; $('[data-success]').hidden = false;
        $('[data-success]').scrollIntoView({ behavior: 'smooth', block: 'center' });
      } catch (error) {
        $('[data-feedback]').textContent = error.message;
        $('[data-submit]').disabled = false;
        if (error.message.includes('disponível')) loadTimes();
      }
    });
    $('[data-new]').addEventListener('click', () => window.location.reload());
    state.month = new Date(new Date(`${today}T12:00:00`).getFullYear(), new Date(`${today}T12:00:00`).getMonth(), 1);
    renderCalendar(); updateSummary(); loadServices();
  });
})();
