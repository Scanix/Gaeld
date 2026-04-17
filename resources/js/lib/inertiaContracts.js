import { z } from 'zod'

const moneyValue = z.union([z.number(), z.string()])

const dashboardSchema = z.object({
  revenue: moneyValue.optional(),
  expenses: moneyValue.optional(),
  balance: moneyValue.optional(),
  cashBalance: moneyValue.optional(),
  unpaidInvoices: z.object({
    count: z.coerce.number().optional(),
    total: moneyValue.optional(),
  }).passthrough().optional(),
  pendingExpenses: z.object({
    count: z.coerce.number().optional(),
    total: moneyValue.optional(),
  }).passthrough().optional(),
  previousRevenue: moneyValue.optional(),
  previousExpenses: moneyValue.optional(),
  previousBalance: moneyValue.optional(),
  budgetSummary: z.record(z.string(), z.unknown()).nullable().optional(),
  vatSummary: z.record(z.string(), z.unknown()).nullable().optional(),
  receivablesAging: z.record(z.string(), z.unknown()).nullable().optional(),
  recentTransactions: z.array(z.record(z.string(), z.unknown())).optional(),
  monthlyBreakdown: z.object({
    monthIndices: z.array(z.coerce.number()).optional(),
    revenue: z.array(z.coerce.number()).optional(),
    expenses: z.array(z.coerce.number()).optional(),
    forecast: z.array(z.coerce.number()).optional(),
    revenueItems: z.array(z.array(z.string())).optional(),
    expenseItems: z.array(z.array(z.string())).optional(),
    forecastItems: z.array(z.array(z.string())).optional(),
  }).passthrough().optional(),
  checklist: z.object({
    getting_started: z.array(z.unknown()).optional(),
    accounting: z.array(z.unknown()).optional(),
  }).passthrough().optional(),
  pendingOcrScans: z.coerce.number().optional(),
  displayYear: z.coerce.number().optional(),
}).passthrough()

const agingRowSchema = z.object({
  id: z.union([z.number(), z.string()]),
  name: z.string().optional(),
  document_number: z.string().nullable().optional(),
  date: z.string().nullable().optional(),
  due_date: z.string().nullable().optional(),
  current: z.coerce.number().optional(),
  b1_30: z.coerce.number().optional(),
  b31_60: z.coerce.number().optional(),
  b61_90: z.coerce.number().optional(),
  b90plus: z.coerce.number().optional(),
}).passthrough()

const agingSchema = z.object({
  type: z.enum(['receivables', 'payables']).optional(),
  report: z.object({
    type: z.enum(['receivables', 'payables']).optional(),
    rows: z.array(agingRowSchema).optional(),
  }).passthrough().optional(),
}).passthrough()

const reconciliationSchema = z.object({
  bankAccount: z.object({
    id: z.union([z.number(), z.string()]).optional(),
    uuid: z.string().optional(),
    name: z.string().optional(),
    balance: moneyValue.optional(),
    currency: z.string().optional(),
    is_mixed_use: z.boolean().optional(),
  }).passthrough().optional(),
  transactions: z.object({
    data: z.array(z.record(z.string(), z.unknown())).optional(),
    current_page: z.coerce.number().optional(),
    last_page: z.coerce.number().optional(),
    prev_page_url: z.string().nullable().optional(),
    next_page_url: z.string().nullable().optional(),
  }).passthrough().optional(),
  suggestions: z.record(z.string(), z.object({
    invoices: z.array(z.record(z.string(), z.unknown())).optional(),
    expenses: z.array(z.record(z.string(), z.unknown())).optional(),
  }).passthrough()).optional(),
  personalSuggestions: z.array(z.union([z.number(), z.string()])).optional(),
  filter: z.enum(['unreconciled', 'reconciled', 'all']).optional(),
  openInvoices: z.array(z.record(z.string(), z.unknown())).optional(),
  pageFeatures: z.object({
    auto_reconciliation: z.boolean().optional(),
  }).passthrough().optional(),
}).passthrough()

