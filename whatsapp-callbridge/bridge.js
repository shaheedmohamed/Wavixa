/**
 * Wavixa WhatsApp Call Bridge — production service.
 *
 * A Puppeteer-driven WhatsApp Web session (linked device on 01024520482) that
 * performs real WhatsApp voice calls. Media goes through the page's own audio
 * stack:
 *   - uplink   : CRM agent mic → WS → __cbMicPush → fake-mic MediaStream → WA
 *   - downlink : WA remote audio → AudioNode.connect tap → PCM → WS → CRM
 *
 * HTTP control API (localhost only — Laravel proxies it):
 *   GET  /state
 *   POST /dial {to}
 *   POST /answer | /reject | /hangup | /mute
 *   GET  /events
 *   GET  /shot
 *
 * WS /ws?ticket=uid.exp.hmac  — JSON frames:
 *   server→client {type:'audio', b64, sr}   remote PCM16 mono
 *   server→client {type:'event', event, ...}  call lifecycle events
 *   server→client {type:'state', ...}       full state on connect
 *   client→server {type:'mic', b64, sr}     agent PCM16 mono
 *   client→server {type:'control', action}  dial/answer/reject/hangup/mute
 *
 * Incoming calls are detected from WALOG console lines + DOM poll, then
 * forwarded to the CRM webhook (Laravel) for Reverb broadcast.
 */
const { Client, LocalAuth } = require('whatsapp-web.js');
const express = require('express');
const http = require('http');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const { WebSocketServer } = require('ws');
const qrcode = require('qrcode');

const PHONE = '201024520482';
const AUTH_ROOT = path.join(__dirname, '.auth');
const PORT = 3999;
const CHROME =
  '/var/www/Wavixa/whatsapp-service/.puppeteer_cache/chrome/linux-146.0.7680.31/chrome-linux64/chrome';
const OUT_DIR = path.join(__dirname, 'out');
const WS_SECRET = (() => {
  try { return fs.readFileSync(path.join(__dirname, '.wssecret'), 'utf8').trim(); }
  catch (e) { return null; }
})();
const CRM_WEBHOOK = process.env.CRM_WEBHOOK || 'http://127.0.0.1:8088/api/crm/call/event';
// Same shared key the other Node services use to sign CRM webhooks
// (VerifyServiceSignature middleware — X-Service-Signature HMAC over raw body).
const SERVICE_KEY = (() => {
  try { return fs.readFileSync(path.join(__dirname, '.service-key'), 'utf8').trim(); }
  catch (e) { return null; }
})();
fs.mkdirSync(OUT_DIR, { recursive: true });

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const log = (...a) => console.log(new Date().toISOString(), ...a);

let client = null;
let pairingCode = null;
let currentQr = null;
let sessionReady = false;
// callState: idle | dialing | ringing | connecting | active | incoming | ended
let callState = 'idle';
let callPeer = null;      // remote number/jid
let callMuted = false;
let wsClients = new Set();
let pageSockets = new Set();
let readyAt = 0;
let cspReloadTried = false;
let callDirection = null;      // 'out' | 'in'
let callIsVideo = false;       // true while the current call carries video
let callConnectedAt = 0;       // ms ts when the call went active
let callEventsMutedUntil = 0;  // swallow stale WALOG call lines right after a call ends
let dialStartedAt = 0;
let waOnline = true;           // last observed WA Web socket state (banner check)
let offlineSince = 0;          // ts when the offline banner was first seen
let lastRetryClick = 0;        // last "Retry now" nudge ts

// AudioWorklet source — runs inside the WhatsApp page's audio thread.
// Kept at module level (can't nest a template literal inside HOOKS_SRC).
const CB_WORKLET_SRC = `
  // feed: pulls Float32 chunks posted from the main thread (uplink mic)
  class CBFeed extends AudioWorkletProcessor {
    constructor() {
      super();
      this.q = []; this.last = null; this.g = 0;
      this.port.onmessage = (e) => {
        const c = e.data;
        if (c && c.length) this.q.push(c);
        // ~0.5s cap — drop oldest on runaway backlog
        let total = 0; for (const x of this.q) total += x.length;
        while (total > 8000 && this.q.length) { total -= this.q[0].length; this.q.shift(); }
      };
    }
    process(i, o) {
      const out = o[0][0];
      let idx = 0; const q = this.q;
      while (idx < out.length && q.length) {
        const c = q[0];
        const n = Math.min(c.length, out.length - idx);
        out.set(c.subarray(0, n), idx); idx += n;
        q[0] = c.subarray(n);
        if (!q[0].length) q.shift();
      }
      if (idx === 0 && out.length) {
        // underrun → decaying replay of last block (PLC-lite), else silence
        if (this.last && this.g > 0.05) {
          for (let j = 0; j < out.length; j++) out[j] = this.last[j] * this.g;
          this.g *= 0.85;
        }
      } else if (idx > 0) { this.last = out.slice(); this.g = 0.6; }
      return true;
    }
  }
  // spy: captures input audio into ~85 ms blocks posted to the main thread
  class CBSpy extends AudioWorkletProcessor {
    constructor() { super(); this.buf = new Float32Array(4096); this.n = 0; }
    process(i, o) {
      const f = i[0] && i[0][0];
      if (f) {
        for (let k = 0; k < f.length; k++) {
          this.buf[this.n++] = f[k];
          if (this.n >= this.buf.length) { this.port.postMessage(this.buf.slice(0)); this.n = 0; }
        }
      }
      if (o[0] && o[0][0]) o[0][0].fill(0);
      return true;
    }
  }
  registerProcessor('cb-feed', CBFeed);
  registerProcessor('cb-spy', CBSpy);
`;

