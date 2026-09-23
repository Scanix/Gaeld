import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

export function useTranslations() {
  const page = usePage()
  const translations = computed(() => page.props.translations || {})
  const locale = computed(() => page.props.locale || 'en')

  function t(key, replacements = {}) {
    let value = translations.value[key] || key

    // Handle Laravel-style pluralization: {1} singular|[2,*] plural
    if (typeof replacements.count !== 'undefined' && value.includes('|')) {
      const parts = value.split('|')
      value = replacements.count === 1 ? parts[0] : parts[1] || parts[0]
      // Remove Laravel choice markers like {1}, [2,*]
      value = value.replace(/^\{[^}]+\}\s*/, '').replace(/^\[[^\]]+\]\s*/, '')
    }

    // Replace :placeholder with values
    for (const [rKey, rValue] of Object.entries(replacements)) {
      value = value.replace(new RegExp(`:${rKey}`, 'g'), rValue)
    }

    return value
  }

  function expenseCategoryLabel(category) {
    const keys = {
      'Office Supplies': 'cat_office_supplies',
      'Travel': 'cat_travel',
      'Software': 'cat_software',
      'Professional Services': 'cat_professional_services',
      'Marketing': 'cat_marketing',
      'Rent': 'cat_rent',
      'Utilities': 'cat_utilities',
      'Insurance': 'cat_insurance',
      'Other': 'cat_other',
      'Goods Purchased for Resale': 'cat_goods_purchased_for_resale',
    }

    const key = keys[category]

    return key ? t(key) : category
  }

  return { t, locale, expenseCategoryLabel }
}
