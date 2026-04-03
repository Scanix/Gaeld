<script setup>
import { ref } from 'vue'
import { cn } from '@/lib/utils'
import { useId } from 'vue'

const props = defineProps({
  content: {
    type: String,
    default: '',
  },
  side: {
    type: String,
    default: 'right',
    validator: (v) => ['top', 'right', 'bottom', 'left'].includes(v),
  },
  class: String,
})

const tooltipId = `tooltip-${useId()}`
const visible = ref(false)

const positionClasses = {
  top: 'bottom-full left-1/2 -translate-x-1/2 mb-2',
  right: 'left-full top-1/2 -translate-y-1/2 ml-2',
  bottom: 'top-full left-1/2 -translate-x-1/2 mt-2',
  left: 'right-full top-1/2 -translate-y-1/2 mr-2',
}
</script>

<template>
  <div
    class="relative inline-flex"
    :aria-describedby="visible ? tooltipId : undefined"
    @mouseenter="visible = true"
    @mouseleave="visible = false"
    @focusin="visible = true"
    @focusout="visible = false"
  >
    <slot />
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="visible && (content || $slots.tooltip)"
        :id="tooltipId"
        role="tooltip"
        :class="cn(
          'pointer-events-none absolute z-50 whitespace-nowrap rounded-md bg-[hsl(var(--popover))] px-3 py-1.5 text-xs text-[hsl(var(--popover-foreground))] shadow-md border border-[hsl(var(--border))]',
          positionClasses[side],
          props.class,
        )"
      >
        <slot name="tooltip">{{ content }}</slot>
      </div>
    </Transition>
  </div>
</template>
