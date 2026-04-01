<script setup>
import { ref } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
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
import FormSelect from '@/Components/UI/FormSelect.vue'
import Combobox from '@/Components/UI/Combobox.vue'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { computed } from 'vue'

const { t } = useTranslations()
const { formatDate } = useFormatters()

const props = defineProps({
  asset: Object,
  depreciationHistory: { type: Array, default: () => [] },
  accounts: { type: Array, default: () => [] },
})

const showDisposeModal = ref(false)
const recordForm = useForm({})
const disposeForm = useForm({
  disposal_date: new Date().toISOString().slice(0, 10),
  disposal_amount: '',
  disposal_account_id: null,
})

const accountOptions = props.accounts.map(a => ({ value: a.id, label: `${a.code} — ${a.name}` }))

function recordDepreciation() {
  recordForm.post(`/assets/${props.asset.id}/depreciate`)
}

function submitDisposal() {
  disposeForm.post(`/assets/${props.asset.id}/dispose`, {
    onSuccess: () => { showDisposeModal.value = false },
  })
}

function formatSwiss(value) {
  if (value == null) return '—'
  return Number(value).toLocaleString('de-CH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const depreciationProgress = computed(() => {
  if (!props.asset.purchase_amount || props.asset.purchase_amount === 0) return 0
  const accumulated = props.asset.purchase_amount - props.asset.net_book_value
  return Math.min(100, Math.round((accumulated / props.asset.purchase_amount) * 100))
})

const historyColumns = computed(() => [
  { key: 'depreciation_date', label: t('date') },
  { key: 'amount', label: t('amount'), class: 'text-right' },
  { key: 'book_value_after', label: t('net_book_value'), class: 'text-right' },
  { key: 'journal_entry_id', label: t('journal_entry') },
])
</script>

