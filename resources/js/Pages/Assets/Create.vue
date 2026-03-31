<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import Combobox from '@/Components/UI/Combobox.vue'
import HelpText from '@/Components/HelpText.vue'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  accounts: { type: Array, default: () => [] },
})

const accountOptions = props.accounts.map(a => ({ value: a.id, label: `${a.code} — ${a.name}` }))

const methodOptions = [
  { value: 'straight_line', label: t('straight_line') },
  { value: 'declining_balance', label: t('declining_balance') },
]

const form = useForm({
  name: '',
  description: '',
  purchase_date: new Date().toISOString().slice(0, 10),
  purchase_amount: '',
  depreciation_method: 'declining_balance',
  useful_life_years: '',
  salvage_value: '0',
  asset_account_id: null,
  depreciation_account_id: null,
  accumulated_depreciation_account_id: null,
})

function submit() {
  form.transform((data) => ({
    name: data.name,
    description: data.description || null,
    purchase_date: data.purchase_date,
    purchase_amount: data.purchase_amount,
    useful_life_years: data.useful_life_years,
    salvage_value: data.salvage_value || '0',
    depreciation_method: data.depreciation_method === 'straight_line' ? 'linear' : data.depreciation_method,
    asset_account_id: data.asset_account_id,
    depreciation_expense_account_id: data.depreciation_account_id,
    accumulated_depreciation_account_id: data.accumulated_depreciation_account_id,
  })).post('/assets')
}
</script>

<template>
  <AppLayout :title="t('new_asset')" help-page="assets">
    <Breadcrumb :items="[{ label: t('assets'), href: '/assets' }, { label: t('new_asset') }]" class="mb-4" />

    <HelpText :title="t('help_assets_title')" class="mb-6">
      <p>{{ t('help_assets_depreciation_hint') }}</p>
      <p class="mt-2 font-medium">{{ t('help_assets_rates') }}</p>
      <ul class="mt-1 list-disc list-inside space-y-0.5">
        <li>{{ t('help_assets_rate_it') }}</li>
        <li>{{ t('help_assets_rate_vehicles') }}</li>
        <li>{{ t('help_assets_rate_furniture') }}</li>
        <li>{{ t('help_assets_rate_realestate') }}</li>
      </ul>
    </HelpText>

    <Card class="max-w-2xl">
      <CardHeader>
        <CardTitle>{{ t('new_asset') }}</CardTitle>
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
            <FormInput
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
              <div>
                <label class="mb-1.5 block text-sm font-medium">{{ t('asset_account') }}</label>
                <Combobox
                  v-model="form.asset_account_id"
                  :options="accountOptions"
                  :placeholder="t('select_account')"
                />
                <p v-if="form.errors.asset_account_id" class="mt-1 text-xs text-[hsl(var(--destructive))]">{{ form.errors.asset_account_id }}</p>
              </div>
              <div>
                <label class="mb-1.5 block text-sm font-medium">{{ t('depreciation_account') }}</label>
                <Combobox
                  v-model="form.depreciation_account_id"
                  :options="accountOptions"
                  :placeholder="t('select_account')"
                />
                <p v-if="form.errors.depreciation_account_id" class="mt-1 text-xs text-[hsl(var(--destructive))]">{{ form.errors.depreciation_account_id }}</p>
              </div>
              <div>
                <label class="mb-1.5 block text-sm font-medium">{{ t('accumulated_depreciation_account') }}</label>
                <Combobox
                  v-model="form.accumulated_depreciation_account_id"
                  :options="accountOptions"
                  :placeholder="t('select_account')"
                />
                <p v-if="form.errors.accumulated_depreciation_account_id" class="mt-1 text-xs text-[hsl(var(--destructive))]">{{ form.errors.accumulated_depreciation_account_id }}</p>
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-3">
            <Button type="button" variant="outline" as="a" href="/assets">
              {{ t('cancel') }}
            </Button>
            <Button type="submit" :disabled="form.processing">
              {{ t('create_asset') }}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </AppLayout>
</template>
