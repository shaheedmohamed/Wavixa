<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-all duration-300 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition-all duration-200 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center p-0 sm:p-4"
        :class="isMobile ? 'items-end' : 'items-center'"
        @click.self="closePanel"
      >
        <div
          class="relative w-full sm:w-[420px] h-[60vh] sm:h-[680px] max-h-[90vh] bg-dark-900/95 backdrop-blur-xl border border-slate-700/50 sm:rounded-3xl rounded-t-3xl shadow-2xl flex flex-col overflow-hidden"
          :dir="isRTL ? 'rtl' : 'ltr'"
        >
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-4 border-b border-slate-700/30">
            <div class="flex items-center gap-2">
              <div class="w-2 h-2 rounded-full" :class="statusDotClass" />
              <span class="text-xs font-bold text-slate-300">{{ statusTitle }}</span>
            </div>
            <button
              type="button"
              aria-label="Close Voice AI"
              class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-700/40 transition-colors"
              @click="closePanel"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path d="M18 6 6 18M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Main Voice Area -->
          <div class="flex-1 flex flex-col items-center justify-center p-6 gap-6">
            <!-- Voice Orb -->
            <div class="relative w-40 h-40 sm:w-48 sm:h-48">
              <div
                class="absolute inset-0 rounded-full bg-emerald-500/10 blur-xl transition-all duration-500"
                :class="glowClasses"
              />
              <div
                class="relative w-full h-full rounded-full bg-gradient-to-br from-dark-800 to-dark-950 border border-slate-600/30 flex items-center justify-center transition-all duration-500"
                :class="orbClasses"
              >
                <div v-if="state === 'LISTENING'" class="flex items-center gap-1.5 h-8">
                  <span
                    v-for="i in 5"
                    :key="i"
                    class="w-1.5 bg-emerald-400 rounded-full transition-all duration-75"
                    :style="barStyle(i)"
                  />
                </div>

                <div v-else-if="state === 'AI_SPEAKING'" class="flex items-center gap-1.5 h-8">
                  <span
                    v-for="i in 5"
                    :key="i"
                    class="w-1.5 bg-emerald-300 rounded-full transition-all duration-150"
                    :style="speakingBarStyle(i)"
                  />
                </div>

                <svg
                  v-else
                  class="w-12 h-12 text-emerald-500"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <path d="M12 2a3 3 0 0 1 3 3v7a3 3 0 0 1-6 0V5a3 3 0 0 1 3-3Z" />
                  <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                  <line x1="12" y1="19" x2="12" y2="22" />
                </svg>
              </div>
            </div>

            <div class="text-center space-y-1">
              <h3 class="text-lg font-black text-white">{{ t('voice.title') }}</h3>
              <p class="text-sm font-medium text-slate-400 min-h-[1.5em]">{{ statusText }}</p>
            </div>

            <!-- Error / Permission Banner -->
            <div
              v-if="state === 'PERMISSION_DENIED'"
              class="w-full p-3 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-100 text-xs text-center"
            >
              <p class="mb-2">{{ t('voice.permission_denied_text') }}</p>
              <button
                type="button"
                class="px-4 py-1.5 rounded-lg bg-amber-500 text-amber-950 font-bold text-xs hover:bg-amber-400 transition-colors"
                @click="requestMicrophone"
              >
                {{ t('voice.allow_microphone') }}
              </button>
            </div>

            <div
              v-if="state === 'ERROR'"
              class="w-full p-3 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-100 text-xs text-center"
            >
              {{ errorMessage }}
            </div>
          </div>

          <!-- Bottom Controls -->
          <div class="p-5 border-t border-slate-700/30 flex items-center justify-center gap-4">
            <button
              v-if="state === 'AI_SPEAKING'"
              type="button"
              class="p-4 rounded-full bg-slate-700 text-white hover:bg-slate-600 transition-colors"
              :title="t('voice.interrupt')"
              @click="interruptSpeaking"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <rect x="6" y="6" width="12" height="12" rx="2" />
              </svg>
            </button>

            <button
              v-if="state === 'IDLE' || state === 'DISCONNECTED'"
              type="button"
              class="p-5 rounded-full bg-emerald-500 text-dark-950 hover:bg-emerald-400 shadow-emerald-lg transition-all active:scale-95"
              :aria-label="t('voice.start_listening')"
              @click="startListening"
            >
              <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path d="M12 2a3 3 0 0 1 3 3v7a3 3 0 0 1-6 0V5a3 3 0 0 1 3-3Z" />
                <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
              </svg>
            </button>

            <button
              v-else-if="state === 'LISTENING'"
              type="button"
              class="p-5 rounded-full bg-emerald-500 text-dark-950 hover:bg-emerald-400 shadow-emerald-lg transition-all animate-pulse"
              :aria-label="t('voice.stop_listening')"
              @click="stopListening"
            >
              <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <rect x="6" y="6" width="12" height="12" rx="2" />
              </svg>
            </button>

            <button
              v-else
              type="button"
              class="p-4 rounded-full bg-slate-700 text-white hover:bg-slate-600 transition-colors"
              :aria-label="t('voice.cancel')"
              @click="resetToIdle"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path d="M6 18 18 6M6 6l12 12" />
              </svg>
            </button>

            <button
              type="button"
              class="p-4 rounded-full bg-red-500/10 text-red-400 border border-red-500/20 hover:bg-red-500/20 transition-colors"
              :title="t('voice.end_conversation')"
              @click="closePanel"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path d="M10.68 13.31a6 6 0 0 1-3.72-4.31L8.07 7.2a1 1 0 0 1 .92-.65h2.5a1 1 0 0 1 .92.65l1.11 1.8a6 6 0 0 1-3.74 4.31Z" />
                <path d="M17.5 19c0-3.87-3.13-7-7-7s-7 3.13-7 7" />
                <path d="M2 19h15" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script>
