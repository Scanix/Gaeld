<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormTextarea from '@/Components/UI/FormTextarea.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import SearchableSelect from '@/Components/UI/SearchableSelect.vue'
import HelpText from '@/Components/HelpText.vue'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'
import { useTranslations } from '@/lib/useTranslations'
import { buildAccountOptions } from '@/lib/accountOptions'
import { useDocsUrl } from '@/lib/useDocsUrl'

const { t } = useTranslations()
const { url: docsUrl } = useDocsUrl()

const props = defineProps({
  asset: Object,
  accounts: { type: Array, default: () => [] },
})

const accountOptions = buildAccountOptions(props.accounts)
const chartHelpHref = docsUrl('chart-of-accounts')

const methodOptions = [
  { value: 'straight_line', label: t('straight_line') },
  { value: 'declining_balance', label: t('declining_balance') },
]

const form = useForm({
  name: props.asset.name ?? '',
  description: props.asset.description ?? '',
  purchase_date: props.asset.purchase_date ?? '',
  purchase_amount: props.asset.purchase_amount ?? '',
  depreciation_method: props.asset.depreciation_method ?? 'declining_balance',
  useful_life_years: props.asset.useful_life_years ?? '',
  salvage_value: props.asset.salvage_value ?? '0',
  asset_account_id: props.asset.asset_account_id ?? null,
  depreciation_account_id: props.asset.depreciation_expense_account_id ?? null,
  accumulated_depreciation_account_id: props.asset.accumulated_depreciation_account_id ?? null,
})

function submit() {
  form.transform(data => ({
    ...data,
    depreciation_method: data.depreciation_method === 'straight_line' ? 'linear' : data.depreciation_method,
    depreciation_expense_account_id: data.depreciation_account_id,
  })).put(`/assets/${props.asset.id}`)
}
</script>

<template>
  <AppLayout :title="t('edit_asset')" help-page="fixed-assets">
    <Breadcrumb :items="[{ label: t('assets'), href: '/assets' }, { label: asset.name, href: `/assets/${asset.id}` }, { label: t('edit') }]" class="mb-4" />

    <Card class="max-w-2xl">
      <CardHeader>
        <CardTitle>{{ t('edit_asset') }}</CardTitle>
      </CardHeader>
      <CardContent>
        <form class="space-y-6" @submit.prevent="submit">
          <!-- Basic info -->
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormInput
              id="name"
              v-model="form.name"
              :label="t('asset_name')"
              :error="form.errors.name"
              required
              class="sm:col-span-2"
            />
            <FormTextarea
              id="description"
              v-model="form.description"
              :label="t('description')"
              :error="form.errors.description"
              class="sm:col-span-2"
            />
            <FormInput
              id="purchase_date"
              v-model="form.purchase_date"
              type="date"
              :label="t('purchase_date')"
              :error="form.errors.purchase_date"
              required
            />
            <FormInput
              id="purchase_amount"
              v-model="form.purchase_amount"
              type="number"
              step="0.01"
              min="0"
              :label="t('purchase_amount') + ' (CHF)'"
              :error="form.errors.purchase_amount"
              required
            />
          </div>

          <!-- Depreciation -->
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <FormSelect
              id="depreciation_method"
              v-model="form.depreciation_method"
              :label="t('depreciation_method')"
              :options="methodOptions"
              :error="form.errors.depreciation_method"
              required
            />
            <FormInput
              id="useful_life_years"
              v-model="form.useful_life_years"
              type="number"
              min="1"
              max="100"
              :label="t('useful_life') + ' (' + t('years') + ')'"
              :error="form.errors.useful_life_years"
              required
            />
            <FormInput
              id="salvage_value"
              v-model="form.salvage_value"
              type="number"
              step="0.01"
              min="0"
              :label="t('salvage_value') + ' (CHF)'"
              :error="form.errors.salvage_value"
            />
          </div>

          <!-- Account selectors -->
          <div class="space-y-4">
            <p class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('accounting_accounts') }}</p>
            <div class="grid grid-cols-1 gap-4">
              <SearchableSelect
                id="asset_account_id"
                v-model="form.asset_account_id"
                :label="t('asset_account')"
                :options="accountOptions"
                group-key="group"
                :placeholder="t('select_account')"
                :error="form.errors.asset_account_id"
                :help-href="chartHelpHref"
                :help-label="t('chart_of_accounts')"
              />
              <SearchableSelect
                id="depreciation_account_id"
                v-model="form.depreciation_account_id"
                :label="t('depreciation_account')"
                :options="accountOptions"
                group-key="group"
                :placeholder="t('select_account')"
                :error="form.errors.depreciation_account_id"
                :help-href="chartHelpHref"
                :help-label="t('chart_of_accounts')"
              />
              <SearchableSelect
                id="accumulated_depreciation_account_id"
                v-model="form.accumulated_depreciation_account_id"
                :label="t('accumulated_depreciation_account')"
                :options="accountOptions"
                group-key="group"
                :placeholder="t('select_account')"
                :error="form.errors.accumulated_depreciation_account_id"
                :help-href="chartHelpHref"
                :help-label="t('chart_of_accounts')"
              />
            </div>
          </div>

          <div class="flex flex-wrap justify-end gap-3">
            <Button type="button" variant="outline" as="a" :href="`/assets/${asset.id}`">
              {{ t('cancel') }}
            </Button>
            <Button type="submit" :disabled="form.processing">
              {{ t('save_changes') }}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </AppLayout>
</template>
