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
import { CheckCircle2, Zap, AlertCircle, CreditCard, FileText, Settings } from 'lucide-vue-next'

const { t } = useTranslations()
const page = usePage()

const props = defineProps({
  plans: { type: Array, default: () => [] },
  currentSubscription: { type: Object, default: null },
})

// Show success banner when returning from Stripe checkout
const checkoutResult = computed(() => {
  const params = new URLSearchParams(window.location.search)
  return params.get('checkout') // 'success' | 'canceled' | null
})

const statusBadgeClass = {
  active: 'text-[hsl(var(--primary))] bg-[hsl(var(--accent))]',
  trialing: 'text-blue-700 bg-blue-50',
  past_due: 'text-[hsl(var(--destructive))] bg-[hsl(var(--destructive)/0.1)]',
  canceled: 'text-[hsl(var(--muted-foreground))] bg-[hsl(var(--muted))]',
  paused: 'text-yellow-700 bg-yellow-50',
}

function checkout(planId) {
  router.post(`/billing/checkout/${planId}`)
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

          <!-- Portal actions -->
          <div class="border-t border-[hsl(var(--border))] pt-4">
            <p class="text-sm text-[hsl(var(--muted-foreground))] mb-3">{{ t('billing_portal_description') }}</p>
            <div class="flex flex-wrap gap-2">
              <Button variant="outline" size="sm" class="gap-2" @click="openPortal">
                <FileText class="h-4 w-4" />
                {{ t('view_invoices') }}
              </Button>
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
        </CardContent>
      </Card>

      <!-- ══ PLANS ══ -->
      <div>
        <h2 class="text-base font-semibold mb-4">
          {{ currentSubscription ? t('change_plan') : t('choose_plan') }}
        </h2>
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
                <span class="text-3xl font-bold">CHF {{ plan.price_chf }}</span>
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
                <li v-for="feature in plan.features" :key="feature" class="flex items-center gap-2">
                  <CheckCircle2 class="h-4 w-4 text-[hsl(var(--primary))] shrink-0" />
                  {{ t(`feature_${feature}`) }}
                </li>
              </ul>

              <Button
                v-if="currentSubscription?.plan_slug !== plan.slug"
                class="w-full"
                @click="checkout(plan.id)"
              >
                {{ currentSubscription ? t('switch_plan') : t('start_trial') }}
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

