<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Badge from '@/Components/UI/Badge.vue'
import { useTranslations } from '@/lib/useTranslations'
import { FileText, Users, Truck, Receipt } from 'lucide-vue-next'
import { computed } from 'vue'

const { t } = useTranslations()

const props = defineProps({
  query: { type: String, default: '' },
  results: { type: Array, default: () => [] },
})

const typeConfig = {
  invoice: { icon: FileText, label: () => t('invoices'), color: 'text-blue-500' },
  customer: { icon: Users, label: () => t('customers'), color: 'text-green-500' },
  supplier: { icon: Truck, label: () => t('suppliers'), color: 'text-orange-500' },
  expense: { icon: Receipt, label: () => t('expenses'), color: 'text-red-500' },
}

const groupedResults = computed(() => {
  const groups = {}
  for (const r of props.results) {
    if (!groups[r.type]) groups[r.type] = []
    groups[r.type].push(r)
  }
  return groups
})
</script>

<template>
  <AppLayout :title="t('search_results')">
    <h1 class="mb-6 text-2xl font-bold">
      {{ t('search_results') }}
      <span v-if="query" class="text-[hsl(var(--muted-foreground))]">— "{{ query }}"</span>
    </h1>

    <div v-if="results.length > 0" class="space-y-6">
      <div v-for="(items, type) in groupedResults" :key="type">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-[hsl(var(--muted-foreground))] uppercase tracking-wide">
          <component :is="typeConfig[type]?.icon ?? FileText" class="h-4 w-4" :class="typeConfig[type]?.color" />
          {{ typeConfig[type]?.label() ?? type }}
        </h2>
        <div class="space-y-2">
          <Link
            v-for="result in items"
            :key="result.id"
            :href="result.url"
            class="flex items-center justify-between rounded-lg border border-[hsl(var(--border))] p-4 hover:bg-[hsl(var(--accent))] transition-colors"
          >
            <div>
              <p class="font-medium">{{ result.title }}</p>
              <p v-if="result.subtitle" class="text-sm text-[hsl(var(--muted-foreground))]">{{ result.subtitle }}</p>
            </div>
            <Badge v-if="result.status" :variant="result.status === 'paid' || result.status === 'posted' ? 'default' : 'secondary'">
              {{ result.status }}
            </Badge>
          </Link>
        </div>
      </div>
    </div>

    <div v-else class="rounded-lg border border-dashed border-[hsl(var(--border))] py-12 text-center text-sm text-[hsl(var(--muted-foreground))]">
      {{ query ? t('no_results_found') : t('global_search_placeholder') }}
    </div>
  </AppLayout>
</template>
