<template>
  <DashboardLayout>
    <div class="h-[calc(100vh-120px)] flex flex-col md:flex-row gap-6 animate-in fade-in duration-500">
      <!-- Conversations List -->
      <div class="w-full md:w-80 bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden flex flex-col" :class="selected ? 'hidden md:flex' : 'flex'">
        <div class="p-4 border-b border-gray-50">
          <h2 class="text-base font-black text-gray-900">{{ t('messenger_page.inbox') }}</h2>
          <input v-model="search" @input="debounceSearch" type="text" :placeholder="t('contacts_page.search_placeholder')" class="w-full mt-3 bg-gray-50 border border-gray-100 rounded-xl px-3 py-2 text-xs font-bold text-gray-700 outline-none focus:border-[#0084FF]" />
        </div>
        <div class="flex-1 overflow-y-auto">
          <div v-if="loadingConversations" class="p-6 text-center text-gray-400 text-xs font-bold">{{ t('common.loading') }}</div>
          <div v-else-if="conversations.length === 0" class="p-6 text-center text-gray-400 text-xs font-bold">لا توجد محادثات.</div>
          <button
            v-for="conv in conversations"
            :key="conv.id"
            @click="selectConversation(conv)"
            class="w-full text-right p-4 border-b border-gray-50 hover:bg-gray-50/50 transition-all"
            :class="selected?.id === conv.id ? 'bg-blue-50/50' : ''"
          >
            <p class="text-sm font-black text-gray-900">{{ conv.contact?.name || conv.psid }}</p>
            <p class="text-[10px] font-bold text-gray-400 truncate" dir="ltr">{{ conv.page?.page_name }}</p>
            <p class="text-[10px] text-gray-400 mt-1">{{ formatDate(conv.last_message_at) }}</p>
          </button>
        </div>
      </div>

      <!-- Chat -->
      <div class="flex-1 bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden flex-col" :class="selected ? 'flex' : 'hidden md:flex'">
        <div v-if="selected" class="p-4 border-b border-gray-50 flex items-center justify-between gap-2">
          <div class="flex items-center gap-2 min-w-0">
            <button @click="selected = null" class="md:hidden p-1.5 -m-1 text-gray-400 hover:text-gray-700 rounded-lg">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <div class="min-w-0">
              <p class="text-sm font-black text-gray-900 truncate">{{ selected.contact?.name || selected.psid }}</p>
              <p class="text-[10px] font-bold text-gray-400 truncate" dir="ltr">{{ selected.page?.page_name }}</p>
            </div>
          </div>
          <button
            @click="toggleAi"
            :disabled="isTogglingAi"
            class="px-3 py-1.5 text-xs font-black rounded-lg border transition-all"
            :class="selected.ai_disabled ? 'bg-amber-50 text-amber-600 border-amber-100' : 'bg-emerald-50 text-emerald-600 border-emerald-100'"
          >
            {{ selected.ai_disabled ? 'تفعيل AI' : 'إيقاف AI' }}
          </button>
        </div>
        <div v-else class="p-4 border-b border-gray-50">
          <p class="text-sm font-black text-gray-900">{{ t('messenger_page.inbox') }}</p>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-3" ref="messagesContainer">
          <div v-if="loadingMessages" class="text-center text-gray-400 text-xs font-bold">{{ t('common.loading') }}</div>
          <div v-else-if="!selected" class="flex items-center justify-center h-full text-gray-400 text-xs font-bold">اختر محادثة من القائمة.</div>
          <div v-else-if="messages.length === 0" class="flex items-center justify-center h-full text-gray-400 text-xs font-bold">لا توجد رسائل.</div>
          <div v-else v-for="msg in messages" :key="msg.id" class="flex" :class="msg.direction === 'outgoing' ? 'justify-start' : 'justify-end'">
            <div class="max-w-[70%] rounded-2xl px-4 py-2.5 text-xs" :class="msg.direction === 'outgoing' ? 'bg-[#0084FF] text-white rounded-tr-none' : 'bg-gray-100 text-gray-800 rounded-tl-none'">
              <p class="whitespace-pre-wrap leading-relaxed">{{ msg.message }}</p>
              <p class="text-[9px] opacity-70 mt-1">{{ formatTime(msg.created_at) }}</p>
            </div>
          </div>
        </div>

        <div v-if="selected" class="p-4 border-t border-gray-50">
          <form @submit.prevent="sendMessage" class="flex gap-3">
            <input v-model="newMessage" type="text" :disabled="sending" placeholder="اكتب رسالتك..." class="flex-1 bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-xs font-bold text-gray-900 outline-none focus:border-[#0084FF]" />
            <button type="submit" :disabled="!newMessage || sending" class="px-5 py-3 bg-[#0084FF] hover:bg-[#0073E6] disabled:opacity-50 text-white text-xs font-black rounded-2xl transition-all">
              {{ sending ? '...' : 'إرسال' }}
            </button>
          </form>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, nextTick } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import api from '@/services/api';
