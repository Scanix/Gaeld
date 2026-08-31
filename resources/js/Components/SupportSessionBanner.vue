<script setup>
import { Link } from '@inertiajs/vue3'
import { ShieldAlert } from 'lucide-vue-next'
import Banner from '@/Components/UI/Banner.vue'

defineProps({
  session: {
    type: Object,
    required: true,
  },
  stopUrl: {
    type: String,
    default: null,
  },
  label: {
    type: String,
    default: 'Support session active',
  },
  stopLabel: {
    type: String,
    default: 'Stop support session',
  },
})
</script>

<template>
  <Banner color="amber" role="alert">
    <span class="inline-flex min-w-0 items-center gap-2">
      <ShieldAlert class="h-4 w-4 shrink-0" />
      <span class="truncate">{{ label }}</span>
    </span>
    <Link
      v-if="stopUrl"
      :href="stopUrl"
      method="post"
      as="button"
      class="shrink-0 font-semibold underline underline-offset-2"
    >
      {{ stopLabel }}
    </Link>
  </Banner>
</template>