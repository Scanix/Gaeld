<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Badge from '@/Components/UI/Badge.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import { formatCurrency, formatDate } from '@/lib/utils'

defineProps({ entries: Object })

const columns = [
  { key: 'date', label: 'Date', format: v => formatDate(v) },
  { key: 'reference', label: 'Reference' },
  { key: 'description', label: 'Description' },
  { key: 'is_posted', label: 'Status' },
]
</script>

<template>
  <AppLayout title="Journal Entries" help-page="accounting-basics">
    <Card>
      <CardHeader><CardTitle>Journal Entries</CardTitle></CardHeader>
      <CardContent>
        <DataTable :columns="columns" :rows="entries">
          <template #cell-is_posted="{ value }">
            <Badge :variant="value ? 'success' : 'warning'">{{ value ? 'Posted' : 'Draft' }}</Badge>
          </template>
        </DataTable>

        <!-- Expanded lines for each entry -->
        <div v-for="entry in (entries.data || entries)" :key="entry.id" class="mt-4 border rounded-md p-3" v-if="entry.lines?.length">
          <p class="mb-2 text-sm font-medium">{{ entry.reference }} — {{ entry.description }}</p>
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b text-left text-muted-foreground">
                <th class="pb-1">Account</th>
                <th class="pb-1 text-right">Debit</th>
                <th class="pb-1 text-right">Credit</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in entry.lines" :key="line.id">
                <td>{{ line.account?.code }} — {{ line.account?.name }}</td>
                <td class="text-right">{{ formatCurrency(line.debit) }}</td>
                <td class="text-right">{{ formatCurrency(line.credit) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </CardContent>
    </Card>
  </AppLayout>
</template>
