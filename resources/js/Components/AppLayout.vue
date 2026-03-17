<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import Sidebar from '@/Components/Sidebar.vue'
import Topbar from '@/Components/Topbar.vue'
import HelpSidebar from '@/Components/HelpSidebar.vue'

const props = defineProps({
  title: String,
  helpPage: {
    type: String,
    default: null,
  },
})

const showHelp = ref(false)
const collapsed = ref(false)
const mobileOpen = ref(false)

onMounted(() => {
  if (props.helpPage) {
    showHelp.value = localStorage.getItem('gaeld-help-open') === 'true'
  }
})

function toggleHelp() {
  showHelp.value = !showHelp.value
  localStorage.setItem('gaeld-help-open', showHelp.value ? 'true' : 'false')
}
</script>

<template>
  <Head :title="title" />

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
