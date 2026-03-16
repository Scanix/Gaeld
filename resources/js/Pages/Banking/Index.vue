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
import { Plus, Landmark } from 'lucide-vue-next'
import { ref } from 'vue'

defineProps({ bankAccounts: { type: Array, default: () => [] } })

const showModal = ref(false)
const form = useForm({
  name: '',
  iban: '',
  bank_name: '',
  currency: 'CHF',
})

function submit() {
  form.post('/banking', {
    onSuccess: () => { showModal.value = false; form.reset() },
  })
}

const columns = [
  { key: 'name', label: 'Account Name' },
  { key: 'iban', label: 'IBAN', format: v => v || '—' },
  { key: 'bank_name', label: 'Bank', format: v => v || '—' },
  { key: 'currency', label: 'Currency' },
  { key: 'ledger_account', label: 'Ledger Account', format: (v) => v ? `${v.code} — ${v.name}` : '—' },
]
</script>

<template>
  <AppLayout title="Banking">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-semibold">Bank Accounts</h2>
      <Button @click="showModal = true"><Plus class="mr-2 h-4 w-4" /> Add Account</Button>
    </div>

    <Card v-if="bankAccounts.length">
      <CardContent class="pt-6">
        <DataTable :columns="columns" :rows="bankAccounts" :row-link="row => `/banking/${row.id}`" />
      </CardContent>
    </Card>

    <Card v-else>
      <CardContent class="flex flex-col items-center justify-center py-12">
        <Landmark class="mb-4 h-12 w-12 text-muted-foreground" />
        <p class="mb-4 text-muted-foreground">No bank accounts connected yet.</p>
        <Button @click="showModal = true">Add Your First Account</Button>
      </CardContent>
    </Card>

    <Modal :show="showModal" @close="showModal = false" title="Add Bank Account">
      <form class="space-y-4" @submit.prevent="submit">
        <FormInput id="name" v-model="form.name" label="Account Name" :error="form.errors.name" required />
        <FormInput id="iban" v-model="form.iban" label="IBAN" :error="form.errors.iban" />
        <FormInput id="bank_name" v-model="form.bank_name" label="Bank Name" :error="form.errors.bank_name" />
        <div class="flex justify-end gap-3">
          <Button variant="outline" @click="showModal = false">Cancel</Button>
          <Button type="submit" :disabled="form.processing">Create</Button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>
