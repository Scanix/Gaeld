<script setup>
import { ref, watch, computed } from 'vue'
import Modal from '@/Components/UI/Modal.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import SearchableSelect from '@/Components/UI/SearchableSelect.vue'
import { useTranslations } from '@/lib/useTranslations'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  open: Boolean,
  account: { type: Object, default: null },
  accountTypes: Array,
  accounts: Array,
})

const emit = defineEmits(['close'])

const { t } = useTranslations()

const isEditing = computed(() => !!props.account)
const hasTransactions = computed(() => props.account?.has_transactions ?? false)

const form = ref({
  code: '',
  name: '',
  type: '',
  parent_id: '',
  is_active: true,
  description: '',
})

const errors = ref({})
const processing = ref(false)

const parentOptions = computed(() => {
  const opts = [{ value: '', label: t('no_parent'), group: '' }]
  if (!props.accounts) return opts
  const groupLabel = (type) => type ? t('account_type_' + type) : ''
  return opts.concat(
    props.accounts
      .filter(a => !props.account || a.id !== props.account.id)
      .slice()
      .sort((a, b) => String(a.code).localeCompare(String(b.code)))
      .map(a => ({
        value: a.id,
        label: `${a.code} — ${a.display_name ?? a.name}`,
        group: groupLabel(a.type),
      })),
  )
})

watch(() => props.open, (val) => {
  if (!val) return
  errors.value = {}
  if (props.account) {
    form.value = {
      code: props.account.code,
      name: props.account.name,
      type: props.account.type,
      parent_id: props.account.parent_id ?? '',
      is_active: props.account.is_active,
      description: props.account.description ?? '',
    }
  } else {
    form.value = { code: '', name: '', type: '', parent_id: '', is_active: true, description: '' }
  }
})

function submit() {
  processing.value = true
  errors.value = {}

  const method = isEditing.value ? 'put' : 'post'
  const url = isEditing.value
    ? `/accounting/accounts/${props.account.uuid}`
    : '/accounting/accounts'

  const data = { ...form.value }
  if (data.parent_id === '') data.parent_id = null

  router[method](url, data, {
    preserveScroll: true,
    onSuccess: () => {
      emit('close')
    },
    onError: (errs) => {
      errors.value = errs
    },
    onFinish: () => {
      processing.value = false
    },
  })
}
</script>

<template>
  <Modal :open="open" :title="isEditing ? t('edit_account') : t('add_account')" @close="$emit('close')">
    <form class="space-y-4" @submit.prevent="submit">
      <p v-if="hasTransactions" class="text-xs text-amber-600 dark:text-amber-400">
        {{ t('account_has_transactions') }}
      </p>

      <FormInput
        id="account-code"
        v-model="form.code"
        :label="t('account_code')"
        :error="errors.code"
        :disabled="hasTransactions"
        required
      />

      <FormInput
        id="account-name"
        v-model="form.name"
        :label="t('name')"
        :error="errors.name"
        required
      />

      <FormSelect
        id="account-type"
        v-model="form.type"
        :label="t('account_type')"
        :options="accountTypes"
        :error="errors.type"
        :disabled="hasTransactions"
        required
      />

      <SearchableSelect
        id="account-parent"
        v-model="form.parent_id"
        :label="t('parent_account')"
        :options="parentOptions"
        group-key="group"
        :error="errors.parent_id"
      />

      <FormInput
        id="account-description"
        v-model="form.description"
        :label="t('description')"
        :error="errors.description"
      />

      <div class="flex items-center gap-2">
        <input
          id="account-active"
          v-model="form.is_active"
          type="checkbox"
          class="rounded border-[hsl(var(--border))]"
        />
        <label for="account-active" class="text-sm">{{ t('active') }}</label>
      </div>

      <div class="flex justify-end gap-3 pt-2">
        <Button variant="outline" type="button" @click="$emit('close')">{{ t('cancel') }}</Button>
        <Button type="submit" :disabled="processing">
          {{ isEditing ? t('save') : t('create') }}
        </Button>
      </div>
    </form>
  </Modal>
</template>
