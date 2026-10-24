<template>
  <DashboardLayout>
    <div class="flex overflow-hidden bg-white text-slate-800 font-sans antialiased -m-4 sm:-m-6 lg:-m-8 h-[calc(100vh-4rem)]" :dir="isRTL ? 'rtl' : 'ltr'" @click="closeMenus">

      <!-- LEFT PANEL: Conversation list -->
      <aside
        class="w-full md:w-[380px] lg:w-[420px] bg-white border-slate-200 flex flex-col shrink-0 z-10 transition-all duration-200"
        :class="[isRTL ? 'border-l' : 'border-r', { 'max-md:hidden': activePeer }]"
      >
        <!-- Header -->
        <div class="h-16 bg-white px-4 flex items-center justify-between border-b border-slate-200 shrink-0">
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-emerald-500 to-teal-500 text-white font-bold flex items-center justify-center text-sm shadow shrink-0">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2z"/></svg>
            </div>
            <div class="min-w-0">
              <h2 class="text-sm font-bold text-slate-900 leading-tight truncate">{{ t('meta.chats.official_title') }}</h2>
              <p class="text-[11px] text-slate-400 truncate">{{ t('meta.chats.official_sub') }}</p>
            </div>
          </div>

          <div class="flex items-center gap-1">
            <button
              @click="newChatOpen = true"
              class="p-2 text-slate-400 hover:text-emerald-600 rounded-full hover:bg-slate-100 transition-colors"
              :title="t('meta.chats.new')"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </button>
            <button
              @click="loadConvs()"
              class="p-2 text-slate-400 hover:text-emerald-600 rounded-full hover:bg-slate-100 transition-colors"
              :class="{ 'animate-spin': loadingConvs }"
              :title="t('meta.chats.refresh')"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </button>
          </div>
        </div>

        <!-- Search & filters -->
        <div class="p-2.5 bg-white border-b border-slate-100 space-y-2 shrink-0">
          <div class="relative">
            <input
              v-model="search"
              @input="debouncedLoad"
              type="text"
              :placeholder="t('meta.chats.search_ph')"
              class="w-full bg-slate-100 text-slate-700 placeholder-slate-400 text-xs rounded-lg py-2 focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:bg-white border border-transparent focus:border-emerald-300 transition-colors"
              :class="isRTL ? 'pr-9 pl-3' : 'pl-9 pr-3'"
            />
            <div class="absolute inset-y-0 flex items-center pointer-events-none text-slate-400" :class="isRTL ? 'right-3' : 'left-3'">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
          </div>

          <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5">
            <button
              @click="filterMode = 'all'"
              class="px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap transition-colors"
              :class="filterMode === 'all' ? 'bg-emerald-500 text-white font-bold' : 'bg-slate-100 text-slate-500 hover:text-slate-700'"
            >{{ t('meta.chats.filter_all') }}</button>
            <button
              @click="filterMode = 'unread'"
              class="px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap transition-colors"
              :class="filterMode === 'unread' ? 'bg-emerald-500 text-white font-bold' : 'bg-slate-100 text-slate-500 hover:text-slate-700'"
            >{{ t('meta.chats.filter_unread') }}</button>
            <select
              v-model="filterNumber"
              class="bg-slate-100 text-xs text-slate-600 font-semibold rounded-full px-3 py-1 border-0 focus:outline-none focus:ring-2 focus:ring-emerald-200 cursor-pointer shrink-0"
              dir="ltr"
            >
              <option :value="null">{{ t('meta.chats.all_numbers') }}</option>
              <option v-for="n in numbers" :key="n.id" :value="n.id">{{ n.phone_number }}</option>
            </select>
          </div>
        </div>

        <!-- Conversation list -->
        <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
          <div v-if="loadingConvs && !conversations.length" class="p-8 flex flex-col items-center gap-3 text-xs text-slate-400">
            <span class="wx-spinner w-6 h-6"></span>
            {{ t('meta.common.loading') }}
          </div>
          <div v-else-if="!visibleConvs.length" class="p-8 text-center text-xs text-slate-400">
            {{ t('meta.chats.empty') }}
          </div>

          <button
            v-for="c in visibleConvs"
            :key="c.peer"
            @click="openChat(c)"
            class="w-full p-3 transition-colors flex items-center gap-3 relative group"
            :class="[activePeer === c.peer ? 'bg-emerald-50/80' : 'hover:bg-slate-50', isRTL ? 'text-right' : 'text-left']"
          >
            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 text-white font-bold flex items-center justify-center shrink-0 shadow-inner relative overflow-hidden">
              <span class="text-base">{{ convInitial(c) }}</span>
              <span
                v-if="c.unread > 0"
                class="absolute bottom-0 right-0 w-3 h-3 bg-emerald-500 rounded-full ring-2 ring-white"
              ></span>
            </div>

            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between mb-1">
                <div class="flex items-center gap-1.5 min-w-0">
                  <span class="font-bold text-sm text-slate-900 truncate">{{ convTitle(c) }}</span>
                  <span
                    v-if="c.our_number"
                    class="px-1.5 py-0.5 rounded text-[10px] font-black tracking-wide shrink-0 bg-emerald-50 text-emerald-600 border border-emerald-100"
                    dir="ltr"
                    :title="t('meta.chats.via') + ' ' + c.our_number"
                  >{{ c.our_display || c.our_number }}</span>
                </div>
                <span class="text-[11px] shrink-0 font-medium" :class="c.unread > 0 ? 'text-emerald-600' : 'text-slate-400'">
                  {{ fmtTime(c.last_at) }}
                </span>
              </div>

              <div class="flex items-center justify-between text-xs text-slate-500">
                <div class="flex items-center gap-1 truncate max-w-[240px]">
                  <span v-if="c.last_direction === 'outgoing'" class="text-xs shrink-0">
                    <span v-if="c.last_status === 'failed'" class="text-red-500 font-bold">✗</span>
                    <span v-else-if="c.last_status === 'read'" class="text-sky-500 font-bold">✓✓</span>
                    <span v-else-if="c.last_status === 'delivered'" class="text-slate-400">✓✓</span>
                    <span v-else class="text-slate-400">✓</span>
                  </span>
                  <span v-if="c.last_type === 'image'" class="text-slate-500">📷 {{ t('meta.chats.photo') }}</span>
                  <span v-else-if="c.last_type === 'video'" class="text-slate-500">🎥 {{ t('meta.chats.video') }}</span>
                  <span v-else-if="c.last_type === 'audio' || c.last_type === 'voice'" class="text-slate-500">🎤 {{ t('meta.chats.audio') }}</span>
                  <span v-else-if="c.last_type === 'document'" class="text-slate-500">📄 {{ t('meta.chats.doc') }}</span>
                  <span v-else class="truncate">{{ lastLabel(c) }}</span>
                </div>
                <span
                  v-if="c.unread > 0"
                  class="min-w-[20px] h-5 px-1.5 rounded-full bg-emerald-500 text-white font-black text-[11px] flex items-center justify-center shadow-sm shrink-0"
                >{{ c.unread }}</span>
              </div>
            </div>
          </button>

          <button
            v-if="pagination && pagination.current_page < pagination.last_page"
            @click="loadMore"
            class="w-full py-2.5 text-xs font-bold text-emerald-600 hover:bg-emerald-50"
          >{{ t('meta.chats.load_more') }}</button>
        </div>
      </aside>

      <!-- RIGHT PANEL: Chat area -->
      <main class="flex-1 flex flex-col min-w-0 bg-[#efeae2] relative">
        <!-- Empty state -->
        <div v-if="!activePeer" class="flex-1 flex flex-col items-center justify-center p-8 text-center bg-slate-50">
          <div class="w-20 h-20 rounded-3xl bg-emerald-50 border border-emerald-100 text-emerald-500 flex items-center justify-center mb-6 shadow-sm">
            <svg class="w-10 h-10" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2z"/></svg>
          </div>
          <h2 class="text-2xl font-bold text-slate-900 mb-2">{{ t('meta.chats.official_title') }}</h2>
          <p class="text-sm text-slate-500 max-w-md mb-6 leading-relaxed">{{ t('meta.chats.pick') }}</p>
          <button
            @click="newChatOpen = true"
            class="px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-sm transition-all shadow-sm"
          >{{ t('meta.chats.new') }}</button>
          <div class="flex items-center gap-1.5 text-slate-400 text-xs mt-10">
            <svg width="12" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2h-1V6a5 5 0 0 0-5-5zm-3 8V6a3 3 0 1 1 6 0v3H9z"/></svg>
            {{ t('meta.chats.e2e') }}
          </div>
        </div>

        <!-- Active chat -->
        <template v-else>
          <!-- Multi-select action bar -->
          <header v-if="selectMode" class="h-16 bg-white px-4 border-b border-slate-200 flex items-center justify-between shrink-0 z-20">
            <div class="flex items-center gap-3">
              <button @click="exitSelect" class="text-slate-400 hover:text-slate-700 p-1 rounded-full">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
              <span class="text-sm font-bold text-slate-900">{{ selectedIds.length }} {{ t('meta.chats.selected') }}</span>
            </div>
            <div class="flex items-center gap-1">
              <button :disabled="!selectedIds.length" @click="openForward(selectedIds)" class="p-2 text-slate-500 hover:text-emerald-600 rounded-full hover:bg-slate-100 disabled:opacity-40" :title="t('meta.chats.forward')">
                <svg class="w-5 h-5 rtl:-scale-x-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 12h14"/></svg>
              </button>
              <button :disabled="!selectedIds.length" @click="copySelected" class="p-2 text-slate-500 hover:text-emerald-600 rounded-full hover:bg-slate-100 disabled:opacity-40" :title="t('meta.chats.copy')">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
              </button>
              <button :disabled="!selectedIds.length" @click="hideSelected" class="p-2 text-slate-500 hover:text-red-500 rounded-full hover:bg-slate-100 disabled:opacity-40" :title="t('meta.chats.delete')">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              </button>
            </div>
          </header>

          <!-- Normal header -->
          <header v-else class="h-16 bg-white px-4 border-b border-slate-200 flex items-center justify-between shrink-0 z-20">
            <div class="flex items-center gap-3 min-w-0">
              <button @click="activePeer = null" class="md:hidden text-slate-400 hover:text-slate-700 p-1 rounded-full">
                <svg class="w-6 h-6 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
              </button>

              <div class="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 text-white font-bold flex items-center justify-center shrink-0 text-sm shadow">
                {{ activeName ? activeName.trim()[0] : activePeer.slice(-2) }}
              </div>

              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <h3 class="text-sm font-bold text-slate-900 truncate">{{ activeName || '+' + activePeer }}</h3>
                  <button @click="renameContact" class="text-slate-300 hover:text-emerald-600 shrink-0" :title="t('meta.chats.rename')">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                  </button>
                </div>
                <p class="text-[11px] text-slate-400" dir="ltr">
                  +{{ activePeer }}<span v-if="activeConv?.our_number"> · {{ t('meta.chats.via') }} {{ activeConv.our_number }}</span>
                </p>
              </div>
            </div>

            <div class="flex items-center gap-1 shrink-0">
              <button
                @click.stop="searchOpen = !searchOpen; chatSearch = ''"
                class="p-2 rounded-full transition-colors"
                :class="searchOpen ? 'text-emerald-600 bg-emerald-50' : 'text-slate-400 hover:text-emerald-600 hover:bg-slate-100'"
                :title="t('meta.chats.search_chat')"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
              </button>
              <select v-model="sendFrom" class="bg-slate-100 text-xs text-slate-600 font-semibold rounded-lg px-2.5 py-1.5 border-0 focus:outline-none focus:ring-2 focus:ring-emerald-200 cursor-pointer" dir="ltr">
                <option :value="null">{{ t('meta.chats.auto_number') }}</option>
                <option v-for="n in numbers" :key="n.id" :value="n.id">{{ n.phone_number }}</option>
              </select>
            </div>
          </header>

          <!-- In-chat search bar -->
          <div v-if="searchOpen" class="bg-white border-b border-slate-200 px-4 py-2 flex items-center gap-2 shrink-0 z-10">
            <input
              v-model="chatSearch"
              :placeholder="t('meta.chats.search_chat_ph')"
              class="flex-1 bg-slate-100 text-slate-700 placeholder-slate-400 text-xs rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-200"
            />
            <span v-if="chatSearch" class="text-[11px] text-slate-400 font-bold shrink-0">{{ searchHits }} {{ t('meta.chats.hits') }}</span>
          </div>

          <!-- 24h window banner -->
          <div v-if="windowClosed" class="bg-amber-50 border-b border-amber-200 px-4 py-2 text-[11px] text-amber-800 flex items-center gap-2 shrink-0">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ t('meta.chats.window_closed') }} <router-link to="/meta/templates" class="font-bold underline">{{ t('meta.chats.use_template') }}</router-link></span>
          </div>

          <!-- Messages -->
          <div ref="msgsEl" @scroll="onScroll" class="flex-1 overflow-y-auto px-4 sm:px-10 lg:px-16 py-4 space-y-1 wx-chat-bg relative">
            <div v-if="!bubbles.length" class="text-center mt-10">
              <span class="inline-block bg-white text-slate-500 text-xs rounded-lg px-3 py-1.5 shadow-sm border border-slate-100">{{ t('meta.chats.no_msgs') }}</span>
            </div>

            <template v-for="(m, i) in bubbles" :key="m.id">
              <!-- Date separator -->
              <div v-if="showDateSep(m, i)" class="flex justify-center py-2 sticky top-0 z-10">
                <span class="bg-white text-slate-500 text-[11px] font-semibold rounded-lg px-3 py-1.5 shadow-sm border border-slate-100">{{ dateLabel(m.created_at) }}</span>
              </div>

              <div
                :id="'msg-' + m.id"
                class="relative max-w-[75%] sm:max-w-[65%] rounded-lg px-2.5 py-1.5 text-sm shadow-sm transition-all group/bubble"
                :class="[
                  m.direction === 'outgoing'
                    ? 'bg-[#d9fdd3] text-slate-800 ms-auto rounded-tr-none'
                    : 'bg-white text-slate-800 me-auto rounded-tl-none',
                  selectMode ? 'cursor-pointer' : '',
                  selectedIds.includes(m.id) ? 'ring-2 ring-emerald-500' : '',
                  flashId === m.id ? 'wx-flash' : '',
                  chatSearch && !matchesSearch(m) ? 'opacity-40' : '',
                  chatSearch && matchesSearch(m) ? 'ring-2 ring-amber-400' : '',
                ]"
                @contextmenu.prevent="openCtx(m, $event)"
                @click="selectMode && toggleSelect(m.id)"
              >
                <!-- Multi-select checkbox -->
                <div
                  v-if="selectMode"
                  class="absolute top-1/2 -translate-y-1/2 w-4 h-4 rounded border-2 flex items-center justify-center"
                  :class="[selectedIds.includes(m.id) ? 'bg-emerald-500 border-emerald-500' : 'border-slate-300 bg-white', m.direction === 'outgoing' ? '-left-7' : '-right-7']"
                >
                  <svg v-if="selectedIds.includes(m.id)" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                </div>

                <!-- Hover chevron (desktop) / tap target (mobile) -->
                <button
                  v-if="!selectMode"
                  @click.stop="openCtx(m, $event, true)"
                  class="absolute top-0.5 opacity-0 group-hover/bubble:opacity-100 max-md:opacity-60 transition-opacity w-6 h-6 rounded-full text-slate-400 hover:text-slate-600 flex items-center justify-center"
                  :class="m.direction === 'outgoing' ? '-left-7' : '-right-7'"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <!-- Quoted reply block -->
                <button
                  v-if="m.reply_to_id && quoted(m.reply_to_id)"
                  @click.stop="scrollToMsg(m.reply_to_id)"
                  class="w-full text-start mb-1 rounded-md px-2 py-1.5 border-s-4 border-emerald-500 block"
                  :class="m.direction === 'outgoing' ? 'bg-emerald-600/10' : 'bg-slate-100'"
                >
                  <p class="text-[10px] font-bold text-emerald-700">{{ quoted(m.reply_to_id).direction === 'outgoing' ? t('meta.chats.you') : (activeName || '+' + activePeer) }}</p>
                  <p class="text-[11px] text-slate-500 truncate">{{ quotedPreview(quoted(m.reply_to_id)) }}</p>
                </button>
                <div v-else-if="m.reply_to_id" class="mb-1 rounded-md px-2 py-1 bg-slate-100 text-[11px] text-slate-400 italic">{{ t('meta.chats.deleted_msg') }}</div>

                <!-- Media -->
                <template v-if="isMedia(m)">
                  <img v-if="m.message_type === 'image' && mediaReady(m)" :src="mediaUrls[m.id]" class="rounded-md max-h-64 mb-1 cursor-zoom-in" @click.stop="lightbox = { url: mediaUrls[m.id], type: 'image' }" />
                  <video v-else-if="m.message_type === 'video' && mediaReady(m)" :src="mediaUrls[m.id]" controls class="rounded-md max-h-64 mb-1"></video>
                  <div v-else-if="(m.message_type === 'audio' || m.message_type === 'voice') && mediaReady(m)" class="flex items-center gap-2 mb-1" :class="m.direction === 'outgoing' ? '' : ''">
                    <span class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" :class="m.direction === 'outgoing' ? 'bg-emerald-600/20 text-emerald-700' : 'bg-slate-200 text-slate-500'">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 15a3 3 0 003-3V6a3 3 0 10-6 0v6a3 3 0 003 3zm6-3a6 6 0 11-12 0H4a8 8 0 007 7.94V22h2v-2.06A8 8 0 0020 12h-2z"/></svg>
                    </span>
                    <audio :src="mediaUrls[m.id]" controls class="h-8 max-w-[210px]"></audio>
                  </div>
                  <a v-else-if="m.message_type === 'document' && mediaReady(m)" :href="mediaUrls[m.id]" :download="docName(m)" @click.stop class="flex items-center gap-2 rounded-md p-2 mb-1 transition-colors" :class="m.direction === 'outgoing' ? 'bg-emerald-600/10 hover:bg-emerald-600/20' : 'bg-slate-100 hover:bg-slate-200'">
                    <span class="w-9 h-9 rounded-lg bg-red-100 text-red-500 flex items-center justify-center font-bold text-[10px] shrink-0 uppercase">{{ docExt(m) }}</span>
                    <span class="min-w-0">
                      <span class="block text-xs font-bold text-slate-700 truncate">{{ docName(m) }}</span>
                      <span class="block text-[10px] text-slate-400">{{ t('meta.chats.doc') }}</span>
                    </span>
                    <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0 0l-4-4m4 4l4-4"/></svg>
                  </a>
                  <span v-else class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                    <span class="wx-spinner w-4 h-4"></span>[{{ m.message_type }}]
                  </span>
                </template>

                <p v-if="m.body" class="whitespace-pre-wrap break-words leading-relaxed">{{ m.body }}</p>
                <p v-else-if="!isMedia(m)" class="text-xs text-slate-400 italic">[{{ m.message_type || 'template' }}]</p>

                <p class="text-[10px] text-slate-400 mt-0.5 flex items-center gap-1 justify-end select-none">
                  {{ fmtTime(m.created_at) }}
                  <span v-if="m.direction === 'outgoing'" :class="tickClass(m.status)">{{ ticks(m.status) }}</span>
                </p>

                <!-- Reactions badge -->
                <div
                  v-if="reactionsMap[m.id]?.length"
                  class="absolute -bottom-3 flex gap-0.5 z-10"
                  :class="m.direction === 'outgoing' ? 'left-1' : 'right-1'"
                >
                  <span
                    v-for="(r, ri) in reactionsMap[m.id]"
                    :key="ri"
                    class="bg-white rounded-full px-1.5 py-0.5 text-xs shadow border border-slate-200"
                    :title="r.dir === 'outgoing' ? t('meta.chats.you') : (activeName || '')"
                  >{{ r.emoji }}</span>
                </div>
              </div>
            </template>
          </div>

          <!-- Scroll-to-bottom FAB -->
          <button
            v-if="showFab"
            @click="scrollBottom(true)"
            class="absolute bottom-24 end-6 z-20 w-10 h-10 rounded-full bg-white shadow-lg border border-slate-200 text-slate-500 hover:text-emerald-600 flex items-center justify-center"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
          </button>

          <p v-if="sendError" class="px-4 py-2 text-xs text-red-600 bg-red-50 border-t border-red-100">{{ sendError }}</p>

          <!-- Reply preview -->
          <div v-if="replyTarget" class="bg-white border-t border-slate-200 px-4 pt-2 flex items-center gap-2">
            <div class="flex-1 border-s-4 border-emerald-500 bg-slate-50 rounded-lg px-3 py-2 min-w-0">
              <p class="text-[11px] font-bold text-emerald-700">{{ t('meta.chats.replying_to') }} {{ replyTarget.direction === 'outgoing' ? t('meta.chats.you') : (activeName || '+' + activePeer) }}</p>
              <p class="text-xs text-slate-500 truncate">{{ quotedPreview(replyTarget) }}</p>
            </div>
            <button @click="replyTarget = null" class="text-slate-400 hover:text-slate-600 p-1 shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- Composer -->
          <footer class="px-3 py-2 bg-white border-t border-slate-200 flex gap-1.5 items-center shrink-0">
            <div class="relative">
              <button @click.stop="emojiOpen = !emojiOpen; attachOpen = false" class="w-10 h-10 rounded-full text-slate-400 hover:bg-slate-100 flex items-center justify-center" :title="t('meta.chats.emoji')">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              </button>
              <div v-if="emojiOpen" @click.stop class="absolute bottom-12 start-0 bg-white border border-slate-200 rounded-2xl shadow-xl p-3 w-72 z-30 grid grid-cols-8 gap-1 max-h-48 overflow-y-auto">
                <button
                  v-for="e in EMOJIS"
                  :key="e"
                  @click="insertEmoji(e)"
                  class="w-8 h-8 text-lg rounded-lg hover:bg-slate-100 flex items-center justify-center"
                >{{ e }}</button>
              </div>
            </div>

            <div class="relative">
              <button @click.stop="attachOpen = !attachOpen; emojiOpen = false" class="w-10 h-10 rounded-full text-slate-400 hover:bg-slate-100 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
              </button>
              <div v-if="attachOpen" @click.stop class="absolute bottom-12 start-0 bg-white border border-slate-200 rounded-xl shadow-lg p-1.5 w-44 z-20">
                <button @click="pickFile('image')" class="w-full text-start px-3 py-2 text-sm rounded-lg hover:bg-slate-50 text-slate-700">🖼️ {{ t('meta.chats.photo') }}</button>
                <button @click="pickFile('video')" class="w-full text-start px-3 py-2 text-sm rounded-lg hover:bg-slate-50 text-slate-700">🎥 {{ t('meta.chats.video') }}</button>
                <button @click="pickFile('document')" class="w-full text-start px-3 py-2 text-sm rounded-lg hover:bg-slate-50 text-slate-700">📄 {{ t('meta.chats.doc') }}</button>
                <button @click="pickFile('audio')" class="w-full text-start px-3 py-2 text-sm rounded-lg hover:bg-slate-50 text-slate-700">🎵 {{ t('meta.chats.audio') }}</button>
              </div>
              <input ref="fileInput" type="file" class="hidden" @change="onFilePicked" />
            </div>

            <input
              ref="draftInput"
              v-model="draft"
              @keydown.enter.prevent="send"
              @input="notifyTyping"
              :placeholder="t('meta.chats.type_ph')"
              class="flex-1 bg-slate-100 text-slate-800 placeholder-slate-400 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:bg-white border border-transparent focus:border-emerald-300 transition-colors"
            />

            <button
              v-if="!draft.trim()"
              @click="recording ? stopRecording() : startRecording()"
              class="w-11 h-11 rounded-full flex items-center justify-center shrink-0 transition-colors"
              :class="recording ? 'bg-red-500 text-white animate-pulse' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
              :title="recording ? recSeconds + 's — ' + t('meta.chats.tap_send') : t('meta.chats.voice')"
            >
              <svg v-if="!recording" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 15a3 3 0 003-3V6a3 3 0 10-6 0v6a3 3 0 003 3zm6-3a6 6 0 11-12 0H4a8 8 0 007 7.94V22h2v-2.06A8 8 0 0020 12h-2z"/></svg>
              <span v-else class="text-xs font-bold">{{ recSeconds }}s</span>
            </button>
            <button
              v-else
              @click="send"
              :disabled="sending"
              class="w-11 h-11 rounded-full bg-emerald-500 text-white flex items-center justify-center hover:bg-emerald-600 disabled:opacity-40 shrink-0"
            >
              <svg class="w-5 h-5 rtl:-scale-x-100" fill="currentColor" viewBox="0 0 24 24"><path d="M3.4 20.4l17.4-7.5c.8-.35.8-1.45 0-1.8L3.4 3.6c-.66-.29-1.39.2-1.39.91L2 9.12c0 .5.37.93.87.99L17 12 2.87 13.88c-.5.07-.87.5-.87 1l.01 4.61c0 .72.73 1.2 1.39.91z"/></svg>
            </button>
          </footer>
        </template>

        <!-- Context menu -->
        <Teleport to="body">
          <div
            v-if="ctxMenu"
            class="fixed z-[120] bg-white border border-slate-200 rounded-xl shadow-2xl py-1.5 w-52 text-sm text-slate-700"
            :style="{ top: ctxMenu.y + 'px', left: ctxMenu.x + 'px' }"
            @click.stop
            :dir="isRTL ? 'rtl' : 'ltr'"
          >
            <div class="flex gap-1 px-3 py-1.5 border-b border-slate-100 mb-1">
              <button
                v-for="e in REACTIONS"
                :key="e"
                @click="sendReaction(ctxMenu.m, e)"
                class="w-7 h-7 text-base rounded-full hover:bg-slate-100 hover:scale-125 transition-transform flex items-center justify-center"
              >{{ e }}</button>
            </div>
            <button @click="startReply(ctxMenu.m)" class="ctx-item">{{ t('meta.chats.reply') }}</button>
            <button @click="openForward([ctxMenu.m.id])" class="ctx-item">{{ t('meta.chats.forward') }}</button>
            <button v-if="ctxMenu.m.body" @click="copyMsg(ctxMenu.m)" class="ctx-item">{{ t('meta.chats.copy') }}</button>
            <button v-if="isMedia(ctxMenu.m) && mediaReady(ctxMenu.m)" @click="downloadMsg(ctxMenu.m)" class="ctx-item">{{ t('meta.chats.download') }}</button>
            <button @click="selectMode = true; toggleSelect(ctxMenu.m.id); ctxMenu = null" class="ctx-item">{{ t('meta.chats.select') }}</button>
            <button @click="infoMsg = ctxMenu.m; ctxMenu = null" class="ctx-item">{{ t('meta.chats.info') }}</button>
            <div class="border-t border-slate-100 my-1"></div>
            <button @click="hideMessages([ctxMenu.m.id])" class="ctx-item text-red-600 hover:!bg-red-50">{{ t('meta.chats.delete') }}</button>
          </div>
        </Teleport>
      </main>

      <!-- Image lightbox -->
      <Teleport to="body">
        <div v-if="lightbox" class="fixed inset-0 z-[130] bg-black/80 backdrop-blur-sm flex items-center justify-center p-6" @click="lightbox = null">
          <button class="absolute top-4 end-4 text-white/80 hover:text-white p-2">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
          <img :src="lightbox.url" class="max-w-full max-h-full rounded-lg shadow-2xl object-contain" @click.stop />
        </div>
      </Teleport>

      <!-- Forward modal -->
      <Teleport to="body">
        <div v-if="fwdOpen" class="fixed inset-0 z-[100] bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4" @click.self="fwdOpen = false">
          <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden" :dir="isRTL ? 'rtl' : 'ltr'">
            <div class="bg-slate-50 border-b border-slate-100 px-4 py-3 flex items-center justify-between">
              <h3 class="text-sm font-bold text-slate-900">{{ t('meta.chats.forward_to') }}</h3>
              <button @click="fwdOpen = false" class="text-slate-400 hover:text-slate-700 p-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <div class="p-3 max-h-72 overflow-y-auto divide-y divide-slate-100">
              <button
                v-for="c in conversations"
                :key="'f-' + c.peer"
                @click="doForward(c.peer)"
                class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-slate-50 text-start"
              >
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 text-white font-bold flex items-center justify-center text-xs shrink-0">{{ convInitial(c) }}</div>
                <div class="min-w-0">
                  <p class="text-sm font-bold text-slate-800 truncate">{{ convTitle(c) }}</p>
                  <p class="text-[11px] text-slate-400" dir="ltr">+{{ c.peer }}</p>
                </div>
              </button>
            </div>
            <div class="p-3 border-t border-slate-100 flex gap-2">
              <input v-model="fwdPeer" dir="ltr" placeholder="+2010XXXXXXXX" class="flex-1 bg-slate-100 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-200" @keydown.enter.prevent="doForward(fwdPeer)" />
              <button @click="doForward(fwdPeer)" :disabled="forwarding" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold rounded-lg disabled:opacity-40">{{ t('meta.chats.send') }}</button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- Message info modal -->
      <Teleport to="body">
        <div v-if="infoMsg" class="fixed inset-0 z-[100] bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4" @click.self="infoMsg = null">
          <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-xs shadow-2xl overflow-hidden" :dir="isRTL ? 'rtl' : 'ltr'">
            <div class="bg-slate-50 border-b border-slate-100 px-4 py-3 flex items-center justify-between">
              <h3 class="text-sm font-bold text-slate-900">{{ t('meta.chats.info') }}</h3>
              <button @click="infoMsg = null" class="text-slate-400 hover:text-slate-700 p-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <div class="p-4 space-y-2.5 text-xs">
              <div class="flex justify-between"><span class="text-slate-400">{{ t('meta.chats.status') }}</span><span class="font-bold text-slate-800">{{ infoMsg.status }}</span></div>
              <div class="flex justify-between"><span class="text-slate-400">{{ t('meta.chats.sent_at') }}</span><span class="font-semibold text-slate-700">{{ fmtFull(infoMsg.sent_at || infoMsg.created_at) }}</span></div>
              <div class="flex justify-between"><span class="text-slate-400">{{ t('meta.chats.delivered_at') }}</span><span class="font-semibold text-slate-700">{{ fmtFull(infoMsg.delivered_at) || '—' }}</span></div>
              <div class="flex justify-between"><span class="text-slate-400">{{ t('meta.chats.read_at') }}</span><span class="font-semibold text-slate-700">{{ fmtFull(infoMsg.read_at) || '—' }}</span></div>
              <div v-if="infoMsg.error_message" class="pt-1 text-red-600 text-[11px] break-words">{{ infoMsg.error_message }}</div>
              <div class="pt-1 text-[10px] text-slate-300 break-all" dir="ltr">{{ infoMsg.meta_message_id }}</div>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- New chat modal -->
      <Teleport to="body">
        <div v-if="newChatOpen" class="fixed inset-0 z-[100] bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4" @click.self="newChatOpen = false">
          <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden" :dir="isRTL ? 'rtl' : 'ltr'">
            <div class="bg-slate-50 border-b border-slate-100 px-4 py-3 flex items-center justify-between">
              <h3 class="text-sm font-bold text-slate-900">{{ t('meta.chats.new') }}</h3>
              <button @click="newChatOpen = false" class="text-slate-400 hover:text-slate-700 p-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <div class="p-4 space-y-3">
              <input
                v-model="newPeer"
                dir="ltr"
                placeholder="+2010XXXXXXXX"
                class="w-full bg-slate-100 text-slate-800 placeholder-slate-400 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:bg-white border border-transparent focus:border-emerald-300 transition-colors"
                @keydown.enter.prevent="openNewChat"
              />
              <select v-model="newChatFrom" class="w-full bg-slate-100 text-slate-600 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-200" dir="ltr">
                <option :value="null">{{ t('meta.chats.auto_number') }}</option>
                <option v-for="n in numbers" :key="n.id" :value="n.id">{{ n.phone_number }}</option>
              </select>
              <button
                @click="openNewChat"
                class="w-full py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-bold rounded-lg transition-colors"
              >{{ t('meta.chats.start') }}</button>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import metaTest from '@/services/metaTest';
