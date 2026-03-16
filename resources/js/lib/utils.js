import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs) {
  return twMerge(clsx(inputs))
}

export function formatCurrency(amount, currency = 'CHF') {
  return new Intl.NumberFormat('de-CH', {
    style: 'currency',
    currency,
  }).format(amount)
}

export function formatDate(date) {
  if (!date) return ''
  return new Intl.DateTimeFormat('de-CH').format(new Date(date))
}