const sharedSidebarSchema = z.object({
  features: z.record(z.string(), z.unknown()).optional(),
  routeCapabilities: z.object({
    accounting: z.object({
      taxDeclarations: z.boolean().optional(),
      costCenters: z.boolean().optional(),
      analyticalReport: z.boolean().optional(),
      consolidation: z.boolean().optional(),
      exchangeRates: z.boolean().optional(),
    }).passthrough().optional(),
  }).passthrough().optional(),
}).passthrough()

const DASHBOARD_FALLBACK = {
  revenue: 0,
  expenses: 0,
  balance: 0,
  cashBalance: 0,
  unpaidInvoices: { count: 0, total: 0 },
  pendingExpenses: { count: 0, total: 0 },
  previousRevenue: 0,
  previousExpenses: 0,
  previousBalance: 0,
  budgetSummary: null,
  vatSummary: null,
  receivablesAging: null,
  recentTransactions: [],
  monthlyBreakdown: {
    monthIndices: [],
    revenue: [],
    expenses: [],
    forecast: [],
    revenueItems: [],
    expenseItems: [],
    forecastItems: [],
  },
  checklist: { getting_started: [], accounting: [] },
  pendingOcrScans: 0,
  displayYear: new Date().getFullYear(),
}

const AGING_FALLBACK = {
  type: 'receivables',
  report: {
    type: 'receivables',
    rows: [],
  },
}

const RECONCILIATION_FALLBACK = {
  bankAccount: {},
  transactions: {
    data: [],
    current_page: 1,
    last_page: 1,
    prev_page_url: null,
    next_page_url: null,
  },
  suggestions: {},
  personalSuggestions: [],
  filter: 'unreconciled',
  openInvoices: [],
  pageFeatures: {
    auto_reconciliation: false,
  },
}

const SIDEBAR_FALLBACK = {
  features: {},
  routeCapabilities: {
    accounting: {
      taxDeclarations: false,
      costCenters: false,
      analyticalReport: false,
      consolidation: false,
      exchangeRates: false,
    },
  },
}

function asObject(value) {
  return value && typeof value === 'object' && !Array.isArray(value) ? value : {}
}

function mergeFallback(fallback, payload) {
  if (Array.isArray(fallback)) {
    return Array.isArray(payload) ? payload : fallback
  }

  if (!fallback || typeof fallback !== 'object') {
    return payload ?? fallback
  }

  const merged = { ...fallback }
  const candidate = asObject(payload)

  for (const key of Object.keys(merged)) {
    if (key in candidate) {
      merged[key] = mergeFallback(merged[key], candidate[key])
    }
  }

  for (const key of Object.keys(candidate)) {
    if (!(key in merged)) {
      merged[key] = candidate[key]
    }
  }

  return merged
}

function normalizeContract(schema, payload, fallback, label) {
  const result = schema.safeParse(payload)
  if (result.success) {
    return mergeFallback(fallback, result.data)
  }

  // Keep app resilient when backend/frontend drift temporarily.
  console.warn(`[contracts] ${label} contract mismatch`, result.error.flatten())

  return mergeFallback(fallback, payload)
}

export function normalizeDashboardContract(payload) {
  return normalizeContract(dashboardSchema, payload, DASHBOARD_FALLBACK, 'dashboard')
}

export function normalizeAgingContract(payload) {
  const normalized = normalizeContract(agingSchema, payload, AGING_FALLBACK, 'aging-report')

  if (!['receivables', 'payables'].includes(normalized.type)) {
    normalized.type = normalized.report?.type ?? 'receivables'
  }

  return normalized
}

export function normalizeReconciliationShowContract(payload) {
  return normalizeContract(reconciliationSchema, payload, RECONCILIATION_FALLBACK, 'reconciliation-show')
}

export function normalizeSidebarSharedProps(payload) {
  return normalizeContract(sharedSidebarSchema, payload, SIDEBAR_FALLBACK, 'sidebar-shared-props')
}
