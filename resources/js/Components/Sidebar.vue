<script setup>
import { computed, ref } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import {
  LayoutDashboard,
  FileText,
  Receipt,
  BarChart3,
  BookOpen,
  HelpCircle,
  Landmark,
  Building2,
  ChevronLeft,
  ChevronRight,
  ChevronDown,
  Check,
  Users,
  X,
  CreditCard,
  Repeat,
  Package,
  Briefcase,
  Settings,
  Sun,
  Moon,
} from 'lucide-vue-next'
import { useTranslations } from '@/lib/useTranslations'
import { usePermissions } from '@/lib/usePermissions'
import { useTheme } from '@/lib/useTheme'
import { normalizeSidebarSharedProps } from '@/lib/inertiaContracts'
import Tooltip from '@/Components/UI/Tooltip.vue'

const { t } = useTranslations()
const { can } = usePermissions()
const { isDark, toggleTheme } = useTheme()

const props = defineProps({
  collapsed: { type: Boolean, default: false },
  mobileOpen: { type: Boolean, default: false },
  helpPage: { type: String, default: null },
  docsUrl: { type: String, default: null },
})

const emit = defineEmits(['update:collapsed', 'closeMobile', 'toggleHelp', 'toggleDocs'])

const page = usePage()
const sharedProps = computed(() => normalizeSidebarSharedProps(page.props))
const features = computed(() => sharedProps.value.features)
const routeCapabilities = computed(() => sharedProps.value.routeCapabilities)
const accountingRoutes = computed(() => routeCapabilities.value.accounting ?? {})

const showOrgMenu = ref(false)
const currentOrg = computed(() => page.props.auth?.currentOrganization)
const organizations = computed(() => page.props.auth?.organizations ?? [])
const hasMultipleOrgs = computed(() => organizations.value.length > 1)

function switchOrg(orgId) {
  showOrgMenu.value = false
  const form = useForm({})
  form.post(`/organizations/${orgId}/switch`)
}

// Collapsible sidebar sections with localStorage persistence
const STORAGE_KEY = 'gaeld-sidebar-expanded'
function loadExpandedGroups() {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    return stored ? new Set(JSON.parse(stored)) : new Set()
  } catch { return new Set() }
}
const expandedGroups = ref(loadExpandedGroups())

function toggleGroup(key) {
  const s = new Set(expandedGroups.value)
  if (s.has(key)) s.delete(key)
  else s.add(key)
  expandedGroups.value = s
  localStorage.setItem(STORAGE_KEY, JSON.stringify([...s]))
}

function isExpanded(item) {
  return expandedGroups.value.has(item.key) || isGroupActive(item)
}

const businessType = computed(() => currentOrg.value?.business_type)

