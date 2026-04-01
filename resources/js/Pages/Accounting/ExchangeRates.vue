<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Badge from '@/Components/UI/Badge.vue'
import Button from '@/Components/UI/Button.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import Modal from '@/Components/UI/Modal.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import { useTranslations } from '@/lib/useTranslations'
import EmptyState from '@/Components/UI/EmptyState.vue'
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { Plus, Trash2, Download, ArrowLeftRight } from 'lucide-vue-next'

const props = defineProps({
  rates: Object, // Laravel paginator
})

const { t } = useTranslations()

const sourceVariant = { manual: 'default', ecb: 'info', snb: 'success' }

const columns = computed(() => [
  { key: 'currency_from', label: t('exchange_rate_from') },
  { key: 'currency_to', label: t('exchange_rate_to') },
  { key: 'rate', label: t('exchange_rate_rate'), class: 'text-right' },
  { key: 'date', label: t('exchange_rate_date') },
  { key: 'source', label: t('source') },
  { key: 'actions', label: '', sortable: false },
])

// Create dialog
const showForm = ref(false)
const form = useForm({
  currency_from: '',
  currency_to: '',
  rate: '',
  date: new Date().toISOString().slice(0, 10),
})

function openCreate() {
  form.reset()
  form.date = new Date().toISOString().slice(0, 10)
  form.clearErrors()
  showForm.value = true
}

function submitForm() {
  form.post('/accounting/exchange-rates', {
    preserveScroll: true,
    onSuccess: () => { showForm.value = false },
  })
}

// Delete
const showDelete = ref(false)
const deletingRate = ref(null)
const deleteProcessing = ref(false)

function confirmDelete(rate) {
  deletingRate.value = rate
  showDelete.value = true
}

function performDelete() {
  if (!deletingRate.value) return
  deleteProcessing.value = true
  router.delete(`/accounting/exchange-rates/${deletingRate.value.id}`, {
    preserveScroll: true,
    onFinish: () => {
      deleteProcessing.value = false
      showDelete.value = false
      deletingRate.value = null
    },
  })
}

// Fetch from ECB
const fetchingEcb = ref(false)
function fetchEcb() {
  fetchingEcb.value = true
  router.post('/accounting/exchange-rates/fetch-ecb', {}, {
    preserveScroll: true,
    onFinish: () => { fetchingEcb.value = false },
  })
}
</script>

<template>
  <AppLayout :title="t('exchange_rates')">
    <Card>
      <CardHeader>
        <div class="flex items-center justify-between">
          <CardTitle>{{ t('exchange_rates') }}</CardTitle>
          <div class="flex gap-2">
            <Button variant="outline" size="sm" :disabled="fetchingEcb" @click="fetchEcb">
              <Download class="mr-1 h-4 w-4" /> {{ t('fetch_ecb_rates') }}
            </Button>
            <Button size="sm" @click="openCreate">
              <Plus class="mr-1 h-4 w-4" /> {{ t('add') }}
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <p class="mb-4 text-sm text-[hsl(var(--muted-foreground))]">{{ t('exchange_rates_desc') }}</p>
        <DataTable :columns="columns" :rows="rates.data" :pagination="rates">
          <template #empty>
            <EmptyState
              :icon="ArrowLeftRight"
              :title="t('empty_exchange_rates_title')"
              :description="t('empty_exchange_rates_desc')"
              :action-label="t('create_first')"
              @action="openCreate"
            />
          </template>
          <template #cell-source="{ value }">
            <Badge :variant="sourceVariant[value] || 'default'">{{ value }}</Badge>
          </template>
          <template #cell-actions="{ row }">
            <div class="flex items-center gap-1 justify-end">
              <Button
                v-if="row.source === 'manual'"
                variant="ghost"
                size="icon"
                @click="confirmDelete(row)"
              >
                <Trash2 class="h-4 w-4 text-[hsl(var(--destructive))]" />
              </Button>
            </div>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <Modal :open="showForm" :title="t('exchange_rate')" @close="showForm = false">
      <form class="space-y-4" @submit.prevent="submitForm">
        <div class="grid grid-cols-2 gap-4">
          <FormInput
            id="currency_from"
            v-model="form.currency_from"
            :label="t('exchange_rate_from')"
            :error="form.errors.currency_from"
            placeholder="EUR"
            maxlength="3"
            required
          />
          <FormInput
            id="currency_to"
            v-model="form.currency_to"
            :label="t('exchange_rate_to')"
            :error="form.errors.currency_to"
            placeholder="CHF"
            maxlength="3"
            required
          />
        </div>
        <FormInput
          id="rate"
          v-model="form.rate"
          type="number"
          step="0.00001"
          :label="t('exchange_rate_rate')"
          :error="form.errors.rate"
          required
        />
        <FormInput
          id="date"
          v-model="form.date"
          type="date"
          :label="t('exchange_rate_date')"
          :error="form.errors.date"
          required
        />
        <div class="flex justify-end gap-2">
          <Button variant="outline" type="button" @click="showForm = false">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="form.processing">{{ t('save') }}</Button>
        </div>
      </form>
    </Modal>

    <ConfirmDialog
      :open="showDelete"
      :title="t('confirm_delete')"
      :message="deletingRate ? `${deletingRate.currency_from}/${deletingRate.currency_to} — ${deletingRate.date}` : ''"
      :processing="deleteProcessing"
      @confirm="performDelete"
      @cancel="showDelete = false"
    />
  </AppLayout>
</template>
