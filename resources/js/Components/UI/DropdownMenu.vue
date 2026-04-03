<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { EllipsisVertical } from 'lucide-vue-next'
import Button from './Button.vue'

defineProps({
  align: {
    type: String,
    default: 'right',
    validator: (v) => ['left', 'right'].includes(v),
  },
})

const open = ref(false)
const dropdownRef = ref(null)

function close() {
  open.value = false
}

function handleClickOutside(e) {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
    close()
  }
}

onMounted(() => document.addEventListener('mousedown', handleClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside))

defineExpose({ close })
</script>

<template>
  <div ref="dropdownRef" class="relative">
    <slot name="trigger" :toggle="() => (open = !open)" :open="open">
      <Button variant="outline" size="icon" aria-haspopup="menu" :aria-expanded="open" @click="open = !open">
        <EllipsisVertical class="h-4 w-4" />
      </Button>
    </slot>

    <Transition
      enter-active-class="transition duration-100 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-75 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="open"
        role="menu"
        :class="[
          'absolute z-50 mt-1 min-w-[12rem] rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--popover))] p-1 shadow-lg',
          align === 'right' ? 'right-0' : 'left-0',
        ]"
      >
        <slot :close="close" />
      </div>
    </Transition>
  </div>
</template>