import { ref, computed, onUnmounted, watch } from 'vue';
import AgoraRTC from 'agora-rtc-sdk-ng';
import voiceService from '@/services/voice';
import { t, isRTL } from '@/i18n';

export default {
  name: 'VoiceAssistantPanel',
  props: {
    isOpen: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['close'],
  setup(props, { emit }) {
    const state = ref('IDLE');
    const errorMessage = ref('');
    const sessionId = ref(null);
    const sessionData = ref(null);
    const isMobile = ref(window.innerWidth < 640);
    const micData = ref(0);
    const localAudioTrack = ref(null);
    const agoraClient = ref(null);
    const remoteAudioTrack = ref(null);
    const volumeInterval = ref(null);
    const remoteVolumeTimer = ref(null);

    const statusTitle = computed(() => {
      const map = {
        IDLE: t('voice.status.idle'),
        CONNECTING: t('voice.status.connecting'),
        LISTENING: t('voice.status.listening'),
        AI_SPEAKING: t('voice.status.speaking'),
        ERROR: t('voice.status.error'),
        DISCONNECTED: t('voice.status.disconnected'),
        PERMISSION_DENIED: t('voice.status.permission_denied'),
      };
      return map[state.value] ?? t('voice.status.idle');
    });

    const statusText = computed(() => {
      const map = {
        IDLE: t('voice.prompt.idle'),
        CONNECTING: t('voice.prompt.connecting'),
        LISTENING: t('voice.prompt.listening'),
        AI_SPEAKING: t('voice.prompt.speaking'),
        ERROR: t('voice.prompt.error'),
        DISCONNECTED: t('voice.prompt.disconnected'),
        PERMISSION_DENIED: t('voice.prompt.permission_denied'),
      };
      return map[state.value] ?? '';
    });

    const statusDotClass = computed(() => {
      const map = {
        IDLE: 'bg-slate-500',
        CONNECTING: 'bg-amber-400',
        LISTENING: 'bg-emerald-500 animate-pulse',
        AI_SPEAKING: 'bg-emerald-300',
        ERROR: 'bg-red-500',
        DISCONNECTED: 'bg-slate-500',
        PERMISSION_DENIED: 'bg-amber-500',
      };
      return map[state.value] ?? 'bg-slate-500';
    });

    const orbClasses = computed(() => ({
      'shadow-emerald': state.value === 'LISTENING' || state.value === 'AI_SPEAKING',
      'scale-105 border-emerald-500/40': state.value === 'LISTENING',
      'scale-100 border-emerald-300/30': state.value === 'AI_SPEAKING',
      'scale-100 border-slate-600/30': state.value === 'IDLE' || state.value === 'CONNECTING',
    }));

    const glowClasses = computed(() => ({
      'scale-110 opacity-70 animate-pulse': state.value === 'LISTENING' || state.value === 'AI_SPEAKING',
      'scale-100 opacity-0': state.value === 'IDLE' || state.value === 'CONNECTING' || state.value === 'DISCONNECTED',
    }));

    const cleanup = async () => {
      if (volumeInterval.value) {
        clearInterval(volumeInterval.value);
        volumeInterval.value = null;
      }

      stopRemoteVolumeMonitor();

      if (localAudioTrack.value) {
        try { localAudioTrack.value.stop(); } catch (e) {}
        try { localAudioTrack.value.close(); } catch (e) {}
        localAudioTrack.value = null;
      }

      if (agoraClient.value) {
        try { await agoraClient.value.leave(); } catch (e) {}
        agoraClient.value = null;
      }

      if (sessionId.value) {
        try { await voiceService.endSession(sessionId.value); } catch (e) {}
      }

      sessionId.value = null;
      sessionData.value = null;
      remoteAudioTrack.value = null;
      micData.value = 0;
      state.value = 'IDLE';
    };

    const stopRemoteVolumeMonitor = () => {
      if (remoteVolumeTimer.value) {
        clearInterval(remoteVolumeTimer.value);
        remoteVolumeTimer.value = null;
      }
    };

    const startRemoteVolumeMonitor = (track) => {
      stopRemoteVolumeMonitor();
      if (!track) return;

      const SPEAKING_THRESHOLD = 0.03;
      const SILENCE_TIMEOUT = 900;
      const CHECK_INTERVAL = 150;
      let silenceStart = null;
      let wasSpeaking = false;

      remoteVolumeTimer.value = setInterval(() => {
        if (!track || state.value === 'IDLE' || state.value === 'DISCONNECTED' || state.value === 'ERROR') {
          stopRemoteVolumeMonitor();
          return;
        }

        const vol = typeof track.getVolumeLevel === 'function' ? track.getVolumeLevel() : 0;

        if (vol > SPEAKING_THRESHOLD) {
          silenceStart = null;
          if (!wasSpeaking) {
            wasSpeaking = true;
            try { track.play(); } catch (e) {}
          }
          if (remoteAudioTrack.value) {
            try { remoteAudioTrack.value.setVolume(100); } catch (e) {}
          }
          if (state.value !== 'AI_SPEAKING') {
            state.value = 'AI_SPEAKING';
          }
        } else {
          if (silenceStart === null) silenceStart = Date.now();
          if (Date.now() - silenceStart > SILENCE_TIMEOUT) {
            wasSpeaking = false;
            if (state.value !== 'LISTENING') {
              state.value = 'LISTENING';
            }
          }
        }
      }, CHECK_INTERVAL);
    };

    const initAgora = async () => {
      if (!props.isOpen) return;

      state.value = 'CONNECTING';
      errorMessage.value = '';

      try {
        const res = await voiceService.startSession();
        if (!res.success) {
          throw new Error(res.message || t('voice.session_error'));
        }

        const data = res.data;
        if (!data.agora_token || !data.agora_app_id) {
          throw new Error(t('voice.session_error'));
        }

        sessionData.value = data;
        sessionId.value = data.session_id;

        const client = AgoraRTC.createClient({ mode: 'rtc', codec: 'vp8' });
        agoraClient.value = client;

        client.on('connection-state-change', (curState) => {
          if (curState === 'DISCONNECTED' || curState === 'ABORTED') {
            state.value = 'DISCONNECTED';
          } else if (curState === 'CONNECTED') {
            if (state.value !== 'AI_SPEAKING') {
              state.value = 'LISTENING';
            }
          }
        });

        client.on('user-published', async (user, mediaType) => {
          if (mediaType !== 'audio') return;
          await client.subscribe(user, mediaType);
          if (user.audioTrack) {
            user.audioTrack.play();
            remoteAudioTrack.value = user.audioTrack;
            if (String(user.uid) === String(data.agora_agent_uid)) {
              startRemoteVolumeMonitor(user.audioTrack);
            }
          }
        });

        client.on('user-unpublished', (user, mediaType) => {
          if (mediaType === 'audio' && String(user.uid) === String(data.agora_agent_uid)) {
            stopRemoteVolumeMonitor();
            state.value = 'LISTENING';
          }
        });

        client.on('user-left', (user) => {
          if (String(user.uid) === String(data.agora_agent_uid)) {
            stopRemoteVolumeMonitor();
            state.value = 'LISTENING';
          }
        });

        await client.join(
          data.agora_app_id,
          data.channel_name,
          data.agora_token,
          Number(data.agora_uid),
        );

        const micTrack = await AgoraRTC.createMicrophoneAudioTrack();
        localAudioTrack.value = micTrack;
        await client.publish([micTrack]);

        state.value = 'LISTENING';

        volumeInterval.value = setInterval(() => {
          if (localAudioTrack.value) {
            micData.value = Math.round(localAudioTrack.value.getVolumeLevel() * 100);
          }
        }, 100);
      } catch (err) {
        console.error('Agora init error:', err);
        const message = (err?.message || err?.reason || String(err)).toLowerCase();

        if (message.includes('device_not_found') || message.includes('notfounderror') || message.includes('requested device not found')) {
          state.value = 'ERROR';
          errorMessage.value = t('voice.microphone_not_found');
        } else if (message.includes('permission_denied') || message.includes('notallowederror') || message.includes('permission')) {
          state.value = 'PERMISSION_DENIED';
          errorMessage.value = t('voice.microphone_blocked');
        } else {
          state.value = 'ERROR';
          errorMessage.value = t('voice.ai_error');
        }
      }
    };

    const startListening = async () => {
      if (!sessionData.value) {
        await initAgora();
        return;
      }

      if (localAudioTrack.value) {
        try { localAudioTrack.value.setEnabled(true); } catch (e) {}
      }
      state.value = 'LISTENING';
    };

    const stopListening = () => {
      if (localAudioTrack.value) {
        try { localAudioTrack.value.setEnabled(false); } catch (e) {}
      }
      state.value = 'IDLE';
    };

    const interruptSpeaking = () => {
      if (remoteAudioTrack.value) {
        try { remoteAudioTrack.value.setVolume(0); } catch (e) {}
      }
      state.value = 'LISTENING';
      if (localAudioTrack.value) {
        try { localAudioTrack.value.setEnabled(true); } catch (e) {}
      }
    };

    const resetToIdle = () => {
      state.value = 'IDLE';
    };

    const closePanel = async () => {
      await cleanup();
      emit('close');
    };

    const requestMicrophone = () => {
      state.value = 'IDLE';
      initAgora();
    };

    const barStyle = (i) => ({
      height: `${Math.max(12, Math.min(48, micData.value * 0.4 + i * 4))}px`,
      opacity: 0.6 + (i % 3) * 0.2,
    });

    const speakingBarStyle = (i) => ({
      height: `${12 + Math.random() * 40}px`,
    });

    watch(() => props.isOpen, (open) => {
      if (open) {
        initAgora();
      } else {
        cleanup();
      }
    });

    window.addEventListener('resize', () => {
      isMobile.value = window.innerWidth < 640;
    });

    onUnmounted(() => {
      cleanup();
    });

    return {
      isOpen: computed(() => props.isOpen),
      state,
      errorMessage,
      statusTitle,
      statusText,
      statusDotClass,
      orbClasses,
      glowClasses,
      isMobile,
      isRTL,
      t,
      startListening,
      stopListening,
      interruptSpeaking,
      resetToIdle,
      closePanel,
      requestMicrophone,
      barStyle,
      speakingBarStyle,
    };
  },
};
</script>
