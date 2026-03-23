<script setup>
import { usePage } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import { useTranslations } from '@/lib/useTranslations'
import { CheckCircle2, Zap } from 'lucide-vue-next'

const { t } = useTranslations()
const page = usePage()

const props = defineProps({
  plans: { type: Array, default: () => [] },
  currentSubscription: { type: Object, default: null },
})

const statusColor = {
  active: 'text-green-600',
  trialing: 'text-blue-600',
  past_due: 'text-red-600',
  canceled: 'text-gray-400',
  paused: 'text-yellow-600',
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
    <div class="max-w-4xl mx-auto space-y-6">

      <!-- Current subscription banner -->
      <Card v-if="currentSubscription">
        <CardHeader>
          <CardTitle>{{ t('current_plan') }}</CardTitle>
        </CardHeader>
        <CardContent class="flex items-center justify-between">
          <div>
            <p class="text-lg font-semibold capitalize">{{ currentSubscription.plan_slug }}</p>
            <p :class="statusColor[currentSubscription.status] ?? 'text-gray-600'" class="text-sm capitalize">
              {{ t(`subscription_status_${currentSubscription.status}`) }}
              <span v-if="currentSubscription.trial_ends_at" class="text-gray-500">
                — {{ t('trial_ends') }} {{ currentSubscription.trial_ends_at }}
              </span>
            </p>
          </div>
          <button
            v-if="currentSubscription.status !== 'canceled'"
            @click="openPortal"
            class="text-sm underline text-gray-600 hover:text-gray-900"
          >
            {{ t('manage_subscription') }}
          </button>
        </CardContent>
      </Card>

      <!-- Past due warning -->
      <div v-if="currentSubscription?.status === 'past_due'"
           class="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-800">
        {{ t('payment_failed_warning') }}
        <button @click="openPortal" class="ml-2 underline font-medium">{{ t('update_payment_method') }}</button>
      </div>

      <!-- Plan cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Card
          v-for="plan in plans"
          :key="plan.id"
          :class="currentSubscription?.plan_slug === plan.slug ? 'ring-2 ring-blue-500' : ''"
        >
          <CardHeader>
            <div class="flex items-center gap-2">
              <Zap v-if="plan.slug === 'pro'" class="h-5 w-5 text-blue-500" />
              <CardTitle>{{ plan.name }}</CardTitle>
            </div>
            <CardDescription>{{ plan.description }}</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div>
              <span class="text-3xl font-bold">CHF {{ plan.price_chf }}</span>
              <span class="text-gray-500 text-sm"> / {{ t('month') }}</span>
            </div>

            <ul class="space-y-2 text-sm">
              <li class="flex items-center gap-2">
                <CheckCircle2 class="h-4 w-4 text-green-500 flex-shrink-0" />
                {{ plan.max_users === -1 ? t('unlimited_users') : `${plan.max_users} ${t('users')}` }}
              </li>
              <li class="flex items-center gap-2">
                <CheckCircle2 class="h-4 w-4 text-green-500 flex-shrink-0" />
                {{ plan.max_invoices_per_month === -1 ? t('unlimited_invoices') : `${plan.max_invoices_per_month} ${t('invoices_per_month')}` }}
              </li>
              <li v-for="feature in plan.features" :key="feature" class="flex items-center gap-2">
                <CheckCircle2 class="h-4 w-4 text-green-500 flex-shrink-0" />
                {{ t(`feature_${feature}`) }}
              </li>
            </ul>

            <button
              v-if="currentSubscription?.plan_slug !== plan.slug"
              @click="checkout(plan.id)"
              class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
              {{ currentSubscription ? t('switch_plan') : t('start_trial') }}
            </button>
            <div v-else class="w-full text-center text-sm text-gray-500 py-2">
              {{ t('current_plan') }}
            </div>
          </CardContent>
        </Card>
      </div>

    </div>
  </AppLayout>
</template>
