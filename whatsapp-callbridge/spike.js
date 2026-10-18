/**
 * Call-bridge spike.
 *
 * Goals:
 *  1. Pair a web.whatsapp.com session for 201024520482 (pairing code).
 *  2. Probe the page for call capability (Store modules, DOM buttons, media APIs).
 *  3. Inject media hooks (getUserMedia / RTCPeerConnection / AudioContext /
 *     HTMLMediaElement.srcObject) so we can tap remote audio and feed agent audio.
 *  4. Mini HTTP API (port 3999) to trigger probe/dial/answer/reject/hangup and
 *     dump collected events — for manual driving during the spike.
 */
const { Client, LocalAuth } = require('whatsapp-web.js');
const express = require('express');
const qrcode = require('qrcode');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const PHONE = '201024520482';
const TARGET = '201011679726';
const AUTH_ROOT = path.join(__dirname, '.auth');
const PORT = 3999;
const CHROME =
  '/var/www/Wavixa/whatsapp-service/.puppeteer_cache/chrome/linux-146.0.7680.31/chrome-linux64/chrome';
const OUT_DIR = path.join(__dirname, 'out');
fs.mkdirSync(OUT_DIR, { recursive: true });

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const log = (...a) => console.log(new Date().toISOString(), ...a);

let client = null;
let pairingCode = null;
let currentQr = null;
let callState = 'idle';
let remotePcm = []; // int16 chunks captured from remote audio

