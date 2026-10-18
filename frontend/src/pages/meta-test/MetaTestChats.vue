<template>
  <div class="flex gap-4 h-[calc(100vh-240px)] min-h-[500px]">
    <!-- Conversations list -->
    <aside class="w-80 shrink-0 bg-white border border-gray-100 rounded-2xl flex flex-col overflow-hidden">
      <div class="p-3 border-b border-gray-100 flex gap-2">
        <input
          v-model="search"
          @input="debouncedLoad"
          :placeholder="t('meta.chats.search_ph')"
          class="flex-1 border border-gray-100 rounded-xl px-3 py-2 text-sm bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-100 outline-none"
        />
        <button
          @click="newChatOpen = !newChatOpen"
          class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center hover:bg-emerald-700 shrink-0"
          :title="t('meta.chats.new')"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
      </div>

      <div v-if="newChatOpen" class="p-3 border-b border-gray-100 bg-gray-50 space-y-2">
        <input v-model="newPeer" dir="ltr" placeholder="+2010..." class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm" />
        <button @click="openNewChat" class="w-full py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700">
          {{ t('meta.chats.start') }}
        </button>
      </div>

      <div class="flex-1 overflow-y-auto divide-y divide-gray-50">
        <div v-if="loadingConvs" class="p-4 text-sm text-gray-400">{{ t('meta.common.loading') }}</div>
        <div v-else-if="!conversations.length" class="p-6 text-center text-sm text-gray-400">{{ t('meta.chats.empty') }}</div>

        <button
          v-for="c in conversations"
          :key="c.peer"
          @click="openChat(c)"
          class="w-full text-start p-3 hover:bg-gray-50 transition-colors flex gap-3 items-center"
          :class="{ 'bg-indigo-50/60': activePeer === c.peer }"
        >
          <div class="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-400 to-teal-600 text-white flex items-center justify-center font-bold text-sm shrink-0">
            {{ c.contact_name ? c.contact_name.trim()[0] : c.peer.slice(-2) }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex justify-between items-baseline">
              <p class="font-semibold text-sm text-gray-900 truncate">
                {{ c.contact_name || '+' + c.peer }}
                <span v-if="c.contact_name" class="text-[10px] text-gray-400 font-normal" dir="ltr">+{{ c.peer }}</span>
              </p>
              <span class="text-[10px] text-gray-400 shrink-0">{{ fmtTime(c.last_at) }}</span>
            </div>
            <div class="flex justify-between items-center mt-0.5">
              <p class="text-xs text-gray-500 truncate flex-1">
                <span v-if="c.last_direction === 'outgoing'" class="text-indigo-400">✓ </span>{{ lastLabel(c) }}
              </p>
              <span v-if="c.unread" class="ms-2 min-w-[18px] h-[18px] px-1 rounded-full bg-emerald-500 text-white text-[10px] font-bold flex items-center justify-center shrink-0">
                {{ c.unread }}
              </span>
            </div>
          </div>
        </button>

        <button
          v-if="pagination && pagination.current_page < pagination.last_page"
          @click="loadMore"
          class="w-full py-2.5 text-xs font-bold text-indigo-600 hover:bg-indigo-50"
        >
          {{ t('meta.chats.load_more') }}
        </button>
      </div>
    </aside>

    <!-- Chat area -->
    <main class="flex-1 bg-white border border-gray-100 rounded-2xl flex flex-col overflow-hidden">
      <template v-if="activePeer">
        <header class="px-4 py-3 border-b border-gray-100 flex items-center gap-3 bg-gray-50/60">
          <div class="w-9 h-9 rounded-full bg-gradient-to-br from-emerald-400 to-teal-600 text-white flex items-center justify-center font-bold text-xs">
            {{ activeName ? activeName.trim()[0] : activePeer.slice(-2) }}
          </div>
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <p class="font-bold text-sm text-gray-900 truncate">{{ activeName || '+' + activePeer }}</p>
              <button @click="renameContact" class="text-gray-400 hover:text-indigo-600" :title="t('meta.chats.rename')">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
              </button>
            </div>
            <p class="text-[10px] text-gray-400" dir="ltr">
              +{{ activePeer }}<span v-if="activeConv?.our_number"> · {{ t('meta.chats.via') }} {{ activeConv.our_number }}</span>
            </p>
          </div>
          <div class="ms-auto">
            <select v-model="sendFrom" class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 text-gray-600" dir="ltr">
              <option :value="null">{{ t('meta.chats.auto_number') }}</option>
              <option v-for="n in numbers" :key="n.id" :value="n.id">{{ n.phone_number }}</option>
            </select>
          </div>
        </header>

        <div ref="msgsEl" class="flex-1 overflow-y-auto p-4 space-y-2 bg-[#ece5dd]/40">
          <div v-if="!thread.length" class="text-center text-xs text-gray-400 mt-10">{{ t('meta.chats.no_msgs') }}</div>
          <div
            v-for="m in thread"
            :key="m.id"
            class="max-w-[70%] rounded-xl px-3 py-2 text-sm shadow-sm"
            :class="m.direction === 'outgoing'
              ? 'bg-[#d9fdd3] text-gray-900 ms-auto rounded-br-none'
              : 'bg-white text-gray-900 me-auto rounded-bl-none'"
          >
            <!-- media -->
            <template v-if="isMedia(m)">
              <img v-if="m.message_type === 'image' && mediaUrls[m.id]" :src="mediaUrls[m.id]" class="rounded-lg max-h-64 mb-1 cursor-pointer" @click="openMedia(m)" />
              <video v-else-if="m.message_type === 'video' && mediaUrls[m.id]" :src="mediaUrls[m.id]" controls class="rounded-lg max-h-64 mb-1"></video>
              <audio v-else-if="m.message_type === 'audio' && mediaUrls[m.id]" :src="mediaUrls[m.id]" controls class="w-56 mb-1"></audio>
              <a v-else-if="m.message_type === 'document' && mediaUrls[m.id]" :href="mediaUrls[m.id]" target="_blank" class="flex items-center gap-2 text-indigo-700 font-semibold underline mb-1">
                📄 {{ docName(m) }}
              </a>
              <span v-else class="text-xs text-gray-400">[{{ m.message_type }}]</span>
            </template>
            <p v-if="m.body" class="whitespace-pre-wrap break-words">{{ m.body }}</p>
            <p v-else-if="!isMedia(m)" class="text-xs text-gray-400">[{{ m.message_type || 'template' }}]</p>
            <p class="text-[10px] text-gray-400 mt-1 flex items-center gap-1 justify-end">
              {{ fmtTime(m.created_at) }}
              <span v-if="m.direction === 'outgoing'" :class="tickClass(m.status)">{{ ticks(m.status) }}</span>
            </p>
          </div>
        </div>

        <p v-if="sendError" class="px-4 py-2 text-xs text-red-600 bg-red-50 border-t border-red-100">{{ sendError }}</p>

        <footer class="p-3 border-t border-gray-100 flex gap-2 items-center bg-white">
          <!-- attach -->
          <div class="relative">
            <button @click="attachOpen = !attachOpen" class="w-10 h-10 rounded-full text-gray-500 hover:bg-gray-100 flex items-center justify-center">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
            </button>
            <div v-if="attachOpen" class="absolute bottom-12 start-0 bg-white border border-gray-100 rounded-xl shadow-lg p-1.5 w-40 z-20">
              <button @click="pickFile('image')" class="w-full text-start px-3 py-2 text-sm rounded-lg hover:bg-gray-50">🖼️ {{ t('meta.chats.photo') }}</button>
              <button @click="pickFile('video')" class="w-full text-start px-3 py-2 text-sm rounded-lg hover:bg-gray-50">🎥 {{ t('meta.chats.video') }}</button>
              <button @click="pickFile('document')" class="w-full text-start px-3 py-2 text-sm rounded-lg hover:bg-gray-50">📄 {{ t('meta.chats.doc') }}</button>
              <button @click="pickFile('audio')" class="w-full text-start px-3 py-2 text-sm rounded-lg hover:bg-gray-50">🎵 {{ t('meta.chats.audio') }}</button>
            </div>
            <input ref="fileInput" type="file" class="hidden" @change="onFilePicked" />
          </div>

          <input
            v-model="draft"
            @keydown.enter.prevent="send"
            :placeholder="t('meta.chats.type_ph')"
            class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-100 focus:border-emerald-300 outline-none"
          />

          <!-- voice recorder -->
          <button
            v-if="!recording"
            @click="startRecording"
            class="w-11 h-11 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center hover:bg-gray-200 shrink-0"
            :title="t('meta.chats.voice')"
          >
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 15a3 3 0 003-3V6a3 3 0 10-6 0v6a3 3 0 003 3zm6-3a6 6 0 11-12 0H4a8 8 0 007 7.94V22h2v-2.06A8 8 0 0020 12h-2z"/></svg>
          </button>
          <button
            v-else
            @click="stopRecording"
            class="h-11 px-4 rounded-full bg-red-500 text-white text-xs font-bold flex items-center justify-center gap-2 animate-pulse shrink-0"
          >
            ⏺ {{ recSeconds }}s — {{ t('meta.chats.tap_send') }}
          </button>

          <button
            @click="send"
            :disabled="sending || !draft.trim()"
            class="w-11 h-11 rounded-full bg-emerald-500 text-white flex items-center justify-center hover:bg-emerald-600 disabled:opacity-40 shrink-0"
          >
            <svg class="w-5 h-5 rtl:-scale-x-100" fill="currentColor" viewBox="0 0 24 24"><path d="M3.4 20.4l17.4-7.5c.8-.35.8-1.45 0-1.8L3.4 3.6c-.66-.29-1.39.2-1.39.91L2 9.12c0 .5.37.93.87.99L17 12 2.87 13.88c-.5.07-.87.5-.87 1l.01 4.61c0 .72.73 1.2 1.39.91z"/></svg>
          </button>
        </footer>
      </template>

      <div v-else class="flex-1 flex flex-col items-center justify-center text-gray-400 gap-3">
        <svg class="w-16 h-16 text-gray-200" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2z"/></svg>
        <p class="text-sm font-medium">{{ t('meta.chats.pick') }}</p>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import metaTest from '@/services/metaTest';
import api from '@/services/api';
import { t } from '@/i18n';

const conversations = ref([]);
const pagination = ref(null);
const convPage = ref(1);
const loadingConvs = ref(true);
const search = ref('');
const totalUnread = ref(0);

const activePeer = ref(null);
const activeName = ref(null);
const thread = ref([]);
const sendFrom = ref(null);
const draft = ref('');
const sending = ref(false);
const sendError = ref(null);
const numbers = ref([]);
const newChatOpen = ref(false);
const newPeer = ref('');
const msgsEl = ref(null);
const mediaUrls = ref({});

const attachOpen = ref(false);
const fileInput = ref(null);
const attachKind = ref('image');

const recording = ref(false);
const recSeconds = ref(0);
let mediaRecorder = null;
let recChunks = [];
let recTimer = null;

let poller = null;
let debounce = null;

const activeConv = computed(() => conversations.value.find((c) => c.peer === activePeer.value));

const MEDIA_TYPES = ['image', 'video', 'audio', 'document'];
const isMedia = (m) => MEDIA_TYPES.includes(m.message_type);

function lastLabel(c) {
  if (c.last_body) return c.last_body;
  return '[' + (c.last_type || 'template') + ']';
}

function docName(m) {
  return m.raw_payload?.document?.filename || m.body || 'document';
}

function fmtTime(ts) {
  if (!ts) return '';
  const d = new Date(ts);
  const today = new Date();
  if (d.toDateString() === today.toDateString()) {
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }
  return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

function ticks(status) {
  return { sent: '✓', delivered: '✓✓', read: '✓✓', failed: '✗' }[status] || '✓';
}

function tickClass(status) {
  if (status === 'read') return 'text-sky-500';
  if (status === 'failed') return 'text-red-500';
  return 'text-gray-400';
}

async function fetchMedia(m) {
  if (!isMedia(m) || mediaUrls.value[m.id]) return;
  mediaUrls.value[m.id] = 'loading';
  try {
    const blob = await api.request(`/meta-test/chats/media/${m.id}`, { responseType: 'blob' });
    mediaUrls.value[m.id] = URL.createObjectURL(blob);
  } catch (e) {
    delete mediaUrls.value[m.id];
  }
}

function hydrateMedia(msgs) {
  msgs.forEach((m) => { if (isMedia(m)) fetchMedia(m); });
}

async function loadConvs(quiet = false, page = 1) {
  if (!quiet) loadingConvs.value = true;
  try {
    const res = await metaTest.chats({ search: search.value || undefined, page });
    const items = res.data || [];
    if (page === 1) {
      conversations.value = items;
    } else {
      conversations.value.push(...items);
    }
    pagination.value = res.pagination || null;
    convPage.value = page;
    totalUnread.value = res.total_unread || 0;
  } catch (e) { /* keep quiet */ } finally {
    loadingConvs.value = false;
  }
}

function loadMore() {
  loadConvs(true, convPage.value + 1);
}

function debouncedLoad() {
  clearTimeout(debounce);
  debounce = setTimeout(() => loadConvs(), 400);
}

async function openChat(c) {
  activePeer.value = c.peer;
  sendFrom.value = c.phone_number_id || null;
  sendError.value = null;
  const res = await metaTest.chatMessages(c.peer);
  thread.value = res.data || [];
  activeName.value = res.contact_name || c.contact_name || null;
  hydrateMedia(thread.value);
  c.unread = 0;
  await nextTick();
  scrollBottom();
}

function openNewChat() {
  const peer = newPeer.value.replace(/\D/g, '');
  if (peer.length < 7) return;
  newChatOpen.value = false;
  newPeer.value = '';
  activePeer.value = peer;
  activeName.value = null;
  thread.value = [];
  sendFrom.value = null;
  sendError.value = null;
}

async function renameContact() {
  const name = prompt(t('meta.chats.rename_ph'), activeName.value || '');
  if (name === null) return;
  try {
    await metaTest.chatSetName(activePeer.value, name.trim() || '+' + activePeer.value);
    activeName.value = name.trim() || null;
    const c = conversations.value.find((x) => x.peer === activePeer.value);
    if (c) c.contact_name = name.trim() || null;
  } catch (e) {
    sendError.value = e.data?.message || e.message;
  }
}

async function refreshThread() {
  if (!activePeer.value || !thread.value.length) return;
  const last = thread.value[thread.value.length - 1].id;
  const res = await metaTest.chatMessages(activePeer.value, last);
  if (res.data?.length) {
    thread.value.push(...res.data);
    hydrateMedia(res.data);
    await nextTick();
    scrollBottom();
  }
}

function scrollBottom() {
  if (msgsEl.value) msgsEl.value.scrollTop = msgsEl.value.scrollHeight;
}

async function send() {
  const text = draft.value.trim();
  if (!text || sending.value) return;
  sending.value = true;
  sendError.value = null;
  try {
    const res = await metaTest.chatSend(activePeer.value, text, sendFrom.value);
    if (!res.success) throw new Error(res.message);
    thread.value.push(res.data);
    draft.value = '';
    await nextTick();
    scrollBottom();
    loadConvs(true);
  } catch (e) {
    sendError.value = e.data?.message || e.message;
  } finally {
    sending.value = false;
  }
}

function pickFile(kind) {
  attachKind.value = kind;
  attachOpen.value = false;
  const accept = {
    image: 'image/*',
    video: 'video/*',
    audio: 'audio/*',
    document: '*/*',
  }[kind];
  fileInput.value.accept = accept;
  fileInput.value.click();
}

async function onFilePicked(e) {
  const file = e.target.files?.[0];
  e.target.value = '';
  if (!file) return;

  let caption = null;
  if (['image', 'video'].includes(attachKind.value)) {
    caption = prompt(t('meta.chats.caption_ph'), '') || null;
  }

  sendError.value = null;
  sending.value = true;
  try {
    const fd = new FormData();
    fd.append('file', file);
    fd.append('kind', attachKind.value);
    if (caption) fd.append('caption', caption);
    if (sendFrom.value) fd.append('phone_number_id', sendFrom.value);

    const res = await metaTest.chatSendMedia(activePeer.value, fd);
    if (!res.success) throw new Error(res.message);
    thread.value.push(res.data);
    hydrateMedia([res.data]);
    await nextTick();
    scrollBottom();
    loadConvs(true);
  } catch (err) {
    sendError.value = err.data?.message || err.message;
  } finally {
    sending.value = false;
  }
}

async function startRecording() {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    recChunks = [];
    mediaRecorder = new MediaRecorder(stream);
    mediaRecorder.ondataavailable = (e) => recChunks.push(e.data);
    mediaRecorder.onstop = async () => {
      stream.getTracks().forEach((tr) => tr.stop());
      const blob = new Blob(recChunks, { type: mediaRecorder.mimeType || 'audio/webm' });
      await sendVoice(blob);
    };
    mediaRecorder.start();
    recording.value = true;
    recSeconds.value = 0;
    recTimer = setInterval(() => recSeconds.value++, 1000);
  } catch (e) {
    sendError.value = t('meta.chats.mic_denied');
  }
}

function stopRecording() {
  clearInterval(recTimer);
  recording.value = false;
  if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
}

async function sendVoice(blob) {
  sending.value = true;
  sendError.value = null;
  try {
    const fd = new FormData();
    fd.append('file', new File([blob], 'voice.webm', { type: blob.type }));
    fd.append('kind', 'voice');
    if (sendFrom.value) fd.append('phone_number_id', sendFrom.value);

    const res = await metaTest.chatSendMedia(activePeer.value, fd);
    if (!res.success) throw new Error(res.message);
    thread.value.push(res.data);
    hydrateMedia([res.data]);
    await nextTick();
    scrollBottom();
    loadConvs(true);
  } catch (err) {
    sendError.value = err.data?.message || err.message;
  } finally {
    sending.value = false;
  }
}

onMounted(async () => {
  loadConvs();
  try {
    const res = await metaTest.phoneNumbers();
    numbers.value = (res.data || []).filter((n) => n.is_active);
  } catch (e) { /* ignore */ }

  poller = setInterval(() => {
    loadConvs(true);
    refreshThread();
  }, 5000);
});

onUnmounted(() => {
  clearInterval(poller);
  clearInterval(recTimer);
});
</script>
