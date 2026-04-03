<template>
  <div class="fixed left-8 bottom-8 z-[99]">
    <button
      v-if="isVisible"
      type="button"
      @click="openPreferences"
      :aria-label="'Open cookie preferences'"
      class="flex h-10 w-10 cursor-pointer items-center justify-center rounded-md bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))] shadow-md transition duration-300 ease-in-out hover:bg-[hsl(var(--primary))]/90 hover:shadow-lg"
    >
      <CookieIcon :size="20" />
    </button>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import * as CookieConsent from 'vanilla-cookieconsent'
import 'vanilla-cookieconsent/dist/cookieconsent.css'
import './cookieConsent.css'
import pluginConfig from './CookieConsentConfig'
import { Cookie as CookieIcon } from 'lucide-vue-next'

const isVisible = ref(false)

onMounted(() => {
  CookieConsent.run(pluginConfig)
    .then(() => {
      isVisible.value = CookieConsent.getCookie() !== null
    })
    .catch((err) => {
      console.error('CookieConsent failed to initialize:', err)
      isVisible.value = true
    })
})

function openPreferences() {
  CookieConsent.showPreferences()
}
</script>
