<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { subscriptionStatusClass } from '@/lib/statusClasses'
import { TrendingUp, Users, AlertCircle, CreditCard, Clock, ShieldCheck, Ban, ArrowRightLeft, Settings, Save, ExternalLink, MessageSquare, Trash2 } from 'lucide-vue-next'
import { router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

const props = defineProps({
  stats: { type: Object, default: () => ({}) },
  plans: { type: Array, default: () => [] },
  subscriptions: { type: Array, default: () => [] },
  unsubscribed_orgs: { type: Array, default: () => [] },
  horizon_url: { type: String, default: '/horizon' },
  system_message: { type: String, default: null },
})

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

// Plan configuration (Stripe price IDs)
const planForms = ref({})

function initPlanForm(plan) {
  if (!planForms.value[plan.id]) {
    planForms.value[plan.id] = { stripe_price_id: plan.stripe_price_id ?? '' }
  }
  return planForms.value[plan.id]
}

const savingPlan = ref({})

function updatePlan(plan) {
  const form = planForms.value[plan.id]
  if (!form) return
  savingPlan.value[plan.id] = true
  router.put(`/saas-admin/plans/${plan.id}`, {
    stripe_price_id: form.stripe_price_id || null,
  }, {
    preserveScroll: true,
    onFinish: () => { savingPlan.value[plan.id] = false },
  })
}

// System message
const messageInput = ref(props.system_message ?? '')
const savingMessage = ref(false)

function setSystemMessage() {
  if (!messageInput.value.trim()) return
  savingMessage.value = true
  router.post('/saas-admin/system-message', { message: messageInput.value }, {
    onFinish: () => { savingMessage.value = false },
  })
}

function clearSystemMessage() {
  savingMessage.value = true
  router.delete('/saas-admin/system-message', {
    onFinish: () => { savingMessage.value = false, messageInput.value = '' },
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

      <!-- Quick Links + System Message -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Quick Links -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <ExternalLink class="h-4 w-4" />
              {{ t('quick_links') }}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div class="flex flex-wrap gap-2">
              <a
                :href="horizon_url"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-2 rounded-md border border-[hsl(var(--border))] px-3 py-1.5 text-sm font-medium text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))] transition-colors"
              >
                <svg class="h-4 w-4 text-[hsl(var(--primary))]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                {{ t('horizon_dashboard') }}
                <ExternalLink class="h-3 w-3 opacity-50" />
              </a>
            </div>
          </CardContent>
        </Card>

        <!-- System message -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <MessageSquare class="h-4 w-4" />
              {{ t('system_message') }}
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-3">
            <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('system_message_desc') }}</p>
            <div v-if="system_message" class="rounded-md bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 px-3 py-2 text-sm text-blue-800 dark:text-blue-200">
              {{ system_message }}
            </div>
            <div class="flex gap-2">
              <input
                v-model="messageInput"
                type="text"
                :placeholder="t('system_message_placeholder')"
                maxlength="500"
                class="flex-1 text-sm border border-[hsl(var(--border))] rounded-md px-3 py-1.5 bg-[hsl(var(--background))] text-[hsl(var(--foreground))] placeholder:text-[hsl(var(--muted-foreground))]"
              />
              <Button size="sm" @click="setSystemMessage" :disabled="!messageInput.trim() || savingMessage">
                <Save class="h-3 w-3" />
              </Button>
              <Button v-if="system_message" size="sm" variant="outline" @click="clearSystemMessage" :disabled="savingMessage">
                <Trash2 class="h-3 w-3" />
              </Button>
            </div>
          </CardContent>
        </Card>
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
                <p class="text-2xl font-bold tabular-nums">{{ formatCurrency(stats.mrr_chf) }}</p>
                <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('mrr') }}</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Plans breakdown & configuration -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Settings class="h-4 w-4" />
            {{ t('plans_overview') }}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div v-for="plan in plans" :key="plan.id" class="rounded-lg border border-[hsl(var(--border))] p-4 space-y-3">
              <div class="flex items-center justify-between">
                <span class="font-semibold">{{ plan.name }}</span>
                <span class="text-sm text-[hsl(var(--muted-foreground))]">
                  {{ plan.price_chf > 0 ? `${formatCurrency(plan.price_chf)}/${t('month').toLowerCase().slice(0,2)}` : t('free') }}
                </span>
              </div>
              <div class="flex items-baseline gap-1">
                <span class="text-2xl font-bold tabular-nums">{{ plan.active_count }}</span>
                <span class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('active_subscriptions').toLowerCase() }}</span>
              </div>
              <div class="border-t border-[hsl(var(--border))] pt-3">
                <label class="block text-xs font-medium text-[hsl(var(--muted-foreground))] mb-1">{{ t('stripe_price_id') }}</label>
                <div class="flex gap-2">
                  <input
                    v-model="initPlanForm(plan).stripe_price_id"
                    type="text"
                    placeholder="price_..."
                    class="flex-1 text-xs border border-[hsl(var(--border))] rounded-md px-2 py-1.5 bg-[hsl(var(--background))] text-[hsl(var(--foreground))] placeholder:text-[hsl(var(--muted-foreground))] font-mono"
                  />
                  <Button size="sm" variant="outline" @click="updatePlan(plan)" :disabled="savingPlan[plan.id]">
                    <Save class="h-3 w-3" />
                  </Button>
                </div>
                <p v-if="!initPlanForm(plan).stripe_price_id" class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                  ⚠ {{ t('checkout_disabled_no_stripe') }}
                </p>
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
                    <span :class="subscriptionStatusClass[sub.status]" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium capitalize">
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
