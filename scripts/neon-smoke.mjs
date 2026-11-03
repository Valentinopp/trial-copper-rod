// Uses the real HTTP/Livewire app, then removes only the LOT it created.
const base = (process.env.APP_TEST_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const username = process.env.SMOKE_USERNAME;
const password = process.env.SMOKE_PASSWORD;
if (!username || !password) throw new Error('Set SMOKE_USERNAME and SMOKE_PASSWORD for an existing production operator.');
const cookies = new Map();
let csrf;
let lotId;
let cleaned = false;
const marker = `NEON-SMOKE-${Date.now()}`;
const decode = value => value.replace(/&quot;/g, '"').replace(/&#0*39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
const assert = (condition, message) => { if (!condition) throw new Error(message); };

async function request(path, options = {}) {
  const response = await fetch(base + path, {
    ...options, redirect: 'manual', headers: { Cookie: [...cookies].map(([k, v]) => `${k}=${v}`).join('; '), ...options.headers },
  });
  for (const cookie of response.headers.getSetCookie()) {
    const pair = cookie.split(';')[0];
    const split = pair.indexOf('=');
    cookies.set(pair.slice(0, split), pair.slice(split + 1));
  }
  return response;
}

function snapshot(html, component) {
  for (const match of html.matchAll(/wire:snapshot="([^"]+)"/g)) {
    const value = decode(match[1]);
    if (JSON.parse(value).memo.name === component) return value;
  }
  throw new Error(`Missing Livewire component: ${component}`);
}

async function call(state, method, updates = {}, params = []) {
  const response = await request('/livewire/update', {
    method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Livewire': 'true', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
    body: JSON.stringify({ _token: csrf, components: [{ snapshot: state, updates, calls: [{ path: '', method, params }] }] }),
  });
  assert(response.ok, `Livewire ${method}: HTTP ${response.status}`);
  const result = (await response.json()).components[0];
  const failure = result.effects.dispatches?.find(event => event.name === 'notify' && ['error', 'warning'].includes(event.params?.[0]?.type));
  assert(!failure, `Livewire ${method}: validation failed`);
  return result;
}

function option(html, id) {
  const select = html.match(new RegExp(`<select[^>]*id="${id}"[\\s\\S]*?</select>`))?.[0];
  const values = [...(select || '').matchAll(/<option\s+value="([^"]+)"/g)];
  assert(values.length, `No active master option: ${id}`);
  return decode(values[0][1]);
}

function stamp(time) {
  const parts = Object.fromEntries(new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Asia/Jakarta', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
  }).formatToParts(time).map(part => [part.type, part.value]));
  return { date: `${parts.year}-${parts.month}-${parts.day}`, time: `${parts.hour}:${parts.minute}` };
}

try {
  const login = await request('/login');
  assert(login.status === 200, `Login page: HTTP ${login.status}`);
  csrf = (await login.text()).match(/name="_token"\s+value="([^"]+)"/)?.[1];
  assert(csrf, 'Missing login CSRF token');
  const signedIn = await request('/login', {
    method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ _token: csrf, username, password }),
  });
  assert(signedIn.status === 302 && !signedIn.headers.get('location')?.endsWith('/login'), 'Operator login failed');
  const board = await request('/');
  assert(board.status === 200, `Board: HTTP ${board.status}`);
  const boardHtml = await board.text();
  csrf = boardHtml.match(/name="_token"\s+value="([^"]+)"/)?.[1];
  assert(csrf, 'Missing authenticated CSRF token');
  const opened = await call(snapshot(boardHtml, 'produksi.board-page'), 'openBoardForm');
  const form = opened.effects.html;
  const start = stamp(new Date(Date.now() - 2 * 60 * 60 * 1000));
  const created = await call(opened.snapshot, 'save', {
    lot: marker, take_up: option(form, 'board-take_up'), cooling: option(form, 'board-cooling'),
    crystallizer: option(form, 'board-crystallizer'), rod_diameter_mm: option(form, 'board-rod_diameter_mm'),
    take_up_speed: '1200', upcasting_date: start.date, upcasting_time: start.time,
  });
  const article = [...created.effects.html.matchAll(/<article[^>]*wire:key="mobile-board-(\d+)"[\s\S]*?<\/article>/g)].find(match => match[0].includes(marker));
  assert(article, 'Saved LOT missing from response');
  lotId = Number(article[1]);
  const editing = await call(created.snapshot, 'edit', {}, [lotId]);
  const finish = stamp(new Date(Date.now() - 60 * 1000));
  await call(editing.snapshot, 'save', {
    actual_pickup_date: finish.date, actual_pickup_time: finish.time, event_status: 'panen', weight_kg: '123.45', reject_weight_kg: '3.45',
  });
  const pages = {};
  let outputHtml;
  for (const path of ['/', '/crystallizer', '/copper-break', '/output']) {
    const response = await request(path);
    pages[path] = response.status;
    assert(response.status === 200, `${path}: HTTP ${response.status}`);
    const html = await response.text();
    if (path === '/' || path === '/crystallizer') assert(html.includes(marker), `${path}: saved LOT not persisted`);
    if (path === '/output') outputHtml = html;
  }
  const exported = await call(snapshot(outputHtml, 'produksi.output-page'), 'exportExcel');
  const event = exported.effects.dispatches.find(item => item.name === 'export-excel');
  assert(event, 'Output export missing');
  const actual = event.params[0].rows.find(row => row[1] === 'AKTUAL OUTPUT (MT)');
  const actualMt = actual.slice(2).reduce((sum, value) => sum + Number(value || 0), 0);
  assert(Math.abs(actualMt - 0.12) < 0.000001, `Expected 0.12 MT in monthly report, got ${actualMt}`);
  console.log(JSON.stringify({ status: 'HTTP_NEON_SMOKE_OK', login: signedIn.status, pages, lotCreated: true, lotUpdated: true, actualGoodKg: 120, exportedActualMt: actualMt }));
} finally {
  if (lotId) {
    const response = await request('/');
    const html = await response.text();
    await call(snapshot(html, 'produksi.board-page'), 'remove', {}, [lotId]);
    const checked = await request('/');
    cleaned = !(await checked.text()).includes(marker);
    assert(cleaned, 'Smoke LOT cleanup failed');
    console.log(JSON.stringify({ status: 'SMOKE_LOT_REMOVED', cleaned }));
  }
}
