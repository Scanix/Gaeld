<script setup>
import { ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
  LayoutDashboard,
  FileText,
  Receipt,
  BarChart3,
  BookOpen,
  Landmark,
  Building2,
  ChevronLeft,
  ChevronRight,
} from 'lucide-vue-next'

const collapsed = ref(false)

const page = usePage()

const navigation = [
  { name: 'Dashboard', href: '/', icon: LayoutDashboard },
  { name: 'Invoices', href: '/invoices', icon: FileText },
  { name: 'Expenses', href: '/expenses', icon: Receipt },
  { name: 'Accounting', href: '/accounting/chart-of-accounts', icon: BookOpen, children: [
    { name: 'Chart of Accounts', href: '/accounting/chart-of-accounts' },
    { name: 'Journal Entries', href: '/accounting/journal-entries' },
    { name: 'Trial Balance', href: '/accounting/trial-balance' },
  ]},
  { name: 'Reports', href: '/reports/profit-and-loss', icon: BarChart3, children: [
    { name: 'Profit & Loss', href: '/reports/profit-and-loss' },
    { name: 'Balance Sheet', href: '/reports/balance-sheet' },
  ]},
  { name: 'Banking', href: '/banking', icon: Landmark },
  { name: 'Organization', href: '/organizations', icon: Building2 },
]

function isActive(href) {
  const url = page.url
  if (href === '/') return url === '/'
  return url.startsWith(href)
}

function isGroupActive(item) {
  if (item.children) {
    return item.children.some(c => isActive(c.href))
  }
  return isActive(item.href)
}
</script>

<template>
  <aside
    :class="[
      'fixed inset-y-0 left-0 z-40 flex flex-col border-r border-[hsl(var(--sidebar-border))] bg-[hsl(var(--sidebar))] transition-all duration-200',
      collapsed ? 'w-16' : 'w-60',
    ]"
  >
    <!-- Logo -->
    <div class="flex h-14 items-center border-b border-[hsl(var(--sidebar-border))] px-4">
      <Link href="/" class="flex items-center gap-2">
        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))] text-sm font-bold">
          G
        </div>
        <span v-if="!collapsed" class="text-lg font-semibold text-[hsl(var(--sidebar-foreground))]">Gäld</span>
      </Link>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto p-3 space-y-1">
      <template v-for="item in navigation" :key="item.name">
        <div v-if="item.children">
          <Link
            :href="item.href"
            :class="[
              'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
              isGroupActive(item)
                ? 'bg-[hsl(var(--accent))] text-[hsl(var(--accent-foreground))]'
                : 'text-[hsl(var(--sidebar-foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))]',
            ]"
          >
            <component :is="item.icon" class="h-4 w-4 shrink-0" />
            <span v-if="!collapsed">{{ item.name }}</span>
          </Link>
          <div v-if="!collapsed && isGroupActive(item)" class="ml-7 mt-1 space-y-1">
            <Link
              v-for="child in item.children"
              :key="child.name"
              :href="child.href"
              :class="[
                'block rounded-md px-3 py-1.5 text-xs font-medium transition-colors',
                isActive(child.href)
                  ? 'text-[hsl(var(--primary))]'
                  : 'text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--sidebar-foreground))]',
              ]"
            >
              {{ child.name }}
            </Link>
          </div>
        </div>

        <Link
          v-else
          :href="item.href"
          :class="[
            'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
            isActive(item.href)
              ? 'bg-[hsl(var(--accent))] text-[hsl(var(--accent-foreground))]'
              : 'text-[hsl(var(--sidebar-foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))]',
          ]"
        >
          <component :is="item.icon" class="h-4 w-4 shrink-0" />
          <span v-if="!collapsed">{{ item.name }}</span>
        </Link>
      </template>
    </nav>

    <!-- Collapse toggle -->
    <div class="border-t border-[hsl(var(--sidebar-border))] p-3">
      <button
        class="flex w-full items-center justify-center rounded-lg p-2 text-[hsl(var(--muted-foreground))] transition-colors hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))]"
        @click="collapsed = !collapsed"
      >
        <ChevronLeft v-if="!collapsed" class="h-4 w-4" />
        <ChevronRight v-else class="h-4 w-4" />
      </button>
    </div>
  </aside>
</template>
