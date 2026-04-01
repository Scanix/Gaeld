<script setup>
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import Button from '@/Components/UI/Button.vue'
import ExportDropdown from '@/Components/UI/ExportDropdown.vue'
import { useFormatters } from '@/lib/useFormatters'
import { useTranslations } from '@/lib/useTranslations'
import HelpText from '@/Components/HelpText.vue'
import EmptyState from '@/Components/UI/EmptyState.vue'
import { ref, computed } from 'vue'
import { Scale } from 'lucide-vue-next'

const props = defineProps({
  balances: Array,
  asOfDate: String,
})

const date = ref(props.asOfDate)

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

function applyFilter() {
  router.get('/accounting/trial-balance', { as_of_date: date.value }, { preserveState: true })
}

const columns = computed(() => [
  { key: 'account_code', label: t('code') },
  { key: 'account_name', label: t('account') },
  { key: 'debit', label: t('debit'), format: v => parseFloat(v) > 0 ? formatCurrency(v) : '' },
  { key: 'credit', label: t('credit'), format: v => parseFloat(v) > 0 ? formatCurrency(v) : '' },
])

const totalDebit = computed(() => (props.balances || []).reduce((s, b) => s + (parseFloat(b.debit) || 0), 0))
const totalCredit = computed(() => (props.balances || []).reduce((s, b) => s + (parseFloat(b.credit) || 0), 0))
</script>

<template>
  <AppLayout :title="t('trial_balance')" help-page="accounting-basics">
    <HelpText :title="t('help_trial_balance_title')" class="mb-6">
      <p>{{ t('help_trial_balance_text') }}</p>
    </HelpText>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div class="flex items-end gap-4">
        <FormInput id="as_of_date" v-model="date" type="date" :label="t('as_of_date')" />
        <Button @click="applyFilter">{{ t('apply') }}</Button>
      </div>
      <ExportDropdown base-url="/accounting/trial-balance/export" :params="{ as_of_date: date }" />
    </div>

    <Card>
      <CardHeader><CardTitle>{{ t('trial_balance') }}</CardTitle></CardHeader>
      <CardContent>
        <DataTable :columns="columns" :rows="balances || []">
          <template #empty>
            <EmptyState
              :icon="Scale"
              :title="t('empty_trial_balance_title')"
              :description="t('empty_trial_balance_desc')"
            />
          </template>
        </DataTable>
        <div class="mt-4 flex justify-between border-t pt-3 text-sm font-semibold">
          <span>{{ t('totals') }}</span>
          <div class="flex gap-12">
            <span>{{ formatCurrency(totalDebit) }}</span>
            <span>{{ formatCurrency(totalCredit) }}</span>
          </div>
        </div>
      </CardContent>
    </Card>
  </AppLayout>
</template>
