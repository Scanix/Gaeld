import getTranslations from './CookieConsentTranslations'
import { CONSENT_REVISION, getGoogleRuntime } from '../lib/googleConsent'
import { googleRuntimeConfig } from '../lib/googleRuntimeConfig'

const cookieDomain = import.meta.env.VITE_COOKIE_DOMAIN ||
  (/(^|\.)gaeld\.ch$/.test(window.location.hostname) ? 'gaeld.ch' : '')
const pluginConfig = {
  revision: CONSENT_REVISION,
  guiOptions: {
    consentModal: { layout: 'box', position: 'bottom left', equalWeightButtons: true, flipButtons: false },
    preferencesModal: { layout: 'box', position: 'left', equalWeightButtons: true, flipButtons: false },
  },
  cookie: { name: 'gaeld_cookie_consent', domain: cookieDomain, expiresAfterDays: 365 },
  categories: {
    necessary: { readOnly: true, enabled: true },
    analytics: { enabled: false, autoClear: { cookies: [{ name: /^_ga/ }, { name: '_gid' }] } },
    marketing: { enabled: false, autoClear: { cookies: [{ name: /^_gcl_/ }] } },
  },
  onConsent: ({ cookie }) => { getGoogleRuntime(googleRuntimeConfig()).updateCookie(cookie) },
  onChange: ({ cookie }) => { getGoogleRuntime(googleRuntimeConfig()).updateCookie(cookie) },
  language: { default: 'en', autoDetect: 'document', translations: getTranslations() },
}
export default pluginConfig
