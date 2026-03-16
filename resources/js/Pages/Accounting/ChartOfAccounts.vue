<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Badge from '@/Components/UI/Badge.vue'
import DataTable from '@/Components/UI/DataTable.vue'

defineProps({ accounts: Array })

const typeVariant = {
  asset: 'info',
  liability: 'warning',
  equity: 'default',
  revenue: 'success',
  expense: 'destructive',
}

const columns = [
  { key: 'code', label: 'Code' },
  { key: 'name', label: 'Name' },
  { key: 'type', label: 'Type' },
  { key: 'is_active', label: 'Active', format: v => v ? 'Yes' : 'No' },
]
</script>

<template>
  <AppLayout title="Chart of Accounts" help-page="accounting-basics">
    <Card>
      <CardHeader><CardTitle>Chart of Accounts</CardTitle></CardHeader>
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
