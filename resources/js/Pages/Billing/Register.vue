<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { CheckCircle2, Zap } from 'lucide-vue-next'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import Button from '@/Components/UI/Button.vue'
import PasswordStrength from '@/Components/UI/PasswordStrength.vue'

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

const props = defineProps({
  plans: { type: Array, default: () => [] },
  trial_days: { type: Number, default: 14 },
  chart_templates: { type: Array, default: () => [] },
})

const selectedPlan = ref(props.plans[0]?.id ?? null)

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  org_name: '',
  plan_id: selectedPlan,
  accepted_privacy: false,
  chart_of_accounts: 'swiss_sme',
})

function submit() {
  form.plan_id = selectedPlan.value
  form.post('/signup')
}
</script>

<template>
  <div class="min-h-screen bg-[hsl(var(--background))] flex flex-col justify-center py-12 sm:px-6 lg:px-8">

    <!-- Logo -->
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center mb-8">
      <img src="/logo-wide.svg" alt="Gäld" class="h-8 mx-auto mb-6" />
      <h2 class="text-2xl font-bold text-[hsl(var(--foreground))]">
        {{ t('signup_title', { days: trial_days }) }}
      </h2>
      <p class="mt-2 text-sm text-[hsl(var(--muted-foreground))]">{{ t('signup_subtitle') }}</p>
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-2xl space-y-6">

      <!-- Plan selector -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <button
          v-for="plan in plans"
          :key="plan.id"
          type="button"
          role="radio"
          :aria-checked="selectedPlan === plan.id"
          :aria-pressed="selectedPlan === plan.id"
          @click="selectedPlan = plan.id"
          :class="[
            'relative rounded-lg border-2 p-4 text-left transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[hsl(var(--ring))] focus-visible:ring-offset-2',
            selectedPlan === plan.id
              ? 'border-[hsl(var(--primary))] bg-[hsl(var(--accent))] ring-2 ring-[hsl(var(--primary))] ring-offset-2'
              : 'border-[hsl(var(--border))] bg-[hsl(var(--card))] opacity-80 hover:opacity-100',
          ]"
        >
          <CheckCircle2
            v-if="selectedPlan === plan.id"
            class="absolute right-2 top-2 h-5 w-5 text-[hsl(var(--primary))]"
            aria-hidden="true"
          />
          <div class="flex items-center gap-2 mb-1">
            <Zap v-if="plan.slug !== 'starter'" class="h-4 w-4 text-[hsl(var(--primary))]" />
            <span class="font-semibold text-[hsl(var(--foreground))]">{{ plan.name }}</span>
          </div>
          <p class="text-2xl font-bold text-[hsl(var(--foreground))]">
            {{ formatCurrency(plan.price_chf) }}<span class="text-sm font-normal text-[hsl(var(--muted-foreground))]">/{{ t('month') }}</span>
          </p>
          <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">{{ plan.description }}</p>
          <ul class="mt-3 space-y-1">
            <li class="flex items-center gap-1.5 text-xs text-[hsl(var(--foreground))]">
              <CheckCircle2 class="h-3 w-3 text-[hsl(var(--primary))] shrink-0" />
              {{ plan.max_users === -1 ? t('unlimited_users') : `${plan.max_users} ${t(plan.max_users === 1 ? 'user' : 'users')}` }}
            </li>
            <li class="flex items-center gap-1.5 text-xs text-[hsl(var(--foreground))]">
              <CheckCircle2 class="h-3 w-3 text-[hsl(var(--primary))] shrink-0" />
              {{ plan.max_invoices_per_month === -1 ? t('unlimited_invoices') : `${plan.max_invoices_per_month} ${t(plan.max_invoices_per_month === 1 ? 'invoice_per_month' : 'invoices_per_month')}` }}
            </li>
            <li class="flex items-center gap-1.5 text-xs text-[hsl(var(--foreground))]">
              <CheckCircle2 class="h-3 w-3 text-[hsl(var(--primary))] shrink-0" />
              {{ plan.max_ocr_scans_per_day === -1 ? t('unlimited_ocr_per_day') : `${plan.max_ocr_scans_per_day} ${t(plan.max_ocr_scans_per_day === 1 ? 'ocr_scan_per_day' : 'ocr_scans_per_day')}` }}
            </li>
            <li v-for="feature in plan.features" :key="feature" class="flex items-center gap-1.5 text-xs text-[hsl(var(--foreground))]">
              <CheckCircle2 class="h-3 w-3 text-[hsl(var(--primary))] shrink-0" />
              {{ t(`feature_${feature}`) }}
            </li>
          </ul>
        </button>
      </div>

      <!-- Registration form -->
      <div class="bg-[hsl(var(--card))] rounded-lg border border-[hsl(var(--border))] shadow-sm py-8 px-6">
        <form @submit.prevent="submit" class="space-y-5">

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-[hsl(var(--foreground))] mb-1">{{ t('full_name') }}</label>
              <input
                v-model="form.name"
                type="text"
                required
                class="block w-full rounded-md border border-[hsl(var(--input))] bg-[hsl(var(--background))] px-3 py-2 text-base sm:text-sm text-[hsl(var(--foreground))] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]"
              />
              <p v-if="form.errors.name" class="mt-1 text-xs text-[hsl(var(--destructive))]">{{ form.errors.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-[hsl(var(--foreground))] mb-1">{{ t('company_name') }}</label>
              <input
                v-model="form.org_name"
                type="text"
                required
                class="block w-full rounded-md border border-[hsl(var(--input))] bg-[hsl(var(--background))] px-3 py-2 text-base sm:text-sm text-[hsl(var(--foreground))] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]"
              />
              <p v-if="form.errors.org_name" class="mt-1 text-xs text-[hsl(var(--destructive))]">{{ form.errors.org_name }}</p>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-[hsl(var(--foreground))] mb-1">{{ t('email') }}</label>
            <input
              v-model="form.email"
              type="email"
              required
              class="block w-full rounded-md border border-[hsl(var(--input))] bg-[hsl(var(--background))] px-3 py-2 text-base sm:text-sm text-[hsl(var(--foreground))] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]"
            />
            <p v-if="form.errors.email" class="mt-1 text-xs text-[hsl(var(--destructive))]">{{ form.errors.email }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-[hsl(var(--foreground))] mb-1">{{ t('chart_of_accounts') }}</label>
            <select
              v-model="form.chart_of_accounts"
              class="block w-full rounded-md border border-[hsl(var(--input))] bg-[hsl(var(--background))] px-3 py-2 text-base sm:text-sm text-[hsl(var(--foreground))] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]"
            >
              <option v-for="tpl in chart_templates" :key="tpl.key" :value="tpl.key">{{ t(tpl.label_key) }}</option>
              <option value="none">{{ t('chart_none') }}</option>
            </select>
            <p class="mt-1 text-xs text-[hsl(var(--muted-foreground))]">{{ t('chart_of_accounts_signup_help') }}</p>
            <p v-if="form.errors.chart_of_accounts" class="mt-1 text-xs text-[hsl(var(--destructive))]">{{ form.errors.chart_of_accounts }}</p>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-[hsl(var(--foreground))] mb-1">{{ t('password') }}</label>
              <input
                v-model="form.password"
                type="password"
                required
                class="block w-full rounded-md border border-[hsl(var(--input))] bg-[hsl(var(--background))] px-3 py-2 text-base sm:text-sm text-[hsl(var(--foreground))] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]"
              />
              <p v-if="form.errors.password" class="mt-1 text-xs text-[hsl(var(--destructive))]">{{ form.errors.password }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-[hsl(var(--foreground))] mb-1">{{ t('confirm_password') }}</label>
              <input
                v-model="form.password_confirmation"
                type="password"
                required
                class="block w-full rounded-md border border-[hsl(var(--input))] bg-[hsl(var(--background))] px-3 py-2 text-base sm:text-sm text-[hsl(var(--foreground))] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]"
              />
            </div>
          </div>

          <PasswordStrength :password="form.password" />
          <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('password_requirements_hint') }}</p>

          <div class="space-y-1">
            <label class="flex items-start gap-2">
              <input
                v-model="form.accepted_privacy"
                type="checkbox"
                class="mt-1 h-4 w-4 rounded border-[hsl(var(--input))] text-[hsl(var(--primary))] focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]"
              />
              <span class="text-sm text-[hsl(var(--muted-foreground))]">
                {{ t('accept_privacy_prefix') }}
                <a href="https://gaeld.ch/privacy" target="_blank" class="font-medium text-[hsl(var(--primary))] hover:underline">{{ t('privacy_policy') }}</a>
                {{ t('and') }}
                <a href="https://gaeld.ch/terms" target="_blank" class="font-medium text-[hsl(var(--primary))] hover:underline">{{ t('terms_of_service') }}</a>.
              </span>
            </label>
            <p v-if="form.errors.accepted_privacy" class="text-sm text-[hsl(var(--destructive))]">{{ form.errors.accepted_privacy }}</p>
          </div>

          <Button type="submit" class="w-full" :disabled="form.processing">
            {{ form.processing ? t('creating_account') : t('signup_cta', { days: trial_days }) }}
          </Button>

          <p class="text-center text-xs text-[hsl(var(--muted-foreground))]">
            {{ t('signup_disclaimer') }}
          </p>
        </form>
      </div>

      <p class="text-center text-sm text-[hsl(var(--muted-foreground))]">
        {{ t('have_account') }}
        <a href="/login" class="text-[hsl(var(--primary))] font-medium hover:underline">{{ t('sign_in') }}</a>
      </p>

    </div>
  </div>
</template>
