export const CONSENT_REVISION = 1
export const CONSENT_COOKIE = 'gaeld_cookie_consent'

export function consentChoices(cookie) {
  const valid =
    cookie &&
    cookie.revision === CONSENT_REVISION &&
    Array.isArray(cookie.categories) &&
    cookie.categories.every((item) => typeof item === 'string')
  return {
    analytics: Boolean(valid && cookie.categories.includes('analytics')),
    marketing: Boolean(valid && cookie.categories.includes('marketing')),
  }
}

export function readConsent(cookieHeader = '') {
  const matches = cookieHeader
    .split(';')
    .map((part) => part.trim())
    .filter((part) => part.startsWith(`${CONSENT_COOKIE}=`))
  if (matches.length !== 1) return consentChoices(null)
  try {
    return consentChoices(
      JSON.parse(decodeURIComponent(matches[0].slice(CONSENT_COOKIE.length + 1)))
    )
  } catch {
    return consentChoices(null)
  }
}

export function googleConsent(choices) {
  return {
    analytics_storage: choices.analytics ? 'granted' : 'denied',
    ad_storage: choices.marketing ? 'granted' : 'denied',
    ad_user_data: choices.marketing ? 'granted' : 'denied',
    ad_personalization: 'denied',
  }
}

const CAMPAIGN = /^utm_(?:source|medium|campaign|content|term)$/
const LABEL = /^[\p{L}\p{N}][\p{L}\p{M}\p{N} ._-]{0,99}$/u
const CLICK = /^(?:gclid|gbraid|wbraid)$/

function safeQuery(input, marketing) {
  const result = new URLSearchParams()
  for (const key of new Set(input.keys())) {
    const values = input.getAll(key)
    if (values.length !== 1) continue
    const value = values[0]
    if (key === 'lang' && /^(en|fr|de|it)$/.test(value)) result.set(key, value)
    if (
      CAMPAIGN.test(key) &&
      LABEL.test(value) &&
      !(/^[\d ._-]+$/.test(value) && value.replace(/\D/g, '').length >= 7)
    )
      result.set(key, value)
    if (marketing && CLICK.test(key) && /^[A-Za-z0-9_-]{1,512}$/.test(value)) result.set(key, value)
  }
  return result
}

/** Inspect the actual URL/referrer, not only event parameters read by a tag. */
export function measurementContext(location, referrer, allowedPaths, marketing = false) {
  try {
    const url = new URL(location)
    if (
      url.protocol !== 'https:' ||
      url.username ||
      url.password ||
      !['https://gaeld.ch', 'https://www.gaeld.ch', 'https://app.gaeld.ch'].includes(url.origin) ||
      !allowedPaths.includes(url.pathname)
    )
      return null
    if (
      url.origin === 'https://app.gaeld.ch' &&
      url.pathname === '/conversions/confirm' &&
      referrer
    )
      return null
    const query = safeQuery(url.searchParams, marketing)
    // Functional app URLs must never have their tokens silently removed.
    if (
      url.hostname === 'app.gaeld.ch' &&
      (url.searchParams.toString() !== query.toString() || url.hash)
    )
      return null
    url.search = query.toString()
    url.hash = ''
    let cleanReferrer = ''
    if (referrer) {
      const previous = new URL(referrer)
      if (
        previous.protocol !== 'https:' ||
        previous.username ||
        previous.password ||
        previous.hash ||
        previous.searchParams.toString() !== safeQuery(previous.searchParams, marketing).toString()
      )
        return null
      // A private application URL must not reach Google through document.referrer.
      if (['https://gaeld.ch', 'https://www.gaeld.ch'].includes(previous.origin)) {
        const roots = ['', '/fr', '/de', '/it', '/en']
        const pages = [
          '',
          '/',
          '/pricing',
          '/for/freelancers',
          '/blog',
          '/philosophy',
          '/privacy',
          '/terms',
        ]
        const safePaths = roots.flatMap((root) => pages.map((page) => `${root}${page}` || '/'))
        safePaths.push(
          ...[
            'creer-premiere-facture-qr',
            'importer-releve-camt',
            'guides-et-nouveautes-gaeld',
          ].map((slug) => `/fr/blog/${slug}`)
        )
        if (!safePaths.includes(previous.pathname)) return null
      } else if (previous.origin === 'https://app.gaeld.ch') {
        if (!['/', '/login', '/signup', '/conversions/confirm'].includes(previous.pathname))
          return null
      } else if (previous.pathname !== '/' || previous.search) return null
      cleanReferrer = previous.origin + previous.pathname
    }
    return { location: url.toString(), referrer: cleanReferrer }
  } catch {
    return null
  }
}

function clearStorage(browser, category) {
  const pattern = category === 'marketing' ? /^_gcl_/ : /^(?:_ga|_gid$)/
  for (const item of browser.document.cookie.split(';')) {
    const name = item.trim().split('=')[0]
    if (!pattern.test(name)) continue
    for (const domain of ['', browser.location.hostname, 'gaeld.ch', '.gaeld.ch']) {
      browser.document.cookie = `${name}=; Max-Age=0; Path=/;${domain ? ` Domain=${domain};` : ''} SameSite=Lax; Secure`
    }
  }
  if (category === 'marketing') {
    try {
      browser.localStorage.removeItem('_gcl_ls')
    } catch {
      /* Storage can be unavailable. */
    }
  }
}

