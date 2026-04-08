/**
 * Strip spaces from an IBAN and uppercase it.
 */
export function normalizeIban(iban) {
  return (iban || '').replace(/\s+/g, '').toUpperCase()
}

/**
 * Check if a normalized IBAN looks like a Swiss/LI QR-IBAN.
 * QR-IBANs have an IID (positions 5-9, 0-indexed 4-8) in range 30000–31999.
 */
export function isQrIban(iban) {
  const normalized = normalizeIban(iban)
  if (normalized.length < 9) return false
  if (!/^(CH|LI)/.test(normalized)) return false
  const iid = parseInt(normalized.substring(4, 9), 10)
  return iid >= 30000 && iid <= 31999
}

/**
 * Check if a normalized IBAN looks like a valid Swiss/LI IBAN (by prefix and length).
 */
export function isSwissIban(iban) {
  const normalized = normalizeIban(iban)
  return /^(CH|LI)/.test(normalized) && normalized.length === 21
}

/**
 * Detect the type of IBAN entered.
 * Returns: 'qr-iban' | 'regular-iban' | 'foreign-iban' | 'incomplete' | 'empty'
 */
export function detectIbanType(iban) {
  const normalized = normalizeIban(iban)
  if (!normalized) return 'empty'
  if (normalized.length < 5) return 'incomplete'
  if (/^(CH|LI)/.test(normalized)) {
    if (normalized.length < 9) return 'incomplete'
    if (normalized.length !== 21) return 'incomplete'
    return isQrIban(normalized) ? 'qr-iban' : 'regular-iban'
  }
  return 'foreign-iban'
}
