export function stagingMailLink(content, baseUrl, pathPattern) {
  const origin = new URL(baseUrl).origin
  for (const candidate of content?.match(/https?:\/\/[^\s<>"']+/g) ?? []) {
    const url = URL.parse(candidate.replaceAll('&amp;', '&'))
    if (url?.origin === origin && pathPattern.test(url.pathname)) return url.href
  }
  return null
}

export function isAmazonPaymentsUrl(value) {
  return URL.parse(value)?.hostname === 'payments-eu.amazon.com'
}
