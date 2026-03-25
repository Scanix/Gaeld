import getTranslations from './CookieConsentTranslations'

const cookieDomain = import.meta.env.VITE_COOKIE_DOMAIN || ''

const pluginConfig = {
  guiOptions: {
    consentModal: {
      layout: 'box',
      position: 'bottom left',
      equalWeightButtons: true,
      flipButtons: false,
    },
    preferencesModal: {
      layout: 'box',
      position: 'left',
      equalWeightButtons: true,
      flipButtons: false,
    },
  },

  cookie: {
    name: 'gaeld_cookie_consent',
    domain: cookieDomain,
  },

  categories: {
    necessary: {
      readOnly: true,
      enabled: true,
    },
  },

  language: {
    default: 'en',
    autoDetect: 'document',
    translations: getTranslations(),
  },
}

export default pluginConfig
