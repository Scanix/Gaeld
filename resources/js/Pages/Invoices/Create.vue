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
import { Plus, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  clients: { type: Array, default: () => [] },
  vatRates: { type: Array, default: () => [] },
})

const form = useForm({
  client_id: '',
  number: '',
  issue_date: new Date().toISOString().slice(0, 10),
  due_date: '',
  currency: 'CHF',
  notes: '',
  payment_terms: '',
  lines: [{ description: '', quantity: 1, unit_price: 0, vat_rate_id: '' }],
})

function addLine() {
  form.lines.push({ description: '', quantity: 1, unit_price: 0, vat_rate_id: '' })
}

function removeLine(index) {
  if (form.lines.length > 1) {
    form.lines.splice(index, 1)
  }
}

function submit() {
  form.post('/invoices')
}

const clientOptions = props.clients.map(c => ({ value: c.id, label: c.name }))
const vatOptions = [
  { value: '', label: 'No VAT' },
  ...props.vatRates.map(v => ({ value: v.id, label: `${v.name} (${v.rate}%)` })),
]
</script>

<template>
  <AppLayout title="Create Invoice" help-page="invoices">
    <Card class="max-w-3xl">
      <CardHeader>
        <CardTitle>New Invoice</CardTitle>
      </CardHeader>
      <CardContent>
        <form class="space-y-6" @submit.prevent="submit">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormSelect
              id="client_id"
              v-model="form.client_id"
              label="Client"
              :options="clientOptions"
              placeholder="Select a client"
              :error="form.errors.client_id"
              required
            />
            <FormInput
              id="number"
              v-model="form.number"
              label="Invoice Number"
              placeholder="INV-001"
              :error="form.errors.number"
              required
            />
            <FormInput
              id="issue_date"
              v-model="form.issue_date"
              type="date"
              label="Issue Date"
              :error="form.errors.issue_date"
              required
            />
            <FormInput
              id="due_date"
              v-model="form.due_date"
              type="date"
              label="Due Date"
              :error="form.errors.due_date"
              required
            />
          </div>

          <!-- Line items -->
          <div>
            <h3 class="mb-3 text-sm font-medium">Line Items</h3>
            <div class="space-y-3">
              <div
                v-for="(line, i) in form.lines"
                :key="i"
                class="grid grid-cols-12 items-end gap-2 rounded-lg border border-[hsl(var(--border))] p-3"
              >
                <div class="col-span-4">
                  <FormInput
                    :id="`line-desc-${i}`"
                    v-model="line.description"
                    label="Description"
                    :error="form.errors[`lines.${i}.description`]"
                    required
                  />
                </div>
                <div class="col-span-2">
                  <FormInput
                    :id="`line-qty-${i}`"
                    v-model="line.quantity"
                    type="number"
                    label="Qty"
                    :error="form.errors[`lines.${i}.quantity`]"
                    required
                  />
                </div>
                <div class="col-span-2">
                  <FormInput
                    :id="`line-price-${i}`"
                    v-model="line.unit_price"
                    type="number"
                    label="Unit Price"
                    :error="form.errors[`lines.${i}.unit_price`]"
                    required
                  />
                </div>
                <div class="col-span-3">
                  <FormSelect
                    :id="`line-vat-${i}`"
                    v-model="line.vat_rate_id"
                    label="VAT"
                    :options="vatOptions"
                  />
                </div>
                <div class="col-span-1 flex justify-end pb-2">
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    :disabled="form.lines.length <= 1"
                    @click="removeLine(i)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </div>

            <Button type="button" variant="outline" size="sm" class="mt-3" @click="addLine">
              <Plus class="mr-1 h-4 w-4" />
              Add Line
            </Button>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label for="notes" class="mb-1 block text-sm font-medium">Notes</label>
              <textarea
                id="notes"
                v-model="form.notes"
                rows="3"
                class="flex w-full rounded-md border border-[hsl(var(--input))] bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]"
              />
            </div>
            <FormInput
              id="payment_terms"
              v-model="form.payment_terms"
              label="Payment Terms"
              placeholder="Net 30"
            />
          </div>

          <div class="flex justify-end gap-3">
            <Button as="a" href="/invoices" variant="outline">Cancel</Button>
            <Button type="submit" :disabled="form.processing">Create Invoice</Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </AppLayout>
</template>
