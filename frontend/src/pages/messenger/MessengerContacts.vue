<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('messenger_page.contacts') }}</h2>
          <p class="text-gray-500 text-sm">جهات اتصال Messenger المستخرجة من المحادثات الواردة.</p>
        </div>
      </div>

      <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
        <input v-model="search" @input="debounceSearch" type="text" :placeholder="t('contacts_page.search_placeholder')" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-2 text-sm text-gray-700 outline-none focus:border-[#0084FF]" />
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-right">
            <thead>
              <tr class="bg-gray-50/50 border-b border-gray-50">
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase">{{ t('contacts_page.th_name') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase">PSID</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase">{{ t('messenger_page.page_name') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase">{{ t('contacts_page.th_last_contact') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-if="loading" class="animate-pulse"><td colspan="4" class="px-6 py-12 text-center text-gray-400 font-medium">{{ t('common.loading') }}</td></tr>
              <tr v-else-if="contacts.length === 0"><td colspan="4" class="px-6 py-20 text-center text-gray-900 font-bold">{{ t('contacts_page.no_contacts') }}</td></tr>
              <tr v-else v-for="c in contacts" :key="c.id" class="hover:bg-gray-50/50 transition-all">
                <td class="px-6 py-4 text-sm font-black text-gray-900">{{ c.name || '-' }}</td>
                <td class="px-6 py-4 text-xs font-mono text-gray-500" dir="ltr">{{ c.psid }}</td>
                <td class="px-6 py-4 text-xs text-gray-500">{{ c.page?.page_name || '-' }}</td>
                <td class="px-6 py-4 text-xs text-gray-400 font-bold">{{ formatDate(c.last_message_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import api from '@/services/api';
import { t } from '@/i18n';

export default {
  name: 'MessengerContacts',
  components: { DashboardLayout },
  setup() {
    const contacts = ref([]);
    const loading = ref(true);
    const search = ref('');
    const searchTimeout = ref(null);

    const load = async () => {
      try {
        loading.value = true;
        const res = await api.get('/messenger/contacts', { params: { search: search.value } });
        if (res.success) contacts.value = res.data.data || [];
      } catch (err) {
        console.error('Failed to load Messenger contacts:', err);
      } finally {
        loading.value = false;
      }
    };

    const debounceSearch = () => {
      clearTimeout(searchTimeout.value);
      searchTimeout.value = setTimeout(load, 300);
    };

    const formatDate = (d) => d ? new Date(d).toLocaleDateString('ar-EG') : '-';

    load();

    return { contacts, loading, search, debounceSearch, formatDate, t };
  },
};
</script>