const navigation = computed(() => {
  const bt = businessType.value
  const isFidu = bt === 'fiduciary'
  const isFreelancer = bt === 'freelancer'

  return [
    { key: 'dashboard', href: '/', icon: LayoutDashboard },
    // ── Activity ──
    { type: 'group', label: 'nav_activity' },
    ...(!isFidu ? [
      { key: 'invoices', href: '/invoices', icon: FileText, children: [
        { key: 'invoices', href: '/invoices' },
        { key: 'recurring', href: '/invoices/recurring', icon: Repeat },
      ]},
      { key: 'expenses', href: '/expenses', icon: Receipt, children: [
        { key: 'expenses', href: '/expenses' },
        { key: 'recurring', href: '/expenses/recurring', icon: Repeat },
      ]},
      { key: 'contacts', href: '/contacts', icon: Users },
    ] : []),
    ...(isFidu ? [
      { key: 'contacts', href: '/contacts', icon: Users },
    ] : []),
    // ── Finances ──
    { type: 'group', label: 'nav_finances' },
    { key: 'banking', href: '/banking', icon: Landmark, children: [
      { key: 'bank_accounts', href: '/banking' },
      { key: 'reconciliation', href: '/reconciliation' },
      { key: 'payments_outgoing', href: '/payments/outgoing' },
    ]},
    { key: 'accounting', href: '/accounting/journal-entries', icon: BookOpen, children: [
      { key: 'journal_entries', href: '/accounting/journal-entries' },
      ...(can('accounting.create') ? [
        { key: 'opening_balances', href: '/accounting/opening-balances' },
      ] : []),
      { key: 'chart_of_accounts', href: '/accounting/chart-of-accounts' },
      { key: 'vat_rates', href: '/accounting/vat-rates' },
      ...(features.value.social_charges ? [
        { key: 'social_charges', href: '/accounting/social-charges' },
      ] : []),
      ...(features.value.tax_declaration && accountingRoutes.value.taxDeclarations ? [
        { key: 'tax_declarations', href: '/accounting/tax-declarations' },
      ] : []),
      ...(features.value.budgets ? [
        { key: 'budget', href: '/accounting/budgets' },
      ] : []),
      ...(features.value.year_end_closing && can('accounting.close-year') ? [
        { key: 'fiscal_years', href: '/accounting/fiscal-years' },
        { key: 'year_end_closing', href: '/accounting/year-end-closing' },
      ] : []),
      ...(features.value.account_matching ? [
        { key: 'lettrage', href: '/accounting/account-matching' },
      ] : []),
      ...(features.value.fiduciary_export ? [
        { key: 'fiduciary_export', href: '/accounting/export' },
      ] : []),
      ...(features.value.legal_archives && can('accounting.view') ? [
        { key: 'legal_archives', href: '/accounting/archives' },
      ] : []),
      // Advanced (feature-gated)
      ...(features.value.analytical && accountingRoutes.value.costCenters ? [
        { key: 'cost_centers', href: '/accounting/cost-centers' },
      ] : []),
      ...(features.value.consolidation && accountingRoutes.value.consolidation ? [
        { key: 'consolidation', href: '/accounting/consolidation' },
      ] : []),
      ...(features.value.multi_currency && accountingRoutes.value.exchangeRates ? [
        { key: 'exchange_rates', href: '/accounting/exchange-rates' },
      ] : []),
    ]},
    { key: 'reports', href: '/reports/profit-and-loss', icon: BarChart3, children: [
      { key: 'profit_and_loss', href: '/reports/profit-and-loss' },
      { key: 'balance_sheet', href: '/reports/balance-sheet' },
      { key: 'cash_flow', href: '/reports/cash-flow' },
      { key: 'trial_balance', href: '/accounting/trial-balance' },
      { key: 'vat_report', href: '/reports/vat' },
      { key: 'aging_report', href: '/reports/aging' },
      ...(features.value.analytical && accountingRoutes.value.analyticalReport ? [
        { key: 'analytical_report', href: '/accounting/analytical-report' },
      ] : []),
    ]},
    ...(features.value.assets ? [
      { key: 'assets', href: '/assets', icon: Package },
    ] : []),
    // ── Management ──
    { type: 'group', label: 'nav_management' },
    ...(features.value.payroll ? [
      { key: 'payroll', href: '/payroll/employees', icon: Briefcase, children: [
        { key: 'employees', href: '/payroll/employees' },
        { key: 'salary_slips', href: '/payroll/salary-slips' },
        { key: 'run_payroll', href: '/payroll/run' },
        ...(features.value.withholding_tax ? [
          { key: 'withholding_tax', href: '/payroll/withholding-tax' },
        ] : []),
      ]},
    ] : []),
    ...(!isFreelancer ? [
      { key: 'organization', href: '/organizations', icon: Building2 },
    ] : []),
    { key: 'settings', href: '/settings', icon: Settings, children: [
      { key: 'settings_general', href: '/settings' },
      ...(can('migration.import') ? [
        { key: 'data_migration', href: '/migration' },
      ] : []),
      ...(can('organization.view-audit-log') ? [
        { key: 'activity_log', href: '/settings/activity-log' },
      ] : []),
      ...(features.value.api_access ? [
        { key: 'api_tokens', href: '/settings/api-tokens' },
        { key: 'webhooks', href: '/settings/webhooks' },
      ] : []),
    ]},
  ]
})

const billingNav = computed(() =>
  features.value.saas ? [{ key: 'billing', href: '/billing', icon: CreditCard }] : []
)