import api from '@/services/api';
import { t, isRTL } from '@/i18n';

const conversations = ref([]);
const pagination = ref(null);
const convPage = ref(1);
const loadingConvs = ref(true);
const search = ref('');
const filterMode = ref('all');
const filterNumber = ref(null);

const activePeer = ref(null);
const activeName = ref(null);
const thread = ref([]);
const sendFrom = ref(null);
const draft = ref('');
const draftInput = ref(null);
const sending = ref(false);
const sendError = ref(null);
const numbers = ref([]);
const newChatOpen = ref(false);
const newPeer = ref('');
const newChatFrom = ref(null);
const msgsEl = ref(null);
const mediaUrls = ref({});
const showFab = ref(false);

const attachOpen = ref(false);
const emojiOpen = ref(false);
const fileInput = ref(null);
const attachKind = ref('image');

const recording = ref(false);
const recSeconds = ref(0);
let mediaRecorder = null;
let recChunks = [];
let recTimer = null;

// Rich interaction state
const ctxMenu = ref(null);
const replyTarget = ref(null);
const selectMode = ref(false);
const selectedIds = ref([]);
const fwdOpen = ref(false);
const fwdIds = ref([]);
const fwdPeer = ref('');
const forwarding = ref(false);
const lightbox = ref(null);
const infoMsg = ref(null);
const flashId = ref(null);
const searchOpen = ref(false);
const chatSearch = ref('');

