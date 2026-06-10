<script setup>
import { ref, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Badge from '@/Components/UI/Badge.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import ExportDropdown from '@/Components/UI/ExportDropdown.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import Modal from '@/Components/UI/Modal.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import HelpText from '@/Components/HelpText.vue'
import EmptyState from '@/Components/UI/EmptyState.vue'
import { useFormatters } from '@/lib/useFormatters'
import { useTranslations } from '@/lib/useTranslations'
import { BookText, Plus, Check, RotateCcw, Trash2, Pencil, HelpCircle } from 'lucide-vue-next'

const props = defineProps({
  entries: Object,
  accounts: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({ create: false, edit: false, delete: false }) },
})

const { t } = useTranslations()
const { formatCurrency, formatDate } = useFormatters()

const columns = computed(() => [
  { key: 'date', label: t('date'), format: v => formatDate(v) },
  { key: 'reference', label: t('reference') },
  { key: 'description', label: t('description') },
  { key: 'is_posted', label: t('status') },
  { key: 'actions', label: '', align: 'right' },
])

const accountOptions = computed(() => [
  { value: '', label: t('select_placeholder') },
  ...props.accounts.map(a => ({ value: String(a.id), label: `${a.code} — ${a.name}` })),
])

// Form modal state
const showForm = ref(false)
const editingEntry = ref(null)

const form = useForm({
  date: new Date().toISOString().split('T')[0],
  reference: '',
  description: '',
  is_posted: true,
  lines: [
    { account_id: '', debit: '0.00', credit: '0.00', description: '' },
    { account_id: '', debit: '0.00', credit: '0.00', description: '' },
  ],
})

function openCreate() {
  editingEntry.value = null
  form.reset()
  form.date = new Date().toISOString().split('T')[0]
  form.is_posted = true
  form.lines = [
    { account_id: '', debit: '0.00', credit: '0.00', description: '' },
    { account_id: '', debit: '0.00', credit: '0.00', description: '' },
  ]
  showForm.value = true
}

function openEdit(entry) {
  editingEntry.value = entry
  
  // Format date to YYYY-MM-DD for date input (handle both string and object dates)
  const dateStr = typeof entry.date === 'string' ? entry.date : entry.date?.toString()
  form.date = dateStr ? dateStr.split('T')[0].split(' ')[0] : new Date().toISOString().split('T')[0]
  
  form.reference = entry.reference || ''
  form.description = entry.description || ''
  form.is_posted = entry.is_posted
  form.lines = entry.lines.map(line => ({
    account_id: String(line.account_id),
    debit: String(line.debit || '0.00'),
    credit: String(line.credit || '0.00'),
    description: line.description || '',
  }))
  showForm.value = true
}

function submitForm(post = true) {
  form.is_posted = post

  if (editingEntry.value) {
    form.put(`/accounting/journal-entries/${editingEntry.value.id}`, {
      onSuccess: () => { showForm.value = false },
      preserveScroll: true,
    })
  } else {
    form.post('/accounting/journal-entries', {
      onSuccess: () => { showForm.value = false },
      preserveScroll: true,
    })
  }
}

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

function lineError(index, field) {
  return form.errors[`lines.${index}.${field}`]
}

// Post action
const postingEntry = ref(null)

function confirmPost(entry) {
  postingEntry.value = entry
}

function doPost() {
  router.post(`/accounting/journal-entries/${postingEntry.value.id}/post`, {}, {
    preserveScroll: true,
    onFinish: () => { postingEntry.value = null },
  })
}

// Reverse action
const reversingEntry = ref(null)

function confirmReverse(entry) {
  reversingEntry.value = entry
}

function doReverse() {
  router.post(`/accounting/journal-entries/${reversingEntry.value.id}/reverse`, {}, {
    preserveScroll: true,
    onFinish: () => { reversingEntry.value = null },
  })
}

// Delete action
const deletingEntry = ref(null)

function confirmDelete(entry) {
  deletingEntry.value = entry
}

function doDelete() {
  router.delete(`/accounting/journal-entries/${deletingEntry.value.id}`, {
    preserveScroll: true,
    onFinish: () => { deletingEntry.value = null },
  })
}
</script>

