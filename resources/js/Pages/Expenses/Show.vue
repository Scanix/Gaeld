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
import FormInput from '@/Components/UI/FormInput.vue'
import { formatCurrency, formatDate } from '@/lib/utils'
import { ref } from 'vue'

const props = defineProps({
  expense: Object,
})

const showPostModal = ref(false)
const postForm = useForm({ expense_account_code: '' })

function submitPost() {
  postForm.post(`/expenses/${props.expense.id}/post`, {
    onSuccess: () => { showPostModal.value = false },
  })
}

const statusVariant = {
  pending: 'warning',
  approved: 'info',
  posted: 'success',
}

const journalColumns = [
  { key: 'account', label: 'Account', format: (_, row) => `${row.account?.code} — ${row.account?.name}` },
  { key: 'debit', label: 'Debit', format: v => formatCurrency(v) },
  { key: 'credit', label: 'Credit', format: v => formatCurrency(v) },
]
</script>

<template>
  <AppLayout :title="`Expense — ${expense.category}`" help-page="expenses">
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <Button as="a" href="/expenses" variant="outline" size="sm">← Back</Button>
        <h2 class="text-xl font-semibold">{{ expense.category }}</h2>
        <Badge :variant="statusVariant[expense.status] || 'default'">{{ expense.status }}</Badge>
      </div>
      <Button
        v-if="expense.status === 'pending' || expense.status === 'approved'"
        @click="showPostModal = true"
      >
        Post to Ledger
      </Button>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <Card>
        <CardHeader><CardTitle>Details</CardTitle></CardHeader>
        <CardContent>
          <dl class="grid grid-cols-2 gap-y-3 text-sm">
            <dt class="text-muted-foreground">Amount</dt>
            <dd class="font-medium">{{ formatCurrency(expense.amount, expense.currency) }}</dd>
            <dt class="text-muted-foreground">VAT</dt>
            <dd>{{ expense.vat_rate ? `${expense.vat_rate.name} (${expense.vat_rate.rate}%)` : '—' }}</dd>
            <dt class="text-muted-foreground">VAT Amount</dt>
            <dd>{{ expense.vat_amount ? formatCurrency(expense.vat_amount, expense.currency) : '—' }}</dd>
            <dt class="text-muted-foreground">Date</dt>
            <dd>{{ formatDate(expense.date) }}</dd>
            <dt class="text-muted-foreground">Vendor</dt>
            <dd>{{ expense.vendor || '—' }}</dd>
            <dt class="text-muted-foreground">Description</dt>
            <dd>{{ expense.description || '—' }}</dd>
          </dl>
        </CardContent>
      </Card>

      <Card v-if="expense.journal_entry">
        <CardHeader><CardTitle>Journal Entry</CardTitle></CardHeader>
        <CardContent>
          <p class="mb-2 text-sm text-muted-foreground">
            {{ expense.journal_entry.reference }} — {{ formatDate(expense.journal_entry.date) }}
          </p>
          <DataTable :columns="journalColumns" :rows="expense.journal_entry.lines" />
        </CardContent>
      </Card>
    </div>

    <Modal :show="showPostModal" @close="showPostModal = false" title="Post Expense to Ledger">
      <form class="space-y-4" @submit.prevent="submitPost">
        <FormInput
          id="expense_account_code"
          v-model="postForm.expense_account_code"
          label="Expense Account Code"
          placeholder="e.g. 6000"
          :error="postForm.errors.expense_account_code"
          required
        />
        <div class="flex justify-end gap-3">
          <Button variant="outline" @click="showPostModal = false">Cancel</Button>
          <Button type="submit" :disabled="postForm.processing">Post</Button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>
