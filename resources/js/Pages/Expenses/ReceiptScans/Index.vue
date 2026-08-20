<script setup>
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import EmptyState from '@/Components/UI/EmptyState.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { ScanLine, Trash2, FilePlus2 } from 'lucide-vue-next'
import { ref } from 'vue'

defineProps({
  scans: {
    type: Array,
    default: () => [],
  },
})

const { t } = useTranslations()
const { formatDate } = useFormatters()

const discardTarget = ref(null)
const discarding = ref(false)

function confirmDiscard(scan) {
  discardTarget.value = scan
}

function executeDiscard() {
  if (!discardTarget.value) return
  discarding.value = true
  router.delete(`/expenses/receipt-scans/${discardTarget.value.scan_id}`, {
    preserveScroll: true,
    onFinish: () => {
      discarding.value = false
      discardTarget.value = null
    },
  })
}

function statusVariant(status) {
  switch (status) {
    case 'completed':
      return 'success'
    case 'pending':
      return 'warning'
    case 'failed':
      return 'destructive'
    default:
      return 'secondary'
  }
}

function statusLabel(status) {
  return t(`receipt_scan_status_${status}`)
}
</script>

<template>
  <AppLayout :title="t('receipt_scans_title')">
    <div class="space-y-6 p-6">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <ScanLine class="h-5 w-5 text-amber-600 dark:text-amber-400" />
          <h1 class="text-xl font-semibold">{{ t('receipt_scans_title') }}</h1>
        </div>
        <Link href="/expenses">
          <Button variant="outline">{{ t('back') }}</Button>
        </Link>
      </div>

      <EmptyState
        v-if="!scans.length"
        :title="t('receipt_scans_empty_title')"
        :description="t('receipt_scans_empty_description')"
        :icon="ScanLine"
      />

      <div v-else class="space-y-3">
        <Card v-for="scan in scans" :key="scan.id">
          <CardContent class="flex flex-wrap items-center justify-between gap-4 pt-6">
            <div class="min-w-0 space-y-1">
              <div class="flex items-center gap-2">
                <Badge :variant="statusVariant(scan.status)">{{ statusLabel(scan.status) }}</Badge>
                <span class="text-xs text-[hsl(var(--muted-foreground))]">
                  {{ t('receipt_scan_expires_at', { date: formatDate(scan.expires_at) }) }}
                </span>
              </div>
              <p class="text-sm text-[hsl(var(--muted-foreground))]">
                {{ t('receipt_scan_created_at', { date: formatDate(scan.created_at) }) }}
              </p>
              <p v-if="scan.extracted?.vendor" class="text-sm font-medium">
                {{ scan.extracted.vendor }}
                <span v-if="scan.extracted?.amount" class="ml-2 text-[hsl(var(--muted-foreground))]">
                  {{ scan.extracted.amount }}
                </span>
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <Link :href="scan.create_url">
                <Button variant="default">
                  <FilePlus2 class="mr-2 h-4 w-4" />
                  {{ t('receipt_scan_create_expense') }}
                </Button>
              </Link>
              <Button variant="ghost" @click="confirmDiscard(scan)">
                <Trash2 class="mr-2 h-4 w-4" />
                {{ t('receipt_scan_discard') }}
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>

    <ConfirmDialog
      :open="!!discardTarget"
      :title="t('receipt_scan_discard_confirm_title')"
      :message="t('receipt_scan_discard_confirm_description')"
      :confirm-label="t('receipt_scan_discard')"
      :processing="discarding"
      @confirm="executeDiscard"
      @cancel="discardTarget = null"
    />
  </AppLayout>
</template>
