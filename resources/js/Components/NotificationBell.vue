<script setup>
import { ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Bell } from 'lucide-vue-next'
import { useTranslations } from '@/lib/useTranslations'
import Button from './UI/Button.vue'

const { t } = useTranslations()
const page = usePage()

const showDropdown = ref(false)
const notifications = ref([])
const loading = ref(false)
const localUnreadCount = ref(page.props.auth?.notifications_unread_count ?? 0)

watch(
  () => page.props.auth?.notifications_unread_count,
  (val) => { localUnreadCount.value = val ?? 0 },
)

function getCsrfToken() {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

async function openDropdown() {
  showDropdown.value = !showDropdown.value
  if (showDropdown.value) {
    await loadNotifications()
  }
}

async function loadNotifications() {
  loading.value = true
  try {
    const resp = await fetch('/notifications', {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      credentials: 'same-origin',
    })
    notifications.value = await resp.json()
  } finally {
    loading.value = false
  }
}

async function markRead(id) {
  await fetch(`/notifications/${id}/read`, {
    method: 'PATCH',
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-XSRF-TOKEN': getCsrfToken(),
      Accept: 'application/json',
    },
    credentials: 'same-origin',
  })
  const n = notifications.value.find((x) => x.id === id)
  if (n && !n.read_at) {
    n.read_at = new Date().toISOString()
    localUnreadCount.value = Math.max(0, localUnreadCount.value - 1)
  }
}

async function markAllRead() {
  await fetch('/notifications/read-all', {
    method: 'PATCH',
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-XSRF-TOKEN': getCsrfToken(),
      Accept: 'application/json',
    },
    credentials: 'same-origin',
  })
  notifications.value.forEach((n) => {
    if (!n.read_at) n.read_at = new Date().toISOString()
  })
  localUnreadCount.value = 0
}

const notificationResolvers = {
  ocr_completed: (data) => t('notification_ocr_completed', { filename: data.filename ?? '' }),
  ocr_failed: (data) => t('notification_ocr_failed', { filename: data.filename ?? '' }),
  trial_expiring: (data) => t('notification_trial_expiring', { days: data.days_remaining ?? 0 }),
  upgrade_nudge: () => t('notification_upgrade_nudge'),
}

function getNotificationMessage(n) {
  const resolver = notificationResolvers[n.data?.type]
  return resolver ? resolver(n.data) : t('notification_unknown')
}

function formatDate(iso) {
  return new Date(iso).toLocaleString()
}
</script>

<template>
  <div class="relative">
    <Button
      variant="ghost"
      size="icon"
      class="relative"
      :title="t('notifications_title')"
      @click="openDropdown"
    >
      <Bell class="h-4 w-4" />
      <span
        v-if="localUnreadCount > 0"
        class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-[hsl(var(--destructive))] text-[10px] font-bold text-white"
      >{{ localUnreadCount > 9 ? '9+' : localUnreadCount }}</span>
    </Button>

    <div
      v-if="showDropdown"
      role="menu"
      class="absolute right-0 top-full z-40 mt-1 w-80 rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--popover))] shadow-lg"
      @mouseleave="showDropdown = false"
    >
      <div class="flex items-center justify-between border-b border-[hsl(var(--border))] px-3 py-2">
        <span class="text-sm font-medium">{{ t('notifications_title') }}</span>
        <button
          v-if="localUnreadCount > 0"
          class="text-xs text-[hsl(var(--primary))] hover:underline"
          @click="markAllRead"
        >
          {{ t('notifications_mark_all_read') }}
        </button>
      </div>

      <div v-if="loading" class="py-6 text-center text-sm text-[hsl(var(--muted-foreground))]">…</div>
      <div
        v-else-if="notifications.length === 0"
        class="py-6 text-center text-sm text-[hsl(var(--muted-foreground))]"
      >
        {{ t('notifications_empty') }}
      </div>
      <ul
        v-else
        class="max-h-72 divide-y divide-[hsl(var(--border))] overflow-y-auto"
      >
        <li
          v-for="n in notifications"
          :key="n.id"
          class="flex items-start gap-2 px-3 py-2.5 text-sm"
          :class="{ 'bg-[hsl(var(--accent)/0.3)]': !n.read_at }"
        >
          <div class="min-w-0 flex-1">
            <p
              class="truncate"
              :class="n.read_at ? 'text-[hsl(var(--muted-foreground))]' : 'font-medium text-[hsl(var(--foreground))]'"
            >
              {{ getNotificationMessage(n) }}
            </p>
            <p class="mt-0.5 text-xs text-[hsl(var(--muted-foreground))]">{{ formatDate(n.created_at) }}</p>
          </div>
          <button
            v-if="!n.read_at"
            class="shrink-0 text-xs text-[hsl(var(--primary))] hover:underline"
            @click="markRead(n.id)"
          >
            {{ t('notifications_mark_read') }}
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>
