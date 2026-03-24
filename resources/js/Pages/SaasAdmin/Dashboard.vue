<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import { useTranslations } from '@/lib/useTranslations'
import { TrendingUp, Users, AlertCircle, CreditCard, Clock } from 'lucide-vue-next'

const { t } = useTranslations()

const props = defineProps({
  stats: { type: Object, default: () => ({}) },
  plans: { type: Array, default: () => [] },
  subscriptions: { type: Array, default: () => [] },
})

const statusClass = {
  active: 'text-[hsl(var(--primary))] bg-[hsl(var(--accent))]',
  trialing: 'text-blue-600 bg-blue-50',
  past_due: 'text-[hsl(var(--destructive))] bg-[hsl(var(--destructive)/0.08)]',
  canceled: 'text-[hsl(var(--muted-foreground))] bg-[hsl(var(--muted))]',
  paused: 'text-yellow-700 bg-yellow-50',
}
</script>

<template>
  <AppLayout :title="t('saas_admin')">
    <div class="space-y-6">

      <!-- KPI row -->
      <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <Card>
          <CardContent class="pt-6">
            <div class="flex items-start gap-3">
              <Users class="h-5 w-5 text-[hsl(var(--muted-foreground))] mt-0.5" />
              <div>
                <p class="text-2xl font-bold">{{ stats.total_orgs }}</p>
                <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('total_orgs') }}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent class="pt-6">
            <div class="flex items-start gap-3">
              <CreditCard class="h-5 w-5 text-[hsl(var(--primary))] mt-0.5" />
              <div>
                <p class="text-2xl font-bold">{{ stats.active_subscriptions }}</p>
                <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('active_subscriptions') }}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent class="pt-6">
            <div class="flex items-start gap-3">
              <Clock class="h-5 w-5 text-blue-500 mt-0.5" />
              <div>
                <p class="text-2xl font-bold">{{ stats.trialing }}</p>
                <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('trialing') }}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent class="pt-6">
            <div class="flex items-start gap-3">
              <AlertCircle class="h-5 w-5 text-[hsl(var(--destructive))] mt-0.5" />
              <div>
                <p class="text-2xl font-bold">{{ stats.past_due }}</p>
                <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('past_due') }}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent class="pt-6">
            <div class="flex items-start gap-3">
              <TrendingUp class="h-5 w-5 text-[hsl(var(--primary))] mt-0.5" />
              <div>
                <p class="text-2xl font-bold">{{ stats.mrr_chf }}</p>
                <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('mrr') }}</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Plans breakdown -->
      <Card>
        <CardHeader><CardTitle>{{ t('billing') }}</CardTitle></CardHeader>
        <CardContent>
          <div class="divide-y divide-[hsl(var(--border))]">
            <div v-for="plan in plans" :key="plan.name" class="flex justify-between items-center py-3">
              <span class="font-medium">{{ plan.name }}</span>
              <div class="flex items-center gap-4 text-sm text-[hsl(var(--muted-foreground))]">
                <span>CHF {{ plan.price_chf }}/mo</span>
                <span class="font-semibold text-[hsl(var(--foreground))]">{{ plan.active_count }} {{ t('active_subscriptions').toLowerCase() }}</span>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Subscriptions table -->
      <Card>
        <CardHeader><CardTitle>{{ t('all_subscriptions') }}</CardTitle></CardHeader>
        <CardContent>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-[hsl(var(--border))]">
                  <th class="text-left py-2 px-3 font-medium text-[hsl(var(--muted-foreground))]">{{ t('organization') }}</th>
                  <th class="text-left py-2 px-3 font-medium text-[hsl(var(--muted-foreground))]">{{ t('billing') }}</th>
                  <th class="text-left py-2 px-3 font-medium text-[hsl(var(--muted-foreground))]">{{ t('status') }}</th>
                  <th class="text-left py-2 px-3 font-medium text-[hsl(var(--muted-foreground))]">{{ t('trial_ends') }}</th>
                  <th class="text-left py-2 px-3 font-medium text-[hsl(var(--muted-foreground))]">{{ t('created_at') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="sub in subscriptions" :key="sub.id" class="border-b border-[hsl(var(--border))] hover:bg-[hsl(var(--accent)/0.5)]">
                  <td class="py-2 px-3 font-medium">{{ sub.org_name }}</td>
                  <td class="py-2 px-3 text-[hsl(var(--muted-foreground))]">{{ sub.plan }}</td>
                  <td class="py-2 px-3">
                    <span :class="statusClass[sub.status]" class="px-2 py-0.5 rounded-full text-xs font-medium capitalize">
                      {{ t(`subscription_status_${sub.status}`) }}
                    </span>
                  </td>
                  <td class="py-2 px-3 text-[hsl(var(--muted-foreground))]">{{ sub.trial_ends_at ?? '—' }}</td>
                  <td class="py-2 px-3 text-[hsl(var(--muted-foreground))]">{{ sub.created_at }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>

    </div>
  </AppLayout>
</template>
