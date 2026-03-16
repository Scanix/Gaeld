<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { X } from 'lucide-vue-next'
import Button from './Button.vue'

const props = defineProps({
  open: Boolean,
  title: String,
})

const emit = defineEmits(['close'])

const dialogRef = ref(null)

function onKeydown(e) {
  if (e.key === 'Escape') emit('close')
}

watch(() => props.open, (val) => {
  if (val) {
    document.addEventListener('keydown', onKeydown)
    document.body.style.overflow = 'hidden'
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
      <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="fixed inset-0 bg-black/50" @click="$emit('close')" />
        <div
          ref="dialogRef"
          role="dialog"
          aria-modal="true"
          class="relative z-50 w-full max-w-lg rounded-xl border border-[hsl(var(--border))] bg-[hsl(var(--background))] p-6 shadow-lg"
        >
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold">{{ title }}</h2>
            <Button variant="ghost" size="icon" @click="$emit('close')">
              <X class="h-4 w-4" />
            </Button>
          </div>
          <slot />
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
