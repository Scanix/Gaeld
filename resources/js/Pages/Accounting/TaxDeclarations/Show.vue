<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Badge from '@/Components/UI/Badge.vue'
import Button from '@/Components/UI/Button.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { router, Link } from '@inertiajs/vue3'
import { ArrowLeft, Lock } from 'lucide-vue-next'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'

const props = defineProps({
  declaration: Object,
})

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

const statusVariant = {
  draft: 'default',
  finalized: 'success',
  submitted: 'info',
}

function finalize() {
  router.post(`/accounting/tax-declarations/${props.declaration.id}/finalize`, {}, {
    preserveScroll: true,
  })
}

const dataColumns = [
  { key: 'label', label: t('description') },
  { key: 'value', label: t('amount'), class: 'text-right', format: v => typeof v === 'number' ? formatCurrency(v) : v },
]

const declarationKeyOrder = [
  'revenue',
  'expenses',
  'profit',
  'net_result',
  'assets',
  'liabilities',
  'equity',
  'vat_payable_estimate',
]

function formatDeclarationLabel(key) {
  return key
    .split('_')
    .map(part => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}

function dataRows() {
  const data = props.declaration.data
  if (!data || typeof data !== 'object') return []

  const entries = Object.entries(data)
  entries.sort(([a], [b]) => {
    const aIndex = declarationKeyOrder.indexOf(a)
    const bIndex = declarationKeyOrder.indexOf(b)

    if (aIndex === -1 && bIndex === -1) return a.localeCompare(b)
    if (aIndex === -1) return 1
    if (bIndex === -1) return -1

    return aIndex - bIndex
  })

  return entries.map(([key, value]) => ({
    label: formatDeclarationLabel(key),
    value,
  }))
}
</script>

<template>
  <AppLayout :title="`${t('tax_declaration')} — ${declaration.fiscal_year} (${declaration.canton})`">
    <Breadcrumb :items="[{ label: t('tax_declarations'), href: '/accounting/tax-declarations' }, { label: `${declaration.fiscal_year} (${declaration.canton})` }]" class="mb-4" />

    <div class="space-y-6">
      <Card>
        <CardHeader>
          <div class="flex items-center justify-between">
            <CardTitle>
              {{ t('tax_declaration') }} — {{ declaration.fiscal_year }} ({{ declaration.canton }})
            </CardTitle>
            <Badge :variant="statusVariant[declaration.status] || 'default'">
              {{ t(`tax_declaration_status_${declaration.status}`) }}
            </Badge>
          </div>
        </CardHeader>
        <CardContent>
          <DataTable :columns="dataColumns" :rows="dataRows()" />

          <div v-if="declaration.status === 'draft'" class="mt-6 flex justify-end">
            <Button @click="finalize">
              <Lock class="mr-1 h-4 w-4" /> {{ t('finalize') }}
            </Button>
          </div>

          <p v-if="declaration.finalized_at" class="mt-4 text-sm text-[hsl(var(--muted-foreground))]">
            {{ t('tax_declaration_status_finalized') }}: {{ declaration.finalized_at }}
          </p>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