// ---------------------------------------------------------------------------
// Page-side hooks (injected after ready, before any call is made)
// ---------------------------------------------------------------------------
const HOOKS_SRC = `
(() => {
  if (window.__cb) return 'already';
  window.__cb = { events: [], pcs: [], remoteStreams: [], micStreams: [], audioCtxs: [], mediaEls: [], tapCount: 0 };
  const C = window.__cb;
  C.log = (...a) => { try { C.events.push([Date.now(), a.map(x => { try { return typeof x === 'object' ? JSON.stringify(x).slice(0, 300) : String(x); } catch (e) { return '?'; } }).join(' ')]); if (C.events.length > 2000) C.events.shift(); } catch (e) {} };
  C.log('hooks-installed', navigator.userAgent);

  // RTCPeerConnection — capture remote tracks.
  // IMPORTANT: must be a subclass so statics (generateCertificate) survive —
  // WhatsApp's SCTP fallback calls RTCPeerConnection.generateCertificate and
  // a plain function wrapper silently kills every relay connection.
  try {
    const _PC = window.RTCPeerConnection;
    class HookedPC extends _PC {
      constructor(...a) {
        super(...a);
        C.pcs.push(this);
        C.log('new-pc');
        this.addEventListener('track', (ev) => {
          C.log('pc.track', ev.track.kind, ev.track.id, ev.streams.length);
          const s = ev.streams && ev.streams[0] ? ev.streams[0] : new MediaStream([ev.track]);
          C.remoteStreams.push(s);
          if (ev.track.kind === 'audio') { try { window.__cbTap(s); } catch (e) { C.log('tap-err', String(e)); } }
        });
      }
    }
    window.RTCPeerConnection = HookedPC;
  } catch (e) { C.log('pc-hook-err', String(e)); }

  // getUserMedia — observe + optional fake mic
  try {
    const _gum = navigator.mediaDevices.getUserMedia.bind(navigator.mediaDevices);
    navigator.mediaDevices.getUserMedia = async (constraints) => {
      C.log('gum-request', constraints);
      if (C.fakeMicStream) { C.log('gum-served-fake'); return C.fakeMicStream; }
      const s = await _gum(constraints);
      C.micStreams.push(s);
      return s;
    };
  } catch (e) { C.log('gum-hook-err', String(e)); }

  // enumerateDevices — servers have zero audio devices; WA checks this and
  // fails the call before init. Report a virtual mic + speaker.
  try {
    const _ed = navigator.mediaDevices.enumerateDevices.bind(navigator.mediaDevices);
    navigator.mediaDevices.enumerateDevices = async () => {
      const real = await _ed().catch(() => []);
      const mk = (kind, label, id) => ({ deviceId: id, kind, label, groupId: 'cb-' + kind, toJSON() { return { deviceId: this.deviceId, kind: this.kind, label: this.label, groupId: this.groupId }; } });
      const fake = [mk('audioinput', 'Wavixa Virtual Mic', 'default'), mk('audiooutput', 'Wavixa Virtual Speaker', 'default'), mk('audiooutput', 'Communications', 'communications')];
      const seen = new Set(real.map(d => d.kind + ':' + d.deviceId));
      return [...real, ...fake.filter(f => !seen.has(f.kind + ':' + f.deviceId))];
    };
  } catch (e) { C.log('enum-hook-err', String(e)); }

  // AudioContext — catch WebAudio-based remote audio (preserve prototype/statics)
  try {
    const _AC = window.AudioContext;
    window.AudioContext = class extends _AC {
      constructor(...a) { super(...a); C.audioCtxs.push(this); }
    };
    const _cmss = _AC.prototype.createMediaStreamSource;
    _AC.prototype.createMediaStreamSource = function (s) {
      C.log('webaudio-ms-source', s.getTracks().map(t => t.kind + ':' + t.readyState).join(','));
      C.remoteStreams.push(s);
      try { window.__cbTap(s); } catch (e) { C.log('tap-err', String(e)); }
      return _cmss.call(this, s);
    };
  } catch (e) { C.log('actx-hook-err', String(e)); }

  // HTMLMediaElement.srcObject — catch plain <audio> playback
  try {
    const proto = HTMLMediaElement.prototype;
    const desc = Object.getOwnPropertyDescriptor(proto, 'srcObject');
    if (desc && desc.set) {
      Object.defineProperty(proto, 'srcObject', {
        configurable: true,
        get: desc.get,
        set: function (v) {
          if (v && v.getTracks) {
            C.log('elem-srcObject', v.getTracks().map(t => t.kind + ':' + t.readyState).join(','));
            C.remoteStreams.push(v);
            try { window.__cbTap(v); } catch (e) { C.log('tap-err', String(e)); }
          }
          return desc.set.call(this, v);
        }
      });
    }
    // track element creation too
    const _sa = proto.setAttribute;
    proto.setAttribute = function (k, v) { return _sa.call(this, k, v); };
  } catch (e) { C.log('srcobj-hook-err', String(e)); }

  // ---- remote audio tap: stream -> PCM int16 -> Node via __cbAudioOut -------
  window.__cbTap = (stream) => {
    const audioTrack = stream.getAudioTracks()[0];
    if (!audioTrack) { C.log('tap-no-audio'); return; }
    if (C.tappedTrackId === audioTrack.id) { return; }
    C.tappedTrackId = audioTrack.id;
    C.tapCount++;
    const ac = C.tapCtx || (C.tapCtx = new AudioContext());
    const src = ac.createMediaStreamSource(stream);
    const proc = ac.createScriptProcessor(4096, 1, 1);
    const gain = ac.createGain();
    gain.gain.value = 0;
    src.connect(proc);
    proc.connect(gain);
    gain.connect(ac.destination);
    proc.onaudioprocess = (e) => {
      const f = e.inputBuffer.getChannelData(0);
      const i16 = new Int16Array(f.length);
      for (let i = 0; i < f.length; i++) {
        const s = Math.max(-1, Math.min(1, f[i]));
        i16[i] = s < 0 ? s * 0x8000 : s * 0x7fff;
      }
      const bytes = new Uint8Array(i16.buffer);
      let bin = '';
      for (let i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
      try { window.__cbAudioOut(btoa(bin), ac.sampleRate); } catch (err) {}
    };
    C.log('tap-installed', 'sr=' + ac.sampleRate, 'track=' + audioTrack.id);
  };

  // ---- fake mic: Node pushes PCM chunks -> MediaStream served to getUserMedia -
  window.__cbInitFakeMic = () => {
    if (C.micReady) return 'already';
    const ac = C.micCtx = new AudioContext({ sampleRate: 48000 });
    const dest = ac.createMediaStreamDestination();
    const proc = ac.createScriptProcessor(2048, 0, 1);
    C.micQueue = [];
    proc.onaudioprocess = (e) => {
      const out = e.outputBuffer.getChannelData(0);
      const q = C.micQueue;
      let i = 0;
      while (i < out.length && q.length) {
        const chunk = q[0];
        const n = Math.min(chunk.length, out.length - i);
        out.set(chunk.subarray(0, n), i);
        i += n;
        q[0] = chunk.subarray(n);
        if (!q[0].length) q.shift();
      }
    };
    proc.connect(dest);
    C.fakeMicStream = dest.stream;
    C.micReady = true;
    C.log('fake-mic-ready', 'track=' + dest.stream.getAudioTracks()[0].id);
    return 'ok';
  };

  window.__cbMicPush = (b64) => {
    if (!C.micQueue) return 'no-queue';
    const bin = atob(b64);
    const i16 = new Int16Array(bin.length / 2);
    for (let i = 0; i < i16.length; i++) i16[i] = bin.charCodeAt(i * 2) | (bin.charCodeAt(i * 2 + 1) << 8);
    const f = new Float32Array(i16.length);
    for (let i = 0; i < i16.length; i++) f[i] = i16[i] / 0x8000;
    C.micQueue.push(f);
    return C.micQueue.length;
  };

  return 'installed';
})()
`;

