<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Badge from '@/Components/UI/Badge.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import ExportDropdown from '@/Components/UI/ExportDropdown.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useFormatters } from '@/lib/useFormatters'
import { useTranslations } from '@/lib/useTranslations'
import { computed } from 'vue'
import HelpText from '@/Components/HelpText.vue'
import EmptyState from '@/Components/UI/EmptyState.vue'
import { HelpCircle, BookText } from 'lucide-vue-next'

defineProps({ entries: Object })

const { t } = useTranslations()
const { formatCurrency, formatDate } = useFormatters()

const columns = computed(() => [
  { key: 'date', label: t('date'), format: v => formatDate(v) },
  { key: 'reference', label: t('reference') },
  { key: 'description', label: t('description') },
  { key: 'is_posted', label: t('status') },
])
</script>

<template>
  <AppLayout :title="t('journal_entries')" help-page="accounting-basics">
    <HelpText :title="t('help_journal_title')" class="mb-6">
      <p>{{ t('help_journal_text') }}</p>
    </HelpText>

    <div class="mb-4 flex justify-end">
      <ExportDropdown base-url="/accounting/journal-entries/export" />
    </div>

    <Card>
      <CardHeader><CardTitle>{{ t('journal_entries') }}</CardTitle></CardHeader>
      <CardContent>
        <DataTable :columns="columns" :rows="entries?.data ?? []" :pagination="entries">
          <template #empty>
            <EmptyState
              :icon="BookText"
              :title="t('empty_journal_entries_title')"
              :description="t('empty_journal_entries_desc')"
              :action-label="t('go_to_invoices')"
              action-href="/invoices/create"
            />
          </template>
          <template #cell-is_posted="{ value }">
            <Badge :variant="value ? 'success' : 'warning'">{{ value ? t('posted') : t('draft') }}</Badge>
          </template>
        </DataTable>

        <!-- Expanded lines for each entry -->
        <template v-for="entry in (entries?.data ?? [])" :key="entry.id">
        <div v-if="entry.lines?.length" class="mt-4 border rounded-md p-3">
          <p class="mb-2 text-sm font-medium">{{ entry.reference }} — {{ entry.description }}</p>
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b text-left text-muted-foreground">
                <th class="pb-1">{{ t('account') }}</th>
                <th class="pb-1 text-right">
                  <span class="inline-flex items-center gap-1">
                    {{ t('debit') }}
                    <Tooltip :content="t('tooltip_journal_balance')" side="top">
                      <HelpCircle class="h-3 w-3 text-[hsl(var(--muted-foreground))]" />
                    </Tooltip>
                  </span>
                </th>
                <th class="pb-1 text-right">{{ t('credit') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in entry.lines" :key="line.id">
                <td>{{ line.account?.code }} — {{ line.account?.name }}</td>
                <td class="text-right">{{ formatCurrency(line.debit) }}</td>
                <td class="text-right">{{ formatCurrency(line.credit) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        </template>
      </CardContent>
    </Card>
  </AppLayout>
</template>
