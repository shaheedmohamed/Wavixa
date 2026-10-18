<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('contacts_page.title') }}</h2>
          <p class="text-gray-500 text-sm mt-1">{{ t('contacts_page.subtitle') }}</p>
        </div>
        <div class="flex items-center gap-3">
          <input ref="fileInput" type="file" accept=".csv,.xlsx,.xls,.vcf,.txt" @change="handleFile" class="hidden" />
          <button @click="downloadTemplate" class="flex items-center gap-2 px-3.5 py-2 bg-gray-100 text-gray-700 text-sm font-bold rounded-xl hover:bg-gray-200 transition-all active:scale-95" :title="isRTL ? 'تحميل نموذج ملف جهات الاتصال' : 'Download sample template'">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
            <span class="hidden sm:inline">{{ isRTL ? 'تحميل نموذج' : 'Download Template' }}</span>
          </button>
          <button @click="$refs.fileInput.click()" :disabled="importing" class="flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-bold rounded-xl hover:bg-emerald-700 transition-all shadow-sm active:scale-95 disabled:opacity-50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
            <span>{{ importing ? t('contacts_page.importing') : t('contacts_page.import_file') }}</span>
          </button>
          <button @click="openAdd" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-sm active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>{{ t('contacts_page.add_contact') }}</span>
          </button>
        </div>
      </div>

      <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex flex-col md:flex-row gap-4 items-center">
        <div class="relative flex-1 w-full">
          <svg class="absolute top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" :class="isRTL ? 'right-3' : 'left-3'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
          <input 
            v-model="search" 
            @input="debounceSearch" 
            type="text" 
            :placeholder="t('contacts_page.search_placeholder')" 
            class="w-full bg-gray-50 border border-gray-100 rounded-xl py-2 text-sm text-gray-700 placeholder-gray-400 focus:bg-white focus:border-indigo-300 transition-all outline-none" 
            :class="isRTL ? 'pr-10 pl-4' : 'pl-10 pr-4'"
          />
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full border-collapse" :class="isRTL ? 'text-right' : 'text-left'">
            <thead>
              <tr class="bg-gray-50/50 border-b border-gray-50">
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('contacts_page.th_name') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('contacts_page.th_phone') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('contacts_page.th_source') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('contacts_page.th_last_contact') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider" :class="isRTL ? 'text-left' : 'text-right'">{{ t('contacts_page.th_actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-if="loading" class="animate-pulse">
                <td colspan="5" class="px-6 py-12 text-center text-gray-400 font-medium">{{ t('contacts_page.loading_contacts') }}</td>
              </tr>
              <tr v-else-if="contacts.length === 0">
                <td colspan="5" class="px-6 py-20 text-center">
                  <div class="flex flex-col items-center">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4 text-gray-200">
                      <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    </div>
                    <p class="text-gray-900 font-bold">{{ t('contacts_page.no_contacts') }}</p>
                    <p class="text-gray-400 text-sm mt-1">{{ t('contacts_page.no_contacts_desc') }}</p>
                  </div>
                </td>
              </tr>
              <tr v-else v-for="contact in contacts" :key="contact.phone" class="hover:bg-gray-50/50 transition-all group">
                <td class="px-6 py-4">
                  <div v-if="editing && editing.phone === contact.phone" class="flex items-center gap-2">
                    <input v-model="editing.name" type="text" class="bg-gray-50 border border-gray-200 rounded-lg px-2 py-1 text-sm w-40" :placeholder="t('contacts_page.modal_name')" />
                  </div>
                  <p v-else class="text-sm font-black text-gray-900">{{ contact.name || '—' }}</p>
                </td>
                <td class="px-6 py-4">
                  <p class="text-sm font-black text-gray-900 font-mono" dir="ltr">{{ contact.phone }}</p>
                </td>
                <td class="px-6 py-4">
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase', sourceColorClass(contact.source)]">
                    {{ sourceLabel(contact.source) }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <p class="text-xs font-bold text-gray-400">{{ contact.last_contacted_at ? formatDate(contact.last_contacted_at) : '—' }}</p>
                </td>
                <td class="px-6 py-4" :class="isRTL ? 'text-left' : 'text-right'">
                  <div class="flex items-center gap-2" :class="isRTL ? 'justify-end' : 'justify-end'">
                    <template v-if="editing && editing.phone === contact.phone">
                      <button @click="saveEdit" :disabled="saving" class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-bold rounded-lg hover:bg-indigo-700 disabled:opacity-50">{{ t('common.save') }}</button>
                      <button @click="editing = null" class="px-3 py-1.5 bg-white border border-gray-200 text-gray-700 text-xs font-bold rounded-lg hover:bg-gray-50">{{ t('common.cancel') }}</button>
                    </template>
                    <template v-else>
                      <button @click="editContact(contact)" class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all" :title="t('contacts_page.edit_name')">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                      </button>
                      <button v-if="contact.id" @click="deleteContact(contact.id)" :disabled="deleting === contact.id" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all disabled:opacity-50" :title="t('contacts_page.delete')">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                      </button>
                    </template>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-if="showAdd" class="fixed inset-0 bg-black/20 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-in fade-in duration-200">
      <div class="bg-white border border-gray-100 rounded-3xl shadow-2xl max-w-md w-full p-6" :dir="isRTL ? 'rtl' : 'ltr'">
        <h3 class="text-lg font-black text-gray-900 mb-4">{{ t('contacts_page.modal_add_title') }}</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-gray-500 mb-1">{{ t('contacts_page.modal_name') }} ({{ t('common.optional') }})</label>
            <input v-model="newContact.name" type="text" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-2 text-sm focus:bg-white focus:border-indigo-300 outline-none" placeholder="e.g. John Doe" />
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-500 mb-1">{{ t('contacts_page.modal_phone') }}</label>
            <input v-model="newContact.phone" type="tel" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-2 text-sm focus:bg-white focus:border-indigo-300 outline-none font-mono" placeholder="e.g. 20101234567" dir="ltr" />
          </div>
          <p v-if="addError" class="text-xs font-bold text-red-600">{{ addError }}</p>
        </div>
        <div class="flex justify-end gap-3 mt-6">
          <button @click="showAdd = false" class="px-5 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-bold rounded-xl hover:bg-gray-100">{{ t('contacts_page.modal_cancel') }}</button>
          <button @click="addContact" :disabled="saving" class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 disabled:opacity-50">{{ t('contacts_page.modal_save') }}</button>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/services/api';
import auth from '@/services/auth';
import { t, isRTL } from '@/i18n';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

export default {
  name: 'Contacts',
  components: { DashboardLayout },
  setup() {
    const router = useRouter();
    const contacts = ref([]);
    const loading = ref(false);
    const saving = ref(false);
    const deleting = ref(null);
    const search = ref('');
    const showAdd = ref(false);
    const addError = ref('');
    const editing = ref(null);
    const newContact = ref({ name: '', phone: '' });
    const importing = ref(false);
    const fileInput = ref(null);
    let searchTimeout = null;

    onMounted(async () => {
      if (!auth.getUser()) {
        router.push('/login');
        return;
      }
      await loadContacts();
    });

    const loadContacts = async () => {
      loading.value = true;
      try {
        const params = {};
        if (search.value.trim()) params.search = search.value.trim();
        const response = await api.get('/contacts', { params });
        if (response.success) {
          contacts.value = response.data || [];
        }
      } catch (err) {
        console.error('Error loading contacts:', err);
      } finally {
        loading.value = false;
      }
    };

    const debounceSearch = () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => loadContacts(), 300);
    };

    const editContact = (contact) => {
      editing.value = { phone: contact.phone, name: contact.name || '' };
    };

    const saveEdit = async () => {
      saving.value = true;
      try {
        const response = await api.post('/contacts', { phone: editing.value.phone, name: editing.value.name });
        if (response.success) {
          editing.value = null;
          await loadContacts();
        } else {
          alert(response.message || (isRTL.value ? 'فشل الحفظ' : 'Failed to save'));
        }
      } catch (err) {
        alert(err.data?.message || (isRTL.value ? 'فشل الحفظ' : 'Failed to save'));
      } finally {
        saving.value = false;
      }
    };

    const handleFile = async (event) => {
      const file = event.target.files[0];
      if (!file) return;

      importing.value = true;
      try {
        const formData = new FormData();
        formData.append('file', file);
        const response = await api.upload('/contacts/import', formData);
        if (response.success) {
          const { imported = 0, updated = 0, invalid = 0 } = response.data || {};
          let msg = '';
          if (isRTL.value) {
            if (imported > 0 && updated > 0) {
              msg = `تم استيراد ${imported} جهة اتصال جديدة وتحديث ${updated} جهة اتصال موجودة.`;
            } else if (imported > 0) {
              msg = `تم استيراد ${imported} جهة اتصال بنجاح.`;
            } else if (updated > 0) {
              msg = `تم تحديث ${updated} جهة اتصال موجودة مسبقاً.`;
            } else {
              msg = 'تمت معالجة جهات الاتصال بنجاح.';
            }
            if (invalid > 0) {
              msg += ` (تم تجاهل ${invalid} رقم غير صالح)`;
            }
          } else {
            if (imported > 0 && updated > 0) {
              msg = `Imported ${imported} new contacts and updated ${updated} existing contacts.`;
            } else if (imported > 0) {
              msg = `Imported ${imported} contacts successfully.`;
            } else if (updated > 0) {
              msg = `Updated ${updated} existing contacts.`;
            } else {
              msg = 'Contacts processed successfully.';
            }
            if (invalid > 0) {
              msg += ` (${invalid} invalid numbers skipped)`;
            }
          }
          alert(msg);
          await loadContacts();
        } else {
          alert(response.message || (isRTL.value ? 'فشل الاستيراد' : 'Failed to import'));
        }
      } catch (err) {
        alert(err.data?.message || err.message || (isRTL.value ? 'فشل الاستيراد' : 'Failed to import'));
      } finally {
        importing.value = false;
        if (fileInput.value) fileInput.value.value = '';
      }
    };

    const downloadTemplate = () => {
      const csvContent = "\uFEFFالاسم,رقم الهاتف\nمحمد أحمد,201012345678\nأحمد علي,201112345678\nمحمود حسن,201212345678\n";
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement("a");
      const url = URL.createObjectURL(blob);
      link.setAttribute("href", url);
      link.setAttribute("download", "wavixa_contacts_template.csv");
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    };

    const openAdd = () => {
      newContact.value = { name: '', phone: '' };
      addError.value = '';
      showAdd.value = true;
    };

    const addContact = async () => {
      if (!newContact.value.phone.trim()) {
        addError.value = isRTL.value ? 'يرجى إدخال رقم الهاتف' : 'Please enter a phone number';
        return;
      }
      saving.value = true;
      addError.value = '';
      try {
        const response = await api.post('/contacts', newContact.value);
        if (response.success) {
          showAdd.value = false;
          newContact.value = { name: '', phone: '' };
          await loadContacts();
        } else {
          addError.value = response.message || (isRTL.value ? 'فشل الإضافة' : 'Failed to add');
        }
      } catch (err) {
        addError.value = err.data?.message || (isRTL.value ? 'فشل الإضافة' : 'Failed to add');
      } finally {
        saving.value = false;
      }
    };

    const deleteContact = async (id) => {
      if (!confirm(isRTL.value ? 'هل تريد حذف جهة الاتصال؟' : 'Are you sure you want to delete this contact?')) return;
      deleting.value = id;
      try {
        await api.delete(`/contacts/${id}`);
        await loadContacts();
      } catch (err) {
        alert(err.data?.message || (isRTL.value ? 'فشل الحذف' : 'Failed to delete'));
      } finally {
        deleting.value = null;
      }
    };

    const sourceLabel = (source) => {
      const labels = {
        manual: t('contacts_page.source_manual'),
        campaign: t('contacts_page.source_campaign'),
        message: t('contacts_page.source_message'),
        imported: t('contacts_page.source_import'),
      };
      return labels[source] || source;
    };

    const sourceColorClass = (source) => {
      const colors = { manual: 'bg-indigo-100 text-indigo-700', campaign: 'bg-amber-100 text-amber-700', message: 'bg-blue-100 text-blue-700', imported: 'bg-emerald-100 text-emerald-700' };
      return colors[source] || 'bg-gray-100 text-gray-700';
    };

    const formatDate = (date) => {
      if (!date) return '—';
      return new Date(date).toLocaleString(isRTL.value ? 'ar-EG' : 'en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    };

    return {
      contacts,
      loading,
      saving,
      deleting,
      search,
      showAdd,
      addError,
      editing,
      newContact,
      importing,
      fileInput,
      loadContacts,
      debounceSearch,
      editContact,
      saveEdit,
      openAdd,
      addContact,
      handleFile,
      downloadTemplate,
      deleteContact,
      sourceLabel,
      sourceColorClass,
      formatDate,
      t,
      isRTL,
    };
  },
};
</script>

<style scoped>
.animate-in {
  animation: animate-in 0.3s ease-out;
}

@keyframes animate-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