// ---------------------------------------------------------------------------
// Page-side hooks — injected via evaluateOnNewDocument AND after ready.
// IMPORTANT: RTCPeerConnection must be a subclass so statics
// (generateCertificate) survive — WhatsApp's SCTP relay path needs it.
// ---------------------------------------------------------------------------
const HOOKS_SRC = `
(() => {
  if (window.__cb) return 'already';
  window.__cb = { events: [], pcs: [], remoteStreams: [], micStreams: [], audioCtxs: [], micQueue: [], micWanted: true };
  const C = window.__cb;
  C.log = (...a) => { try { C.events.push([Date.now(), a.map(x => { try { return typeof x === 'object' ? JSON.stringify(x).slice(0, 300) : String(x); } catch (e) { return '?'; } }).join(' ')]); if (C.events.length > 3000) C.events.shift(); } catch (e) {} };
  C.log('hooks-installed', navigator.userAgent);

  // RTCPeerConnection — subclass keeps statics (generateCertificate) intact
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
          if (ev.track.kind === 'video') { try { window.__cbVideoTap(ev.track); } catch (e) { C.log('vtap-err', String(e)); } }
        });
      }
    }
    window.RTCPeerConnection = HookedPC;
  } catch (e) { C.log('pc-hook-err', String(e)); }

  // ---- AudioWorklet: ScriptProcessor runs on the busy main thread (WA's WASM
  // voip engine + React hog it on this 1-CPU box → gaps). Worklets run on the
  // real-time audio thread — immune to main-thread jank.
  const CB_WORKLET = ${JSON.stringify(CB_WORKLET_SRC)};
  C.ensureWorklet = (ac) => {
    C.worklets = C.worklets || new Map();
    if (!C.worklets.has(ac)) {
      if (!ac.audioWorklet) { C.worklets.set(ac, Promise.reject(new Error('no audioWorklet'))); }
      else {
        const url = URL.createObjectURL(new Blob([CB_WORKLET], { type: 'application/javascript' }));
        C.worklets.set(ac, ac.audioWorklet.addModule(url));
      }
    }
    return C.worklets.get(ac);
  };

  // ---- fake mic: lazy, per-call — MediaStreamAudioDestinationNode track fed
  // from the cb-feed worklet (audio thread). The ctx runs at 16 kHz to match
  // WA's capture constraints and the wire format — zero resampling needed.
  async function buildFakeMic() {
    try {
      if (C.micCtx && C.micCtx.state === 'closed') { C.micCtx = null; C.fakeMicTrack = null; C.micNode = null; }
      if (!C.micCtx) C.micCtx = new AudioContext({ sampleRate: 16000 });
      C.micCtx.resume().catch(() => {});
      if (C.fakeMicTrack && C.fakeMicTrack.readyState === 'live' && C.micNode) return;
      const dest = C.micCtx.createMediaStreamDestination();
      try {
        await C.ensureWorklet(C.micCtx);
        const node = new AudioWorkletNode(C.micCtx, 'cb-feed', { numberOfInputs: 0, numberOfOutputs: 1, outputChannelCount: [1] });
        node.connect(dest);
        C.micNode = node;
        C.micFeed = (i16) => {
          const f = new Float32Array(i16.length);
          for (let i = 0; i < i16.length; i++) f[i] = i16[i] / 32768;
          node.port.postMessage(f);
        };
      } catch (e) {
        // fallback: ScriptProcessor (main thread — janky, but better than nothing)
        C.log('worklet-fallback', String(e).slice(0, 120));
        const proc = C.micCtx.createScriptProcessor(2048, 0, 1);
        proc.onaudioprocess = (ev) => {
          const out = ev.outputBuffer.getChannelData(0);
          const q = C.micQueue;
          let i = 0;
          while (i < out.length && q.length) {
            const chunk = q[0];
            const n = Math.min(chunk.length, out.length - i);
            out.set(chunk.subarray(0, n), i); i += n;
            q[0] = chunk.subarray(n);
            if (!q[0].length) q.shift();
          }
        };
        proc.connect(dest);
        C.micFeed = (i16) => {
          const f = new Float32Array(i16.length);
          for (let i = 0; i < i16.length; i++) f[i] = i16[i] / 32768;
          C.micQueue.push(f);
          if (C.micQueue.length > 250) C.micQueue.splice(0, C.micQueue.length - 250);
        };
      }
      C.fakeMicTrack = dest.stream.getAudioTracks()[0];
      C.log('fake-mic-built', 'track=' + C.fakeMicTrack.id, 'ctx=' + C.micCtx.state, 'sr=' + C.micCtx.sampleRate);
    } catch (e) { C.log('fake-mic-err', String(e)); }
  }
  window.__cbBuildFakeMic = buildFakeMic;

  // ---- fake camera: canvas.captureStream painted with JPEG frames pushed
  // from the CRM agent's webcam over the audio WS (tag 0x02). captureStream
  // only emits when the canvas changes → repaint on a timer.
  async function buildFakeCam() {
    try {
      if (C.fakeCamTrack && C.fakeCamTrack.readyState === 'live') return;
      C.camCanvas = C.camCanvas || document.createElement('canvas');
      C.camCanvas.width = 640; C.camCanvas.height = 360;
      const ctx = C.camCanvas.getContext('2d');
      ctx.fillStyle = '#0b141a'; ctx.fillRect(0, 0, 640, 360);
      C.camStream = C.camCanvas.captureStream(15);
      C.fakeCamTrack = C.camStream.getVideoTracks()[0];
      if (!C.camPaintTimer) C.camPaintTimer = setInterval(() => {
        try {
          if (C.camLastFrame) { ctx.drawImage(C.camLastFrame, 0, 0, 640, 360); }
          else {
            ctx.fillStyle = '#0b141a'; ctx.fillRect(0, 0, 640, 360);
            ctx.fillStyle = '#8696a0'; ctx.font = '30px sans-serif';
            ctx.fillText('Wavixa', 270, 185);
          }
        } catch (e) {}
      }, 50);
      C.camPush = (u8) => {
        createImageBitmap(new Blob([u8], { type: 'image/jpeg' })).then((bmp) => {
          const old = C.camLastFrame; C.camLastFrame = bmp;
          try { ctx.drawImage(bmp, 0, 0, 640, 360); } catch (e) {}
          if (old && old.close) old.close();
        }).catch(() => {});
      };
      // H.264 camera path — the CRM sends annexb chunks (tag 0x03); decode on
      // the page and paint straight to the fake-cam canvas (smooth 20fps vs
      // the choppy JPEG trickle)
      if (typeof VideoDecoder === 'function') {
        try {
          const dec = new VideoDecoder({
            output: (frame) => {
              try { ctx.drawImage(frame, 0, 0, 640, 360); } catch (e) {}
              const old = C.camLastFrame;
              C.camLastFrame = frame;
              if (old && old.close) { try { old.close(); } catch (_) {} }
            },
            error: (e) => C.log('camdec-err', String(e)),
          });
          dec.configure({ codec: 'avc1.42001f', optimizeForLatency: true });
          let gotKey = false;
          C.camDec = (u8) => {
            try {
              const flags = u8[0];
              if (!gotKey && !(flags & 1)) return; // wait for a keyframe
              const ts = Number(new DataView(u8.buffer, u8.byteOffset).getBigUint64(1));
              dec.decode(new EncodedVideoChunk({ type: flags & 1 ? 'key' : 'delta', timestamp: ts, data: u8.subarray(9) }));
              if (flags & 1) gotKey = true;
            } catch (e) {}
          };
          C.log('cam-decoder h264');
        } catch (e) { C.log('camdec-init-err', String(e)); }
      }
      C.log('fake-cam-built', 'track=' + C.fakeCamTrack.id);
    } catch (e) { C.log('fake-cam-err', String(e)); }
  }
  window.__cbBuildFakeCam = buildFakeCam;

  // ---- remote video tap: attach the incoming track to a hidden <video>,
  // snapshot to JPEG ~11fps and ship over the WS (tag 0x02)
  window.__cbVideoTap = (track) => {
    if (C.fakeCamTrack && track.id === C.fakeCamTrack.id) return; // our own cam (self-view)
    if (C.camStream && C.camStream.getVideoTracks().includes(track)) return;
    if (C.videoTapId === track.id) return;
    C.videoTapId = track.id;
    const v = document.createElement('video');
    v.muted = true; v.playsInline = true; v.autoplay = true;
    v.srcObject = new MediaStream([track]);
    v.play().catch(() => {});
    const cv = new OffscreenCanvas(640, 360);
    const cx = cv.getContext('2d');
    let last = 0, stopped = false;
    const tick = async () => {
      if (stopped || track.readyState !== 'live') return;
      const now = Date.now();
      if (now - last > 90 && v.videoWidth && v.readyState >= 2) {
        last = now;
        try {
          const w = v.videoWidth, h = v.videoHeight;
          const sc = Math.min(640 / w, 360 / h);
          const dw = Math.max(2, Math.round(w * sc)), dh = Math.max(2, Math.round(h * sc));
          cx.fillStyle = '#000'; cx.fillRect(0, 0, 640, 360);
          cx.drawImage(v, (640 - dw) >> 1, (360 - dh) >> 1, dw, dh);
          const blob = await cv.convertToBlob({ type: 'image/jpeg', quality: 0.55 });
          C.sendVideoOut(new Uint8Array(await blob.arrayBuffer()));
        } catch (e) {}
      }
      setTimeout(tick, 50);
    };
    track.onended = () => { stopped = true; C.videoTapId = null; C.log('video-tap-end'); };
    tick();
    C.log('video-tap', track.id);
    // tell Node so incoming video calls flip the CRM UI to video mode too
    try { C.audioWs && C.audioWs.readyState === 1 && C.audioWs.send(JSON.stringify({ type: 'video-track' })); } catch (e) {}
    // H.264 path replaces the JPEG trick above — much smoother (real video
    // compression @20fps, ~350 kbps) when WebCodecs is available
    if (typeof VideoEncoder === 'function') {
      try { stopped = true; } catch (e) {} // stop the JPEG loop — h264 owns it now
      try {
        const enc = new VideoEncoder({
          output: (chunk) => {
            try {
              if (!C.audioWs || C.audioWs.readyState !== 1) return;
              const b = new Uint8Array(chunk.byteLength);
              chunk.copyTo(b);
              const f = new Uint8Array(b.length + 10);
              f[0] = 3; // tag: h264 video
              f[1] = chunk.type === 'key' ? 1 : 0;
              new DataView(f.buffer).setBigUint64(2, BigInt(chunk.timestamp || 0));
              f.set(b, 10);
              C.audioWs.send(f.buffer);
            } catch (e) {}
          },
          error: (e) => C.log('venc-err', String(e)),
        });
        enc.configure({ codec: 'avc1.42001f', width: 640, height: 360, bitrate: 350000, framerate: 20, latencyMode: 'realtime', avc: { format: 'annexb' } });
        let n = 0;
        const h264tick = () => {
          if (track.readyState !== 'live') { try { enc.close(); } catch (e) {} return; }
          try {
            if (v.videoWidth && v.readyState >= 2 && enc.state === 'configured' && enc.encodeQueueSize < 8) {
              enc.encode(new VideoFrame(v, { timestamp: Math.round(performance.now() * 1000) }), { keyFrame: n++ % 40 === 0 });
            }
          } catch (e) {}
          setTimeout(h264tick, 50); // ~20fps
        };
        h264tick();
        C.log('video-tap h264');
      } catch (e) { C.log('venc-init-err', String(e)); }
    }
  };

  // getUserMedia — serve the fake mic/cam when enabled
  try {
    const _gum = navigator.mediaDevices.getUserMedia.bind(navigator.mediaDevices);
    navigator.mediaDevices.getUserMedia = async (constraints) => {
      C.log('gum-request', constraints);
      if (constraints && (constraints.audio || constraints.video) && C.micWanted) {
        const tracks = [];
        if (constraints.audio) {
          await buildFakeMic();
          if (C.fakeMicTrack && C.fakeMicTrack.readyState === 'live') tracks.push(C.fakeMicTrack);
        }
        if (constraints.video) {
          await buildFakeCam();
          if (C.fakeCamTrack && C.fakeCamTrack.readyState === 'live') tracks.push(C.fakeCamTrack);
        }
        if (tracks.length) {
          const s = new MediaStream(tracks);
          C.micStreams.push(s);
          C.log('gum-served-fake', constraints.video ? 'av' : 'a');
          return s;
        }
      }
      const s = await _gum(constraints);
      C.micStreams.push(s);
      return s;
    };
  } catch (e) { C.log('gum-hook-err', String(e)); }

  // enumerateDevices — headless boxes may have zero audio devices; WA checks
  // this list before starting a call. Report virtual mic + speaker.
  try {
    const _ed = navigator.mediaDevices.enumerateDevices.bind(navigator.mediaDevices);
    navigator.mediaDevices.enumerateDevices = async () => {
      const real = await _ed().catch(() => []);
      const mk = (kind, label, id) => ({ deviceId: id, kind, label, groupId: 'cb-' + kind, toJSON() { return { deviceId: this.deviceId, kind: this.kind, label: this.label, groupId: this.groupId }; } });
      const fake = [mk('audioinput', 'Wavixa Virtual Mic', 'default'), mk('audiooutput', 'Wavixa Virtual Speaker', 'default'), mk('audiooutput', 'Communications', 'communications'), mk('videoinput', 'Wavixa Virtual Cam', 'default')];
      const seen = new Set(real.map(d => d.kind + ':' + d.deviceId));
      return [...real, ...fake.filter(f => !seen.has(f.kind + ':' + f.deviceId))];
    };
  } catch (e) { C.log('enum-hook-err', String(e)); }

  // AudioContext — catch WebAudio-based remote audio
  try {
    const _AC = window.AudioContext;
    window.AudioContext = class extends _AC {
      constructor(...a) { super(...a); C.audioCtxs.push(this); }
    };
    const _cmss = _AC.prototype.createMediaStreamSource;
    _AC.prototype.createMediaStreamSource = function (s) {
      C.log('webaudio-ms-source', s.getTracks().map(t => t.kind + ':' + t.readyState).join(','));
      // don't tap our own injected mic stream — that would echo the agent's
      // voice straight back into the downlink
      const isOurs = C.micStreams.includes(s) || (C.fakeMicTrack && s.getAudioTracks()[0] && s.getAudioTracks()[0].id === C.fakeMicTrack.id);
      if (!isOurs) {
        C.remoteStreams.push(s);
        try { window.__cbTap(s); } catch (e) { C.log('tap-err', String(e)); }
      }
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
            const vt = v.getVideoTracks && v.getVideoTracks()[0];
            if (vt) { try { window.__cbVideoTap(vt); } catch (e) { C.log('vtap-err', String(e)); } }
          }
          return desc.set.call(this, v);
        }
      });
    }
  } catch (e) { C.log('srcobj-hook-err', String(e)); }

  // ---- direct audio WebSocket: page <-> bridge, binary PCM16 mono @16 kHz.
  // CDP (evaluate/exposeFunction) is far too slow for per-buffer audio — the
  // WS carries every chunk with ~0 overhead.
  C.sendAudioOut = (i16) => {
    if (C.audioWs && C.audioWs.readyState === 1) {
      try {
        const src = new Uint8Array(i16.buffer, i16.byteOffset, i16.byteLength);
        const f = new Uint8Array(src.length + 1);
        f[0] = 1; f.set(src, 1);
        C.audioWs.send(f.buffer);
        return;
      } catch (e) {}
    }
    // fallback: legacy binding path (rarely hit)
    const b = new Uint8Array(i16.buffer); let bin = '';
    for (let i = 0; i < b.length; i++) bin += String.fromCharCode(b[i]);
    try { window.__cbAudioOut(btoa(bin), 16000); } catch (err) {}
  };
  C.sendVideoOut = (u8) => {
    if (C.audioWs && C.audioWs.readyState === 1) {
      try {
        const f = new Uint8Array(u8.length + 1);
        f[0] = 2; f.set(u8, 1);
        C.audioWs.send(f.buffer);
      } catch (e) {}
    }
  };
  // float32 @srcRate -> Int16 @16000, linear interp
  window.__cbDownmix = (f, srcRate) => {
    const dst = 16000;
    if (srcRate === dst) {
      const i16 = new Int16Array(f.length);
      for (let i = 0; i < f.length; i++) { const s = Math.max(-1, Math.min(1, f[i])); i16[i] = s < 0 ? s * 0x8000 : s * 0x7fff; }
      return i16;
    }
    const ratio = srcRate / dst;
    const outLen = Math.floor(f.length / ratio);
    const i16 = new Int16Array(outLen);
    for (let i = 0; i < outLen; i++) {
      const pos = i * ratio;
      const i0 = Math.min(f.length - 1, Math.floor(pos));
      const i1 = Math.min(f.length - 1, i0 + 1);
      const v = f[i0] + (f[i1] - f[i0]) * (pos - i0);
      const s = Math.max(-1, Math.min(1, v));
      i16[i] = s < 0 ? s * 0x8000 : s * 0x7fff;
    }
    return i16;
  };
  // Int16 @16k -> Float32 @dstRate (mic feed side)
  window.__cbUpmix = (i16, dstRate) => {
    const src = 16000;
    const ratio = src / dstRate;
    const outLen = Math.round(i16.length / ratio);
    const f = new Float32Array(outLen);
    for (let i = 0; i < outLen; i++) {
      const pos = i * ratio;
      const i0 = Math.min(i16.length - 1, Math.floor(pos));
      const i1 = Math.min(i16.length - 1, i0 + 1);
      const fr = pos - i0;
      f[i] = (i16[i0] + (i16[i1] - i16[i0]) * fr) / 32768;
    }
    return f;
  };
  window.__cbStartAudioWs = (url) => {
    C.audioWsUrl = url;
    if (C.audioWs && (C.audioWs.readyState === 0 || C.audioWs.readyState === 1)) return 'open';
    try {
      const ws = new WebSocket(url);
      C.audioWs = ws;
      ws.binaryType = 'arraybuffer';
      ws.onopen = () => C.log('audio-ws-open');
      ws.onmessage = (ev) => {
        // binary uplink: [0x01|PCM16 mic] [0x02|JPEG camera frame]; a raw
        // buffer without a tag is legacy PCM16 mic audio.
        if (typeof ev.data === 'string') return;
        const u8 = new Uint8Array(ev.data);
        if (u8[0] === 2) { if (C.camPush) C.camPush(u8.slice(1)); return; }
        if (u8[0] === 3) { if (C.camDec) C.camDec(u8.subarray(1)); return; }
        const pcmBuf = u8[0] === 1 ? u8.slice(1).buffer : ev.data;
        const i16 = new Int16Array(pcmBuf);
        if (C.micFeed) { C.micFeed(i16); return; }
        if (!C.micCtx) buildFakeMic();
        const f = window.__cbUpmix(i16, (C.micCtx && C.micCtx.sampleRate) || 48000);
        C.micQueue.push(f);
        if (C.micQueue.length > 250) C.micQueue.splice(0, C.micQueue.length - 250);
      };
      ws.onclose = (ev) => {
        C.log('audio-ws-close', ev.code);
        C.audioWs = null;
        if (ev.code === 4401) {
          // ticket expired — fetch a fresh URL from Node once, then retry
          try { window.__cbWsTicket && window.__cbWsTicket().then(u => { C.audioWsUrl = u; setTimeout(() => window.__cbStartAudioWs(C.audioWsUrl), 1500); }); } catch (e) {}
        } else {
          setTimeout(() => window.__cbStartAudioWs(C.audioWsUrl), 2000);
        }
      };
      ws.onerror = () => {};
      return 'connecting';
    } catch (e) { return 'err:' + String(e); }
  };

  // remote stream → PCM tap (cb-spy worklet on the audio thread; falls back
  // to ScriptProcessor only if the worklet can't load)
  window.__cbTap = (stream) => {
    const audioTrack = stream.getAudioTracks()[0];
    if (!audioTrack) { C.log('tap-no-audio'); return; }
    if (C.tappedTrackId === audioTrack.id) { return; }
    C.tappedTrackId = audioTrack.id;
    const ac = C.tapCtx || (C.tapCtx = new AudioContext());
    const src = ac.createMediaStreamSource(stream);
    const handleBlock = (f) => {
      // the remote-track tap is the preferred downlink source once it carries
      // real audio; destination taps act as fallback to avoid duplicate sends
      let peak = 0;
      for (let i = 0; i < f.length; i += 16) { const a = f[i] < 0 ? -f[i] : f[i]; if (a > peak) peak = a; }
      if (peak > 0.001) { C.trackTapIsSource = true; C.trackTapQuietAt = 0; }
      else if (C.trackTapIsSource) {
        if (!C.trackTapQuietAt) C.trackTapQuietAt = Date.now();
        if (Date.now() - C.trackTapQuietAt > 5000) { C.trackTapIsSource = false; C.log('track-tap released source'); }
      }
      if (!C.trackTapIsSource) return;
      C.sendAudioOut(window.__cbDownmix(f, ac.sampleRate));
    };
    C.ensureWorklet(ac).then(() => {
      const spy = new AudioWorkletNode(ac, 'cb-spy', { numberOfInputs: 1, numberOfOutputs: 1, outputChannelCount: [1] });
      spy.port.onmessage = (e) => handleBlock(e.data);
      src.connect(spy);
      spy.connect(ac.destination); // spy emits silence — keeps it pulled
      C.log('tap-installed worklet', 'sr=' + ac.sampleRate, 'track=' + audioTrack.id);
    }).catch(() => {
      const proc = ac.createScriptProcessor(2048, 1, 1);
      const gain = ac.createGain();
      gain.gain.value = 0;
      src.connect(proc);
      proc.connect(gain);
      gain.connect(ac.destination);
      proc.onaudioprocess = (e) => handleBlock(e.inputBuffer.getChannelData(0));
      C.log('tap-installed fallback', 'sr=' + ac.sampleRate, 'track=' + audioTrack.id);
    });
  };

  // mic push — legacy b64 path; routes into the worklet feed when available
  window.__cbMicPush = (b64, srcRate) => {
    if (!C.micCtx) buildFakeMic();
    const bin = atob(b64);
    const n = bin.length >> 1;
    const i16 = new Int16Array(n);
    for (let i = 0; i < n; i++) i16[i] = (bin.charCodeAt(i * 2) | (bin.charCodeAt(i * 2 + 1) << 8)) << 16 >> 16;
    if (C.micFeed && (!srcRate || srcRate === 16000)) { C.micFeed(i16); return -1; }
    const dst = (C.micCtx && C.micCtx.sampleRate) || 48000;
    const f = window.__cbUpmix(i16, dst);
    C.micQueue.push(f);
    if (C.micQueue.length > 200) C.micQueue.splice(0, C.micQueue.length - 200);
    return C.micQueue.length;
  };
  window.__cbMicMute = (m) => { C.micMuted = !!m; if (C.fakeMicTrack) C.fakeMicTrack.enabled = !m; return m; };

  // WALOG tap — installed lazily once WA's module registry is up; mirrors every
  // voip/call log line to console so the Node side can track call lifecycle.
  window.__cbInstallWalog = () => {
    if (C.walogDone) return 'already';
    if (typeof require !== 'function') return 'no-require';
    try {
      const LG = require('WALogger');
      const fmt = (a) => {
        const s = a[0] && a[0].raw ? a[0].raw : a[0];
        const arr = Array.isArray(s) ? s : [s];
        let out = '';
        for (let i = 0; i < arr.length; i++) {
          out += arr[i];
          if (i < a.length - 1) {
            const x = a[i + 1];
            out += x instanceof Error ? (x.name + ': ' + x.message) : (typeof x === 'object' ? JSON.stringify(x).slice(0, 300) : String(x));
          }
        }
        return out;
      };
      for (const k of ['LOG', 'WARN', 'ERROR']) {
        const orig = LG[k];
        if (typeof orig !== 'function' || orig.__cb) continue;
        const wrapped = function (...a) {
          try { const m = fmt(a); if (/voip|call|wt|transport|sctp|relay/i.test(m)) console.log('[VALOG:' + k + ']', m.slice(0, 500)); } catch (e) {}
          return orig.apply(this, a);
        };
        wrapped.__cb = 1;
        LG[k] = wrapped;
      }
      C.walogDone = true;
      return 'walog-installed';
    } catch (e) { return 'err:' + String(e).slice(0, 120); }
  };

  return 'installed';
})()
`;

