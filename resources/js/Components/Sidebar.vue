<script setup>
import { computed, ref } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
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
  ChevronDown,
  Check,
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

const showOrgMenu = ref(false)
const currentOrg = computed(() => page.props.auth?.currentOrganization)
const organizations = computed(() => page.props.auth?.organizations ?? [])
const hasMultipleOrgs = computed(() => organizations.value.length > 1)

function switchOrg(orgId) {
  showOrgMenu.value = false
  const form = useForm({})
  form.post(`/organizations/${orgId}/switch`)
}

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
        aria-label="Close navigation"
        @click="emit('closeMobile')"
      >
        <X class="h-5 w-5" />
      </button>
    </div>

    <!-- Organization Switcher -->
    <div v-if="currentOrg" class="relative border-b border-[hsl(var(--sidebar-border))] px-3 py-2">
      <button
        class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium text-[hsl(var(--sidebar-foreground))] transition-colors hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))]"
        :class="collapsed ? 'justify-center' : ''"
        :aria-expanded="hasMultipleOrgs ? showOrgMenu : undefined"
        :aria-haspopup="hasMultipleOrgs ? 'listbox' : undefined"
        @click="hasMultipleOrgs ? (showOrgMenu = !showOrgMenu) : undefined"
      >
        <Building2 class="h-4 w-4 shrink-0" />
        <span v-if="!collapsed" class="min-w-0 truncate">{{ currentOrg.name }}</span>
        <ChevronDown v-if="!collapsed && hasMultipleOrgs" class="ml-auto h-3 w-3 shrink-0" />
      </button>

      <div
        v-if="showOrgMenu && hasMultipleOrgs"
        :class="[
          'absolute z-50 w-56 rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--background))] p-1 shadow-lg',
          collapsed ? 'left-full top-0 ml-1' : 'left-3 right-3 top-full mt-1 w-auto',
        ]"
        @mouseleave="showOrgMenu = false"
      >
        <button
          v-for="org in organizations"
          :key="org.id"
          class="flex w-full items-center justify-between rounded-md px-3 py-2 text-sm text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))]"
          @click="switchOrg(org.id)"
        >
          <span class="truncate">{{ org.name }}</span>
          <Check v-if="org.id === currentOrg.id" class="ml-2 h-4 w-4 shrink-0 text-[hsl(var(--primary))]" />
        </button>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto p-3 space-y-1">
      <template v-for="item in navigation" :key="item.key">
        <div v-if="item.children">
          <Link
            :href="item.href"
            :aria-label="collapsed ? t(item.key) : undefined"
            :aria-current="isGroupActive(item) ? 'page' : undefined"
            :aria-expanded="!collapsed ? isGroupActive(item) : undefined"
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
          :aria-label="collapsed ? t(item.key) : undefined"
          :aria-current="isActive(item.href) ? 'page' : undefined"
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
          :aria-label="collapsed ? t(item.key) : undefined"
          :aria-current="isActive(item.href) ? 'page' : undefined"
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
        :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
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