let poller = null;
let debounce = null;
let typingAt = 0;

const REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];
const EMOJIS = [
  '😀','😂','🤣','😊','😍','😘','🥰','😎','🤩','😜','🤔','😅','😭','😢','😡','🥺',
  '👍','👎','👏','🙏','💪','🤝','👌','✌️','🤞','👋','❤️','💔','🔥','✨','🎉','💯',
  '✅','❌','⚠️','📌','📷','🎥','📞','💬','📄','🎵','😴','🤝','🫡','😇','🙃','😏',
];

const activeConv = computed(() => conversations.value.find((c) => c.peer === activePeer.value));

const visibleConvs = computed(() => conversations.value.filter((c) => {
  if (filterMode.value === 'unread' && !(c.unread > 0)) return false;
  if (filterNumber.value && c.phone_number_id !== filterNumber.value) return false;
  return true;
}));

// Reaction rows aren't bubbles — they aggregate onto their target message.
const bubbles = computed(() => thread.value.filter((m) => m.message_type !== 'reaction'));

const msgById = computed(() => Object.fromEntries(thread.value.map((m) => [m.id, m])));

const reactionsMap = computed(() => {
  const latest = {};
  for (const m of thread.value) {
    if (m.message_type !== 'reaction' || !m.reply_to_id) continue;
    (latest[m.reply_to_id] ||= {})[m.direction] = m.body;
  }
  const out = {};
  for (const [id, dirs] of Object.entries(latest)) {
    out[id] = Object.entries(dirs).filter(([, e]) => e).map(([dir, emoji]) => ({ dir, emoji }));
  }
  return out;
});