// ---------------------------------------------------------------------------
// Probe: what call capabilities does this page expose?
// ---------------------------------------------------------------------------
const PROBE_SRC = `
(() => {
  const out = {};
  out.ua = navigator.userAgent;
  out.media = {
    gUM: !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia),
    rtc: !!window.RTCPeerConnection,
    actx: !!window.AudioContext,
    msd: !!(window.AudioContext && AudioContext.prototype.createMediaStreamDestination),
  };
  try {
    out.hasStore = !!window.Store;
    out.storeKeys = window.Store ? Object.keys(window.Store).filter(k => /call|voip|rtc|audio|phone/i.test(k)) : [];
  } catch (e) { out.storeErr = String(e); }
  try {
    const names = [];
    if (window.mR && window.mR.modules) {
      for (const n of Object.keys(window.mR.modules)) {
        if (/call|voip|rtc/i.test(n)) names.push(n);
      }
    } else if (window.require) {
      out.hasRequire = true;
    }
    out.moduleNames = names.slice(0, 300);
    out.moduleCount = window.mR ? Object.keys(window.mR.modules).length : 0;
  } catch (e) { out.modErr = String(e); }
  // DOM: header icons across the whole document (call button lives in chat header)
  try {
    const icons = [...document.querySelectorAll('span[data-icon], div[data-icon], button[data-icon]')]
      .map(e => e.getAttribute('data-icon'));
    out.dataIcons = [...new Set(icons)];
    const aria = [...document.querySelectorAll('[aria-label]')]
      .map(e => e.getAttribute('aria-label')).filter(x => x && /call|phone/i.test(x));
    out.callAria = [...new Set(aria)];
  } catch (e) { out.domErr = String(e); }
  return out;
})()
`;

async function runProbe() {
  const page = client.pupPage;
  return await page.evaluate(PROBE_SRC);
}

async function injectHooks(page) {
  page = page || client.pupPage;
  if (!page || page.__cbBound) return 'skip';
  try {
    await page.exposeFunction('__cbAudioOut', (b64, sr) => {
      remotePcm.push(Buffer.from(b64, 'base64'));
      if (remotePcm.length === 1) log(`[audio] remote PCM flowing @${sr}Hz`);
    });
  } catch (e) { /* binding exists */ }
  try {
    await page.evaluateOnNewDocument(HOOKS_SRC);
    await page.evaluate(HOOKS_SRC);
    await page.evaluate(DEST_TAP_SRC);
    page.__cbBound = true;
    return 'installed';
  } catch (e) {
    return 'err:' + e.message.slice(0, 120);
  }
}

