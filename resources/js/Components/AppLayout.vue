<script setup>
import { ref, computed, watch } from 'vue'
import { Head, usePage, Link } from '@inertiajs/vue3'
import { useTranslations } from '@/lib/useTranslations'

const { t: tl } = useTranslations()

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
import ToastContainer from '@/Components/ToastContainer.vue'
import ErrorBoundary from '@/Components/ErrorBoundary.vue'
import OfflineBanner from '@/Components/OfflineBanner.vue'
import Banner from '@/Components/UI/Banner.vue'
import { useHelp } from '@/lib/useHelp'
import { useToast } from '@/lib/useToast'

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

// Convert flash messages to toasts
const { toast } = useToast()
watch(flash, (f) => {
  if (f.success) toast(f.success, 'success')
  if (f.error) toast(f.error, 'error')
  if (f.warning) toast(f.warning, 'warning')
  if (f.info) toast(f.info, 'info')
}, { immediate: true })

const trialDaysLeft = computed(() => {
  if (subscription.value?.status !== 'trialing' || !subscription.value?.trial_ends_at) return null
  const diff = Math.ceil((new Date(subscription.value.trial_ends_at) - new Date()) / 86400000)
  return Math.max(0, diff)
})
const showTrialBanner = computed(() => trialDaysLeft.value !== null && trialDaysLeft.value <= 7)
const showPastDueBanner = computed(() => subscription.value?.status === 'past_due')
const showGracePeriodBanner = computed(() => {
  if (subscription.value?.status !== 'canceled' || !subscription.value?.ends_at) return false
  return new Date(subscription.value.ends_at) > new Date()
})
const showPausedBanner = computed(() => subscription.value?.status === 'paused')
const isSaasAdmin = computed(() => page.props.auth?.is_saas_admin === true)
const systemMessage = computed(() => page.props.systemMessage ?? null)
</script>

<template>
  <a
    href="#main-content"
    class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[100] focus:rounded focus:bg-[hsl(var(--background))] focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:ring-2 focus:ring-[hsl(var(--ring))]"
  >
    {{ tl('skip_to_content') }}
  </a>
  <Head :title="title" />

  <div class="min-h-screen bg-[hsl(var(--background))]">
    <Sidebar
      v-model:collapsed="collapsed"
      :mobileOpen="mobileOpen"
      class="print:hidden"
      @closeMobile="mobileOpen = false"
    />

    <!-- Main content area (offset by sidebar on desktop) -->
    <div
      :class="[
        'transition-all duration-200 print:pl-0',
        collapsed ? 'lg:pl-16' : 'lg:pl-60',
      ]"
    >
      <!-- Offline banner -->
      <OfflineBanner />

      <!-- SaaS Admin Banner -->
      <Banner v-if="isSaasAdmin" color="red" role="alert">
        <span class="inline-flex items-center gap-1.5">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
          {{ tl('saas_admin_warning') }}
        </span>
        <Link href="/saas-admin" class="underline underline-offset-2 hover:text-red-100 whitespace-nowrap">{{ tl('admin_dashboard') }}</Link>
      </Banner>

      <!-- Early Beta Banner -->
      <Banner v-if="!betaDismissed" color="amber" :dismissable="true" @dismiss="dismissBeta">
        <span class="inline-flex items-center gap-1.5">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
          <strong>{{ tl('early_beta') }}</strong> — {{ tl('early_beta_notice') }}
        </span>
        <a href="https://github.com/gaeld/gaeld-app" target="_blank" rel="noopener" class="underline underline-offset-2 hover:text-amber-900 whitespace-nowrap">{{ tl('follow_on_github') }}</a>
      </Banner>

      <!-- Trial ending banner -->
      <Banner v-if="showTrialBanner" color="primary">
        <span>{{ tl('trial_ends_in', { days: trialDaysLeft }) }}</span>
        <Link href="/billing" class="underline underline-offset-2 font-semibold hover:opacity-80 whitespace-nowrap">{{ tl('upgrade_now') }}</Link>
      </Banner>

      <!-- Past-due payment banner -->
      <Banner v-if="showPastDueBanner" color="destructive">
        <span>{{ tl('payment_failed_warning') }}</span>
        <Link href="/billing" class="underline underline-offset-2 font-semibold hover:opacity-80 whitespace-nowrap">{{ tl('update_payment_method') }}</Link>
      </Banner>

      <!-- Canceled (grace period) banner -->
      <Banner v-if="showGracePeriodBanner" color="amber" :dismissable="true">
        <span>{{ tl('subscription_grace_period', { date: subscription.ends_at }) }}</span>
        <Link href="/billing" class="underline underline-offset-2 font-semibold hover:text-amber-900 whitespace-nowrap">{{ tl('resubscribe') }}</Link>
      </Banner>

      <!-- Paused subscription banner -->
      <Banner v-if="showPausedBanner" color="blue">
        <span>{{ tl('subscription_paused_banner') }}</span>
        <Link href="/billing" class="underline underline-offset-2 font-semibold hover:opacity-80 whitespace-nowrap">{{ tl('resume_subscription') }}</Link>
      </Banner>

      <!-- System message banner (set by SaaS admin) -->
      <Banner v-if="systemMessage" color="blue" role="status">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>{{ systemMessage }}</span>
      </Banner>

      <Topbar :helpPage="helpPage" :docs-url="docsBaseUrl" class="print:hidden" @toggleHelp="toggleHelp" @toggleDocs="showDocs = !showDocs" @toggleMobile="mobileOpen = !mobileOpen">
        <template #heading>
          {{ title }}
        </template>
      </Topbar>

      <main id="main-content" class="p-4 sm:p-6">
        <ErrorBoundary>
          <slot />
        </ErrorBoundary>
      </main>
    </div>

    <ToastContainer />

    <HelpSidebar
      v-if="showDocs"
      :page="helpPage"
      :base-url="docsBaseUrl"
      :locale="locale"
      @close="showDocs = false"
    />
  </div>
</template>
