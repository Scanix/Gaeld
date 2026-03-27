<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { CheckCircle2, Circle, ChevronDown, ChevronUp } from 'lucide-vue-next'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
})

const collapsed = ref(false)

const doneCount = computed(() => props.items.filter(i => i.done).length)
const total = computed(() => props.items.length)
const progress = computed(() => total.value > 0 ? Math.round((doneCount.value / total.value) * 100) : 0)

const progressColor = computed(() => {
  if (progress.value === 100) return 'bg-green-500'
  if (progress.value >= 60) return 'bg-yellow-500'
  return 'bg-blue-500'
})
</script>

<template>
  <div class="rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--card))]">
    <!-- Header -->
    <button
      class="flex w-full items-center justify-between px-6 py-4 text-left"
      :aria-expanded="!collapsed"
      @click="collapsed = !collapsed"
    >
      <div class="flex items-center gap-3 min-w-0">
        <span class="font-semibold text-sm">{{ t('accounting_checklist') }}</span>
        <span class="rounded-full bg-[hsl(var(--muted))] px-2 py-0.5 text-xs font-medium text-[hsl(var(--muted-foreground))]">
          {{ doneCount }}/{{ total }}
        </span>
      </div>
      <component :is="collapsed ? ChevronDown : ChevronUp" class="h-4 w-4 shrink-0 text-[hsl(var(--muted-foreground))]" />
    </button>

    <!-- Progress bar -->
    <div class="px-6 pb-2">
      <div class="h-1.5 w-full overflow-hidden rounded-full bg-[hsl(var(--muted))]">
        <div
          :class="['h-full rounded-full transition-all duration-500', progressColor]"
          :style="{ width: progress + '%' }"
        />
      </div>
      <p class="mt-1 text-right text-xs text-[hsl(var(--muted-foreground))]">{{ progress }}%</p>
    </div>

    <!-- Item list -->
    <div v-if="!collapsed" class="border-t border-[hsl(var(--border))] px-6 py-4">
      <ul class="space-y-2.5">
        <li
          v-for="item in items"
          :key="item.key"
          class="flex items-center gap-3"
        >
          <CheckCircle2
            v-if="item.done"
            class="h-5 w-5 shrink-0 text-green-500"
          />
          <Circle
            v-else
            class="h-5 w-5 shrink-0 text-[hsl(var(--muted-foreground))]"
          />
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

      <p v-if="progress === 100" class="mt-4 text-sm font-medium text-green-600 dark:text-green-400">
        🎉 {{ t('checklist_complete') }}
      </p>
    </div>
  </div>
</template>
