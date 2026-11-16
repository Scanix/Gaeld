import test from 'node:test'
import assert from 'node:assert/strict'
import { confirmGoogleAds } from '../../resources/js/googleAdsConversions.js'

const config = { adsId: 'AW-18572107763', signupLabel: 'MJhhCJzQs7cdEPO_75dF', firstPaymentLabel: 'V3LNCJ_Qs7cdEPO_75dF' }
const consent = (categories = ['marketing'], revision = 1) =>
  `gaeld_cookie_consent=${encodeURIComponent(JSON.stringify({ categories, revision }))}`
const fact = { event: 'signup', transaction_id: 'a'.repeat(32), value: 0, currency: 'CHF' }

function fixture() {
  const actions = []
  let resolveLoad
  const loaded = new Promise((resolve) => { resolveLoad = resolve })
  const browser = {
    location: { origin: 'https://app.gaeld.ch', pathname: '/conversions/confirm', search: '', hash: '', replace: (url) => actions.push(['navigate', url]) },
    document: { referrer: '', cookie: consent(), querySelector: () => ({ content: 'local-test-csrf' }) },
    __gaeldGoogleRuntime: {
      refresh: () => actions.push(['refresh']),
      loadAds: () => { actions.push(['sdk']); return loaded },
      convert: async (event, complete) => { actions.push(['conversion', event]); complete(); return true },
    },
    fetch: async (url, options) => { actions.push(['claim', url, options]); return { ok: true, json: async () => ({ conversions: [fact] }) } },
    setTimeout, clearTimeout,
  }
  return { browser, actions, resolveLoad }
}

for (const [name, value] of [
  ['no choice', ''], ['legacy analytics', consent(['analytics'], 0)],
  ['analytics only', consent(['analytics'])], ['refused', consent([])],
]) {
  test(`${name}: no Ads load, claim or event`, async () => {
    const tab = fixture(); tab.browser.document.cookie = value
    await confirmGoogleAds(config, '/welcome', '/conversions/claim', tab.browser)
    assert.deepEqual(tab.actions, [['refresh'], ['navigate', '/welcome']])
  })
}

test('waits for SDK onload before claiming server facts and emitting', async () => {
  const tab = fixture()
  const running = confirmGoogleAds(config, '/welcome', '/conversions/claim', tab.browser)
  assert.deepEqual(tab.actions, [['refresh'], ['sdk']])
  tab.resolveLoad(true); await running
  assert.deepEqual(tab.actions.map(([name]) => name), ['refresh', 'sdk', 'claim', 'conversion', 'navigate'])
  const [, url, options] = tab.actions.find(([name]) => name === 'claim')
  assert.equal(url, '/conversions/claim')
  assert.equal(options.method, 'POST'); assert.equal(options.body, '{}')
  assert.equal(options.credentials, 'same-origin'); assert.equal(options.headers['X-CSRF-TOKEN'], 'local-test-csrf')
  assert.deepEqual(tab.actions.find(([name]) => name === 'conversion')[1], fact)
})

test('failed SDK does not claim and does not prevent access', async () => {
  const tab = fixture(); tab.resolveLoad(false)
  await confirmGoogleAds(config, '/billing', '/conversions/claim', tab.browser)
  assert.deepEqual(tab.actions, [['refresh'], ['sdk'], ['navigate', '/billing']])
})

test('consent withdrawn while SDK loads cannot be claimed later', async () => {
  const tab = fixture()
  const running = confirmGoogleAds(config, '/welcome', '/conversions/claim', tab.browser)
  tab.browser.document.cookie = consent([]); tab.resolveLoad(true); await running
  assert.equal(tab.actions.some(([name]) => name === 'claim' || name === 'conversion'), false)
})

test('consent withdrawn during claim cannot emit', async () => {
  const tab = fixture(); tab.resolveLoad(true)
  tab.browser.fetch = async () => {
    tab.actions.push(['claim']); tab.browser.document.cookie = consent([])
    return { ok: true, json: async () => ({ conversions: [fact] }) }
  }
  await confirmGoogleAds(config, '/welcome', '/conversions/claim', tab.browser)
  assert.equal(tab.actions.some(([name]) => name === 'claim'), true)
  assert.equal(tab.actions.some(([name]) => name === 'conversion'), false)
})

test('a private referrer causes a clean hop without SDK or claim', async () => {
  const tab = fixture(); tab.browser.document.referrer = 'https://app.gaeld.ch/invoices/customer-secret'
  await confirmGoogleAds(config, '/welcome', '/conversions/claim', tab.browser)
  assert.deepEqual(tab.actions, [['navigate', '/conversions/confirm']])
})

test('a referrer still present after the clean hop fails closed without looping', async () => {
  const tab = fixture(); tab.browser.document.referrer = 'https://app.gaeld.ch/conversions/confirm'
  await confirmGoogleAds(config, '/welcome', '/conversions/claim', tab.browser)
  assert.deepEqual(tab.actions, [['navigate', '/welcome']])
})

