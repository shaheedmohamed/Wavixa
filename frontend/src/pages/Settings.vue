<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('settings_page.title') }}</h2>
          <p class="text-gray-500 text-sm mt-1">{{ t('settings_page.subtitle') }}</p>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-20 bg-white rounded-3xl border border-gray-100 shadow-sm">
        <div class="w-10 h-10 border-4 border-indigo-100 border-t-indigo-600 rounded-full animate-spin"></div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-50 border border-red-100 rounded-2xl p-4 flex items-center gap-3 text-red-600 animate-in slide-in-from-bottom-2">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <p class="text-sm font-bold">{{ error }}</p>
      </div>

      <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Sidebar Navigation -->
        <div class="lg:col-span-1 space-y-2">
          <button 
            v-for="section in sections" 
            :key="section.id"
            @click="activeSection = section.id"
            class="w-full px-4 py-3 rounded-xl font-bold text-sm transition-all"
            :class="[isRTL ? 'text-right' : 'text-left', activeSection === section.id ? 'bg-indigo-50 text-indigo-600 shadow-sm' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900']"
          >
            {{ section.label }}
          </button>
        </div>

        <!-- Main Content Area -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Profile Section -->
          <div v-if="activeSection === 'profile'" class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden animate-in slide-in-from-left-4">
            <div class="px-8 py-6 border-b border-gray-50 bg-gray-50/30">
              <h3 class="text-lg font-black text-gray-900">{{ t('settings_page.profile_title') }}</h3>
            </div>
            <div class="p-8 space-y-8">
              <!-- Avatar -->
              <div class="flex items-center gap-6">
                <div class="relative group">
                  <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-3xl font-black shadow-lg overflow-hidden border-4 border-white">
                    <img v-if="avatarUrl" :src="avatarUrl" alt="Avatar" class="w-full h-full object-cover" />
                    <span v-else>{{ initials }}</span>
                  </div>
                  <label class="absolute -bottom-2 -right-2 w-8 h-8 bg-white text-indigo-600 rounded-xl shadow-lg border border-gray-100 flex items-center justify-center cursor-pointer hover:scale-110 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    <input type="file" accept="image/*" class="hidden" @change="uploadAvatar" />
                  </label>
                </div>
                <div>
                  <h4 class="text-sm font-black text-gray-900 mb-1">{{ t('settings_page.avatar_title') }}</h4>
                  <p class="text-xs text-gray-400 font-medium mb-3">{{ t('settings_page.avatar_hint') }}</p>
                  <button v-if="avatarUrl" @click="removeAvatar" class="text-xs font-black text-red-500 hover:underline">{{ t('settings_page.remove_avatar') }}</button>
                </div>
              </div>

              <!-- Form -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                  <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ t('settings_page.full_name') }}</label>
                  <input v-model="profile.name" type="text" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-3 text-gray-900 font-bold focus:bg-white focus:border-indigo-300 transition-all outline-none" />
                </div>
                <div class="space-y-2">
                  <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ t('settings_page.email') }}</label>
                  <input v-model="profile.email" type="email" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-3 text-gray-900 font-bold focus:bg-white focus:border-indigo-300 transition-all outline-none" />
                </div>
                <div class="space-y-2">
                  <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ t('settings_page.phone') }}</label>
                  <input v-model="profile.phone_number" type="text" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-3 text-gray-900 font-bold focus:bg-white focus:border-indigo-300 transition-all outline-none font-mono" dir="ltr" />
                </div>
                <div class="space-y-2">
                  <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ t('settings_page.language') }}</label>
                  <select v-model="profile.language" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-3 text-gray-900 font-bold focus:bg-white focus:border-indigo-300 transition-all outline-none">
                    <option value="en">English (EN)</option>
                    <option value="ar">العربية (AR)</option>
                  </select>
                </div>
                <div class="md:col-span-2 space-y-2">
                  <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ t('settings_page.timezone') }}</label>
                  <select v-model="profile.timezone" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-3 text-gray-900 font-bold focus:bg-white focus:border-indigo-300 transition-all outline-none" dir="ltr">
                    <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                  </select>
                </div>
              </div>

              <div class="flex justify-end pt-4">
                <button
                  @click="saveProfile"
                  :disabled="saving.profile"
                  class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-8 py-3 rounded-2xl font-black text-sm transition-all shadow-lg shadow-indigo-100 active:scale-95"
                >
                  {{ saving.profile ? t('common.saving') : t('settings_page.save_changes') }}
                </button>
              </div>
            </div>
          </div>

          <!-- Security Section -->
          <div v-if="activeSection === 'security'" class="space-y-6 animate-in slide-in-from-left-4">
            <!-- Change Password -->
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
              <div class="px-8 py-6 border-b border-gray-50 bg-gray-50/30">
                <h3 class="text-lg font-black text-gray-900">{{ t('settings_page.security_title') }}</h3>
              </div>
              <div class="p-8 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                  <div class="space-y-2">
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ t('settings_page.current_password') }}</label>
                    <input v-model="password.current_password" type="password" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-3 text-gray-900 font-bold focus:bg-white focus:border-indigo-300 transition-all outline-none" />
                  </div>
                  <div class="space-y-2">
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ t('settings_page.new_password') }}</label>
                    <input v-model="password.password" type="password" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-3 text-gray-900 font-bold focus:bg-white focus:border-indigo-300 transition-all outline-none" />
                  </div>
                  <div class="space-y-2">
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ t('settings_page.confirm_password') }}</label>
                    <input v-model="password.password_confirmation" type="password" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-3 text-gray-900 font-bold focus:bg-white focus:border-indigo-300 transition-all outline-none" />
                  </div>
                </div>
                <div class="flex justify-end pt-4">
                  <button
                    @click="changePassword"
                    :disabled="saving.password"
                    class="bg-gray-900 hover:bg-black disabled:opacity-50 text-white px-8 py-3 rounded-2xl font-black text-sm transition-all active:scale-95"
                  >
                    {{ saving.password ? t('common.saving') : t('settings_page.update_password') }}
                  </button>
                </div>
              </div>
            </div>

            <!-- Active Devices -->
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
              <div class="px-8 py-6 border-b border-gray-50 bg-gray-50/30 flex items-center justify-between">
                <h3 class="text-lg font-black text-gray-900">{{ isRTL ? 'الأجهزة النشطة' : 'Active Devices' }}</h3>
                <button
                  @click="revokeOtherDevices"
                  :disabled="saving.devices"
                  class="text-xs font-black text-red-500 hover:bg-red-50 px-4 py-2 rounded-xl transition-all"
                >
                  {{ isRTL ? 'تسجيل الخروج من كل الأجهزة الأخرى' : 'Log out from all other devices' }}
                </button>
              </div>
              <div class="p-4">
                <div v-if="devicesLoading" class="py-8 text-center animate-pulse text-gray-400 font-bold">{{ t('common.loading') }}</div>
                <div v-else-if="devices.length === 0" class="py-12 text-center text-gray-400 font-bold">{{ isRTL ? 'لا يوجد أجهزة نشطة حالياً' : 'No other active devices' }}</div>
                <div v-else class="space-y-2">
                  <div v-for="device in devices" :key="device.id" class="flex items-center justify-between p-5 rounded-2xl bg-gray-50 border border-gray-50 hover:border-indigo-100 transition-all group">
                    <div class="flex items-center gap-4">
                      <div class="w-12 h-12 bg-white text-gray-400 rounded-xl flex items-center justify-center shadow-sm border border-gray-100">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                      </div>
                      <div>
                        <div class="flex items-center gap-2 mb-1">
                          <p class="text-sm font-black text-gray-900">{{ device.name }}</p>
                          <span v-if="device.is_current" class="px-2 py-0.5 rounded-full text-[9px] font-black bg-indigo-50 text-indigo-600 uppercase tracking-widest">{{ isRTL ? 'الحالي' : 'Current' }}</span>
                        </div>
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-tighter">{{ device.ip_address }} · {{ formatDate(device.last_active_at) }}</p>
                      </div>
                    </div>
                    <button v-if="!device.is_current" @click="revokeDevice(device.id)" :disabled="saving.devices" class="p-2 text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-xl transition-all opacity-0 group-hover:opacity-100" title="Revoke">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Preferences Section -->
          <div v-if="activeSection === 'preferences'" class="space-y-6 animate-in slide-in-from-left-4">
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
              <div class="px-8 py-6 border-b border-gray-50 bg-gray-50/30">
                <h3 class="text-lg font-black text-gray-900">{{ t('settings_page.notifications_title') }}</h3>
              </div>
              <div class="p-8 space-y-4">
                <label v-for="(val, key) in notifications" :key="key" class="flex items-center justify-between p-5 rounded-2xl bg-gray-50 border border-gray-50 hover:bg-white hover:border-indigo-100 transition-all cursor-pointer group">
                  <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-white text-gray-400 rounded-xl flex items-center justify-center border border-gray-100 group-hover:text-indigo-600 transition-colors">
                      <svg v-if="key === 'email'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                      <svg v-else-if="key === 'security'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                      <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 003.5 0V5.882M17 9.176V19.24a1.76 1.76 0 003.5 0V9.176M5 14.294V19.24a1.76 1.76 0 003.5 0v-4.946" /></svg>
                    </div>
                    <div>
                      <p class="text-sm font-black text-gray-900 capitalize">{{ notificationLabel(key) }}</p>
                      <p class="text-[10px] text-gray-400 font-bold uppercase tracking-tighter">{{ notificationDesc(key) }}</p>
                    </div>
                  </div>
                  <input type="checkbox" v-model="notifications[key]" class="w-6 h-6 rounded-lg border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                </label>

                <div class="flex justify-end pt-4">
                  <button
                    @click="saveNotifications"
                    :disabled="saving.notifications"
                    class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-8 py-3 rounded-2xl font-black text-sm transition-all shadow-lg shadow-indigo-100 active:scale-95"
                  >
                    {{ saving.notifications ? t('common.saving') : t('settings_page.save_changes') }}
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- History Section -->
          <div v-if="activeSection === 'history'" class="space-y-6 animate-in slide-in-from-left-4">
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
              <div class="px-8 py-6 border-b border-gray-50 bg-gray-50/30">
                <h3 class="text-lg font-black text-gray-900">{{ isRTL ? 'سجل تسجيل الدخول' : 'Login Audit History' }}</h3>
              </div>
              <div class="overflow-x-auto">
                <table class="w-full border-collapse" :class="isRTL ? 'text-right' : 'text-left'">
                  <thead>
                    <tr class="bg-gray-50/50">
                      <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ t('common.device') }}</th>
                      <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">IP Address</th>
                      <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ t('common.date') }}</th>
                      <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ t('common.status') }}</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-50">
                    <tr v-if="historyLoading" class="animate-pulse">
                      <td colspan="4" class="px-6 py-8 text-center text-gray-400 font-bold">{{ t('common.loading') }}</td>
                    </tr>
                    <tr v-else-if="loginHistory.length === 0">
                      <td colspan="4" class="px-6 py-12 text-center text-gray-400 font-bold">{{ isRTL ? 'لا يوجد سجل دخول متاح' : 'No login history available' }}</td>
                    </tr>
                    <tr v-for="entry in loginHistory" :key="entry.id" class="hover:bg-gray-50/50 transition-colors">
                      <td class="px-6 py-4 text-xs font-black text-gray-900">{{ entry.device_name || 'Unknown' }}</td>
                      <td class="px-6 py-4 text-xs font-bold text-gray-400 font-mono" dir="ltr">{{ entry.ip_address || '-' }}</td>
                      <td class="px-6 py-4 text-xs font-bold text-gray-400">{{ formatDate(entry.login_at) }}</td>
                      <td class="px-6 py-4">
                        <span :class="['px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest', entry.success ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600']">
                          {{ entry.success ? t('common.success') : t('common.failed') }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Product Tour Section -->
          <div v-if="activeSection === 'tour'" class="animate-in slide-in-from-left-4">
            <div class="bg-indigo-50 border border-indigo-100 rounded-3xl p-8 space-y-6">
              <div>
                <h3 class="text-lg font-black text-indigo-900 mb-2">{{ isRTL ? 'جولة تعريفية' : 'Product Tour' }}</h3>
                <p class="text-sm text-indigo-600 font-medium">{{ isRTL ? 'هل تريد مشاهدة جولة سريعة داخل المنصة مرة أخرى؟' : 'Would you like to take the quick platform tour again?' }}</p>
              </div>
              <button
                @click="startTour"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-3 rounded-2xl font-black text-sm transition-all active:scale-95 shadow-lg shadow-indigo-100"
              >
                {{ isRTL ? 'إعادة تشغيل الجولة' : 'Restart Tour' }}
              </button>
            </div>
          </div>

          <!-- Danger Zone Section -->
          <div v-if="activeSection === 'danger'" class="animate-in slide-in-from-left-4">
            <div class="bg-red-50 border border-red-100 rounded-3xl p-8 space-y-6">
              <div>
                <h3 class="text-lg font-black text-red-900 mb-2">{{ isRTL ? 'منطقة الخطر' : 'Danger Zone' }}</h3>
                <p class="text-sm text-red-600 font-medium">{{ isRTL ? 'بمجرد حذف حسابك، سيتم مسح كافة البيانات والجلسات والرسائل نهائياً. لا يمكن التراجع عن هذا الإجراء.' : 'Deleting your account is permanent. All WhatsApp sessions, messages, and API keys will be deleted immediately.' }}</p>
              </div>
              <button
                @click="showDeleteModal = true"
                class="bg-red-600 hover:bg-red-700 text-white px-8 py-3 rounded-2xl font-black text-sm transition-all active:scale-95 shadow-lg shadow-red-100"
              >
                {{ isRTL ? 'حذف الحساب نهائياً' : 'Delete Account Permanently' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Account Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 bg-black/20 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-in fade-in duration-200">
      <div class="bg-white border border-gray-100 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden" :dir="isRTL ? 'rtl' : 'ltr'">
        <div class="p-8 text-center space-y-6">
          <div class="w-16 h-16 bg-red-50 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4 border border-red-100">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
          </div>
          <div>
            <h3 class="text-xl font-black text-gray-900 mb-2">{{ isRTL ? 'تأكيد حذف الحساب' : 'Confirm Account Deletion' }}</h3>
            <p class="text-sm text-gray-500 font-medium">{{ isRTL ? 'يرجى إدخال كلمة المرور الحالية لتأكيد طلب حذف الحساب.' : 'Please enter your current password to confirm account deletion.' }}</p>
          </div>
          <input
            v-model="deletePassword"
            type="password"
            :placeholder="t('settings_page.current_password')"
            class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-3 text-gray-900 font-bold placeholder-gray-400 focus:bg-white focus:border-indigo-300 transition-all outline-none"
          />
          <div class="flex gap-3">
            <button
              @click="deleteAccount"
              :disabled="!deletePassword || saving.delete"
              class="flex-1 bg-red-600 text-white px-6 py-3 rounded-2xl font-black text-sm hover:bg-red-700 transition-all disabled:opacity-50"
            >
              {{ saving.delete ? t('common.loading') : (isRTL ? 'حذف نهائي' : 'Delete Permanently') }}
            </button>
            <button
              @click="showDeleteModal = false"
              class="flex-1 bg-white border border-gray-200 text-gray-700 px-6 py-3 rounded-2xl font-black text-sm hover:bg-gray-50 transition-all"
            >
              {{ t('common.cancel') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/services/api';
import auth from '@/services/auth';
import { t, isRTL } from '@/i18n';
import { useOnboarding } from '@/composables/useOnboarding';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

export default {
  name: 'Settings',
  components: { DashboardLayout },
  setup() {
    const router = useRouter();
    const { restart } = useOnboarding();
    const startTour = async () => { await restart(); };
    const user = ref(auth.getUser());
    const loading = ref(true);
    const error = ref('');
    const activeSection = ref('profile');
    const showDeleteModal = ref(false);
    const deletePassword = ref('');

    const profile = ref({
      name: '',
      email: '',
      phone_number: '',
      language: 'en',
      timezone: 'UTC',
    });

    const password = ref({
      current_password: '',
      password: '',
      password_confirmation: '',
    });

    const notifications = ref({
      email: true,
      security: true,
      marketing: false,
    });

    const sections = computed(() => [
      { id: 'profile', label: t('settings_page.tab_profile') },
      { id: 'security', label: t('settings_page.tab_security') },
      { id: 'preferences', label: t('settings_page.tab_preferences') },
      { id: 'history', label: isRTL.value ? 'سجل الدخول' : 'Login Audit' },
      { id: 'tour', label: isRTL.value ? 'جولة تعريفية' : 'Product Tour' },
      { id: 'danger', label: isRTL.value ? 'منطقة الخطر' : 'Danger Zone' },
    ]);

    const baseUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';
    const assetBaseUrl = baseUrl.replace('/api', '');

    const avatarUrl = ref('');
    const devices = ref([]);
    const loginHistory = ref([]);
    const devicesLoading = ref(false);
    const historyLoading = ref(false);

    const saving = ref({
      profile: false,
      password: false,
      notifications: false,
      devices: false,
      delete: false,
    });

    const timezones = [
      'UTC', 'Africa/Cairo', 'Africa/Casablanca', 'Africa/Tunis', 'Africa/Algiers', 
      'Asia/Riyadh', 'Asia/Dubai', 'Asia/Kuwait', 'Asia/Qatar', 'Asia/Amman',
      'America/New_York', 'America/Los_Angeles', 'Europe/London', 'Europe/Paris'
    ];

    const initials = computed(() => {
      const name = profile.value.name || user.value?.name || '';
      return name.split(' ').map((n) => n[0]).join('').toUpperCase().slice(0, 2);
    });

    const loadSettings = async () => {
      try {
        loading.value = true;
        error.value = '';
        const response = await api.get('/settings');
        if (response.success) {
          const data = response.data.user;
          user.value = data;
          auth.setUser(data);
          profile.value = {
            name: data.name || '',
            email: data.email || '',
            phone_number: data.phone_number || '',
            language: data.language || 'en',
            timezone: data.timezone || 'UTC',
          };
          avatarUrl.value = data.avatar ? `${assetBaseUrl}/storage/${data.avatar}` : '';
          notifications.value = {
            email: response.data.preferences?.notifications?.email ?? true,
            security: response.data.preferences?.notifications?.security ?? true,
            marketing: response.data.preferences?.notifications?.marketing ?? false,
          };
        }
      } catch (err) {
        error.value = err.data?.message || (isRTL.value ? 'فشل في تحميل الإعدادات' : 'Failed to load settings');
      } finally {
        loading.value = false;
      }
    };

    const loadDevices = async () => {
      try {
        devicesLoading.value = true;
        const response = await api.get('/settings/devices');
        if (response.success) devices.value = response.data || [];
      } catch (err) {
        console.error('Failed to load devices:', err);
      } finally {
        devicesLoading.value = false;
      }
    };

    const loadLoginHistory = async () => {
      try {
        historyLoading.value = true;
        const response = await api.get('/settings/login-history');
        if (response.success) loginHistory.value = response.data?.data || [];
      } catch (err) {
        console.error('Failed to load login history:', err);
      } finally {
        historyLoading.value = false;
      }
    };

    const saveProfile = async () => {
      try {
        saving.value.profile = true;
        error.value = '';
        const response = await api.put('/settings/profile', profile.value);
        if (response.success) {
          auth.setUser(response.data);
          user.value = response.data;
          alert(isRTL.value ? 'تم حفظ الملف الشخصي بنجاح' : 'Profile updated successfully');
        }
      } catch (err) {
        error.value = err.data?.message || (isRTL.value ? 'فشل في حفظ التعديلات' : 'Failed to update profile');
      } finally {
        saving.value.profile = false;
      }
    };

    const uploadAvatar = async (event) => {
      const file = event.target.files[0];
      if (!file) return;
      try {
        const formData = new FormData();
        formData.append('avatar', file);
        const response = await api.request('/settings/avatar', {
          method: 'POST',
          body: formData,
        });
        if (response.success) {
          avatarUrl.value = response.data.avatar_url;
          await loadSettings();
        }
      } catch (err) {
        error.value = err.data?.message || (isRTL.value ? 'فشل في رفع الصورة' : 'Failed to upload avatar');
      }
    };

    const removeAvatar = async () => {
      if (!confirm(isRTL.value ? 'هل أنت متأكد من حذف الصورة؟' : 'Are you sure you want to remove your avatar?')) return;
      try {
        const response = await api.delete('/settings/avatar');
        if (response.success) {
          avatarUrl.value = '';
          await loadSettings();
        }
      } catch (err) {
        error.value = err.data?.message || (isRTL.value ? 'فشل في حذف الصورة' : 'Failed to remove avatar');
      }
    };

    const changePassword = async () => {
      try {
        saving.value.password = true;
        const response = await api.post('/settings/change-password', password.value);
        if (response.success) {
          password.value = { current_password: '', password: '', password_confirmation: '' };
          alert(isRTL.value ? 'تم تغيير كلمة المرور بنجاح' : 'Password changed successfully');
        }
      } catch (err) {
        error.value = err.data?.message || (isRTL.value ? 'فشل في تغيير كلمة المرور' : 'Failed to change password');
      } finally {
        saving.value.password = false;
      }
    };

    const saveNotifications = async () => {
      try {
        saving.value.notifications = true;
        const response = await api.put('/settings/notifications', notifications.value);
        if (response.success) alert(isRTL.value ? 'تم حفظ تفضيلات التنبيهات' : 'Notification preferences saved');
      } catch (err) {
        error.value = err.data?.message || (isRTL.value ? 'فشل في حفظ التنبيهات' : 'Failed to save notifications');
      } finally {
        saving.value.notifications = false;
      }
    };

    const revokeDevice = async (id) => {
      if (!confirm(isRTL.value ? 'هل أنت متأكد من تسجيل الخروج من هذا الجهاز؟' : 'Are you sure you want to revoke this session?')) return;
      try {
        saving.value.devices = true;
        const response = await api.delete(`/settings/devices/${id}`);
        if (response.success) await loadDevices();
      } catch (err) {
        error.value = err.data?.message || (isRTL.value ? 'فشل في تسجيل الخروج' : 'Failed to revoke device');
      } finally {
        saving.value.devices = false;
      }
    };

    const revokeOtherDevices = async () => {
      if (!confirm(isRTL.value ? 'هل أنت متأكد من تسجيل الخروج من كافة الأجهزة الأخرى؟' : 'Are you sure you want to log out of all other sessions?')) return;
      try {
        saving.value.devices = true;
        const response = await api.post('/settings/devices/revoke-others', {});
        if (response.success) await loadDevices();
      } catch (err) {
        error.value = err.data?.message || (isRTL.value ? 'فشل في تسجيل الخروج' : 'Failed to log out others');
      } finally {
        saving.value.devices = false;
      }
    };

    const deleteAccount = async () => {
      try {
        saving.value.delete = true;
        const response = await api.delete('/settings/account', {
          body: JSON.stringify({ password: deletePassword.value }),
        });
        if (response.success) {
          auth.logout();
          router.push('/login');
        }
      } catch (err) {
        error.value = err.data?.message || (isRTL.value ? 'فشل في حذف الحساب' : 'Failed to delete account');
      } finally {
        saving.value.delete = false;
      }
    };

    const notificationLabel = (key) => {
      const labels = isRTL.value ? { email: 'تنبيهات البريد الإلكتروني', security: 'تنبيهات الأمان', marketing: 'رسائل تسويقية' } : { email: 'Email Notifications', security: 'Security Alerts', marketing: 'Product & Marketing Updates' };
      return labels[key] || key;
    };

    const notificationDesc = (key) => {
      const descs = isRTL.value ? { email: 'استلام تحديثات الحساب والتقارير عبر البريد', security: 'إشعارات عند تسجيل دخول جديد أو تغيير كلمة المرور', marketing: 'الحصول على آخر العروض والمزايا الجديدة' } : { email: 'Receive summary reports and account updates via email', security: 'Receive instant notifications for new logins and password updates', marketing: 'Receive new feature announcements and product tips' };
      return descs[key] || '';
    };

    const formatDate = (date) => {
      if (!date) return '-';
      return new Date(date).toLocaleDateString(isRTL.value ? 'ar-EG' : 'en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    };

    onMounted(async () => {
      await loadSettings();
      await loadDevices();
      await loadLoginHistory();
    });

    return {
      user,
      loading,
      error,
      activeSection,
      sections,
      profile,
      password,
      notifications,
      avatarUrl,
      devices,
      loginHistory,
      devicesLoading,
      historyLoading,
      saving,
      showDeleteModal,
      deletePassword,
      initials,
      timezones,
      saveProfile,
      uploadAvatar,
      removeAvatar,
      changePassword,
      saveNotifications,
      revokeDevice,
      revokeOtherDevices,
      deleteAccount,
      notificationLabel,
      notificationDesc,
      formatDate,
      startTour,
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
