import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { formatCurrency as rawFormatCurrency, formatDate as rawFormatDate, intlLocale } from './utils'

export function useFormatters() {
  const page = usePage()
  const locale = computed(() => page.props.locale || 'en')

  function formatCurrency(amount, currency = 'CHF') {
    return rawFormatCurrency(amount, currency, locale.value)
  }

  function formatDate(date) {
    return rawFormatDate(date, locale.value)
  }

  function intlMonthName(monthIndex) {
    return new Date(2000, monthIndex).toLocaleString(intlLocale(locale.value), { month: 'long' })
  }

  const formatMoney = formatCurrency

  return { formatCurrency, formatMoney, formatDate, intlMonthName, locale }
}
