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
import { formatCurrency } from '@/lib/utils'
import { router, Link } from '@inertiajs/vue3'
import { ArrowLeft, Lock } from 'lucide-vue-next'

const props = defineProps({
  declaration: Object,
})

const { t } = useTranslations()

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

function dataRows() {
  const data = props.declaration.data
  if (!data || typeof data !== 'object') return []
  return Object.entries(data).map(([key, value]) => ({ label: key, value }))
}
</script>

<template>
  <AppLayout :title="`${t('tax_declaration')} — ${declaration.fiscal_year} (${declaration.canton})`">
    <div class="mb-4">
      <Link href="/accounting/tax-declarations" class="inline-flex items-center gap-1 text-sm text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]">
        <ArrowLeft class="h-4 w-4" /> {{ t('back') }}
      </Link>
    </div>

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
