import { getGoogleRuntime, readConsent } from './lib/googleConsent.js'

/** Runs only on the standalone page; all business facts come from the server. */
export async function confirmGoogleAds(config, continueUrl, claimUrl, browser = window) {
  let destination = '/welcome'
  let claim = null
  try {
    const next = new URL(continueUrl, browser.location.origin)
    if (next.origin === browser.location.origin && ['/welcome', '/billing'].includes(next.pathname) && !next.search && !next.hash)
      destination = next.pathname
    const url = new URL(claimUrl, browser.location.origin)
    if (url.origin === browser.location.origin && url.pathname === '/conversions/claim' && !url.search && !url.hash)
      claim = url.pathname
  } catch { /* Only fixed same-origin routes are used. */ }
  const proceed = () => browser.location.replace(destination)
  if (!/^AW-\d+$/.test(config?.adsId || '') ||
    ![config?.signupLabel, config?.firstPaymentLabel].every((label) => typeof label === 'string' && /^[A-Za-z0-9_-]{1,100}$/.test(label))) {
    proceed(); return
  }
  if (browser.location.pathname !== '/conversions/confirm' || browser.location.search || browser.location.hash ||
    !claim) { proceed(); return }

  if (browser.document.referrer) {
    let samePage = false
    try {
      const previous = new URL(browser.document.referrer)
      samePage = previous.origin === browser.location.origin && previous.pathname === '/conversions/confirm'
    } catch { /* A malformed referrer cannot be sent to Google. */ }
    // This response has Referrer-Policy:no-referrer. A clean full navigation
    // avoids exposing the previous authenticated page to the SDK.
    browser.location.replace(samePage ? destination : '/conversions/confirm')
    return
  }
  const runtime = getGoogleRuntime({ ...config, gtmId: '', allowedPaths: ['/conversions/confirm'] }, browser)
  if (!config.statusUrl) runtime.refresh()
  if (!readConsent(browser.document.cookie).marketing) { proceed(); return }
  const delay = (milliseconds) => new Promise((resolve) => browser.setTimeout(resolve, milliseconds))
  try {
    if (config.statusUrl) {
      const status = new URL(config.statusUrl, browser.location.origin)
      if (status.origin !== browser.location.origin || status.pathname !== '/conversions/status' || status.search || status.hash) {
        proceed(); return
      }
      const deadline = Date.now() + 60000
      let ready = false
      let available = false
      for (let attempt = 0; attempt < 20 && Date.now() < deadline; attempt++) {
        if (!readConsent(browser.document.cookie).marketing) { proceed(); return }
        const response = await browser.fetch(status.pathname, {
          credentials: 'same-origin', headers: { Accept: 'application/json' },
          signal: AbortSignal.timeout(Math.max(1, Math.min(5000, deadline - Date.now()))),
        })
        if (!response.ok || !readConsent(browser.document.cookie).marketing) { proceed(); return }
        const payload = await response.json()
        available = payload.ready === true
        if (payload.waiting !== true) { ready = available; break }
        await delay(Math.min(3000, Math.max(0, deadline - Date.now())))
      }
      // An older signup may remain available at the bounded end of the wait.
      // The claim still decides which verified server facts can be delivered.
      if (!ready && !available) { proceed(); return }
    }
    if (config.statusUrl) runtime.refresh()
    if (!await runtime.loadAds() || !readConsent(browser.document.cookie).marketing) { proceed(); return }
    const csrf = browser.document.querySelector('meta[name="csrf-token"]')?.content
    if (!csrf) { proceed(); return }
    let payload = null
    for (let attempt = 0; attempt < 3; attempt++) {
      if (!readConsent(browser.document.cookie).marketing) { proceed(); return }
      try {
        const response = await browser.fetch(claim, {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf,
            ...(attempt ? { 'X-Gaeld-Ads-Retry': '1' } : {}) },
          body: '{}', signal: AbortSignal.timeout(18000),
        })
        if (!readConsent(browser.document.cookie).marketing) { proceed(); return }
        if (!response.ok && response.status < 500) { proceed(); return }
        if (response.ok) { payload = await response.json(); break }
      } catch { /* A lost response can be retried with the same server transaction ID. */ }
      if (attempt < 2) await delay(500)
    }
    if (!payload || !readConsent(browser.document.cookie).marketing) { proceed(); return }
    const events = Array.isArray(payload.conversions) ? payload.conversions.slice(0, 2) : []
    await Promise.all(events.map((event) => new Promise((resolve) => {
      const timeout = browser.setTimeout(resolve, 1700)
      const complete = () => { browser.clearTimeout(timeout); resolve() }
      runtime.convert(event, complete).then((sent) => { if (!sent) complete() }, complete)
    })))
  } catch { /* Measurement never blocks access to the application. */ }
  proceed()
}

const element = typeof document === 'undefined' ? null : document.getElementById('gaeld-google-config')
if (element) {
  try {
    const { config, continueUrl, claimUrl } = JSON.parse(element.textContent)
    void confirmGoogleAds(config, continueUrl, claimUrl)
  } catch {
    window.location.replace('/welcome')
  }
}
