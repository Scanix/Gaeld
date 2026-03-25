<script setup>
import { ref } from 'vue'
import { Camera } from 'lucide-vue-next'
import QuickReceiptModal from '@/Components/QuickReceiptModal.vue'
import { useTranslations } from '@/lib/useTranslations'

const showModal = ref(false)
const { t } = useTranslations()
</script>

<template>
  <div>
    <button
      :title="t('quick_receipt')"
      class="fixed bottom-6 right-6 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))] shadow-lg transition-transform hover:scale-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[hsl(var(--ring))] focus-visible:ring-offset-2 active:scale-95"
      @click="showModal = true"
    >
      <Camera class="h-6 w-6" />
    </button>

    <QuickReceiptModal
      :open="showModal"
      @close="showModal = false"
    />
  </div>
</template>
