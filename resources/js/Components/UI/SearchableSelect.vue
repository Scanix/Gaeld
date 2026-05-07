<script setup>
import { computed, onMounted, onUnmounted, ref, watch, nextTick } from 'vue'
import { ChevronDown, HelpCircle, X } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

const props = defineProps({
  modelValue: { type: [String, Number, null], default: null },
  options: { type: Array, default: () => [] },
  labelKey: { type: String, default: 'label' },
  valueKey: { type: String, default: 'value' },
  groupKey: { type: String, default: '' }, // when set, options are grouped by this key
  label: { type: String, default: '' },
  id: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  searchPlaceholder: { type: String, default: '' },
  emptyText: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  error: { type: String, default: '' },
  helpHref: { type: String, default: '' },
  helpLabel: { type: String, default: '' },
  // Below this option count, render a plain native <select> for snappier UX on short lists.
  searchableThreshold: { type: Number, default: 8 },
  forceSearchable: { type: Boolean, default: false },
  class: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const query = ref('')
const inputRef = ref(null)
const containerRef = ref(null)
const highlightedIndex = ref(-1)

const useSearchable = computed(
  () => props.forceSearchable || props.options.length >= props.searchableThreshold,
)

function getLabel(option) {
  return typeof option === 'object' && option !== null ? option[props.labelKey] : String(option)
}
function getValue(option) {
  return typeof option === 'object' && option !== null ? option[props.valueKey] : option
}
function getGroup(option) {
  if (!props.groupKey) return ''
  return typeof option === 'object' && option !== null ? option[props.groupKey] ?? '' : ''
}

const selectedOption = computed(() =>
  props.options.find((o) => String(getValue(o)) === String(props.modelValue)),
)

const filtered = computed(() => {
  if (!query.value) return props.options
  const q = query.value.toLowerCase()
  return props.options.filter((o) => {
    const label = String(getLabel(o)).toLowerCase()
    const value = String(getValue(o)).toLowerCase()
    return label.includes(q) || value.includes(q)
  })
})

// Flat list of {kind:'header'|'option', ...} so keyboard navigation skips headers.
const flatList = computed(() => {
  if (!props.groupKey) {
    return filtered.value.map((option, idx) => ({ kind: 'option', option, idx }))
  }
  const groups = new Map()
  for (const opt of filtered.value) {
    const g = getGroup(opt)
    if (!groups.has(g)) groups.set(g, [])
    groups.get(g).push(opt)
  }
  const out = []
  let optionIdx = 0
  for (const [group, opts] of groups.entries()) {
    if (group !== '') out.push({ kind: 'header', label: group })
    for (const option of opts) {
      out.push({ kind: 'option', option, idx: optionIdx })
      optionIdx += 1
    }
  }
  return out
})

const optionsOnly = computed(() => flatList.value.filter((i) => i.kind === 'option'))

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
  nextTick(() => inputRef.value?.focus())
}

function onKeydown(e) {
  const max = optionsOnly.value.length - 1
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    highlightedIndex.value = Math.min(highlightedIndex.value + 1, max)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0)
  } else if (e.key === 'Enter' && highlightedIndex.value >= 0) {
    e.preventDefault()
    select(optionsOnly.value[highlightedIndex.value].option)
  } else if (e.key === 'Escape') {
    open.value = false
  }
}

function onClickOutside(e) {
  if (containerRef.value && !containerRef.value.contains(e.target)) {
    open.value = false
  }
}

watch(query, () => {
  highlightedIndex.value = -1
})

onMounted(() => document.addEventListener('mousedown', onClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside))

function onNativeChange(e) {
  emit('update:modelValue', e.target.value)
}
</script>