import { t } from '@/i18n';

export default {
  name: 'MessengerInbox',
  components: { DashboardLayout },
  setup() {
    const conversations = ref([]);
    const loadingConversations = ref(true);
    const selected = ref(null);
    const messages = ref([]);
    const loadingMessages = ref(false);
    const newMessage = ref('');
    const sending = ref(false);
    const search = ref('');
    const searchTimeout = ref(null);
    const isTogglingAi = ref(false);
    const messagesContainer = ref(null);

    const loadConversations = async () => {
      try {
        loadingConversations.value = true;
        const res = await api.get('/messenger/inbox', { params: { search: search.value } });
        if (res.success) conversations.value = res.data.data || [];
      } catch (err) {
        console.error('Failed to load conversations:', err);
      } finally {
        loadingConversations.value = false;
      }
    };

    const selectConversation = async (conv) => {
      selected.value = conv;
      try {
        loadingMessages.value = true;
        const res = await api.get(`/messenger/conversations/${conv.id}/messages`);
        if (res.success) {
          messages.value = res.data.messages.data || [];
          selected.value = res.data.conversation;
        }
      } catch (err) {
        console.error('Failed to load messages:', err);
      } finally {
        loadingMessages.value = false;
        nextTick(scrollToBottom);
      }
    };

    const sendMessage = async () => {
      if (!newMessage.value || !selected.value) return;
      try {
        sending.value = true;
        await api.post('/messenger/send-message', {
          conversation_id: selected.value.id,
          message: newMessage.value,
        });
        newMessage.value = '';
        await selectConversation(selected.value);
      } catch (err) {
        console.error('Failed to send message:', err);
        alert(err.data?.message || 'فشل إرسال الرسالة.');
      } finally {
        sending.value = false;
      }
    };

    const toggleAi = async () => {
      try {
        isTogglingAi.value = true;
        const res = await api.post(`/messenger/conversations/${selected.value.id}/toggle-ai`);
        if (res.success) {
          selected.value.ai_disabled = res.data.ai_disabled;
        }
      } catch (err) {
        console.error('Toggle AI failed:', err);
      } finally {
        isTogglingAi.value = false;
      }
    };

    const debounceSearch = () => {
      clearTimeout(searchTimeout.value);
      searchTimeout.value = setTimeout(loadConversations, 300);
    };

    const scrollToBottom = () => {
      if (messagesContainer.value) messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    };

    const formatDate = (d) => d ? new Date(d).toLocaleDateString('ar-EG') : '-';
    const formatTime = (d) => d ? new Date(d).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' }) : '-';

    loadConversations();

    return { conversations, loadingConversations, selected, messages, loadingMessages, newMessage, sending, search, isTogglingAi, messagesContainer, selectConversation, sendMessage, toggleAi, debounceSearch, formatDate, formatTime, t };
  },
};
</script>
