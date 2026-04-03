<script setup>
import { Link, router } from '@inertiajs/vue3'
import { ChevronLeft, ChevronRight, ArrowUp, ArrowDown, ArrowUpDown, Search, X, Columns3, Download } from 'lucide-vue-next'
import Button from './Button.vue'
import Badge from './Badge.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useMediaQuery } from '@/lib/useMediaQuery'
import { ref, computed, watch } from 'vue'

const { t } = useTranslations()
const isMobile = useMediaQuery('(max-width: 639px)')

const props = defineProps({
  columns: {
    type: Array,
    required: true,
    // Each column: { key: string, label: string, class?: string, format?: Function, sortable?: boolean }
  },
  rows: {
    type: Array,
    default: () => [],
  },
  pagination: {
    type: Object,
    default: null,
    // Expects Laravel pagination object with links, current_page, last_page
  },
  emptyMessage: {
    type: String,
    default: null,
  },
  rowLink: {
    type: Function,
    default: null,
    // (row) => '/invoices/123'
  },
  // Sorting support (server-side)
  sort: {
    type: String,
    default: null,
  },
  direction: {
    type: String,
    default: 'asc',
  },
  // Search support (server-side)
  searchable: {
    type: Boolean,
    default: false,
  },
  searchValue: {
    type: String,
    default: '',
  },
  searchPlaceholder: {
    type: String,
    default: null,
  },
  // Filter support (server-side, provided as active filters + available options)
  filters: {
    type: Array,
    default: () => [],
    // Each: { key: string, label: string, value: string|null, options: [{ value: string, label: string }] }
  },
  // Selectable rows (bulk actions)
  selectable: {
    type: Boolean,
    default: false,
  },
  // CSV export
  exportable: {
    type: Boolean,
    default: false,
  },
  exportFilename: {
    type: String,
    default: 'export',
  },
})

const emit = defineEmits(['sort', 'search', 'filter', 'selection-change'])

const localSearch = ref(props.searchValue || '')
let searchTimeout = null

watch(() => props.searchValue, (val) => {
  localSearch.value = val || ''
})

function handleSort(column) {
  if (!column.sortable) return
  const newDirection = (props.sort === column.key && props.direction === 'asc') ? 'desc' : 'asc'
  emit('sort', { sort: column.key, direction: newDirection })
}

function handleSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    emit('search', localSearch.value)
  }, 300)
}

function clearSearch() {
  localSearch.value = ''
  emit('search', '')
}

function handleFilter(key, value) {
  emit('filter', { key, value })
}

function getSortIcon(column) {
  if (!column.sortable) return null
  if (props.sort !== column.key) return 'unsorted'
  return props.direction === 'asc' ? 'asc' : 'desc'
}

const hasToolbar = computed(() => props.searchable || props.filters.length > 0)

// Column visibility
const hiddenColumns = ref(new Set())
const showColumnMenu = ref(false)

const visibleColumns = computed(() =>
  props.columns.filter((col) => !hiddenColumns.value.has(col.key))
)

function toggleColumn(key) {
  const next = new Set(hiddenColumns.value)
  next.has(key) ? next.delete(key) : next.add(key)
  hiddenColumns.value = next
}

// Row selection
const selectedIds = ref(new Set())

const allSelected = computed(() =>
  props.rows.length > 0 && props.rows.every((r) => selectedIds.value.has(r.id))
)

function toggleAll() {
  if (allSelected.value) {
    selectedIds.value = new Set()
  } else {
    selectedIds.value = new Set(props.rows.map((r) => r.id))
  }
  emit('selection-change', [...selectedIds.value])
}

function toggleRow(id) {
  const next = new Set(selectedIds.value)
  next.has(id) ? next.delete(id) : next.add(id)
  selectedIds.value = next
  emit('selection-change', [...next])
}

