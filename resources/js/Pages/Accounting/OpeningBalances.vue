<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import HelpText from '@/Components/HelpText.vue'
import Badge from '@/Components/UI/Badge.vue'
import { useForm, Link } from '@inertiajs/vue3'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { computed, ref } from 'vue'
import { ChevronDown, ChevronUp } from 'lucide-vue-next'

const props = defineProps({
  accounts: { type: Array, default: () => [] },
  defaultDate: { type: String, default: '' },
  existingOpening: { type: Object, default: null },
  isStartingFresh: { type: Boolean, default: false },
  equityAccounts: { type: Array, default: () => [] },
  existingHistorical: { type: Object, default: null },
})

const { t } = useTranslations()
const { formatCurrency, formatDate } = useFormatters()

const guideOpen = ref(false)

const typeLabels = {
  asset: t('account_type_asset'),
  liability: t('account_type_liability'),
  equity: t('account_type_equity'),
}

const form = useForm({
  date: props.defaultDate,
  reference: '',
  description: '',
  balances: props.accounts.map(a => ({ account_id: a.id, amount: '' })),
})

const historicalForm = useForm({
  date: props.defaultDate,
  account_id: props.equityAccounts[0]?.id ?? '',
  amount: '',
  reference: '',
  description: '',
})

const groupedAccounts = computed(() => {
  const groups = { asset: [], liability: [], equity: [] }
  props.accounts.forEach((a, idx) => {
    if (groups[a.type]) {
      groups[a.type].push({ ...a, index: idx })
    }
  })
  return groups
})

const totalSigned = computed(() =>
  form.balances.reduce((sum, b) => sum + (parseFloat(b.amount) || 0), 0)
)

const filledCount = computed(() =>
  form.balances.filter(b => parseFloat(b.amount) !== 0 && b.amount !== '').length
)

function balanceError(index) {
  return form.errors[`balances.${index}.amount`]
}

function submit() {
  form.transform(data => ({
    ...data,
    balances: data.balances
      .filter(b => b.amount !== '' && parseFloat(b.amount) !== 0)
      .map(b => ({ account_id: b.account_id, amount: String(b.amount) })),
  })).post('/accounting/opening-balances', { preserveScroll: true })
}

function submitHistorical() {
  historicalForm.post('/accounting/opening-balances/historical', { preserveScroll: true })
}
</script>