// Destination tap — capture every AudioContext's final mix (catches remote
// audio even when WA renders straight to the destination).
const DEST_TAP_SRC = `
(() => {
  const C = window.__cb; if (!C || C.destTapDone) return 'skip';
  C.destTapDone = true;
  const _conn = AudioNode.prototype.connect;
  C.taps = C.taps || new Map();
  function tapFor(ac) {
    if (C.taps.has(ac)) return C.taps.get(ac);
    const node = { pending: true };
    const handler = (f) => {
      if (C.trackTapIsSource) return; // remote-track tap owns the downlink
      // multiple destination taps exist (WA uses several AudioContexts) —
      // first tap to see real audio claims the downlink until it goes quiet
      let peak = 0;
      for (let i = 0; i < f.length; i += 16) { const a = f[i] < 0 ? -f[i] : f[i]; if (a > peak) peak = a; }
      if (peak > 0.001 && (!C.destOwner || C.destOwner === node || Date.now() - (C.destOwnerHot || 0) > 500)) {
        C.destOwner = node;
      }
      if (C.destOwner === node) C.destOwnerHot = Date.now();
      if (C.destOwner !== node) return;
      C.sendAudioOut(window.__cbDownmix(f, ac.sampleRate));
    };
    C.taps.set(ac, node);
    C.ensureWorklet(ac).then(() => {
      const spy = new AudioWorkletNode(ac, 'cb-spy', { numberOfInputs: 1, numberOfOutputs: 1, outputChannelCount: [1] });
      spy.port.onmessage = (e) => handler(e.data);
      _conn.call(spy, ac.destination); // spy outputs silence; keeps it pulled
      node.spy = spy;
      C.log('dest-tap worklet sr=' + ac.sampleRate);
    }).catch(() => {
      const proc = ac.createScriptProcessor(2048, 1, 1);
      const gain = ac.createGain(); gain.gain.value = 0;
      proc.onaudioprocess = (e) => handler(e.inputBuffer.getChannelData(0));
      _conn.call(proc, gain); _conn.call(gain, ac.destination);
      node.spy = proc;
      C.log('dest-tap fallback sr=' + ac.sampleRate);
    });
    return node;
  }
  AudioNode.prototype.connect = function (dest, ...rest) {
    try {
      if (dest && dest.constructor && dest.constructor.name === 'AudioDestinationNode') {
        const node = tapFor(dest.context);
        // connect through the spy once loaded — audio path unchanged otherwise
        _conn.call(this, dest, ...rest);
        const wire = () => {
          if (node.spy) { try { _conn.call(this, node.spy, ...rest); } catch (e) {} }
          else setTimeout(wire, 300);
        };
        wire();
        return dest;
      }
    } catch (e) { C.log('conn-hook-err', String(e)); }
    return _conn.call(this, dest, ...rest);
  };
  return 'ok';
})()
`;

