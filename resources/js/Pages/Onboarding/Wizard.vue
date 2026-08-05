<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, useForm, usePage, router, Link } from '@inertiajs/vue3'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useTheme } from '@/lib/useTheme'
import { Check, User, Building2, Calculator, Sun, Moon, Rocket } from 'lucide-vue-next'

const props = defineProps({
  organization: { type: Object, default: () => ({}) },
  modules: { type: Array, default: () => [] },
  modulePresets: { type: Object, default: () => ({}) },
  hasFiscalYear: { type: Boolean, default: false },
  hasBankAccount: { type: Boolean, default: false },
})

const { t } = useTranslations()
const { isDark, toggleTheme } = useTheme()
const page = usePage()
const moduleKeys = computed(() => Array.isArray(props.modules) ? props.modules : [])

const LOCALES = [
  { value: 'en', label: 'EN' },
  { value: 'fr', label: 'FR' },
  { value: 'de', label: 'DE' },
  { value: 'it', label: 'IT' },
]

const currentLocale = computed(() => page.props.locale ?? 'en')

const isSaas = computed(() => page.props.features?.saas ?? false)
const isFreePlan = computed(() => page.props.subscription?.plan_slug === 'free')

function switchLocale(lang) {
  if (lang === currentLocale.value) return
  router.put('/profile/locale', { locale: lang }, {
    preserveScroll: true,
    preserveState: false,
  })
}

const cantons = ['AG','AI','AR','BE','BL','BS','FR','GE','GL','GR','JU','LU','NE','NW','OW','SG','SH','SO','SZ','TG','TI','UR','VD','VS','ZG','ZH']

const featureFlags = usePage().props.features || {}

const form = useForm({
  business_type: props.organization.business_type || '',
  modules: Object.fromEntries(
    props.modules.map((key) => {
      const orgValue = props.organization.enabled_modules?.[key]
      return [key, orgValue ?? !!featureFlags[key]]
    })
  ),
  legal_name: props.organization.legal_name || '',
  address: props.organization.address || '',
  city: props.organization.city || '',
  postal_code: props.organization.postal_code || '',
  canton: props.organization.canton || '',
  vat_number: props.organization.vat_number || '',
  fiscal_year_name: '',
  fiscal_year_start: '',
  fiscal_year_end: '',
  bank_account_name: '',
  bank_name: '',
  iban: '',
})

// Optional sections — skipped by default so the wizard stays light.
const createFiscalYear = ref(false)
const createBankAccount = ref(false)

const businessTypes = [
  { value: 'freelancer', icon: User, label: () => t('business_type_freelancer'), desc: () => t('business_type_freelancer_desc') },
  { value: 'sme', icon: Building2, label: () => t('business_type_sme'), desc: () => t('business_type_sme_desc') },
  { value: 'fiduciary', icon: Calculator, label: () => t('business_type_fiduciary'), desc: () => t('business_type_fiduciary_desc') },
]

function selectBusinessType(type) {
  form.business_type = type
  const preset = props.modulePresets[type]
  if (!preset) return
  props.modules.forEach((key) => {
    if (key in preset) {
      form.modules[key] = preset[key]
    }
  })
}

const enabledModuleCount = computed(
  () => props.modules.filter((key) => form.modules[key]).length
)

const baseSteps = [
  { key: 'modules', label: () => t('onboarding_step_modules') },
  { key: 'company', label: () => t('onboarding_step_company') },
  { key: 'fiscal_year', label: () => t('onboarding_step_fiscal_year') },
  { key: 'bank', label: () => t('onboarding_step_bank') },
]

const steps = computed(() => {
  if (isSaas.value && isFreePlan.value) {
    return [...baseSteps, { key: 'upgrade', label: () => t('onboarding_step_upgrade') }]
  }
  return baseSteps
})

const currentStep = ref(0)

function nextStep() {
  if (currentStep.value < steps.value.length - 1) {
    currentStep.value++
  }
}