test('private/query/token routes and foreign claim destinations never load a SDK', async () => {
  for (const mutate of [
    (tab) => { tab.browser.location.pathname = '/billing' },
    (tab) => { tab.browser.location.search = '?token=secret' },
    (tab) => { tab.browser.location.hash = '#secret' },
  ]) {
    const tab = fixture(); mutate(tab)
    await confirmGoogleAds(config, '/welcome', '/conversions/claim', tab.browser)
    assert.deepEqual(tab.actions, [['navigate', '/welcome']])
  }
  const tab = fixture()
  await confirmGoogleAds(config, 'https://foreign.example', 'https://foreign.example/conversions/claim', tab.browser)
  assert.deepEqual(tab.actions, [['navigate', '/welcome']])
})

test('fixed same-origin absolute URLs normalize to safe relative paths', async () => {
  const tab = fixture(); tab.resolveLoad(true)
  await confirmGoogleAds(config, 'https://app.gaeld.ch/billing', 'https://app.gaeld.ch/conversions/claim', tab.browser)
  assert.deepEqual(tab.actions.at(-1), ['navigate', '/billing'])
  assert.equal(tab.actions.find(([name]) => name === 'claim')[1], '/conversions/claim')
})

test('claim failure never fabricates a conversion', async () => {
  const tab = fixture(); tab.resolveLoad(true)
  tab.browser.fetch = async () => { throw new Error('offline') }
  await confirmGoogleAds(config, '/welcome', '/conversions/claim', tab.browser)
  assert.deepEqual(tab.actions, [['refresh'], ['sdk'], ['navigate', '/welcome']])
})

test('both labels and a valid Ads ID are required before consuming a server fact', async () => {
  for (const broken of [
    { ...config, adsId: '' }, { ...config, adsId: 'G-incorrect' },
    { ...config, signupLabel: '' }, { ...config, firstPaymentLabel: '' },
  ]) {
    const tab = fixture()
    await confirmGoogleAds(broken, '/welcome', '/conversions/claim', tab.browser)
    assert.deepEqual(tab.actions, [['navigate', '/welcome']])
  }
})

const waitingConfig = { ...config, statusUrl: '/conversions/status' }
const instantDelays = (tab) => { tab.browser.setTimeout = (callback) => { queueMicrotask(callback); return 1 } }

test('delayed verified webhook is polled internally before any SDK or claim', async () => {
  const tab = fixture(); instantDelays(tab); tab.resolveLoad(true)
  let polls = 0
  tab.browser.fetch = async (url) => {
    if (url === '/conversions/status') {
      tab.actions.push(['status'])
      assert.equal(tab.actions.some(([name]) => name === 'sdk'), false)
      return { ok: true, json: async () => ({ ready: ++polls === 3, waiting: polls < 3 }) }
    }
    tab.actions.push(['claim'])
    return { ok: true, json: async () => ({ conversions: [fact] }) }
  }
  await confirmGoogleAds(waitingConfig, '/billing', '/conversions/claim', tab.browser)
  assert.deepEqual(tab.actions.map(([name]) => name), ['status', 'status', 'status', 'refresh', 'sdk', 'claim', 'conversion', 'navigate'])
})

test('waiting is bounded, has no Google load and cannot manufacture payment', async () => {
  const tab = fixture(); instantDelays(tab)
  let polls = 0
  tab.browser.fetch = async () => { polls++; return { ok: true, json: async () => ({ ready: false, waiting: true }) } }
  await confirmGoogleAds(waitingConfig, '/billing', '/conversions/claim', tab.browser)
  assert.equal(polls, 20)
  assert.deepEqual(tab.actions, [['navigate', '/billing']])
})

test('withdrawal while waiting stops polling without SDK, claim or conversion', async () => {
  const tab = fixture(); instantDelays(tab)
  tab.browser.fetch = async () => {
    tab.browser.document.cookie = consent([])
    return { ok: true, json: async () => ({ ready: true, waiting: false }) }
  }
  await confirmGoogleAds(waitingConfig, '/billing', '/conversions/claim', tab.browser)
  assert.deepEqual(tab.actions, [['navigate', '/billing']])
})

test('lost claim response retries internally and emits the same server fact once', async () => {
  const tab = fixture(); instantDelays(tab); tab.resolveLoad(true)
  let claims = 0
  tab.browser.fetch = async (url, options) => {
    tab.actions.push(['claim', options.headers['X-Gaeld-Ads-Retry']])
    if (++claims === 1) throw new Error('Synthetic lost response after server delivery')
    return { ok: true, json: async () => ({ conversions: [fact] }) }
  }
  await confirmGoogleAds(config, '/billing', '/conversions/claim', tab.browser)
  assert.equal(claims, 2)
  assert.deepEqual(tab.actions.filter(([name]) => name === 'claim'), [['claim', undefined], ['claim', '1']])
  assert.deepEqual(tab.actions.filter(([name]) => name === 'conversion'), [['conversion', fact]])
})

test('temporary claim failures stop after three attempts and 4xx are not retried', async () => {
  for (const status of [503, 419]) {
    const tab = fixture(); instantDelays(tab); tab.resolveLoad(true)
    let claims = 0
    tab.browser.fetch = async () => { claims++; return { ok: false, status } }
    await confirmGoogleAds(config, '/billing', '/conversions/claim', tab.browser)
    assert.equal(claims, status === 503 ? 3 : 1)
    assert.equal(tab.actions.some(([name]) => name === 'conversion'), false)
  }
})