// Public WS URL the WhatsApp page uses for its direct audio socket (TLS via nginx)
const WSS_PUBLIC = process.env.BRIDGE_PUBLIC_WS || 'wss://wavixa.net/call-ws';

function makeTicket(uid, ttlMs) {
  const exp = Date.now() + ttlMs;
  const sig = crypto.createHmac('sha256', WS_SECRET).update(`${uid}.${exp}`).digest('hex');
  return `${uid}.${exp}.${sig}`;
}
function pageWsUrl() { return `${WSS_PUBLIC}?ticket=${makeTicket(0, 24 * 3600e3)}&role=page`; }

async function injectHooks(page) {
  try {
    // WA's CSP connect-src blocks our page-side audio WebSocket — bypass it.
    await page.setBypassCSP(true).catch(() => {});
    await page.evaluateOnNewDocument(HOOKS_SRC);
    await page.evaluateOnNewDocument(DEST_TAP_SRC);
    const r = await page.evaluate(HOOKS_SRC);
    const d = await page.evaluate(DEST_TAP_SRC);
    await page.exposeFunction('__cbAudioOut', (b64, sr) => {
      if (callState === 'active' || callState === 'connecting' || callState === 'dialing' || callState === 'ringing' || callState === 'incoming') {
        broadcast({ type: 'audio', b64, sr });
      }
    }).catch(() => {});
    await page.exposeFunction('__cbWsTicket', () => pageWsUrl()).catch(() => {});
    // hand the page its direct audio WS URL
    await page.evaluate(`window.__cbStartAudioWs && window.__cbStartAudioWs(${JSON.stringify(pageWsUrl())})`).catch(() => {});
    return r + '|' + d;
  } catch (e) { return 'err:' + e.message.slice(0, 150); }
}

