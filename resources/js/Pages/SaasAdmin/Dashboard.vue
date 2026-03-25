<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { useTranslations } from '@/lib/useTranslations'
import { TrendingUp, Users, AlertCircle, CreditCard, Clock, ShieldCheck, Ban, ArrowRightLeft } from 'lucide-vue-next'
import { router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const { t } = useTranslations()

const props = defineProps({
  stats: { type: Object, default: () => ({}) },
  plans: { type: Array, default: () => [] },
  subscriptions: { type: Array, default: () => [] },
  unsubscribed_orgs: { type: Array, default: () => [] },
})

const statusClass = {
  active: 'text-emerald-700 bg-emerald-50 dark:text-emerald-400 dark:bg-emerald-950/50',
  trialing: 'text-blue-700 bg-blue-50 dark:text-blue-400 dark:bg-blue-950/50',
  past_due: 'text-[hsl(var(--destructive))] bg-[hsl(var(--destructive)/0.08)]',
  canceled: 'text-[hsl(var(--muted-foreground))] bg-[hsl(var(--muted))]',
  paused: 'text-yellow-700 bg-yellow-50 dark:text-yellow-400 dark:bg-yellow-950/50',
}

const selectedPlan = ref({})
const processing = ref(false)

// Confirm dialog state
const showRevokeConfirm = ref(false)
const revokeTarget = ref(null)

const activeSubscriptions = computed(() => props.subscriptions.filter(s => s.status === 'active' || s.status === 'trialing'))
const canceledSubscriptions = computed(() => props.subscriptions.filter(s => s.status === 'canceled' || s.status === 'paused'))

function grantPlan(orgId) {
  const planId = selectedPlan.value[orgId]
  if (!planId) return
  processing.value = true
  router.post(`/saas-admin/${orgId}/grant-plan`, { plan_id: planId }, {
    onFinish: () => { processing.value = false },
  })
}

function changePlan(sub) {
  const planId = selectedPlan.value[sub.organization_id]
  if (!planId) return
  processing.value = true
  router.post(`/saas-admin/${sub.organization_id}/grant-plan`, { plan_id: planId }, {
    onFinish: () => { processing.value = false },
  })
}

function confirmRevoke(sub) {
  revokeTarget.value = sub
  showRevokeConfirm.value = true
}

function revokePlan() {
  if (!revokeTarget.value) return
  processing.value = true
  router.post(`/saas-admin/${revokeTarget.value.organization_id}/revoke-plan`, {}, {
    onFinish: () => {
      processing.value = false
      showRevokeConfirm.value = false
      revokeTarget.value = null
    },
  })
}
</script>

<template>
  <AppLayout :title="t('saas_admin')">
    <div class="space-y-6">

      <!-- Header -->
      <div class="flex items-center gap-3">
        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-[hsl(var(--primary)/0.1)]">
          <ShieldCheck class="h-5 w-5 text-[hsl(var(--primary))]" />
        </div>
        <div>
          <h1 class="text-xl font-bold">{{ t('saas_admin') }}</h1>
          <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('saas_admin_subtitle') }}</p>
        </div>
      </div>

      <!-- KPI row -->
      <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <Card>
          <CardContent class="pt-6">
            <div class="flex items-start gap-3">
              <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[hsl(var(--muted))]">
                <Users class="h-4 w-4 text-[hsl(var(--muted-foreground))]" />
              </div>
              <div>
                <p class="text-2xl font-bold tabular-nums">{{ stats.total_orgs }}</p>
                <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('total_orgs') }}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent class="pt-6">
            <div class="flex items-start gap-3">
              <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-950/50">
                <CreditCard class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
              </div>
              <div>
                <p class="text-2xl font-bold tabular-nums">{{ stats.active_subscriptions }}</p>
                <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('active_subscriptions') }}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent class="pt-6">
            <div class="flex items-start gap-3">
              <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-950/50">
                <Clock class="h-4 w-4 text-blue-600 dark:text-blue-400" />
              </div>
              <div>
                <p class="text-2xl font-bold tabular-nums">{{ stats.trialing }}</p>
                <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('trialing') }}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent class="pt-6">
            <div class="flex items-start gap-3">
              <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[hsl(var(--destructive)/0.1)]">
                <AlertCircle class="h-4 w-4 text-[hsl(var(--destructive))]" />
              </div>
              <div>
                <p class="text-2xl font-bold tabular-nums">{{ stats.past_due }}</p>
                <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('past_due') }}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent class="pt-6">
            <div class="flex items-start gap-3">
              <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[hsl(var(--primary)/0.1)]">
                <TrendingUp class="h-4 w-4 text-[hsl(var(--primary))]" />
              </div>
              <div>
                <p class="text-2xl font-bold tabular-nums">CHF {{ stats.mrr_chf }}</p>
                <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('mrr') }}</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Plans breakdown -->
      <Card>
        <CardHeader>
          <CardTitle>{{ t('plans_overview') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div v-for="plan in plans" :key="plan.id" class="rounded-lg border border-[hsl(var(--border))] p-4">
              <div class="flex items-center justify-between">
                <span class="font-semibold">{{ plan.name }}</span>
                <span class="text-sm text-[hsl(var(--muted-foreground))]">
                  {{ plan.price_chf > 0 ? `CHF ${plan.price_chf}/mo` : t('free') }}
                </span>
              </div>
              <div class="mt-2 flex items-baseline gap-1">
                <span class="text-2xl font-bold tabular-nums">{{ plan.active_count }}</span>
                <span class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('active_subscriptions').toLowerCase() }}</span>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Active Subscriptions table -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <CreditCard class="h-4 w-4" />
            {{ t('active_subscriptions') }}
            <span class="ml-1 text-sm font-normal text-[hsl(var(--muted-foreground))]">({{ activeSubscriptions.length }})</span>
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div v-if="activeSubscriptions.length" class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-[hsl(var(--border))]">
                  <th class="text-left py-2.5 px-3 font-medium text-[hsl(var(--muted-foreground))]">{{ t('organization') }}</th>
                  <th class="text-left py-2.5 px-3 font-medium text-[hsl(var(--muted-foreground))]">{{ t('billing') }}</th>
                  <th class="text-left py-2.5 px-3 font-medium text-[hsl(var(--muted-foreground))]">{{ t('status') }}</th>
                  <th class="text-left py-2.5 px-3 font-medium text-[hsl(var(--muted-foreground))]">{{ t('since') }}</th>
                  <th class="text-right py-2.5 px-3 font-medium text-[hsl(var(--muted-foreground))]">{{ t('actions') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="sub in activeSubscriptions" :key="sub.id" class="border-b border-[hsl(var(--border))] last:border-0 hover:bg-[hsl(var(--accent)/0.3)]">
                  <td class="py-2.5 px-3 font-medium">{{ sub.org_name }}</td>
                  <td class="py-2.5 px-3 text-[hsl(var(--muted-foreground))]">{{ sub.plan }}</td>
                  <td class="py-2.5 px-3">
                    <span :class="statusClass[sub.status]" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium capitalize">
                      {{ sub.status }}
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-[hsl(var(--muted-foreground))] tabular-nums">{{ sub.created_at }}</td>
                  <td class="py-2.5 px-3">
                    <div class="flex items-center justify-end gap-2">
                      <select v-model="selectedPlan[sub.organization_id]" class="text-xs border border-[hsl(var(--border))] rounded-md px-2 py-1.5 bg-[hsl(var(--background))] text-[hsl(var(--foreground))]">
                        <option value="" disabled selected>{{ t('change_plan') }}…</option>
                        <option v-for="p in plans" :key="p.id" :value="p.id" :disabled="p.id === sub.plan_id">{{ p.name }}</option>
                      </select>
                      <Button size="sm" variant="outline" @click="changePlan(sub)" :disabled="!selectedPlan[sub.organization_id] || processing">
                        <ArrowRightLeft class="h-3 w-3" />
                      </Button>
                      <Button size="sm" variant="destructive" @click="confirmRevoke(sub)" :disabled="processing">
                        <Ban class="h-3 w-3" />
                      </Button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="text-sm text-[hsl(var(--muted-foreground))] py-4 text-center">{{ t('no_active_subscriptions') }}</p>
        </CardContent>
      </Card>

      <!-- Canceled subscriptions (collapsed) -->
      <Card v-if="canceledSubscriptions.length">
        <CardHeader>
          <CardTitle class="text-[hsl(var(--muted-foreground))]">
            {{ t('canceled_subscriptions') }}
            <span class="ml-1 text-sm font-normal">({{ canceledSubscriptions.length }})</span>
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-[hsl(var(--border))]">
                  <th class="text-left py-2.5 px-3 font-medium text-[hsl(var(--muted-foreground))]">{{ t('organization') }}</th>
                  <th class="text-left py-2.5 px-3 font-medium text-[hsl(var(--muted-foreground))]">{{ t('billing') }}</th>
                  <th class="text-left py-2.5 px-3 font-medium text-[hsl(var(--muted-foreground))]">{{ t('ended_at') }}</th>
                  <th class="text-right py-2.5 px-3 font-medium text-[hsl(var(--muted-foreground))]">{{ t('actions') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="sub in canceledSubscriptions" :key="sub.id" class="border-b border-[hsl(var(--border))] last:border-0 text-[hsl(var(--muted-foreground))]">
                  <td class="py-2.5 px-3 font-medium">{{ sub.org_name }}</td>
                  <td class="py-2.5 px-3">{{ sub.plan }}</td>
                  <td class="py-2.5 px-3 tabular-nums">{{ sub.ends_at ?? '—' }}</td>
                  <td class="py-2.5 px-3">
                    <div class="flex items-center justify-end gap-2">
                      <select v-model="selectedPlan[sub.organization_id]" class="text-xs border border-[hsl(var(--border))] rounded-md px-2 py-1.5 bg-[hsl(var(--background))] text-[hsl(var(--foreground))]">
                        <option value="" disabled selected>{{ t('reactivate') }}…</option>
                        <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                      </select>
                      <Button size="sm" variant="outline" @click="changePlan(sub)" :disabled="!selectedPlan[sub.organization_id] || processing">
                        {{ t('grant') }}
                      </Button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>

      <!-- Unsubscribed organizations -->
      <Card v-if="unsubscribed_orgs.length">
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Users class="h-4 w-4" />
            {{ t('unsubscribed_orgs') }}
            <span class="ml-1 text-sm font-normal text-[hsl(var(--muted-foreground))]">({{ unsubscribed_orgs.length }})</span>
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-[hsl(var(--border))]">
                  <th class="text-left py-2.5 px-3 font-medium text-[hsl(var(--muted-foreground))]">{{ t('organization') }}</th>
                  <th class="text-right py-2.5 px-3 font-medium text-[hsl(var(--muted-foreground))]">{{ t('actions') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="org in unsubscribed_orgs" :key="org.id" class="border-b border-[hsl(var(--border))] last:border-0 hover:bg-[hsl(var(--accent)/0.3)]">
                  <td class="py-2.5 px-3 font-medium">{{ org.name }}</td>
                  <td class="py-2.5 px-3">
                    <div class="flex items-center justify-end gap-2">
                      <select v-model="selectedPlan[org.id]" class="text-xs border border-[hsl(var(--border))] rounded-md px-2 py-1.5 bg-[hsl(var(--background))] text-[hsl(var(--foreground))]">
                        <option value="" disabled selected>{{ t('select_plan') }}…</option>
                        <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                      </select>
                      <Button size="sm" @click="grantPlan(org.id)" :disabled="!selectedPlan[org.id] || processing">
                        {{ t('grant') }}
                      </Button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>

    </div>

    <!-- Revoke confirmation dialog -->
    <ConfirmDialog
      :open="showRevokeConfirm"
      :title="t('revoke_subscription')"
      :message="t('revoke_subscription_confirm', { org: revokeTarget?.org_name })"
      :confirmLabel="t('revoke')"
      confirmVariant="destructive"
      :processing="processing"
      @confirm="revokePlan"
      @cancel="showRevokeConfirm = false; revokeTarget = null"
    />
  </AppLayout>
</template>