// Intercept connect(destination) → route audio through a PCM tap.
const DEST_TAP_SRC = `
(() => {
  const C = window.__cb; if (!C || C.destTapDone) return 'skip';
  C.destTapDone = true;
  const _conn = AudioNode.prototype.connect;
  C.taps = C.taps || new Map();
  function tapFor(ac) {
    if (C.taps.has(ac)) return C.taps.get(ac);
    const proc = ac.createScriptProcessor(4096, 1, 1);
    const gain = ac.createGain(); gain.gain.value = 0;
    proc.onaudioprocess = (e) => {
      const f = e.inputBuffer.getChannelData(0);
      const i16 = new Int16Array(f.length);
      for (let i = 0; i < f.length; i++) { const s = Math.max(-1, Math.min(1, f[i])); i16[i] = s < 0 ? s * 0x8000 : s * 0x7fff; }
      const b = new Uint8Array(i16.buffer); let bin = '';
      for (let i = 0; i < b.length; i++) bin += String.fromCharCode(b[i]);
      try { window.__cbAudioOut(btoa(bin), ac.sampleRate); } catch (err) {}
    };
    _conn.call(proc, gain); _conn.call(gain, ac.destination);
    C.taps.set(ac, proc); C.log('tap-created sr=' + ac.sampleRate);
    return proc;
  }
  AudioNode.prototype.connect = function (dest, ...rest) {
    try {
      if (dest && dest.constructor && dest.constructor.name === 'AudioDestinationNode') {
        const proc = tapFor(dest.context);
        _conn.call(this, proc, ...rest);
        return dest;
      }
    } catch (e) { C.log('conn-hook-err', String(e)); }
    return _conn.call(this, dest, ...rest);
  };
  return 'ok';
})()
`;

async function injectAllPages() {
  const results = {};
  try {
    const pages = await client.pupBrowser.pages();
    for (const p of pages) {
      const url = p.url();
      if (!/web\.whatsapp\.com/.test(url)) continue;
      results[url.slice(0, 60)] = await injectHooks(p);
    }
  } catch (e) { results.err = e.message; }
  return results;
}

// ---------------------------------------------------------------------------
// Call control attempts — multiple strategies, report what works
// ---------------------------------------------------------------------------
async function findCallApi() {
  const page = client.pupPage;
  return await page.evaluate(`(() => {
    const found = {};
    // strategy 1: Store namespaces
    if (window.Store) {
      for (const k of Object.keys(window.Store)) {
        const v = window.Store[k];
        if (!v) continue;
        const fns = typeof v === 'function' ? [] : Object.getOwnPropertyNames(v).filter(p => typeof v[p] === 'function');
        if (/call|voip/i.test(k)) found['Store.' + k] = fns.slice(0, 40);
      }
    }
    // strategy 2: module registry — find modules exporting call-ish functions
    if (window.mR) {
      const names = Object.keys(window.mR.modules).filter(n => /call|voip/i.test(n));
      found.modules = names.slice(0, 100);
      for (const n of names.slice(0, 60)) {
        try {
          const m = window.mR.findModule(n)[0];
          if (m) {
            const keys = Object.keys(m).filter(p => /call|dial|answer|accept|reject|hangup|end|voip|start/i.test(p) && typeof m[p] === 'function');
            if (keys.length) found['mod:' + n] = keys.slice(0, 30);
          }
        } catch (e) {}
      }
    }
    return found;
  })()`);
}

async function tryDomDial(to) {
  const page = client.pupPage;
  // open the chat then click the voice-call button
  try { await client.interface.openChatWindow(`${to}@c.us`); } catch (e) { log('openChatWindow err', e.message); }
  await sleep(2500);
  return await page.evaluate(`(() => {
    const cands = [...document.querySelectorAll('span[data-icon]')];
    const names = cands.map(e => e.getAttribute('data-icon'));
    // try common call icon names
    for (const icon of cands) {
      const n = (icon.getAttribute('data-icon') || '').toLowerCase();
      if (/audio-call|voice-call|(^|-)call(-|$)|phone/.test(n)) {
        let el = icon;
        for (let i = 0; i < 6 && el; i++) { if (el.getAttribute('role') === 'button' || el.tagName === 'BUTTON' || el.getAttribute('tabindex')) break; el = el.parentElement; }
        (el || icon).dispatchEvent(new MouseEvent('mousedown', {bubbles:true}));
        (el || icon).click();
        return { clicked: n };
      }
    }
    return { clicked: null, icons: [...new Set(names)] };
  })()`);
}

