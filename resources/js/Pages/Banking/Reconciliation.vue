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
import { useFormatters } from '@/lib/useFormatters'
import EmptyState from '@/Components/UI/EmptyState.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import { computed } from 'vue'
import HelpText from '@/Components/HelpText.vue'

const props = defineProps({
  bankAccounts: { type: Object, default: () => ({}) },
  pageFeatures: { type: Object, default: () => ({}) },
})

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

const columns = computed(() => [
  { key: 'name', label: t('account_name') },
  { key: 'iban', label: t('iban'), format: v => v || '—' },
  { key: 'balance', label: t('balance'), class: 'text-right', format: v => formatCurrency(v) },
  { key: 'unreconciled_count', label: t('unreconciled'), class: 'text-center' },
])
</script>

<template>
  <AppLayout :title="t('reconciliation')">
    <HelpText :title="t('help_reconciliation_title')" class="mb-6">
      <p>{{ t('help_reconciliation_text') }}</p>
    </HelpText>

    <PageHeader :title="t('reconciliation')" />

    <Card v-if="(bankAccounts?.data ?? []).length">
      <CardHeader>
        <CardTitle>{{ t('bank_accounts') }}</CardTitle>
      </CardHeader>
      <CardContent>
        <DataTable
          :columns="columns"
          :rows="bankAccounts?.data ?? []"
          :pagination="bankAccounts"
          :row-link="row => `/reconciliation/${row.uuid}`"
        >
          <template #cell-unreconciled_count="{ value }">
            <Badge v-if="value > 0" variant="destructive">{{ value }}</Badge>
            <Badge v-else variant="default">0</Badge>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <Card v-else>
      <CardContent class="pt-6">
        <EmptyState
          :icon="ArrowLeftRight"
          :title="t('empty_reconciliation_title')"
          :description="t('empty_reconciliation_desc')"
          :action-label="t('go_to_banking')"
          action-href="/banking"
        />
      </CardContent>
    </Card>
  </AppLayout>
</template>
