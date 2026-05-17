<script setup>
import { useForm, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import Badge from '@/Components/UI/Badge.vue'
import Button from '@/Components/UI/Button.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import EmptyState from '@/Components/UI/EmptyState.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import Modal from '@/Components/UI/Modal.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { Calendar, Pencil, Plus, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  fiscalYears: { type: Array, default: () => [] },
  maxDurationMonths: { type: Number, default: 23 },
})

const { t } = useTranslations()
const { formatDate } = useFormatters()

const showCreate = ref(false)
const editing = ref(null)
const deleting = ref(null)

const createForm = useForm({ name: '', start_date: '', end_date: '' })
const editForm = useForm({ name: '', start_date: '', end_date: '' })

const statusVariant = (status) => ({ closed: 'secondary', operative: 'success', planned: 'info', expired: 'warning' }[status] ?? 'secondary')

const columns = computed(() => [
  { key: 'name', label: t('name') },
  { key: 'start_date', label: t('start_date'), format: v => formatDate(v) },
  { key: 'end_date', label: t('end_date'), format: v => formatDate(v) },
  { key: 'duration_months', label: t('duration'), format: v => `${v} ${t('months')}` },
  { key: 'status', label: t('status') },
  { key: 'actions', label: '', sortable: false },
])

const editingIsOperative = computed(() => editing.value?.status === 'operative')
const editingIsClosed = computed(() => editing.value?.status === 'closed')

function openCreate() {
  createForm.reset()
  showCreate.value = true
}

function submitCreate() {
  createForm.post('/accounting/fiscal-years', {
    preserveScroll: true,
    onSuccess: () => {
      showCreate.value = false
      createForm.reset()
    },
  })
}

function openEdit(fy) {
  editing.value = fy
  editForm.name = fy.name
  editForm.start_date = fy.start_date
  editForm.end_date = fy.end_date
}

function submitEdit() {
  if (!editing.value) return
  editForm.put(`/accounting/fiscal-years/${editing.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      editing.value = null
    },
  })
}

function confirmDelete(fy) {
  deleting.value = fy
}

function doDelete() {
  if (!deleting.value) return
  router.delete(`/accounting/fiscal-years/${deleting.value.id}`, {
    preserveScroll: true,
    onFinish: () => {
      deleting.value = null
    },
  })
}
</script>

<template>
  <AppLayout :title="t('fiscal_years')">
    <Card>
      <CardHeader class="flex flex-row items-start justify-between gap-4">
        <div>
          <CardTitle>{{ t('fiscal_years') }}</CardTitle>
          <CardDescription>
            {{ t('fiscal_years_description', { max: maxDurationMonths }) }}
          </CardDescription>
        </div>
        <Button @click="openCreate">
          <Plus class="mr-1 h-4 w-4" />
          {{ t('add_fiscal_year') }}
        </Button>
      </CardHeader>
      <CardContent>
        <DataTable :columns="columns" :rows="fiscalYears">
          <template #empty>
            <EmptyState
              :icon="Calendar"
              :title="t('no_fiscal_years')"
              :action-label="t('add_fiscal_year')"
              @action="openCreate"
            />
          </template>
          <template #cell-status="{ row }">
            <Badge :variant="statusVariant(row.status)">
              {{ t('fiscal_year_status_' + row.status) }}
            </Badge>
          </template>
          <template #cell-actions="{ row }">
            <div class="flex justify-end gap-2">
              <Button
                v-if="row.status !== 'closed'"
                variant="ghost"
                size="icon"
                :aria-label="t('edit')"
                :title="t('edit')"
                @click="openEdit(row)"
              >
                <Pencil class="h-4 w-4" />
              </Button>
              <Button
                v-if="row.status === 'planned'"
                variant="ghost"
                size="icon"
                :aria-label="t('delete')"
                :title="t('delete')"
                @click="confirmDelete(row)"
              >
                <Trash2 class="h-4 w-4 text-[hsl(var(--destructive))]" />
              </Button>
            </div>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <!-- Create dialog -->
    <Modal :open="showCreate" :title="t('add_fiscal_year')" @close="showCreate = false">
      <form class="space-y-4" @submit.prevent="submitCreate">
        <FormInput
          id="name"
          v-model="createForm.name"
          :label="t('name')"
          :error="createForm.errors.name"
          required
        />
        <FormInput
          id="start_date"
          v-model="createForm.start_date"
          type="date"
          :label="t('start_date')"
          :error="createForm.errors.start_date"
          required
        />
        <FormInput
          id="end_date"
          v-model="createForm.end_date"
          type="date"
          :label="t('end_date')"
          :error="createForm.errors.end_date"
          required
        />
        <p class="text-xs text-[hsl(var(--muted-foreground))]">
          {{ t('fiscal_year_max_hint', { max: maxDurationMonths }) }}
        </p>
        <div class="flex justify-end gap-3 pt-2">
          <Button type="button" variant="outline" @click="showCreate = false">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="createForm.processing" :loading="createForm.processing">{{ t('create') }}</Button>
        </div>
      </form>
    </Modal>

    <!-- Edit dialog -->
    <Modal :open="!!editing" :title="t('edit_fiscal_year')" @close="editing = null">
      <form v-if="editing" class="space-y-4" @submit.prevent="submitEdit">
        <FormInput
          id="edit_name"
          v-model="editForm.name"
          :label="t('name')"
          :error="editForm.errors.name"
          required
        />
        <FormInput
          id="edit_start_date"
          v-model="editForm.start_date"
          type="date"
          :label="t('start_date')"
          :error="editForm.errors.start_date"
          :disabled="editingIsOperative || editingIsClosed"
          required
        />
        <FormInput
          id="edit_end_date"
          v-model="editForm.end_date"
          type="date"
          :label="t('end_date')"
          :error="editForm.errors.end_date"
          :disabled="editingIsOperative || editingIsClosed"
          required
        />
        <p
          v-if="editingIsOperative"
          class="text-xs text-[hsl(var(--warning))]"
        >
          {{ t('fiscal_year_operative_dates_locked') }}
        </p>
        <div class="flex justify-end gap-3 pt-2">
          <Button type="button" variant="outline" @click="editing = null">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="editForm.processing" :loading="editForm.processing">{{ t('save') }}</Button>
        </div>
      </form>
    </Modal>

    <!-- Delete confirmation -->
    <ConfirmDialog
      :open="!!deleting"
      :title="t('delete_fiscal_year')"
      :message="deleting ? t('delete_fiscal_year_confirm', { name: deleting.name }) : ''"
      :confirm-label="t('delete')"
      @confirm="doDelete"
      @cancel="deleting = null"
    />
  </AppLayout>
</template>
