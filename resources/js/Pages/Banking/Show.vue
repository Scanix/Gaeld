<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import { formatCurrency, formatDate } from '@/lib/utils'
import { useTranslations } from '@/lib/useTranslations'
import { computed } from 'vue'

const props = defineProps({
  bankAccount: Object,
  transactions: Object,
})

const { t } = useTranslations()

const columns = computed(() => [
  { key: 'date', label: t('date'), format: (v) => formatDate(v) },
  { key: 'description', label: t('description') },
  { key: 'reference', label: t('reference'), format: (v) => v || '—' },
  { key: 'type', label: t('type') },
  { key: 'amount', label: t('amount'), class: 'text-right', format: (v) => formatCurrency(v) },
])
</script>

<template>
  <AppLayout :title="`${t('bank')} — ${bankAccount.name}`">
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <Button as="a" href="/banking" variant="outline" size="sm">← {{ t('back') }}</Button>
        <h2 class="text-xl font-semibold">{{ bankAccount.name }}</h2>
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3 mb-6">
      <Card>
        <CardHeader><CardTitle class="text-sm">{{ t('balance') }}</CardTitle></CardHeader>
        <CardContent>
          <p class="text-2xl font-bold">{{ formatCurrency(bankAccount.balance, bankAccount.currency) }}</p>
        </CardContent>
      </Card>
      <Card>
        <CardHeader><CardTitle class="text-sm">{{ t('iban') }}</CardTitle></CardHeader>
        <CardContent>
          <p class="text-sm font-mono">{{ bankAccount.iban || '—' }}</p>
        </CardContent>
      </Card>
      <Card>
        <CardHeader><CardTitle class="text-sm">{{ t('bank') }}</CardTitle></CardHeader>
        <CardContent>
          <p class="text-sm">{{ bankAccount.bank_name || '—' }}</p>
          <p class="text-xs text-[hsl(var(--muted-foreground))]" v-if="bankAccount.ledger_account">
            {{ t('ledger') }}: {{ bankAccount.ledger_account.code }} — {{ bankAccount.ledger_account.name }}
          </p>
        </CardContent>
      </Card>
    </div>

    <Card>
      <CardHeader><CardTitle>{{ t('transactions') }}</CardTitle></CardHeader>
      <CardContent>
        <DataTable
          :columns="columns"
          :rows="transactions?.data ?? []"
          :pagination="transactions"
          :empty-message="t('no_transactions_recorded')"
        >
          <template #cell-type="{ value }">
            <Badge :variant="value === 'credit' ? 'default' : 'secondary'">
              {{ value }}
            </Badge>
          </template>
        </DataTable>
      </CardContent>
    </Card>
  </AppLayout>
</template>
