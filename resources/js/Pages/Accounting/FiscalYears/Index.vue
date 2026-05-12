<script setup>
import { useForm, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import Modal from '@/Components/UI/Modal.vue'
import { useTranslations } from '@/lib/useTranslations'

const props = defineProps({
  fiscalYears: { type: Array, default: () => [] },
  maxDurationMonths: { type: Number, default: 23 },
})

const { t } = useTranslations()

const showCreate = ref(false)
const editing = ref(null) // null | fiscal year object
const deleting = ref(null)

const createForm = useForm({
  name: '',
  start_date: '',
  end_date: '',
})

const editForm = useForm({
  name: '',
  start_date: '',
  end_date: '',
})

const statusBadgeClass = (status) => {
  switch (status) {
    case 'closed':
      return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
    case 'operative':
      return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
    case 'planned':
      return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
    case 'expired':
    default:
      return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300'
  }
}

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
          {{ t('add_fiscal_year') }}
        </Button>
      </CardHeader>
      <CardContent>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead>
              <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                <th class="px-3 py-2">{{ t('name') }}</th>
                <th class="px-3 py-2">{{ t('start_date') }}</th>
                <th class="px-3 py-2">{{ t('end_date') }}</th>
                <th class="px-3 py-2">{{ t('duration') }}</th>
                <th class="px-3 py-2">{{ t('status') }}</th>
                <th class="px-3 py-2 text-right">{{ t('actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="fy in fiscalYears" :key="fy.id">
                <td class="px-3 py-2 font-medium">{{ fy.name }}</td>
                <td class="px-3 py-2">{{ fy.start_date }}</td>
                <td class="px-3 py-2">{{ fy.end_date }}</td>
                <td class="px-3 py-2">{{ fy.duration_months }} {{ t('months') }}</td>
                <td class="px-3 py-2">
                  <span
                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :class="statusBadgeClass(fy.status)"
                  >
                    {{ t('fiscal_year_status_' + fy.status) }}
                  </span>
                </td>
                <td class="px-3 py-2 text-right">
                  <Button
                    v-if="fy.status !== 'closed'"
                    size="sm"
                    variant="outline"
                    class="mr-2"
                    @click="openEdit(fy)"
                  >
                    {{ t('edit') }}
                  </Button>
                  <Button
                    v-if="fy.status === 'planned'"
                    size="sm"
                    variant="destructive"
                    @click="confirmDelete(fy)"
                  >
                    {{ t('delete') }}
                  </Button>
                </td>
              </tr>
              <tr v-if="fiscalYears.length === 0">
                <td colspan="6" class="px-3 py-6 text-center text-sm text-gray-500">
                  {{ t('no_fiscal_years') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
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
        <p class="text-xs text-gray-500">
          {{ t('fiscal_year_max_hint', { max: maxDurationMonths }) }}
        </p>
        <div class="flex justify-end gap-3 pt-2">
          <Button type="button" variant="outline" @click="showCreate = false">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="createForm.processing">{{ t('create') }}</Button>
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
          class="text-xs text-amber-700 dark:text-amber-300"
        >
          {{ t('fiscal_year_operative_dates_locked') }}
        </p>
        <div class="flex justify-end gap-3 pt-2">
          <Button type="button" variant="outline" @click="editing = null">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="editForm.processing">{{ t('save') }}</Button>
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
