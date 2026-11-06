import http from 'node:http';

const port = Number(process.env.SVDT_PORT || 10011);
const serviceId = Number(process.env.SVDT_SERVICE_ID);
const firstId = Number(process.env.SVDT_FIRST_PROFESSIONAL_ID);
const secondId = Number(process.env.SVDT_SECOND_PROFESSIONAL_ID);
const date = process.env.SVDT_DATE;
const marker = process.env.SVDT_TEST_MARKER;
if (!serviceId || !firstId || !secondId || !date || !marker) throw new Error('Fixture de concorrência incompleta.');

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

async function slots(professionalId) {
  const response = await request('GET', `/wp-json/svdt/v1/availability?service_id=${serviceId}&professional_id=${professionalId}&date=${date}`);
  if (response.status !== 200) throw new Error(`Disponibilidade falhou: ${response.status}`);
  return response.data.slots;
}

const path = '/wp-json/svdt/v1/bookings';
const body = (professionalId, time) => ({ service_id: serviceId, professional_id: professionalId, date, time,
  customer_name: 'Teste de Concorrência', customer_email: 'team-concurrency@example.com', customer_phone: '11999999999', notes: marker });

const missingProfessional = await request('GET', `/wp-json/svdt/v1/availability?service_id=${serviceId}&date=${date}`);
if (missingProfessional.status !== 400 || missingProfessional.data.code !== 'professional_required') {
  throw new Error('A API não exigiu a escolha de profissional para a equipe.');
}
const secondSlots = await slots(secondId);
const common = (await slots(firstId)).filter(time => secondSlots.includes(time));
if (!common.length) throw new Error('Não há horário comum para o teste.');
const same = await Promise.all([request('POST', path, body(firstId, common[0])), request('POST', path, body(firstId, common[0]))]);
if (same.filter(result => result.status === 201).length !== 1 || same.filter(result => result.status === 409).length !== 1) {
  throw new Error(`Conflito na mesma agenda falhou: ${JSON.stringify(same.map(result => result.status))}`);
}
const nextSecondSlots = await slots(secondId);
const nextCommon = (await slots(firstId)).filter(time => nextSecondSlots.includes(time));
if (!nextCommon.length) throw new Error('Não há segundo horário comum para o teste.');
const different = await Promise.all([request('POST', path, body(firstId, nextCommon[0])), request('POST', path, body(secondId, nextCommon[0]))]);
if (different.some(result => result.status !== 201)) {
  throw new Error(`Reservas em agendas diferentes falharam: ${JSON.stringify(different.map(result => result.status))}`);
}
console.log(JSON.stringify({ same: same.map(result => result.status), different: different.map(result => result.status) }));