const windowClosed = computed(() => {
  const lastIn = [...bubbles.value].reverse().find((m) => m.direction === 'incoming');
  if (!lastIn) return false;
  return (Date.now() - new Date(lastIn.created_at).getTime()) > 24 * 3600 * 1000;
});

const searchHits = computed(() => bubbles.value.filter(matchesSearch).length);

const MEDIA_TYPES = ['image', 'video', 'audio', 'document', 'voice'];
const isMedia = (m) => MEDIA_TYPES.includes(m.message_type);
const mediaReady = (m) => mediaUrls.value[m.id] && mediaUrls.value[m.id] !== 'loading';
const quoted = (id) => msgById.value[id];

function matchesSearch(m) {
  if (!chatSearch.value) return false;
  return (m.body || '').toLowerCase().includes(chatSearch.value.toLowerCase());
}

function quotedPreview(m) {
  if (!m) return '';
  if (m.body) return m.body;
  const labels = { image: '📷', video: '🎥', audio: '🎤', voice: '🎤', document: '📄', sticker: '🏷️', template: '📋' };
  return (labels[m.message_type] || '') + ' ' + (m.message_type || 'message');
}

function convTitle(c) {
  return c.contact_name || '+' + c.peer;
}

function convInitial(c) {
  return c.contact_name ? c.contact_name.trim()[0].toUpperCase() : c.peer.slice(-2);
}

