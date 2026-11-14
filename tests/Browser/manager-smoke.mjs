// Read-only browser checks of HTML exported by ManagerMultiroleTest; no live requests.
import { spawn } from 'node:child_process';
import path from 'node:path';
import fs from 'node:fs';
import { pathToFileURL } from 'node:url';
import assert from 'node:assert/strict';

const directory = path.resolve('storage/app/private/ui-preview');
const chrome = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--allow-file-access-from-files', '--remote-debugging-port=0',
  '--user-data-dir='+path.join(directory, 'manager-chrome'), 'about:blank',
], { windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
let socket;
try {
  const endpoint = await new Promise((resolve, reject) => {
    let output = '';
    const timeout = setTimeout(() => reject(new Error('Chrome startup timed out')), 15000);
    chrome.on('error', reject);
    chrome.stderr.on('data', data => {
      output += data;
      const match = output.match(/DevTools listening on (ws:\/\/[^\s]+)/);
      if (match) { clearTimeout(timeout); resolve(match[1]); }
    });
  });
  const targets = await (await fetch(`http://127.0.0.1:${new URL(endpoint).port}/json/list`)).json();
  socket = new WebSocket(targets.find(target => target.type === 'page').webSocketDebuggerUrl);
  await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
  let id = 0;
  const pending = new Map();
  const errors = [];
  socket.addEventListener('message', event => {
    const message = JSON.parse(event.data);
    if (message.id) {
      const task = pending.get(message.id);
      if (task) { pending.delete(message.id); message.error ? task.reject(new Error(JSON.stringify(message.error))) : task.resolve(message.result); }
    }
    if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.text);
  });
  const send = (method, params = {}) => new Promise((resolve, reject) => {
    const key = ++id;
    const timer = setTimeout(() => { pending.delete(key); reject(new Error(method+' timed out')); }, 10000);
    pending.set(key, { resolve: result => { clearTimeout(timer); resolve(result); }, reject });
    socket.send(JSON.stringify({ id: key, method, params }));
  });
  await send('Page.enable');
  await send('Runtime.enable');
  for (const page of process.argv.includes('--users-only') ? ['users'] : ['dashboard', 'users']) {
    for (const width of [1672, 390]) {
      await send('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: false });
      await send('Page.navigate', { url: pathToFileURL(path.join(directory, `manager-${page}.html`)).href });
      let result;
      for (let attempt = 0; attempt < 20; attempt++) {
        result = await send('Runtime.evaluate', { expression: `document.readyState === 'complete' && document.querySelector('.mgr-page') !== null`, returnByValue: true });
        if (result.result.value) break;
        await new Promise(resolve => setTimeout(resolve, 100));
      }
      assert.equal(result.result.value, true, page+' loaded');
      const state = await send('Runtime.evaluate', { expression: `JSON.stringify({width:innerWidth, contentWidth:document.documentElement.scrollWidth, kpis:document.querySelectorAll('.mgr-kpi').length, checkboxCount:document.querySelectorAll('input[name="role_ids[]"]').length, editor:!!document.querySelector('.mgr-editor'), color:getComputedStyle(document.querySelector('.app-sidebar')).backgroundImage})`, returnByValue: true });
      const values = JSON.parse(state.result.value);
      assert.equal(values.kpis, 4);
      assert.ok(values.contentWidth <= width + 1, `${page} at ${width}px overflows: ${values.contentWidth}`);
      assert.ok(values.color.includes('linear-gradient'));
      if (page === 'users') { assert.equal(values.checkboxCount, 7); assert.equal(values.editor, true); }
      const screenshot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
      fs.writeFileSync(path.join(directory, `manager-${page}-${width}.png`), Buffer.from(screenshot.data, 'base64'));
      if (page === 'users') {
        const behavior = await send('Runtime.evaluate', { expression: `(async () => {
          const select = document.querySelector('[data-primary-role]');
          const original = select.value;
          const next = [...select.options].find(option => option.value && option.value !== original).value;
          select.value = next;
          select.dispatchEvent(new Event('change', {bubbles:true}));
          const oldCheckbox = document.querySelector('input[name="role_ids[]"][value="'+original+'"]');
          const mainCheckbox = document.querySelector('input[name="role_ids[]"][value="'+next+'"]');
          const rolesPreserved = oldCheckbox.checked && !oldCheckbox.disabled && mainCheckbox.disabled && !mainCheckbox.checked;
          const input = document.querySelector('[data-employee-photo]');
          const transfer = new DataTransfer();
          const canvas = document.createElement('canvas'); canvas.width = canvas.height = 16;
          const context = canvas.getContext('2d'); context.fillStyle = '#3589bb'; context.fillRect(0,0,16,16);
          const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
          transfer.items.add(new File([blob], 'preview.png', {type:'image/png'}));
          input.files = transfer.files;
          input.dispatchEvent(new Event('change',{bubbles:true}));
          const preview = document.querySelector('.mgr-profile-header [data-avatar-photo]');
          await preview.decode();
          const previewReady = preview.src.startsWith('blob:') && !preview.hidden && preview.naturalWidth === 16;
          const invalid = new DataTransfer();
          invalid.items.add(new File(['invalid'], 'document.pdf', {type:'application/pdf'}));
          input.files = invalid.files;
          input.dispatchEvent(new Event('change',{bubbles:true}));
          return JSON.stringify({rolesPreserved, previewReady, rejected: input.files.length === 0 && document.querySelector('[data-photo-feedback]').classList.contains('alert'), positionSelect:!!document.querySelector('select[name="id_puesto"]')});
        })()`, returnByValue: true, awaitPromise: true });
        const checks = JSON.parse(behavior.result.value);
        assert.deepEqual(checks, {rolesPreserved:true, previewReady:true, rejected:true, positionSelect:true});
      }
      console.log(`PASS ${page} ${width}px: four KPIs, no page overflow${page === 'users' ? ', editor and seven role checkboxes' : ''}`);
    }
  }
  assert.deepEqual(errors, []);
} finally {
  socket?.close();
  chrome.kill();
}
