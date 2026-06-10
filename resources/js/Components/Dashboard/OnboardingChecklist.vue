<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { CheckCircle2, Circle, ChevronDown, ChevronUp, Rocket, BookOpen, X } from 'lucide-vue-next'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  checklist: {
    type: Object,
    required: true,
  },
})

const gettingStarted = computed(() => props.checklist.getting_started ?? [])
const accounting = computed(() => props.checklist.accounting ?? [])

const gsDone = computed(() => gettingStarted.value.filter(i => i.done).length)
const gsTotal = computed(() => gettingStarted.value.length)
const gsProgress = computed(() => gsTotal.value > 0 ? Math.round((gsDone.value / gsTotal.value) * 100) : 0)

const accDone = computed(() => accounting.value.filter(i => i.done).length)
const accTotal = computed(() => accounting.value.length)
const accProgress = computed(() => accTotal.value > 0 ? Math.round((accDone.value / accTotal.value) * 100) : 0)

const totalDone = computed(() => gsDone.value + accDone.value)
const totalTotal = computed(() => gsTotal.value + accTotal.value)

const gsCollapsed = ref(false)
const accCollapsed = ref(true)

const progressColor = (pct) => {
  if (pct === 100) return 'bg-green-500'
  if (pct >= 60) return 'bg-yellow-500'
  return 'bg-blue-500'
}

function dismiss() {
  router.post('/onboarding/dismiss', {}, { preserveScroll: true })
}
</script>

<template>
  <div class="rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--card))]">
    <div class="flex items-center justify-between px-6 py-4 border-b border-[hsl(var(--border))]">
      <div class="flex items-center gap-3 min-w-0">
        <span class="font-semibold text-sm">{{ t('checklist_title') }}</span>
        <span class="rounded-full bg-[hsl(var(--muted))] px-2 py-0.5 text-xs font-medium text-[hsl(var(--muted-foreground))]">
          {{ t('checklist_progress', { done: totalDone, total: totalTotal }) }}
        </span>
      </div>
      <button
        type="button"
        :title="t('checklist_dismiss')"
        :aria-label="t('checklist_dismiss')"
        class="rounded p-1 text-[hsl(var(--muted-foreground))] hover:bg-[hsl(var(--muted))] hover:text-[hsl(var(--foreground))]"
        @click="dismiss"
      >
        <X class="h-4 w-4" />
      </button>
    </div>

    <!-- Getting Started Section -->
    <div v-if="gettingStarted.length" class="border-b border-[hsl(var(--border))]">
      <button
        type="button"
        class="flex w-full items-center justify-between px-6 py-3 text-left"
        :aria-expanded="!gsCollapsed"
        @click="gsCollapsed = !gsCollapsed"
      >
        <div class="flex items-center gap-3 min-w-0">
          <Rocket class="h-4 w-4 text-blue-500" />
          <span class="font-semibold text-sm">{{ t('checklist_section_getting_started') }}</span>
          <span class="rounded-full bg-[hsl(var(--muted))] px-2 py-0.5 text-xs font-medium text-[hsl(var(--muted-foreground))]">
            {{ gsDone }}/{{ gsTotal }}
          </span>
        </div>
        <component :is="gsCollapsed ? ChevronDown : ChevronUp" class="h-4 w-4 shrink-0 text-[hsl(var(--muted-foreground))]" />
      </button>

      <div class="px-6 pb-2">
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-[hsl(var(--muted))]">
          <div
            :class="['h-full rounded-full transition-all duration-500', progressColor(gsProgress)]"
            :style="{ width: gsProgress + '%' }"
          />
        </div>
      </div>

      <div v-if="!gsCollapsed" class="px-6 py-4">
        <ul class="space-y-2.5">
          <li v-for="item in gettingStarted" :key="item.key" class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
              <CheckCircle2 v-if="item.done" class="h-5 w-5 shrink-0 text-green-500" />
              <Circle v-else class="h-5 w-5 shrink-0 text-[hsl(var(--muted-foreground))]" />
              <span
                :class="[
                  'text-sm',
                  item.done
                    ? 'text-[hsl(var(--muted-foreground))] line-through'
                    : 'text-[hsl(var(--foreground))]',
                ]"
              >
                {{ t(item.key) }}
              </span>
            </div>
            <Link
              v-if="!item.done && item.href"
              :href="item.href"
              class="shrink-0 text-xs font-medium text-[hsl(var(--primary))] hover:underline"
            >
              {{ t('checklist_go') }}
            </Link>
          </li>
        </ul>
      </div>
    </div>

    <!-- Accounting Section -->
    <div v-if="accounting.length">
      <button
        type="button"
        class="flex w-full items-center justify-between px-6 py-3 text-left"
        :aria-expanded="!accCollapsed"
        @click="accCollapsed = !accCollapsed"
      >
        <div class="flex items-center gap-3 min-w-0">
          <BookOpen class="h-4 w-4 text-[hsl(var(--muted-foreground))]" />
          <span class="font-semibold text-sm">{{ t('checklist_section_accounting') }}</span>
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
      </div>

      <div v-if="!accCollapsed" class="px-6 py-4">
        <ul class="space-y-2.5">
          <li v-for="item in accounting" :key="item.key" class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
              <CheckCircle2 v-if="item.done" class="h-5 w-5 shrink-0 text-green-500" />
              <Circle v-else class="h-5 w-5 shrink-0 text-[hsl(var(--muted-foreground))]" />
              <span
                :class="[
                  'text-sm',
                  item.done
                    ? 'text-[hsl(var(--muted-foreground))] line-through'
                    : 'text-[hsl(var(--foreground))]',
                ]"
              >
                {{ t(item.key) }}
              </span>
            </div>
            <Link
              v-if="!item.done && item.href"
              :href="item.href"
              class="shrink-0 text-xs font-medium text-[hsl(var(--primary))] hover:underline"
            >
              {{ t('checklist_go') }}
            </Link>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