<template>
  <AppLayout :title="t('opening_balances')" help-page="accounting-basics">
    <HelpText :title="t('opening_balances_title')" class="mb-6">
      <p>{{ t('opening_balances_help') }}</p>
      <p class="mt-2">{{ t('opening_balances_sign_help') }}</p>
    </HelpText>

    <div v-if="props.isStartingFresh && !existingOpening" class="mb-4 rounded-md border border-[hsl(var(--primary)/0.3)] bg-[hsl(var(--primary)/0.06)] p-4 text-sm">
      <p class="font-medium text-[hsl(var(--primary))]">{{ t('opening_balances_fresh_start_title') }}</p>
      <p class="mt-1 text-[hsl(var(--muted-foreground))]">{{ t('opening_balances_fresh_start_text') }}</p>
    </div>

    <div v-if="existingOpening" class="mb-4 rounded-md border border-[hsl(var(--warning)/0.4)] bg-[hsl(var(--warning)/0.1)] p-4 text-sm">
      <p>
        {{ t('opening_balances_already_exists', { reference: existingOpening.reference, date: formatDate(existingOpening.date) }) }}
      </p>
    </div>

    <!-- Step 4.1: Scenario guide -->
    <div class="mb-4 rounded-md border border-[hsl(var(--border))] bg-[hsl(var(--muted)/0.3)]">
      <button
        type="button"
        class="flex w-full items-center justify-between px-4 py-3 text-sm font-medium text-left"
        @click="guideOpen = !guideOpen"
      >
        <span>{{ t('opening_balances_scenario_guide_title') }}</span>
        <ChevronUp v-if="guideOpen" class="h-4 w-4 text-[hsl(var(--muted-foreground))]" />
        <ChevronDown v-else class="h-4 w-4 text-[hsl(var(--muted-foreground))]" />
      </button>
      <div v-if="guideOpen" class="grid gap-3 border-t border-[hsl(var(--border))] px-4 py-3 sm:grid-cols-2">
        <div class="rounded-md border border-[hsl(var(--border))] bg-[hsl(var(--background))] p-3">
          <p class="text-sm font-semibold">{{ t('opening_balances_scenario_fresh_title') }}</p>
          <p class="mt-1 text-sm text-[hsl(var(--muted-foreground))]">{{ t('opening_balances_scenario_fresh_desc') }}</p>
        </div>
        <div class="rounded-md border border-[hsl(var(--border))] bg-[hsl(var(--background))] p-3">
          <p class="text-sm font-semibold">{{ t('opening_balances_scenario_migrating_title') }}</p>
          <p class="mt-1 text-sm text-[hsl(var(--muted-foreground))]">{{ t('opening_balances_scenario_migrating_desc') }}</p>
        </div>
      </div>
    </div>

    <form @submit.prevent="submit">
      <Card class="mb-4">
        <CardHeader><CardTitle>{{ t('entry_header') }}</CardTitle></CardHeader>
        <CardContent>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <FormInput
              id="date"
              v-model="form.date"
              type="date"
              :label="t('date')"
              :error="form.errors.date"
              :hint="t('opening_balances_date_hint')"
              required
            />
            <FormInput
              id="reference"
              v-model="form.reference"
              :label="t('reference')"
              :error="form.errors.reference"
              :placeholder="t('opening_balances_reference_placeholder')"
            />
            <FormInput
              id="description"
              v-model="form.description"
              :label="t('description')"
              :error="form.errors.description"
            />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>{{ t('opening_balances_accounts') }}</CardTitle></CardHeader>
        <CardContent>
          <div v-for="(group, type) in groupedAccounts" :key="type" class="mb-6 last:mb-0">
            <div v-if="group.length" class="mb-2 flex items-center gap-2">
              <h3 class="text-sm font-semibold">{{ typeLabels[type] }}</h3>
              <Badge variant="default">{{ group.length }}</Badge>
            </div>
            <table v-if="group.length" class="w-full text-sm">
              <thead>
                <tr class="border-b text-left text-[hsl(var(--muted-foreground))]">
                  <th class="pb-1 w-20">{{ t('account_code') }}</th>
                  <th class="pb-1">{{ t('account_name') }}</th>
                  <th class="pb-1 w-48 text-right">{{ t('amount') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="a in group" :key="a.id" class="border-b last:border-b-0">
                  <td class="py-2 font-mono">{{ a.code }}</td>
                  <td class="py-2">{{ a.name }}</td>
                  <td class="py-2 text-right">
                    <FormInput
                      :id="`balance_${a.index}`"
                      v-model="form.balances[a.index].amount"
                      type="number"
                      step="0.01"
                      :error="balanceError(a.index)"
                      placeholder="0.00"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="mt-6 rounded-md border border-[hsl(var(--border))] bg-[hsl(var(--muted)/0.4)] p-4 text-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <div class="text-[hsl(var(--muted-foreground))]">{{ t('opening_balances_filled') }}</div>
                <div class="text-base font-semibold">{{ filledCount }}</div>
              </div>
              <div>
                <div class="text-[hsl(var(--muted-foreground))]">{{ t('opening_balances_net_total') }}</div>
                <div class="text-base font-semibold">{{ formatCurrency(totalSigned) }}</div>
              </div>
              <p class="text-xs text-[hsl(var(--muted-foreground))] max-w-md">
                {{ t('opening_balances_contra_help') }}
              </p>
            </div>
          </div>

          <div v-if="form.errors.balances" class="mt-2 text-xs text-[hsl(var(--destructive))]">{{ form.errors.balances }}</div>

          <div class="mt-6 flex flex-wrap justify-end gap-2">
            <Link href="/accounting/journal-entries">
              <Button type="button" variant="outline">{{ t('cancel') }}</Button>
            </Link>
            <Button
              type="submit"
              :disabled="form.processing || filledCount === 0"
              :loading="form.processing"
            >
              {{ t('opening_balances_submit') }}
            </Button>
          </div>
        </CardContent>
      </Card>
    </form>

    <!-- Step 4.2: Historical summary entry -->
    <form v-if="equityAccounts.length" class="mt-6" @submit.prevent="submitHistorical">
      <Card>
        <CardHeader>
          <CardTitle>{{ t('historical_summary_card_title') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <p class="mb-4 text-sm text-[hsl(var(--muted-foreground))]">{{ t('historical_summary_desc') }}</p>

          <div v-if="existingHistorical" class="mb-4 rounded-md border border-[hsl(var(--warning)/0.4)] bg-[hsl(var(--warning)/0.1)] p-3 text-sm">
            {{ t('historical_summary_already_exists', { reference: existingHistorical.reference }) }}
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <FormInput
              id="hist_date"
              v-model="historicalForm.date"
              type="date"
              :label="t('date')"
              :error="historicalForm.errors.date"
              required
            />
            <div>
              <label for="hist_account" class="mb-1 block text-sm font-medium">{{ t('historical_summary_account') }}</label>
              <select
                id="hist_account"
                v-model="historicalForm.account_id"
                class="w-full rounded-md border border-[hsl(var(--border))] bg-[hsl(var(--background))] px-3 py-2 text-sm"
              >
                <option v-for="a in equityAccounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
              </select>
              <p v-if="historicalForm.errors.account_id" class="mt-1 text-xs text-[hsl(var(--destructive))]">{{ historicalForm.errors.account_id }}</p>
            </div>
            <FormInput
              id="hist_amount"
              v-model="historicalForm.amount"
              type="number"
              step="0.01"
              :label="t('historical_summary_amount')"
              :hint="t('historical_summary_amount_hint')"
              :error="historicalForm.errors.amount"
              placeholder="0.00"
              required
            />
            <FormInput
              id="hist_reference"
              v-model="historicalForm.reference"
              :label="t('reference')"
              :error="historicalForm.errors.reference"
              :placeholder="t('historical_summary_reference_placeholder')"
            />
          </div>

          <div class="mt-4 flex justify-end">
            <Button
              type="submit"
              :disabled="historicalForm.processing || !historicalForm.amount"
              :loading="historicalForm.processing"
            >
              {{ t('opening_balances_submit') }}
            </Button>
          </div>
        </CardContent>
      </Card>
    </form>
  </AppLayout>
</template>
