<script setup>
import { ref, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import SearchableSelect from '@/Components/UI/SearchableSelect.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import HelpText from '@/Components/HelpText.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { buildAccountOptions } from '@/lib/accountOptions'
import { useDocsUrl } from '@/lib/useDocsUrl'
import EmptyState from '@/Components/UI/EmptyState.vue'
import { Plus, Trash2, PieChart } from 'lucide-vue-next'

const props = defineProps({
  budgets: { type: Object, default: () => ({}) },
  accounts: { type: Array, default: () => [] },
  fiscalYears: { type: Array, default: () => [] },
  selectedYear: { type: [String, Number], default: null },
})

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

// Fiscal year selector
const year = ref(props.selectedYear ?? props.fiscalYears[0]?.value ?? new Date().getFullYear())

function switchYear(val) {
  year.value = val
  router.get('/accounting/budgets', { year: val }, { preserveState: true })
}

// Account options for searchable selector
const accountOptions = computed(() => buildAccountOptions(props.accounts))
const { url: docsUrl } = useDocsUrl()
const chartHelpHref = docsUrl('chart-of-accounts')

// Inline editing: track dirty rows
const editingRows = ref({})

function startEdit(budget) {
  editingRows.value[budget.id] = { monthly_amount: budget.monthly_amount }
}

function cancelEdit(id) {
  delete editingRows.value[id]
}

function saveRow(budget) {
  const data = editingRows.value[budget.id]
  router.patch(`/accounting/budgets/${budget.id}`, {
    monthly_amount: parseFloat(data.monthly_amount) || 0,
  }, {
    preserveScroll: true,
    onSuccess: () => { delete editingRows.value[budget.id] },
  })
}

// Delete row
const showDelete = ref(false)
const deletingId = ref(null)

function confirmDelete(id) {
  deletingId.value = id
  showDelete.value = true
}

function performDelete() {
  router.delete(`/accounting/budgets/${deletingId.value}`, {
    preserveScroll: true,
    onSuccess: () => {
      showDelete.value = false
      deletingId.value = null
    },
  })
}

// Add new row
const showAddForm = ref(false)
const addForm = useForm({
  account_id: null,
  monthly_amount: '',
  fiscal_year: year.value,
})

function submitAdd() {
  addForm.fiscal_year = year.value
  addForm.post('/accounting/budgets', {
    preserveScroll: true,
    onSuccess: () => {
      showAddForm.value = false
      addForm.reset()
    },
  })
}

function annualTotal(monthly) {
  return (parseFloat(monthly) || 0) * 12
}
</script>

<template>
  <AppLayout :title="t('budget')">
    <HelpText :title="t('help_budget_title')" class="mb-6">
      <p>{{ t('help_budget_text') }}</p>
    </HelpText>

    <!-- Year selector + add button -->
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
      <FormSelect
        id="budget-year"
        v-model="year"
        :label="t('fiscal_year')"
        :options="fiscalYears.length ? fiscalYears : [{ value: year, label: String(year) }]"
        option-value="value"
        option-label="label"
        class="w-36"
        @change="switchYear(year)"
      />
      <Button @click="showAddForm = !showAddForm">
        <Plus class="mr-1.5 h-4 w-4" />
        {{ t('add_budget_line') }}
      </Button>
    </div>

    <!-- Add row form -->
    <Card v-if="showAddForm" class="mb-6">
      <CardHeader><CardTitle>{{ t('add_budget_line') }}</CardTitle></CardHeader>
      <CardContent>
        <div class="flex flex-wrap items-end gap-4">
          <div class="flex-1 min-w-48">
            <SearchableSelect
              id="add-account"
              v-model="addForm.account_id"
              :label="t('account')"
              :options="accountOptions"
              group-key="group"
              :placeholder="t('select_account')"
              :error="addForm.errors.account_id"
              :help-href="chartHelpHref"
              :help-label="t('chart_of_accounts')"
            />
          </div>
          <FormInput
            id="add-monthly"
            v-model="addForm.monthly_amount"
            type="number"
            :label="t('budget_monthly_amount')"
            placeholder="0.00"
            class="w-40"
            :error="addForm.errors.monthly_amount"
          />
          <div class="flex gap-2 pb-0.5">
            <Button :disabled="addForm.processing" @click="submitAdd">{{ t('save') }}</Button>
            <Button variant="outline" @click="showAddForm = false; addForm.reset()">{{ t('cancel') }}</Button>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Budget table -->
    <Card>
      <CardContent class="overflow-x-auto p-0">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b bg-[hsl(var(--muted))]/50 text-xs font-medium text-[hsl(var(--muted-foreground))]">
              <th class="px-4 py-3 text-left">{{ t('code') }}</th>
              <th class="px-4 py-3 text-left">{{ t('account') }}</th>
              <th class="px-4 py-3 text-right">{{ t('budget_monthly_amount') }}</th>
              <th class="px-4 py-3 text-right">{{ t('budget_annual_total') }}</th>
              <th class="px-4 py-3 w-24" />
            </tr>
          </thead>
          <tbody>
            <template v-if="(budgets?.data ?? []).length">
              <tr
                v-for="budget in (budgets?.data ?? [])"
                :key="budget.id"
                class="border-b last:border-0 hover:bg-[hsl(var(--accent))]/40"
              >
                <td class="px-4 py-2.5 font-mono text-xs text-[hsl(var(--muted-foreground))]">
                  {{ budget.account?.code }}
                </td>
                <td class="px-4 py-2.5 font-medium">{{ budget.account?.display_name ?? budget.account?.name }}</td>

                <!-- Editable monthly amount -->
                <td class="px-4 py-2.5 text-right">
                  <template v-if="editingRows[budget.id]">
                    <input
                      v-model="editingRows[budget.id].monthly_amount"
                      type="number"
                      class="w-28 rounded border border-[hsl(var(--input))] bg-transparent px-2 py-1 text-right text-sm"
                    />
                  </template>
                  <span v-else class="tabular-nums">{{ formatCurrency(budget.monthly_amount) }}</span>
                </td>

                <td class="px-4 py-2.5 text-right tabular-nums text-[hsl(var(--muted-foreground))]">
                  {{ formatCurrency(editingRows[budget.id]
                    ? annualTotal(editingRows[budget.id].monthly_amount)
                    : annualTotal(budget.monthly_amount)) }}
                </td>

                <td class="px-4 py-2.5">
                  <div class="flex items-center justify-end gap-1">
                    <template v-if="editingRows[budget.id]">
                      <Button size="sm" @click="saveRow(budget)">{{ t('save') }}</Button>
                      <Button size="sm" variant="ghost" @click="cancelEdit(budget.id)">{{ t('cancel') }}</Button>
                    </template>
                    <template v-else>
                      <Button size="sm" variant="ghost" @click="startEdit(budget)">{{ t('edit') }}</Button>
                      <Button size="icon" variant="ghost" @click="confirmDelete(budget.id)">
                        <Trash2 class="h-4 w-4 text-[hsl(var(--destructive))]" />
                      </Button>
                    </template>
                  </div>
                </td>
              </tr>
            </template>
            <tr v-else>
              <td colspan="5" class="px-4 py-8 text-center">
                <EmptyState
                  :icon="PieChart"
                  :title="t('empty_budgets_title')"
                  :description="t('empty_budgets_desc')"
                  :action-label="t('create_first')"
                  @action="showAddForm = true"
                />
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="budgets?.last_page > 1" class="flex items-center justify-between border-t border-[hsl(var(--border))] px-4 py-3">
          <span class="text-sm text-[hsl(var(--muted-foreground))]">
            {{ t('page') }} {{ budgets.current_page }} / {{ budgets.last_page }}
          </span>
          <div class="flex gap-2">
            <Button v-if="budgets.prev_page_url" variant="outline" size="sm" @click="router.get(budgets.prev_page_url)">{{ t('previous') }}</Button>
            <Button v-if="budgets.next_page_url" variant="outline" size="sm" @click="router.get(budgets.next_page_url)">{{ t('next') }}</Button>
          </div>
        </div>
      </CardContent>
    </Card>

    <ConfirmDialog
      :open="showDelete"
      :title="t('delete')"
      :message="t('budget_delete_confirm')"
      :confirm-label="t('delete')"
      confirm-variant="destructive"
      @confirm="performDelete"
      @cancel="showDelete = false; deletingId = null"
    />
  </AppLayout>
</template>
