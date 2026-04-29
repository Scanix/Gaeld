<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import Modal from '@/Components/UI/Modal.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { ref } from 'vue'
import { router, useForm, Link } from '@inertiajs/vue3'
import { ArrowLeft, Plus, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  group: Object,
  fiscal_year: Number,
  result: Object,
  accountOptions: {
    type: Array,
    default: () => [],
  },
})

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

// Consolidated report data
const reportColumns = [
  { key: 'code', label: t('code') },
  { key: 'name', label: t('account') },
  { key: 'balance', label: t('balance'), class: 'text-right', format: v => formatCurrency(v) },
]

// Eliminations
const eliminationColumns = [
  { key: 'description', label: t('description') },
  { key: 'amount', label: t('amount'), class: 'text-right', format: v => formatCurrency(v) },
  { key: 'actions', label: '', sortable: false },
]

// Add elimination dialog
const showElimForm = ref(false)
const elimForm = useForm({
  account_debit_id: '',
  account_credit_id: '',
  amount: '',
  fiscal_year: props.fiscal_year,
  description: '',
})

function openAddElimination() {
  elimForm.reset()
  elimForm.fiscal_year = props.fiscal_year
  elimForm.clearErrors()
  showElimForm.value = true
}

function submitElimination() {
  elimForm.post(`/accounting/consolidation/${props.group.id}/eliminations`, {
    preserveScroll: true,
    onSuccess: () => { showElimForm.value = false },
  })
}

// Delete elimination
const showDelete = ref(false)
const deletingElim = ref(null)
const deleteProcessing = ref(false)

function confirmDeleteElim(elim) {
  deletingElim.value = elim
  showDelete.value = true
}

function performDeleteElim() {
  if (!deletingElim.value) return
  deleteProcessing.value = true
  router.delete(`/accounting/consolidation/eliminations/${deletingElim.value.id}`, {
    preserveScroll: true,
    onFinish: () => {
      deleteProcessing.value = false
      showDelete.value = false
      deletingElim.value = null
    },
  })
}
</script>

<template>
  <AppLayout :title="`${t('consolidation_report')} — ${group.name}`">
    <div class="mb-4">
      <Link href="/accounting/consolidation" class="inline-flex items-center gap-1 text-sm text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]">
        <ArrowLeft class="h-4 w-4" /> {{ t('back') }}
      </Link>
    </div>

    <div class="space-y-6">
      <!-- Header -->
      <Card>
        <CardHeader>
          <CardTitle>{{ group.name }} — {{ fiscal_year }}</CardTitle>
        </CardHeader>
        <CardContent>
          <p class="text-sm text-[hsl(var(--muted-foreground))]">
            {{ t('consolidation_base_currency') }}: {{ group.base_currency }}
          </p>
        </CardContent>
      </Card>

      <Card v-if="result?.missing_exchange_rates?.length" class="border-amber-300 bg-amber-50/60">
        <CardContent class="pt-4 text-sm text-amber-900">
          <p class="font-semibold">{{ t('exchange_rates') }}</p>
          <p class="mt-1">
            {{ t('unexpected_error') }}
          </p>
          <p class="mt-1">
            Missing pairs: {{ result.missing_exchange_rates.join(', ') }}
          </p>
        </CardContent>
      </Card>

      <!-- Consolidated accounts -->
      <Card v-if="result?.accounts?.length">
        <CardHeader><CardTitle>{{ t('chart_of_accounts') }}</CardTitle></CardHeader>
        <CardContent>
          <DataTable :columns="reportColumns" :rows="result.accounts" />
        </CardContent>
      </Card>

      <!-- Eliminations -->
      <Card>
        <CardHeader>
          <div class="flex items-center justify-between">
            <CardTitle>{{ t('consolidation_eliminations') }}</CardTitle>
            <Button size="sm" @click="openAddElimination">
              <Plus class="mr-1 h-4 w-4" /> {{ t('add') }}
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <DataTable v-if="result?.eliminations?.length" :columns="eliminationColumns" :rows="result.eliminations">
            <template #cell-actions="{ row }">
              <div class="flex items-center gap-1 justify-end">
                <Button variant="ghost" size="icon" @click="confirmDeleteElim(row)">
                  <Trash2 class="h-4 w-4 text-[hsl(var(--destructive))]" />
                </Button>
              </div>
            </template>
          </DataTable>
          <p v-else class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('no_data') }}</p>
          <div v-if="result?.eliminations?.length" class="mt-4 flex justify-between border-t pt-3 text-sm font-semibold">
            <span>{{ t('eliminations_applied') }}</span>
            <span>{{ result.eliminations.length }}</span>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Add elimination dialog -->
    <Modal :open="showElimForm" :title="t('consolidation_eliminations')" @close="showElimForm = false">
      <form class="space-y-4" @submit.prevent="submitElimination">
        <FormSelect
          id="account_debit_id"
          v-model="elimForm.account_debit_id"
          :label="t('debit_account')"
          :options="accountOptions"
          :error="elimForm.errors.account_debit_id"
          :placeholder="t('select')"
          required
        />
        <FormSelect
          id="account_credit_id"
          v-model="elimForm.account_credit_id"
          :label="t('credit_account')"
          :options="accountOptions"
          :error="elimForm.errors.account_credit_id"
          :placeholder="t('select')"
          required
        />
        <FormInput id="amount" v-model="elimForm.amount" type="number" step="0.01" :label="t('amount')" :error="elimForm.errors.amount" required />
        <FormInput id="description" v-model="elimForm.description" :label="t('description')" :error="elimForm.errors.description" />
        <div class="flex justify-end gap-2">
          <Button variant="outline" type="button" @click="showElimForm = false">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="elimForm.processing" :loading="elimForm.processing">{{ t('save') }}</Button>
        </div>
      </form>
    </Modal>

    <ConfirmDialog
      :open="showDelete"
      :title="t('confirm_delete')"
      :message="deletingElim?.description ?? ''"
      :processing="deleteProcessing"
      @confirm="performDeleteElim"
      @cancel="showDelete = false"
    />
  </AppLayout>
</template>
