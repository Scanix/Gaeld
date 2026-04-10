<script setup>
import { computed } from 'vue'
import { Share2, Printer } from 'lucide-vue-next'
import Button from './Button.vue'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  title: {
    type: String,
    default: '',
  },
  url: {
    type: String,
    default: null,
  },
})

const canShare = computed(
  () => typeof navigator !== 'undefined' && 'share' in navigator
)

function share() {
  navigator.share({
    title: props.title,
    url: props.url ?? window.location.href,
  }).catch(() => {
    // User dismissed or browser denied — silent fail
  })
}

function print() {
  window.print()
}
</script>

<template>
  <div class="no-print flex items-center gap-2">
    <Button
      v-if="canShare"
      variant="outline"
      size="sm"
      :aria-label="t('share')"
      @click="share"
    >
      <Share2 class="h-4 w-4 sm:mr-1.5" />
      <span class="hidden sm:inline">{{ t('share') }}</span>
    </Button>
    <Button
      variant="outline"
      size="sm"
      :aria-label="t('print')"
      @click="print"
    >
      <Printer class="h-4 w-4 sm:mr-1.5" />
      <span class="hidden sm:inline">{{ t('print') }}</span>
    </Button>
  </div>
</template>
