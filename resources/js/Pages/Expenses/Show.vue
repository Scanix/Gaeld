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
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import DropdownMenu from '@/Components/UI/DropdownMenu.vue'
import { useFormatters } from '@/lib/useFormatters'
import { useTranslations } from '@/lib/useTranslations'
import { ref, computed } from 'vue'
import { Pencil, Trash2, CheckCircle, Download, Eye, X } from 'lucide-vue-next'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'

const props = defineProps({
  expense: Object,
  receiptUrl: { type: String, default: null },
})

const showPostDialog = ref(false)
const showDeleteDialog = ref(false)
const showReceiptPreview = ref(false)
const deleting = ref(false)
const posting = ref(false)
const approveForm = useForm({})

function submitPost() {
  posting.value = true
  router.post(`/expenses/${props.expense.id}/post`, {}, {
    onFinish: () => { posting.value = false; showPostDialog.value = false },
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

const expenseTitle = computed(() => props.expense.description || categoryLabel.value)

const journalColumns = computed(() => [
  { key: 'account', label: t('account'), format: (_, row) => `${row.account?.code} — ${row.account?.name}` },
  { key: 'debit', label: t('debit'), format: v => formatCurrency(v) },
  { key: 'credit', label: t('credit'), format: v => formatCurrency(v) },
])
</script>

<template>
  <AppLayout :title="`${t('expense')} — ${expenseTitle}`" help-page="expenses">
    <Breadcrumb :items="[{ label: t('expenses'), href: '/expenses' }, { label: expenseTitle }]" class="mb-4" />

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
      <div class="flex items-center gap-3">
        <h2 class="text-xl font-semibold">{{ expenseTitle }}</h2>
        <Badge :variant="statusVariant[expense.status] || 'default'">{{ t('expense_status_' + expense.status) }}</Badge>
        <Badge v-if="expense.archived_at" variant="secondary">{{ t('archived') }}</Badge>
      </div>
      <div class="flex flex-wrap gap-2">
        <Button
          v-if="expense.status !== 'posted' && !expense.archived_at"
          as="a"
          :href="`/expenses/${expense.id}/edit`"
          variant="outline"
          size="sm"
        >
          <Pencil class="mr-1 h-4 w-4" />
          {{ t('edit') }}
        </Button>
        <Button
          v-if="expense.status === 'approved' && !expense.archived_at"
          size="sm"
          :disabled="!expense.expense_account_code"
          :title="!expense.expense_account_code ? t('expense_account_code_required') : undefined"
          @click="expense.expense_account_code && (showPostDialog = true)"
        >
          {{ t('post_to_ledger') }}
        </Button>
        <DropdownMenu v-if="expense.status === 'pending' && !expense.archived_at">
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
            <dt class="text-muted-foreground">{{ t('supplier') }}</dt>
            <dd>
              <a v-if="expense.supplier" :href="`/contacts/${expense.supplier.uuid}`" class="text-[hsl(var(--primary))] hover:underline">
                {{ expense.supplier.name }}
              </a>
              <span v-else-if="expense.vendor">{{ expense.vendor }}</span>
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
              v-if="expense.status !== 'posted' && !expense.archived_at"
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
          <div class="flex flex-wrap items-center gap-3">
            <Button variant="outline" size="sm" @click="showReceiptPreview = true">
              <Eye class="mr-1.5 h-4 w-4" />
              {{ t('view_receipt') }}
            </Button>
            <a
              :href="receiptUrl"
              class="inline-flex items-center gap-1.5 text-sm text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] transition-colors"
            >
              <Download class="h-4 w-4" />
              {{ t('download') }}
            </a>
          </div>
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

    <ConfirmDialog
      :open="showPostDialog"
      :title="t('post_expense_ledger')"
      :message="t('post_expense_confirm')"
      :confirm-label="t('post')"
      :processing="posting"
      @confirm="submitPost"
      @cancel="showPostDialog = false"
    />

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

  <!-- Receipt preview overlay -->
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="showReceiptPreview" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/60" @click="showReceiptPreview = false" />
        <div class="relative z-50 flex w-full max-w-4xl flex-col rounded-xl border border-[hsl(var(--border))] bg-[hsl(var(--background))] shadow-xl" style="max-height: 90dvh;">
          <div class="flex shrink-0 items-center justify-between border-b border-[hsl(var(--border))] px-4 py-3">
            <h2 class="text-base font-semibold">{{ t('receipt') }}</h2>
            <div class="flex items-center gap-2">
              <a
                :href="receiptUrl"
                class="inline-flex items-center gap-1.5 text-sm text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] transition-colors"
              >
                <Download class="h-4 w-4" />
                {{ t('download') }}
              </a>
              <Button variant="ghost" size="icon" :aria-label="t('close')" @click="showReceiptPreview = false">
                <X class="h-4 w-4" />
              </Button>
            </div>
          </div>
          <div class="flex-1 overflow-hidden p-2">
            <iframe
              :src="`${receiptUrl}?inline=1`"
              class="h-full w-full rounded border-0"
              style="min-height: 70dvh;"
              :title="t('receipt')"
            />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