function prevStep() {
  if (currentStep.value > 0) {
    currentStep.value--
  }
}

// The bank step is always index 3. Submit posts the form from that step.
function submit() {
  const payload = { ...form.data() }

  if (!createFiscalYear.value) {
    payload.fiscal_year_name = ''
    payload.fiscal_year_start = ''
    payload.fiscal_year_end = ''
  }
  if (!createBankAccount.value) {
    payload.bank_account_name = ''
    payload.bank_name = ''
    payload.iban = ''
  }

  form.transform(() => payload).post('/welcome', {
    onSuccess: () => {
      // If there is an upgrade step, advance to it instead of following the redirect.
      if (steps.value.length > 4) {
        currentStep.value = 4
      }
    },
    onError: () => {
      if (form.errors.legal_name || form.errors.address || form.errors.city || form.errors.postal_code || form.errors.canton || form.errors.vat_number) {
        currentStep.value = 1
      } else if (form.errors.fiscal_year_name || form.errors.fiscal_year_start || form.errors.fiscal_year_end) {
        currentStep.value = 2
      } else if (form.errors.bank_account_name || form.errors.bank_name || form.errors.iban) {
        currentStep.value = 3
      }
    },
  })
}

function skip() {
  router.post('/welcome/skip')
}
</script>

<template>
  <Head :title="t('onboarding_wizard')" />
  <!-- Language + theme bar -->
  <div class="fixed top-3 right-4 z-50 flex items-center gap-1">
    <button
      v-for="l in LOCALES"
      :key="l.value"
      :aria-label="l.label"
      :aria-pressed="currentLocale === l.value"
      class="h-8 rounded px-2 text-xs font-medium transition-colors"
      :class="currentLocale === l.value
        ? 'bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))]'
        : 'text-[hsl(var(--muted-foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))]'"
      @click="switchLocale(l.value)"
    >{{ l.label }}</button>
    <button
      class="ml-1 h-8 w-8 rounded flex items-center justify-center text-[hsl(var(--muted-foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))] transition-colors"
      :aria-label="isDark ? 'Light mode' : 'Dark mode'"
      @click="toggleTheme"
    >
      <Sun v-if="isDark" class="h-4 w-4" />
      <Moon v-else class="h-4 w-4" />
    </button>
  </div>
  <div class="flex min-h-screen items-center justify-center bg-[hsl(var(--background))] p-8">
    <Card class="w-full max-w-2xl">
      <CardHeader>
        <CardTitle class="text-3xl">{{ t('onboarding_welcome_title') }}</CardTitle>
        <CardDescription>{{ t('onboarding_welcome_desc') }}</CardDescription>

        <!-- Stepper indicator -->
        <nav aria-label="Onboarding progress" class="mt-6">
          <ol class="flex items-center gap-2">
            <li
              v-for="(step, i) in steps"
              :key="step.key"
              class="flex items-center gap-2"
            >
              <button
                type="button"
                class="flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-medium transition-colors"
                :class="[
                  i === currentStep
                    ? 'bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))]'
                    : i < currentStep
                      ? 'bg-[hsl(var(--primary)/0.15)] text-[hsl(var(--primary))]'
                      : 'bg-[hsl(var(--muted))] text-[hsl(var(--muted-foreground))]',
                ]"
                @click="i < currentStep ? currentStep = i : undefined"
              >
                <span
                  class="flex h-5 w-5 items-center justify-center rounded-full text-xs"
                  :class="i < currentStep ? '' : 'border border-current'"
                >
                  <Check v-if="i < currentStep" class="h-3 w-3" />
                  <span v-else>{{ i + 1 }}</span>
                </span>
                {{ step.label() }}
              </button>
              <span v-if="i < steps.value.length - 1" class="h-px w-6 bg-[hsl(var(--border))]" />
            </li>
          </ol>
        </nav>
      </CardHeader>
      <CardContent>
        <form class="space-y-6" @submit.prevent="submit">
          <!-- Step 1: Modules -->
          <fieldset v-show="currentStep === 0" class="space-y-4">
            <legend class="text-lg font-semibold">{{ t('onboarding_modules_title') }}</legend>
            <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('onboarding_modules_desc') }}</p>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
              <button
                v-for="bt in businessTypes"
                :key="bt.value"
                type="button"
                class="flex flex-col items-center gap-3 rounded-xl border-2 p-5 text-center transition-all hover:border-[hsl(var(--primary))] hover:bg-[hsl(var(--accent))]"
                :class="form.business_type === bt.value ? 'border-[hsl(var(--primary))] bg-[hsl(var(--accent))]' : 'border-[hsl(var(--border))]'"
                @click="selectBusinessType(bt.value)"
              >
                <component :is="bt.icon" class="h-7 w-7" :class="form.business_type === bt.value ? 'text-[hsl(var(--primary))]' : 'text-[hsl(var(--muted-foreground))]'" />
                <span class="text-sm font-semibold">{{ bt.label() }}</span>
                <span class="text-xs text-[hsl(var(--muted-foreground))]">{{ bt.desc() }}</span>
              </button>
            </div>

            <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('onboarding_modules_preset_hint') }}</p>

            <div class="space-y-2">
              <label
                v-for="key in moduleKeys"
                :key="key"
                class="flex items-start gap-3 rounded-lg border border-[hsl(var(--border))] p-3 hover:bg-[hsl(var(--accent))]/50"
              >
                <input
                  type="checkbox"
                  v-model="form.modules[key]"
                  class="mt-0.5 h-4 w-4 rounded border-[hsl(var(--border))]"
                />
                <div class="flex-1">
                  <div class="text-sm font-medium">{{ t('module_' + key) }}</div>
                  <div class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('module_' + key + '_desc') }}</div>
                </div>
              </label>
            </div>
            <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ enabledModuleCount }} / {{ moduleKeys.length }}</p>
          </fieldset>

          <!-- Step 2: Company details -->
          <fieldset v-show="currentStep === 1" class="space-y-6">
            <legend class="text-lg font-semibold">{{ t('onboarding_company_title') }}</legend>
            <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('onboarding_company_desc') }}</p>
            <FormInput id="legal_name" v-model="form.legal_name" :label="t('legal_name_different')" :error="form.errors.legal_name" />
            <FormInput id="address" v-model="form.address" :label="t('address')" :error="form.errors.address" />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormInput id="city" v-model="form.city" :label="t('city')" :error="form.errors.city" />
              <FormInput id="postal_code" v-model="form.postal_code" :label="t('postal_code')" :error="form.errors.postal_code" />
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormSelect id="canton" v-model="form.canton" :label="t('canton')" :options="cantons" :placeholder="t('select')" :error="form.errors.canton" />
              <FormInput id="vat_number" v-model="form.vat_number" :label="t('vat_number')" :placeholder="t('placeholder_vat_uid')" :error="form.errors.vat_number" />
            </div>
          </fieldset>

          <!-- Step 3: Fiscal year (optional) -->
          <fieldset v-show="currentStep === 2" class="space-y-6">
            <legend class="text-lg font-semibold">{{ t('onboarding_fiscal_year_title') }}</legend>
            <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('onboarding_fiscal_year_desc') }}</p>
            <label class="flex items-center gap-3 rounded-lg border border-[hsl(var(--border))] p-3">
              <input type="checkbox" v-model="createFiscalYear" class="h-4 w-4 rounded border-[hsl(var(--border))]" />
              <span class="text-sm font-medium">{{ t('onboarding_fiscal_year_enable') }}</span>
            </label>
            <div v-if="createFiscalYear" class="space-y-4">
              <FormInput id="fiscal_year_name" v-model="form.fiscal_year_name" :label="t('name')" :error="form.errors.fiscal_year_name" />
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormInput id="fiscal_year_start" v-model="form.fiscal_year_start" type="date" :label="t('start_date')" :error="form.errors.fiscal_year_start" />
                <FormInput id="fiscal_year_end" v-model="form.fiscal_year_end" type="date" :label="t('end_date')" :error="form.errors.fiscal_year_end" />
              </div>
            </div>
          </fieldset>

          <!-- Step 4: Bank account (optional) -->
          <fieldset v-show="currentStep === 3" class="space-y-6">
            <legend class="text-lg font-semibold">{{ t('onboarding_bank_title') }}</legend>
            <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('onboarding_bank_desc') }}</p>
            <label class="flex items-center gap-3 rounded-lg border border-[hsl(var(--border))] p-3">
              <input type="checkbox" v-model="createBankAccount" class="h-4 w-4 rounded border-[hsl(var(--border))]" />
              <span class="text-sm font-medium">{{ t('onboarding_bank_enable') }}</span>
            </label>
            <div v-if="createBankAccount" class="space-y-4">
              <FormInput id="bank_account_name" v-model="form.bank_account_name" :label="t('name')" :error="form.errors.bank_account_name" />
              <FormInput id="bank_name" v-model="form.bank_name" :label="t('bank_name')" :error="form.errors.bank_name" />
              <FormInput id="iban" v-model="form.iban" label="IBAN" :error="form.errors.iban" />
            </div>
          </fieldset>

          <!-- Step 5: Upgrade nudge (SaaS free plan only) -->
          <div v-if="currentStep === 4" class="space-y-6">
            <div class="flex flex-col items-center gap-4 py-4 text-center">
              <div class="flex h-14 w-14 items-center justify-center rounded-full bg-[hsl(var(--primary)/0.12)]">
                <Rocket class="h-7 w-7 text-[hsl(var(--primary))]" />
              </div>
              <div>
                <h3 class="text-lg font-semibold">{{ t('onboarding_upgrade_title') }}</h3>
                <p class="mt-1 text-sm text-[hsl(var(--muted-foreground))]">{{ t('onboarding_upgrade_desc') }}</p>
              </div>
              <div class="flex w-full flex-col gap-3 sm:flex-row sm:justify-center">
                <Link href="/billing" class="inline-flex items-center justify-center gap-2 rounded-md bg-[hsl(var(--primary))] px-6 py-2.5 text-sm font-medium text-[hsl(var(--primary-foreground))] transition-colors hover:bg-[hsl(var(--primary))]/90">
                  {{ t('onboarding_upgrade_cta') }}
                </Link>
                <Link href="/" class="inline-flex items-center justify-center rounded-md border border-[hsl(var(--border))] px-6 py-2.5 text-sm font-medium transition-colors hover:bg-[hsl(var(--accent))]">
                  {{ t('onboarding_go_dashboard') }}
                </Link>
              </div>
            </div>
          </div>

          <!-- Navigation buttons -->
          <div class="flex items-center justify-between">
            <Button
              v-if="currentStep > 0"
              type="button"
              variant="outline"
              @click="prevStep"
            >
              {{ t('back') }}
            </Button>
            <span v-else />

            <div class="flex items-center gap-3">
              <!-- Hide skip on the upgrade step -->
              <button
                v-if="currentStep < steps.value.length - 1"
                type="button"
                class="text-sm text-[hsl(var(--muted-foreground))] underline-offset-2 hover:underline"
                @click="skip"
              >
                {{ t('onboarding_skip') }}
              </button>
              <!-- Next button for steps 0-2 -->
              <Button
                v-if="currentStep < 3"
                type="button"
                @click="nextStep"
              >
                {{ t('next') }}
              </Button>
              <!-- Finish setup on the bank step (index 3) -->
              <Button
                v-else-if="currentStep === 3"
                type="submit"
                :disabled="form.processing"
              >
                {{ t('onboarding_finish') }}
              </Button>
              <!-- No extra button on upgrade step — CTAs are inline above -->
            </div>
          </div>
        </form>
      </CardContent>
    </Card>
  </div>
</template>
