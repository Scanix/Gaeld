<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Badge from '@/Components/UI/Badge.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import { ArrowLeftRight } from 'lucide-vue-next'
import { useTranslations } from '@/lib/useTranslations'
import { formatCurrency } from '@/lib/utils'
import { computed } from 'vue'
import HelpText from '@/Components/HelpText.vue'

const props = defineProps({
  bankAccounts: { type: Array, default: () => [] },
  features: { type: Object, default: () => ({}) },
})

const { t } = useTranslations()

const columns = computed(() => [
  { key: 'name', label: t('account_name') },
  { key: 'iban', label: t('iban'), format: v => v || '—' },
  { key: 'balance', label: t('balance'), class: 'text-right', format: v => formatCurrency(v) },
  { key: 'unreconciled_count', label: t('unreconciled') || 'Unreconciled', class: 'text-center' },
])
</script>

<template>
  <AppLayout :title="t('reconciliation') || 'Reconciliation'">
    <HelpText :title="t('help_reconciliation_title')" class="mb-6">
      <p>{{ t('help_reconciliation_text') }}</p>
    </HelpText>

    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-semibold">{{ t('reconciliation') || 'Reconciliation' }}</h2>
    </div>

    <Card v-if="bankAccounts.length">
      <CardHeader>
        <CardTitle>{{ t('bank_accounts') || 'Bank Accounts' }}</CardTitle>
      </CardHeader>
      <CardContent>
        <DataTable
          :columns="columns"
          :rows="bankAccounts"
          :row-link="row => `/reconciliation/${row.id}`"
        >
          <template #cell-unreconciled_count="{ value }">
            <Badge v-if="value > 0" variant="destructive">{{ value }}</Badge>
            <Badge v-else variant="default">0</Badge>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <Card v-else>
      <CardContent class="flex flex-col items-center justify-center py-12">
        <ArrowLeftRight class="mb-4 h-12 w-12 text-muted-foreground" />
        <p class="text-muted-foreground">{{ t('no_bank_accounts') || 'No bank accounts found. Create a bank account first.' }}</p>
      </CardContent>
    </Card>
  </AppLayout>
</template>
