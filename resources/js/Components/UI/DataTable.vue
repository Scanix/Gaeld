<script setup>
import { Link } from '@inertiajs/vue3'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import Button from './Button.vue'

defineProps({
  columns: {
    type: Array,
    required: true,
    // Each column: { key: string, label: string, class?: string, format?: Function }
  },
  rows: {
    type: Array,
    default: () => [],
  },
  pagination: {
    type: Object,
    default: null,
    // Expects Laravel pagination object with links, current_page, last_page
  },
  emptyMessage: {
    type: String,
    default: 'No records found.',
  },
  rowLink: {
    type: Function,
    default: null,
    // (row) => '/invoices/123'
  },
})
</script>

<template>
  <div class="rounded-xl border border-[hsl(var(--border))] bg-[hsl(var(--card))]">
    <div class="overflow-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-[hsl(var(--border))] bg-[hsl(var(--muted))]/50">
            <th
              v-for="col in columns"
              :key="col.key"
              :class="['px-4 py-3 text-left font-medium text-[hsl(var(--muted-foreground))]', col.class]"
            >
              {{ col.label }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, i) in rows"
            :key="row.id ?? i"
            class="border-b border-[hsl(var(--border))] last:border-0 transition-colors hover:bg-[hsl(var(--muted))]/50"
          >
            <td
              v-for="col in columns"
              :key="col.key"
              :class="['px-4 py-3', col.class]"
            >
              <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                <Link v-if="rowLink" :href="rowLink(row)" class="hover:underline">
                  {{ col.format ? col.format(row[col.key], row) : row[col.key] }}
                </Link>
                <template v-else>
                  {{ col.format ? col.format(row[col.key], row) : row[col.key] }}
                </template>
              </slot>
            </td>
          </tr>
          <tr v-if="rows.length === 0">
            <td :colspan="columns.length" class="px-4 py-8 text-center text-[hsl(var(--muted-foreground))]">
              {{ emptyMessage }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="pagination && pagination.last_page > 1" class="flex items-center justify-between border-t border-[hsl(var(--border))] px-4 py-3">
      <p class="text-sm text-[hsl(var(--muted-foreground))]">
        Page {{ pagination.current_page }} of {{ pagination.last_page }}
      </p>
      <div class="flex gap-1">
        <Button
          v-if="pagination.prev_page_url"
          as="a"
          :href="pagination.prev_page_url"
          variant="outline"
          size="sm"
        >
          <ChevronLeft class="h-4 w-4" />
        </Button>
        <Button
          v-if="pagination.next_page_url"
          as="a"
          :href="pagination.next_page_url"
          variant="outline"
          size="sm"
        >
          <ChevronRight class="h-4 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
