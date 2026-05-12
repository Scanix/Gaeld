import { useTranslations } from '@/lib/useTranslations'

/**
 * Build options for the account selectors used across the app.
 *
 * Each input account is expected to have at least { id, code, display_name? , name, type? }.
 * `display_name` is provided by the Account model accessor; we fall back to `name`
 * for legacy payloads.
 *
 * Options shape:  { value, label, group }
 *   - label  : "1020 — Bank Account CHF"
 *   - group  : translated AccountType label, used by SearchableSelect's groupBy.
 *
 * Pass an optional `filter` (e.g. (a) => a.type === 'expense') to restrict.
 */
export function buildAccountOptions(accounts, { filter, includeGroup = true } = {}) {
  const { t } = useTranslations()

  const groupLabel = (type) => {
    switch (type) {
      case 'asset':
        return t('account_type_asset')
      case 'liability':
        return t('account_type_liability')
      case 'equity':
        return t('account_type_equity')
      case 'revenue':
        return t('account_type_revenue')
      case 'expense':
        return t('account_type_expense')
      default:
        return ''
    }
  }

  const list = filter ? accounts.filter(filter) : accounts.slice()

  // Sort by code so groups stay contiguous (Asset 1xxx, Liability 2xxx, …).
  list.sort((a, b) => String(a.code).localeCompare(String(b.code)))

  return list.map((a) => ({
    value: a.id,
    label: `${a.code} — ${a.display_name ?? a.name}`,
    group: includeGroup ? groupLabel(a.type) : '',
  }))
}
