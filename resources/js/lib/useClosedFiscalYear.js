import { computed, toValue } from 'vue'
import { usePage } from '@inertiajs/vue3'

/**
 * Composable to check if a given date or year falls in a closed fiscal year.
 *
 * Prefers the new `fiscal_years` array (date-range based, supports long
 * fiscal years) and falls back to the legacy `closed_fiscal_years` integer
 * array when no fiscal year records are available.
 *
 * @param {import('vue').MaybeRefOrGetter<string|number|null>} dateOrYear — reactive date string (YYYY-MM-DD) or year number
 * @returns {{ isClosed: import('vue').ComputedRef<boolean>, closedYear: import('vue').ComputedRef<number|null>, closedYears: import('vue').ComputedRef<number[]> }}
 */
export function useClosedFiscalYear(dateOrYear) {
  const page = usePage()

  const fiscalYears = computed(() => {
    return page.props.auth?.currentOrganization?.fiscal_years ?? []
  })

  const closedYears = computed(() => {
    return page.props.auth?.currentOrganization?.closed_fiscal_years ?? []
  })

  const resolvedDate = computed(() => {
    const val = toValue(dateOrYear)
    if (val == null || val === '') return null
    if (typeof val === 'number') {
      // Treat numeric input as the middle of the calendar year for
      // date-range matching against fiscal_years records.
      return `${val}-06-30`
    }
    return String(val).slice(0, 10)
  })

  const resolvedYear = computed(() => {
    const date = resolvedDate.value
    if (date == null) return null
    const year = parseInt(date.slice(0, 4), 10)
    return Number.isFinite(year) ? year : null
  })

  const matchingFiscalYear = computed(() => {
    const date = resolvedDate.value
    if (date == null || fiscalYears.value.length === 0) return null
    return (
      fiscalYears.value.find(
        (fy) => fy.start_date <= date && date <= fy.end_date,
      ) ?? null
    )
  })

  const isClosed = computed(() => {
    if (matchingFiscalYear.value) {
      return matchingFiscalYear.value.status === 'closed'
    }
    // Legacy fallback for orgs without fiscal_year records.
    const year = resolvedYear.value
    if (year == null) return false
    return closedYears.value.includes(year)
  })

  const closedYear = computed(() => {
    return isClosed.value ? resolvedYear.value : null
  })

  return { isClosed, closedYear, closedYears }
}
