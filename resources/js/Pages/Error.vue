<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useTranslations } from '@/lib/useTranslations'
import { ShieldX, FileQuestion, Clock, ServerCrash, Construction, Timer, ExternalLink, RefreshCw, LogOut } from 'lucide-vue-next'

const props = defineProps({
  status: { type: Number, required: true },
})

const { t } = useTranslations()

// Hardcoded fallbacks for when translations are unavailable (e.g. 429 rendered outside middleware)
const FALLBACKS = {
  something_went_wrong: 'Something went wrong',
  unexpected_error_occurred: 'An unexpected error occurred.',
  go_to_dashboard: 'Go to Dashboard',
  go_back: 'Go Back',
  check_service_status: 'Check service status',
  error_service_status_title: 'Is Gäld having an outage?',
  error_service_status_description: 'Check the system status for incidents and maintenance updates.',
  try_again: 'Try again',
  sign_out_and_try_again: 'Sign out and try again',
}

function tSafe(key) {
  const val = t(key)
  return val !== key ? val : FALLBACKS[key] || key
}

const errorConfig = computed(() => {
  const configs = {
    403: { icon: ShieldX, color: 'var(--destructive)' },
    404: { icon: FileQuestion, color: 'var(--muted-foreground)' },
    419: { icon: Clock, color: 'var(--warning, 38 92% 50%)' },
    429: { icon: Timer, color: 'var(--warning, 38 92% 50%)' },
    500: { icon: ServerCrash, color: 'var(--destructive)' },
    503: { icon: Construction, color: 'var(--warning, 38 92% 50%)' },
  }

  return configs[props.status] || configs[500]
})

const titleKey = `error_${props.status}_title`
const descKey = `error_${props.status}_description`
const title = computed(() => {
  const val = t(titleKey)
  return val !== titleKey ? val : tSafe('something_went_wrong')
})
const description = computed(() => {
  const val = t(descKey)
  return val !== descKey ? val : tSafe('unexpected_error_occurred')
})
const isServiceIssue = computed(() => props.status >= 500)

function goBack() {
  window.history.back()
}

function retry() {
  window.location.reload()
}
</script>

<template>
  <Head :title="`${status} — ${title}`" />

  <div class="flex min-h-screen items-center justify-center bg-[hsl(var(--background))] px-4">
    <div class="w-full max-w-md text-center">
      <!-- Icon -->
      <div
        class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full"
        :style="{ backgroundColor: `hsl(${errorConfig.color} / 0.1)` }"
      >
        <component
          :is="errorConfig.icon"
          class="h-8 w-8"
          :style="{ color: `hsl(${errorConfig.color})` }"
        />
      </div>

      <!-- Status code -->
      <p class="mb-2 text-sm font-medium tracking-wider text-[hsl(var(--muted-foreground))]">
        {{ status }}
      </p>

      <!-- Title -->
      <h1 class="mb-3 text-2xl font-bold text-[hsl(var(--foreground))]">
        {{ title }}
      </h1>

      <!-- Description -->
      <p class="mb-8 text-[hsl(var(--muted-foreground))]">
        {{ description }}
      </p>

      <div
        v-if="isServiceIssue"
        class="mb-8 rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--muted)/0.35)] p-4 text-left"
        role="status"
      >
        <p class="mb-1 text-sm font-semibold text-[hsl(var(--foreground))]">
          {{ tSafe('error_service_status_title') }}
        </p>
        <p class="mb-3 text-sm text-[hsl(var(--muted-foreground))]">
          {{ tSafe('error_service_status_description') }}
        </p>
        <a
          href="https://status.nectoria.com"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-2 text-sm font-medium text-[hsl(var(--primary))] underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]"
        >
          {{ tSafe('check_service_status') }}
          <ExternalLink class="h-4 w-4" aria-hidden="true" />
        </a>
      </div>

      <!-- Actions -->
      <div class="flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
        <button
          type="button"
          class="inline-flex items-center justify-center gap-2 rounded-md bg-[hsl(var(--primary))] px-4 py-2 text-sm font-medium text-[hsl(var(--primary-foreground))] shadow hover:bg-[hsl(var(--primary)/0.9)] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]"
          @click="retry"
        >
          <RefreshCw class="h-4 w-4" aria-hidden="true" />
          {{ tSafe('try_again') }}
        </button>
        <a
          v-if="isServiceIssue"
          href="/logout"
          class="inline-flex items-center justify-center gap-2 rounded-md border border-[hsl(var(--border))] bg-[hsl(var(--background))] px-4 py-2 text-sm font-medium text-[hsl(var(--foreground))] shadow-sm hover:bg-[hsl(var(--accent))] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]"
        >
          <LogOut class="h-4 w-4" aria-hidden="true" />
          {{ tSafe('sign_out_and_try_again') }}
        </a>
        <Link
          href="/"
          class="inline-flex items-center justify-center rounded-md border border-[hsl(var(--border))] bg-[hsl(var(--background))] px-4 py-2 text-sm font-medium text-[hsl(var(--foreground))] shadow-sm hover:bg-[hsl(var(--accent))] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]"
        >
          {{ tSafe('go_to_dashboard') }}
        </Link>
        <button
          type="button"
          class="inline-flex items-center justify-center rounded-md border border-[hsl(var(--border))] bg-[hsl(var(--background))] px-4 py-2 text-sm font-medium text-[hsl(var(--foreground))] shadow-sm hover:bg-[hsl(var(--accent))] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]"
          @click="goBack"
        >
          {{ tSafe('go_back') }}
        </button>
      </div>

      <!-- Branding -->
      <p class="mt-12 text-xs text-[hsl(var(--muted-foreground)/0.6)]">
        Gäld
      </p>
    </div>
  </div>
</template>