// CSV export
function exportCsv() {
  const cols = visibleColumns.value
  const header = cols.map((c) => `"${c.label.replace(/"/g, '""')}"`).join(',')
  const body = props.rows.map((row) =>
    cols.map((c) => {
      const val = c.format ? c.format(row[c.key], row) : (row[c.key] ?? '')
      return `"${String(val).replace(/"/g, '""')}"`
    }).join(',')
  ).join('\n')
  const blob = new Blob([`${header}\n${body}`], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `${props.exportFilename}.csv`
  a.click()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <div class="rounded-xl border border-[hsl(var(--border))] bg-[hsl(var(--card))]">
    <!-- Toolbar: Search + Filters + Column Toggle + Export -->
    <div v-if="hasToolbar || exportable" class="flex flex-wrap items-center gap-3 border-b border-[hsl(var(--border))] px-4 py-3">
      <!-- Search -->
      <div v-if="searchable" class="relative flex-1 min-w-[200px] max-w-sm">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[hsl(var(--muted-foreground))]" />
        <input
          v-model="localSearch"
          type="text"
          :placeholder="searchPlaceholder ?? t('search')"
          class="h-9 w-full rounded-md border border-[hsl(var(--input))] bg-transparent pl-9 pr-9 text-sm outline-none placeholder:text-[hsl(var(--muted-foreground))] focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]"
          @input="handleSearch"
        />
        <button
          v-if="localSearch"
          class="absolute right-2 top-1/2 -translate-y-1/2 text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]"
          :aria-label="t('clear_search')"
          @click="clearSearch"
        >
          <X class="h-4 w-4" />
        </button>
      </div>

      <!-- Filter dropdowns -->
      <div v-for="filter in filters" :key="filter.key" class="flex items-center gap-1">
        <select
          :value="filter.value ?? ''"
          class="h-9 rounded-md border border-[hsl(var(--input))] bg-transparent px-3 text-sm text-[hsl(var(--foreground))] outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]"
          @change="handleFilter(filter.key, $event.target.value)"
        >
          <option value="">{{ filter.label }}</option>
          <option v-for="opt in filter.options" :key="opt.value" :value="opt.value">
            {{ opt.label }}
          </option>
        </select>
      </div>

      <div class="ml-auto flex items-center gap-2">
        <!-- Column visibility toggle -->
        <div class="relative">
          <Button variant="outline" size="sm" @click="showColumnMenu = !showColumnMenu">
            <Columns3 class="h-4 w-4" />
          </Button>
          <div v-if="showColumnMenu" class="absolute right-0 top-full z-20 mt-1 min-w-[160px] rounded-md border border-[hsl(var(--border))] bg-[hsl(var(--card))] p-2 shadow-md">
            <label
              v-for="col in columns"
              :key="col.key"
              class="flex items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-[hsl(var(--muted))]/50 cursor-pointer"
            >
              <input
                type="checkbox"
                :checked="!hiddenColumns.has(col.key)"
                class="rounded border-[hsl(var(--input))]"
                @change="toggleColumn(col.key)"
              />
              {{ col.label }}
            </label>
          </div>
        </div>

        <!-- CSV export -->
        <Button v-if="exportable" variant="outline" size="sm" @click="exportCsv">
          <Download class="h-4 w-4" />
        </Button>
      </div>
    </div>

    <div class="overflow-auto">
      <!-- Mobile card view -->
      <div v-if="isMobile" class="divide-y divide-[hsl(var(--border))]">
        <component
          :is="rowLink ? Link : 'div'"
          v-for="(row, i) in rows"
          :key="row.id ?? i"
          v-bind="rowLink ? { href: rowLink(row) } : {}"
          class="block px-4 py-3 space-y-1"
          :class="rowLink ? 'hover:bg-[hsl(var(--muted))]/50' : ''"
        >
          <div v-if="selectable" class="flex items-center gap-2 pb-1">
            <input type="checkbox" :checked="selectedIds.has(row.id)" class="rounded border-[hsl(var(--input))]" @change="toggleRow(row.id)" />
          </div>
          <div v-for="col in visibleColumns" :key="col.key" class="flex justify-between gap-2 text-sm">
            <span class="text-[hsl(var(--muted-foreground))] shrink-0">{{ col.label }}</span>
            <span :class="col.class" class="text-right">
              <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                {{ col.format ? col.format(row[col.key], row) : row[col.key] }}
              </slot>
            </span>
          </div>
        </component>
        <div v-if="rows.length === 0" class="px-4 py-8 text-center text-[hsl(var(--muted-foreground))]">
          <slot name="empty">
            {{ emptyMessage ?? t('no_records') }}
          </slot>
        </div>
      </div>

      <!-- Desktop table view -->
      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-[hsl(var(--border))] bg-[hsl(var(--muted))]/50">
            <th v-if="selectable" scope="col" class="w-10 px-4 py-3">
              <input type="checkbox" :checked="allSelected" class="rounded border-[hsl(var(--input))]" @change="toggleAll" />
            </th>
            <th
              v-for="col in visibleColumns"
              :key="col.key"
              scope="col"
              :aria-sort="col.sortable ? (sort === col.key ? (direction === 'asc' ? 'ascending' : 'descending') : 'none') : undefined"
              :class="[
                'px-4 py-3 text-left font-medium text-[hsl(var(--muted-foreground))]',
                col.class,
                col.sortable ? 'cursor-pointer select-none hover:text-[hsl(var(--foreground))]' : '',
              ]"
              @click="handleSort(col)"
            >
              <span class="inline-flex items-center gap-1">
                {{ col.label }}
                <ArrowUp v-if="getSortIcon(col) === 'asc'" class="h-3.5 w-3.5" />
                <ArrowDown v-else-if="getSortIcon(col) === 'desc'" class="h-3.5 w-3.5" />
                <ArrowUpDown v-else-if="getSortIcon(col) === 'unsorted'" class="h-3.5 w-3.5 opacity-40" />
              </span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, i) in rows"
            :key="row.id ?? i"
            class="border-b border-[hsl(var(--border))] last:border-0 transition-colors hover:bg-[hsl(var(--muted))]/50"
          >
            <td v-if="selectable" class="w-10 px-4 py-3">
              <input type="checkbox" :checked="selectedIds.has(row.id)" class="rounded border-[hsl(var(--input))]" @change="toggleRow(row.id)" />
            </td>
            <td
              v-for="col in visibleColumns"
              :key="col.key"
              :class="['px-4 py-3', col.class]"
            >
              <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                <Link v-if="rowLink" :href="rowLink(row)" class="hover:underline">
                  {{ col.format ? col.format(row[col.key], row) : row[col.key] }}
                </Link>
                <template v-else>
                  {{ col.format ? col.format(row[col.key], row) : row[col.key] }}
                </template>
              </slot>
            </td>
          </tr>
          <tr v-if="rows.length === 0">
            <td :colspan="visibleColumns.length + (selectable ? 1 : 0)" class="px-4 py-8 text-center text-[hsl(var(--muted-foreground))]">
              <slot name="empty">
                {{ emptyMessage ?? t('no_records') }}
              </slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Selection count -->
    <div v-if="selectable && selectedIds.size > 0" class="flex items-center gap-3 border-t border-[hsl(var(--border))] px-4 py-2 text-sm text-[hsl(var(--muted-foreground))]">
      {{ t('n_selected', { count: selectedIds.size }) }}
      <slot name="bulk-actions" :selected-ids="[...selectedIds]" />
    </div>

    <div v-if="pagination && pagination.last_page > 1" class="flex items-center justify-between border-t border-[hsl(var(--border))] px-4 py-3">
      <p class="text-sm text-[hsl(var(--muted-foreground))]">
        {{ t('page_of', { current: pagination.current_page, last: pagination.last_page }) }}
      </p>
      <div class="flex gap-1">
        <Button
          v-if="pagination.prev_page_url"
          as="a"
          :href="pagination.prev_page_url"
          variant="outline"
          size="sm"
        >
          <ChevronLeft class="h-4 w-4" />
        </Button>
        <Button
          v-if="pagination.next_page_url"
          as="a"
          :href="pagination.next_page_url"
          variant="outline"
          size="sm"
        >
          <ChevronRight class="h-4 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
