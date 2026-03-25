import { createApp, h } from 'vue'
import CookieConsentComponent from './Components/CookieConsent.vue'

const el = document.createElement('div')
document.body.appendChild(el)

createApp({ render: () => h(CookieConsentComponent) }).mount(el)
