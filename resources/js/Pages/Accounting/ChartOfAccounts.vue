<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Badge from '@/Components/UI/Badge.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import { useTranslations } from '@/lib/useTranslations'
import { computed } from 'vue'

defineProps({ accounts: Array })

const { t } = useTranslations()

const typeVariant = {
  asset: 'info',
  liability: 'warning',
  equity: 'default',
  revenue: 'success',
  expense: 'destructive',
}

const columns = computed(() => [
  { key: 'code', label: t('code') },
  { key: 'name', label: t('name') },
  { key: 'type', label: t('type') },
  { key: 'is_active', label: t('active'), format: v => v ? t('yes') : t('no') },
])
</script>

<template>
  <AppLayout :title="t('chart_of_accounts')" help-page="accounting-basics">
    <Card>
      <CardHeader><CardTitle>{{ t('chart_of_accounts') }}</CardTitle></CardHeader>
      <CardContent>
        <DataTable :columns="columns" :rows="accounts">
          <template #cell-type="{ value }">
            <Badge :variant="typeVariant[value] || 'default'">{{ value }}</Badge>
          </template>
        </DataTable>
      </CardContent>
    </Card>
  </AppLayout>
</template>
