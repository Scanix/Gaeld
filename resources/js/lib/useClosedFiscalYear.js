import { computed, toValue } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useTranslations } from '@/lib/useTranslations'

/**
 * Composable to check if a given date or year falls in a closed fiscal year.
 *
 * @param {import('vue').MaybeRefOrGetter<string|number|null>} dateOrYear — reactive date string (YYYY-MM-DD) or year number
 * @returns {{ isClosed: import('vue').ComputedRef<boolean>, closedYear: import('vue').ComputedRef<number|null> }}
 */
export function useClosedFiscalYear(dateOrYear) {
  const page = usePage()
  const { t } = useTranslations()

  const closedYears = computed(() => {
    return page.props.auth?.currentOrganization?.closed_fiscal_years ?? []
  })

  const resolvedYear = computed(() => {
    const val = toValue(dateOrYear)
    if (val == null || val === '') return null
    if (typeof val === 'number') return val
    // Extract year from date string (YYYY-MM-DD)
    const year = parseInt(String(val).slice(0, 4), 10)
    return Number.isFinite(year) ? year : null
  })

  const isClosed = computed(() => {
    const year = resolvedYear.value
    if (year == null) return false
    return closedYears.value.includes(year)
  })

  const closedYear = computed(() => {
    return isClosed.value ? resolvedYear.value : null
  })

  return { isClosed, closedYear, closedYears }
}