<template>
  <AppLayout :title="t('journal_entries')" help-page="accounting-basics">
    <HelpText :title="t('help_journal_title')" class="mb-6">
      <p>{{ t('help_journal_text') }}</p>
    </HelpText>

    <div class="mb-4 flex justify-end gap-2">
      <Button v-if="can.create" size="sm" @click="openCreate">
        <Plus class="mr-1 h-4 w-4" />
        {{ t('new_journal_entry') }}
      </Button>
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
              :action-label="can.create ? t('new_journal_entry') : null"
              @action="openCreate"
            />
          </template>
          <template #cell-is_posted="{ value }">
            <Badge :variant="value ? 'success' : 'warning'">{{ value ? t('posted') : t('draft') }}</Badge>
          </template>
          <template #cell-actions="{ row }">
            <div class="flex justify-end gap-1">
              <!-- Draft entry actions -->
              <template v-if="!row.is_posted">
                <Tooltip v-if="can.edit" :content="t('edit')" side="left">
                  <Button variant="ghost" size="icon" @click="openEdit(row)">
                    <Pencil class="h-4 w-4" />
                  </Button>
                </Tooltip>
                <Tooltip v-if="can.edit" :content="t('post')" side="left">
                  <Button variant="ghost" size="icon" @click="confirmPost(row)">
                    <Check class="h-4 w-4 text-[hsl(var(--success))]" />
                  </Button>
                </Tooltip>
                <Tooltip v-if="can.delete" :content="t('delete')" side="left">
                  <Button variant="ghost" size="icon" @click="confirmDelete(row)">
                    <Trash2 class="h-4 w-4 text-[hsl(var(--destructive))]" />
                  </Button>
                </Tooltip>
              </template>
              <!-- Posted entry actions (immutable - can only reverse) -->
              <template v-else>
                <Tooltip v-if="can.edit" :content="t('tooltip_reverse_journal_entry')" side="left">
                  <Button variant="ghost" size="icon" @click="confirmReverse(row)">
                    <RotateCcw class="h-4 w-4" />
                  </Button>
                </Tooltip>
              </template>
            </div>
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

    <!-- Create/Edit Modal -->
    <Modal
      :open="showForm"
      :title="editingEntry ? t('edit_journal_entry') : t('new_journal_entry')"
      size="xl"
      @close="showForm = false"
    >
      <form @submit.prevent="submitForm(true)">
        <div class="space-y-6">
          <!-- Header section -->
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

          <!-- Lines section -->
          <div>
            <div class="mb-2 flex items-center justify-between">
              <label class="text-sm font-medium">{{ t('entry_lines') }}</label>
              <Button type="button" size="sm" variant="outline" @click="addLine">
                <Plus class="mr-1 h-3 w-3" /> {{ t('add_line') }}
              </Button>
            </div>

            <div class="space-y-2">
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
          </div>

          <!-- Balance summary -->
          <div class="rounded-md border border-[hsl(var(--border))] bg-[hsl(var(--muted)/0.4)] p-4">
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

          <div v-if="form.errors.lines" class="text-xs text-[hsl(var(--destructive))]">{{ form.errors.lines }}</div>

          <!-- Actions -->
          <div class="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" @click="showForm = false">{{ t('cancel') }}</Button>
            <Button
              v-if="!editingEntry || !editingEntry.is_posted"
              type="button"
              variant="outline"
              :disabled="form.processing"
              @click="submitForm(false)"
            >
              {{ t('save_as_draft') }}
            </Button>
            <Button
              type="submit"
              :disabled="form.processing || !isBalanced"
              :loading="form.processing"
            >
              {{ editingEntry ? t('save_changes') : t('post_entry') }}
            </Button>
          </div>
        </div>
      </form>
    </Modal>

    <!-- Post confirm -->
    <ConfirmDialog
      :open="!!postingEntry"
      :title="t('post')"
      :message="t('confirm_post_journal_entry')"
      @confirm="doPost"
      @cancel="postingEntry = null"
    />

    <!-- Reverse confirm -->
    <ConfirmDialog
      :open="!!reversingEntry"
      :title="t('reverse')"
      :message="t('confirm_reverse_journal_entry')"
      @confirm="doReverse"
      @cancel="reversingEntry = null"
    />

    <!-- Delete confirm -->
    <ConfirmDialog
      :open="!!deletingEntry"
      :title="t('delete_journal_entry')"
      :message="t('confirm_delete_journal_entry')"
      variant="destructive"
      @confirm="doDelete"
      @cancel="deletingEntry = null"
    />
  </AppLayout>
</template>
