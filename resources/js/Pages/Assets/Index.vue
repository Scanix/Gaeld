<script setup>
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Button from '@/Components/UI/Button.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import Badge from '@/Components/UI/Badge.vue'
import HelpText from '@/Components/HelpText.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { Plus, Eye } from 'lucide-vue-next'
import { computed } from 'vue'

const { t } = useTranslations()
const { formatCurrency, formatDate } = useFormatters()

const props = defineProps({
  assets: Object,
  query: {
    type: Object,
    default: () => ({ sort: 'name', direction: 'asc', search: '' }),
  },
})

function applyQuery(params) {
  router.get('/assets', { ...props.query, ...params, page: 1 }, { preserveState: true, replace: true })
}

function handleSort({ sort, direction }) {
  applyQuery({ sort, direction })
}

function handleSearch(search) {
  applyQuery({ search })
}



const columns = computed(() => [
  { key: 'name', label: t('asset_name'), sortable: true },
  { key: 'purchase_date', label: t('purchase_date'), sortable: true },
  { key: 'purchase_amount', label: t('purchase_amount'), sortable: true, class: 'text-right' },
  { key: 'depreciation_method', label: t('depreciation_method') },
  { key: 'useful_life_years', label: t('useful_life'), class: 'text-right' },
  { key: 'net_book_value', label: t('net_book_value'), class: 'text-right' },
  { key: 'status', label: t('status') },
  { key: 'actions', label: '', class: 'text-right w-20' },
])
</script>

<template>
  <AppLayout :title="t('assets')" help-page="assets">
    <HelpText :title="t('help_assets_title')" class="mb-6">
      <p>{{ t('help_assets_text') }}</p>
      <p class="mt-2 font-medium">{{ t('help_assets_rates') }}</p>
      <ul class="mt-1 list-disc list-inside space-y-0.5">
        <li>{{ t('help_assets_rate_it') }}</li>
        <li>{{ t('help_assets_rate_vehicles') }}</li>
        <li>{{ t('help_assets_rate_furniture') }}</li>
        <li>{{ t('help_assets_rate_realestate') }}</li>
      </ul>
    </HelpText>

    <div class="mb-6 flex items-center justify-between">
      <p class="text-sm text-[hsl(var(--muted-foreground))]">
        {{ t('manage_assets') }}
      </p>
      <Button as="a" href="/assets/create">
        <Plus class="mr-2 h-4 w-4" />
        {{ t('new_asset') }}
      </Button>
    </div>

    <DataTable
      :columns="columns"
      :rows="assets?.data ?? []"
      :pagination="assets"
      :sort="query.sort"
      :direction="query.direction"
      :search="query.search"
      :search-placeholder="t('search_assets')"
      :empty-message="t('no_assets_yet')"
      @sort="handleSort"
      @search="handleSearch"
    >
      <template #cell-purchase_date="{ row }">
        {{ formatDate(row.purchase_date) }}
      </template>
      <template #cell-purchase_amount="{ row }">
        <span class="font-mono">{{ formatCurrency(row.purchase_amount) }}</span>
      </template>
      <template #cell-depreciation_method="{ row }">
        {{ row.depreciation_method === 'straight_line' ? t('straight_line') : t('declining_balance') }}
      </template>
      <template #cell-useful_life_years="{ row }">
        {{ row.useful_life_years }} {{ t('years') }}
      </template>
      <template #cell-net_book_value="{ row }">
        <span class="font-mono">{{ formatCurrency(row.net_book_value) }}</span>
      </template>
      <template #cell-status="{ row }">
        <Badge :variant="row.status === 'active' ? 'default' : row.status === 'disposed' ? 'destructive' : 'secondary'">
          {{ t('asset_status_' + row.status) }}
        </Badge>
      </template>
      <template #cell-actions="{ row }">
        <Button as="a" :href="`/assets/${row.id}`" variant="ghost" size="icon" :title="t('view')">
          <Eye class="h-4 w-4" />
        </Button>
      </template>
    </DataTable>
  </AppLayout>
</template>
