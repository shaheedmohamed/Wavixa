<template>
  <div class="min-h-screen bg-[#070b14] text-slate-100 font-sans overflow-x-hidden">
    <header class="border-b border-slate-800/80 bg-[#070b14]/95 backdrop-blur-md sticky top-0 z-50">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
        <router-link to="/" class="flex items-center gap-2.5 group">
          <div class="w-8 h-8 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-center p-1">
            <img :src="logo" alt="Wavixa" class="h-6 w-auto object-contain" />
          </div>
          <span class="text-lg font-black tracking-tight text-white">WAVIXA</span>
        </router-link>
        <router-link to="/" class="text-xs font-bold text-slate-400 hover:text-white transition-colors">
          &larr; Back to home
        </router-link>
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 sm:px-6 py-10 sm:py-14">
      <h1 class="text-3xl sm:text-4xl font-black text-white mb-2">Privacy Policy</h1>
      <p class="text-sm text-slate-400 mb-10">Last updated: October 20, 2026</p>

      <div class="space-y-8 text-sm sm:text-[15px] leading-relaxed text-slate-300">
        <section>
          <h2 class="text-lg font-bold text-white mb-3">1. Introduction</h2>
          <p>
            Wavixa ("we", "our", "the platform") is a software-as-a-service platform that lets
            businesses connect their WhatsApp accounts — via QR pairing or the official Meta
            WhatsApp Business Platform (Cloud API, including WhatsApp Business app
            "coexistence" onboarding) — and manage customer conversations, campaigns,
            templates, and automations from a single dashboard. This policy explains what
            data we collect, how we use and disclose it, and how you can manage or delete it.
          </p>
        </section>

        <section>
          <h2 class="text-lg font-bold text-white mb-3">2. Information We Collect</h2>
          <ul class="list-disc pr-5 space-y-2">
            <li>
              <span class="font-semibold text-slate-100">Account information:</span> name, email
              address, password (hashed), business name, and subscription/billing details you
              provide when registering.
            </li>
            <li>
              <span class="font-semibold text-slate-100">Meta / WhatsApp integration data:</span>
              when you connect through Meta Embedded Signup, we receive and store your Meta
              access token, WhatsApp Business Account ID (WABA ID), phone number ID, display
              phone number, verified business name, and granted permission scopes.
            </li>
            <li>
              <span class="font-semibold text-slate-100">Messaging data:</span> messages,
              delivery statuses, media, contacts, and template content that flow through your
              connected WhatsApp numbers — received via Meta webhooks or the QR engine — so we
              can display your inbox, sync history, and report delivery.
            </li>
            <li>
              <span class="font-semibold text-slate-100">Usage and technical data:</span> log
              data, IP address, browser type, pages visited, and product analytics used to
              operate and secure the service.
            </li>
          </ul>
        </section>

        <section>
          <h2 class="text-lg font-bold text-white mb-3">3. How We Use Your Information</h2>
          <ul class="list-disc pr-5 space-y-2">
            <li>Provide, operate, and maintain the Wavixa platform and your connected channels.</li>
            <li>Send and receive WhatsApp messages on your behalf through the official Meta APIs.</li>
            <li>Synchronize conversations and message history (including WhatsApp Business app coexistence data such as app-state sync and message echoes) into your dashboard.</li>
            <li>Deliver webhooks, API responses, and notifications you configure.</li>
            <li>Monitor reliability, prevent abuse, and provide customer support.</li>
            <li>Process subscription payments and comply with legal obligations.</li>
          </ul>
        </section>

        <section>
          <h2 class="text-lg font-bold text-white mb-3">4. How We Share Information</h2>
          <p class="mb-3">We do not sell your data. We share information only with:</p>
          <ul class="list-disc pr-5 space-y-2">
            <li>
              <span class="font-semibold text-slate-100">Meta Platforms, Inc.:</span> to
              authenticate your account, register phone numbers, send/receive messages, and
              receive webhook events for the WhatsApp Business Platform, subject to Meta's
              own terms and privacy policy.
            </li>
            <li>
              <span class="font-semibold text-slate-100">Service providers:</span> hosting,
              payment processing, and infrastructure providers bound by confidentiality
              obligations, only as needed to operate the service.
            </li>
            <li>
              <span class="font-semibold text-slate-100">Legal requirements:</span> when
              required by law, regulation, or valid legal process.
            </li>
          </ul>
          <p class="mt-3">
            Each customer's Meta credentials and messaging data are isolated to that
            customer's account. No other Wavixa customer can access your WhatsApp data.
          </p>
        </section>

        <section>
          <h2 class="text-lg font-bold text-white mb-3">5. Data Retention</h2>
          <p>
            We retain account, integration, and messaging data for as long as your account is
            active or as needed to provide the service. Access tokens are stored encrypted/at
            rest on our servers and are used only to act on your behalf. When you disconnect a
            WhatsApp integration, we mark the connection disconnected and stop processing its
            webhooks; you may request full deletion at any time (see Section 7).
          </p>
        </section>

        <section>
          <h2 class="text-lg font-bold text-white mb-3">6. Security</h2>
          <p>
            We use HTTPS/TLS for all traffic, store secrets and access tokens server-side
            only (never in the browser or client code), restrict access to production systems,
            and keep audit logs of administrative actions. No method of transmission or
            storage is 100% secure; if you suspect unauthorized access, contact us
            immediately.
          </p>
        </section>

        <section>
          <h2 class="text-lg font-bold text-white mb-3">7. Your Rights &amp; Data Deletion</h2>
          <p class="mb-3">
            You may access, correct, export, or delete your data at any time:
          </p>
          <ul class="list-disc pr-5 space-y-2">
            <li>Disconnect a WhatsApp number from the dashboard (Devices page), which revokes our use of that connection.</li>
            <li>Request a copy or deletion of your account and all associated data — including stored Meta tokens, WABA IDs, messages, and contacts — by emailing <a href="mailto:support@wavixa.net" class="text-emerald-400 hover:text-emerald-300 font-semibold">support@wavixa.net</a> from your registered email address.</li>
            <li>We process verified deletion requests within 30 days and confirm completion by email.</li>
          </ul>
        </section>

        <section>
          <h2 class="text-lg font-bold text-white mb-3">8. Cookies &amp; Analytics</h2>
          <p>
            We use cookies and similar technologies for authentication sessions, preferences,
            and product analytics (including the Meta Pixel on our marketing pages). You can
            control cookies through your browser settings; disabling them may affect sign-in.
          </p>
        </section>

        <section>
          <h2 class="text-lg font-bold text-white mb-3">9. Children's Privacy</h2>
          <p>
            Wavixa is a business tool and is not directed at children under 18. We do not
            knowingly collect personal data from children.
          </p>
        </section>

        <section>
          <h2 class="text-lg font-bold text-white mb-3">10. Changes to This Policy</h2>
          <p>
            We may update this policy from time to time. Material changes will be posted on
            this page with an updated "Last updated" date and, where appropriate, notified to
            registered users by email.
          </p>
        </section>

        <section>
          <h2 class="text-lg font-bold text-white mb-3">11. Contact Us</h2>
          <p>
            For privacy questions, data access, or deletion requests, contact us at
            <a href="mailto:support@wavixa.net" class="text-emerald-400 hover:text-emerald-300 font-semibold">support@wavixa.net</a>
            or through the support channel inside your Wavixa dashboard.
          </p>
        </section>
      </div>
    </main>

    <footer class="border-t border-slate-800/60 mt-6">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 py-6 flex items-center justify-between text-xs text-slate-500">
        <span>&copy; {{ new Date().getFullYear() }} Wavixa. All rights reserved.</span>
        <router-link to="/" class="hover:text-slate-300 transition-colors">wavixa.net</router-link>
      </div>
    </footer>
  </div>
</template>

<script setup>
import logo from '@/assets/logo.png';
</script>
