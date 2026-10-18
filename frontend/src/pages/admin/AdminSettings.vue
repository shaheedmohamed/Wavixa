<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Header -->
      <div class="pb-2 border-b border-slate-200/60">
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">إعدادات النظام</h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-0.5">إدارة تهيئة المنصة والقيم العامة</p>
      </div>

      <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-xs">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div v-for="setting in settings" :key="setting.id" class="space-y-2 p-4 bg-slate-50/50 rounded-xl border border-slate-100">
            <label class="block text-xs font-bold text-slate-900">{{ setting.label || setting.key }}</label>
            <p class="text-[10px] text-slate-500">{{ setting.description }}</p>
            <input 
              v-if="setting.type === 'string'" 
              v-model="setting.value" 
              @change="saveSetting(setting)" 
              type="text" 
              class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition-all" 
            />
            <input 
              v-else-if="setting.type === 'integer'" 
              v-model.number="setting.value" 
              @change="saveSetting(setting)" 
              type="number" 
              class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition-all" 
            />
            <select 
              v-else-if="setting.type === 'boolean'" 
              v-model="setting.value" 
              @change="saveSetting(setting)" 
              class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition-all"
            >
              <option :value="true">مفعل</option>
              <option :value="false">معطل</option>
            </select>
            <textarea 
              v-else-if="setting.type === 'json'" 
              v-model="setting.value" 
              @change="saveSetting(setting)" 
              rows="3" 
              class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition-all font-mono"
            ></textarea>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script>
import { ref, onMounted } from 'vue';
import admin from '@/services/admin';
import AdminLayout from '@/layouts/AdminLayout.vue';

export default {
  name: 'AdminSettings',
  components: { AdminLayout },
  setup() {
    const settings = ref([]);

    const load = async () => {
      try {
        const response = await admin.getSettings();
        if (response.success) {
          settings.value = response.data.map(s => ({
            ...s,
            value: s.type === 'boolean' ? (s.value === '1' || s.value === 'true' || s.value === true) : s.value,
          }));
        }
      } catch (err) { console.error(err); }
    };

    const saveSetting = async (setting) => {
      try {
        let value = setting.value;
        if (setting.type === 'boolean') value = value ? '1' : '0';
        if (setting.type === 'json') {
          try { JSON.parse(value); } catch { alert('بيانات JSON غير صالحة'); return; }
        }
        await admin.updateSetting({
          key: setting.key,
          value,
          type: setting.type,
          group: setting.group,
          label: setting.label,
          description: setting.description,
        });
      } catch (err) { console.error(err); }
    };

    onMounted(load);

    return { settings, saveSetting };
  },
};
</script>