async function injectAllPages() {
  const results = {};
  try {
    for (const p of await client.pupBrowser.pages()) {
      const url = p.url();
      if (!/web\.whatsapp\.com/.test(url)) continue;
      results[url.slice(0, 60)] = await injectHooks(p);
    }
  } catch (e) { results.err = e.message; }
  return results;
}

// ---------------------------------------------------------------------------
// Events / state
// ---------------------------------------------------------------------------
function setCallState(s, extra = {}) {
  if (callState === s) return;
  const prev = callState;
  callState = s;
  if (s === 'active') callConnectedAt = Date.now();
  log('callState →', s, JSON.stringify(extra));
  broadcast({ type: 'event', event: 'state', callState: s, ...extra });
  const payload = { event: 'state', callState: s, peer: callPeer ?? extra.peer, prevState: prev, video: callIsVideo, ...extra };
  if (s === 'idle' && prev !== 'idle') {
    // attach call metadata so the CRM can write a chat call-log bubble
    payload.direction = callDirection;
    payload.answered = !!callConnectedAt;
    payload.duration = callConnectedAt ? Math.round((Date.now() - callConnectedAt) / 1000) : 0;
    callDirection = null; callConnectedAt = 0; callIsVideo = false;
    callEventsMutedUntil = Date.now() + 8000; // WA replays stale call stanzas
  }
  webhook(payload);
}

function broadcast(msg) {
  const data = JSON.stringify(msg);
  for (const ws of wsClients) { try { ws.send(data); } catch (e) {} }
}

async function webhook(payload) {
  try {
    const body = JSON.stringify({ ...payload, bridgePhone: PHONE, ts: Date.now() });
    const sig = SERVICE_KEY ? crypto.createHmac('sha256', SERVICE_KEY).update(body).digest('hex') : '';
    const r = await fetch(CRM_WEBHOOK, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Service-Signature': sig },
      body,
    });
    if (!r.ok && payload.event === 'state') log('webhook', r.status, payload.callState);
  } catch (e) { if (payload.event === 'state') log('webhook err', e.message); }
}

// Console line → lifecycle events
function onConsoleLine(text) {
  const t = text;
  // WA replays stale call stanzas (terminate/active from the *previous* call)
  // for a few seconds after hangup — swallow them so they can't resurrect a
  // dead call or clear the next call's peer.
  if (Date.now() < callEventsMutedUntil) return;
  if (/call_state_changed:ReceivedCall|Handle MESSAGE Offer|incoming call|call offer/i.test(t) && callState === 'idle') {
    callState = 'incoming';
    callDirection = 'in';
    // best-effort peer extraction: "Offer from:NNNN:dd@lid" or DOM later
    const m = t.match(/from:([0-9]+)/);
    callPeer = m ? m[1] : callPeer;
    broadcast({ type: 'event', event: 'incoming', peer: callPeer });
    webhook({ event: 'incoming', peer: callPeer });
    return;
  }
  if (/call_state_changed:CallActive|state CallActive/i.test(t)) return setCallState('active');
  if (/call_state_changed:PreacceptReceived/i.test(t) && callState !== 'active') return setCallState('ringing');
  if (/start call|starting 1:1 call/i.test(t) && callState === 'idle') return setCallState('dialing');
  if (/call terminate received|Call terminate received|-> None\]|call_state_changed:None/i.test(t)) {
    // a terminate while we're still dialling (first ~15s) is almost always a
    // replayed stanza from the previous call — a real call can't end before
    // it's placed
    if (callState === 'dialing' && Date.now() - dialStartedAt < 15000) return;
    if (callState !== 'idle') { const peer = callPeer; callPeer = null; callMuted = false; setCallState('idle', { reason: 'terminated', peer }); }
  }
}

// ---------------------------------------------------------------------------
// DOM call control
// ---------------------------------------------------------------------------
async function openChatFor(to) {
  // to can be E.164 digits, a full jid, or a bare group id (g.us)
  const jid = /@/.test(to) ? to
    : (/^120363\d+/.test(to) ? `${to}@g.us` : `${to}@c.us`);
  const page = client.pupPage;
  const chatOpen = async () => page.evaluate(
    `!!document.querySelector('#main header')`
  ).catch(() => false);
  for (let i = 0; i < 4; i++) {
    try { await client.interface.openChatWindow(jid); } catch (e) { log('openChatWindow err', e.message); }
    for (let w = 0; w < 8; w++) { await sleep(700); if (await chatOpen()) return; }
  }
  log('openChatFor: chat did not open for', jid);
}

