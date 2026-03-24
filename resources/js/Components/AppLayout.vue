<script setup>
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'

const betaDismissed = ref(
  typeof localStorage !== 'undefined' && localStorage.getItem('beta-dismissed') === '1'
)
function dismissBeta() {
  betaDismissed.value = true
  localStorage.setItem('beta-dismissed', '1')
}
import Sidebar from '@/Components/Sidebar.vue'
import Topbar from '@/Components/Topbar.vue'
import HelpSidebar from '@/Components/HelpSidebar.vue'
import { useHelp } from '@/lib/useHelp'

const props = defineProps({
  title: String,
  helpPage: {
    type: String,
    default: null,
  },
})

const { showHelp, toggleHelp } = useHelp()
const collapsed = ref(false)
const mobileOpen = ref(false)
</script>

<template>
  <Head :title="title" />

  <!-- Early Beta Banner -->
  <div
    v-if="!betaDismissed"
    class="relative z-50 bg-amber-400 text-amber-950 text-sm font-medium"
    role="alert"
  >
    <div class="max-w-full px-6 py-2 flex items-center justify-center gap-3">
      <span class="inline-flex items-center gap-1.5">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        <strong>Early Beta</strong> — Gäld is under active development. Data may be reset without notice.
      </span>
      <a href="https://github.com/gaeld/gaeld-app" target="_blank" rel="noopener" class="underline underline-offset-2 hover:text-amber-900 whitespace-nowrap">Follow on GitHub</a>
      <button @click="dismissBeta" class="ml-2 opacity-60 hover:opacity-100 transition-opacity" aria-label="Dismiss">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
  </div>

  <div class="min-h-screen bg-[hsl(var(--background))]">
    <Sidebar
      v-model:collapsed="collapsed"
      :mobileOpen="mobileOpen"
      @closeMobile="mobileOpen = false"
    />

    <!-- Main content area (offset by sidebar on desktop) -->
    <div
      :class="[
        'transition-all duration-200',
        collapsed ? 'lg:pl-16' : 'lg:pl-60',
      ]"
    >
      <Topbar :helpPage="helpPage" @toggleHelp="toggleHelp" @toggleMobile="mobileOpen = !mobileOpen">
        <template #heading>
          {{ title }}
        </template>
      </Topbar>

      <main class="p-4 sm:p-6">
        <slot />
      </main>
    </div>

    <HelpSidebar
      v-if="helpPage && showHelp"
      :page="helpPage"
      @close="toggleHelp"
    />
  </div>
</template>
