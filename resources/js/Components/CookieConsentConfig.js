import getTranslations from './CookieConsentTranslations'

const cookieDomain = import.meta.env.VITE_COOKIE_DOMAIN || ''

function updateGtmConsent(accepted) {
  if (typeof window === 'undefined' || typeof window.dataLayer === 'undefined') return
  const value = accepted ? 'granted' : 'denied'
  function gtag() {
    window.dataLayer.push(arguments)
  }
  gtag('consent', 'update', {
    ad_storage: value,
    ad_user_data: value,
    ad_personalization: value,
    analytics_storage: value,
  })
}

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
    analytics: {
      enabled: false,
      autoClear: {
        cookies: [
          { name: /^_ga/ },
          { name: '_gid' },
        ],
      },
    },
  },

  onConsent: ({ cookie }) => {
    updateGtmConsent(cookie.categories.includes('analytics'))
  },

  onChange: ({ cookie }) => {
    updateGtmConsent(cookie.categories.includes('analytics'))
  },

  language: {
    default: 'en',
    autoDetect: 'document',
    translations: getTranslations(),
  },
}

export default pluginConfig
