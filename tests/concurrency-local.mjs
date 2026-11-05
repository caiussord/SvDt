import http from 'node:http';

const port = Number(process.env.AGENDAPRO_PORT || 10011);

function request(method, path, body) {
  return new Promise((resolve, reject) => {
    const payload = body ? JSON.stringify(body) : '';
    const req = http.request({ hostname: '127.0.0.1', port, path, method, headers: {
      Host: `localhost:${port}`, 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(payload),
    } }, res => {
      let text = '';
      res.on('data', chunk => { text += chunk; });
      res.on('end', () => { try { resolve({ status: res.statusCode, data: JSON.parse(text) }); } catch { reject(new Error(text)); } });
    });
    req.on('error', reject);
    req.end(payload);
  });
}

const services = await request('GET', '/wp-json/agendapro/v1/services');
if (services.status !== 200 || !services.data.length) throw new Error('Serviços não disponíveis.');
const service = services.data[0];
let day = new Date(); day.setDate(day.getDate() + 14);
let slots, date;
for (let i = 0; i < 7; i++) {
  date = `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`;
  slots = await request('GET', `/wp-json/agendapro/v1/availability?service_id=${service.id}&date=${date}`);
  if (slots.data.slots?.length) break;
  day.setDate(day.getDate() + 1);
}
if (!slots.data.slots?.length) throw new Error('Nenhum horário futuro disponível para teste.');
const body = { service_id: service.id, date, time: slots.data.slots[0], customer_name: 'Cliente Demonstração', customer_email: 'demo@example.com', customer_phone: '11999999999', notes: 'Teste de concorrência' };
const results = await Promise.all([request('POST', '/wp-json/agendapro/v1/bookings', body), request('POST', '/wp-json/agendapro/v1/bookings', body)]);
console.log(JSON.stringify({ date, time: body.time, results }, null, 2));
if (results.filter(x => x.status === 201).length !== 1 || results.filter(x => x.status === 409).length !== 1) process.exitCode = 1;
