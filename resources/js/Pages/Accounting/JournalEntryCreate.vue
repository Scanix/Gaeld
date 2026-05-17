<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import HelpText from '@/Components/HelpText.vue'
import { useForm, Link } from '@inertiajs/vue3'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { computed } from 'vue'
import { Plus, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  accounts: { type: Array, default: () => [] },
  defaultDate: { type: String, default: '' },
})

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

const accountOptions = computed(() => [
  { value: '', label: t('select_placeholder') },
  ...props.accounts.map(a => ({ value: String(a.id), label: `${a.code} — ${a.name}` })),
])

const form = useForm({
  date: props.defaultDate,
  reference: '',
  description: '',
  is_posted: true,
  lines: [
    { account_id: '', debit: '0.00', credit: '0.00', description: '' },
    { account_id: '', debit: '0.00', credit: '0.00', description: '' },
  ],
})

function addLine() {
  form.lines.push({ account_id: '', debit: '0.00', credit: '0.00', description: '' })
}

function removeLine(index) {
  if (form.lines.length <= 2) return
  form.lines.splice(index, 1)
}

const totalDebit = computed(() =>
  form.lines.reduce((sum, l) => sum + (parseFloat(l.debit) || 0), 0)
)
const totalCredit = computed(() =>
  form.lines.reduce((sum, l) => sum + (parseFloat(l.credit) || 0), 0)
)
const difference = computed(() => +(totalDebit.value - totalCredit.value).toFixed(2))
const isBalanced = computed(() => difference.value === 0 && totalDebit.value > 0)

function submit(post = true) {
  form.is_posted = post
  form.post('/accounting/journal-entries', { preserveScroll: true })
}

function lineError(index, field) {
  return form.errors[`lines.${index}.${field}`]
}
</script>

<template>
  <AppLayout :title="t('new_journal_entry')" help-page="accounting-basics">
    <HelpText :title="t('new_journal_entry')" class="mb-6">
      <p>{{ t('help_new_journal_entry') }}</p>
    </HelpText>

    <form @submit.prevent="submit(true)">
      <Card class="mb-4">
        <CardHeader><CardTitle>{{ t('entry_header') }}</CardTitle></CardHeader>
        <CardContent>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <FormInput
              id="date"
              v-model="form.date"
              type="date"
              :label="t('date')"
              :error="form.errors.date"
              required
            />
            <FormInput
              id="reference"
              v-model="form.reference"
              :label="t('reference')"
              :error="form.errors.reference"
              :placeholder="t('reference_placeholder')"
            />
            <FormInput
              id="description"
              v-model="form.description"
              :label="t('description')"
              :error="form.errors.description"
            />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <div class="flex items-center justify-between">
            <CardTitle>{{ t('entry_lines') }}</CardTitle>
            <Button type="button" size="sm" variant="outline" @click="addLine">
              <Plus class="mr-1 h-4 w-4" /> {{ t('add_line') }}
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <div class="space-y-3">
            <div
              v-for="(line, index) in form.lines"
              :key="index"
              class="grid grid-cols-12 gap-2 items-start"
            >
              <div class="col-span-12 sm:col-span-5">
                <FormSelect
                  :id="`account_${index}`"
                  v-model="line.account_id"
                  :label="index === 0 ? t('account') : ''"
                  :options="accountOptions"
                  :error="lineError(index, 'account_id')"
                  required
                />
              </div>
              <div class="col-span-5 sm:col-span-2">
                <FormInput
                  :id="`debit_${index}`"
                  v-model="line.debit"
                  type="number"
                  step="0.01"
                  min="0"
                  :label="index === 0 ? t('debit') : ''"
                  :error="lineError(index, 'debit')"
                />
              </div>
              <div class="col-span-5 sm:col-span-2">
                <FormInput
                  :id="`credit_${index}`"
                  v-model="line.credit"
                  type="number"
                  step="0.01"
                  min="0"
                  :label="index === 0 ? t('credit') : ''"
                  :error="lineError(index, 'credit')"
                />
              </div>
              <div class="col-span-10 sm:col-span-2">
                <FormInput
                  :id="`line_desc_${index}`"
                  v-model="line.description"
                  :label="index === 0 ? t('description') : ''"
                  :error="lineError(index, 'description')"
                />
              </div>
              <div class="col-span-2 sm:col-span-1 flex items-end pb-2">
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  :disabled="form.lines.length <= 2"
                  @click="removeLine(index)"
                >
                  <Trash2 class="h-4 w-4" />
                </Button>
              </div>
            </div>
          </div>

          <div class="mt-6 rounded-md border border-[hsl(var(--border))] bg-[hsl(var(--muted)/0.4)] p-4">
            <div class="grid grid-cols-3 gap-2 text-sm">
              <div>
                <div class="text-[hsl(var(--muted-foreground))]">{{ t('total_debit') }}</div>
                <div class="text-base font-semibold">{{ formatCurrency(totalDebit) }}</div>
              </div>
              <div>
                <div class="text-[hsl(var(--muted-foreground))]">{{ t('total_credit') }}</div>
                <div class="text-base font-semibold">{{ formatCurrency(totalCredit) }}</div>
              </div>
              <div>
                <div class="text-[hsl(var(--muted-foreground))]">{{ t('difference') }}</div>
                <div
                  class="text-base font-semibold"
                  :class="isBalanced ? 'text-[hsl(var(--success))]' : 'text-[hsl(var(--destructive))]'"
                >
                  {{ formatCurrency(difference) }}
                  <span class="ml-1 text-xs font-normal">
                    {{ isBalanced ? t('entry_balanced') : t('entry_unbalanced') }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div v-if="form.errors.lines" class="mt-2 text-xs text-[hsl(var(--destructive))]">{{ form.errors.lines }}</div>

          <div class="mt-6 flex flex-wrap justify-end gap-2">
            <Link href="/accounting/journal-entries">
              <Button type="button" variant="outline">{{ t('cancel') }}</Button>
            </Link>
            <Button
              type="button"
              variant="outline"
              :disabled="form.processing"
              @click="submit(false)"
            >
              {{ t('save_as_draft') }}
            </Button>
            <Button
              type="submit"
              :disabled="form.processing || !isBalanced"
              :loading="form.processing"
            >
              {{ t('post_entry') }}
            </Button>
          </div>
        </CardContent>
      </Card>
    </form>
  </AppLayout>
</template>
