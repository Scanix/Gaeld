/**
 * Shared option lists for contact forms (Customers, Suppliers, QuickCreate).
 * Each function accepts a translation function `t` to produce localised labels.
 */

// ISO-3166 alpha-2 codes (excluding the 6 pinned at top).
const ISO_COUNTRY_CODES = [
  'AD','AE','AF','AG','AI','AL','AM','AO','AQ','AR','AS','AU','AW','AX','AZ',
  'BA','BB','BD','BE','BF','BG','BH','BI','BJ','BL','BM','BN','BO','BQ','BR','BS','BT','BV','BW','BY','BZ',
  'CA','CC','CD','CF','CG','CI','CK','CL','CM','CN','CO','CR','CU','CV','CW','CX','CY','CZ',
  'DJ','DK','DM','DO','DZ',
  'EC','EE','EG','EH','ER','ES','ET',
  'FI','FJ','FK','FM','FO',
  'GA','GB','GD','GE','GF','GG','GH','GI','GL','GM','GN','GP','GQ','GR','GS','GT','GU','GW','GY',
  'HK','HM','HN','HR','HT','HU',
  'ID','IE','IL','IM','IN','IO','IQ','IR','IS',
  'JE','JM','JO','JP',
  'KE','KG','KH','KI','KM','KN','KP','KR','KW','KY','KZ',
  'LA','LB','LC','LK','LR','LS','LT','LU','LV','LY',
  'MA','MC','MD','ME','MF','MG','MH','MK','ML','MM','MN','MO','MP','MQ','MR','MS','MT','MU','MV','MW','MX','MY','MZ',
  'NA','NC','NE','NF','NG','NI','NL','NO','NP','NR','NU','NZ',
  'OM',
  'PA','PE','PF','PG','PH','PK','PL','PM','PN','PR','PS','PT','PW','PY',
  'QA',
  'RE','RO','RS','RU','RW',
  'SA','SB','SC','SD','SE','SG','SH','SI','SJ','SK','SL','SM','SN','SO','SR','SS','ST','SV','SX','SY','SZ',
  'TC','TD','TF','TG','TH','TJ','TK','TL','TM','TN','TO','TR','TT','TV','TW','TZ',
  'UA','UG','UM','US','UY','UZ',
  'VA','VC','VE','VG','VI','VN','VU',
  'WF','WS',
  'YE','YT',
  'ZA','ZM','ZW',
]

let _countryListCache = null
let _countryListCacheLocale = null

export function countryOptions(t) {
  // Pinned at top: Switzerland & neighbours (translated). Then full ISO list, sorted by display label in current locale.
  const pinned = [
    { value: 'CH', label: t('country_switzerland') },
    { value: 'LI', label: t('country_liechtenstein') },
    { value: 'DE', label: t('country_germany') },
    { value: 'FR', label: t('country_france') },
    { value: 'IT', label: t('country_italy') },
    { value: 'AT', label: t('country_austria') },
  ]
  const pinnedCodes = new Set(pinned.map(p => p.value))

  let dn = null
  let locale = 'en'
  try {
    dn = new Intl.DisplayNames(undefined, { type: 'region' })
    locale = (typeof navigator !== 'undefined' && navigator.language) || 'en'
  } catch {
    // older browsers — fall back to ISO codes as labels
  }

  if (_countryListCache && _countryListCacheLocale === locale) {
    return [...pinned, ..._countryListCache]
  }

  const rest = ISO_COUNTRY_CODES
    .filter(code => !pinnedCodes.has(code))
    .map(code => ({ value: code, label: dn ? (dn.of(code) || code) : code }))
    .sort((a, b) => a.label.localeCompare(b.label, locale))

  _countryListCache = rest
  _countryListCacheLocale = locale
  return [...pinned, ...rest]
}

/** Resolve an ISO-3166 alpha-2 code to a display label using the localised list. */
export function countryLabel(code, t) {
  if (!code) return ''
  const found = countryOptions(t).find(c => c.value === code)
  if (found) return found.label
  // Fallback: try Intl.DisplayNames for any ISO code we didn't pin.
  try {
    const dn = new Intl.DisplayNames(undefined, { type: 'region' })
    return dn.of(code) || code
  } catch {
    return code
  }
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
