<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { CheckCircle2, Circle, ChevronDown, ChevronUp, Rocket, BookOpen, X } from 'lucide-vue-next'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  gettingStarted: {
    type: Array,
    default: () => [],
  },
  accounting: {
    type: Array,
    default: () => [],
  },
})

// Getting Started stats
const gsDone = computed(() => props.gettingStarted.filter(i => i.done).length)
const gsTotal = computed(() => props.gettingStarted.length)
const gsProgress = computed(() => gsTotal.value > 0 ? Math.round((gsDone.value / gsTotal.value) * 100) : 0)
const gsComplete = computed(() => gsProgress.value === 100)

// Accounting stats
const accDone = computed(() => props.accounting.filter(i => i.done).length)
const accTotal = computed(() => props.accounting.length)
const accProgress = computed(() => accTotal.value > 0 ? Math.round((accDone.value / accTotal.value) * 100) : 0)

const gsCollapsed = ref(false)
const accCollapsed = ref(true)

const progressColor = (pct) => {
  if (pct === 100) return 'bg-green-500'
  if (pct >= 60) return 'bg-yellow-500'
  return 'bg-blue-500'
}

function dismissOnboarding() {
  router.post('/profile/onboarding/dismiss', {}, { preserveScroll: true })
}
</script>

<template>
  <div class="space-y-4">
    <!-- Getting Started Section -->
    <div v-if="gettingStarted.length" class="rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--card))]">
      <div class="flex items-center justify-between px-6 py-4">
        <button
          class="flex items-center gap-3 min-w-0 text-left"
          :aria-expanded="!gsCollapsed"
          @click="gsCollapsed = !gsCollapsed"
        >
          <Rocket class="h-4 w-4 text-blue-500" />
          <span class="font-semibold text-sm">{{ t('getting_started_checklist') }}</span>
          <span class="rounded-full bg-[hsl(var(--muted))] px-2 py-0.5 text-xs font-medium text-[hsl(var(--muted-foreground))]">
            {{ gsDone }}/{{ gsTotal }}
          </span>
          <component :is="gsCollapsed ? ChevronDown : ChevronUp" class="h-4 w-4 shrink-0 text-[hsl(var(--muted-foreground))]" />
        </button>
        <button
          :title="t('dismiss_onboarding')"
          class="rounded p-1 text-[hsl(var(--muted-foreground))] hover:bg-[hsl(var(--muted))] hover:text-[hsl(var(--foreground))]"
          @click.stop="dismissOnboarding"
        >
          <X class="h-4 w-4" />
        </button>
      </div>

      <div class="px-6 pb-2">
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-[hsl(var(--muted))]">
          <div
            :class="['h-full rounded-full transition-all duration-500', progressColor(gsProgress)]"
            :style="{ width: gsProgress + '%' }"
          />
        </div>
        <p class="mt-1 text-right text-xs text-[hsl(var(--muted-foreground))]">{{ gsProgress }}%</p>
      </div>

      <div v-if="!gsCollapsed" class="border-t border-[hsl(var(--border))] px-6 py-4">
        <ul class="space-y-2.5">
          <li v-for="item in gettingStarted" :key="item.key" class="flex items-center gap-3">
            <CheckCircle2 v-if="item.done" class="h-5 w-5 shrink-0 text-green-500" />
            <Circle v-else class="h-5 w-5 shrink-0 text-[hsl(var(--muted-foreground))]" />
            <component
              :is="item.href ? Link : 'span'"
              :href="item.href ?? undefined"
              :class="[
                'text-sm',
                item.done
                  ? 'text-[hsl(var(--muted-foreground))] line-through'
                  : item.href
                    ? 'text-[hsl(var(--foreground))] hover:text-[hsl(var(--primary))] hover:underline'
                    : 'text-[hsl(var(--foreground))]',
              ]"
            >
              {{ t(item.key) }}
            </component>
          </li>
        </ul>

        <p v-if="gsComplete" class="mt-4 text-sm font-medium text-green-600 dark:text-green-400">
          🎉 {{ t('getting_started_complete') }}
        </p>
      </div>
    </div>

    <!-- Accounting Checklist Section -->
    <div v-if="accounting.length" class="rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--card))]">
      <button
        class="flex w-full items-center justify-between px-6 py-4 text-left"
        :aria-expanded="!accCollapsed"
        @click="accCollapsed = !accCollapsed"
      >
        <div class="flex items-center gap-3 min-w-0">
          <BookOpen class="h-4 w-4 text-[hsl(var(--muted-foreground))]" />
          <span class="font-semibold text-sm">{{ t('accounting_checklist') }}</span>
          <span class="rounded-full bg-[hsl(var(--muted))] px-2 py-0.5 text-xs font-medium text-[hsl(var(--muted-foreground))]">
            {{ accDone }}/{{ accTotal }}
          </span>
        </div>
        <component :is="accCollapsed ? ChevronDown : ChevronUp" class="h-4 w-4 shrink-0 text-[hsl(var(--muted-foreground))]" />
      </button>

      <div class="px-6 pb-2">
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-[hsl(var(--muted))]">
          <div
            :class="['h-full rounded-full transition-all duration-500', progressColor(accProgress)]"
            :style="{ width: accProgress + '%' }"
          />
        </div>
        <p class="mt-1 text-right text-xs text-[hsl(var(--muted-foreground))]">{{ accProgress }}%</p>
      </div>

      <div v-if="!accCollapsed" class="border-t border-[hsl(var(--border))] px-6 py-4">
        <ul class="space-y-2.5">
          <li v-for="item in accounting" :key="item.key" class="flex items-center gap-3">
            <CheckCircle2 v-if="item.done" class="h-5 w-5 shrink-0 text-green-500" />
            <Circle v-else class="h-5 w-5 shrink-0 text-[hsl(var(--muted-foreground))]" />
            <component
              :is="item.href ? Link : 'span'"
              :href="item.href ?? undefined"
              :class="[
                'text-sm',
                item.done
                  ? 'text-[hsl(var(--muted-foreground))] line-through'
                  : item.href
                    ? 'text-[hsl(var(--foreground))] hover:text-[hsl(var(--primary))] hover:underline'
                    : 'text-[hsl(var(--foreground))]',
              ]"
            >
              {{ t(item.key) }}
            </component>
          </li>
        </ul>

        <p v-if="accProgress === 100" class="mt-4 text-sm font-medium text-green-600 dark:text-green-400">
          🎉 {{ t('checklist_complete') }}
        </p>
      </div>
    </div>
  </div>
</template>