function isActive(href) {
  const currentPath = (page.url || '/').split('?')[0]
  if (href === '/') return currentPath === '/'

  return currentPath === href || currentPath.startsWith(`${href}/`)
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
        :aria-label="t('close_navigation')"
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
      <template v-for="item in navigation" :key="item.key || item.label">
        <!-- Section group label -->
        <div
          v-if="item.type === 'group'"
          class="px-3 pt-4 pb-1 text-[10px] font-semibold uppercase tracking-wider text-[hsl(var(--muted-foreground))]"
          :class="collapsed ? 'sr-only' : ''"
        >
          {{ t(item.label) }}
        </div>

        <div v-else-if="item.children">
          <Tooltip :content="collapsed ? t(item.key) : ''" side="right">
            <div class="flex items-center">
              <Link
                :href="item.href"
                :aria-label="collapsed ? t(item.key) : undefined"
                :aria-current="isGroupActive(item) ? 'page' : undefined"
                :class="[
                  'flex flex-1 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                  isGroupActive(item)
                    ? 'bg-[hsl(var(--accent))] text-[hsl(var(--accent-foreground))]'
                    : 'text-[hsl(var(--sidebar-foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))]',
                ]"
              >
                <component :is="item.icon" class="h-4 w-4 shrink-0" />
                <span v-if="!collapsed">{{ t(item.key) }}</span>
              </Link>
              <button
                v-if="!collapsed"
                class="rounded p-1 text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] transition-colors"
                :aria-label="isExpanded(item) ? t('collapse') : t('expand')"
                @click.prevent="toggleGroup(item.key)"
              >
                <ChevronDown
                  class="h-3.5 w-3.5 transition-transform duration-200"
                  :class="isExpanded(item) ? '' : '-rotate-90'"
                />
              </button>
            </div>
          </Tooltip>
          <div v-if="!collapsed && isExpanded(item)" class="ml-7 mt-1 space-y-0.5">
            <template v-for="child in item.children" :key="child.key || child.label">
              <div
                v-if="child.type === 'separator'"
                class="px-3 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-[hsl(var(--muted-foreground))]"
              >
                {{ t(child.label) }}
              </div>
              <Link
                v-else
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
            </template>
          </div>
        </div>

        <Tooltip v-else :content="collapsed ? t(item.key) : ''" side="right">
          <Link
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
        </Tooltip>
      </template>

      <!-- Billing (SaaS only) -->
      <template v-if="billingNav.length">
        <div class="my-1 border-t border-[hsl(var(--sidebar-border))]" />
        <Tooltip
          v-for="item in billingNav"
          :key="item.key"
          :content="collapsed ? t(item.key) : ''"
          side="right"
        >
          <Link
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
        </Tooltip>
      </template>
    </nav>

    <!-- Mobile utility actions: theme, help, docs (desktop uses topbar) -->
    <div class="border-t border-[hsl(var(--sidebar-border))] p-3 space-y-1 lg:hidden">
      <button
        class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-[hsl(var(--sidebar-foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))] transition-colors"
        :aria-label="isDark ? t('light_mode') : t('dark_mode')"
        @click="toggleTheme"
      >
        <Sun v-if="isDark" class="h-4 w-4 shrink-0" />
        <Moon v-else class="h-4 w-4 shrink-0" />
        <span>{{ isDark ? t('light_mode') : t('dark_mode') }}</span>
      </button>
      <button
        v-if="helpPage"
        class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-[hsl(var(--sidebar-foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))] transition-colors"
        @click="emit('toggleHelp'); emit('closeMobile')"
      >
        <HelpCircle class="h-4 w-4 shrink-0" />
        <span>{{ t('help') }}</span>
      </button>
      <button
        v-if="docsUrl"
        class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-[hsl(var(--sidebar-foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))] transition-colors"
        @click="emit('toggleDocs'); emit('closeMobile')"
      >
        <BookOpen class="h-4 w-4 shrink-0" />
        <span>{{ t('documentation') }}</span>
      </button>
    </div>

    <!-- Collapse toggle (desktop only) -->
    <div class="hidden border-t border-[hsl(var(--sidebar-border))] p-3 lg:block">
      <button
        class="flex w-full items-center justify-center rounded-lg p-2 text-[hsl(var(--muted-foreground))] transition-colors hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))]"
        :aria-label="collapsed ? t('expand_sidebar') : t('collapse_sidebar')"
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
