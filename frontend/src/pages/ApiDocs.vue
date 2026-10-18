<template>
  <div class="min-h-screen bg-[#F8FAFC] text-slate-800 flex flex-col font-sans selection:bg-indigo-500/20 selection:text-indigo-900" :dir="isRTL ? 'rtl' : 'ltr'">
    
    <!-- Top Global Header -->
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur-md border-b border-slate-200/80">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
        <!-- Logo & Title -->
        <div class="flex items-center gap-3">
          <router-link to="/" class="flex items-center gap-2.5 group">
            <div class="p-1.5 rounded-xl bg-slate-50 border border-slate-100 group-hover:border-indigo-100 transition-colors">
              <img :src="logo" alt="Wavixa" class="h-6 sm:h-7 w-auto object-contain" />
            </div>
            <div class="flex items-center gap-1.5">
              <span class="text-base sm:text-lg font-black tracking-tight text-slate-900">Wavixa</span>
              <span class="text-[10px] font-extrabold px-1.5 py-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-100 uppercase">API Docs</span>
            </div>
          </router-link>

          <span class="hidden md:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-100">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>{{ isRTL ? 'REST API v1.0 جاهز للعمل' : 'REST API v1.0 Operational' }}</span>
          </span>
        </div>

        <!-- Right Side Nav Actions -->
        <div class="flex items-center gap-2 sm:gap-3">
          <!-- Fast Search Bar -->
          <div class="relative hidden sm:block w-48 md:w-64">
            <input
              v-model="searchQuery"
              type="text"
              :placeholder="isRTL ? 'ابحث عن endpoint أو ميزة...' : 'Search endpoints or features...'"
              class="w-full py-1.5 bg-slate-100/80 border border-slate-200/80 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-500 transition-all"
              :class="isRTL ? 'pl-3 pr-9' : 'pr-3 pl-9'"
            />
            <svg class="w-4 h-4 text-slate-400 absolute top-2.5" :class="isRTL ? 'right-3' : 'left-3'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>

          <!-- Language Switcher -->
          <LanguageSwitcher class="scale-90" />

          <!-- Auth / Navigation Buttons -->
          <template v-if="isAuthenticated">
            <router-link
              to="/dashboard"
              class="px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200/80 rounded-xl text-xs font-extrabold transition-all flex items-center gap-1.5"
            >
              <span>{{ isRTL ? 'لوحة التحكم' : 'Dashboard' }}</span>
              <svg class="w-3.5 h-3.5" :class="isRTL ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </router-link>
            <router-link
              to="/api-keys"
              class="hidden sm:inline-flex px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-extrabold transition-all shadow-xs"
            >
              {{ isRTL ? 'مفاتيح API' : 'My API Keys' }}
            </router-link>
          </template>
          <template v-else>
            <router-link
              to="/login"
              class="px-3.5 py-2 text-slate-600 hover:text-slate-900 text-xs font-bold transition-colors"
            >
              {{ isRTL ? 'تسجيل الدخول' : 'Log In' }}
            </router-link>
            <router-link
              to="/register"
              class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-black transition-all shadow-md shadow-indigo-100"
            >
              {{ isRTL ? 'إنشاء حساب مجاناً' : 'Get Started Free' }}
            </router-link>
          </template>
        </div>
      </div>
    </header>

    <!-- Main Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-1">
      
      <!-- Top Hero Banner -->
      <div class="bg-gradient-to-br from-indigo-900 via-indigo-950 to-slate-950 text-white rounded-3xl p-6 sm:p-10 shadow-xl relative overflow-hidden mb-10 border border-indigo-800/40">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
        
        <div class="relative z-10 max-w-3xl">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-indigo-300 text-xs font-extrabold border border-white/10 mb-4 backdrop-blur-xs">
            <span>{{ isRTL ? '⚡ الدليل الشامل للربط البرمجي' : '⚡ Complete API Integration Guide' }}</span>
            <span>•</span>
            <span>{{ isRTL ? 'بسيط، سريع، وموثوق' : 'Simple, Fast & Reliable' }}</span>
          </div>

          <h1 class="text-2xl sm:text-4xl lg:text-5xl font-black tracking-tight leading-tight mb-4">
            <template v-if="isRTL">
              اربط الواتساب بموقعك أو نظامك <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-teal-300">في دقيقتين فقط!</span>
            </template>
            <template v-else>
              Integrate WhatsApp into your App or SaaS <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-teal-300">in Minutes!</span>
            </template>
          </h1>

          <p class="text-slate-300 text-sm sm:text-base font-medium leading-relaxed mb-6">
            <template v-if="isRTL">
              منصة Wavixa توفر لك واجهة برمجية (REST API) فائقة البساطة لإرسال واستقبال رسائل WhatsApp، الصور، الملفات، أكواد الـ OTP والتحقق، مع إمكانية ربط أجهزة عملائك عن بُعد بكل سهولة.
            </template>
            <template v-else>
              Wavixa provides a developer-friendly REST API to send and receive WhatsApp messages, media, PDFs, and OTP verification codes. Embed remote QR pairing directly into your custom applications and platforms.
            </template>
          </p>

          <!-- Quick Metrics Bar -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2 border-t border-white/10">
            <div class="bg-white/5 rounded-2xl p-3 border border-white/5">
              <span class="block text-[11px] text-slate-400 font-bold">Base URL</span>
              <span class="block text-xs font-mono font-bold text-emerald-400 mt-0.5 truncate" dir="ltr">{{ baseUrl }}</span>
            </div>
            <div class="bg-white/5 rounded-2xl p-3 border border-white/5">
              <span class="block text-[11px] text-slate-400 font-bold">{{ isRTL ? 'صيغة البيانات' : 'Format' }}</span>
              <span class="block text-xs font-mono font-bold text-indigo-300 mt-0.5" dir="ltr">JSON (REST)</span>
            </div>
            <div class="bg-white/5 rounded-2xl p-3 border border-white/5">
              <span class="block text-[11px] text-slate-400 font-bold">{{ isRTL ? 'المصادقة' : 'Authentication' }}</span>
              <span class="block text-xs font-mono font-bold text-amber-300 mt-0.5" dir="ltr">X-API-Key Header</span>
            </div>
            <div class="bg-white/5 rounded-2xl p-3 border border-white/5">
              <span class="block text-[11px] text-slate-400 font-bold">{{ isRTL ? 'سرعة الإرسال' : 'Delivery Speed' }}</span>
              <span class="block text-xs font-bold text-white mt-0.5">{{ isRTL ? 'فوري & طوابير ذكية' : 'Instant & Smart Queue' }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Live Sandbox Key Injector Box -->
      <div class="bg-white border border-slate-200/80 rounded-3xl p-5 sm:p-6 shadow-sm mb-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start gap-3.5">
          <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shrink-0 text-lg">
            🔑
          </div>
          <div>
            <h3 class="text-sm font-black text-slate-900">
              {{ isRTL ? 'مفتاح الـ API الخاص بك للتجربة التفاعلية' : 'Interactive Sandbox API Key' }}
            </h3>
            <p class="text-xs text-slate-500 font-medium mt-0.5">
              {{ isRTL ? 'ضع مفتاحك هنا وسيتم تحديث جميع الأكواد البرمجية بالأسفل فوراً لنسخها وتشغيلها بضغطة زر.' : 'Paste your API key here to automatically inject it into all code snippets across this documentation.' }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2 w-full md:w-auto">
          <div class="relative flex-1 md:w-80">
            <input
              v-model="apiKey"
              type="text"
              :placeholder="isRTL ? 'الصق مفتاحك: wav_live_...' : 'Paste your key: wav_live_...'"
              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all"
              dir="ltr"
            />
          </div>
          <button
            v-if="apiKey"
            @click="apiKey = ''"
            class="p-2.5 text-slate-400 hover:text-slate-600 bg-slate-100 rounded-xl transition-colors cursor-pointer"
            :title="isRTL ? 'مسح' : 'Clear'"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>
      </div>

      <!-- 3-Step Super Simple Getting Started Guide -->
      <div id="quickstart" class="mb-14 scroll-mt-24">
        <div class="flex items-center gap-2 mb-2">
          <span class="px-2.5 py-0.5 rounded-full text-[11px] font-extrabold bg-indigo-50 text-indigo-700 border border-indigo-100">
            {{ isRTL ? 'ابدأ الآن' : 'Quick Start' }}
          </span>
          <h2 class="text-xl sm:text-2xl font-black text-slate-900">
            {{ isRTL ? 'كيف تبدأ الربط في 3 خطوات بسيطة؟' : 'Get Connected in 3 Easy Steps' }}
          </h2>
        </div>
        <p class="text-xs sm:text-sm text-slate-500 font-medium mb-6">
          {{ isRTL ? 'لا داعي للتعقيد! اتبع هذه الخطوات الثلاث لتجعل نظامك يرسل رسائل واتساب خلال دقائق معدودة:' : 'Follow these 3 straightforward steps to start sending WhatsApp messages from your application:' }}
        </p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
          <!-- Step 1 -->
          <div class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-xs relative flex flex-col justify-between hover:border-indigo-200 transition-colors">
            <div>
              <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white font-black text-xs flex items-center justify-center mb-4 shadow-sm shadow-indigo-200">
                1
              </div>
              <h3 class="text-base font-black text-slate-900 mb-2">
                {{ isRTL ? 'احصل على مفتاح API' : 'Get your API Key' }}
              </h3>
              <p class="text-xs text-slate-500 font-medium leading-relaxed mb-4">
                <template v-if="isRTL">
                  سجل حسابك وادخل على صفحة <router-link to="/api-keys" class="text-indigo-600 font-bold underline">مفاتيح API</router-link> ثم اضغط "إنشاء مفتاح جديد".
                </template>
                <template v-else>
                  Create an account, navigate to <router-link to="/api-keys" class="text-indigo-600 font-bold underline">API Keys</router-link>, and generate a new secret key.
                </template>
              </p>
            </div>
            <div class="bg-slate-50 border border-slate-100 rounded-2xl p-3 text-[11px] font-mono text-slate-600 flex items-center justify-between" dir="ltr">
              <span>X-API-Key: wav_live_xxx</span>
              <span class="text-emerald-600 font-bold">{{ isRTL ? 'جاهز' : 'Ready' }}</span>
            </div>
          </div>

          <!-- Step 2 -->
          <div class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-xs relative flex flex-col justify-between hover:border-indigo-200 transition-colors">
            <div>
              <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white font-black text-xs flex items-center justify-center mb-4 shadow-sm shadow-indigo-200">
                2
              </div>
              <h3 class="text-base font-black text-slate-900 mb-2">
                {{ isRTL ? 'اربط جهاز الواتساب' : 'Link WhatsApp Device' }}
              </h3>
              <p class="text-xs text-slate-500 font-medium leading-relaxed mb-4">
                <template v-if="isRTL">
                  من صفحة <router-link to="/devices" class="text-indigo-600 font-bold underline">الأجهزة</router-link> امسح كود الـ QR من تطبيق واتساب بهاتفك ليصبح الجهاز متصلاً فوراً.
                </template>
                <template v-else>
                  On the <router-link to="/devices" class="text-indigo-600 font-bold underline">Devices</router-link> page, scan the QR code with your WhatsApp app to link instantly.
                </template>
              </p>
            </div>
            <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-3 text-[11px] font-bold text-emerald-800 flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
              <span>{{ isRTL ? 'حالة الجهاز: متصل (Connected)' : 'Device Status: Connected' }}</span>
            </div>
          </div>

          <!-- Step 3 -->
          <div class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-xs relative flex flex-col justify-between hover:border-indigo-200 transition-colors">
            <div>
              <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white font-black text-xs flex items-center justify-center mb-4 shadow-sm shadow-indigo-200">
                3
              </div>
              <h3 class="text-base font-black text-slate-900 mb-2">
                {{ isRTL ? 'أرسل أول رسالة برمجياً' : 'Send Your First Message' }}
              </h3>
              <p class="text-xs text-slate-500 font-medium leading-relaxed mb-4">
                <template v-if="isRTL">
                  استدعِ مسار <code class="text-indigo-600 font-mono font-bold">/public/whatsapp/send-message</code> ومرر رقم المستلم ونص الرسالة. مبروك!
                </template>
                <template v-else>
                  Make a POST request to <code class="text-indigo-600 font-mono font-bold">/public/whatsapp/send-message</code> with recipient and message body. That's it!
                </template>
              </p>
            </div>
            <div class="bg-indigo-50 border border-indigo-100 rounded-2xl p-3 text-[11px] font-bold text-indigo-800 flex items-center justify-between">
              <span>{{ isRTL ? 'الاستجابة: 200 OK' : 'Response: 200 OK' }}</span>
              <span class="text-xs">🚀 {{ isRTL ? 'تم الإرسال' : 'Delivered' }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Layout with Sticky Sidebar & Endpoint Sections -->
      <div class="flex flex-col lg:flex-row gap-8 items-start">
        
        <!-- Sidebar Navigation -->
        <aside class="w-full lg:w-72 bg-white rounded-3xl border border-slate-200/80 p-5 shadow-xs lg:sticky lg:top-20 shrink-0">
          <h3 class="text-xs font-black text-slate-400 uppercase tracking-wider mb-3 px-2">
            {{ isRTL ? 'فهرس التوثيق السريع' : 'API Table of Contents' }}
          </h3>
          
          <nav class="space-y-1 text-xs">
            <template v-for="cat in categories" :key="cat.id">
              <div class="pt-3 pb-1 px-2 font-black text-slate-900 flex items-center gap-2">
                <span>{{ cat.icon }}</span>
                <span>{{ isRTL ? cat.title_ar : cat.title_en }}</span>
              </div>
              
              <a
                v-for="item in cat.items"
                :key="item.id"
                :href="`#${item.id}`"
                @click.prevent="scrollTo(item.id)"
                :class="[
                  'block px-3 py-2 rounded-xl font-bold transition-all truncate cursor-pointer',
                  activeSection === item.id
                    ? 'bg-indigo-50 text-indigo-700 font-black'
                    : 'text-slate-500 hover:text-slate-900 hover:bg-slate-50'
                ]"
              >
                {{ isRTL ? item.title_ar : item.title_en }}
              </a>
            </template>
          </nav>

          <div class="mt-6 pt-5 border-t border-slate-100">
            <a
              href="#sdks"
              @click.prevent="scrollTo('sdks')"
              class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-slate-900 text-white font-black text-xs hover:bg-slate-800 transition-colors justify-center text-center"
            >
              <span>💻 {{ isRTL ? 'أكواد جاهزة (PHP / Node / Python)' : 'Code Examples (PHP / JS / Python)' }}</span>
            </a>
          </div>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-1 min-w-0 space-y-12">
          
          <!-- Category 1: Status & Diagnostics -->
          <section id="status-group" class="space-y-6">
            <div class="border-b border-slate-200/80 pb-3">
              <div class="flex items-center gap-2">
                <span class="text-xl">⚡</span>
                <h2 class="text-xl font-black text-slate-900">
                  {{ isRTL ? '1. فحص الاتصال وحالة الحساب' : '1. Status & Health Diagnostics' }}
                </h2>
              </div>
              <p class="text-xs text-slate-500 font-medium mt-1">
                {{ isRTL ? 'تأكد من صلاحية المفتاح، حالة اتصال جهازك بالإنترنت، وحالة طابور الرسائل.' : 'Validate your API key credentials, check WhatsApp device connectivity, and monitor queue status.' }}
              </p>
            </div>

            <div v-for="ep in statusEndpoints" :key="ep.id" :id="ep.id" class="scroll-mt-24">
              <EndpointCard :endpoint="ep" :api-key="apiKey" :base-url="baseUrl" />
            </div>
          </section>

          <!-- Category 2: Sending Messages -->
          <section id="messages-group" class="space-y-6">
            <div class="border-b border-slate-200/80 pb-3">
              <div class="flex items-center gap-2">
                <span class="text-xl">💬</span>
                <h2 class="text-xl font-black text-slate-900">
                  {{ isRTL ? '2. إرسال مختلف أنواع الرسائل والوسائط' : '2. Sending Messages & Media' }}
                </h2>
              </div>
              <p class="text-xs text-slate-500 font-medium mt-1">
                {{ isRTL ? 'إرسال نصوص، صور، ملفات PDF، فيديوهات، رسائل صوتية، بطاقات جهات اتصال ومواقع جغرافية.' : 'Send text messages, images, PDF documents, videos, voice notes, location coordinates, and contact cards.' }}
              </p>
            </div>

            <div v-for="ep in messagingEndpoints" :key="ep.id" :id="ep.id" class="scroll-mt-24">
              <EndpointCard :endpoint="ep" :api-key="apiKey" :base-url="baseUrl" />
            </div>
          </section>

          <!-- Category 3: OTP Verification -->
          <section id="otp-group" class="space-y-6">
            <div class="border-b border-slate-200/80 pb-3">
              <div class="flex items-center gap-2">
                <span class="text-xl">🔢</span>
                <h2 class="text-xl font-black text-slate-900">
                  {{ isRTL ? '3. رموز التحقق والـ OTP' : '3. OTP & Passwordless Verification' }}
                </h2>
              </div>
              <p class="text-xs text-slate-500 font-medium mt-1">
                {{ isRTL ? 'نظام متكامل لتوليد رموز OTP وتأكيد عمليات التسجيل وتسجيل الدخول واسترجاع كلمة المرور للعملاء.' : 'Built-in OTP generation, expiry management, and verification for secure user onboarding and 2FA.' }}
              </p>
            </div>

            <div v-for="ep in otpEndpoints" :key="ep.id" :id="ep.id" class="scroll-mt-24">
              <EndpointCard :endpoint="ep" :api-key="apiKey" :base-url="baseUrl" />
            </div>
          </section>

          <!-- Category 4: Remote Device Pairing -->
          <section id="pairing-group" class="space-y-6">
            <div class="border-b border-slate-200/80 pb-3">
              <div class="flex items-center gap-2">
                <span class="text-xl">📲</span>
                <h2 class="text-xl font-black text-slate-900">
                  {{ isRTL ? '4. ربط الأجهزة عن بُعد (Remote Pairing QR)' : '4. Remote Device Pairing (QR Code for SaaS)' }}
                </h2>
              </div>
              <p class="text-xs text-slate-500 font-medium mt-1">
                {{ isRTL ? 'خاصية حصرية لمنصات الـ SaaS والشركات: اعرض QR Code داخل موقعك أو لوحة التحكم الخاصة بك ليمسحه عميلك ويربط هاتفه مباشرة!' : 'White-label QR pairing: render dynamic WhatsApp QR codes inside your SaaS portal or mobile app so users can link their devices directly.' }}
              </p>
            </div>

            <!-- Remote Pairing Tutorial Card -->
            <div class="bg-gradient-to-br from-indigo-50 to-white rounded-3xl border border-indigo-100 p-6 shadow-xs">
              <h3 class="text-sm font-black text-indigo-950 mb-3 flex items-center gap-2">
                <span>💡 {{ isRTL ? 'كيف يعمل الـ Remote Pairing في موقعك الخارجي؟' : 'How does Remote Pairing work in your SaaS?' }}</span>
              </h3>
              <ol class="space-y-2.5 text-xs text-slate-600 font-medium list-decimal list-inside pr-2 leading-relaxed">
                <template v-if="isRTL">
                  <li>من لوحة التحكم في صفحة مفاتيح API، تأكد من تفعيل خيار <strong class="text-indigo-700">السماح بربط الأجهزة عن بُعد (Allow Remote Pairing)</strong>.</li>
                  <li>يقوم خادمك باستدعاء مسار <code class="text-indigo-600 font-mono font-bold">POST /public/whatsapp/pairing-sessions</code>.</li>
                  <li>يُرجع لك النظام كود QR (نصي أو صورة)، تقوم بعرضه في صفحة موقعك للمستخدم ليمسحه من تطبيق الواتساب.</li>
                  <li>يقوم خادمك بعمل polling عبر <code class="text-indigo-600 font-mono font-bold">GET /public/whatsapp/pairing-sessions/{id}</code> لمعرفة متى أصبح متصلاً.</li>
                  <li>بمجرد الاتصال، يرجع لك <code class="text-indigo-600 font-mono font-bold">session_token</code> ورقم الهاتف، ويمكنك فوراً استخدامهما لإرسال الرسائل عبر تمرير معامل <code class="font-mono text-indigo-700">device</code>.</li>
                </template>
                <template v-else>
                  <li>From your Wavixa dashboard under API Keys, ensure <strong class="text-indigo-700">Allow Remote Pairing</strong> is enabled.</li>
                  <li>Your backend calls <code class="text-indigo-600 font-mono font-bold">POST /public/whatsapp/pairing-sessions</code>.</li>
                  <li>Render the returned QR code on your custom dashboard for the user to scan with WhatsApp.</li>
                  <li>Poll <code class="text-indigo-600 font-mono font-bold">GET /public/whatsapp/pairing-sessions/{id}</code> until the status changes to <strong class="text-emerald-600 font-bold">connected</strong>.</li>
                  <li>Use the returned <code class="text-indigo-600 font-mono font-bold">session_token</code> as the <code class="font-mono text-indigo-700">device</code> parameter in all messaging endpoints.</li>
                </template>
              </ol>
            </div>

            <div v-for="ep in pairingEndpoints" :key="ep.id" :id="ep.id" class="scroll-mt-24">
              <EndpointCard :endpoint="ep" :api-key="apiKey" :base-url="baseUrl" />
            </div>
          </section>

          <!-- Category 5: Scheduling & Delayed Messaging -->
          <section id="scheduling-section" class="scroll-mt-24 bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-8 shadow-xs">
            <div class="flex items-center gap-2 mb-3">
              <span class="text-xl">⏱️</span>
              <h2 class="text-xl font-black text-slate-900">
                {{ isRTL ? '5. جدولة وتأخير إرسال الرسائل' : '5. Message Scheduling & Delays' }}
              </h2>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 font-medium leading-relaxed mb-6">
              {{ isRTL ? 'جميع مسارات الإرسال تدعم الجدولة المباشرة دون الحاجة لبرمجة Cron Jobs في موقعك. يكفي تمرير أحد المعاملين التاليين:' : 'All message sending endpoints support automated scheduling without external cron jobs. Simply pass either parameter:' }}
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
              <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4">
                <div class="flex items-center justify-between mb-2">
                  <span class="font-mono text-xs font-bold text-indigo-600" dir="ltr">scheduled_at</span>
                  <span class="text-[10px] bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded font-bold">ISO 8601 Date</span>
                </div>
                <p class="text-xs text-slate-600 mb-2">
                  {{ isRTL ? 'لتحديد وقت وتاريخ محدد في المستقبل للإرسال.' : 'Specify an exact future timestamp for message delivery.' }}
                </p>
                <div class="bg-slate-900 rounded-xl p-3 text-[11px] font-mono text-emerald-400" dir="ltr">
                  "scheduled_at": "2026-10-15T14:30:00Z"
                </div>
              </div>

              <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4">
                <div class="flex items-center justify-between mb-2">
                  <span class="font-mono text-xs font-bold text-indigo-600" dir="ltr">delay_minutes</span>
                  <span class="text-[10px] bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded font-bold">{{ isRTL ? 'Integer (دقائق)' : 'Integer (minutes)' }}</span>
                </div>
                <p class="text-xs text-slate-600 mb-2">
                  {{ isRTL ? 'لتأخير إرسال الرسالة بعد عدد محدد من الدقائق (مثلاً بعد 60 دقيقة من الشراء).' : 'Delay sending by a specified number of minutes (e.g., 60 minutes after checkout).' }}
                </p>
                <div class="bg-slate-900 rounded-xl p-3 text-[11px] font-mono text-emerald-400" dir="ltr">
                  "delay_minutes": 60
                </div>
              </div>
            </div>
          </section>

          <!-- Category 6: Webhooks & Event Handling -->
          <section id="webhooks-section" class="scroll-mt-24 bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-8 shadow-xs">
            <div class="flex items-center gap-2 mb-3">
              <span class="text-xl">🔔</span>
              <h2 class="text-xl font-black text-slate-900">
                {{ isRTL ? '6. خطافات الويب (Webhooks)' : '6. Real-Time Webhooks' }}
              </h2>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 font-medium leading-relaxed mb-6">
              {{ isRTL ? 'استقبل إشعارات فورية على خادمك عند حدوث أحداث على حسابك، مثل: استلام رسالة جديدة، تأكيد تسليم رسالة، أو انفصال جهاز.' : 'Receive instant HTTP POST callbacks on your server when messages are delivered, read, failed, or when devices connect or disconnect.' }}
            </p>

            <div class="space-y-4">
              <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4">
                <h4 class="text-xs font-black text-slate-900 mb-2">
                  {{ isRTL ? 'الأحداث المدعومة (Events):' : 'Supported Events:' }}
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                  <div class="flex items-center gap-2 text-slate-700">
                    <code class="font-mono text-indigo-600 font-bold bg-indigo-50 px-2 py-0.5 rounded" dir="ltr">message.sent</code>
                    <span>{{ isRTL ? 'تم إرسال الرسالة من الهاتف' : 'Message dispatched from device' }}</span>
                  </div>
                  <div class="flex items-center gap-2 text-slate-700">
                    <code class="font-mono text-indigo-600 font-bold bg-indigo-50 px-2 py-0.5 rounded" dir="ltr">message.delivered</code>
                    <span>{{ isRTL ? 'تم تسليم الرسالة للعميل (صحين)' : 'Delivered to recipient' }}</span>
                  </div>
                  <div class="flex items-center gap-2 text-slate-700">
                    <code class="font-mono text-indigo-600 font-bold bg-indigo-50 px-2 py-0.5 rounded" dir="ltr">message.failed</code>
                    <span>{{ isRTL ? 'فشل الإرسال (رقم خطأ أو غير مسجل)' : 'Delivery failed' }}</span>
                  </div>
                  <div class="flex items-center gap-2 text-slate-700">
                    <code class="font-mono text-indigo-600 font-bold bg-indigo-50 px-2 py-0.5 rounded" dir="ltr">device.disconnected</code>
                    <span>{{ isRTL ? 'انفصال جلسة الواتساب' : 'Device disconnected' }}</span>
                  </div>
                </div>
              </div>

              <div>
                <h4 class="text-xs font-black text-slate-400 uppercase tracking-wider mb-2">
                  {{ isRTL ? 'شكل البيانات المرسلة لخادمك (Webhook Payload Example)' : 'Webhook Payload Example' }}
                </h4>
                <div class="bg-slate-950 rounded-2xl p-4 text-left" dir="ltr">
                  <pre class="text-xs font-mono text-emerald-400 leading-relaxed overflow-x-auto">{
  "event": "message.delivered",
  "timestamp": "2026-09-08T12:00:00Z",
  "data": {
    "message_id": 1248,
    "to": "+201012345678",
    "status": "delivered"
  }
}</pre>
                </div>
              </div>
            </div>
          </section>

          <!-- Category 7: Full SDK & Integration Guides -->
          <section id="sdks" class="scroll-mt-24 bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-8 shadow-xs">
            <div class="flex items-center gap-2 mb-3">
              <span class="text-xl">💻</span>
              <h2 class="text-xl font-black text-slate-900">
                {{ isRTL ? '7. أمثلة كاملة بلغات البرمجة (Ready Code Snippets)' : '7. Ready Code SDKs & Examples' }}
              </h2>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 font-medium leading-relaxed mb-6">
              {{ isRTL ? 'انسخ الكود بالكامل مباشرة وضعه في مشروعك (Laravel, Node.js, Python) لبدء الإرسال فوراً:' : 'Copy and paste complete production-ready code examples directly into your application:' }}
            </p>

            <div class="space-y-6">
              <!-- Laravel / PHP Example -->
              <div class="border border-slate-200 rounded-2xl overflow-hidden">
                <div class="bg-slate-100 px-5 py-3 flex items-center justify-between border-b border-slate-200">
                  <span class="text-xs font-black text-slate-800">🐘 PHP / Laravel (Http Client)</span>
                  <span class="text-[10px] font-mono text-slate-500">PHP 8+</span>
                </div>
                <div class="bg-slate-950 p-5 text-left" dir="ltr">
                  <pre class="text-xs font-mono text-indigo-300 leading-relaxed overflow-x-auto">use Illuminate\Support\Facades\Http;

$response = Http::withHeaders([
    'X-API-Key' => '{{ apiKey || "YOUR_API_KEY" }}',
    'Accept' => 'application/json',
])->post('{{ baseUrl }}/public/whatsapp/send-message', [
    'to' => '+201012345678',
    'message' => 'Hello! Your confirmation code is 582190',
]);

if ($response->successful()) {
    $result = $response->json();
    // Message Queued & Dispatched Successfully!
}</pre>
                </div>
              </div>

              <!-- Node.js / Express Example -->
              <div class="border border-slate-200 rounded-2xl overflow-hidden">
                <div class="bg-slate-100 px-5 py-3 flex items-center justify-between border-b border-slate-200">
                  <span class="text-xs font-black text-slate-800">⚡ Node.js / TypeScript (Axios / Fetch)</span>
                  <span class="text-[10px] font-mono text-slate-500">Node 18+</span>
                </div>
                <div class="bg-slate-950 p-5 text-left" dir="ltr">
                  <pre class="text-xs font-mono text-emerald-300 leading-relaxed overflow-x-auto">import axios from 'axios';

async function sendWhatsApp(to, message) {
  try {
    const res = await axios.post('{{ baseUrl }}/public/whatsapp/send-message', {
      to: to,
      message: message
    }, {
      headers: {
        'X-API-Key': '{{ apiKey || "YOUR_API_KEY" }}',
        'Content-Type': 'application/json'
      }
    });

    console.log('Sent:', res.data);
    return res.data;
  } catch (err) {
    console.error('Error:', err.response?.data || err.message);
  }
}</pre>
                </div>
              </div>

              <!-- Python Example -->
              <div class="border border-slate-200 rounded-2xl overflow-hidden">
                <div class="bg-slate-100 px-5 py-3 flex items-center justify-between border-b border-slate-200">
                  <span class="text-xs font-black text-slate-800">🐍 Python (Requests)</span>
                  <span class="text-[10px] font-mono text-slate-500">Python 3.8+</span>
                </div>
                <div class="bg-slate-950 p-5 text-left" dir="ltr">
                  <pre class="text-xs font-mono text-yellow-300 leading-relaxed overflow-x-auto">import requests

url = "{{ baseUrl }}/public/whatsapp/send-message"
headers = {
    "X-API-Key": "{{ apiKey || "YOUR_API_KEY" }}",
    "Content-Type": "application/json",
    "Accept": "application/json"
}
payload = {
    "to": "+201012345678",
    "message": "Welcome to Wavixa!"
}

response = requests.post(url, headers=headers, json=payload)
print(response.json())</pre>
                </div>
              </div>
            </div>
          </section>

          <!-- Category 8: Error Codes & Troubleshooting -->
          <section id="troubleshooting" class="scroll-mt-24 bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-8 shadow-xs">
            <div class="flex items-center gap-2 mb-3">
              <span class="text-xl">⚠️</span>
              <h2 class="text-xl font-black text-slate-900">
                {{ isRTL ? '8. رموز الأخطاء والحلول السريعة' : '8. HTTP Status Codes & Troubleshooting' }}
              </h2>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 font-medium leading-relaxed mb-6">
              {{ isRTL ? 'جميع الأخطاء مصحوبة برسائل واضحة وكود HTTP قياسي لتسهيل معالجتها برمجياً:' : 'All API errors return standardized HTTP status codes with detailed JSON error messages:' }}
            </p>

            <div class="overflow-x-auto border border-slate-100 rounded-2xl">
              <table class="w-full text-xs" :class="isRTL ? 'text-right' : 'text-left'">
                <thead class="bg-slate-50/70 border-b border-slate-100">
                  <tr>
                    <th class="px-5 py-3.5 font-bold text-slate-500">{{ isRTL ? 'رمز HTTP' : 'HTTP Code' }}</th>
                    <th class="px-5 py-3.5 font-bold text-slate-500">{{ isRTL ? 'المعنى' : 'Meaning' }}</th>
                    <th class="px-5 py-3.5 font-bold text-slate-500">{{ isRTL ? 'السبب وطريقة الحل' : 'Cause & Resolution' }}</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                  <tr class="hover:bg-slate-50/50">
                    <td class="px-5 py-4 font-mono font-bold text-rose-600" dir="ltr">401 Unauthorized</td>
                    <td class="px-5 py-4 font-bold">{{ isRTL ? 'مفتاح API غير صالح' : 'Invalid API Key' }}</td>
                    <td class="px-5 py-4 text-slate-500">{{ isRTL ? 'تأكد من تمرير X-API-Key صحيح وأن المفتاح مفعل في لوحة التحكم.' : 'Ensure you pass a valid active X-API-Key header generated from your dashboard.' }}</td>
                  </tr>
                  <tr class="hover:bg-slate-50/50">
                    <td class="px-5 py-4 font-mono font-bold text-amber-600" dir="ltr">400 Bad Request</td>
                    <td class="px-5 py-4 font-bold">{{ isRTL ? 'الواتساب غير متصل' : 'WhatsApp Disconnected' }}</td>
                    <td class="px-5 py-4 text-slate-500">{{ isRTL ? 'جهازك غير متصل حالياً، ادخل لوحة التحكم > الأجهزة وامسح كود الـ QR لإعادة التوصيل.' : 'No active WhatsApp session found. Re-pair your device via QR from the Devices page.' }}</td>
                  </tr>
                  <tr class="hover:bg-slate-50/50">
                    <td class="px-5 py-4 font-mono font-bold text-amber-600" dir="ltr">403 Forbidden</td>
                    <td class="px-5 py-4 font-bold">{{ isRTL ? 'نفاد رصيد الباقة' : 'Out of Message Credits' }}</td>
                    <td class="px-5 py-4 text-slate-500">{{ isRTL ? 'انتهى عدد الرسائل المتاحة في باقتك، قم بتجديد أو ترقية اشتراكك من صفحة الباقات.' : 'Your message quota has been exhausted. Renew or upgrade your plan.' }}</td>
                  </tr>
                  <tr class="hover:bg-slate-50/50">
                    <td class="px-5 py-4 font-mono font-bold text-sky-600" dir="ltr">422 Unprocessable</td>
                    <td class="px-5 py-4 font-bold">{{ isRTL ? 'معاملات ناقصة أو غير صحيحة' : 'Validation Error' }}</td>
                    <td class="px-5 py-4 text-slate-500">{{ isRTL ? 'تأكد من إرسال رقم الهاتف بصيغة دولية صحيحة (مثل +201012345678) ونص الرسالة.' : 'Invalid parameters provided. Ensure the phone number includes the international country code.' }}</td>
                  </tr>
                  <tr class="hover:bg-slate-50/50">
                    <td class="px-5 py-4 font-mono font-bold text-purple-600" dir="ltr">429 Rate Limited</td>
                    <td class="px-5 py-4 font-bold">{{ isRTL ? 'تجاوز الحد المسموح في الدقيقة' : 'Rate Limit Exceeded' }}</td>
                    <td class="px-5 py-4 text-slate-500">{{ isRTL ? 'تم إرسال عدد طلبات أعلى من المعدل المسموح في الدقيقة، انتظر لحظات وأعد الإرسال.' : 'Too many requests sent in a short window. Throttle requests or implement queuing.' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

        </main>
      </div>

    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200/80 py-8 mt-20 text-center text-xs text-slate-500">
      <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-2">
          <img :src="logo" alt="Wavixa" class="h-5 w-auto" />
          <span class="font-bold text-slate-700">Wavixa API Integration Platform</span>
        </div>
        <div class="flex items-center gap-6 font-medium">
          <router-link to="/pricing" class="hover:text-slate-900 transition-colors">{{ isRTL ? 'الأسعار والباقات' : 'Pricing' }}</router-link>
          <router-link to="/login" class="hover:text-slate-900 transition-colors">{{ isRTL ? 'تسجيل الدخول' : 'Log In' }}</router-link>
          <router-link to="/register" class="hover:text-slate-900 transition-colors">{{ isRTL ? 'إنشاء حساب' : 'Register' }}</router-link>
        </div>
      </div>
    </footer>

  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import auth from '@/services/auth';
import logo from '@/assets/logo.png';
import EndpointCard from '@/components/docs/EndpointCard.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { isRTL } from '@/i18n';

export default {
  name: 'ApiDocs',
  components: {
    EndpointCard,
    LanguageSwitcher,
  },
  setup() {
    const isAuthenticated = ref(auth.isAuthenticated());
    const apiKey = ref('');
    const searchQuery = ref('');
    const activeSection = ref('quickstart');

    const origin = window.location.origin || 'https://wavixa.net';
    const baseUrl = `${origin}/api`;

    const scrollTo = (id) => {
      activeSection.value = id;
      const el = document.getElementById(id);
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    };

    const categories = [
      {
        id: 'cat-status',
        title_ar: 'الفحص والتشخيص',
        title_en: 'Status & Health',
        icon: '⚡',
        items: [
          { id: 'check-api-key', title_ar: 'فحص صلاحية المفتاح والرصيد', title_en: 'Validate API Key & Account Balance' },
          { id: 'check-whatsapp-status', title_ar: 'فحص حالة جهاز الواتساب', title_en: 'Check WhatsApp Connection Status' },
          { id: 'check-queue-status', title_ar: 'فحص طابور الرسائل', title_en: 'Check Message Queue Status' },
        ],
      },
      {
        id: 'cat-messages',
        title_ar: 'إرسال الرسائل',
        title_en: 'Messaging & Media',
        icon: '💬',
        items: [
          { id: 'send-text-message', title_ar: 'إرسال رسالة نصية', title_en: 'Send Text Message' },
          { id: 'get-message-status', title_ar: 'الاستعلام عن حالة رسالة بالـ ID', title_en: 'Get Message Status by ID' },
          { id: 'send-image-message', title_ar: 'إرسال صورة (Image)', title_en: 'Send Image' },
          { id: 'send-file-message', title_ar: 'إرسال ملف/مستند (Document)', title_en: 'Send Document (PDF)' },
          { id: 'send-video-message', title_ar: 'إرسال مقطع فيديو (Video)', title_en: 'Send Video' },
          { id: 'send-voice-message', title_ar: 'إرسال رسالة صوتية (Voice)', title_en: 'Send Voice Message' },
          { id: 'send-location-message', title_ar: 'إرسال موقع جغرافي (Location)', title_en: 'Send Location' },
          { id: 'send-contact-message', title_ar: 'إرسال جهة اتصال (Contact)', title_en: 'Send Contact Card' },
        ],
      },
      {
        id: 'cat-otp',
        title_ar: 'رموز التحقق OTP',
        title_en: 'OTP Verification',
        icon: '🔢',
        items: [
          { id: 'send-otp-code', title_ar: 'توليد وإرسال رمز OTP', title_en: 'Send OTP Code' },
          { id: 'verify-otp-code', title_ar: 'التحقق من صحة OTP', title_en: 'Verify OTP Code' },
        ],
      },
      {
        id: 'cat-pairing',
        title_ar: 'ربط الأجهزة عن بُعد',
        title_en: 'Remote Device Pairing',
        icon: '📲',
        items: [
          { id: 'create-pairing-session', title_ar: 'إنشاء جلسة QR Pairing', title_en: 'Create Pairing Session' },
          { id: 'get-pairing-status', title_ar: 'جلب حالة الـ QR والاتصال', title_en: 'Check Pairing Status' },
          { id: 'delete-pairing-session', title_ar: 'حذف/فصل جلسة الـ QR', title_en: 'Delete Pairing Session' },
        ],
      },
    ];

    // Status Endpoints
    const statusEndpoints = [
      {
        id: 'check-api-key',
        title_ar: 'التحقق من صحة مفتاح API والرصيد',
        title_en: 'Validate API Key & Account Info',
        method: 'GET',
        url: '/public/status',
        badge_ar: 'تشخيص',
        badge_en: 'Diagnostic',
        description_ar: 'استخدم هذا المسار للتأكد من أن مفتاح API يعمل بصورة صحيحة وجلب معلومات الحساب.',
        description_en: 'Verify your API key is valid and retrieve account details, remaining balance, and usage statistics.',
        headers: [
          { name: 'X-API-Key', value: 'wav_live_...', required: true },
          { name: 'Accept', value: 'application/json', required: true },
        ],
        parameters: [],
        requestExample: {},
        responseExample: {
          success: true,
          message: 'API key is valid',
          data: {
            user: {
              id: 42,
              name: 'John Doe',
              email: 'john@example.com',
            },
            key_name: 'Production Server Key',
            requests_count: 1420,
            last_used_at: '2026-09-08T10:15:00Z',
          },
        },
        errors: [
          { status: 401, message_ar: 'مفتاح API غير صالح أو غير موجود', message_en: 'Invalid or missing API key' },
        ],
      },
      {
        id: 'check-whatsapp-status',
        title_ar: 'فحص حالة اتصال جهاز WhatsApp',
        title_en: 'Check WhatsApp Device Connection',
        method: 'GET',
        url: '/public/whatsapp/status',
        badge_ar: 'اتصال',
        badge_en: 'Connection',
        description_ar: 'فحص ما إذا كان هاتف الواتساب متصلاً وجاهزاً لإرسال الرسائل.',
        description_en: 'Verify if your linked WhatsApp device is currently online and ready to dispatch messages.',
        headers: [
          { name: 'X-API-Key', value: 'wav_live_...', required: true },
          { name: 'Accept', value: 'application/json', required: true },
        ],
        parameters: [],
        requestExample: {},
        responseExample: {
          success: true,
          data: {
            whatsapp_status: 'connected',
            phone_number: '+201012345678',
            connected_at: '2026-09-08T08:00:00Z',
          },
        },
        errors: [
          { status: 401, message_ar: 'مفتاح API غير صالح', message_en: 'Invalid API key' },
        ],
      },
      {
        id: 'check-queue-status',
        title_ar: 'فحص حالة طابور الرسائل (Queue Status)',
        title_en: 'Check Message Queue Status',
        method: 'GET',
        url: '/public/whatsapp/queue-status',
        badge_ar: 'طوابير',
        badge_en: 'Queue',
        description_ar: 'استعلم عن عدد الرسائل في قائمة الانتظار، الرسائل المجدولة، والرسائل المعالجة.',
        description_en: 'Retrieve real-time metrics on pending, processing, and scheduled messages in your queue.',
        headers: [
          { name: 'X-API-Key', value: 'wav_live_...', required: true },
          { name: 'Accept', value: 'application/json', required: true },
        ],
        parameters: [],
        requestExample: {},
        responseExample: {
          success: true,
          data: {
            pending: 3,
            processing: 1,
            scheduled: 12,
            failed_today: 0,
          },
        },
        errors: [
          { status: 401, message_ar: 'مفتاح API غير صالح', message_en: 'Invalid API key' },
        ],
      },
    ];

    // Messaging Endpoints
    const messagingEndpoints = [
      {
        id: 'send-text-message',
        title_ar: 'إرسال رسالة نصية (Text Message)',
        title_en: 'Send Text Message',
        method: 'POST',
        url: '/public/whatsapp/send-message',
        badge_ar: 'الأساسي والأكثر استخداماً',
        badge_en: 'Primary Endpoint',
        description_ar: 'إرسال رسالة نصية عادية مع دعم الإيموجي والتنسيقات والروابط والجدولة.',
        description_en: 'Send standard text messages with emoji support, formatting, dynamic link tracking, and scheduling capabilities.',
        headers: [
          { name: 'X-API-Key', value: 'wav_live_...', required: true },
          { name: 'Content-Type', value: 'application/json', required: true },
        ],
        parameters: [
          { name: 'to', type: 'string', required: true, description_ar: 'رقم هاتف المستلم مسبوقاً بكود الدولة (مثال: +201012345678)', description_en: 'Recipient phone number with country code (e.g. +201012345678)', placeholder: '+201012345678' },
          { name: 'message', type: 'string', required: true, description_ar: 'نص الرسالة المراد إرسالها للعميل', description_en: 'The text content to be delivered', placeholder: 'Hello from Wavixa!' },
          { name: 'device', type: 'string', required: false, description_ar: 'معرف الجلسة session_token أو رقم الهاتف لاختيار جهاز معين (اختياري)', description_en: 'Optional session_token or sender phone number to route through a specific device' },
          { name: 'scheduled_at', type: 'string', required: false, description_ar: 'تاريخ الإرسال المستقبلي بتنسيق ISO 8601 (اختياري)', description_en: 'Future UTC timestamp in ISO 8601 format' },
          { name: 'delay_minutes', type: 'integer', required: false, description_ar: 'تأخير الإرسال بعد عدد دقائق معين (اختياري)', description_en: 'Delay sending by N minutes' },
        ],
        requestExample: {
          to: '+201012345678',
          message: 'Hello! Your order #95412 has been confirmed successfully 🚀',
        },
        responseExample: {
          success: true,
          message: 'Message queued successfully',
          data: {
            message_id: 8492,
            to: '+201012345678',
            status: 'queued',
            queued_at: '2026-09-08T12:00:00Z',
          },
        },
        errors: [
          { status: 400, message_ar: 'WhatsApp غير متصل حالياً', message_en: 'WhatsApp device is not connected' },
          { status: 403, message_ar: 'نفاد رصيد الرسائل المتبقي في باقتك', message_en: 'No remaining message credits' },
          { status: 422, message_ar: 'رقم الهاتف أو نص الرسالة غير مدخل', message_en: 'Missing required parameters (to or message)' },
        ],
        note_ar: 'يمكنك إرسال رسائل متعددة وسيقوم النظام بتنظيمها في طابور الإرسال بسرعة وتقليل مخاطر التقييد. لا يُضمن عدم الحظر، فيجب استخدام النظام وفق سياسات WhatsApp.',
        note_en: 'Messages are automatically queued and throttled to reduce restriction risk. This does not guarantee that your number will not be banned; use in accordance with WhatsApp policies.',
      },
      {
        id: 'get-message-status',
        title_ar: 'الاستعلام عن حالة إرسال الرسالة (Message Status by ID)',
        title_en: 'Get Message Delivery Status by ID',
        method: 'GET',
        url: '/public/whatsapp/message-status/8492',
        badge_ar: 'استعلام وتتبع',
        badge_en: 'Tracking & Status',
        description_ar: 'استعلم برمجياً عن حالة أي رسالة أرسلتها بواسطة message_id لمعرفة هل هي queued، sent، delivered، أو failed.',
        description_en: 'Query the delivery status of any sent message using its message_id to determine if it is queued, sent, delivered, or failed.',
        headers: [
          { name: 'X-API-Key', value: 'wav_live_...', required: true },
          { name: 'Accept', value: 'application/json', required: true },
        ],
        parameters: [],
        requestExample: {},
        responseExample: {
          success: true,
          data: {
            message_id: 8492,
            status: 'sent',
            to: '+201012345678',
            from: '+201024520482',
            message_type: 'text',
            message: 'Hello! Your order #95412 has been confirmed successfully 🚀',
            error_message: null,
            sent_at: '2026-09-08T00:30:02Z',
            delivered_at: '2026-09-08T00:30:05Z',
            failed_at: null,
          },
        },
        errors: [
          { status: 404, message_ar: 'الرسالة غير موجودة أو تخص حساباً آخر', message_en: 'Message not found' },
        ],
        note_ar: 'تتغير الحالة إلى sent خلال 1 إلى 3 ثوانٍ من الإرسال. كما يمكنك أيضاً استخدام Webhooks لاستقبال إشعار فوري message.sent عند الإرسال.',
        note_en: 'Status updates to sent within 1 to 3 seconds. Alternatively, configure webhooks to receive instant callbacks on message.sent.',
      },
      {
        id: 'send-image-message',
        title_ar: 'إرسال صورة (Image Message)',
        title_en: 'Send Image Message',
        method: 'POST',
        url: '/public/whatsapp/send-image',
        badge_ar: 'وسائط',
        badge_en: 'Media',
        description_ar: 'إرسال صورة (PNG, JPG, WEBP) عبر رابط URL مباشر مع نص توضيحي (Caption).',
        description_en: 'Send images (PNG, JPG, WEBP) via a direct public URL with an optional caption.',
        headers: [
          { name: 'X-API-Key', value: 'wav_live_...', required: true },
          { name: 'Content-Type', value: 'application/json', required: true },
        ],
        parameters: [
          { name: 'to', type: 'string', required: true, description_ar: 'رقم هاتف المستلم بكود الدولة', description_en: 'Recipient phone number with country code', placeholder: '+201012345678' },
          { name: 'image_url', type: 'string', required: true, description_ar: 'رابط مباشر للصورة المراد إرسالها', description_en: 'Direct publicly accessible image URL', placeholder: 'https://example.com/invoice.jpg' },
          { name: 'caption', type: 'string', required: false, description_ar: 'النص التوضيحي المصاحب للصورة', description_en: 'Optional caption accompanying the image' },
          { name: 'device', type: 'string', required: false, description_ar: 'معرف الجهاز المراد الإرسال منه (اختياري)', description_en: 'Optional device identifier' },
        ],
        requestExample: {
          to: '+201012345678',
          image_url: 'https://images.unsplash.com/photo-1579546929518-9e396f3cc809?w=800',
          caption: 'Check out our special deal for today! 🎉',
        },
        responseExample: {
          success: true,
          message: 'Image queued successfully',
          data: {
            message_id: 8493,
            message_type: 'image',
            status: 'queued',
          },
        },
        errors: [
          { status: 400, message_ar: 'فشل تحميل الصورة أو الواتساب غير متصل', message_en: 'Failed to download image or WhatsApp disconnected' },
        ],
      },
      {
        id: 'send-file-message',
        title_ar: 'إرسال ملف ومستند (PDF / Document)',
        title_en: 'Send Document (PDF / Office / ZIP)',
        method: 'POST',
        url: '/public/whatsapp/send-file',
        badge_ar: 'مستندات',
        badge_en: 'Documents',
        description_ar: 'إرسال ملفات PDF، فواتير إلكترونية، مستندات Word، Excel، أو ZIP للعميل مباشرة.',
        description_en: 'Send PDF invoices, contracts, Word documents, Excel spreadsheets, or ZIP archives directly.',
        headers: [
          { name: 'X-API-Key', value: 'wav_live_...', required: true },
          { name: 'Content-Type', value: 'application/json', required: true },
        ],
        parameters: [
          { name: 'to', type: 'string', required: true, description_ar: 'رقم هاتف المستلم بكود الدولة', description_en: 'Recipient phone number', placeholder: '+201012345678' },
          { name: 'file_url', type: 'string', required: true, description_ar: 'رابط مباشر للملف المرفق', description_en: 'Direct URL to download the document', placeholder: 'https://example.com/invoice.pdf' },
          { name: 'filename', type: 'string', required: false, description_ar: 'اسم الملف الذي سيظهر للعميل (مثال: invoice_9012.pdf)', description_en: 'Filename shown on WhatsApp (e.g. invoice_9012.pdf)' },
          { name: 'caption', type: 'string', required: false, description_ar: 'نص إضافي مع الملف', description_en: 'Optional caption text' },
        ],
        requestExample: {
          to: '+201012345678',
          file_url: 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
          filename: 'Invoice_9012.pdf',
          caption: 'Please find your invoice attached.',
        },
        responseExample: {
          success: true,
          message: 'Document queued successfully',
          data: {
            message_id: 8494,
            message_type: 'document',
            status: 'queued',
          },
        },
        errors: [
          { status: 400, message_ar: 'الملف غير صالح أو تعذر الوصول للرابط', message_en: 'Invalid document or inaccessible URL' },
        ],
      },
      {
        id: 'send-video-message',
        title_ar: 'إرسال مقطع فيديو (Video Message)',
        title_en: 'Send Video Message',
        method: 'POST',
        url: '/public/whatsapp/send-video',
        badge_ar: 'فيديو',
        badge_en: 'Video',
        description_ar: 'إرسال مقطع فيديو بصيغة MP4 مع كابشن توضيحي.',
        description_en: 'Send MP4 video clips with optional caption text.',
        headers: [
          { name: 'X-API-Key', value: 'wav_live_...', required: true },
          { name: 'Content-Type', value: 'application/json', required: true },
        ],
        parameters: [
          { name: 'to', type: 'string', required: true, description_ar: 'رقم هاتف المستلم', description_en: 'Recipient phone number', placeholder: '+201012345678' },
          { name: 'video_url', type: 'string', required: true, description_ar: 'رابط ملف الفيديو المباشر (MP4)', description_en: 'Direct MP4 video URL', placeholder: 'https://example.com/demo.mp4' },
          { name: 'caption', type: 'string', required: false, description_ar: 'النص التوضيحي للفيديو', description_en: 'Optional video caption' },
        ],
        requestExample: {
          to: '+201012345678',
          video_url: 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
          caption: 'Watch the quick product tutorial 🎥',
        },
        responseExample: {
          success: true,
          data: { message_id: 8495, message_type: 'video', status: 'queued' },
        },
        errors: [{ status: 400, message_ar: 'فشل إرسال الفيديو', message_en: 'Failed to send video' }],
      },
      {
        id: 'send-voice-message',
        title_ar: 'إرسال رسالة صوتية (Voice / Audio)',
        title_en: 'Send Voice Note (PTT / Audio)',
        method: 'POST',
        url: '/public/whatsapp/send-voice',
        badge_ar: 'صوت',
        badge_en: 'Audio',
        description_ar: 'إرسال ملف صوتي يتم تشغيله كرسالة صوتية (PTT) على الواتساب.',
        description_en: 'Send an audio file that plays as a native Push-to-Talk (PTT) voice note.',
        headers: [
          { name: 'X-API-Key', value: 'wav_live_...', required: true },
          { name: 'Content-Type', value: 'application/json', required: true },
        ],
        parameters: [
          { name: 'to', type: 'string', required: true, description_ar: 'رقم هاتف المستلم', description_en: 'Recipient phone number', placeholder: '+201012345678' },
          { name: 'audio_url', type: 'string', required: true, description_ar: 'رابط ملف الصوت (MP3, OGG, WAV)', description_en: 'Direct audio URL (MP3, OGG, WAV)', placeholder: 'https://example.com/greeting.mp3' },
        ],
        requestExample: {
          to: '+201012345678',
          audio_url: 'https://example.com/audio/greeting.mp3',
        },
        responseExample: {
          success: true,
          data: { message_id: 8496, message_type: 'voice', status: 'queued' },
        },
        errors: [{ status: 400, message_ar: 'فشل إرسال الصوت', message_en: 'Failed to send audio' }],
      },
      {
        id: 'send-location-message',
        title_ar: 'إرسال موقع جغرافي (Location Message)',
        title_en: 'Send GPS Location Pin',
        method: 'POST',
        url: '/public/whatsapp/send-location',
        badge_ar: 'موقع',
        badge_en: 'Location',
        description_ar: 'إرسال موقع على خريطة GPS يفتح مباشرة في Google Maps أو WhatsApp.',
        description_en: 'Send an interactive map location pin that opens directly in Google Maps.',
        headers: [
          { name: 'X-API-Key', value: 'wav_live_...', required: true },
          { name: 'Content-Type', value: 'application/json', required: true },
        ],
        parameters: [
          { name: 'to', type: 'string', required: true, description_ar: 'رقم هاتف المستلم', description_en: 'Recipient phone number', placeholder: '+201012345678' },
          { name: 'latitude', type: 'number', required: true, description_ar: 'خط العرض (مثال: 30.0444)', description_en: 'Latitude coordinate (e.g. 30.0444)', placeholder: '30.0444' },
          { name: 'longitude', type: 'number', required: true, description_ar: 'خط الطول (مثال: 31.2357)', description_en: 'Longitude coordinate (e.g. 31.2357)', placeholder: '31.2357' },
          { name: 'name', type: 'string', required: false, description_ar: 'اسم المكان (مثال: المقر الرئيسي لشركة Wavixa)', description_en: 'Location venue name' },
          { name: 'address', type: 'string', required: false, description_ar: 'العنوان التفصيلي', description_en: 'Detailed address string' },
        ],
        requestExample: {
          to: '+201012345678',
          latitude: 30.0444,
          longitude: 31.2357,
          name: 'Wavixa HQ',
          address: 'Downtown, Cairo, Egypt',
        },
        responseExample: {
          success: true,
          data: { message_id: 8497, message_type: 'location', status: 'queued' },
        },
        errors: [{ status: 400, message_ar: 'إحداثيات غير صحيحة', message_en: 'Invalid coordinates' }],
      },
      {
        id: 'send-contact-message',
        title_ar: 'إرسال جهة اتصال (Contact Card)',
        title_en: 'Send Contact Card (vCard)',
        method: 'POST',
        url: '/public/whatsapp/send-contact',
        badge_ar: 'جهات اتصال',
        badge_en: 'Contacts',
        description_ar: 'إرسال بطاقة جهة اتصال يمكن للعميل حفظها في هاتفه بضغطة واحدة.',
        description_en: 'Send contact cards that recipients can save to their address book with one tap.',
        headers: [
          { name: 'X-API-Key', value: 'wav_live_...', required: true },
          { name: 'Content-Type', value: 'application/json', required: true },
        ],
        parameters: [
          { name: 'to', type: 'string', required: true, description_ar: 'رقم هاتف المستلم', description_en: 'Recipient phone number', placeholder: '+201012345678' },
          { name: 'contact_name', type: 'string', required: true, description_ar: 'اسم جهة الاتصال', description_en: 'Contact display name' },
          { name: 'contact_phone', type: 'string', required: true, description_ar: 'رقم الهاتف لجهة الاتصال', description_en: 'Contact phone number' },
        ],
        requestExample: {
          to: '+201012345678',
          contact_name: 'Wavixa Support Team',
          contact_phone: '+201011679726',
        },
        responseExample: {
          success: true,
          data: { message_id: 8498, message_type: 'contact', status: 'queued' },
        },
        errors: [{ status: 400, message_ar: 'فشل إرسال جهة الاتصال', message_en: 'Failed to send contact card' }],
      },
    ];

    // OTP Endpoints
    const otpEndpoints = [
      {
        id: 'send-otp-code',
        title_ar: 'توليد وإرسال رمز التحقق (Send OTP)',
        title_en: 'Generate & Send OTP Verification Code',
        method: 'POST',
        url: '/public/whatsapp/send-otp',
        badge_ar: 'أمان وتحقق',
        badge_en: 'Security & 2FA',
        description_ar: 'توليد كود OTP سري وإرساله للمستخدم مع تحديد مدة الصلاحية بالدقائق وتخصيص نص الرسالة.',
        description_en: 'Generate a secure OTP, customize the message template, and set TTL expiry in minutes.',
        headers: [
          { name: 'X-API-Key', value: 'wav_live_...', required: true },
          { name: 'Content-Type', value: 'application/json', required: true },
        ],
        parameters: [
          { name: 'to', type: 'string', required: true, description_ar: 'رقم هاتف المستلم بكود الدولة', description_en: 'Recipient phone number with country code', placeholder: '+201012345678' },
          { name: 'code', type: 'string', required: false, description_ar: 'كود مخصص ترغب بإرساله (إذا ترك فارغاً سيتم توليد كود عشوائي تلقائياً من 6 أرقام)', description_en: 'Custom code (random 6-digit generated automatically if omitted)' },
          { name: 'expiry_minutes', type: 'integer', required: false, description_ar: 'مدة صلاحية الكود بالدقائق (الافتراضي: 10 دقائق)', description_en: 'Code validity lifetime in minutes (default: 10)' },
          { name: 'template', type: 'string', required: false, description_ar: 'قالب الرسالة مع استخدام المتغير {code} (مثال: كود تفعيل حسابك هو {code})', description_en: 'Custom message template with {code} placeholder' },
        ],
        requestExample: {
          to: '+201012345678',
          expiry_minutes: 5,
          template: 'Your verification code is: {code}. Valid for 5 minutes. Do not share it.',
        },
        responseExample: {
          success: true,
          message: 'OTP sent successfully',
          data: {
            to: '+201012345678',
            expires_at: '2026-09-08T12:05:00Z',
          },
        },
        errors: [
          { status: 400, message_ar: 'فشل إرسال OTP أو الواتساب غير متصل', message_en: 'Failed to send OTP or WhatsApp offline' },
        ],
      },
      {
        id: 'verify-otp-code',
        title_ar: 'التحقق من صحة كود OTP (Verify OTP)',
        title_en: 'Verify OTP Code',
        method: 'POST',
        url: '/public/whatsapp/verify-otp',
        badge_ar: 'تحقق',
        badge_en: 'Verification',
        description_ar: 'فحص الكود الذي أدخله المستخدم والتأكد من مطابقته وصلاحيته الزمنية.',
        description_en: 'Validate the code entered by the user against the database and check its validity window.',
        headers: [
          { name: 'X-API-Key', value: 'wav_live_...', required: true },
          { name: 'Content-Type', value: 'application/json', required: true },
        ],
        parameters: [
          { name: 'to', type: 'string', required: true, description_ar: 'رقم الهاتف المراد التحقق منه', description_en: 'Phone number to verify', placeholder: '+201012345678' },
          { name: 'code', type: 'string', required: true, description_ar: 'الكود المكون من 6 أرقام الذي أدخله المستخدم', description_en: 'The code provided by the user', placeholder: '482910' },
        ],
        requestExample: {
          to: '+201012345678',
          code: '482910',
        },
        responseExample: {
          success: true,
          message: 'OTP verified successfully',
          data: {
            verified: true,
            verified_at: '2026-09-08T12:02:15Z',
          },
        },
        errors: [
          { status: 400, message_ar: 'كود غير صحيح أو منتهي الصلاحية', message_en: 'Invalid or expired OTP code' },
        ],
      },
    ];

    // Remote Pairing Endpoints
    const pairingEndpoints = [
      {
        id: 'create-pairing-session',
        title_ar: 'إنشاء جلسة ربط عن بُعد (Create Remote Pairing Session)',
        title_en: 'Create Remote Pairing Session',
        method: 'POST',
        url: '/public/whatsapp/pairing-sessions',
        badge_ar: 'SaaS & Integrations',
        badge_en: 'SaaS & Integrations',
        description_ar: 'إنشاء جلسة جديدة لتوليد QR Code يمكن عرضه في موقع خارجي ليقوم العميل بمسحه من هاتفه.',
        description_en: 'Initiate a new QR pairing session to render WhatsApp QR codes inside your external platform.',
        headers: [
          { name: 'X-API-Key', value: 'wav_live_...', required: true },
          { name: 'Content-Type', value: 'application/json', required: true },
        ],
        parameters: [
          { name: 'device_name', type: 'string', required: false, description_ar: 'اسم وصفي للجهاز (مثال: جهاز المتجر)', description_en: 'Friendly label for the device', placeholder: 'My Store WhatsApp' },
        ],
        requestExample: {
          device_name: 'Store WhatsApp Device',
        },
        responseExample: {
          success: true,
          message: 'Pairing session initialized',
          data: {
            session_id: 104,
            status: 'pending',
            qr: '2@XJkK3a...==,Wv9K...',
            pairing_code: '4892-1049',
            status_url: 'https://wavixa.net/api/public/whatsapp/pairing-sessions/104',
          },
        },
        errors: [
          { status: 403, message_ar: 'مفتاح API غير مفعل عليه Remote Pairing أو تم بلوغ الحد الأقصى للأجهزة', message_en: 'Remote pairing not permitted for this API key or session limit reached' },
        ],
      },
      {
        id: 'get-pairing-status',
        title_ar: 'جلب حالة جلسة الربط والـ QR Code',
        title_en: 'Poll Pairing Status & QR Code',
        method: 'GET',
        url: '/public/whatsapp/pairing-sessions/{id}',
        badge_ar: 'Polling',
        badge_en: 'Polling',
        description_ar: 'استعلام دوري (Polling) لجلب كود الـ QR المتجدد أو معرفة هل قام العميل بمسح الكود وتوصيل هاتفه.',
        description_en: 'Poll the status to fetch updated QR codes or verify when the user completes the connection.',
        headers: [
          { name: 'X-API-Key', value: 'wav_live_...', required: true },
          { name: 'Accept', value: 'application/json', required: true },
        ],
        parameters: [],
        requestExample: {},
        responseExample: {
          success: true,
          data: {
            session_id: 104,
            status: 'connected',
            phone_number: '+201011679726',
            session_token: 'sess_live_99fa821cba7',
            connected_at: '2026-09-08T12:04:30Z',
          },
        },
        errors: [
          { status: 404, message_ar: 'الجلسة غير موجودة أو انتهت', message_en: 'Pairing session not found or expired' },
        ],
      },
      {
        id: 'delete-pairing-session',
        title_ar: 'حذف أو فصل جلسة الربط (Disconnect Pairing)',
        title_en: 'Disconnect & Delete Pairing Session',
        method: 'DELETE',
        url: '/public/whatsapp/pairing-sessions/{id}',
        badge_ar: 'إدارة',
        badge_en: 'Manage',
        description_ar: 'فصل جلسة الواتساب المتصلة وحذف بياناتها بأمان.',
        description_en: 'Gracefully disconnect and delete the paired WhatsApp session.',
        headers: [
          { name: 'X-API-Key', value: 'wav_live_...', required: true },
          { name: 'Accept', value: 'application/json', required: true },
        ],
        parameters: [],
        requestExample: {},
        responseExample: {
          success: true,
          message: 'Pairing session deleted successfully',
        },
        errors: [
          { status: 404, message_ar: 'الجلسة غير موجودة', message_en: 'Session not found' },
        ],
      },
    ];


    onMounted(() => {
      // Auto prefill API key if user has stored one or query param
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('api_key')) {
        apiKey.value = urlParams.get('api_key');
      }
    });

    return {
      logo,
      isRTL,
      isAuthenticated,
      apiKey,
      searchQuery,
      activeSection,
      baseUrl,
      categories,
      statusEndpoints,
      messagingEndpoints,
      otpEndpoints,
      pairingEndpoints,
      scrollTo,
    };
  },
};
</script>

<style scoped>
html {
  scroll-behavior: smooth;
}
</style>
