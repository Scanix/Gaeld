<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import {
  Search, FileText, Users, Truck, Receipt, Loader2, X,
  LayoutDashboard, BookOpen, Landmark, BarChart3, Package, Briefcase, Settings, ArrowRight,
} from 'lucide-vue-next'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

const open = ref(false)
const query = ref('')
const results = ref([])
const loading = ref(false)
const selectedIndex = ref(-1)
const inputRef = ref(null)
let debounceTimer = null

const typeConfig = {
  invoice: { icon: FileText, label: () => t('invoices'), color: 'text-blue-500' },
  customer: { icon: Users, label: () => t('customers'), color: 'text-green-500' },
  supplier: { icon: Truck, label: () => t('suppliers'), color: 'text-orange-500' },
  expense: { icon: Receipt, label: () => t('expenses'), color: 'text-red-500' },
  navigation: { icon: ArrowRight, label: () => t('go_to'), color: 'text-[hsl(var(--muted-foreground))]' },
}

// Quick navigation pages for ⌘K
const navigationItems = computed(() => [
  { type: 'navigation', id: 'nav-dashboard', title: t('dashboard'), url: '/', icon: LayoutDashboard },
  { type: 'navigation', id: 'nav-invoices', title: t('invoices'), url: '/invoices', icon: FileText },
  { type: 'navigation', id: 'nav-recurring', title: t('recurring'), url: '/invoices/recurring', icon: FileText },
  { type: 'navigation', id: 'nav-expenses', title: t('expenses'), url: '/expenses', icon: Receipt },
  { type: 'navigation', id: 'nav-customers', title: t('customers'), url: '/customers', icon: Users },
  { type: 'navigation', id: 'nav-suppliers', title: t('suppliers'), url: '/suppliers', icon: Truck },
  { type: 'navigation', id: 'nav-chart', title: t('chart_of_accounts'), url: '/accounting/chart-of-accounts', icon: BookOpen },
  { type: 'navigation', id: 'nav-journal', title: t('journal_entries'), url: '/accounting/journal-entries', icon: BookOpen },
  { type: 'navigation', id: 'nav-banking', title: t('banking'), url: '/banking', icon: Landmark },
  { type: 'navigation', id: 'nav-reports', title: t('reports'), subtitle: t('profit_and_loss'), url: '/reports/profit-and-loss', icon: BarChart3 },
  { type: 'navigation', id: 'nav-assets', title: t('assets'), url: '/assets', icon: Package },
  { type: 'navigation', id: 'nav-payroll', title: t('payroll'), url: '/payroll/employees', icon: Briefcase },
  { type: 'navigation', id: 'nav-settings', title: t('settings'), url: '/settings', icon: Settings },
])

const matchingNavItems = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (q.length < 1) return []
  return navigationItems.value.filter(item =>
    item.title.toLowerCase().includes(q) ||
    (item.subtitle && item.subtitle.toLowerCase().includes(q))
  )
})

const groupedResults = computed(() => {
  const groups = {}
  // Add matching navigation items first
  if (matchingNavItems.value.length > 0) {
    groups.navigation = matchingNavItems.value
  }
  for (const r of results.value) {
    if (!groups[r.type]) groups[r.type] = []
    groups[r.type].push(r)
  }
  return groups
})

const flatResults = computed(() => [...matchingNavItems.value, ...results.value])

// Show quick links when search is open but query is empty
const showQuickLinks = computed(() => open.value && query.value.trim().length < 2 && results.value.length === 0)

function openSearch() {
  open.value = true
  nextTick(() => inputRef.value?.focus())
}

function closeSearch() {
  open.value = false
  query.value = ''
  results.value = []
  selectedIndex.value = -1
}

function onKeydown(e) {
  if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
    e.preventDefault()
    if (open.value) {
      closeSearch()
    } else {
      openSearch()
    }
  }
}

function onDialogKeydown(e) {
  if (e.key === 'Escape') {
    closeSearch()
  } else if (e.key === 'ArrowDown') {
    e.preventDefault()
    const list = activeList.value
    selectedIndex.value = Math.min(selectedIndex.value + 1, list.length - 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    selectedIndex.value = Math.max(selectedIndex.value - 1, -1)
  } else if (e.key === 'Enter' && selectedIndex.value >= 0) {
    e.preventDefault()
    const list = activeList.value
    if (selectedIndex.value < list.length) navigateTo(list[selectedIndex.value])
  }
}

// The active list for keyboard navigation depends on whether we're showing quick links or search results
const activeList = computed(() => {
  if (showQuickLinks.value) return navigationItems.value
  return flatResults.value
})

function navigateTo(result) {
  closeSearch()
  router.visit(result.url)
}

async function search(q) {
  if (q.length < 2) {
    results.value = []
    return
  }

  loading.value = true
  try {
    const res = await fetch(`/search?q=${encodeURIComponent(q)}`, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })
    if (res.ok) {
      const data = await res.json()
      results.value = data.results || []
      selectedIndex.value = results.value.length > 0 ? 0 : -1
    }
  } finally {
    loading.value = false
  }
}

watch(query, (val) => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => search(val.trim()), 250)
})

onMounted(() => document.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown)
  clearTimeout(debounceTimer)
})

const isMac = typeof navigator !== 'undefined' && /Mac|iPod|iPhone|iPad/.test(navigator.userAgent)
</script>