<template>
  <AppLayout :title="asset.name" help-page="assets">
    <Breadcrumb :items="[{ label: t('assets'), href: '/assets' }, { label: asset.name }]" class="mb-4" />

    <!-- Asset card -->
    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
      <Card class="lg:col-span-2">
        <CardHeader>
          <div class="flex items-start justify-between">
            <div>
              <CardTitle>{{ asset.name }}</CardTitle>
              <CardDescription v-if="asset.description">{{ asset.description }}</CardDescription>
            </div>
            <Badge :variant="asset.status === 'active' ? 'default' : asset.status === 'disposed' ? 'destructive' : 'secondary'">
              {{ t('asset_status_' + asset.status) }}
            </Badge>
          </div>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <p class="text-[hsl(var(--muted-foreground))]">{{ t('purchase_date') }}</p>
              <p class="font-medium">{{ formatDate(asset.purchase_date) }}</p>
            </div>
            <div>
              <p class="text-[hsl(var(--muted-foreground))]">{{ t('purchase_amount') }}</p>
              <p class="font-medium font-mono">CHF {{ formatSwiss(asset.purchase_amount) }}</p>
            </div>
            <div>
              <p class="text-[hsl(var(--muted-foreground))]">{{ t('depreciation_method') }}</p>
              <p class="font-medium">{{ asset.depreciation_method === 'straight_line' ? t('straight_line') : t('declining_balance') }}</p>
            </div>
            <div>
              <p class="text-[hsl(var(--muted-foreground))]">{{ t('useful_life') }}</p>
              <p class="font-medium">{{ asset.useful_life_years }} {{ t('years') }}</p>
            </div>
            <div>
              <p class="text-[hsl(var(--muted-foreground))]">{{ t('salvage_value') }}</p>
              <p class="font-medium font-mono">CHF {{ formatSwiss(asset.salvage_value) }}</p>
            </div>
            <div>
              <p class="text-[hsl(var(--muted-foreground))]">{{ t('net_book_value') }}</p>
              <p class="font-medium font-mono text-[hsl(var(--primary))]">CHF {{ formatSwiss(asset.net_book_value) }}</p>
            </div>
          </div>

          <!-- Depreciation progress -->
          <div>
            <div class="mb-1 flex items-center justify-between text-sm">
              <span class="text-[hsl(var(--muted-foreground))]">{{ t('depreciation_progress') }}</span>
              <span class="font-medium">{{ depreciationProgress }}%</span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-[hsl(var(--muted))]">
              <div
                class="h-full rounded-full bg-[hsl(var(--primary))] transition-all"
                :style="{ width: depreciationProgress + '%' }"
              />
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Actions card -->
      <Card>
        <CardHeader>
          <CardTitle>{{ t('actions') }}</CardTitle>
        </CardHeader>
        <CardContent class="flex flex-col gap-3">
          <Button
            v-if="asset.status === 'active'"
            :disabled="recordForm.processing"
            class="w-full"
            @click="recordDepreciation"
          >
            {{ t('record_depreciation') }}
          </Button>
          <Button
            v-if="asset.status === 'active'"
            variant="destructive"
            class="w-full"
            @click="showDisposeModal = true"
          >
            {{ t('dispose_asset') }}
          </Button>
          <p v-if="asset.status === 'disposed'" class="text-sm text-[hsl(var(--muted-foreground))]">
            {{ t('asset_disposed_on') }} {{ formatDate(asset.disposed_at) }}
          </p>
        </CardContent>
      </Card>
    </div>

    <!-- Depreciation history -->
    <Card>
      <CardHeader>
        <CardTitle>{{ t('depreciation_history') }}</CardTitle>
      </CardHeader>
      <CardContent>
        <DataTable
          :columns="historyColumns"
          :rows="depreciationHistory"
          :pagination="null"
        >
          <template #cell-depreciation_date="{ row }">
            {{ formatDate(row.depreciation_date) }}
          </template>
          <template #cell-amount="{ row }">
            <span class="font-mono text-red-600 dark:text-red-400">−CHF {{ formatSwiss(row.amount) }}</span>
          </template>
          <template #cell-book_value_after="{ row }">
            <span class="font-mono">CHF {{ formatSwiss(row.book_value_after) }}</span>
          </template>
          <template #cell-journal_entry_id="{ row }">
            <Link
              v-if="row.journal_entry_id"
              :href="`/accounting/journal-entries/${row.journal_entry_id}`"
              class="text-[hsl(var(--primary))] hover:underline text-sm"
            >
              #{{ row.journal_entry_id }}
            </Link>
            <span v-else class="text-[hsl(var(--muted-foreground))]">—</span>
          </template>
        </DataTable>
        <p v-if="!depreciationHistory.length" class="py-8 text-center text-sm text-[hsl(var(--muted-foreground))]">
          {{ t('no_depreciation_history') }}
        </p>
      </CardContent>
    </Card>

    <!-- Dispose Modal -->
    <Modal :open="showDisposeModal" :title="t('dispose_asset')" @close="showDisposeModal = false">
      <form class="space-y-6" @submit.prevent="submitDisposal">
        <FormInput
          id="disposal_date"
          v-model="disposeForm.disposal_date"
          type="date"
          :label="t('disposal_date')"
          :error="disposeForm.errors.disposal_date"
          required
        />
        <FormInput
          id="disposal_amount"
          v-model="disposeForm.disposal_amount"
          type="number"
          step="0.01"
          min="0"
          :label="t('disposal_amount') + ' (CHF)'"
          :error="disposeForm.errors.disposal_amount"
        />
        <div>
          <label class="mb-1.5 block text-sm font-medium">{{ t('disposal_account') }}</label>
          <Combobox
            v-model="disposeForm.disposal_account_id"
            :options="accountOptions"
            :placeholder="t('select_account')"
          />
          <p v-if="disposeForm.errors.disposal_account_id" class="mt-1 text-xs text-[hsl(var(--destructive))]">{{ disposeForm.errors.disposal_account_id }}</p>
        </div>
        <div class="flex justify-end gap-3 pt-2">
          <Button type="button" variant="outline" @click="showDisposeModal = false">{{ t('cancel') }}</Button>
          <Button type="submit" variant="destructive" :disabled="disposeForm.processing">{{ t('confirm_disposal') }}</Button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>
