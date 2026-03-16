<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import Modal from '@/Components/UI/Modal.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import { formatCurrency, formatDate } from '@/lib/utils'
import { ref } from 'vue'

const props = defineProps({
  invoice: Object,
})

const showPaymentModal = ref(false)

const finalizeForm = useForm({})
const paymentForm = useForm({ amount: '' })

function finalize() {
  finalizeForm.post(`/invoices/${props.invoice.id}/finalize`)
}

function recordPayment() {
  paymentForm.post(`/invoices/${props.invoice.id}/payment`, {
    onSuccess: () => {
      showPaymentModal.value = false
      paymentForm.reset()
    },
  })
}

const lineColumns = [
  { key: 'description', label: 'Description' },
  { key: 'quantity', label: 'Qty', class: 'text-right' },
  { key: 'unit_price', label: 'Unit Price', class: 'text-right', format: (v) => formatCurrency(v) },
  { key: 'total', label: 'Total', class: 'text-right', format: (v, row) => formatCurrency(row.quantity * row.unit_price) },
]

const statusVariant = {
  draft: 'secondary',
  sent: 'default',
  paid: 'outline',
  overdue: 'destructive',
}
</script>

<template>
  <AppLayout :title="`Invoice ${invoice?.number}`" help-page="invoices">
    <div class="max-w-3xl space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <Badge :variant="statusVariant[invoice?.status] ?? 'secondary'" class="mb-2">
            {{ invoice?.status }}
          </Badge>
          <p class="text-sm text-[hsl(var(--muted-foreground))]">
            {{ invoice?.client?.name }} &middot; Issued {{ formatDate(invoice?.issue_date) }} &middot; Due {{ formatDate(invoice?.due_date) }}
          </p>
        </div>
        <div class="flex gap-2">
          <Button
            v-if="invoice?.status === 'draft'"
            variant="outline"
            :disabled="finalizeForm.processing"
            @click="finalize"
          >
            Finalize
          </Button>
          <Button
            v-if="invoice?.status === 'sent'"
            @click="showPaymentModal = true"
          >
            Record Payment
          </Button>
        </div>
      </div>

      <!-- Line Items -->
      <Card>
        <CardHeader>
          <CardTitle>Line Items</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable :columns="lineColumns" :rows="invoice?.lines ?? []" />
          <div class="mt-4 flex justify-end">
            <div class="text-right">
              <p class="text-sm text-[hsl(var(--muted-foreground))]">Total</p>
              <p class="text-2xl font-bold">{{ formatCurrency(invoice?.total) }}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Journal Entry -->
      <Card v-if="invoice?.journal_entry">
        <CardHeader>
          <CardTitle>Ledger Entry</CardTitle>
          <CardDescription>Ref: {{ invoice.journal_entry.reference }}</CardDescription>
        </CardHeader>
        <CardContent>
          <DataTable
            :columns="[
              { key: 'account', label: 'Account', format: (v) => v ? `${v.code} ${v.name}` : '—' },
              { key: 'debit', label: 'Debit', class: 'text-right', format: (v) => v ? formatCurrency(v) : '' },
              { key: 'credit', label: 'Credit', class: 'text-right', format: (v) => v ? formatCurrency(v) : '' },
            ]"
            :rows="invoice.journal_entry.lines ?? []"
          />
        </CardContent>
      </Card>
    </div>

    <!-- Payment Modal -->
    <Modal :open="showPaymentModal" title="Record Payment" @close="showPaymentModal = false">
      <form class="space-y-4" @submit.prevent="recordPayment">
        <FormInput
          id="payment-amount"
          v-model="paymentForm.amount"
          type="number"
          label="Amount"
          :error="paymentForm.errors.amount"
          required
        />
        <div class="flex justify-end gap-3">
          <Button type="button" variant="outline" @click="showPaymentModal = false">Cancel</Button>
          <Button type="submit" :disabled="paymentForm.processing">Record</Button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>
