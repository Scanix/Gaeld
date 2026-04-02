/**
 * Shared option lists for contact forms (Customers, Suppliers, QuickCreate).
 * Each function accepts a translation function `t` to produce localised labels.
 */

export function countryOptions(t) {
  return [
    { value: 'CH', label: t('country_switzerland') },
    { value: 'DE', label: t('country_germany') },
    { value: 'AT', label: t('country_austria') },
    { value: 'FR', label: t('country_france') },
    { value: 'IT', label: t('country_italy') },
    { value: 'LI', label: t('country_liechtenstein') },
  ]
}

export function currencyOptions(t) {
  return [
    { value: 'CHF', label: t('chf_label') },
    { value: 'EUR', label: t('eur_label') },
    { value: 'USD', label: t('usd_label') },
    { value: 'GBP', label: t('gbp_label') },
  ]
}

export function supplierCategoryOptions(t) {
  return [
    { value: '', label: '—' },
    { value: 'office', label: t('cat_office') },
    { value: 'utilities', label: t('cat_utilities') },
    { value: 'software', label: t('cat_software') },
    { value: 'travel', label: t('cat_travel') },
    { value: 'marketing', label: t('cat_marketing') },
    { value: 'professional_services', label: t('cat_professional_services') },
    { value: 'equipment', label: t('cat_equipment') },
    { value: 'other', label: t('cat_other') },
  ]
}
