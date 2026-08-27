<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'

const props = defineProps({
  entry: { type: Object, required: true },
})

const { t } = useTranslations()
const { formatCurrency, formatDate } = useFormatters()

const totalDebit = computed(() => props.entry.lines?.reduce((total, line) => total + Number(line.debit || 0), 0) || 0)
const totalCredit = computed(() => props.entry.lines?.reduce((total, line) => total + Number(line.credit || 0), 0) || 0)
</script>

<template>
  <AppLayout :title="t('journal_entry')" help-page="accounting-basics">
    <Breadcrumb
      :items="[{ label: t('journal_entries'), href: '/accounting/journal-entries' }, { label: entry.reference || t('journal_entry') }]"
      class="mb-4"
    />

    <div class="max-w-4xl space-y-6">
      <Card>
        <CardHeader>
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <CardTitle>{{ entry.description || entry.reference || t('journal_entry') }}</CardTitle>
              <p class="mt-1 text-sm text-[hsl(var(--muted-foreground))]">{{ entry.reference || '—' }}</p>
            </div>
            <Badge :variant="entry.is_posted ? 'success' : 'warning'">
              {{ entry.is_posted ? t('posted') : t('draft') }}
            </Badge>
          </div>
        </CardHeader>
        <CardContent>
          <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-3">
            <div>
              <dt class="text-[hsl(var(--muted-foreground))]">{{ t('date') }}</dt>
              <dd class="font-medium">{{ formatDate(entry.date) }}</dd>
            </div>
            <div>
              <dt class="text-[hsl(var(--muted-foreground))]">{{ t('reference') }}</dt>
              <dd class="font-medium">{{ entry.reference || '—' }}</dd>
            </div>
            <div>
              <dt class="text-[hsl(var(--muted-foreground))]">{{ t('description') }}</dt>
              <dd class="font-medium">{{ entry.description || '—' }}</dd>
            </div>
          </dl>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>{{ t('entry_lines') }}</CardTitle></CardHeader>
        <CardContent>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-[hsl(var(--border))] text-left text-[hsl(var(--muted-foreground))]">
                  <th class="pb-2 font-medium">{{ t('account') }}</th>
                  <th class="pb-2 font-medium">{{ t('description') }}</th>
                  <th class="pb-2 text-right font-medium">{{ t('debit') }}</th>
                  <th class="pb-2 text-right font-medium">{{ t('credit') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[hsl(var(--border))]">
                <tr v-for="line in entry.lines" :key="line.id">
                  <td class="py-2">{{ line.account?.code }} — {{ line.account?.name }}</td>
                  <td class="py-2">{{ line.description || '—' }}</td>
                  <td class="py-2 text-right font-mono">{{ formatCurrency(line.debit) }}</td>
                  <td class="py-2 text-right font-mono">{{ formatCurrency(line.credit) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="border-t-2 border-[hsl(var(--border))] font-bold">
                  <td class="pt-3" colspan="2">{{ t('total') }}</td>
                  <td class="pt-3 text-right font-mono">{{ formatCurrency(totalDebit) }}</td>
                  <td class="pt-3 text-right font-mono">{{ formatCurrency(totalCredit) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </CardContent>
      </Card>

      <div>
        <Link href="/accounting/journal-entries">
          <Button variant="outline">{{ t('back') }}</Button>
        </Link>
      </div>
    </div>
  </AppLayout>
</template>