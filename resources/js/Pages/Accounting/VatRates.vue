<script setup>
import { ref, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Badge from '@/Components/UI/Badge.vue'
import Button from '@/Components/UI/Button.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import Modal from '@/Components/UI/Modal.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import { useTranslations } from '@/lib/useTranslations'
import EmptyState from '@/Components/UI/EmptyState.vue'
import { Plus, Pencil, Trash2, Percent } from 'lucide-vue-next'

const props = defineProps({
  vatRates: { type: Object, default: () => ({}) },
})

const { t } = useTranslations()

const vatRateNameByCode = {
  NORMAL: 'vat_rate_name_standard',
  REDUCED: 'vat_rate_name_reduced',
  ACCOMMODATION: 'vat_rate_name_accommodation',
  EXEMPT: 'vat_rate_name_exempt',
}

function localizedVatRateName(rate) {
  const key = vatRateNameByCode[rate.code]
  return key ? t(key) : rate.name
}

const columns = [
  { key: 'code', label: t('code') },
  { key: 'name', label: t('name') },
  { key: 'rate', label: t('rate') },
  { key: 'is_default', label: t('default') },
  { key: 'is_active', label: t('active') },
  { key: 'actions', label: '', sortable: false },
]

// Form modal state
const showForm = ref(false)
const editingRate = ref(null)

const form = useForm({
  name: '',
  rate: '',
  code: '',
  is_default: false,
  is_active: true,
})

function openCreate() {
  editingRate.value = null
  form.reset()
  form.is_active = true
  showForm.value = true
}

function openEdit(rate) {
  editingRate.value = rate
  form.name = rate.name
  form.rate = rate.rate
  form.code = rate.code ?? ''
  form.is_default = rate.is_default
  form.is_active = rate.is_active
  showForm.value = true
}

function submitForm() {
  if (editingRate.value) {
    form.put(`/accounting/vat-rates/${editingRate.value.uuid}`, {
      onSuccess: () => { showForm.value = false },
    })
  } else {
    form.post('/accounting/vat-rates', {
      onSuccess: () => { showForm.value = false },
    })
  }
}

// Delete
const deletingRate = ref(null)

function confirmDelete(rate) {
  deletingRate.value = rate
}

function doDelete() {
  router.delete(`/accounting/vat-rates/${deletingRate.value.uuid}`, {
    onFinish: () => { deletingRate.value = null },
  })
}
</script>

<template>
  <AppLayout :title="t('vat_rates')" help-page="vat-rates">
    <Card>
      <CardHeader class="flex flex-row items-center justify-between">
        <CardTitle>{{ t('vat_rates') }}</CardTitle>
        <Button size="sm" @click="openCreate">
          <Plus class="mr-1 h-4 w-4" />
          {{ t('new_vat_rate') }}
        </Button>
      </CardHeader>
      <CardContent>
        <DataTable :columns="columns" :rows="vatRates?.data ?? []" :pagination="vatRates">
          <template #empty>
            <EmptyState
              :icon="Percent"
              :title="t('empty_vat_rates_title')"
              :description="t('empty_vat_rates_desc')"
              :action-label="t('create_first')"
              @action="openCreate"
            />
          </template>
          <template #cell-rate="{ row }">
            {{ row.rate }}%
          </template>
          <template #cell-name="{ row }">
            {{ localizedVatRateName(row) }}
          </template>
          <template #cell-is_default="{ row }">
            <Badge v-if="row.is_default" variant="info">{{ t('yes') }}</Badge>
            <span v-else class="text-[hsl(var(--muted-foreground))]">—</span>
          </template>
          <template #cell-is_active="{ row }">
            <Badge :variant="row.is_active ? 'success' : 'secondary'">
              {{ row.is_active ? t('active') : t('inactive') }}
            </Badge>
          </template>
          <template #cell-actions="{ row }">
            <div class="flex justify-end gap-2">
              <Button variant="ghost" size="icon" :aria-label="t('edit')" :title="t('edit')" @click="openEdit(row)">
                <Pencil class="h-4 w-4" />
              </Button>
              <Button variant="ghost" size="icon" :aria-label="t('delete')" :title="t('delete')" @click="confirmDelete(row)">
                <Trash2 class="h-4 w-4 text-[hsl(var(--destructive))]" />
              </Button>
            </div>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <!-- Create/Edit Modal -->
    <Modal
      :open="showForm"
      :title="editingRate ? t('edit_vat_rate') : t('new_vat_rate')"
      @close="showForm = false"
    >
      <form class="space-y-6" @submit.prevent="submitForm">
        <FormInput
          id="name"
          v-model="form.name"
          :label="t('name')"
          :error="form.errors.name"
          required
        />
        <FormInput
          id="rate"
          v-model="form.rate"
          type="number"
          step="0.01"
          min="0"
          max="100"
          :label="t('rate_percent')"
          :error="form.errors.rate"
          required
        />
        <FormInput
          id="code"
          v-model="form.code"
          :label="t('code')"
          :error="form.errors.code"
        />
        <label class="flex items-center gap-2 text-sm">
          <input v-model="form.is_default" type="checkbox" class="rounded border border-[hsl(var(--input))]" />
          {{ t('set_as_default') }}
        </label>
        <label v-if="editingRate" class="flex items-center gap-2 text-sm">
          <input v-model="form.is_active" type="checkbox" class="rounded border border-[hsl(var(--input))]" />
          {{ t('active') }}
        </label>
        <div class="flex justify-end gap-3 pt-2">
          <Button type="button" variant="outline" @click="showForm = false">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="form.processing" :loading="form.processing">
            {{ editingRate ? t('save_changes') : t('create') }}
          </Button>
        </div>
      </form>
    </Modal>

    <!-- Delete confirm -->
    <ConfirmDialog
      :open="!!deletingRate"
      :title="t('delete_vat_rate')"
      :message="t('confirm_delete_vat_rate')"
      variant="destructive"
      @confirm="doDelete"
      @cancel="deletingRate = null"
    />
  </AppLayout>
</template>