async function dumpCallScreen() {
  const page = client.pupPage;
  return await page.evaluate(`(() => {
    const aria = [...document.querySelectorAll('[aria-label]')].map(e => e.getAttribute('aria-label')).filter(Boolean);
    const icons = [...document.querySelectorAll('span[data-icon]')].map(e => e.getAttribute('data-icon'));
    const texts = [...document.querySelectorAll('header, [role=dialog], [data-testid]')].slice(0, 40).map(e => (e.innerText||'').slice(0, 80));
    return { callishAria: [...new Set(aria.filter(a => /call|end|mute|speaker|video/i.test(a)))], icons: [...new Set(icons)].filter(i => /call|mic|speak|end|video/i.test(i)), texts };
  })()`);
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
async function bootClient() {
  client = new Client({
    authStrategy: new LocalAuth({ clientId: 'cb-482', dataPath: AUTH_ROOT }),
    authTimeoutMs: 300000,
    qrTimeoutMs: 300000,
    restartOnAuthFail: false,
    puppeteer: {
      headless: true,
      executablePath: CHROME,
      protocolTimeout: 180000,
      args: [
        '--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage',
        '--use-fake-ui-for-media-stream', '--autoplay-policy=no-user-gesture-required',
        '--use-fake-device-for-media-stream',
        `--use-file-for-fake-audio-capture=${path.join(OUT_DIR, 'test-speech.wav')}`,
        '--enable-unsafe-swiftshader',
        '--disable-blink-features=AutomationControlled',
        '--lang=en-US', '--window-size=1366,900',
      ],
      defaultViewport: { width: 1366, height: 900 },
    },
  });

  client.on('qr', async (qr) => {
    currentQr = qr;
    fs.writeFileSync(path.join(OUT_DIR, 'qr.txt'), qr);
    log('QR received (len=' + qr.length + ')');
  });
  client.on('code_received', (c) => { pairingCode = c; log('*** PAIRING CODE (evt):', c, '***'); fs.writeFileSync(path.join(OUT_DIR, 'pairing_code.txt'), c); });
  client.on('authenticated', () => log('authenticated'));
  client.on('auth_failure', (m) => log('AUTH FAILURE', m));
  client.on('loading_screen', (p, m) => log('loading', p, m));
  client.on('disconnected', (r) => { log('DISCONNECTED', r); callState = 'offline'; });

  client.on('ready', async () => {
    try {
      log('READY — phone:', client.info?.wid?.user, client.info?.pushname);
      // Inject into every current WhatsApp page AND any future target — WhatsApp
      // opens calls in a separate window, which is its own JS context.
      client.pupBrowser.on('targetcreated', async (t) => {
        try {
          const p = await t.page();
          if (p && /web\.whatsapp\.com/.test(p.url())) {
            const r = await injectHooks(p);
            log('new-page injected:', p.url().slice(0, 70), r);
          }
        } catch (e) { log('targetcreated err', e.message); }
      });
      // Forward the page's own console — WA logs the real call-failure reason.
      for (const p of await client.pupBrowser.pages()) {
        p.on('console', (m) => log('[con:' + m.type() + ']', m.text().slice(0, 250)));
        p.on('pageerror', (e) => log('[pageerror]', String(e).slice(0, 300)));
      }
      await sleep(4000);
      const r = await injectAllPages();
      log('hooks all pages:', JSON.stringify(r));
      // NOTE: no auto fake-mic init — Chrome's --use-fake-device-for-media-stream
      // provides a live browser-level capture track that never dies. The JS fake
      // mic (MediaStreamAudioDestinationNode track) ended immediately after
      // CallActive and made WA auto-terminate the call.
      const probe = await runProbe().catch((e) => ({ err: e.message }));
      fs.writeFileSync(path.join(OUT_DIR, 'probe.json'), JSON.stringify(probe, null, 2));
      log('probe saved. storeKeys:', JSON.stringify(probe.storeKeys), 'modules:', (probe.moduleNames || []).length, 'icons:', JSON.stringify((probe.dataIcons || []).slice(0, 40)));
      const api = await findCallApi().catch((e) => ({ err: e.message }));
      fs.writeFileSync(path.join(OUT_DIR, 'callapi.json'), JSON.stringify(api, null, 2));
      log('call api saved:', JSON.stringify(Object.keys(api)));
    } catch (e) { log('ready-handler err', e.message); }
  });

  client.on('incoming_call', (c) => { log('INCOMING_CALL evt:', JSON.stringify(c)); callState = 'ringing-in'; });
  client.on('call', (c) => { log('call evt:', JSON.stringify(c)); });

  await client.initialize();
  log('client.initialize resolved');
  return client;
}

async function main() {
  // Clear stale Chromium singleton locks from crashed runs — LocalAuth reuses
  // the same userDataDir and a leftover lock aborts the launch.
  const sessDir = path.join(AUTH_ROOT, 'session-cb-482');
  for (const f of ['SingletonLock', 'SingletonSocket', 'SingletonCookie']) {
    try { fs.rmSync(path.join(sessDir, f), { force: true }); } catch (_) {}
  }
  for (let att = 1; att <= 4; att++) {
    try {
      await bootClient();
      break;
    } catch (e) {
      log(`init attempt ${att} failed: ${e.message}`);
      try { if (client?.pupBrowser) await client.pupBrowser.close(); } catch (_) {}
      client = null;
      if (att === 4) throw e;
      await sleep(4000);
    }
  }

  // wweb.js spoofs an ancient Chrome/101 UA with EMPTY userAgentData.brands —
  // WA's voip engine refuses to initialize on that ("chrome-setup-error").
  // Override with the real Chrome 146 identity, then reload so the app boots
  // under it. LocalAuth survives the reload.
  try {
    const sess = await client.pupPage.createCDPSession();
    await sess.send('Emulation.setUserAgentOverride', {
      userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.7680.31 Safari/537.36',
      acceptLanguage: 'en-US,en;q=0.9',
      userAgentMetadata: {
        brands: [
          { brand: 'Chromium', version: '146' },
          { brand: 'Google Chrome', version: '146' },
          { brand: 'Not-A.Brand', version: '99' },
        ],
        fullVersion: '146.0.7680.31',
        platform: 'Linux',
        platformVersion: '6.8.0',
        architecture: 'x86',
        model: '',
        mobile: false,
        bitness: '64',
      },
    });
    log('UA override applied — reloading');
    await client.pupPage.reload({ waitUntil: 'domcontentloaded' });
  } catch (e) { log('UA override err', e.message); }

  // ---- mini API -------------------------------------------------------------
  const app = express();
  app.use(express.json({ limit: '10mb' }));

  app.get('/state', (_req, res) => res.json({ state: client?.info ? 'ready' : callState, pairingCode, callState }));
  app.get('/qr.png', async (_req, res) => {
    if (!currentQr) return res.status(404).end();
    const png = await qrcode.toBuffer(currentQr, { scale: 8, margin: 2 });
    res.type('png').send(png);
  });
  app.get('/qr', (_req, res) => {
    res.type('html').send(`<html><body style="margin:0;background:#111;display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh">
      <img id="q" src="/qr.png?ts=${Date.now()}" style="width:420px;image-rendering:pixelated;background:#fff;padding:12px">
      <div style="color:#fff;font:16px monospace;margin-top:12px">scan me — auto-refreshing</div>
      <script>setInterval(()=>{document.getElementById('q').src='/qr.png?ts='+Date.now()},4000);setTimeout(()=>location.reload(),240000)</script>
    </body></html>`);
  });
  app.get('/probe', async (_req, res) => res.json(await runProbe()));
  app.get('/events', async (_req, res) => {
    const ev = await client.pupPage.evaluate('window.__cb ? window.__cb.events.slice(-200) : []');
    res.json(ev);
  });
  app.get('/streams', async (_req, res) => {
    const s = await client.pupPage.evaluate(`(() => ({ pcs: window.__cb?.pcs.length, remote: window.__cb?.remoteStreams.map(s => s.getTracks().map(t => t.kind + ':' + t.readyState + ':' + t.id.slice(0,8)).join('+')), mic: window.__cb?.micStreams.length, ctxs: window.__cb?.audioCtxs.length, taps: window.__cb?.tapCount }))()`);
    res.json(s);
  });
  app.post('/inject', async (_req, res) => res.json({ r: await injectAllPages() }));
  app.post('/click-voice-call', async (_req, res) => {
    try {
      const page = client.pupPage;
      const box = await page.evaluate(`(() => {
        const b = document.querySelector('button[aria-label="Voice call"]');
        if (!b) return null;
        const r = b.getBoundingClientRect();
        return { x: r.x + r.width / 2, y: r.y + r.height / 2 };
      })()`);
      if (!box) return res.json({ ok: false, err: 'no voice call button' });
      // Real trusted click via CDP — needed so window.open for the call
      // window isn't popup-blocked (element.click() has no user activation).
      await page.mouse.move(box.x, box.y);
      await page.mouse.down(); await sleep(60); await page.mouse.up();
      res.json({ ok: true, clicked: box });
    } catch (e) { res.json({ ok: false, err: e.message }); }
  });
  app.post('/eval-all', async (req, res) => {
    const out = {};
    for (const p of await client.pupBrowser.pages()) {
      try { out[p.url().slice(0, 70)] = await p.evaluate(req.body.js); }
      catch (e) { out[p.url().slice(0, 70)] = 'err:' + e.message.slice(0, 120); }
    }
    res.json(out);
  });
  app.get('/pages', async (_req, res) => {
    const ps = await client.pupBrowser.pages();
    res.json(ps.map((p) => ({ url: p.url(), title: null })));
  });
  app.get('/targets', async (_req, res) => {
    res.json(client.pupBrowser.targets().map((t) => ({ type: t.type(), url: (t.url() || '').slice(0, 90) })));
  });
  app.post('/eval-worker', async (req, res) => {
    const { idx, js } = req.body;
    const ws = client.pupBrowser.targets().filter((t) => /worker|other/.test(t.type()));
    const t = ws[idx];
    if (!t) return res.json({ ok: false, err: 'no such worker', count: ws.length });
    try {
      const w = await t.worker();
      const r = await w.evaluate(js);
      res.json({ ok: true, r });
    } catch (e) { res.json({ ok: false, err: e.message.slice(0, 300) }); }
  });
  app.post('/eval-target', async (req, res) => {
    const { idx, js } = req.body;
    const ts = client.pupBrowser.targets().filter((t) => t.type() !== 'browser');
    const t = ts[idx];
    if (!t) return res.json({ ok: false, err: 'no such target', count: ts.length });
    try {
      const s = await t.createCDPSession();
      const r = await s.send('Runtime.evaluate', { expression: js, returnByValue: true, awaitPromise: true });
      await s.detach();
      res.json({ ok: true, r: r.result && r.result.value, exc: r.exceptionDetails && JSON.stringify(r.exceptionDetails).slice(0, 200) });
    } catch (e) { res.json({ ok: false, err: String(e.message || e).slice(0, 250) }); }
  });
  app.post('/open-chat', async (req, res) => {
    try { await client.interface.openChatWindow(`${req.body.to || TARGET}@c.us`); res.json({ ok: true }); }
    catch (e) { res.json({ ok: false, err: e.message }); }
  });
  app.get('/dom', async (_req, res) => res.json(await dumpCallScreen()));
  app.post('/dial', async (req, res) => {
    const to = req.body.to || TARGET;
    log('DIAL attempt →', to);
    callState = 'dialing';
    res.json(await tryDomDial(to));
  });
  app.post('/dial-api', async (req, res) => {
    // try programmatic call via discovered modules
    const r = await client.pupPage.evaluate(`(() => {
      const tries = [];
      const targets = ${JSON.stringify(req.body.paths || [])};
      for (const p of targets) {
        try {
          const fn = p.split('.').reduce((o, k) => o && o[k], window);
          if (typeof fn === 'function') { tries.push({ path: p, r: String(fn('${TARGET}@c.us')).slice(0, 120) }); }
          else tries.push({ path: p, err: 'not fn' });
        } catch (e) { tries.push({ path: p, err: String(e).slice(0, 200) }); }
      }
      return tries;
    })()`);
    res.json(r);
  });
  app.post('/hangup', async (_req, res) => {
    const r = await client.pupPage.evaluate(`(() => {
      const btns = [...document.querySelectorAll('span[data-icon]')];
      for (const b of btns) {
        const n = (b.getAttribute('data-icon') || '').toLowerCase();
        if (/end|hangup|call-end|decline/.test(n)) { let el = b; for (let i=0;i<6&&el;i++){ if(el.getAttribute('role')==='button'||el.tagName==='BUTTON'||el.getAttribute('tabindex'))break; el=el.parentElement;} (el||b).click(); return { clicked: n }; }
      }
      return { clicked: null };
    })()`);
    callState = 'idle';
    res.json(r);
  });
  app.post('/answer', async (_req, res) => {
    const r = await client.pupPage.evaluate(`(() => {
      const btns = [...document.querySelectorAll('span[data-icon]')];
      for (const b of btns) {
        const n = (b.getAttribute('data-icon') || '').toLowerCase();
        if (/accept|answer|call-accept/.test(n)) { let el=b; for(let i=0;i<6&&el;i++){if(el.getAttribute('role')==='button'||el.tagName==='BUTTON'||el.getAttribute('tabindex'))break;el=el.parentElement;} (el||b).click(); return {clicked:n}; }
      }
      return { clicked: null };
    })()`);
    res.json(r);
  });
  app.post('/fake-mic', async (_req, res) => res.json({ r: await client.pupPage.evaluate('window.__cbInitFakeMic()') }));
  app.post('/mic-push', async (req, res) => {
    const r = await client.pupPage.evaluate(`window.__cbMicPush('${req.body.b64}')`);
    res.json({ queued: r });
  });
  app.post('/mic-play', async (req, res) => {
    // stream a wav file's PCM into the fake mic, chunked in real time
    const file = req.body.file;
    const pcm = wavToPcm(path.resolve(file));
    res.json({ samples: pcm.length, playing: true });
    const CHUNK = 4800; // 100ms @48k
    for (let i = 0; i < pcm.length; i += CHUNK) {
      const b64 = Buffer.from(pcm.buffer, i * 2, Math.min(CHUNK, pcm.length - i) * 2).toString('base64');
      await client.pupPage.evaluate(`window.__cbMicPush('${b64}')`);
      await sleep(100);
    }
  });
  app.post('/record-start', (_req, res) => { remotePcm = []; res.json({ ok: true }); });
  app.post('/record-stop', async (req, res) => {
    const file = path.join(OUT_DIR, req.body.name || `remote-${Date.now()}.wav`);
    const sr = req.body.sr || 48000;
    const pcm = Buffer.concat(remotePcm);
    fs.writeFileSync(file, wavHeader(pcm.length, sr));
    fs.appendFileSync(file, pcm);
    res.json({ file, bytes: pcm.length, seconds: pcm.length / 2 / sr });
  });
  app.get('/shot', async (_req, res) => {
    const f = path.join(OUT_DIR, `shot-${Date.now()}.png`);
    await client.pupPage.screenshot({ path: f });
    res.json({ file: f });
  });
  app.post('/eval', async (req, res) => {
    try {
      const r = await client.pupPage.evaluate(req.body.js);
      res.json({ ok: true, r: r === undefined ? null : r });
    } catch (e) { res.json({ ok: false, err: String(e).slice(0, 500) }); }
  });

  app.listen(PORT, () => log(`spike API on :${PORT}`));
}

function wavHeader(dataLen, sr) {
  const h = Buffer.alloc(44);
  h.write('RIFF', 0); h.writeUInt32LE(36 + dataLen, 4); h.write('WAVE', 8);
  h.write('fmt ', 12); h.writeUInt32LE(16, 16); h.writeUInt16LE(1, 20); h.writeUInt16LE(1, 22);
  h.writeUInt32LE(sr, 24); h.writeUInt32LE(sr * 2, 28); h.writeUInt16LE(2, 32); h.writeUInt16LE(16, 34);
  h.write('data', 36); h.writeUInt32LE(dataLen, 40);
  return h;
}

function wavToPcm(file) {
  const tmp = `/tmp/cb-${Date.now()}.raw`;
  execSync(`ffmpeg -y -i "${file}" -f s16le -ac 1 -ar 48000 "${tmp}" 2>/dev/null`);
  const b = fs.readFileSync(tmp);
  return new Int16Array(b.buffer, b.byteOffset, b.length / 2);
}

// Graceful shutdown — Chrome must flush LocalAuth (IndexedDB) or the linked
// session is lost on the next boot.
let shuttingDown = false;
async function gracefulExit(sig) {
  if (shuttingDown) return;
  shuttingDown = true;
  log('shutdown', sig);
  try { if (client?.pupBrowser) await client.pupBrowser.close(); } catch (_) {}
  setTimeout(() => process.exit(0), 1500);
}
process.on('SIGTERM', () => gracefulExit('SIGTERM'));
process.on('SIGINT', () => gracefulExit('SIGINT'));

main().catch((e) => { console.error('FATAL', e); process.exit(1); });