test('withdrawal before a claim retry stops every later attempt', async () => {
  const tab = fixture(); instantDelays(tab); tab.resolveLoad(true)
  let claims = 0
  tab.browser.fetch = async () => {
    claims++; tab.browser.document.cookie = consent([])
    throw new Error('Synthetic offline response')
  }
  await confirmGoogleAds(config, '/billing', '/conversions/claim', tab.browser)
  assert.equal(claims, 1)
  assert.equal(tab.actions.some(([name]) => name === 'conversion'), false)
})


test('real shared runtime and bridge preserve official queue, labels, values and clean context with a no-transport SDK', async () => {
  const tab = fixture()
  delete tab.browser.__gaeldGoogleRuntime
  Object.assign(tab.browser.location, { href: 'https://app.gaeld.ch/conversions/confirm', hostname: 'app.gaeld.ch' })
  tab.browser.history = { state: null, replaceState() { throw new Error('Clean URL must not change') } }
  tab.browser.localStorage = { removeItem() {} }
  tab.browser.document.querySelector = (selector) => selector.includes('csrf-token') ? { content: 'local-test-csrf' } : null
  tab.browser.document.createElement = () => ({ remove() {} })
  tab.browser.document.head = { appendChild(element) {
    tab.actions.push(['sdk-request-intercepted', element.src])
    queueMicrotask(() => { tab.actions.push(['sdk-onload']); element.onload() })
  } }
  tab.browser.gtag = function () {
    tab.browser.dataLayer.push(arguments)
    if (arguments[0] === 'event' && arguments[1] === 'conversion') queueMicrotask(arguments[2].event_callback)
  }
  const paid = { event: 'first_payment', transaction_id: 'b'.repeat(32), value: 15.25, currency: 'CHF' }
  tab.browser.fetch = async (url) => {
    tab.actions.push(['internal-request', url])
    return { ok: true, json: async () => url === '/conversions/status' ? { ready: true, waiting: false } : { conversions: [fact, paid] } }
  }
  await confirmGoogleAds(waitingConfig, '/billing', '/conversions/claim', tab.browser)
  const commands = tab.browser.dataLayer
  assert.equal(commands.every((item) => Object.prototype.toString.call(item) === '[object Arguments]'), true)
  const init = commands.find((item) => item[0] === 'config')
  assert.equal(init[1], config.adsId)
  assert.equal(init[2].page_location, 'https://app.gaeld.ch/conversions/confirm')
  assert.equal(init[2].page_referrer, '')
  const events = commands.filter((item) => item[0] === 'event')
  assert.deepEqual(events.map((item) => [item[2].send_to, item[2].value, item[2].transaction_id]), [
    [`${config.adsId}/${config.signupLabel}`, 0, fact.transaction_id],
    [`${config.adsId}/${config.firstPaymentLabel}`, 15.25, paid.transaction_id],
  ])
  assert.equal(tab.actions.filter(([name]) => name === 'sdk-request-intercepted').length, 1)
  assert.deepEqual(tab.actions.filter(([name]) => name === 'internal-request').map((item) => item[1]), ['/conversions/status', '/conversions/claim'])
  assert.deepEqual(tab.actions.at(-1), ['navigate', '/billing'])
})


test('an unclaimed signup does not end the wait for a new first payment', async () => {
  const tab = fixture(); instantDelays(tab); tab.resolveLoad(true)
  const paid = { event: 'first_payment', transaction_id: 'c'.repeat(32), value: 15, currency: 'CHF' }
  let polls = 0
  tab.browser.fetch = async (url) => {
    if (url === '/conversions/status') {
      tab.actions.push(['status'])
      assert.equal(tab.actions.some(([name]) => name === 'sdk'), false)
      return { ok: true, json: async () => ({ ready: true, waiting: ++polls < 3 }) }
    }
    tab.actions.push(['claim'])
    return { ok: true, json: async () => ({ conversions: [fact, paid] }) }
  }
  await confirmGoogleAds(waitingConfig, '/billing', '/conversions/claim', tab.browser)
  assert.equal(polls, 3)
  assert.deepEqual(tab.actions.filter(([name]) => name === 'conversion').map((item) => item[1]), [fact, paid])
})

test('at the bounded end only available signup facts are delivered without inventing payment', async () => {
  const tab = fixture(); instantDelays(tab); tab.resolveLoad(true)
  let polls = 0
  tab.browser.fetch = async (url) => {
    if (url === '/conversions/status') {
      polls++; return { ok: true, json: async () => ({ ready: true, waiting: true }) }
    }
    return { ok: true, json: async () => ({ conversions: [fact] }) }
  }
  await confirmGoogleAds(waitingConfig, '/billing', '/conversions/claim', tab.browser)
  assert.equal(polls, 20)
  assert.deepEqual(tab.actions.filter(([name]) => name === 'conversion'), [['conversion', fact]])
})
