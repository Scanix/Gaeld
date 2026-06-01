<script setup>
import { ref } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  organization: { type: Object, required: true },
  members: { type: Array, default: () => [] },
  subscriptions: { type: Array, default: () => [] },
  activity: { type: Object, default: () => ({}) },
})

const suspendReason = ref(props.organization.suspended_reason ?? '')
const showSuspend = ref(false)
const showDelete = ref(false)

function suspend() {
  router.post(`/saas-admin/${props.organization.id}/suspend`, {
    reason: suspendReason.value || null,
  }, {
    preserveScroll: true,
    onFinish: () => { showSuspend.value = false },
  })
}

function reactivate() {
  router.post(`/saas-admin/${props.organization.id}/reactivate`, {}, { preserveScroll: true })
}

function destroy() {
  router.delete(`/saas-admin/${props.organization.id}`, {
    onFinish: () => { showDelete.value = false },
  })
}
</script>

<template>
  <AppLayout :title="organization.name">
    <Head :title="organization.name" />

    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <Link href="/saas-admin" class="text-sm text-[hsl(var(--muted-foreground))] hover:underline">
            ← {{ t('back_to_saas_admin') }}
          </Link>
          <h1 class="mt-1 text-2xl font-bold">{{ organization.name }}</h1>
          <p class="text-sm text-[hsl(var(--muted-foreground))]">
            {{ organization.country }} · {{ organization.currency }} · {{ t('created') }}
            {{ organization.created_at?.slice(0, 10) }}
          </p>
        </div>

        <div class="flex items-center gap-2">
          <Badge v-if="organization.suspended_at" variant="destructive">{{ t('suspended') }}</Badge>
          <Button v-if="organization.suspended_at" size="sm" @click="reactivate">
            {{ t('reactivate') }}
          </Button>
          <Button v-else size="sm" variant="outline" @click="showSuspend = true">
            {{ t('suspend') }}
          </Button>
          <Button size="sm" variant="destructive" @click="showDelete = true">
            {{ t('delete') }}
          </Button>
        </div>
      </div>

      <Card v-if="organization.suspended_at">
        <CardContent class="pt-6">
          <p class="text-sm">
            <strong>{{ t('suspended_at') }}:</strong>
            {{ organization.suspended_at }}
          </p>
          <p v-if="organization.suspended_reason" class="mt-1 text-sm">
            <strong>{{ t('reason') }}:</strong>
            {{ organization.suspended_reason }}
          </p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{{ t('members') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-[hsl(var(--border))]">
                <th class="py-2 px-3 text-left">{{ t('name') }}</th>
                <th class="py-2 px-3 text-left">{{ t('email') }}</th>
                <th class="py-2 px-3 text-left">{{ t('role') }}</th>
                <th class="py-2 px-3 text-left">{{ t('last_login_at') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="m in members" :key="m.id" class="border-b border-[hsl(var(--border))] last:border-0">
                <td class="py-2 px-3 font-medium">{{ m.name }}</td>
                <td class="py-2 px-3">{{ m.email }}</td>
                <td class="py-2 px-3 capitalize">{{ m.role }}</td>
                <td class="py-2 px-3 text-[hsl(var(--muted-foreground))]">{{ m.last_login_at?.slice(0, 16).replace('T', ' ') }}</td>
              </tr>
            </tbody>
          </table>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{{ t('activity') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <ul class="space-y-1 text-sm">
            <li v-for="(stats, table) in activity" :key="table" class="flex justify-between border-b border-[hsl(var(--border))] py-1.5 last:border-0">
              <span class="font-medium">{{ table }}</span>
              <span class="tabular-nums">
                {{ stats.total }}
                <span v-if="stats.last_at" class="ml-2 text-[hsl(var(--muted-foreground))]">
                  · {{ String(stats.last_at).slice(0, 16).replace('T', ' ') }}
                </span>
              </span>
            </li>
          </ul>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{{ t('subscription_history') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-[hsl(var(--border))]">
                <th class="py-2 px-3 text-left">{{ t('plan') }}</th>
                <th class="py-2 px-3 text-left">{{ t('status') }}</th>
                <th class="py-2 px-3 text-left">{{ t('trial_ends_at') }}</th>
                <th class="py-2 px-3 text-left">{{ t('ends_at') }}</th>
                <th class="py-2 px-3 text-left">{{ t('created') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in subscriptions" :key="s.id" class="border-b border-[hsl(var(--border))] last:border-0">
                <td class="py-2 px-3 font-medium">{{ s.plan }}</td>
                <td class="py-2 px-3 capitalize">{{ s.status }}</td>
                <td class="py-2 px-3">{{ s.trial_ends_at?.slice(0, 10) }}</td>
                <td class="py-2 px-3">{{ s.ends_at?.slice(0, 10) }}</td>
                <td class="py-2 px-3 text-[hsl(var(--muted-foreground))]">{{ s.created_at?.slice(0, 10) }}</td>
              </tr>
            </tbody>
          </table>
        </CardContent>
      </Card>

      <!-- Dialogs -->
      <div v-if="showSuspend" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showSuspend = false">
        <div class="w-full max-w-md rounded-lg bg-[hsl(var(--background))] p-5 shadow-lg">
          <h3 class="text-lg font-semibold">{{ t('suspend_org_title', { name: organization.name }) }}</h3>
          <p class="mt-2 text-sm text-[hsl(var(--muted-foreground))]">{{ t('suspend_org_desc') }}</p>
          <textarea v-model="suspendReason" rows="3" maxlength="500" class="mt-3 w-full rounded-md border border-[hsl(var(--border))] bg-[hsl(var(--background))] px-3 py-2 text-sm" />
          <div class="mt-4 flex justify-end gap-2">
            <Button variant="outline" size="sm" @click="showSuspend = false">{{ t('cancel') }}</Button>
            <Button variant="destructive" size="sm" @click="suspend">{{ t('suspend') }}</Button>
          </div>
        </div>
      </div>

      <div v-if="showDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showDelete = false">
        <div class="w-full max-w-md rounded-lg bg-[hsl(var(--background))] p-5 shadow-lg">
          <h3 class="text-lg font-semibold">{{ t('delete_org_title', { name: organization.name }) }}</h3>
          <p class="mt-2 text-sm text-[hsl(var(--muted-foreground))]">{{ t('delete_org_desc') }}</p>
          <div class="mt-4 flex justify-end gap-2">
            <Button variant="outline" size="sm" @click="showDelete = false">{{ t('cancel') }}</Button>
            <Button variant="destructive" size="sm" @click="destroy">{{ t('delete') }}</Button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
