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
import Button from '@/Components/UI/Button.vue'
import { Link } from '@inertiajs/vue3'
import { HelpCircle, BookText, Plus } from 'lucide-vue-next'

defineProps({ entries: Object, can: { type: Object, default: () => ({ create: false, delete: false }) } })

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

    <div class="mb-4 flex justify-end gap-2">
      <Link v-if="can.create" href="/accounting/journal-entries/create">
        <Button>
          <Plus class="mr-1 h-4 w-4" />
          {{ t('new_journal_entry') }}
        </Button>
      </Link>
      <ExportDropdown base-url="/accounting/journal-entries/export" />
    </div>

    <Card>
      <CardHeader><CardTitle>{{ t('journal_entries') }}</CardTitle></CardHeader>
      <CardContent>
        <DataTable :columns="columns" :rows="entries?.data ?? []" :pagination="entries" expandable>
          <template #empty>
            <EmptyState
              :icon="BookText"
              :title="t('empty_journal_entries_title')"
              :description="t('empty_journal_entries_desc')"
              :action-label="can.create ? t('new_journal_entry') : t('go_to_invoices')"
              :action-href="can.create ? '/accounting/journal-entries/create' : '/invoices/create'"
            />
          </template>
          <template #cell-is_posted="{ value }">
            <Badge :variant="value ? 'success' : 'warning'">{{ value ? t('posted') : t('draft') }}</Badge>
          </template>
          <template #expand-row="{ row }">
            <div v-if="row.lines?.length" class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b text-left text-[hsl(var(--muted-foreground))]">
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
                  <tr v-for="line in row.lines" :key="line.id">
                    <td>{{ line.account?.code }} — {{ line.account?.name }}</td>
                    <td class="text-right">{{ formatCurrency(line.debit) }}</td>
                    <td class="text-right">{{ formatCurrency(line.credit) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-else class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('no_journal_lines') }}</p>
          </template>
        </DataTable>
      </CardContent>
    </Card>
  </AppLayout>
</template>