/** One runtime per document; it shares the existing GTM dataLayer. */
export function getGoogleRuntime(config, browser = window) {
  if (browser.__gaeldGoogleRuntime) return browser.__gaeldGoogleRuntime
  browser.dataLayer = browser.dataLayer || []
  browser.gtag =
    browser.gtag ||
    function () {
      browser.dataLayer.push(arguments)
    }
  const gtag = (...args) => browser.gtag(...args)
  let choices = { analytics: false, marketing: false }
  let adsReady = false
  let adsPromise = null
  let gtmLoaded = false
  const emitted = new Set()
  const allowedPaths = config.allowedPaths || []
  const adsId = /^AW-\d+$/.test(config.adsId || '') ? config.adsId : ''
  const gtmId = /^GTM-[A-Z0-9]+$/.test(config.gtmId || '') ? config.gtmId : ''
  const nonce = browser.document.querySelector('meta[name="csp-nonce"]')?.content

  gtag('consent', 'default', { ...googleConsent(choices), wait_for_update: 500 })
  gtag('set', 'ads_data_redaction', true)
  gtag('set', 'allow_ad_personalization_signals', false)
  gtag('set', 'allow_google_signals', false)

  function context() {
    const result = measurementContext(
      browser.location.href,
      browser.document.referrer,
      allowedPaths,
      choices.marketing
    )
    if (!result) return null
    if (result.location !== browser.location.href)
      browser.history.replaceState(browser.history.state, '', result.location)
    return result
  }

  function script(id, src) {
    const element = browser.document.createElement('script')
    element.id = id
    element.async = true
    element.src = src
    if (nonce) element.nonce = nonce
    return element
  }

  function loadGtm() {
    if (!choices.analytics || !gtmId || gtmLoaded || !context()) return
    gtmLoaded = true
    browser.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' })
    browser.document.head.appendChild(
      script('gaeld-gtm', `https://www.googletagmanager.com/gtm.js?id=${gtmId}`)
    )
  }

  function loadAds() {
    if (!choices.marketing || !adsId) return Promise.resolve(false)
    if (adsPromise) return adsPromise
    const page = context()
    if (!page) return Promise.resolve(false)
    gtag('js', new Date())
    gtag('config', adsId, {
      cookie_domain: 'gaeld.ch',
      allow_ad_personalization_signals: false,
      page_location: page.location,
      page_referrer: page.referrer,
    })
    adsPromise = new Promise((resolve) => {
      const element = script(
        'gaeld-google-ads',
        `https://www.googletagmanager.com/gtag/js?id=${adsId}`
      )
      const timer = browser.setTimeout(() => {
        element.remove()
        resolve(false)
      }, 5000)
      element.onload = () => {
        browser.clearTimeout(timer)
        adsReady = true
        resolve(true)
      }
      element.onerror = () => {
        browser.clearTimeout(timer)
        element.remove()
        resolve(false)
      }
      browser.document.head.appendChild(element)
    })
    return adsPromise
  }

  function update(next) {
    const previous = choices
    choices = next
    gtag('consent', 'update', googleConsent(choices))
    if (!choices.marketing) clearStorage(browser, 'marketing')
    if (!choices.analytics) clearStorage(browser, 'analytics')
    if (
      (previous.marketing && !choices.marketing && adsPromise) ||
      (previous.analytics && !choices.analytics && gtmLoaded)
    ) {
      // Removing a script cannot stop a SDK which has already executed.
      browser.location.reload()
      return
    }
    loadGtm()
    void loadAds()
  }

  const runtime = {
    updateCookie(cookie) {
      update(consentChoices(cookie))
    },
    refresh() {
      update(readConsent(browser.document.cookie))
      return { ...choices }
    },
    loadAds,
    async convert(event, onComplete = () => {}) {
      const current = readConsent(browser.document.cookie)
      if (!adsId || !current.marketing || !choices.marketing || !adsReady || !context())
        return false
      const label =
        event.event === 'signup'
          ? config.signupLabel
          : event.event === 'first_payment'
            ? config.firstPaymentLabel
            : ''
      if (
        !/^[A-Za-z0-9_-]+$/.test(label || '') ||
        !/^[a-f0-9]{32}$/.test(event.transaction_id || '') ||
        event.currency !== 'CHF' ||
        !Number.isFinite(event.value) ||
        event.value < 0 ||
        (event.event === 'first_payment' && event.value <= 0) ||
        (event.event === 'signup' && event.value !== 0) ||
        emitted.has(event.transaction_id)
      )
        return false
      emitted.add(event.transaction_id)
      gtag('event', 'conversion', {
        send_to: `${adsId}/${label}`,
        transaction_id: event.transaction_id,
        value: event.value,
        currency: 'CHF',
        event_callback: onComplete,
        event_timeout: 1500,
      })
      return true
    },
  }
  browser.__gaeldGoogleRuntime = runtime
  return runtime
}
