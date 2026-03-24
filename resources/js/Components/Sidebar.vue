<script setup>
import { computed } from 'vue'
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
  ArrowLeftRight,
  Users,
  X,
  CreditCard,
} from 'lucide-vue-next'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  collapsed: { type: Boolean, default: false },
  mobileOpen: { type: Boolean, default: false },
})

const emit = defineEmits(['update:collapsed', 'closeMobile'])

const page = usePage()
const features = computed(() => page.props.features ?? {})

const navigation = [
  { key: 'dashboard', href: '/', icon: LayoutDashboard },
  { key: 'invoices', href: '/invoices', icon: FileText },
  { key: 'expenses', href: '/expenses', icon: Receipt },
  { key: 'contacts', href: '/customers', icon: Users, children: [
    { key: 'customers', href: '/customers' },
    { key: 'suppliers', href: '/suppliers' },
  ]},
  { key: 'accounting', href: '/accounting/chart-of-accounts', icon: BookOpen, children: [
    { key: 'chart_of_accounts', href: '/accounting/chart-of-accounts' },
    { key: 'journal_entries', href: '/accounting/journal-entries' },
    { key: 'trial_balance', href: '/accounting/trial-balance' },
    { key: 'year_end_closing', href: '/accounting/year-end-closing' },
  ]},
  { key: 'reports', href: '/reports/profit-and-loss', icon: BarChart3, children: [
    { key: 'profit_and_loss', href: '/reports/profit-and-loss' },
    { key: 'balance_sheet', href: '/reports/balance-sheet' },
  ]},
  { key: 'banking', href: '/banking', icon: Landmark },
  { key: 'reconciliation', href: '/reconciliation', icon: ArrowLeftRight },
  { key: 'organization', href: '/organizations', icon: Building2 },
]

const billingNav = computed(() =>
  features.value.saas ? [{ key: 'billing', href: '/billing', icon: CreditCard }] : []
)

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
  <!-- Mobile backdrop -->
  <Transition name="fade">
    <div
      v-if="mobileOpen"
      class="fixed inset-0 z-30 bg-black/50 lg:hidden"
      @click="emit('closeMobile')"
    />
  </Transition>

  <aside
    :class="[
      'fixed inset-y-0 left-0 z-40 flex flex-col border-r border-[hsl(var(--sidebar-border))] bg-[hsl(var(--sidebar))] transition-all duration-200 w-60',
      collapsed ? 'lg:w-16' : 'lg:w-60',
      mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
    ]"
  >
    <!-- Logo + mobile close -->
    <div class="flex h-14 items-center justify-between border-b border-[hsl(var(--sidebar-border))] px-4">
      <Link href="/" class="flex min-w-0 items-center gap-2">
        <img src="/logo-square.svg" alt="Gäld" class="h-8 w-8 shrink-0 rounded-lg" />
        <img v-if="!collapsed" src="/logo-wide.svg" alt="Gäld" class="h-6 w-auto shrink-0" />
      </Link>
      <button
        class="ml-2 rounded p-1 text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] lg:hidden"
        @click="emit('closeMobile')"
      >
        <X class="h-5 w-5" />
      </button>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto p-3 space-y-1">
      <template v-for="item in navigation" :key="item.key">
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
            <span v-if="!collapsed">{{ t(item.key) }}</span>
          </Link>
          <div v-if="!collapsed && isGroupActive(item)" class="ml-7 mt-1 space-y-1">
            <Link
              v-for="child in item.children"
              :key="child.key"
              :href="child.href"
              :class="[
                'block rounded-md px-3 py-1.5 text-xs font-medium transition-colors',
                isActive(child.href)
                  ? 'text-[hsl(var(--primary))]'
                  : 'text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--sidebar-foreground))]',
              ]"
            >
              {{ t(child.key) }}
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
          <span v-if="!collapsed">{{ t(item.key) }}</span>
        </Link>
      </template>

      <!-- Billing (SaaS only) -->
      <template v-if="billingNav.length">
        <div class="my-1 border-t border-[hsl(var(--sidebar-border))]" />
        <Link
          v-for="item in billingNav"
          :key="item.key"
          :href="item.href"
          :class="[
            'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
            isActive(item.href)
              ? 'bg-[hsl(var(--accent))] text-[hsl(var(--accent-foreground))]'
              : 'text-[hsl(var(--sidebar-foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))]',
          ]"
        >
          <component :is="item.icon" class="h-4 w-4 shrink-0" />
          <span v-if="!collapsed">{{ t(item.key) }}</span>
        </Link>
      </template>
    </nav>

    <!-- Collapse toggle (desktop only) -->
    <div class="hidden border-t border-[hsl(var(--sidebar-border))] p-3 lg:block">
      <button
        class="flex w-full items-center justify-center rounded-lg p-2 text-[hsl(var(--muted-foreground))] transition-colors hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))]"
        @click="emit('update:collapsed', !collapsed)"
      >
        <ChevronLeft v-if="!collapsed" class="h-4 w-4" />
        <ChevronRight v-else class="h-4 w-4" />
      </button>
    </div>
  </aside>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
