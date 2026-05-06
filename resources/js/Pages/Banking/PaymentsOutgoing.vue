<script setup>
import { useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import EmptyState from '@/Components/UI/EmptyState.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import { Banknote, Download } from 'lucide-vue-next'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'

const props = defineProps({
  expenses: { type: Array, default: () => [] },
  bankAccounts: { type: Array, default: () => [] },
})

const { t } = useTranslations()
const { formatCurrency, formatDate } = useFormatters()

const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10)

const form = useForm({
  bank_account_id: props.bankAccounts[0]?.id ?? '',
  expense_ids: [],
  execution_date: tomorrow,
})

const bankAccountOptions = computed(() =>
  props.bankAccounts.map(ba => ({
    value: ba.id,
    label: `${ba.name} — ${ba.iban} (${ba.currency})`,
  })),
)

const selectedExpenses = computed(() =>
  props.expenses.filter(e => form.expense_ids.includes(e.id)),
)

const totalsByCurrency = computed(() => {
  const totals = {}
  for (const e of selectedExpenses.value) {
    totals[e.currency] = (totals[e.currency] ?? 0) + Number(e.amount)
  }
  return totals
})

const allSelected = computed(
  () => props.expenses.length > 0 && form.expense_ids.length === props.expenses.length,
)

function toggleAll() {
  form.expense_ids = allSelected.value ? [] : props.expenses.map(e => e.id)
}

const hasMultipleCurrencies = computed(() => Object.keys(totalsByCurrency.value).length > 1)

function submit() {
  if (hasMultipleCurrencies.value) return
  form.post('/payments/outgoing/download', {
    preserveScroll: true,
  })
}
</script>

<template>
  <AppLayout :title="t('payments_outgoing')">
    <PageHeader :title="t('payments_outgoing')">
      <template #description>
        <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('payments_outgoing_help') }}</p>
      </template>
    </PageHeader>

    <Card v-if="!bankAccounts.length">
      <CardContent class="py-10">
        <EmptyState
          :icon="Banknote"
          :title="t('payments_outgoing_no_bank_title')"
          :description="t('payments_outgoing_no_bank_description')"
        />
      </CardContent>
    </Card>

    <template v-else>
      <Card v-if="!expenses.length">
        <CardContent class="py-10">
          <EmptyState
            :icon="Banknote"
            :title="t('payments_outgoing_empty_title')"
            :description="t('payments_outgoing_empty_description')"
          />
        </CardContent>
      </Card>

      <div v-else class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <Card>
          <CardContent class="pt-6">
            <table class="w-full text-sm">
              <thead class="text-left text-xs uppercase text-[hsl(var(--muted-foreground))]">
                <tr>
                  <th class="w-8 pb-2">
                    <input type="checkbox" :checked="allSelected" @change="toggleAll" />
                  </th>
                  <th class="pb-2">{{ t('date') }}</th>
                  <th class="pb-2">{{ t('supplier') }}</th>
                  <th class="pb-2">{{ t('iban') }}</th>
                  <th class="pb-2">{{ t('description') }}</th>
                  <th class="pb-2 text-right">{{ t('amount') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[hsl(var(--border))]">
                <tr
                  v-for="e in expenses"
                  :key="e.id"
                  class="cursor-pointer transition-colors hover:bg-[hsl(var(--muted))]"
                  @click="
                    form.expense_ids = form.expense_ids.includes(e.id)
                      ? form.expense_ids.filter(id => id !== e.id)
                      : [...form.expense_ids, e.id]
                  "
                >
                  <td class="py-2">
                    <input type="checkbox" :checked="form.expense_ids.includes(e.id)" @click.stop />
                  </td>
                  <td class="py-2">{{ formatDate(e.date) }}</td>
                  <td class="py-2">
                    {{ e.supplier?.name }}
                    <Badge v-if="!e.supplier?.iban" variant="outline" class="ml-1 text-[10px]">
                      {{ t('no_iban') }}
                    </Badge>
                  </td>
                  <td class="py-2 font-mono text-xs">{{ e.supplier?.iban || '—' }}</td>
                  <td class="py-2">{{ e.description }}</td>
                  <td class="py-2 text-right font-medium">
                    {{ formatCurrency(Number(e.amount), e.currency) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </CardContent>
        </Card>

        <Card>
          <CardContent class="space-y-4 pt-6">
            <h3 class="text-sm font-semibold">{{ t('payments_outgoing_summary') }}</h3>

            <FormSelect
              id="bank_account_id"
              v-model="form.bank_account_id"
              :label="t('debtor_account')"
              :options="bankAccountOptions"
              :error="form.errors.bank_account_id"
            />

            <FormInput
              id="execution_date"
              v-model="form.execution_date"
              type="date"
              :label="t('execution_date')"
              :error="form.errors.execution_date"
            />

            <div class="space-y-1 border-t border-[hsl(var(--border))] pt-3 text-sm">
              <div class="flex justify-between">
                <span>{{ t('selected_count') }}</span>
                <span class="font-medium">{{ form.expense_ids.length }}</span>
              </div>
              <div
                v-for="(total, currency) in totalsByCurrency"
                :key="currency"
                class="flex justify-between"
              >
                <span>{{ t('total') }} ({{ currency }})</span>
                <span class="font-semibold">{{ formatCurrency(total, currency) }}</span>
              </div>
            </div>

            <p v-if="hasMultipleCurrencies" class="rounded bg-amber-50 p-2 text-xs text-amber-800">
              {{ t('payments_outgoing_single_currency') }}
            </p>

            <Button
              class="w-full"
              :disabled="!form.expense_ids.length || form.processing || hasMultipleCurrencies"
              :loading="form.processing"
              @click="submit"
            >
              <Download class="mr-2 h-4 w-4" />
              {{ t('download_pain001') }}
            </Button>
          </CardContent>
        </Card>
      </div>
    </template>
  </AppLayout>
</template>
