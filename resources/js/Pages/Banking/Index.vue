<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import Modal from '@/Components/UI/Modal.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import { Plus, Landmark } from 'lucide-vue-next'
import HelpText from '@/Components/HelpText.vue'
import { useTranslations } from '@/lib/useTranslations'
import { ref, computed } from 'vue'

const props = defineProps({
  bankAccounts: { type: Array, default: () => [] },
  accounts: { type: Array, default: () => [] },
})

const showModal = ref(false)
const form = useForm({
  name: '',
  iban: '',
  bank_name: '',
  currency: 'CHF',
  account_id: '',
})

function submit() {
  form.post('/banking', {
    onSuccess: () => { showModal.value = false; form.reset() },
  })
}

const { t } = useTranslations()

const currencyOptions = [
  { value: 'CHF', label: 'CHF' },
  { value: 'EUR', label: 'EUR' },
  { value: 'USD', label: 'USD' },
  { value: 'GBP', label: 'GBP' },
]

const accountOptions = computed(() =>
  props.accounts.map(a => ({ value: a.id.toString(), label: `${a.code} — ${a.name}` }))
)

const columns = computed(() => [
  { key: 'name', label: t('account_name') },
  { key: 'iban', label: t('iban'), format: v => v || '—' },
  { key: 'bank_name', label: t('bank'), format: v => v || '—' },
  { key: 'currency', label: t('currency') },
  { key: 'ledger_account', label: t('ledger_account'), format: (v) => v ? `${v.code} — ${v.name}` : '—' },
])
</script>

<template>
  <AppLayout :title="t('banking')" help-page="banking">
    <HelpText :title="t('help_banking_title')" class="mb-6">
      <p>{{ t('help_banking_text') }}</p>
    </HelpText>

    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-semibold">{{ t('bank_accounts') }}</h2>
      <Button @click="showModal = true"><Plus class="mr-2 h-4 w-4" /> {{ t('add_account') }}</Button>
    </div>

    <Card v-if="bankAccounts.length">
      <CardContent class="pt-6">
        <DataTable :columns="columns" :rows="bankAccounts" :row-link="row => `/banking/${row.id}`" />
      </CardContent>
    </Card>

    <Card v-else>
      <CardContent class="flex flex-col items-center justify-center py-12">
        <Landmark class="mb-4 h-12 w-12 text-muted-foreground" />
        <p class="mb-4 text-muted-foreground">{{ t('no_bank_accounts') }}</p>
        <Button @click="showModal = true">{{ t('add_first_account') }}</Button>
      </CardContent>
    </Card>

    <Modal :show="showModal" @close="showModal = false" :title="t('add_bank_account')">
      <form class="space-y-4" @submit.prevent="submit">
        <FormInput id="name" v-model="form.name" :label="t('account_name')" :error="form.errors.name" required />
        <FormInput id="iban" v-model="form.iban" :label="t('iban')" :error="form.errors.iban" />
        <FormInput id="bank_name" v-model="form.bank_name" :label="t('bank_name')" :error="form.errors.bank_name" />
        <FormSelect
          id="currency"
          v-model="form.currency"
          :label="t('currency')"
          :options="currencyOptions"
          :error="form.errors.currency"
        />
        <FormSelect
          id="account_id"
          v-model="form.account_id"
          :label="t('ledger_account')"
          :options="accountOptions"
          :placeholder="t('select_account')"
          :error="form.errors.account_id"
        />
        <div class="flex justify-end gap-3">
          <Button variant="outline" @click="showModal = false">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="form.processing">{{ t('create') }}</Button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>
