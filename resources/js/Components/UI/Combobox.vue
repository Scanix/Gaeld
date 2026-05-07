<script setup>
import { computed, ref, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { cn } from '@/lib/utils'
import { ChevronDown, X } from 'lucide-vue-next'

const props = defineProps({
  modelValue: { type: [String, Number], default: null },
  options: { type: Array, default: () => [] },
  labelKey: { type: String, default: 'label' },
  valueKey: { type: String, default: 'value' },
  placeholder: { type: String, default: 'Search…' },
  emptyText: { type: String, default: 'No results found.' },
  disabled: { type: Boolean, default: false },
  error: { type: String, default: '' },
  class: String,
})

const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const query = ref('')
const inputRef = ref(null)
const containerRef = ref(null)
const triggerRef = ref(null)
const highlightedIndex = ref(-1)
const dropdownStyle = ref({})

const selectedOption = computed(() =>
  props.options.find((o) => (typeof o === 'object' ? o[props.valueKey] : o) === props.modelValue)
)

const filtered = computed(() => {
  if (!query.value) return props.options
  const q = query.value.toLowerCase()
  return props.options.filter((o) => {
    const label = typeof o === 'object' ? o[props.labelKey] : String(o)
    return label.toLowerCase().includes(q)
  })
})

function getLabel(option) {
  return typeof option === 'object' ? option[props.labelKey] : String(option)
}

function getValue(option) {
  return typeof option === 'object' ? option[props.valueKey] : option
}

function select(option) {
  emit('update:modelValue', getValue(option))
  query.value = ''
  open.value = false
}

function clear() {
  emit('update:modelValue', null)
  query.value = ''
}

function openDropdown() {
  if (props.disabled) return
  open.value = true
  highlightedIndex.value = -1
  updateDropdownPosition()
  requestAnimationFrame(() => inputRef.value?.focus())
}

function updateDropdownPosition() {
  if (!triggerRef.value) return
  const rect = triggerRef.value.getBoundingClientRect()
  const spaceBelow = window.innerHeight - rect.bottom
  const dropdownHeight = 280
  if (spaceBelow >= dropdownHeight || spaceBelow >= 120) {
    dropdownStyle.value = {
      position: 'fixed',
      top: rect.bottom + 4 + 'px',
      left: rect.left + 'px',
      width: rect.width + 'px',
      zIndex: 9999,
    }
  } else {
    dropdownStyle.value = {
      position: 'fixed',
      bottom: window.innerHeight - rect.top + 4 + 'px',
      left: rect.left + 'px',
      width: rect.width + 'px',
      zIndex: 9999,
    }
  }
}

function onKeydown(e) {
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    highlightedIndex.value = Math.min(highlightedIndex.value + 1, filtered.value.length - 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0)
  } else if (e.key === 'Enter' && highlightedIndex.value >= 0) {
    e.preventDefault()
    select(filtered.value[highlightedIndex.value])
  } else if (e.key === 'Escape') {
    open.value = false
  }
}

function onClickOutside(e) {
  if (
    containerRef.value && !containerRef.value.contains(e.target) &&
    inputRef.value && !inputRef.value.closest('[role="listbox"]')?.contains(e.target)
  ) {
    open.value = false
  }
}

watch(query, () => {
  highlightedIndex.value = -1
})

onMounted(() => document.addEventListener('mousedown', onClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside))
</script>

<template>
  <div ref="containerRef" :class="cn('relative', props.class)">
    <button
      ref="triggerRef"
      type="button"
      class="flex h-10 w-full items-center justify-between rounded-md border border-[hsl(var(--input))] bg-transparent px-3 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))] disabled:cursor-not-allowed disabled:opacity-50 sm:h-9"
      :class="error ? 'border-[hsl(var(--destructive))]' : ''"
      :disabled="disabled"
      :aria-expanded="open"
      aria-haspopup="listbox"
      @click="openDropdown"
    >
      <span :class="selectedOption ? '' : 'text-[hsl(var(--muted-foreground))]'">
        {{ selectedOption ? getLabel(selectedOption) : placeholder }}
      </span>
      <div class="flex items-center gap-1">
        <X
          v-if="selectedOption"
          class="h-3.5 w-3.5 text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]"
          @click.stop="clear"
        />
        <ChevronDown class="h-3.5 w-3.5 text-[hsl(var(--muted-foreground))]" />
      </div>
    </button>

    <Teleport to="body">
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
          role="listbox"
          :style="dropdownStyle"
          class="rounded-md border border-[hsl(var(--border))] bg-[hsl(var(--popover))] p-1 shadow-lg"
        >
          <input
            ref="inputRef"
            v-model="query"
            type="text"
            class="w-full rounded-sm border-0 bg-transparent px-2 py-1.5 text-sm placeholder:text-[hsl(var(--muted-foreground))] focus:outline-none"
            :placeholder="placeholder"
            @keydown="onKeydown"
          />
          <div class="max-h-60 overflow-y-auto">
            <button
              v-for="(option, index) in filtered"
              :key="getValue(option)"
              type="button"
              role="option"
              :aria-selected="getValue(option) === modelValue"
              :class="[
                'flex w-full items-center rounded-sm px-2 py-1.5 text-sm transition-colors',
                getValue(option) === modelValue
                  ? 'bg-[hsl(var(--accent))] text-[hsl(var(--accent-foreground))]'
                  : highlightedIndex === index
                    ? 'bg-[hsl(var(--accent))]'
                    : 'hover:bg-[hsl(var(--accent))]',
              ]"
              @click="select(option)"
              @mouseenter="highlightedIndex = index"
            >
              {{ getLabel(option) }}
            </button>
            <p
              v-if="filtered.length === 0"
              class="px-2 py-4 text-center text-sm text-[hsl(var(--muted-foreground))]"
            >
              {{ emptyText }}
            </p>
          </div>
        </div>
      </Transition>
    </Teleport>

    <p v-if="error" class="mt-1 text-xs text-[hsl(var(--destructive))]">{{ error }}</p>
  </div>
</template>