function lastLabel(c) {
  if (c.last_body) return c.last_body;
  return '[' + (c.last_type || 'template') + ']';
}

function docName(m) {
  return m.raw_payload?.document?.filename || m.body || 'document';
}

function docExt(m) {
  const n = docName(m);
  const ext = n.includes('.') ? n.split('.').pop() : '';
  return (ext || 'file').slice(0, 4);
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

function fmtFull(ts) {
  if (!ts) return '';
  return new Date(ts).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
}

function showDateSep(m, i) {
  if (i === 0) return true;
  const prev = bubbles.value[i - 1];
  return new Date(prev.created_at).toDateString() !== new Date(m.created_at).toDateString();
}

function dateLabel(ts) {
  const d = new Date(ts);
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(today.getDate() - 1);
  if (d.toDateString() === today.toDateString()) return t('meta.chats.today');
  if (d.toDateString() === yesterday.toDateString()) return t('meta.chats.yesterday');
  return d.toLocaleDateString([], { year: 'numeric', month: 'short', day: 'numeric' });
}

function ticks(status) {
  return { sent: '✓', delivered: '✓✓', read: '✓✓', failed: '✗' }[status] || '✓';
}

function tickClass(status) {
  if (status === 'read') return 'text-sky-500';
  if (status === 'failed') return 'text-red-500';
  return 'text-slate-400';
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
  exitSelect();
  replyTarget.value = null;
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
  sendFrom.value = newChatFrom.value;
  sendError.value = null;
  exitSelect();
  replyTarget.value = null;
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
    const nearBottom = msgsEl.value
      ? msgsEl.value.scrollHeight - msgsEl.value.scrollTop - msgsEl.value.clientHeight < 120
      : true;
    thread.value.push(...res.data);
    hydrateMedia(res.data);
    if (nearBottom) {
      await nextTick();
      scrollBottom();
    }
  }
}

