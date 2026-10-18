<template>
  <DashboardLayout>
    <div class="space-y-8 animate-in fade-in duration-500">
      <!-- Welcome Celebration Overlay -->
      <Transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div v-if="showWelcome" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/20 backdrop-blur-sm">
          <div class="bg-white rounded-3xl p-8 max-w-sm w-full text-center shadow-2xl border border-indigo-50 relative overflow-hidden">
            <!-- Animated Background Gradient -->
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-50/50 via-white to-purple-50/50 -z-10 animate-pulse"></div>
            
            <div class="w-24 h-24 bg-indigo-600 text-white rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg shadow-indigo-200 animate-bounce">
              <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-7.714 2.143L11 21l-2.286-6.857L1 12l7.714-2.143L11 3z" />
              </svg>
            </div>
            
            <h3 class="text-2xl font-black text-gray-900 mb-2">{{ t('dashboard.welcome_title') }}</h3>
            <p class="text-gray-500 font-medium mb-8 leading-relaxed">
              {{ t('dashboard.welcome_desc') }}
            </p>
            
            <button 
              @click="showWelcome = false"
              class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-4 rounded-2xl font-black text-lg shadow-lg shadow-indigo-100 transition-all active:scale-95"
            >
              {{ t('dashboard.welcome_start') }}
            </button>
          </div>
        </div>
      </Transition>

      <!-- Welcome Header -->
      <div data-tour="dashboard-header" class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">{{ t('dashboard.title') }}</h2>
          <p class="text-slate-500 text-xs sm:text-sm mt-0.5">
            {{ isRTL ? `مرحباً بك مجدداً، ${user?.name || ''}! إليك ملخص لأداء أعمالك اليوم.` : `Welcome back, ${user?.name || ''}! Here is a summary of your performance today.` }}
          </p>
        </div>
        <div class="flex items-center gap-2">
          <div class="bg-white border border-slate-200/80 rounded-xl px-3.5 py-1.5 flex items-center gap-2 text-xs font-bold text-slate-700 shadow-2xs">
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
            <span>{{ t('dashboard.last_7_days') }}</span>
          </div>
        </div>
      </div>

      <!-- Stats Grid -->
      <div data-tour="dashboard-stats-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
        <div v-for="stat in stats" :key="stat.label" class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-2xs hover:shadow-xs transition-all group">
          <div class="flex items-center justify-between mb-3">
            <div :class="['w-9 h-9 rounded-xl flex items-center justify-center transition-colors', stat.bg, stat.color]">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="stat.icon"></svg>
            </div>
            <span v-if="stat.change" :class="['text-[10px] font-black px-2 py-0.5 rounded-full', stat.change > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-slate-50 text-slate-400 border border-slate-100']">
              {{ stat.change > 0 ? '+' : '' }}{{ stat.change }}%
            </span>
          </div>
          <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">{{ stat.label }}</p>
          <div class="flex items-baseline gap-2">
            <h3 class="text-xl sm:text-2xl font-black text-slate-900 leading-none">{{ stat.value }}</h3>
          </div>
        </div>
      </div>

      <!-- Main Performance Section -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Performance Chart -->
        <div data-tour="dashboard-chart" class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden flex flex-col">
          <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-white sticky top-0 z-10">
            <div>
              <h3 class="font-black text-slate-900 text-sm sm:text-base">{{ t('dashboard.messages_performance') }}</h3>
              <p class="text-[11px] text-slate-400 font-medium">{{ t('dashboard.performance_subtitle') }}</p>
            </div>
            <div class="flex gap-3">
              <div v-for="item in legend" :key="item.label" class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: item.color }"></span>
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-tight">{{ item.label }}</span>
              </div>
            </div>
          </div>
          <div class="p-4 sm:p-5 flex-1 min-h-[280px]">
            <canvas ref="lineChart"></canvas>
          </div>
        </div>

        <!-- Devices List -->
        <div data-tour="dashboard-devices-card" class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs flex flex-col">
          <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-black text-slate-900 text-sm sm:text-base">{{ t('dashboard.connected_devices') }}</h3>
            <router-link to="/devices" class="text-xs font-bold text-indigo-600 hover:text-indigo-700">{{ t('dashboard.manage_all') }}</router-link>
          </div>
          <div class="p-3.5 flex-1 space-y-2.5 overflow-y-auto max-h-[380px] custom-scrollbar">
            <div v-for="num in numbers" :key="num.id" class="p-3 rounded-xl border border-slate-100 bg-slate-50/60 hover:bg-white hover:border-slate-200 transition-all group">
              <div class="flex items-center gap-2.5 mb-2.5">
                <div :class="['w-2 h-2 rounded-full animate-pulse', num.status === 'connected' ? 'bg-emerald-500' : 'bg-red-500']"></div>
                <div class="flex-1">
                  <p class="text-xs font-black text-slate-900 font-mono" dir="ltr">{{ num.number || t('dashboard.unknown_number') }}</p>
                  <p class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">{{ num.label || 'WhatsApp' }}</p>
                </div>
              </div>
              <div class="grid grid-cols-3 gap-1.5 bg-white p-2 rounded-lg border border-slate-100">
                <div class="text-center">
                  <span class="block text-xs font-black text-slate-900">{{ num.sent }}</span>
                  <span class="text-[8px] font-bold text-slate-400 uppercase">{{ t('dashboard.sent_stat') }}</span>
                </div>
                <div class="text-center">
                  <span class="block text-xs font-black text-slate-900">{{ num.delivered }}</span>
                  <span class="text-[8px] font-bold text-slate-400 uppercase">{{ t('dashboard.delivered_stat') }}</span>
                </div>
                <div class="text-center">
                  <span class="block text-xs font-black text-slate-900">{{ num.read }}</span>
                  <span class="text-[8px] font-bold text-slate-400 uppercase">{{ t('dashboard.read_stat') }}</span>
                </div>
              </div>
            </div>
            <div v-if="numbers.length === 0" class="flex flex-col items-center justify-center py-10 text-center">
              <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center mb-2.5 text-slate-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
              </div>
              <p class="text-xs font-bold text-slate-400">{{ t('dashboard.no_devices') }}</p>
              <router-link to="/devices" class="text-xs font-bold text-indigo-600 mt-1 hover:underline">{{ t('dashboard.add_first_device') }}</router-link>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom Activity Section -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <!-- Message Types Donut -->
        <div data-tour="dashboard-message-types" class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-5">
          <h3 class="font-black text-slate-900 text-sm sm:text-base mb-5">{{ t('dashboard.message_types_dist') }}</h3>
          <div class="flex flex-col sm:flex-row items-center gap-6">
            <div class="w-36 h-36 flex-shrink-0">
              <canvas ref="donutChart"></canvas>
            </div>
            <div class="flex-1 grid grid-cols-2 gap-3 w-full">
              <div v-for="item in messagesByType" :key="item.label" class="p-2 rounded-xl bg-slate-50/70 border border-slate-100 flex items-center gap-2.5">
                <span class="w-2.5 h-2.5 rounded-sm" :style="{ backgroundColor: item.color }"></span>
                <div>
                  <p class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">{{ item.label }}</p>
                  <p class="text-xs font-black text-slate-900">{{ item.value }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Activity -->
        <div data-tour="dashboard-activity" class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs">
          <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-black text-slate-900 text-sm sm:text-base">{{ t('dashboard.recent_activity') }}</h3>
            <router-link to="/messages" class="text-xs font-bold text-indigo-600 hover:text-indigo-700">{{ t('dashboard.messages_log') }}</router-link>
          </div>
          <div class="divide-y divide-slate-100 max-h-[280px] overflow-y-auto custom-scrollbar">
            <div v-for="activity in recentActivity" :key="activity.id" class="px-5 py-3.5 flex items-center gap-3 hover:bg-slate-50 transition-colors">
              <div :class="['w-8 h-8 rounded-xl flex items-center justify-center shadow-2xs shrink-0', activityIconBg(activity.type)]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="activityIcon(activity.type)"></svg>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-black text-slate-900 truncate">{{ activity.title }}</p>
                <p class="text-[10px] font-semibold text-slate-400 truncate">{{ activity.description }}</p>
              </div>
              <div class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter shrink-0">{{ formatTime(activity.created_at) }}</div>
            </div>
            <div v-if="recentActivity.length === 0" class="flex flex-col items-center justify-center py-10 text-center text-slate-400">
              <p class="text-xs font-bold">{{ t('dashboard.no_recent_activity') }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Heatmap -->
      <div data-tour="dashboard-heatmap" class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-5">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h3 class="font-black text-slate-900 text-sm sm:text-base">{{ t('dashboard.heatmap_title') }}</h3>
            <p class="text-[11px] text-slate-400 font-medium">{{ t('dashboard.heatmap_subtitle') }}</p>
          </div>
          <div class="flex items-center gap-2 text-[10px] font-bold text-slate-400">
            <span>{{ t('dashboard.less') }}</span>
            <div class="flex gap-1">
              <div v-for="c in heatmapColors" :key="c" class="w-3 h-3 rounded-sm" :style="{ background: c }"></div>
            </div>
            <span>{{ t('dashboard.more') }}</span>
          </div>
        </div>
        <div class="flex gap-3">
          <div class="grid grid-rows-7 gap-1 pt-1">
            <span v-for="day in days" :key="day" class="text-[9px] font-black text-slate-400 h-5 flex items-center uppercase leading-none">{{ day }}</span>
          </div>
          <div class="flex-1 grid grid-rows-7 gap-1">
            <div v-for="day in 7" :key="day" class="grid grid-cols-6 gap-1">
              <div v-for="hour in 6" :key="hour" 
                class="h-5 rounded-sm transition-all hover:ring-2 hover:ring-indigo-200" 
                :style="{ background: heatmapColor(day, hour) }"
                :title="t('dashboard.heatmap_tooltip')"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue';
import confetti from 'canvas-confetti';
import { Chart, registerables } from 'chart.js';
import api from '@/services/api';
import auth from '@/services/auth';
import { t, isRTL, locale } from '@/i18n';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

Chart.register(...registerables);

export default {
  name: 'Dashboard',
  components: { DashboardLayout },
  setup() {
    const loading = ref(true);
    const showWelcome = ref(false);
    const user = ref(auth.getUser());
    const stats_data = ref({
      totalMessages: 0,
      delivered: 0,
      read: 0,
      newContacts: 0
    });
    const performance = ref({ labels: [], sent: [], delivered: [], read: [], replied: [] });
    const numbers = ref([]);
    const messagesByType = ref([]);
    const recentActivity = ref([]);
    const heatmap = ref([]);

    const lineChart = ref(null);
    const donutChart = ref(null);

    const stats = computed(() => [
      { 
        label: t('dashboard.total_messages'), 
        value: stats_data.value.totalMessages.toLocaleString(), 
        change: 12.5, 
        bg: 'bg-indigo-50', 
        color: 'text-indigo-600', 
        icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />' 
      },
      { 
        label: t('dashboard.delivered'), 
        value: stats_data.value.delivered.toLocaleString(), 
        change: 8.2, 
        bg: 'bg-green-50', 
        color: 'text-green-600', 
        icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />' 
      },
      { 
        label: t('dashboard.read'), 
        value: stats_data.value.read.toLocaleString(), 
        change: 4.1, 
        bg: 'bg-blue-50', 
        color: 'text-blue-600', 
        icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />' 
      },
      { 
        label: t('dashboard.new_contacts'), 
        value: stats_data.value.newContacts.toLocaleString(), 
        change: 15.7, 
        bg: 'bg-purple-50', 
        color: 'text-purple-600', 
        icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />' 
      },
    ]);

    const legend = computed(() => [
      { label: t('dashboard.legend_sent'), color: '#6366F1' },
      { label: t('dashboard.legend_delivered'), color: '#10B981' },
      { label: t('dashboard.legend_read'), color: '#3B82F6' },
    ]);

    const typeColors = ['#6366F1', '#10B981', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6', '#64748B'];
    const days = computed(() => isRTL.value 
      ? ['الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت', 'الأحد']
      : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
    );
    const heatmapColors = ['#EEF2FF', '#E0E7FF', '#C7D2FE', '#818CF8', '#4F46E5'];

    const chartData = computed(() => ({
      labels: performance.value.labels.length ? performance.value.labels : ['', '', '', '', '', '', ''],
      datasets: [
        { label: t('dashboard.legend_sent'), data: performance.value.sent, borderColor: '#6366F1', backgroundColor: 'rgba(99, 102, 241, 0.05)', fill: true, tension: 0.4, pointRadius: 2, borderWidth: 2 },
        { label: t('dashboard.legend_delivered'), data: performance.value.delivered, borderColor: '#10B981', tension: 0.4, pointRadius: 0, borderWidth: 2 },
        { label: t('dashboard.legend_read'), data: performance.value.read, borderColor: '#3B82F6', tension: 0.4, pointRadius: 0, borderWidth: 2 },
      ],
    }));

    const donutData = computed(() => ({
      labels: messagesByType.value.map((i) => i.label),
      datasets: [{
        data: messagesByType.value.map((i) => i.value),
        backgroundColor: messagesByType.value.map((_, i) => typeColors[i % typeColors.length]),
        borderWidth: 0,
        hoverOffset: 4
      }],
    }));

    let lineInstance = null;
    let donutInstance = null;

    const renderLineChart = () => {
      if (!lineChart.value) return;
      if (lineInstance) lineInstance.destroy();
      lineInstance = new Chart(lineChart.value, {
        type: 'line',
        data: chartData.value,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: { legend: { display: false }, tooltip: { backgroundColor: '#111827', padding: 12, titleFont: { size: 12, weight: 'bold' }, bodyFont: { size: 11 }, cornerRadius: 10, displayColors: true } },
          scales: {
            x: { grid: { display: false }, ticks: { color: '#94A3B8', font: { size: 10, weight: '600' } } },
            y: { border: { display: false }, grid: { color: '#F1F5F9' }, ticks: { color: '#94A3B8', font: { size: 10, weight: '600' }, maxTicksLimit: 6 } },
          },
        },
      });
    };

    const renderDonutChart = () => {
      if (!donutChart.value) return;
      if (donutInstance) donutInstance.destroy();
      donutInstance = new Chart(donutChart.value, {
        type: 'doughnut',
        data: donutData.value,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '82%',
          plugins: { legend: { display: false }, tooltip: { backgroundColor: '#111827', padding: 10, cornerRadius: 8 } },
        },
      });
    };

    const loadDashboard = async () => {
      try {
        loading.value = true;
        const response = await api.get('/whatsapp/dashboard-stats');
        if (response.success) {
          const data = response.data;
          stats_data.value = {
            totalMessages: data.totalMessages || 0,
            delivered: data.delivered || 0,
            read: data.read || 0,
            newContacts: data.newContacts || 0,
          };
          performance.value = data.performance || { labels: [], sent: [], delivered: [], read: [], replied: [] };
          numbers.value = data.numbers || [];
          messagesByType.value = (data.messagesByType || []).map((item, i) => ({ ...item, color: typeColors[i % typeColors.length] }));
          recentActivity.value = data.recentActivity || [];
          heatmap.value = data.heatmap || [];
        }
      } catch (err) {
        console.error('Error loading dashboard:', err);
      } finally {
        loading.value = false;
      }
    };

    const formatTime = (date) => {
      if (!date) return '-';
      const now = new Date();
      const then = new Date(date);
      const diff = Math.floor((now - then) / 1000);
      if (diff < 60) return isRTL.value ? 'الآن' : 'just now';
      if (diff < 3600) return isRTL.value ? `منذ ${Math.floor(diff / 60)}د` : `${Math.floor(diff / 60)}m ago`;
      if (diff < 86400) return isRTL.value ? `منذ ${Math.floor(diff / 3600)}س` : `${Math.floor(diff / 3600)}h ago`;
      return isRTL.value ? `منذ ${Math.floor(diff / 86400)}ي` : `${Math.floor(diff / 86400)}d ago`;
    };

    const activityIcon = (type) => {
      const icons = {
        message: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />',
        contact: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />',
        campaign: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 003.5 0V5.882M17 9.176V19.24a1.76 1.76 0 003.5 0V9.176M5 14.294V19.24a1.76 1.76 0 003.5 0v-4.946" />',
      };
      return icons[type] || icons.message;
    };

    const activityIconBg = (type) => {
      const colors = { message: 'bg-indigo-50 text-indigo-600', contact: 'bg-green-50 text-green-600', campaign: 'bg-blue-50 text-blue-600' };
      return colors[type] || 'bg-gray-50 text-gray-400';
    };

    const heatmapColor = (day, hour) => {
      const item = heatmap.value.find((h) => h.day + 1 === day && h.hour === (hour - 1) * 4);
      const value = item ? item.value : 0;
      if (value === 0) return '#F8FAFC';
      const max = Math.max(...heatmap.value.map((h) => h.value), 1);
      const ratio = value / max;
      if (ratio < 0.25) return '#E0E7FF';
      if (ratio < 0.5) return '#C7D2FE';
      if (ratio < 0.75) return '#818CF8';
      return '#4F46E5';
    };

    watch(performance, () => {
      if (lineChart.value) renderLineChart();
    });

    watch(messagesByType, () => {
      if (donutChart.value) renderDonutChart();
    });

    watch(locale, () => {
      if (lineChart.value) renderLineChart();
      if (donutChart.value) renderDonutChart();
    });

    onMounted(async () => {
      await loadDashboard();
      renderLineChart();
      renderDonutChart();

      // Show welcome message if it's the first time in this session
      const hasSeenWelcome = sessionStorage.getItem('hasSeenWelcome');
      if (!hasSeenWelcome) {
        showWelcome.value = true;
        sessionStorage.setItem('hasSeenWelcome', 'true');
        
        // Celebration effect
        confetti({
          particleCount: 150,
          spread: 70,
          origin: { y: 0.6 },
          colors: ['#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6']
        });

        // Side bursts
        setTimeout(() => {
          confetti({
            particleCount: 50,
            angle: 60,
            spread: 55,
            origin: { x: 0 },
            colors: ['#4F46E5', '#10B981']
          });
          confetti({
            particleCount: 50,
            angle: 120,
            spread: 55,
            origin: { x: 1 },
            colors: ['#4F46E5', '#F59E0B']
          });
        }, 500);
      }
    });

    return {
      user,
      stats,
      legend,
      numbers,
      messagesByType,
      recentActivity,
      days,
      heatmapColors,
      lineChart,
      donutChart,
      formatTime,
      activityIcon,
      activityIconBg,
      heatmapColor,
      showWelcome,
      t,
      isRTL,
    };
  },
};
</script>

<style scoped>
.animate-in {
  animation: animate-in 0.5s ease-out;
}

@keyframes animate-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
