import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs) {
  return twMerge(clsx(inputs))
}

const localeMap = {
  en: 'en-CH',
  fr: 'fr-CH',
  de: 'de-CH',
  it: 'it-CH',
}

export function intlLocale(appLocale) {
  return localeMap[appLocale] || 'de-CH'
}

export function formatCurrency(amount, currency = 'CHF', locale = 'de-CH') {
  const value = Number(amount)
  const formatted = new Intl.NumberFormat(intlLocale(locale), {
    style: 'currency',
    currency,
  }).format(Number.isFinite(value) ? value : 0)
  // Normalize negative formatting: some Swiss locales produce "CHF-85.50" without a space.
  // Ensure there is always a space between the currency code and the minus sign.
  return formatted.replace(/([A-Z]{3})(-)/, '$1 $2')
}

export const formatMoney = formatCurrency

export function formatDate(date, locale = 'de-CH') {
  if (!date) return ''
  return new Intl.DateTimeFormat(intlLocale(locale)).format(new Date(date))
}
