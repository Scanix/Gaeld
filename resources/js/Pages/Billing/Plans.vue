<script setup>
import { computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { CheckCircle2, Zap, AlertCircle, CreditCard, FileText, Settings, Download, ExternalLink } from 'lucide-vue-next'

const { t } = useTranslations()
const { formatCurrency } = useFormatters()
const page = usePage()

const props = defineProps({
  plans: { type: Array, default: () => [] },
  currentSubscription: { type: Object, default: null },
  invoices: { type: Array, default: () => [] },
})

// Show success banner when returning from Stripe checkout
const checkoutResult = computed(() => {
  const [, query = ''] = (page.url || '').split('?')
  const params = new URLSearchParams(query)
  return params.get('checkout') // 'success' | 'canceled' | null
})

const hasPlans = computed(() => props.plans.length > 0)

const isTrialingWithoutStripe = computed(() =>
  props.currentSubscription?.status === 'trialing' && !props.currentSubscription?.has_stripe
)

const invoiceQuota = computed(() => page.props.auth?.invoice_quota ?? { invoices_this_month: 0, invoice_monthly_limit: -1 })
const invoiceQuotaPercent = computed(() => {
  const { invoices_this_month, invoice_monthly_limit } = invoiceQuota.value
  if (invoice_monthly_limit === -1) return null
  return Math.min(100, Math.round((invoices_this_month / invoice_monthly_limit) * 100))
})

const statusBadgeClass = {
  active: 'text-[hsl(var(--primary))] bg-[hsl(var(--accent))]',
  trialing: 'text-blue-700 bg-blue-50 dark:text-blue-400 dark:bg-blue-950/50',
  past_due: 'text-[hsl(var(--destructive))] bg-[hsl(var(--destructive)/0.1)]',
  canceled: 'text-[hsl(var(--muted-foreground))] bg-[hsl(var(--muted))]',
  paused: 'text-yellow-700 bg-yellow-50 dark:text-yellow-400 dark:bg-yellow-950/50',
}

function checkout(planId) {
  router.post(`/billing/checkout/${planId}`)
}

function checkoutCurrentPlan() {
  const currentPlan = props.plans.find(p => p.slug === props.currentSubscription?.plan_slug)
  if (currentPlan) {
    checkout(currentPlan.id)
  }
}

function openPortal() {
  router.post('/billing/portal')
}
</script>

<template>
  <AppLayout :title="t('billing')">
    <div class="max-w-3xl mx-auto space-y-6">

      <!-- Checkout success banner -->
      <div
        v-if="checkoutResult === 'success'"
        class="flex items-center gap-3 rounded-lg border border-[hsl(var(--primary)/0.3)] bg-[hsl(var(--accent))] p-4 text-sm text-[hsl(var(--primary))]"
      >
        <CheckCircle2 class="h-4 w-4 shrink-0" />
        {{ t('checkout_success') }}
      </div>

      <!-- Checkout canceled banner -->
      <div
        v-if="checkoutResult === 'canceled'"
        class="flex items-center gap-3 rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--muted))] p-4 text-sm text-[hsl(var(--muted-foreground))]"
      >
        {{ t('checkout_canceled') }}
      </div>

      <!-- Past due warning -->
      <div
        v-if="currentSubscription?.status === 'past_due'"
        class="flex items-center gap-3 rounded-lg border border-[hsl(var(--destructive)/0.3)] bg-[hsl(var(--destructive)/0.08)] p-4 text-sm text-[hsl(var(--destructive))]"
      >
        <AlertCircle class="h-4 w-4 shrink-0" />
        <span>{{ t('payment_failed_warning') }}</span>
        <button class="ml-auto underline font-medium" @click="openPortal">{{ t('update_payment_method') }}</button>
      </div>

      <!-- Trial without payment method — prompt to complete setup -->
      <div
        v-if="isTrialingWithoutStripe"
        class="flex items-center gap-3 rounded-lg border border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/50 p-4 text-sm text-blue-700 dark:text-blue-400"
      >
        <CreditCard class="h-4 w-4 shrink-0" />
        <span>{{ t('trial_complete_payment_hint') }}</span>
        <button
          class="ml-auto underline font-medium whitespace-nowrap"
          @click="checkoutCurrentPlan"
        >
          {{ t('add_payment_method') }}
        </button>
      </div>

      <!-- ══ MY SUBSCRIPTION ══ -->
      <Card v-if="currentSubscription">
        <CardHeader>
          <div class="flex items-center justify-between">
            <CardTitle>{{ t('my_subscription') }}</CardTitle>
            <span
              :class="statusBadgeClass[currentSubscription.status]"
              class="text-xs font-medium px-2.5 py-1 rounded-full"
            >
              {{ t(`subscription_status_${currentSubscription.status}`) }}
            </span>
          </div>
        </CardHeader>
        <CardContent class="space-y-5">

          <!-- Subscription meta -->
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
            <div>
              <p class="text-[hsl(var(--muted-foreground))]">{{ t('plan') }}</p>
              <p class="font-semibold mt-0.5">{{ currentSubscription.plan_name }}</p>
            </div>
            <div v-if="currentSubscription.trial_ends_at">
              <p class="text-[hsl(var(--muted-foreground))]">{{ t('trial_ends') }}</p>
              <p class="font-semibold mt-0.5">{{ currentSubscription.trial_ends_at }}</p>
            </div>
            <div v-if="currentSubscription.ends_at">
              <p class="text-[hsl(var(--muted-foreground))]">{{ t('subscription_ends') }}</p>
              <p class="font-semibold mt-0.5">{{ currentSubscription.ends_at }}</p>
            </div>
          </div>

          <!-- Invoice monthly usage (only shown when plan has a limit) -->
          <div v-if="invoiceQuotaPercent !== null" class="border-t border-[hsl(var(--border))] pt-4">
            <div class="flex items-center justify-between text-sm mb-1.5">
              <span class="text-[hsl(var(--muted-foreground))]">{{ t('invoices_this_month') }}</span>
              <span class="font-medium">{{ invoiceQuota.invoices_this_month }} / {{ invoiceQuota.invoice_monthly_limit }}</span>
            </div>
            <div class="h-2 rounded-full bg-[hsl(var(--muted))] overflow-hidden">
              <div
                class="h-full rounded-full transition-all"
                :class="invoiceQuotaPercent >= 90 ? 'bg-[hsl(var(--destructive))]' : 'bg-[hsl(var(--primary))]'"
                :style="{ width: `${invoiceQuotaPercent}%` }"
              />
            </div>
          </div>

          <!-- Portal actions (only for Stripe-backed subscriptions) -->
          <div v-if="currentSubscription.has_stripe" class="border-t border-[hsl(var(--border))] pt-4">
            <p class="text-sm text-[hsl(var(--muted-foreground))] mb-3">{{ t('billing_portal_description') }}</p>
            <div class="flex flex-wrap gap-2">
              <Button variant="outline" size="sm" class="gap-2" @click="openPortal">
                <CreditCard class="h-4 w-4" />
                {{ t('update_payment_method') }}
              </Button>
              <Button variant="outline" size="sm" class="gap-2" @click="openPortal">
                <Settings class="h-4 w-4" />
                {{ t('manage_subscription') }}
              </Button>
            </div>
          </div>

          <!-- Invoices -->
          <div v-if="invoices.length > 0" class="border-t border-[hsl(var(--border))] pt-4">
            <h3 class="text-sm font-semibold mb-3">{{ t('recent_invoices') }}</h3>
            <div class="overflow-x-auto -mx-1">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-[hsl(var(--border))] text-[hsl(var(--muted-foreground))]">
                    <th class="text-left py-2 px-1 font-medium">{{ t('invoice_number') }}</th>
                    <th class="text-left py-2 px-1 font-medium">{{ t('date') }}</th>
                    <th class="text-right py-2 px-1 font-medium">{{ t('amount') }}</th>
                    <th class="text-center py-2 px-1 font-medium">{{ t('status') }}</th>
                    <th class="text-right py-2 px-1 font-medium"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="invoice in invoices"
                    :key="invoice.id"
                    class="border-b border-[hsl(var(--border)/0.5)] last:border-0"
                  >
                    <td class="py-2.5 px-1 font-mono text-xs">{{ invoice.number }}</td>
                    <td class="py-2.5 px-1">{{ invoice.date }}</td>
                    <td class="py-2.5 px-1 text-right font-medium">
                      {{ formatCurrency(invoice.amount, invoice.currency) }}
                    </td>
                    <td class="py-2.5 px-1 text-center">
                      <span
                        :class="{
                          'text-[hsl(var(--primary))] bg-[hsl(var(--accent))]': invoice.status === 'paid',
                          'text-yellow-700 bg-yellow-50 dark:text-yellow-400 dark:bg-yellow-950/50': invoice.status === 'open',
                          'text-[hsl(var(--destructive))] bg-[hsl(var(--destructive)/0.1)]': invoice.status === 'uncollectible',
                          'text-[hsl(var(--muted-foreground))] bg-[hsl(var(--muted))]': invoice.status === 'draft' || invoice.status === 'void',
                        }"
                        class="text-xs font-medium px-2 py-0.5 rounded-full"
                      >
                        {{ t(`invoice_status_${invoice.status}`) }}
                      </span>
                    </td>
                    <td class="py-2.5 px-1 text-right">
                      <div class="flex items-center justify-end gap-1">
                        <a
                          v-if="invoice.pdf_url"
                          :href="invoice.pdf_url"
                          target="_blank"
                          rel="noopener noreferrer"
                          class="inline-flex items-center justify-center h-7 w-7 rounded-md hover:bg-[hsl(var(--accent))] text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] transition-colors"
                          :title="t('download_pdf')"
                        >
                          <Download class="h-3.5 w-3.5" />
                        </a>
                        <a
                          v-if="invoice.hosted_url"
                          :href="invoice.hosted_url"
                          target="_blank"
                          rel="noopener noreferrer"
                          class="inline-flex items-center justify-center h-7 w-7 rounded-md hover:bg-[hsl(var(--accent))] text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] transition-colors"
                          :title="t('view_online')"
                        >
                          <ExternalLink class="h-3.5 w-3.5" />
                        </a>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- ══ PLANS ══ -->
      <div>
        <h2 class="text-base font-semibold mb-4">
          {{ currentSubscription ? t('change_plan') : t('choose_plan') }}
        </h2>
        <div
          v-if="!hasPlans"
          class="rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--muted)/0.4)] p-4 text-sm text-[hsl(var(--muted-foreground))]"
        >
          {{ t('no_plans_available') }}
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Card
            v-for="plan in plans"
            :key="plan.id"
            :class="currentSubscription?.plan_slug === plan.slug ? 'ring-2 ring-[hsl(var(--primary))]' : ''"
          >
            <CardHeader>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <Zap v-if="plan.slug !== 'starter'" class="h-5 w-5 text-[hsl(var(--primary))]" />
                  <CardTitle>{{ plan.name }}</CardTitle>
                </div>
                <span
                  v-if="currentSubscription?.plan_slug === plan.slug"
                  class="text-xs font-medium px-2 py-0.5 rounded-full bg-[hsl(var(--accent))] text-[hsl(var(--accent-foreground))]"
                >
                  {{ t('current_plan') }}
                </span>
              </div>
              <CardDescription>{{ plan.description }}</CardDescription>
            </CardHeader>
            <CardContent class="space-y-5">
              <div>
                <span class="text-3xl font-bold">{{ formatCurrency(plan.price_chf) }}</span>
                <span class="text-[hsl(var(--muted-foreground))] text-sm"> / {{ t('month') }}</span>
              </div>

              <ul class="space-y-2 text-sm">
                <li class="flex items-center gap-2">
                  <CheckCircle2 class="h-4 w-4 text-[hsl(var(--primary))] shrink-0" />
                  {{ plan.max_users === -1 ? t('unlimited_users') : `${plan.max_users} ${t('users')}` }}
                </li>
                <li class="flex items-center gap-2">
                  <CheckCircle2 class="h-4 w-4 text-[hsl(var(--primary))] shrink-0" />
                  {{ plan.max_invoices_per_month === -1 ? t('unlimited_invoices') : `${plan.max_invoices_per_month} ${t('invoices_per_month')}` }}
                </li>
                <li class="flex items-center gap-2">
                  <CheckCircle2 class="h-4 w-4 text-[hsl(var(--primary))] shrink-0" />
                  {{ plan.max_ocr_scans_per_day === -1 ? t('unlimited_ocr_per_day') : `${plan.max_ocr_scans_per_day} ${t('ocr_scans_per_day')}` }}
                </li>
                <li v-for="feature in plan.features" :key="feature" class="flex items-center gap-2">
                  <CheckCircle2 class="h-4 w-4 text-[hsl(var(--primary))] shrink-0" />
                  {{ t(`feature_${feature}`) }}
                </li>
              </ul>

              <Button
                v-if="currentSubscription?.plan_slug !== plan.slug || isTrialingWithoutStripe"
                class="w-full"
                :disabled="!plan.is_checkout_available"
                @click="checkout(plan.id)"
              >
                {{
                  !plan.is_checkout_available
                    ? t('plan_checkout_unavailable')
                    : isTrialingWithoutStripe && currentSubscription?.plan_slug === plan.slug
                      ? t('add_payment_method')
                      : currentSubscription
                        ? t('switch_plan')
                        : t('start_trial')
                }}
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

