<script setup>
import { useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import Modal from '@/Components/UI/Modal.vue'
import { ref, computed } from 'vue'

const props = defineProps({
  organization: Object,
})

const page = usePage()
const isOwner = page.props.auth?.organizations?.find(
  o => o.id === props.organization.id
)?.role === 'owner'

const showEditModal = ref(false)

const form = useForm({
  name: props.organization.name,
  legal_name: props.organization.legal_name || '',
  address: props.organization.address || '',
  city: props.organization.city || '',
  postal_code: props.organization.postal_code || '',
  canton: props.organization.canton || '',
  vat_number: props.organization.vat_number || '',
  currency: props.organization.currency || 'CHF',
  locale: props.organization.locale || 'en',
  require_two_factor: props.organization.require_two_factor || false,
})

function submitUpdate() {
  form.put(`/organizations/${props.organization.id}`, {
    onSuccess: () => { showEditModal.value = false },
  })
}

import { useTranslations } from '@/lib/useTranslations'
const { t } = useTranslations()

const cantonOptions = [
  'AG', 'AI', 'AR', 'BE', 'BL', 'BS', 'FR', 'GE', 'GL', 'GR',
  'JU', 'LU', 'NE', 'NW', 'OW', 'SG', 'SH', 'SO', 'SZ', 'TG',
  'TI', 'UR', 'VD', 'VS', 'ZG', 'ZH',
].map(c => ({ value: c, label: c }))

const localeOptions = [
  { value: 'en', label: t('locale_en') },
  { value: 'fr', label: t('locale_fr') },
  { value: 'de', label: t('locale_de') },
  { value: 'it', label: t('locale_it') },
  { value: 'rm', label: t('locale_rm') },
]

const userColumns = computed(() => [
  { key: 'name', label: t('name') },
  { key: 'email', label: t('email') },
  { key: 'pivot', label: t('role'), format: (v) => v?.role ?? '—' },
])
</script>

<template>
  <AppLayout :title="organization.name">
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <Button as="a" href="/organizations" variant="outline" size="sm">← {{ t('back') }}</Button>
        <h2 class="text-xl font-semibold">{{ organization.name }}</h2>
      </div>
      <Button v-if="isOwner" @click="showEditModal = true">{{ t('edit') }}</Button>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <Card>
        <CardHeader><CardTitle>{{ t('organization_details') }}</CardTitle></CardHeader>
        <CardContent>
          <dl class="grid grid-cols-2 gap-y-3 text-sm">
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('legal_name') }}</dt>
            <dd>{{ organization.legal_name || '—' }}</dd>
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('address') }}</dt>
            <dd>{{ organization.address || '—' }}</dd>
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('city') }}</dt>
            <dd>{{ [organization.postal_code, organization.city].filter(Boolean).join(' ') || '—' }}</dd>
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('canton') }}</dt>
            <dd>{{ organization.canton || '—' }}</dd>
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('country') }}</dt>
            <dd>{{ organization.country || '—' }}</dd>
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('vat_number') }}</dt>
            <dd>{{ organization.vat_number || '—' }}</dd>
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('currency') }}</dt>
            <dd>{{ organization.currency || 'CHF' }}</dd>
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('locale') }}</dt>
            <dd class="uppercase">{{ organization.locale || '—' }}</dd>
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('require_two_factor') }}</dt>
            <dd>
              <Badge :variant="organization.require_two_factor ? 'default' : 'secondary'">
                {{ organization.require_two_factor ? t('enabled') : t('disabled') }}
              </Badge>
            </dd>
          </dl>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>{{ t('members') }}</CardTitle></CardHeader>
        <CardContent>
          <DataTable
            :columns="userColumns"
            :rows="organization.users ?? []"
            :empty-message="t('no_members')"
          >
            <template #cell-pivot="{ value }">
              <Badge :variant="value?.role === 'owner' ? 'default' : 'secondary'">
                {{ value?.role ?? 'member' }}
              </Badge>
            </template>
          </DataTable>
        </CardContent>
      </Card>
    </div>

    <Modal :show="showEditModal" @close="showEditModal = false" :title="t('edit_organization')">
      <form class="space-y-4" @submit.prevent="submitUpdate">
        <FormInput id="name" v-model="form.name" :label="t('name')" :error="form.errors.name" required />
        <FormInput id="legal_name" v-model="form.legal_name" :label="t('legal_name')" :error="form.errors.legal_name" />
        <FormInput id="address" v-model="form.address" :label="t('address')" :error="form.errors.address" />
        <div class="grid grid-cols-2 gap-4">
          <FormInput id="city" v-model="form.city" :label="t('city')" :error="form.errors.city" />
          <FormInput id="postal_code" v-model="form.postal_code" :label="t('postal_code')" :error="form.errors.postal_code" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <FormSelect id="canton" v-model="form.canton" :label="t('canton')" :options="cantonOptions" :placeholder="t('select')" :error="form.errors.canton" />
          <FormSelect id="locale" v-model="form.locale" :label="t('locale')" :options="localeOptions" :error="form.errors.locale" />
        </div>
        <FormInput id="vat_number" v-model="form.vat_number" :label="t('vat_number')" :error="form.errors.vat_number" />
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('require_two_factor') }}</p>
            <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('require_two_factor_desc') }}</p>
          </div>
          <button
            type="button"
            role="switch"
            :aria-checked="form.require_two_factor"
            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[hsl(var(--ring))] focus-visible:ring-offset-2"
            :class="form.require_two_factor ? 'bg-[hsl(var(--primary))]' : 'bg-[hsl(var(--input))]'"
            @click="form.require_two_factor = !form.require_two_factor"
          >
            <span
              class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
              :class="form.require_two_factor ? 'translate-x-5' : 'translate-x-0'"
            />
          </button>
        </div>
        <div class="flex justify-end gap-3">
          <Button variant="outline" @click="showEditModal = false">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="form.processing">{{ t('save') }}</Button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>
