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
