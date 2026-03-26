<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, Link } from '@inertiajs/vue3'

const betaDismissed = ref(
  typeof localStorage !== 'undefined' && localStorage.getItem('beta-dismissed') === '1'
)
function dismissBeta() {
  betaDismissed.value = true
  localStorage.setItem('beta-dismissed', '1')
}
import Sidebar from '@/Components/Sidebar.vue'
import Topbar from '@/Components/Topbar.vue'
import HelpSidebar from '@/Components/HelpSidebar.vue'
import { useHelp } from '@/lib/useHelp'

const props = defineProps({
  title: String,
  helpPage: {
    type: String,
    default: null,
  },
})

const { showHelp, toggleHelp } = useHelp()
const page = usePage()
const docsBaseUrl = computed(() => page.props.docsBaseUrl)
const locale = computed(() => page.props.locale ?? 'en')
const showDocs = ref(false)
const collapsed = ref(false)
const mobileOpen = ref(false)

const subscription = computed(() => page.props.auth?.subscription ?? null)
const flash = computed(() => page.props.flash || {})
const trialDaysLeft = computed(() => {
  if (subscription.value?.status !== 'trialing' || !subscription.value?.trial_ends_at) return null
  const diff = Math.ceil((new Date(subscription.value.trial_ends_at) - new Date()) / 86400000)
  return Math.max(0, diff)
})
const showTrialBanner = computed(() => trialDaysLeft.value !== null && trialDaysLeft.value <= 7)
const showPastDueBanner = computed(() => subscription.value?.status === 'past_due')
</script>

<template>
  <a
    href="#main-content"
    class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[100] focus:rounded focus:bg-[hsl(var(--background))] focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:ring-2 focus:ring-[hsl(var(--ring))]"
  >
    Skip to content
  </a>
  <Head :title="title" />

  <!-- Early Beta Banner -->
  <div
    v-if="!betaDismissed"
    class="relative z-50 bg-amber-400 text-amber-950 text-sm font-medium"
    role="alert"
  >
    <div class="max-w-full px-6 py-2 flex items-center justify-center gap-3">
      <span class="inline-flex items-center gap-1.5">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        <strong>Early Beta</strong> — Gäld is under active development. Data may be reset without notice.
      </span>
      <a href="https://github.com/gaeld/gaeld-app" target="_blank" rel="noopener" class="underline underline-offset-2 hover:text-amber-900 whitespace-nowrap">Follow on GitHub</a>
      <button @click="dismissBeta" class="ml-2 opacity-60 hover:opacity-100 transition-opacity" aria-label="Dismiss">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
  </div>

  <div class="min-h-screen bg-[hsl(var(--background))]">
    <!-- Trial ending banner -->
    <div
      v-if="showTrialBanner"
      class="relative z-40 bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))] text-sm font-medium"
    >
      <div class="max-w-full px-6 py-2 flex items-center justify-center gap-3">
        <span>{{ t('trial_ends_in', { days: trialDaysLeft }) }}</span>
        <Link href="/billing" class="underline underline-offset-2 font-semibold hover:opacity-80 whitespace-nowrap">{{ t('upgrade_now') }}</Link>
      </div>
    </div>

    <!-- Past-due payment banner -->
    <div
      v-if="showPastDueBanner"
      class="relative z-40 bg-[hsl(var(--destructive))] text-[hsl(var(--destructive-foreground))] text-sm font-medium"
    >
      <div class="max-w-full px-6 py-2 flex items-center justify-center gap-3">
        <span>{{ t('payment_failed_warning') }}</span>
        <Link href="/billing" class="underline underline-offset-2 font-semibold hover:opacity-80 whitespace-nowrap">{{ t('update_payment_method') }}</Link>
      </div>
    </div>

    <Sidebar
      v-model:collapsed="collapsed"
      :mobileOpen="mobileOpen"
      @closeMobile="mobileOpen = false"
    />

    <!-- Main content area (offset by sidebar on desktop) -->
    <div
      :class="[
        'transition-all duration-200',
        collapsed ? 'lg:pl-16' : 'lg:pl-60',
      ]"
    >
      <Topbar :helpPage="helpPage" :docs-url="docsBaseUrl ? `${docsBaseUrl}/docs/${helpPage}` : null" @toggleHelp="toggleHelp" @toggleDocs="showDocs = !showDocs" @toggleMobile="mobileOpen = !mobileOpen">
        <template #heading>
          {{ title }}
        </template>
      </Topbar>

      <main id="main-content" class="p-4 sm:p-6">
        <div v-if="flash.success" class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
          {{ flash.success }}
        </div>
        <div v-if="flash.error" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
          {{ flash.error }}
        </div>
        <slot />
      </main>
    </div>

    <HelpSidebar
      v-if="helpPage && showDocs"
      :page="helpPage"
      :base-url="docsBaseUrl"
      :locale="locale"
      @close="showDocs = false"
    />
  </div>
</template>
