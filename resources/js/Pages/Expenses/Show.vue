<script setup>
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import Modal from '@/Components/UI/Modal.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import DropdownMenu from '@/Components/UI/DropdownMenu.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import { useFormatters } from '@/lib/useFormatters'
import { useTranslations } from '@/lib/useTranslations'
import { ref, computed } from 'vue'
import { Pencil, Trash2, CheckCircle } from 'lucide-vue-next'

const props = defineProps({
  expense: Object,
  receiptUrl: { type: String, default: null },
})

const showPostModal = ref(false)
const showDeleteDialog = ref(false)
const deleting = ref(false)
const postForm = useForm({ expense_account_code: '' })
const approveForm = useForm({})

function submitPost() {
  postForm.post(`/expenses/${props.expense.id}/post`, {
    onSuccess: () => { showPostModal.value = false },
  })
}

function approve() {
  approveForm.post(`/expenses/${props.expense.id}/approve`)
}

function removeReceipt() {
  router.delete(`/expenses/${props.expense.id}/receipt`)
}

function executeDelete() {
  deleting.value = true
  router.delete(`/expenses/${props.expense.id}`, {
    onFinish: () => { deleting.value = false },
  })
}

const statusVariant = {
  pending: 'warning',
  approved: 'info',
  posted: 'success',
}

const { t } = useTranslations()
const { formatCurrency, formatDate } = useFormatters()

const categoryKeys = {
  'Office Supplies': 'cat_office_supplies',
  'Travel': 'cat_travel',
  'Software': 'cat_software',
  'Professional Services': 'cat_professional_services',
  'Marketing': 'cat_marketing',
  'Rent': 'cat_rent',
  'Utilities': 'cat_utilities',
  'Insurance': 'cat_insurance',
  'Other': 'cat_other',
}
const categoryLabel = computed(() => {
  const key = categoryKeys[props.expense.category]
  return key ? t(key) : props.expense.category
})

const journalColumns = computed(() => [
  { key: 'account', label: t('account'), format: (_, row) => `${row.account?.code} — ${row.account?.name}` },
  { key: 'debit', label: t('debit'), format: v => formatCurrency(v) },
  { key: 'credit', label: t('credit'), format: v => formatCurrency(v) },
])
</script>

<template>
  <AppLayout :title="`${t('expense')} — ${categoryLabel}`" help-page="expenses">
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <Button as="a" href="/expenses" variant="outline" size="sm">← {{ t('back') }}</Button>
        <h2 class="text-xl font-semibold">{{ categoryLabel }}</h2>
        <Badge :variant="statusVariant[expense.status] || 'default'">{{ expense.status }}</Badge>
      </div>
      <div class="flex gap-2">
        <Button
          v-if="expense.status !== 'posted'"
          as="a"
          :href="`/expenses/${expense.id}/edit`"
          variant="outline"
          size="sm"
        >
          <Pencil class="mr-1 h-4 w-4" />
          {{ t('edit') }}
        </Button>
        <Button
          v-if="expense.status === 'pending' || expense.status === 'approved'"
          size="sm"
          @click="showPostModal = true"
        >
          {{ t('post_to_ledger') }}
        </Button>
        <DropdownMenu v-if="expense.status === 'pending'">
          <template #default="{ close }">
            <button
              class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))]"
              :disabled="approveForm.processing"
              @click="approve(); close()"
            >
              <CheckCircle class="h-4 w-4 shrink-0" />
              {{ t('approve') }}
            </button>
            <div class="my-1 border-t border-[hsl(var(--border))]" />
            <button
              class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-[hsl(var(--destructive))] hover:bg-[hsl(var(--destructive))]/10"
              @click="showDeleteDialog = true; close()"
            >
              <Trash2 class="h-4 w-4 shrink-0" />
              {{ t('delete') }}
            </button>
          </template>
        </DropdownMenu>
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <Card>
        <CardHeader><CardTitle>{{ t('details') }}</CardTitle></CardHeader>
        <CardContent>
          <dl class="grid grid-cols-2 gap-y-3 text-sm">
            <dt class="text-muted-foreground">{{ t('amount') }}</dt>
            <dd class="font-medium">{{ formatCurrency(expense.amount, expense.currency) }}</dd>
            <dt class="text-muted-foreground">{{ t('vat') }}</dt>
            <dd>{{ expense.vat_rate ? `${expense.vat_rate.name} (${expense.vat_rate.rate}%)` : '—' }}</dd>
            <dt class="text-muted-foreground">{{ t('vat_amount') }}</dt>
            <dd>{{ expense.vat_amount ? formatCurrency(expense.vat_amount, expense.currency) : '—' }}</dd>
            <dt class="text-muted-foreground">{{ t('date') }}</dt>
            <dd>{{ formatDate(expense.date) }}</dd>
            <dt class="text-muted-foreground">{{ t('vendor') }}</dt>
            <dd>{{ expense.vendor || '—' }}</dd>
            <dt class="text-muted-foreground">{{ t('supplier') }}</dt>
            <dd>
              <a v-if="expense.supplier" :href="`/contacts/suppliers/${expense.supplier.id}`" class="text-[hsl(var(--primary))] hover:underline">
                {{ expense.supplier.name }}
              </a>
              <span v-else>—</span>
            </dd>
            <dt class="text-muted-foreground">{{ t('description') }}</dt>
            <dd>{{ expense.description || '—' }}</dd>
          </dl>
        </CardContent>
      </Card>

      <!-- Receipt -->
      <Card v-if="receiptUrl">
        <CardHeader>
          <div class="flex items-center justify-between">
            <CardTitle>{{ t('receipt') }}</CardTitle>
            <Button
              v-if="expense.status !== 'posted'"
              variant="ghost"
              size="sm"
              @click="removeReceipt"
            >
              <Trash2 class="mr-1 h-4 w-4" />
              {{ t('remove') }}
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <a :href="receiptUrl" target="_blank" class="text-sm text-[hsl(var(--primary))] hover:underline">
            {{ t('view_receipt') }}
          </a>
        </CardContent>
      </Card>

      <Card v-if="expense.journal_entry">
        <CardHeader><CardTitle>{{ t('journal_entry') }}</CardTitle></CardHeader>
        <CardContent>
          <p class="mb-2 text-sm text-muted-foreground">
            {{ expense.journal_entry.reference }} — {{ formatDate(expense.journal_entry.date) }}
          </p>
          <DataTable :columns="journalColumns" :rows="expense.journal_entry.lines" />
        </CardContent>
      </Card>
    </div>

    <Modal :show="showPostModal" @close="showPostModal = false" :title="t('post_expense_ledger')">
      <form class="space-y-6" @submit.prevent="submitPost">
        <FormInput
          id="expense_account_code"
          v-model="postForm.expense_account_code"
          :label="t('expense_account_code')"
          placeholder="e.g. 6000"
          :error="postForm.errors.expense_account_code"
          required
        />
        <div class="flex justify-end gap-3">
          <Button variant="outline" @click="showPostModal = false">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="postForm.processing">{{ t('post') }}</Button>
        </div>
      </form>
    </Modal>

    <ConfirmDialog
      :open="showDeleteDialog"
      :title="t('delete_expense')"
      :message="t('delete_expense_confirm')"
      :confirm-label="t('delete')"
      :processing="deleting"
      @confirm="executeDelete"
      @cancel="showDeleteDialog = false"
    />
  </AppLayout>
</template>
