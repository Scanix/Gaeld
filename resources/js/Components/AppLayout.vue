<script setup>
import { ref } from 'vue'
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
</script>

<template>
  <Head :title="title" />

  <div class="min-h-screen bg-[hsl(var(--background))]">
    <Sidebar />

    <!-- Main content area (offset by sidebar) -->
    <div class="pl-60 transition-all duration-200">
      <Topbar :helpPage="helpPage" @toggleHelp="showHelp = !showHelp">
        <template #heading>
          {{ title }}
        </template>
      </Topbar>

      <main class="p-6">
        <slot />
      </main>
    </div>

    <HelpSidebar
      v-if="helpPage && showHelp"
      :page="helpPage"
      @close="showHelp = false"
    />
  </div>
</template>