<template>
  <!-- Trigger button -->
  <button
    class="flex items-center gap-2 rounded-md border border-[hsl(var(--border))] bg-[hsl(var(--background))] px-3 py-1.5 text-sm text-[hsl(var(--muted-foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))] transition-colors"
    @click="openSearch"
  >
    <Search class="h-4 w-4" />
    <span class="hidden md:inline">{{ t('global_search') }}</span>
    <kbd class="pointer-events-none hidden h-5 select-none items-center gap-1 rounded border border-[hsl(var(--border))] bg-[hsl(var(--muted))] px-1.5 font-mono text-[10px] font-medium text-[hsl(var(--muted-foreground))] md:inline-flex">
      {{ isMac ? '⌘' : 'Ctrl' }}K
    </kbd>
  </button>

  <!-- Search dialog -->
  <Teleport to="body">
    <Transition name="search-modal">
      <div v-if="open" class="fixed inset-0 z-50" role="dialog" aria-modal="true" :aria-label="t('global_search')" @keydown="onDialogKeydown">
        <!-- Overlay -->
        <div class="fixed inset-0 bg-black/50" @click="closeSearch" />

        <!-- Dialog -->
        <div class="fixed left-1/2 top-[20%] z-50 w-full max-w-lg -translate-x-1/2">
          <div class="rounded-xl border border-[hsl(var(--border))] bg-[hsl(var(--background))] shadow-2xl">
            <!-- Search input -->
            <div class="flex items-center border-b border-[hsl(var(--border))] px-4">
              <Search class="h-4 w-4 shrink-0 text-[hsl(var(--muted-foreground))]" />
              <input
                ref="inputRef"
                v-model="query"
                type="text"
                :placeholder="t('global_search_placeholder')"
                class="flex h-12 w-full bg-transparent px-3 text-sm outline-none placeholder:text-[hsl(var(--muted-foreground))]"
              />
              <Loader2 v-if="loading" class="h-4 w-4 shrink-0 animate-spin text-[hsl(var(--muted-foreground))]" />
              <button v-else-if="query" class="shrink-0 text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]" @click="query = ''">
                <X class="h-4 w-4" />
              </button>
            </div>

            <!-- Quick links (shown when query is empty) -->
            <div v-if="showQuickLinks" class="max-h-80 overflow-y-auto p-2">
              <div class="px-2 py-1.5 text-xs font-medium text-[hsl(var(--muted-foreground))]">
                {{ t('go_to') }}
              </div>
              <button
                v-for="(item, idx) in navigationItems"
                :key="item.id"
                class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors"
                :class="[
                  idx === selectedIndex
                    ? 'bg-[hsl(var(--accent))] text-[hsl(var(--accent-foreground))]'
                    : 'text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))]'
                ]"
                @click="navigateTo(item)"
                @mouseenter="selectedIndex = idx"
              >
                <component :is="item.icon" class="h-4 w-4 shrink-0 text-[hsl(var(--muted-foreground))]" />
                <span class="truncate font-medium">{{ item.title }}</span>
              </button>
            </div>

            <!-- Results -->
            <div v-if="query.length >= 2" class="max-h-80 overflow-y-auto p-2">
              <template v-if="Object.keys(groupedResults).length > 0">
                <div v-for="(items, type) in groupedResults" :key="type" class="mb-2 last:mb-0">
                  <div class="px-2 py-1.5 text-xs font-medium text-[hsl(var(--muted-foreground))]">
                    {{ typeConfig[type]?.label() ?? type }}
                  </div>
                  <button
                    v-for="(result, idx) in items"
                    :key="result.id"
                    class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors"
                    :class="[
                      flatResults.indexOf(result) === selectedIndex
                        ? 'bg-[hsl(var(--accent))] text-[hsl(var(--accent-foreground))]'
                        : 'text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))]'
                    ]"
                    @click="navigateTo(result)"
                    @mouseenter="selectedIndex = flatResults.indexOf(result)"
                  >
                    <component
                      :is="typeConfig[type]?.icon ?? FileText"
                      class="h-4 w-4 shrink-0"
                      :class="typeConfig[type]?.color"
                    />
                    <div class="min-w-0 flex-1">
                      <div class="truncate font-medium">{{ result.title }}</div>
                      <div v-if="result.subtitle" class="truncate text-xs text-[hsl(var(--muted-foreground))]">
                        {{ result.subtitle }}
                      </div>
                    </div>
                    <span
                      v-if="result.status"
                      class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium capitalize"
                      :class="{
                        'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300': result.status === 'paid' || result.status === 'posted',
                        'bg-yellow-100 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300': result.status === 'sent' || result.status === 'approved' || result.status === 'pending',
                        'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300': result.status === 'overdue',
                        'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400': result.status === 'draft' || result.status === 'cancelled',
                      }"
                    >
                      {{ result.status }}
                    </span>
                  </button>
                </div>
              </template>

              <div v-else-if="!loading" class="py-6 text-center text-sm text-[hsl(var(--muted-foreground))]">
                {{ t('no_results_found') }}
              </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between border-t border-[hsl(var(--border))] px-4 py-2 text-xs text-[hsl(var(--muted-foreground))]">
              <div class="flex items-center gap-2">
                <kbd class="rounded border border-[hsl(var(--border))] bg-[hsl(var(--muted))] px-1.5 py-0.5 font-mono text-[10px]">↑↓</kbd>
                <span>{{ t('navigate') }}</span>
                <kbd class="rounded border border-[hsl(var(--border))] bg-[hsl(var(--muted))] px-1.5 py-0.5 font-mono text-[10px]">↵</kbd>
                <span>{{ t('open') }}</span>
              </div>
              <div>
                <kbd class="rounded border border-[hsl(var(--border))] bg-[hsl(var(--muted))] px-1.5 py-0.5 font-mono text-[10px]">esc</kbd>
                <span class="ml-1">{{ t('close') }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.search-modal-enter-active,
.search-modal-leave-active {
  transition: opacity 0.15s ease;
}
.search-modal-enter-from,
.search-modal-leave-to {
  opacity: 0;
}
</style>
