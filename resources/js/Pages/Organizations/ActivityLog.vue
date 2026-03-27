<script setup>
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Badge from '@/Components/UI/Badge.vue'
import Button from '@/Components/UI/Button.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import { formatDate } from '@/lib/utils'
import { useTranslations } from '@/lib/useTranslations'
import { computed, ref } from 'vue'
import { ArrowLeft, History, Search } from 'lucide-vue-next'

const props = defineProps({
  activities: Object,
  subjectTypes: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const { t } = useTranslations()

const searchValue = ref(props.filters.search || '')
const selectedType = ref(props.filters.subject_type || '')
const selectedEvent = ref(props.filters.event || '')

const columns = computed(() => [
  { key: 'created_at', label: t('date') || 'Date', sortable: true, format: (v) => formatDate(v) },
  { key: 'description', label: t('description') || 'Description', sortable: false },
  { key: 'subject_type', label: t('entity') || 'Entity', sortable: true, format: (v) => v ? v.split('\\').pop() : '—' },
  { key: 'event', label: t('event') || 'Event', sortable: true },
  { key: 'causer', label: t('user') || 'User', sortable: false, format: (v) => v?.name || '—' },
  { key: 'changes', label: t('changes') || 'Changes', sortable: false },
])

const rows = computed(() => {
  return (props.activities?.data || []).map(activity => ({
    ...activity,
    changes: formatChanges(activity.properties),
  }))
})

function formatChanges(properties) {
  if (!properties) return null
  const { old, attributes, organization_id, ...rest } = properties
  if (!old && !attributes) return null
  return { old, attributes }
}

function eventColor(event) {
  switch (event) {
    case 'created': return 'green'
    case 'updated': return 'yellow'
    case 'deleted': return 'destructive'
    default: return 'secondary'
  }
}

function applyFilters() {
  router.get('/settings/activity-log', {
    ...(searchValue.value && { search: searchValue.value }),
    ...(selectedType.value && { subject_type: selectedType.value }),
    ...(selectedEvent.value && { event: selectedEvent.value }),
  }, { preserveState: true, replace: true })
}

function handleSearch(value) {
  searchValue.value = value
  applyFilters()
}

function handleSort({ sort, direction }) {
  router.get('/settings/activity-log', {
    ...props.filters,
    sort,
    direction,
  }, { preserveState: true, replace: true })
}

function clearFilters() {
  searchValue.value = ''
  selectedType.value = ''
  selectedEvent.value = ''
  router.get('/settings/activity-log', {}, { preserveState: true, replace: true })
}

const typeOptions = computed(() => [
  { value: '', label: t('all_types') || 'All types' },
  ...props.subjectTypes,
])

const eventOptions = [
  { value: '', label: t('all_events') || 'All events' },
  { value: 'created', label: t('created') || 'Created' },
  { value: 'updated', label: t('updated') || 'Updated' },
  { value: 'deleted', label: t('deleted') || 'Deleted' },
]
</script>

<template>
  <AppLayout :title="t('activity_log') || 'Activity Log'">
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <Button as="a" href="/settings" variant="outline" size="sm">
          <ArrowLeft class="mr-2 h-4 w-4" /> {{ t('settings') || 'Settings' }}
        </Button>
        <h2 class="text-xl font-semibold">
          <History class="inline mr-2 h-5 w-5" />
          {{ t('activity_log') || 'Activity Log' }}
        </h2>
      </div>
    </div>

    <!-- Filters -->
    <Card class="mb-4">
      <CardContent class="pt-4">
        <div class="flex flex-wrap items-end gap-3">
          <div class="w-48">
            <FormSelect
              :modelValue="selectedType"
              @update:modelValue="v => { selectedType = v; applyFilters() }"
              :options="typeOptions"
              :label="t('entity_type') || 'Entity type'"
            />
          </div>
          <div class="w-40">
            <FormSelect
              :modelValue="selectedEvent"
              @update:modelValue="v => { selectedEvent = v; applyFilters() }"
              :options="eventOptions"
              :label="t('event') || 'Event'"
            />
          </div>
          <Button v-if="selectedType || selectedEvent || searchValue" variant="outline" size="sm" @click="clearFilters">
            {{ t('clear_filters') || 'Clear filters' }}
          </Button>
        </div>
      </CardContent>
    </Card>

    <!-- Activity table -->
    <Card>
      <CardHeader>
        <CardTitle>{{ t('recent_activity') || 'Recent Activity' }}</CardTitle>
        <CardDescription>
          {{ t('activity_log_description') || 'All changes made to your organization data.' }}
        </CardDescription>
      </CardHeader>
      <CardContent>
        <DataTable
          :columns="columns"
          :rows="rows"
          :pagination="activities"
          searchable
          :search-value="searchValue"
          :empty-message="t('no_activity') || 'No activity recorded yet.'"
          @search="handleSearch"
          @sort="handleSort"
        >
          <template #cell-event="{ value }">
            <Badge :variant="eventColor(value)">{{ value }}</Badge>
          </template>

          <template #cell-changes="{ value }">
            <div v-if="value" class="max-w-xs">
              <details class="text-xs">
                <summary class="cursor-pointer text-muted-foreground hover:text-foreground">
                  {{ t('view_changes') || 'View changes' }}
                </summary>
                <div class="mt-1 space-y-1">
                  <div v-if="value.old" v-for="(val, key) in value.attributes" :key="key" class="flex gap-1">
                    <span class="font-medium">{{ key }}:</span>
                    <span class="line-through text-muted-foreground">{{ value.old?.[key] ?? '—' }}</span>
                    <span>→</span>
                    <span>{{ val ?? '—' }}</span>
                  </div>
                  <div v-else-if="value.attributes" v-for="(val, key) in value.attributes" :key="key" class="flex gap-1">
                    <span class="font-medium">{{ key }}:</span>
                    <span>{{ val }}</span>
                  </div>
                </div>
              </details>
            </div>
            <span v-else class="text-muted-foreground">—</span>
          </template>
        </DataTable>
      </CardContent>
    </Card>
  </AppLayout>
</template>
