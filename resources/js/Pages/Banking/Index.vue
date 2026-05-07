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
import SearchableSelect from '@/Components/UI/SearchableSelect.vue'
import Badge from '@/Components/UI/Badge.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import { Plus, Landmark, Send } from 'lucide-vue-next'
import HelpText from '@/Components/HelpText.vue'
import IbanHint from '@/Components/IbanHint.vue'
import BicField from '@/Components/BicField.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { currencyOptions } from '@/lib/contactOptions'
import { buildAccountOptions } from '@/lib/accountOptions'
import { useDocsUrl } from '@/lib/useDocsUrl'
import { ref, computed } from 'vue'

const props = defineProps({
  bankAccounts: { type: Object, default: () => ({}) },
  accounts: { type: Array, default: () => [] },
})

const showModal = ref(false)
const form = useForm({
  name: '',
  iban: '',
  qr_iban: '',
  bank_name: '',
  bic: '',
  currency: 'CHF',
  account_id: '',
  is_mixed_use: false,
  is_default_for_invoicing: false,
})

function submit() {
  form.post('/banking', {
    onSuccess: () => { showModal.value = false; form.reset() },
  })
}

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

const accountOptions = computed(() =>
  buildAccountOptions(props.accounts).map(o => ({ ...o, value: String(o.value) }))
)
const { url: docsUrl } = useDocsUrl()
const chartHelpHref = docsUrl('chart-of-accounts')

const columns = computed(() => [
  { key: 'name', label: t('account_name') },
  { key: 'iban', label: t('iban'), format: v => v || '—' },
  { key: 'bank_name', label: t('bank'), format: v => v || '—' },
  { key: 'currency', label: t('currency') },
  { key: 'ledger_account', label: t('ledger_account'), format: (v) => v ? `${v.code} — ${v.display_name ?? v.name}` : '—' },
  { key: 'derived_balance', label: t('balance'), class: 'text-right', format: (v, row) => formatCurrency(v ?? 0, row.currency || 'CHF') },
])
</script>

<template>
  <AppLayout :title="t('banking')" help-page="banking">
    <HelpText :title="t('help_banking_title')" class="mb-6">
      <p>{{ t('help_banking_text') }}</p>
    </HelpText>

    <PageHeader :title="t('bank_accounts')">
      <a href="/payments/outgoing">
        <Button variant="outline" class="mr-2"><Send class="mr-2 h-4 w-4" /> {{ t('payments_outgoing') }}</Button>
      </a>
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
        <FormInput id="qr_iban" v-model="form.qr_iban" :label="t('iban_qr_iban')" :placeholder="t('qr_iban_placeholder')" :error="form.errors.qr_iban" :help="t('tooltip_qr_iban')" />
        <FormInput id="bank_name" v-model="form.bank_name" :label="t('bank_name')" :error="form.errors.bank_name" />
        <BicField id="bic" v-model="form.bic" :iban="form.iban" :error="form.errors.bic" />
        <FormSelect
          id="currency"
          v-model="form.currency"
          :label="t('currency')"
          :options="currencyOptions(t)"
          :error="form.errors.currency"
        />
        <SearchableSelect
          id="account_id"
          v-model="form.account_id"
          :label="t('ledger_account')"
          :options="accountOptions"
          group-key="group"
          :placeholder="t('select_account')"
          :error="form.errors.account_id"
          :help-href="chartHelpHref"
          :help-label="t('chart_of_accounts')"
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
        <div class="flex items-start gap-3">
          <input
            id="is_default_for_invoicing"
            v-model="form.is_default_for_invoicing"
            type="checkbox"
            class="mt-1 h-4 w-4 rounded border-[hsl(var(--input))]"
          />
          <div>
            <label for="is_default_for_invoicing" class="text-sm font-medium">{{ t('default_for_invoicing_label') }}</label>
            <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('default_for_invoicing_tooltip') }}</p>
          </div>
        </div>
        <div class="flex justify-end gap-3">
          <Button variant="outline" @click="showModal = false">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="form.processing" :loading="form.processing">{{ t('create') }}</Button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>