<template>
  <div :class="cn('space-y-2', props.class)">
    <div v-if="label || helpHref" class="flex items-center justify-between gap-2">
      <label v-if="label" :for="id" class="text-sm font-medium leading-none">
        {{ label }}
        <span v-if="required" class="text-[hsl(var(--destructive))]">*</span>
      </label>
      <a
        v-if="helpHref"
        :href="helpHref"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]"
        :title="helpLabel || 'Help'"
        :aria-label="helpLabel || 'Help'"
      >
        <HelpCircle class="h-3.5 w-3.5" />
      </a>
    </div>

    <!-- Native select for short lists -->
    <select
      v-if="!useSearchable"
      :id="id"
      :value="modelValue"
      :required="required"
      :disabled="disabled"
      :aria-describedby="error ? id + '-error' : undefined"
      :aria-invalid="error ? true : undefined"
      :class="cn(
        'flex h-11 w-full rounded-md border border-[hsl(var(--input))] bg-[hsl(var(--background))] px-3 py-1 text-base text-[hsl(var(--foreground))] shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))] disabled:cursor-not-allowed disabled:opacity-50 [&>option]:bg-[hsl(var(--background))] [&>option]:text-[hsl(var(--foreground))] sm:h-9 sm:text-sm',
        error && 'border-[hsl(var(--destructive))]',
      )"
      @change="onNativeChange"
    >
      <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
      <option v-for="opt in options" :key="getValue(opt)" :value="getValue(opt)">
        {{ getLabel(opt) }}
      </option>
    </select>

    <!-- Searchable combobox for long lists -->
    <div v-else ref="containerRef" class="relative">
      <button
        :id="id"
        type="button"
        class="flex h-11 w-full items-center justify-between rounded-md border border-[hsl(var(--input))] bg-transparent px-3 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))] disabled:cursor-not-allowed disabled:opacity-50 sm:h-9"
        :class="error ? 'border-[hsl(var(--destructive))]' : ''"
        :disabled="disabled"
        :aria-expanded="open"
        :aria-invalid="error ? true : undefined"
        :aria-describedby="error ? id + '-error' : undefined"
        aria-haspopup="listbox"
        @click="openDropdown"
      >
        <span :class="['truncate text-left', selectedOption ? '' : 'text-[hsl(var(--muted-foreground))]']">
          {{ selectedOption ? getLabel(selectedOption) : placeholder || '—' }}
        </span>
        <div class="ml-2 flex items-center gap-1">
          <X
            v-if="selectedOption && !required"
            class="h-3.5 w-3.5 text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]"
            @click.stop="clear"
          />
          <ChevronDown class="h-3.5 w-3.5 text-[hsl(var(--muted-foreground))]" />
        </div>
      </button>

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
          class="absolute z-50 mt-1 w-full rounded-md border border-[hsl(var(--border))] bg-[hsl(var(--popover))] p-1 shadow-lg"
        >
          <input
            ref="inputRef"
            v-model="query"
            type="text"
            class="w-full rounded-sm border-0 bg-transparent px-2 py-1.5 text-sm placeholder:text-[hsl(var(--muted-foreground))] focus:outline-none"
            :placeholder="searchPlaceholder || placeholder || 'Search…'"
            @keydown="onKeydown"
          />
          <div class="max-h-60 overflow-y-auto">
            <template v-for="(item, i) in flatList" :key="i">
              <div
                v-if="item.kind === 'header'"
                class="sticky top-0 bg-[hsl(var(--popover))] px-2 pt-2 pb-1 text-xs font-semibold uppercase tracking-wide text-[hsl(var(--muted-foreground))]"
              >
                {{ item.label }}
              </div>
              <button
                v-else
                type="button"
                role="option"
                :aria-selected="String(getValue(item.option)) === String(modelValue)"
                :class="[
                  'flex w-full items-center rounded-sm px-2 py-1.5 text-left text-sm transition-colors',
                  String(getValue(item.option)) === String(modelValue)
                    ? 'bg-[hsl(var(--accent))] text-[hsl(var(--accent-foreground))]'
                    : highlightedIndex === item.idx
                      ? 'bg-[hsl(var(--accent))]'
                      : 'hover:bg-[hsl(var(--accent))]',
                ]"
                @click="select(item.option)"
                @mouseenter="highlightedIndex = item.idx"
              >
                {{ getLabel(item.option) }}
              </button>
            </template>
            <p
              v-if="optionsOnly.length === 0"
              class="px-2 py-4 text-center text-sm text-[hsl(var(--muted-foreground))]"
            >
              {{ emptyText || 'No results found.' }}
            </p>
          </div>
        </div>
      </Transition>
    </div>

    <p v-if="error" :id="id + '-error'" role="alert" class="text-xs text-[hsl(var(--destructive))]">
      {{ error }}
    </p>
  </div>
</template>
