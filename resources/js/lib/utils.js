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
  return new Intl.NumberFormat(intlLocale(locale), {
    style: 'currency',
    currency,
  }).format(amount)
}

export const formatMoney = formatCurrency

export function formatDate(date, locale = 'de-CH') {
  if (!date) return ''
  return new Intl.DateTimeFormat(intlLocale(locale)).format(new Date(date))
}