const CLICK_CALL_JS = `(kind) => (() => {
  const wantAria = kind === 'voice' ? /voice call|audio call/i : /video call/i;
  const wantIcon = kind === 'voice'
    ? /audio-call|voice-call|(^|-)call(-|$)|ic-call|phone(?!-call)/
    : /video-call|ic-videocam/;
  const isVideo = (s) => /video|videocam/.test(s);
  const clickIt = (el) => {
    let t = el;
    for (let i = 0; i < 6 && t; i++) { if (t.getAttribute('role') === 'button' || t.tagName === 'BUTTON' || t.getAttribute('tabindex')) break; t = t.parentElement; }
    (t || el).click();
  };
  const seen = [];
  // current WA Web: header buttons expose aria-label "Voice call"/"Video call"
  for (const el of document.querySelectorAll('#main header [aria-label], #main header button, header button')) {
    const a = (el.getAttribute('aria-label') || '').toLowerCase();
    if (!a) continue;
    seen.push(a);
    if (wantAria.test(a) && (kind === 'voice' ? !/video/.test(a) : true)) { clickIt(el); return { clicked: 'aria:' + a }; }
  }
  // legacy layout: span[data-icon]
  for (const icon of document.querySelectorAll('#main header span[data-icon], header span[data-icon]')) {
    const n = (icon.getAttribute('data-icon') || '').toLowerCase();
    seen.push(n);
    if (wantIcon.test(n) && (kind === 'voice' ? !isVideo(n) : true)) { clickIt(icon); return { clicked: n }; }
  }
  return { clicked: null, icons: [...new Set(seen)].slice(0, 60) };
})()`;

async function domClickCall(kind) {
  const page = client.pupPage;
  return await page.evaluate(`(${CLICK_CALL_JS})('${kind}')`);
}

// Current WA Web removed per-chat call buttons — calls are placed through
// "New group call": the ⋮ menu opens a participant picker pre-seeded with the
// open chat's contact and finishes on a green Voice/Video button.
async function domOpenCallPicker() {
  const page = client.pupPage;
  return await page.evaluate(`(async () => {
    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
    const clickIt = (el) => {
      let t = el;
      for (let i = 0; i < 6 && t; i++) { if (t.getAttribute('role') === 'button' || t.tagName === 'BUTTON' || t.getAttribute('tabindex')) break; t = t.parentElement; }
      (t || el).click();
    };
    const pickerOpen = () => [...document.querySelectorAll('button')].some((b) => /^(voice|video)$/i.test((b.innerText || '').trim()));
    if (pickerOpen()) return { ok: true, already: true };
    const header = document.querySelector('#main header');
    if (!header) return { err: 'no-chat-header' };
    const menuBtn = [...header.querySelectorAll('[aria-label]')].find((e) => /^(menu|options)$/i.test((e.getAttribute('aria-label') || '').trim()));
    if (!menuBtn) return { err: 'no-menu-button' };
    clickIt(menuBtn);
    await sleep(700);
    const item = [...document.querySelectorAll('[role=menuitem], li[role=button], li')]
      .find((e) => /new group call|group call|voice call|video call/i.test((e.innerText || '') + ' ' + (e.getAttribute('aria-label') || '')));
    if (!item) { document.body.click(); return { err: 'no-call-menu-item' }; }
    clickIt(item);
    for (let i = 0; i < 10; i++) { await sleep(500); if (pickerOpen()) return { ok: true }; }
    return { err: 'picker-not-open' };
  })()`);
}

async function domPickerMemberCount() {
  const page = client.pupPage;
  return await page.evaluate(`document.querySelectorAll('[aria-label*="Remove member"], [aria-label*="إزالة"]').length`).catch(() => 0);
}

// The picker pre-adds the open chat's contact. If it came up empty (chat
// didn't open, unsaved number), search the raw number and tick the first
// result. For groups, tick member rows (a group call is meant for members).
async function domPickerEnsureMembers(searchTerm, isGroup) {
  const page = client.pupPage;
  if ((await domPickerMemberCount()) > 0) return true;
  if (searchTerm && !isGroup) {
    const focused = await page.evaluate(`(() => {
      const inp = document.querySelector('[aria-label="Search name, number or @username"], [aria-label*="Search name"]');
      if (!inp) return false; inp.focus(); return true;
    })()`).catch(() => false);
    if (focused) {
      try { await page.keyboard.type(String(searchTerm), { delay: 30 }); } catch (e) {}
      await sleep(1500);
      await page.evaluate(`(() => {
        const boxes = [...document.querySelectorAll('input[type=checkbox]')];
        const row = boxes[0];
        if (!row) return;
        let t = row; for (let i = 0; i < 5 && t; i++) { if (t.getAttribute('role') === 'button' || t.tagName === 'LI') break; t = t.parentElement; }
        (t || row).click();
      })()`).catch(() => {});
      await sleep(600);
    }
  }
  if (isGroup && (await domPickerMemberCount()) === 0) {
    // group call: select member rows (WhatsApp caps group calls at 32)
    await page.evaluate(`(() => {
      let n = 0;
      for (const box of document.querySelectorAll('input[type=checkbox]')) {
        if (n >= 32) break;
        let t = box; for (let i = 0; i < 5 && t; i++) { if (t.getAttribute('role') === 'button' || t.tagName === 'LI') break; t = t.parentElement; }
        (t || box).click(); n++;
      }
    })()`).catch(() => {});
    await sleep(800);
  }
  return (await domPickerMemberCount()) > 0;
}

async function domPickerClickCall(kind) {
  const page = client.pupPage;
  return await page.evaluate(`(() => {
    const want = ${kind === 'voice' ? '/^\\s*voice\\s*$/i' : '/^\\s*video\\s*$/i'};
    for (const b of document.querySelectorAll('button')) {
      const t = (b.innerText || '').trim();
      if (want.test(t)) { b.click(); return { clicked: t }; }
    }
    return { clicked: null };
  })()`);
}

async function domClosePicker() {
  const page = client.pupPage;
  try { await page.keyboard.press('Escape'); } catch (e) {}
  await page.evaluate(`(() => {
    const x = [...document.querySelectorAll('[aria-label="Close"], [aria-label="Back"]')][0];
    if (x) { let t = x; for (let i = 0; i < 6 && t; i++) { if (t.tagName === 'BUTTON') break; t = t.parentElement; } (t || x).click(); }
  })()`).catch(() => {});
}

async function domClickButton(pred) {
  // pred runs in page; finds first clickable element matching and clicks it.
  // WA Web opens calls in a separate window — the End/Mute buttons live
  // there, not in the main page. Sweep every open page.
  const js = `(() => {
    const want = ${pred};
    for (const el of document.querySelectorAll('[aria-label], span[data-icon], button, [role=button]')) {
      const a = (el.getAttribute('aria-label') || '') + ' ' + (el.getAttribute('data-icon') || '');
      if (want.test(a.toLowerCase())) {
        let t = el;
        for (let i = 0; i < 6 && t; i++) { if (t.getAttribute('role') === 'button' || t.tagName === 'BUTTON' || t.getAttribute('tabindex')) break; t = t.parentElement; }
        (t || el).click();
        return { clicked: a.trim().slice(0, 80) };
      }
    }
    return { clicked: null };
  })()`;
  try {
    for (const p of await client.pupBrowser.pages()) {
      if (!/whatsapp\.com|web\.whatsapp/i.test(p.url()) && p !== client.pupPage) continue;
      try {
        const r = await p.evaluate(js);
        if (r && r.clicked) return r;
      } catch (e) { /* page navigated — skip it */ }
    }
  } catch (e) {}
  return { clicked: null };
}

async function ensureFakeMic() {
  // fake-mic builds lazily inside getUserMedia; nothing needed here
  return true;
}

