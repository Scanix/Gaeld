<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import HelpText from '@/Components/HelpText.vue'
import { useTranslations } from '@/lib/useTranslations'
import { Calculator } from 'lucide-vue-next'

const props = defineProps({
  rates: { type: Object, default: () => ({}) },
})

const { t } = useTranslations()

// Swiss number formatting with apostrophe
function chf(val) {
  if (val === null || val === undefined) return '—'
  return Number(val).toLocaleString('de-CH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// Income input (raw string to allow apostrophe typing)
const incomeInput = ref('')
const income = computed(() => parseFloat(incomeInput.value.replace(/['']/g, '').replace(',', '.')) || 0)

// Swiss AVS/AI/APG rates (defaults if not passed from backend)
const defaultRates = {
  avs: 10.25,
  ai: 1.40,
  apg: 0.495,
}
const effectiveRates = computed(() => ({ ...defaultRates, ...props.rates }))

// Breakdown calculation
const breakdown = computed(() => {
  const inc = income.value
  if (!inc || inc <= 0) return null
  const avs = (inc * effectiveRates.value.avs) / 100
  const ai = (inc * effectiveRates.value.ai) / 100
  const apg = (inc * effectiveRates.value.apg) / 100
  const total = avs + ai + apg
  return { avs, ai, apg, total }
})

const rateRows = computed(() => [
  { key: 'avs', label: t('social_avs'), rate: effectiveRates.value.avs },
  { key: 'ai', label: t('social_ai'), rate: effectiveRates.value.ai },
  { key: 'apg', label: t('social_apg'), rate: effectiveRates.value.apg },
])

const totalRate = computed(() =>
  effectiveRates.value.avs + effectiveRates.value.ai + effectiveRates.value.apg
)

// Post to ledger
const showPost = ref(false)
const postForm = useForm({ annual_income: 0 })

function openPostDialog() {
  postForm.annual_income = income.value
  showPost.value = true
}

function postToLedger() {
  postForm.post('/accounting/social-charges/post', {
    preserveScroll: true,
    onSuccess: () => { showPost.value = false },
  })
}
</script>

<template>
  <AppLayout :title="t('social_charges')">
    <HelpText :title="t('help_social_title')" class="mb-6">
      <p>{{ t('help_social_text') }}</p>
    </HelpText>

    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Income Input -->
      <Card>
        <CardHeader><CardTitle>{{ t('social_calculate') }}</CardTitle></CardHeader>
        <CardContent>
          <div class="space-y-4">
            <FormInput
              id="social-income"
              v-model="incomeInput"
              :label="t('social_annual_income')"
              placeholder="100'000"
            />
            <p class="text-xs text-[hsl(var(--muted-foreground))]">
              {{ t('social_income_hint') }}
            </p>
          </div>
        </CardContent>
      </Card>

      <!-- Swiss AVS Rates Table -->
      <Card>
        <CardHeader><CardTitle>{{ t('social_rates_2025') }}</CardTitle></CardHeader>
        <CardContent>
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b text-xs text-[hsl(var(--muted-foreground))]">
                <th class="pb-2 text-left">{{ t('social_contribution') }}</th>
                <th class="pb-2 text-right">{{ t('vat_rate') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rateRows" :key="row.key" class="border-b last:border-0">
                <td class="py-2">{{ row.label }}</td>
                <td class="py-2 text-right tabular-nums">{{ row.rate }}%</td>
              </tr>
              <tr class="border-t font-semibold">
                <td class="py-2">{{ t('total') }}</td>
                <td class="py-2 text-right tabular-nums">{{ totalRate.toFixed(3) }}%</td>
              </tr>
            </tbody>
          </table>
        </CardContent>
      </Card>
    </div>

    <!-- Breakdown Results -->
    <Card v-if="breakdown" class="mt-6">
      <CardHeader>
        <div class="flex items-center justify-between">
          <CardTitle>
            <Calculator class="mr-2 inline h-4 w-4" />
            {{ t('social_breakdown') }}
          </CardTitle>
          <Button @click="openPostDialog">{{ t('post_to_ledger') }}</Button>
        </div>
      </CardHeader>
      <CardContent>
        <div class="space-y-3">
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div class="rounded-lg border border-[hsl(var(--border))] p-4">
              <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('social_avs') }}</p>
              <p class="mt-1 text-xl font-bold tabular-nums">CHF {{ chf(breakdown.avs) }}</p>
            </div>
            <div class="rounded-lg border border-[hsl(var(--border))] p-4">
              <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('social_ai') }}</p>
              <p class="mt-1 text-xl font-bold tabular-nums">CHF {{ chf(breakdown.ai) }}</p>
            </div>
            <div class="rounded-lg border border-[hsl(var(--border))] p-4">
              <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('social_apg') }}</p>
              <p class="mt-1 text-xl font-bold tabular-nums">CHF {{ chf(breakdown.apg) }}</p>
            </div>
            <div class="rounded-lg border border-[hsl(var(--primary))]/10 bg-[hsl(var(--primary))]/5 p-4">
              <p class="text-xs font-medium text-[hsl(var(--primary))]">{{ t('social_total_charges') }}</p>
              <p class="mt-1 text-2xl font-bold tabular-nums text-[hsl(var(--primary))]">CHF {{ chf(breakdown.total) }}</p>
            </div>
          </div>
          <p class="text-xs text-[hsl(var(--muted-foreground))]">
            {{ t('social_based_on_income', { income: chf(income) }) }}
          </p>
        </div>
      </CardContent>
    </Card>

    <div v-else class="mt-6 rounded-lg border border-dashed border-[hsl(var(--border))] py-12 text-center text-sm text-[hsl(var(--muted-foreground))]">
      {{ t('social_enter_income_hint') }}
    </div>

    <!-- Post to Ledger Confirm -->
    <ConfirmDialog
      :open="showPost"
      :title="t('post_to_ledger')"
      :message="t('social_post_confirm', { amount: breakdown ? chf(breakdown.total) : '' })"
      :confirm-label="t('post')"
      confirm-variant="default"
      :processing="postForm.processing"
      @confirm="postToLedger"
      @cancel="showPost = false"
    />
  </AppLayout>
</template>
