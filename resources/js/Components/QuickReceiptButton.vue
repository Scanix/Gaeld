<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Camera } from 'lucide-vue-next'
import QuickReceiptModal from '@/Components/QuickReceiptModal.vue'
import { useTranslations } from '@/lib/useTranslations'
import { ref } from 'vue'

const showModal = ref(false)
const { t } = useTranslations()

const page = usePage()
const scansToday = computed(() => page.props.auth?.ocr_quota?.ocr_scans_today ?? 0)
const dailyLimit = computed(() => page.props.auth?.ocr_quota?.ocr_daily_limit ?? -1)
const limitReached = computed(() => dailyLimit.value !== -1 && scansToday.value >= dailyLimit.value)
</script>

<template>
  <div>
    <button
      :title="limitReached ? t('ocr_daily_limit_reached', { limit: dailyLimit }) : t('quick_receipt')"
      :disabled="limitReached"
      class="fixed bottom-6 right-6 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-[hsl(var(--primary))] text-white shadow-lg transition-transform hover:scale-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[hsl(var(--ring))] focus-visible:ring-offset-2 active:scale-95 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:scale-100"
      @click="!limitReached && (showModal = true)"
    >
      <Camera class="h-6 w-6" />
      <span
        v-if="dailyLimit !== -1"
        class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-white px-1 text-[10px] font-semibold tabular-nums"
        :class="limitReached ? 'text-red-500' : 'text-[hsl(var(--primary))]'"
      >
        {{ scansToday }}/{{ dailyLimit }}
      </span>
    </button>

    <QuickReceiptModal
      :open="showModal"
      @close="showModal = false"
    />
  </div>
</template>
