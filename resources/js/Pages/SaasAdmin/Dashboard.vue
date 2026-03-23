<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import { TrendingUp, Users, AlertCircle, CreditCard } from 'lucide-vue-next'
import { formatCurrency } from '@/lib/utils'

const props = defineProps({
  stats: { type: Object, default: () => ({}) },
  plans: { type: Array, default: () => [] },
  subscriptions: { type: Array, default: () => [] },
})

const subscriptionColumns = [
  { key: 'org_name', label: 'Organization' },
  { key: 'plan', label: 'Plan' },
  { key: 'status', label: 'Status' },
  { key: 'trial_ends_at', label: 'Trial ends' },
  { key: 'created_at', label: 'Since' },
]

const statusColor = {
  active: 'text-green-600 bg-green-50',
  trialing: 'text-blue-600 bg-blue-50',
  past_due: 'text-red-600 bg-red-50',
  canceled: 'text-gray-500 bg-gray-100',
  paused: 'text-yellow-600 bg-yellow-50',
}
</script>

<template>
  <AppLayout title="SaaS Admin">
    <div class="space-y-6">
      <h1 class="text-2xl font-bold text-gray-900">SaaS Admin Dashboard</h1>

      <!-- KPI row -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <Card>
          <CardContent class="pt-6">
            <div class="flex items-center gap-3">
              <Users class="h-8 w-8 text-gray-400" />
              <div>
                <p class="text-2xl font-bold">{{ stats.total_orgs }}</p>
                <p class="text-xs text-gray-500">Total organizations</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent class="pt-6">
            <div class="flex items-center gap-3">
              <CreditCard class="h-8 w-8 text-green-400" />
              <div>
                <p class="text-2xl font-bold">{{ stats.active_subscriptions }}</p>
                <p class="text-xs text-gray-500">Active subscriptions</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent class="pt-6">
            <div class="flex items-center gap-3">
              <TrendingUp class="h-8 w-8 text-blue-400" />
              <div>
                <p class="text-2xl font-bold">CHF {{ stats.mrr_chf }}</p>
                <p class="text-xs text-gray-500">Monthly Recurring Revenue</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent class="pt-6">
            <div class="flex items-center gap-3">
              <AlertCircle class="h-8 w-8 text-red-400" />
              <div>
                <p class="text-2xl font-bold">{{ stats.past_due }}</p>
                <p class="text-xs text-gray-500">Past due</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Plans breakdown -->
      <Card>
        <CardHeader><CardTitle>Plans breakdown</CardTitle></CardHeader>
        <CardContent>
          <div class="grid grid-cols-2 gap-4">
            <div v-for="plan in plans" :key="plan.name" class="flex justify-between items-center py-2 border-b last:border-0">
              <span class="font-medium">{{ plan.name }}</span>
              <span class="text-gray-600">{{ plan.active_count }} active</span>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Subscriptions table -->
      <Card>
        <CardHeader><CardTitle>All subscriptions</CardTitle></CardHeader>
        <CardContent>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b">
                  <th v-for="col in subscriptionColumns" :key="col.key"
                      class="text-left py-2 px-3 font-medium text-gray-600">{{ col.label }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="sub in subscriptions" :key="sub.id" class="border-b hover:bg-gray-50">
                  <td class="py-2 px-3 font-medium">{{ sub.org_name }}</td>
                  <td class="py-2 px-3">{{ sub.plan }}</td>
                  <td class="py-2 px-3">
                    <span :class="statusColor[sub.status]" class="px-2 py-0.5 rounded-full text-xs font-medium capitalize">
                      {{ sub.status }}
                    </span>
                  </td>
                  <td class="py-2 px-3 text-gray-500">{{ sub.trial_ends_at ?? '—' }}</td>
                  <td class="py-2 px-3 text-gray-500">{{ sub.created_at }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
