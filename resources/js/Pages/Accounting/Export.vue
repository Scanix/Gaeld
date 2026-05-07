<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import HelpText from '@/Components/HelpText.vue'
import { useTranslations } from '@/lib/useTranslations'
import { Download, FileArchive, FileText, BookOpen, Calculator, ChevronRight } from 'lucide-vue-next'

const { t } = useTranslations()

const props = defineProps({
  fiscalYears: { type: Array, default: () => [] },
  currentFiscalYear: { type: [String, Number], default: null },
})

const yearOptions = props.fiscalYears.map(y => ({ value: String(y), label: String(y) }))

const form = useForm({ fiscal_year: props.currentFiscalYear ? String(props.currentFiscalYear) : '' })

function generateExport() {
  if (!form.fiscal_year) return
  form.post('/accounting/export', {
    preserveScroll: true,
  })
}

const zipContents = [
  { icon: FileText, key: 'export_content_journal' },
  { icon: BookOpen, key: 'export_content_chart' },
  { icon: Calculator, key: 'export_content_trial_balance' },
  { icon: FileText, key: 'export_content_pl' },
  { icon: FileText, key: 'export_content_balance_sheet' },
  { icon: FileText, key: 'export_content_vat' },
  { icon: FileText, key: 'export_content_invoices' },
  { icon: FileText, key: 'export_content_expenses' },
]
</script>

<template>
  <AppLayout :title="t('fiduciary_export')" help-page="fiduciary-export">
    <HelpText :title="t('help_export_title')" class="mb-6">
      <p>{{ t('help_export_text') }}</p>
    </HelpText>

    <div class="max-w-2xl space-y-6">
      <!-- Period selection -->
      <Card>
        <CardHeader>
          <CardTitle>{{ t('select_fiscal_year') }}</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <FormSelect
            id="fiscal_year"
            v-model="form.fiscal_year"
            :label="t('fiscal_year')"
            :options="yearOptions"
            :error="form.errors.fiscal_year"
          />

          <Button
            :disabled="!form.fiscal_year || form.processing"
            class="w-full sm:w-auto"
            @click="generateExport"
          >
            <template v-if="form.processing">
              <svg class="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              {{ t('generating_export') }}…
            </template>
            <template v-else>
              <Download class="mr-2 h-4 w-4" />
              {{ t('generate_export') }}
            </template>
          </Button>
        </CardContent>
      </Card>

      <!-- ZIP contents description -->
      <Card>
        <CardHeader>
          <CardTitle>
            <FileArchive class="mr-2 inline-block h-5 w-5" />
            {{ t('export_zip_contents') }}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <ul class="space-y-3">
            <li
              v-for="item in zipContents"
              :key="item.key"
              class="flex items-center gap-3 text-sm"
            >
              <component :is="item.icon" class="h-4 w-4 shrink-0 text-[hsl(var(--muted-foreground))]" />
              <span>{{ t(item.key) }}</span>
            </li>
          </ul>
          <p class="mt-4 text-xs text-[hsl(var(--muted-foreground))]">{{ t('export_format_note') }}</p>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
