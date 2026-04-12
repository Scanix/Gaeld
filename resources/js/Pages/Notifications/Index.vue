<script setup>
import { ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import { useTranslations } from '@/lib/useTranslations'

const props = defineProps({
  notifications: Object,
  unreadCount: { type: Number, default: 0 },
})

const { t } = useTranslations()

const notificationResolvers = {
  ocr_completed: (data) => t('notification_ocr_completed', { filename: data.filename ?? '' }),
  ocr_failed: (data) => t('notification_ocr_failed', { filename: data.filename ?? '' }),
  trial_expiring: (data) => t('notification_trial_expiring', { days: data.days_remaining ?? 0 }),
  upgrade_nudge: () => t('notification_upgrade_nudge'),
  expense_submitted: (data) => t('expense_submitted_notification', { name: data.submitter_name ?? '' }),
  expense_approved: () => t('expense_approved_notification'),
  invoice_payment_recorded: (data) => t('invoice_payment_recorded_notification', { number: data.invoice_number ?? '' }),
}

function getNotificationMessage(n) {
  const resolver = notificationResolvers[n.data?.type]
  return resolver ? resolver(n.data) : t('notification_unknown')
}

function formatDate(iso) {
  return new Date(iso).toLocaleString()
}

function getCsrfToken() {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

const markingAll = ref(false)

async function markAllRead() {
  markingAll.value = true
  await fetch('/notifications/read-all', {
    method: 'PATCH',
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-XSRF-TOKEN': getCsrfToken(),
      Accept: 'application/json',
    },
    credentials: 'same-origin',
  })
  markingAll.value = false
  router.reload({ only: ['notifications', 'unreadCount'] })
}

function visitNotification(n) {
  if (!n.read_at) {
    fetch(`/notifications/${n.id}/read`, {
      method: 'PATCH',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': getCsrfToken(),
        Accept: 'application/json',
      },
      credentials: 'same-origin',
    })
  }
  const url = n.data?.url
  if (url) router.visit(url)
}
</script>

<template>
  <AppLayout :title="t('notifications_title')">
    <Breadcrumb :items="[{ label: t('notifications_title') }]" class="mb-4" />

    <Card>
      <CardHeader class="flex flex-row items-center justify-between">
        <CardTitle>{{ t('notifications_title') }}</CardTitle>
        <Button
          v-if="unreadCount > 0"
          variant="outline"
          size="sm"
          :disabled="markingAll"
          @click="markAllRead"
        >
          {{ t('notifications_mark_all_read') }}
        </Button>
      </CardHeader>
      <CardContent class="p-0">
        <div
          v-if="notifications.data.length === 0"
          class="py-12 text-center text-sm text-[hsl(var(--muted-foreground))]"
        >
          {{ t('notifications_empty') }}
        </div>
        <ul v-else class="divide-y divide-[hsl(var(--border))]">
          <li
            v-for="n in notifications.data"
            :key="n.id"
            class="flex items-start gap-3 px-4 py-3 text-sm"
            :class="[
              !n.read_at ? 'bg-[hsl(var(--accent)/0.2)]' : '',
              n.data?.url ? 'cursor-pointer hover:bg-[hsl(var(--accent)/0.4)]' : '',
            ]"
            @click="visitNotification(n)"
          >
            <div class="mt-0.5">
              <Badge v-if="!n.read_at" variant="default" class="text-[10px]">{{ t('notifications_mark_read') }}</Badge>
            </div>
            <div class="min-w-0 flex-1">
              <p
                class="truncate"
                :class="n.read_at ? 'text-[hsl(var(--muted-foreground))]' : 'font-medium text-[hsl(var(--foreground))]'"
              >
                {{ getNotificationMessage(n) }}
              </p>
              <p class="mt-0.5 text-xs text-[hsl(var(--muted-foreground))]">{{ formatDate(n.created_at) }}</p>
            </div>
          </li>
        </ul>
        <div v-if="notifications.last_page > 1" class="flex items-center justify-between border-t border-[hsl(var(--border))] px-4 py-3">
          <span class="text-sm text-[hsl(var(--muted-foreground))]">
            {{ t('page') }} {{ notifications.current_page }} / {{ notifications.last_page }}
          </span>
          <div class="flex gap-2">
            <Link v-if="notifications.prev_page_url" :href="notifications.prev_page_url">
              <Button variant="outline" size="sm">{{ t('previous') }}</Button>
            </Link>
            <Link v-if="notifications.next_page_url" :href="notifications.next_page_url">
              <Button variant="outline" size="sm">{{ t('next') }}</Button>
            </Link>
          </div>
        </div>
      </CardContent>
    </Card>
  </AppLayout>
</template>