// ---------------------------------------------------------------------------
// Call actions
// ---------------------------------------------------------------------------
async function doDial(to, opts = {}) {
  if (!sessionReady) return { ok: false, err: 'session not ready' };
  if (callState !== 'idle') return { ok: false, err: 'call already in progress', callState };
  if (!waOnline) return { ok: false, err: 'whatsapp web is offline (reconnecting) — try again in a few seconds' };
  const kind = opts.video ? 'video' : 'voice';
  const raw = String(to).trim();
  const isGroup = /@g\.us/i.test(raw) || /^120363\d+/.test(raw);
  const num = raw.split('@')[0].replace(/\D/g, '');
  callPeer = num;
  callDirection = 'out';
  callIsVideo = !!opts.video;
  dialStartedAt = Date.now();
  setCallState('dialing', { peer: num, video: callIsVideo });
  let r = { clicked: null, icons: [] };
  try {
    await openChatFor(isGroup ? `${num}@g.us` : num);
    // fast path — older WA builds still expose header call buttons.
    // page.evaluate throws "Execution context was destroyed" when WA
    // navigates mid-eval (call flow opens a new window/url) — catch and
    // retry instead of crashing the whole dial.
    for (let i = 0; i < 6 && !r.clicked && callState === 'dialing'; i++) {
      try { r = await domClickCall(kind); } catch (e) { log('domClickCall err', e.message.slice(0, 80)); }
      if (!r.clicked) await sleep(800);
    }
    // current WA Web: ⋮ → "New group call" picker → green Voice/Video button
    if (!r.clicked && callState === 'dialing') {
      let opened = {};
      try { opened = await domOpenCallPicker(); } catch (e) { log('picker open err', e.message.slice(0, 80)); }
      if (opened.ok) {
        let hasMembers = false;
        try { hasMembers = await domPickerEnsureMembers(num, isGroup); } catch (e) {}
        if (hasMembers) {
          try { r = await domPickerClickCall(kind); } catch (e) { log('picker click err', e.message.slice(0, 80)); }
          if (!r.clicked) r = { clicked: null, icons: [] };
        } else {
          try { await domClosePicker(); } catch (e) {}
          const peer = callPeer; callPeer = null; callIsVideo = false;
          setCallState('idle', { reason: 'no-member', peer });
          return { ok: false, err: 'call picker: no participant selected' };
        }
      } else if (opened.err) {
        log('call picker open failed:', opened.err);
      }
    }
  } catch (e) {
    log('doDial crashed:', e.message.slice(0, 120));
    const peer = callPeer; callPeer = null; callIsVideo = false;
    if (callState === 'dialing') setCallState('idle', { reason: 'error', peer });
    return { ok: false, err: e.message.slice(0, 160) };
  }
  if (callState !== 'dialing') return { ok: false, err: 'cancelled' };
  if (!r.clicked) { const peer = callPeer; callPeer = null; callIsVideo = false; setCallState('idle', { reason: 'no-call-button', peer }); return { ok: false, err: kind + ' call button not found', icons: r.icons }; }
  return { ok: true, peer: num, video: callIsVideo };
}

async function doHangup() {
  // the call UI takes a moment to appear — keep retrying the End button so a
  // hangup during "dialing"/"connecting" still kills the call. But when the
  // call never started (offline socket, dead picker) there is no End button —
  // give 'dialing' only a few seconds before resetting to idle so the CRM
  // hangup click doesn't look dead for a minute.
  let r = { clicked: null };
  for (let i = 0; i < 12 && !r.clicked; i++) {
    r = await domClickButton('/end|hangup|call-end|decline|reject/');
    if (!r.clicked) await sleep(1000);
    if (callState === 'idle') break;
    if (callState === 'dialing' && i >= 3) break;
  }
  if (callState !== 'idle') { const peer = callPeer; callPeer = null; callMuted = false; setCallState('idle', { reason: 'user-hangup', peer }); }
  return r;
}

async function doAnswer() {
  const r = await domClickButton('/accept|answer|audio accept|call accept/');
  return r;
}

async function doReject() {
  const r = await domClickButton('/decline|reject|end/');
  if (callState === 'incoming') { const peer = callPeer; callPeer = null; setCallState('idle', { reason: 'rejected', peer }); }
  return r;
}

async function doMute(m) {
  // WA-side mute button + our fake track mute
  const r = await domClickButton('/mute|unmute/');
  try { await client.pupPage.evaluate(`window.__cbMicMute(${m === undefined ? 'true' : m})`); } catch (e) {}
  callMuted = !callMuted;
  broadcast({ type: 'event', event: 'mute', muted: callMuted });
  return { clicked: r.clicked, muted: callMuted };
}

// ---------------------------------------------------------------------------
// Browser boot
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
    try { await qrcode.toFile(path.join(OUT_DIR, 'qr.png'), qr, { width: 300, margin: 1 }); } catch (e) {}
    log('QR received');
  });

  client.on('authenticated', () => log('authenticated'));
  client.on('auth_failure', (m) => log('AUTH FAILURE', m));
  client.on('disconnected', (r) => { sessionReady = false; log('DISCONNECTED', r); });

  client.on('ready', async () => {
    try {
      sessionReady = true;
      readyAt = Date.now();
      log('READY — phone:', client.info?.wid?.user, client.info?.pushname);
      client.pupBrowser.on('targetcreated', async (t) => {
        try {
          const p = await t.page();
          if (p && /web\.whatsapp\.com/.test(p.url())) {
            p.on('console', (m) => onConsoleLine(m.text()));
            const r = await injectHooks(p);
            log('new-page injected:', p.url().slice(0, 70), r);
          }
        } catch (e) { log('targetcreated err', e.message); }
      });
      for (const p of await client.pupBrowser.pages()) {
        p.on('console', (m) => { const t = m.text(); if (t.startsWith('[VALOG')) onConsoleLine(t); });
        p.on('pageerror', (e) => log('[pageerror]', String(e).slice(0, 300)));
      }
      await sleep(4000);
      log('hooks all pages:', JSON.stringify(await injectAllPages()));
      // NOTE: no UA override + reload — the reload races wweb.js' Client.inject
      // and kills the process. Calls work fine once the RTCPeerConnection
      // subclass (generateCertificate) is in place.
    } catch (e) { log('ready-handler err', e.message); }
  });

  // retry loop — wweb.js races with page reloads after auth
  for (let i = 0; i < 6; i++) {
    try { await client.initialize(); log('client.initialize resolved'); return; }
    catch (e) { log('initialize retry', i, e.message.slice(0, 120)); await sleep(5000); }
  }
}

// WALOG re-installer — page may reload, hooks get reinstalled via
// evaluateOnNewDocument but WALOG needs WA's module registry first.
setInterval(async () => {
  if (!client || !client.pupPage) return;
  try {
    // a dial that never reached the peer (dead socket, navigation crash)
    // leaves callState stuck in 'dialing' forever — sweep it.
    if (callState === 'dialing' && Date.now() - dialStartedAt > 90000) {
      const peer = callPeer; callPeer = null; callIsVideo = false;
      setCallState('idle', { reason: 'dial-timeout', peer });
    }
    for (const p of await client.pupBrowser.pages()) {
      if (!/web\.whatsapp\.com/.test(p.url())) continue;
      const r = await p.evaluate('try{window.__cbInstallWalog&&window.__cbInstallWalog()}catch(e){String(e)}').catch(() => null);
      if (r && r !== 'already' && r !== 'no-require') log('walog:', r);
      // keep the page's direct audio WS alive across reloads
      const wsState = await p.evaluate(`try{
        const C = window.__cb;
        if (C && window.__cbStartAudioWs && (!C.audioWs || C.audioWs.readyState > 1) && window.__cbWsTicket) {
          window.__cbWsTicket().then(u => window.__cbStartAudioWs(u));
        }
        C && C.audioWs ? C.audioWs.readyState : -1
      }catch(e){-2}`).catch(() => null);
      // CSP bypass only applies to documents loaded after it was set — if the
      // page WS still can't open after ~20s, reload once so it takes effect.
      if (sessionReady && !pageSockets.size && wsState !== 0 && wsState !== 1 && !cspReloadTried && Date.now() - readyAt > 20000) {
        cspReloadTried = true;
        log('page audio WS down — reloading once so CSP bypass applies');
        p.reload({ waitUntil: 'domcontentloaded' }).catch(() => {});
      }
    }
  } catch (e) {}
}, 10000);

