<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useReceiptQueue } from '@/lib/useReceiptQueue'
import { useTranslations } from '@/lib/useTranslations'
import { Loader2, Check, X, AlertCircle, Receipt, ChevronUp, ChevronDown } from 'lucide-vue-next'

const { scans, dismiss } = useReceiptQueue()
const { t } = useTranslations()

const expanded = ref(false)

const scanList = computed(() => {
  const entries = []
  scans.forEach((scan, id) => {
    entries.push({ id, ...scan })
  })
  return entries
})

const hasScans = computed(() => scanList.value.length > 0)
const processingCount = computed(() => scanList.value.filter(s => s.status === 'processing').length)
const completedCount = computed(() => scanList.value.filter(s => s.status === 'completed').length)

function createExpense(scan) {
  const params = {}
  if (scan.extracted?.amount) params.amount = scan.extracted.amount
  if (scan.extracted?.date) params.date = scan.extracted.date
  if (scan.extracted?.vendor) params.vendor = scan.extracted.vendor
  if (scan.receiptPath) params.receipt_path = scan.receiptPath

  dismiss(scan.id)

  router.get('/expenses/create', params)
}

function dismissScan(scanId) {
  dismiss(scanId)
}
</script>

<template>
  <div v-if="hasScans" class="fixed bottom-6 right-24 z-40">
    <!-- Collapsed badge -->
    <button
      class="flex items-center gap-2 rounded-full bg-[hsl(var(--primary))] px-4 py-2 text-sm font-medium text-[hsl(var(--primary-foreground))] shadow-lg transition-transform hover:scale-105"
      @click="expanded = !expanded"
    >
      <Receipt class="h-4 w-4" />
      <span v-if="processingCount > 0">
        <Loader2 class="inline h-3 w-3 animate-spin" />
        {{ processingCount }}
      </span>
      <span v-if="completedCount > 0" class="flex items-center gap-1">
        <Check class="inline h-3 w-3" />
        {{ completedCount }}
      </span>
      <component :is="expanded ? ChevronDown : ChevronUp" class="h-3 w-3" />
    </button>

    <!-- Expanded panel -->
    <div
      v-if="expanded"
      class="absolute bottom-12 right-0 w-80 rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--card))] p-3 shadow-xl"
    >
      <div class="mb-2 flex items-center justify-between">
        <h4 class="text-sm font-semibold text-[hsl(var(--card-foreground))]">
          {{ t('receipt_queue') }}
        </h4>
      </div>

      <div class="max-h-60 space-y-2 overflow-y-auto">
        <div
          v-for="scan in scanList"
          :key="scan.id"
          class="flex items-center gap-3 rounded-md border border-[hsl(var(--border))] p-2"
        >
          <!-- Status icon -->
          <div class="shrink-0">
            <Loader2 v-if="scan.status === 'processing'" class="h-5 w-5 animate-spin text-[hsl(var(--primary))]" />
            <Check v-else-if="scan.status === 'completed'" class="h-5 w-5 text-green-600" />
            <AlertCircle v-else class="h-5 w-5 text-red-500" />
          </div>

          <!-- Content -->
          <div class="min-w-0 flex-1">
            <p v-if="scan.status === 'processing'" class="truncate text-xs text-[hsl(var(--muted-foreground))]">
              {{ t('scanning_receipt') }}
            </p>
            <template v-else-if="scan.status === 'completed'">
              <p class="truncate text-sm font-medium text-[hsl(var(--card-foreground))]">
                {{ scan.extracted?.vendor || t('receipt') }}
              </p>
              <p v-if="scan.extracted?.amount" class="text-xs text-[hsl(var(--muted-foreground))]">
                {{ scan.extracted.amount }} — {{ scan.extracted?.date }}
              </p>
            </template>
            <p v-else class="truncate text-xs text-red-600 dark:text-red-400">
              {{ t('scan_failed') }}
            </p>
          </div>

          <!-- Actions -->
          <div class="flex shrink-0 gap-1">
            <button
              v-if="scan.status === 'completed'"
              class="rounded px-2 py-1 text-xs font-medium text-[hsl(var(--primary))] hover:bg-[hsl(var(--accent))]"
              @click="createExpense(scan)"
            >
              {{ t('create') }}
            </button>
            <button
              class="rounded p-1 text-[hsl(var(--muted-foreground))] hover:bg-[hsl(var(--accent))]"
              @click="dismissScan(scan.id)"
            >
              <X class="h-3 w-3" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
