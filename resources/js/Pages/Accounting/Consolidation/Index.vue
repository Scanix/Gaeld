<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import Modal from '@/Components/UI/Modal.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import { useTranslations } from '@/lib/useTranslations'
import { computed, ref } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import { Plus, BarChart3 } from 'lucide-vue-next'

const props = defineProps({
  groups: Array,
  organizationOptions: {
    type: Array,
    default: () => [],
  },
})

const { t } = useTranslations()

const columns = computed(() => [
  { key: 'name', label: t('consolidation_group_name') },
  { key: 'base_currency', label: t('consolidation_base_currency') },
  { key: 'eliminations_count', label: t('consolidation_eliminations') },
  { key: 'actions', label: '', sortable: false },
])

// Create dialog
const showForm = ref(false)
const form = useForm({
  name: '',
  member_organization_ids: [],
  base_currency: 'CHF',
})

function openCreate() {
  form.reset()
  form.clearErrors()
  showForm.value = true
}

function submitForm() {
  form.transform(data => ({
    name: data.name,
    member_organization_ids: data.member_organization_ids,
    base_currency: data.base_currency,
  })).post('/accounting/consolidation/groups', {
    preserveScroll: true,
    onSuccess: () => { showForm.value = false },
  })
}

function toggleMemberOrganization(organizationId) {
  const current = [...(form.member_organization_ids ?? [])]
  const idx = current.indexOf(organizationId)
  if (idx === -1) {
    current.push(organizationId)
  } else {
    current.splice(idx, 1)
  }
  form.member_organization_ids = current
}

function isMemberOrganizationSelected(organizationId) {
  return (form.member_organization_ids ?? []).includes(organizationId)
}
</script>

<template>
  <AppLayout :title="t('consolidation')">
    <Card>
      <CardHeader>
        <div class="flex items-center justify-between">
          <CardTitle>{{ t('consolidation_groups') }}</CardTitle>
          <Button size="sm" @click="openCreate">
            <Plus class="mr-1 h-4 w-4" /> {{ t('add') }}
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <p class="mb-4 text-sm text-[hsl(var(--muted-foreground))]">{{ t('consolidation_desc') }}</p>
        <DataTable :columns="columns" :rows="groups">
          <template #cell-actions="{ row }">
            <div class="flex items-center gap-1 justify-end">
              <Link :href="`/accounting/consolidation/${row.id}/report`">
                <Button variant="ghost" size="icon">
                  <BarChart3 class="h-4 w-4" />
                </Button>
              </Link>
            </div>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <Modal :open="showForm" :title="t('consolidation')" @close="showForm = false">
      <form class="space-y-4" @submit.prevent="submitForm">
        <FormInput
          id="name"
          v-model="form.name"
          :label="t('consolidation_group_name')"
          :error="form.errors.name"
          required
        />
        <div class="space-y-2">
          <label class="text-sm font-medium leading-none">
            {{ t('consolidation_members') }}
            <span class="text-[hsl(var(--destructive))]">*</span>
          </label>
          <div class="max-h-48 space-y-2 overflow-y-auto rounded-md border border-[hsl(var(--input))] p-3">
            <label
              v-for="organization in organizationOptions"
              :key="organization.value"
              class="flex items-center gap-2 text-sm"
            >
              <input
                type="checkbox"
                class="h-4 w-4 rounded border-[hsl(var(--input))]"
                :checked="isMemberOrganizationSelected(organization.value)"
                @change="toggleMemberOrganization(organization.value)"
              >
              <span>{{ organization.label }}</span>
            </label>
            <p v-if="organizationOptions.length === 0" class="text-sm text-[hsl(var(--muted-foreground))]">
              {{ t('no_data') }}
            </p>
          </div>
          <p v-if="form.errors.member_organization_ids" class="text-xs text-[hsl(var(--destructive))]">
            {{ form.errors.member_organization_ids }}
          </p>
          <p v-if="form.errors['member_organization_ids.0']" class="text-xs text-[hsl(var(--destructive))]">
            {{ form.errors['member_organization_ids.0'] }}
          </p>
        </div>
        <FormInput
          id="base_currency"
          v-model="form.base_currency"
          :label="t('consolidation_base_currency')"
          :error="form.errors.base_currency"
          required
        />
        <div class="flex justify-end gap-2">
          <Button variant="outline" type="button" @click="showForm = false">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="form.processing" :loading="form.processing">{{ t('save') }}</Button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>
