<script setup>
import { usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import { useTranslations } from '@/lib/useTranslations'
import { CheckCircle2, Zap, AlertCircle, CreditCard } from 'lucide-vue-next'

const { t } = useTranslations()
const page = usePage()

const props = defineProps({
  plans: { type: Array, default: () => [] },
  currentSubscription: { type: Object, default: null },
})

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

      <!-- Past due warning -->
      <div
        v-if="currentSubscription?.status === 'past_due'"
        class="flex items-center gap-3 rounded-lg border border-[hsl(var(--destructive)/0.3)] bg-[hsl(var(--destructive)/0.08)] p-4 text-sm text-[hsl(var(--destructive))]"
      >
        <AlertCircle class="h-4 w-4 shrink-0" />
        <span>{{ t('payment_failed_warning') }}</span>
        <button class="ml-auto underline font-medium" @click="openPortal">{{ t('update_payment_method') }}</button>
      </div>

      <!-- Current subscription card -->
      <Card v-if="currentSubscription">
        <CardHeader>
          <CardTitle>{{ t('current_plan') }}</CardTitle>
        </CardHeader>
        <CardContent class="flex items-center justify-between gap-4">
          <div class="space-y-1">
            <p class="font-semibold capitalize">{{ currentSubscription.plan_slug }}</p>
            <p class="text-sm">
              <span :class="{
                'text-[hsl(var(--primary))]': currentSubscription.status === 'active',
                'text-blue-600': currentSubscription.status === 'trialing',
                'text-[hsl(var(--destructive))]': currentSubscription.status === 'past_due',
                'text-[hsl(var(--muted-foreground))]': ['canceled','paused'].includes(currentSubscription.status),
              }">
                {{ t(`subscription_status_${currentSubscription.status}`) }}
              </span>
              <span v-if="currentSubscription.trial_ends_at" class="text-[hsl(var(--muted-foreground))] ml-1">
                — {{ t('trial_ends') }} {{ currentSubscription.trial_ends_at }}
              </span>
            </p>
          </div>
          <Button
            v-if="currentSubscription.status !== 'canceled'"
            variant="outline"
            size="sm"
            class="gap-2"
            @click="openPortal"
          >
            <CreditCard class="h-4 w-4" />
            {{ t('manage_subscription') }}
          </Button>
        </CardContent>
      </Card>

      <!-- Plan cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
  </AppLayout>
</template>

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