function scrollBottom(smooth = false) {
  if (msgsEl.value) {
    msgsEl.value.scrollTo({ top: msgsEl.value.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
  }
}

function onScroll() {
  if (!msgsEl.value) return;
  showFab.value = msgsEl.value.scrollHeight - msgsEl.value.scrollTop - msgsEl.value.clientHeight > 300;
}

async function scrollToMsg(id) {
  const el = document.getElementById('msg-' + id);
  if (!el) return;
  el.scrollIntoView({ behavior: 'smooth', block: 'center' });
  flashId.value = id;
  setTimeout(() => { flashId.value = null; }, 1600);
}

// ---- Context menu ----

function openCtx(m, ev, isButton = false) {
  ev.preventDefault();
  ev.stopPropagation();
  const W = 210, H = 300;
  let x = isButton ? ev.clientX : ev.clientX;
  let y = ev.clientY;
  if (x + W > window.innerWidth) x = window.innerWidth - W - 8;
  if (y + H > window.innerHeight) y = window.innerHeight - H - 8;
  if (isRTL.value) x = Math.max(8, x - W);
  ctxMenu.value = { m, x, y };
}

function closeMenus() {
  ctxMenu.value = null;
  attachOpen.value = false;
  emojiOpen.value = false;
}

function startReply(m) {
  replyTarget.value = m;
  ctxMenu.value = null;
  nextTick(() => draftInput.value?.focus());
}

// ---- Reactions ----

async function sendReaction(m, emoji) {
  ctxMenu.value = null;
  try {
    const res = await metaTest.chatReact(m.id, emoji);
    if (!res.success) throw new Error(res.message);
    thread.value.push(res.data);
  } catch (e) {
    sendError.value = e.data?.message || e.message;
  }
}

// ---- Multi-select ----

function toggleSelect(id) {
  const i = selectedIds.value.indexOf(id);
  if (i === -1) selectedIds.value.push(id);
  else selectedIds.value.splice(i, 1);
}

function exitSelect() {
  selectMode.value = false;
  selectedIds.value = [];
}

function copySelected() {
  const texts = thread.value
    .filter((m) => selectedIds.value.includes(m.id) && m.body)
    .map((m) => m.body);
  if (texts.length) navigator.clipboard.writeText(texts.join('\n'));
  exitSelect();
}

async function hideSelected() {
  await hideMessages(selectedIds.value);
  exitSelect();
}

// ---- Forward ----

function openForward(ids) {
  fwdIds.value = [...ids];
  fwdPeer.value = '';
  fwdOpen.value = true;
  ctxMenu.value = null;
}

async function doForward(peer) {
  const target = (peer || '').replace(/\D/g, '');
  if (target.length < 7 || forwarding.value) return;
  forwarding.value = true;
  sendError.value = null;
  try {
    const res = await metaTest.chatForward(target, fwdIds.value, sendFrom.value);
    if (!res.success) throw new Error(res.errors?.[0] || res.message);
    if (res.errors?.length) sendError.value = res.errors.join(' · ');
    fwdOpen.value = false;
    exitSelect();
    loadConvs(true);
  } catch (e) {
    sendError.value = e.data?.message || e.message;
  } finally {
    forwarding.value = false;
  }
}

// ---- Local delete ----

async function hideMessages(ids) {
  ctxMenu.value = null;
  try {
    await metaTest.chatHide([...ids]);
    const hidden = new Set(ids);
    thread.value = thread.value.filter((m) => !hidden.has(m.id));
    loadConvs(true);
  } catch (e) {
    sendError.value = e.data?.message || e.message;
  }
}

// ---- Misc actions ----

function copyMsg(m) {
  ctxMenu.value = null;
  navigator.clipboard.writeText(m.body || '');
}

function downloadMsg(m) {
  ctxMenu.value = null;
  const a = document.createElement('a');
  a.href = mediaUrls.value[m.id];
  a.download = docName(m);
  a.click();
}

function insertEmoji(e) {
  draft.value += e;
  emojiOpen.value = false;
  draftInput.value?.focus();
}

function notifyTyping() {
  const now = Date.now();
  if (now - typingAt < 5000 || !activePeer.value) return;
  typingAt = now;
  metaTest.chatTyping(activePeer.value, sendFrom.value).catch(() => {});
}

// ---- Sending ----

async function send() {
  const text = draft.value.trim();
  if (!text || sending.value) return;
  sending.value = true;
  sendError.value = null;
  try {
    const res = await metaTest.chatSend(activePeer.value, text, sendFrom.value, replyTarget.value?.id || null);
    if (!res.success) throw new Error(res.message);
    thread.value.push(res.data);
    draft.value = '';
    replyTarget.value = null;
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
    if (replyTarget.value?.id) fd.append('reply_to', replyTarget.value.id);

    const res = await metaTest.chatSendMedia(activePeer.value, fd);
    if (!res.success) throw new Error(res.message);
    thread.value.push(res.data);
    replyTarget.value = null;
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
    if (replyTarget.value?.id) fd.append('reply_to', replyTarget.value.id);

    const res = await metaTest.chatSendMedia(activePeer.value, fd);
    if (!res.success) throw new Error(res.message);
    thread.value.push(res.data);
    replyTarget.value = null;
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

  document.addEventListener('keydown', escHandler);
});

function escHandler(e) {
  if (e.key === 'Escape') {
    ctxMenu.value = null;
    lightbox.value = null;
    fwdOpen.value = false;
    infoMsg.value = null;
    if (selectMode.value) exitSelect();
    replyTarget.value = null;
  }
}

onUnmounted(() => {
  clearInterval(poller);
  clearInterval(recTimer);
  document.removeEventListener('keydown', escHandler);
});
</script>

<style scoped>
.wx-chat-bg {
  background-color: #efeae2;
  background-image: radial-gradient(rgba(15, 23, 42, 0.045) 1px, transparent 1px);
  background-size: 22px 22px;
}
.wx-spinner {
  border: 2px solid #e2e8f0;
  border-top-color: #10b981;
  border-radius: 9999px;
  display: inline-block;
  animation: wx-spin 0.8s linear infinite;
}
@keyframes wx-spin {
  to { transform: rotate(360deg); }
}
@keyframes wx-flash {
  0%, 40% { box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.55); }
  100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
.wx-flash {
  animation: wx-flash 1.6s ease-out;
}
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
.ctx-item {
  display: block;
  width: 100%;
  text-align: start;
  padding: 0.45rem 0.9rem;
  font-size: 0.8rem;
  transition: background-color 0.1s;
}
.ctx-item:hover {
  background-color: #f1f5f9;
}
</style>
