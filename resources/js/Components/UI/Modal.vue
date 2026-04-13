<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue'
import { X } from 'lucide-vue-next'
import Button from './Button.vue'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  open: Boolean,
  show: Boolean,
  title: String,
})

const isOpen = computed(() => props.open || props.show)

const emit = defineEmits(['close'])

const dialogRef = ref(null)

const FOCUSABLE_SELECTORS = [
  'a[href]',
  'button:not([disabled])',
  'textarea:not([disabled])',
  'input:not([disabled])',
  'select:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(', ')

function getFocusableEls() {
  return dialogRef.value ? [...dialogRef.value.querySelectorAll(FOCUSABLE_SELECTORS)] : []
}

function onKeydown(e) {
  if (e.key === 'Escape') {
    emit('close')
    return
  }
  if (e.key === 'Tab') {
    const els = getFocusableEls()
    if (!els.length) { e.preventDefault(); return }
    const first = els[0]
    const last = els[els.length - 1]
    if (e.shiftKey) {
      if (document.activeElement === first) { e.preventDefault(); last.focus() }
    } else {
      if (document.activeElement === last) { e.preventDefault(); first.focus() }
    }
  }
}

watch(isOpen, (val) => {
  if (val) {
    document.addEventListener('keydown', onKeydown)
    document.body.style.overflow = 'hidden'
    nextTick(() => {
      const els = getFocusableEls()
      if (els.length) {
        els[0].focus()
      } else {
        dialogRef.value?.focus()
      }
    })
  } else {
    document.removeEventListener('keydown', onKeydown)
    document.body.style.overflow = ''
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="fixed inset-0 bg-black/50" @click="$emit('close')" />
        <div
          ref="dialogRef"
          role="dialog"
          aria-modal="true"
          aria-labelledby="modal-title"
          tabindex="-1"
          class="relative z-50 mx-4 flex max-h-[90dvh] w-full max-w-lg flex-col rounded-xl border border-[hsl(var(--border))] bg-[hsl(var(--background))] shadow-lg outline-none sm:mx-auto"
        >
          <div class="flex shrink-0 items-center justify-between border-b border-[hsl(var(--border))] px-4 py-4 sm:px-6">
            <h2 id="modal-title" class="text-lg font-semibold">{{ title }}</h2>
            <Button variant="ghost" size="icon" :aria-label="t('close')" @click="$emit('close')">
              <X class="h-4 w-4" />
            </Button>
          </div>
          <div class="overflow-y-auto px-4 py-4 sm:px-6 sm:py-6">
            <slot />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.15s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