// WA Web connectivity watchdog — the "Connecting to WhatsApp / Retrying…"
// banner means the phone-link socket dropped. wweb.js never emits
// 'disconnected' for that, so sessionReady stays true while dials silently
// go nowhere. Detect the banner, nudge "Retry now", and hard-reload the page
// when it stays stuck — otherwise every call dead-ends in 'dialing'.
setInterval(async () => {
  if (!client || !client.pupPage || !sessionReady) return;
  try {
    const p = client.pupPage;
    const offline = await p.evaluate(`(() => {
      const t = (document.body && document.body.innerText) || '';
      return /connecting to whatsapp|retrying\\.\\.\\.|retry now|phone not connected|computer not connected/i.test(t);
    })()`).catch(() => null);
    if (offline === null) return;
    if (offline) {
      if (waOnline) { waOnline = false; offlineSince = Date.now(); log('WA Web offline — reconnect banner detected'); }
      const stuckMs = Date.now() - offlineSince;
      if (stuckMs > 60000) {
        log('WA Web offline >60s — reloading page');
        offlineSince = Date.now();
        await p.reload({ waitUntil: 'domcontentloaded' }).catch(() => {});
      } else if (Date.now() - lastRetryClick > 12000) {
        lastRetryClick = Date.now();
        const clicked = await p.evaluate(`(() => {
          const el = [...document.querySelectorAll('button, a, span, div')].find((e) => /^retry now$/i.test((e.innerText || '').trim()));
          if (el) { el.click(); return true; }
          return false;
        })()`).catch(() => false);
        if (clicked) log('clicked Retry now');
      }
    } else if (!waOnline) {
      waOnline = true; offlineSince = 0;
      log('WA Web back online');
    }
  } catch (e) {}
}, 5000);

// ---------------------------------------------------------------------------
// HTTP + WS API
// ---------------------------------------------------------------------------
async function main() {
  await bootClient();
  const app = express();
  app.use(express.json({ limit: '8mb' }));

  app.get('/state', (_req, res) => res.json({ ready: sessionReady, online: waOnline, callState, peer: callPeer, muted: callMuted, video: callIsVideo, phone: PHONE }));
  app.get('/events', async (_req, res) => {
    try { res.json(await client.pupPage.evaluate('window.__cb.events.slice(-300)')); }
    catch (e) { res.json({ err: e.message }); }
  });
  app.get('/qr', (_req, res) => res.json({ qr: currentQr, pairingCode }));
  app.get('/shot', async (_req, res) => {
    const f = path.join(OUT_DIR, `shot-${Date.now()}.png`);
    await client.pupPage.screenshot({ path: f });
    res.json({ file: f });
  });
  const callRoute = (fn) => async (req, res) => {
    try { res.json(await fn(req)); }
    catch (e) { res.json({ ok: false, err: String(e && e.message || e).slice(0, 200) }); }
  };
  app.post('/dial', callRoute((req) => doDial(req.body.to, { video: !!req.body.video })));
  app.post('/answer', callRoute(() => doAnswer()));
  app.post('/reject', callRoute(() => doReject()));
  app.post('/hangup', callRoute(() => doHangup()));
  app.post('/mute', callRoute((req) => doMute(req.body.mute)));
  app.post('/inject', async (_req, res) => res.json({ r: await injectAllPages() }));
  app.post('/eval', async (req, res) => {
    try { res.json({ ok: true, r: await client.pupPage.evaluate(req.body.js) ?? null }); }
    catch (e) { res.json({ ok: false, err: String(e).slice(0, 500) }); }
  });

  const server = http.createServer(app);
  const wss = new WebSocketServer({ server, path: '/ws' });
  wss.on('connection', (ws, req) => {
    const url = new URL(req.url, 'http://x');
    const ticket = url.searchParams.get('ticket') || '';
    if (WS_SECRET && !validTicket(ticket)) { ws.close(4401, 'bad ticket'); return; }
    const role = url.searchParams.get('role') === 'page' ? 'page' : 'client';
    ws.__role = role;
    if (role === 'page') {
      // the WhatsApp page itself — binary frames: [0x01|PCM audio]
      // [0x02|JPEG remote video]; text frames are page→Node signals.
      pageSockets.add(ws);
      log('ws page connected', pageSockets.size);
      ws.on('message', (data, isBinary) => {
        if (!isBinary) {
          try {
            const m = JSON.parse(data.toString());
            if (m.type === 'video-track' && callState !== 'idle' && !callIsVideo) {
              callIsVideo = true;
              broadcast({ type: 'event', event: 'video', video: true });
              log('remote video track detected — call upgraded to video');
            }
          } catch (e) {}
          return;
        }
        // downlink: WhatsApp audio/video → all CRM clients
        for (const c of wsClients) { if (c.readyState === 1) c.send(data); }
      });
      ws.on('close', () => { pageSockets.delete(ws); log('ws page gone', pageSockets.size); });
      ws.on('error', () => {});
      return;
    }
    wsClients.add(ws);
    log('ws client connected', wsClients.size);
    ws.send(JSON.stringify({ type: 'state', ready: sessionReady, callState, peer: callPeer, muted: callMuted, video: callIsVideo }));
    ws.on('message', async (data, isBinary) => {
      // binary uplink: [0x01|PCM mic] [0x02|JPEG cam] [0x03|H.264 cam] → page
      if (isBinary) {
        const tag = data[0];
        if (tag === 2 || tag === 3) {
          if (callState !== 'idle') for (const p of pageSockets) { if (p.readyState === 1) p.send(data); }
        } else if (callState === 'active') {
          for (const p of pageSockets) { if (p.readyState === 1) p.send(data); }
        }
        return;
      }
      let msg;
      try { msg = JSON.parse(data.toString()); } catch (e) { return; }
      try {
        if (msg.type === 'mic') {
          // legacy JSON path — only when the page WS is unavailable
          if (callState === 'active' && !pageSockets.size) await client.pupPage.evaluate(`window.__cbMicPush('${msg.b64}', ${msg.sr || 16000})`);
        } else if (msg.type === 'control') {
          if (msg.action === 'dial') ws.send(JSON.stringify({ type: 'ack', action: 'dial', r: await doDial(msg.to, { video: !!msg.video }) }));
          else if (msg.action === 'answer') ws.send(JSON.stringify({ type: 'ack', action: 'answer', r: await doAnswer() }));
          else if (msg.action === 'reject') ws.send(JSON.stringify({ type: 'ack', action: 'reject', r: await doReject() }));
          else if (msg.action === 'hangup') ws.send(JSON.stringify({ type: 'ack', action: 'hangup', r: await doHangup() }));
          else if (msg.action === 'mute') ws.send(JSON.stringify({ type: 'ack', action: 'mute', r: await doMute(msg.mute) }));
        }
      } catch (e) { ws.send(JSON.stringify({ type: 'error', err: String(e).slice(0, 200) })); }
    });
    ws.on('close', () => wsClients.delete(ws));
  });

  server.listen(PORT, '127.0.0.1', () => log(`bridge API+WS on 127.0.0.1:${PORT}`));
}

function validTicket(ticket) {
  const m = /^(\d+)\.(\d+)\.([a-f0-9]{64})$/.exec(ticket);
  if (!m) return false;
  const [, uid, exp, sig] = m;
  if (Date.now() > Number(exp)) return false;
  const expected = crypto.createHmac('sha256', WS_SECRET).update(`${uid}.${exp}`).digest('hex');
  return crypto.timingSafeEqual(Buffer.from(sig), Buffer.from(expected));
}

// wweb.js races page reloads with in-flight evaluates — an unhandled rejection
// inside its Client.inject must not kill the bridge.
process.on('unhandledRejection', (e) => log('unhandledRejection (ignored):', String(e && e.message || e).slice(0, 200)));

// graceful shutdown — kills via SIGTERM save WA auth state
for (const sig of ['SIGINT', 'SIGTERM']) {
  process.on(sig, async () => {
    log('shutdown', sig);
    try { await client?.destroy(); } catch (e) {}
    process.exit(0);
  });
}

main().catch((e) => { log('FATAL', e); process.exit(1); });
