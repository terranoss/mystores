/* Minimal DOM shim to smoke-test the demo renderer without a browser.
   Not shipped — verification only. */
class ClassList {
  constructor(node) { this.node = node; }
  add() {} remove() {} contains() { return false; }
}

class Node {
  constructor(tag) {
    this.tagName = (tag || '').toUpperCase();
    this.children = [];
    this.attributes = {};
    this.listeners = {};
    this._text = '';
    this.style = new Proxy({}, { set: (t, k, v) => (t[k] = v), get: (t, k) => t[k] || '' });
    this.classList = new ClassList(this);
    this.disabled = false;
    this.checked = false;
    this.value = '';
  }
  get className() { return this.attributes.class || ''; }
  set className(v) { this.attributes.class = v; }
  set innerHTML(v) { this._html = v; this.children = []; }
  get innerHTML() { return this._html || ''; }
  set textContent(v) { this._text = v; this.children = []; }
  get textContent() { return this._text; }
  appendChild(c) { this.children.push(c); return c; }
  setAttribute(k, v) {
    this.attributes[k] = v;
    if (k === 'value') this.value = v;
    if (k === 'checked') this.checked = v;
  }
  getAttribute(k) { return this.attributes[k]; }
  addEventListener(evt, fn) { (this.listeners[evt] = this.listeners[evt] || []).push(fn); }
  removeEventListener() {}
  click() { (this.listeners.click || []).forEach((f) => f({ preventDefault() {}, stopPropagation() {}, target: this })); }
  change() { (this.listeners.change || []).forEach((f) => f({ target: this })); }
  input() { (this.listeners.input || []).forEach((f) => f({ target: this })); }
  getContext() { return null; }
}

const ROOT = new Node('div');
const doc = {
  createElement: (t) => new Node(t),
  createTextNode: (t) => ({ text: String(t), isText: true }),
  getElementById: (id) => (id === 'root' ? ROOT : new Node('div')),
  addEventListener: () => {},
};

global.document = doc;
global.window = global;   // single shared global so module `window.x =` is visible

function walk(node, out = []) {
  if (!node) return out;
  if (node.isText) { out.push(node.text); return out; }
  if (node._html) out.push(node._html.replace(/<[^>]+>/g, ''));
  if (node._text) out.push(node._text);
  for (const c of node.children) walk(c, out);
  return out;
}

globalThis.__walk = walk;
globalThis.__node = Node;

const errors = [];
process.on('uncaughtException', (e) => { errors.push(e); });

await import('./assets/demo.js');

const root = ROOT;
const text = walk(root).join(' ');

const checks = [
  ['app rendered', root.children.length > 0],
  ['demo bar present', text.includes('not connected to a live store')],
  ['sidebar brand', text.includes('MyStores')],
  ['nav items', ['Dashboard', 'Orders', 'Stock', 'Promotions', 'Team', 'Settings'].every((n) => text.includes(n))],
  ['version', text.includes('v1.0.0')],
  ['user name', text.includes('Aisyah Rahman')],
  ['dashboard heading', text.includes('Dashboard')],
  ['KPI labels', ['Linked Stores', 'Orders Today', 'Sales Today', 'Pending Orders', 'Low Stock Items'].every((n) => text.includes(n))],
  ['currency format', /RM\s[\d,]+\.\d{2}/.test(text)],
  ['sales trend heading', /Sales \(\d+ days\)/.test(text)],
  ['cancel reasons', text.includes('Cancel reasons')],
  ['linked stores', text.includes('Linked Stores')],
  ['health badges', text.includes('Active') && text.includes('Expiring') && text.includes('Reauthorize Required')],
  ['low stock alert', text.includes('running low on stock')],
  ['bar chart bars', (text.match(/\d/g) || []).length > 0],
];

let failed = 0;
for (const [name, ok] of checks) {
  if (!ok) failed++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}`);
}

// Exercise every page for runtime errors
console.log('\n--- PAGE SWEEP ---');
const PAGES = ['dashboard', 'orders', 'stock', 'picklist-today', 'picklist-pending', 'promotions', 'team', 'settings', 'docs-auth', 'docs-menu'];
for (const p of PAGES) {
  try {
    window.__demoGo(p);
    const t = walk(ROOT).join(' ');
    console.log(`PASS  ${p.padEnd(16)} (${t.length} chars)`);
    if (t.length < 200) { console.log(`  FAIL  ${p} rendered too little`); failed++; }
  } catch (e) {
    failed++;
    console.log(`FAIL  ${p.padEnd(16)} ${e.message}`);
  }
}

// Exercise key interactions
console.log('\n--- INTERACTIONS ---');
const interact = [
  ['marketplace filter -> lazada', () => { window.__demoSetMarketplace('lazada'); return true }],
  ['marketplace filter -> shopee', () => { window.__demoSetMarketplace('shopee'); return true }],
  ['trend 30 days', () => { window.__demoSetTrend(30); return true }],
  ['trend 7 days', () => { window.__demoSetTrend(7); return true }],
  ['order status filter', () => { window.__demoSetOrderFilter('SHIPPED'); return true }],
  ['stock search', () => { window.__demoSetStockQuery('kitch'); return true }],
  ['reset filters', () => { window.__demoReset(); return true }],
];
for (const [name, fn] of interact) {
  try { fn(); console.log(`PASS  ${name}`); }
  catch (e) { failed++; console.log(`FAIL  ${name} ${e.message}`); }
}

const after = walk(ROOT).join(' ');
if (!after.includes('Dashboard')) { console.log('FAIL  post-interaction render'); failed++; }
else console.log('PASS  post-interaction render');
console.log(`\ntext length: ${text.length}`);
console.log(errors.length ? `RUNTIME ERRORS:\n${errors.join('\n')}` : 'no runtime errors');
process.exit(failed || errors.length ? 1 : 0);