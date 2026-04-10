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
import Badge from '@/Components/UI/Badge.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import { Plus, Landmark } from 'lucide-vue-next'
import HelpText from '@/Components/HelpText.vue'
import IbanHint from '@/Components/IbanHint.vue'
import { useTranslations } from '@/lib/useTranslations'
import { currencyOptions } from '@/lib/contactOptions'
import { ref, computed } from 'vue'

const props = defineProps({
  bankAccounts: { type: Object, default: () => ({}) },
  accounts: { type: Array, default: () => [] },
})

const showModal = ref(false)
const form = useForm({
  name: '',
  iban: '',
  bank_name: '',
  currency: 'CHF',
  account_id: '',
  is_mixed_use: false,
})

function submit() {
  form.post('/banking', {
    onSuccess: () => { showModal.value = false; form.reset() },
  })
}

const { t } = useTranslations()

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

    <PageHeader :title="t('bank_accounts')">
      <Button @click="showModal = true"><Plus class="mr-2 h-4 w-4" /> {{ t('add_account') }}</Button>
    </PageHeader>

    <Card v-if="(bankAccounts?.data ?? []).length">
      <CardContent class="pt-6">
        <DataTable :columns="columns" :rows="bankAccounts?.data ?? []" :pagination="bankAccounts" :row-link="row => `/banking/${row.uuid}`">
          <template #cell-name="{ row, value }">
            {{ value }}
            <Badge v-if="row.is_mixed_use" variant="outline" class="ml-2 text-xs">{{ t('mixed') }}</Badge>
          </template>
        </DataTable>
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
      <form class="space-y-6" @submit.prevent="submit">
        <FormInput id="name" v-model="form.name" :label="t('account_name')" :error="form.errors.name" required />
        <FormInput id="iban" v-model="form.iban" :label="t('iban')" :placeholder="t('iban_placeholder')" :error="form.errors.iban" />
        <IbanHint :iban="form.iban" mode="any" />
        <FormInput id="bank_name" v-model="form.bank_name" :label="t('bank_name')" :error="form.errors.bank_name" />
        <FormSelect
          id="currency"
          v-model="form.currency"
          :label="t('currency')"
          :options="currencyOptions(t)"
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
        <div class="flex items-start gap-3">
          <input
            id="is_mixed_use"
            v-model="form.is_mixed_use"
            type="checkbox"
            class="mt-1 h-4 w-4 rounded border-[hsl(var(--input))]"
          />
          <div>
            <label for="is_mixed_use" class="text-sm font-medium">{{ t('mixed_use_label') }}</label>
            <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('mixed_use_tooltip') }}</p>
          </div>
        </div>
        <div class="flex justify-end gap-3">
          <Button variant="outline" @click="showModal = false">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="form.processing">{{ t('create') }}</Button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>
