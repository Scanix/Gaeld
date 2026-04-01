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
import FormSelect from '@/Components/UI/FormSelect.vue'
import { useTranslations } from '@/lib/useTranslations'
import EmptyState from '@/Components/UI/EmptyState.vue'
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { Plus, Pencil, Trash2, GitBranch } from 'lucide-vue-next'

const props = defineProps({
  costCenters: Array,
})

const { t } = useTranslations()

// Flatten hierarchical cost centers for table display
function flattenCenters(centers, depth = 0) {
  const result = []
  for (const center of centers) {
    result.push({ ...center, _depth: depth, _name: '  '.repeat(depth) + center.name })
    if (center.children?.length) {
      result.push(...flattenCenters(center.children, depth + 1))
    }
  }
  return result
}

const rows = computed(() => flattenCenters(props.costCenters))

// Build parent options from flat list (only top-level for simplicity)
const parentOptions = computed(() => [
  { value: '', label: t('none') },
  ...props.costCenters.map(c => ({ value: c.id, label: `${c.code} — ${c.name}` })),
])

const columns = computed(() => [
  { key: 'code', label: t('code') },
  { key: '_name', label: t('name') },
  { key: 'is_active', label: t('active'), format: v => v ? t('yes') : t('no') },
  { key: 'actions', label: '', sortable: false },
])

// Form dialog
const showForm = ref(false)
const editingCenter = ref(null)

const form = useForm({
  code: '',
  name: '',
  parent_id: '',
  is_active: true,
})

function openCreate() {
  editingCenter.value = null
  form.reset()
  form.clearErrors()
  showForm.value = true
}

function openEdit(center) {
  editingCenter.value = center
  form.code = center.code
  form.name = center.name
  form.parent_id = center.parent_id ?? ''
  form.is_active = center.is_active
  form.clearErrors()
  showForm.value = true
}

function submitForm() {
  if (editingCenter.value) {
    form.put(`/accounting/cost-centers/${editingCenter.value.id}`, {
      preserveScroll: true,
      onSuccess: () => { showForm.value = false },
    })
  } else {
    form.post('/accounting/cost-centers', {
      preserveScroll: true,
      onSuccess: () => { showForm.value = false },
    })
  }
}

// Delete
const showDelete = ref(false)
const deletingCenter = ref(null)
const deleteProcessing = ref(false)

function confirmDelete(center) {
  deletingCenter.value = center
  showDelete.value = true
}

function performDelete() {
  if (!deletingCenter.value) return
  deleteProcessing.value = true
  router.delete(`/accounting/cost-centers/${deletingCenter.value.id}`, {
    preserveScroll: true,
    onFinish: () => {
      deleteProcessing.value = false
      showDelete.value = false
      deletingCenter.value = null
    },
  })
}
</script>

<template>
  <AppLayout :title="t('cost_centers')">
    <Card>
      <CardHeader>
        <div class="flex items-center justify-between">
          <CardTitle>{{ t('cost_centers') }}</CardTitle>
          <Button size="sm" @click="openCreate">
            <Plus class="mr-1 h-4 w-4" /> {{ t('add') }}
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <p class="mb-4 text-sm text-[hsl(var(--muted-foreground))]">{{ t('cost_centers_desc') }}</p>
        <DataTable :columns="columns" :rows="rows">
          <template #empty>
            <EmptyState
              :icon="GitBranch"
              :title="t('empty_cost_centers_title')"
              :description="t('empty_cost_centers_desc')"
              :action-label="t('create_first')"
              @action="openCreate"
            />
          </template>
          <template #cell-is_active="{ value }">
            <Badge :variant="value ? 'success' : 'default'">{{ value ? t('yes') : t('no') }}</Badge>
          </template>
          <template #cell-actions="{ row }">
            <div class="flex items-center gap-1 justify-end">
              <Button variant="ghost" size="icon" @click="openEdit(row)">
                <Pencil class="h-4 w-4" />
              </Button>
              <Button variant="ghost" size="icon" @click="confirmDelete(row)">
                <Trash2 class="h-4 w-4 text-[hsl(var(--destructive))]" />
              </Button>
            </div>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <!-- Create / Edit dialog -->
    <Modal :open="showForm" :title="editingCenter ? t('edit') : t('add')" @close="showForm = false">
      <form class="space-y-6" @submit.prevent="submitForm">
        <FormInput id="code" v-model="form.code" :label="t('cost_center_code')" :error="form.errors.code" required />
        <FormInput id="name" v-model="form.name" :label="t('cost_center_name')" :error="form.errors.name" required />
        <FormSelect
          v-if="!editingCenter"
          id="parent_id"
          v-model="form.parent_id"
          :label="t('cost_center_parent')"
          :options="parentOptions"
          :error="form.errors.parent_id"
        />
        <label v-if="editingCenter" class="flex items-center gap-2 text-sm">
          <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-[hsl(var(--input))]" />
          {{ t('active') }}
        </label>
        <div class="flex justify-end gap-2">
          <Button variant="outline" type="button" @click="showForm = false">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="form.processing">{{ t('save') }}</Button>
        </div>
      </form>
    </Modal>

    <ConfirmDialog
      :open="showDelete"
      :title="t('confirm_delete')"
      :message="deletingCenter ? `${deletingCenter.code} — ${deletingCenter.name}` : ''"
      :processing="deleteProcessing"
      @confirm="performDelete"
      @cancel="showDelete = false"
    />
  </AppLayout>
</template>
