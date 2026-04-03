<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import Modal from '@/Components/UI/Modal.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { useFormatters } from '@/lib/useFormatters'
import { useTranslations } from '@/lib/useTranslations'
import { ref, reactive } from 'vue'
import { Pencil, Plus, Trash2, Star } from 'lucide-vue-next'
import axios from 'axios'
import { useToast } from '@/lib/useToast'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'
import EmptyState from '@/Components/UI/EmptyState.vue'

const { t } = useTranslations()
const { formatCurrency, formatDate } = useFormatters()
const { toast } = useToast()

const props = defineProps({
  customer: { type: Object, required: true },
  invoices: { type: Array, default: () => [] },
})

const statusVariant = {
  draft: 'secondary',
  sent: 'default',
  paid: 'success',
  overdue: 'destructive',
  cancelled: 'outline',
}

// Contact persons state
const contactPersons = ref(props.customer.contact_persons ?? [])
const showContactModal = ref(false)
const showDeleteContactDialog = ref(false)
const contactToDelete = ref(null)
const editingContact = ref(null)
const contactErrors = ref({})
const contactProcessing = ref(false)

const contactForm = reactive({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  position: '',
  is_primary: false,
  notes: '',
})

function resetContactForm() {
  Object.assign(contactForm, {
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    position: '',
    is_primary: false,
    notes: '',
  })
  contactErrors.value = {}
  editingContact.value = null
}

function openAddContact() {
  resetContactForm()
  showContactModal.value = true
}

function openEditContact(contact) {
  editingContact.value = contact
  Object.assign(contactForm, {
    first_name: contact.first_name,
    last_name: contact.last_name,
    email: contact.email ?? '',
    phone: contact.phone ?? '',
    position: contact.position ?? '',
    is_primary: contact.is_primary ?? false,
    notes: contact.notes ?? '',
  })
  contactErrors.value = {}
  showContactModal.value = true
}

async function submitContact() {
  contactProcessing.value = true
  contactErrors.value = {}
  try {
    if (editingContact.value) {
      const { data } = await axios.put(
        `/customers/${props.customer.id}/contact-persons/${editingContact.value.id}`,
        contactForm,
      )
      const idx = contactPersons.value.findIndex(c => c.id === editingContact.value.id)
      if (idx !== -1) contactPersons.value[idx] = data.contact_person
      if (data.contact_person.is_primary) {
        contactPersons.value.forEach((c, i) => {
          if (i !== idx) c.is_primary = false
        })
      }
    } else {
      const { data } = await axios.post(
        `/customers/${props.customer.id}/contact-persons`,
        contactForm,
      )
      if (data.contact_person.is_primary) {
        contactPersons.value.forEach(c => { c.is_primary = false })
      }
      contactPersons.value.push(data.contact_person)
    }
    showContactModal.value = false
    resetContactForm()
    toast(editingContact.value ? t('contact_person_updated') : t('contact_person_created'), 'success')
  } catch (err) {
    if (err.response?.status === 422) {
      contactErrors.value = err.response.data.errors ?? {}
    }
  } finally {
    contactProcessing.value = false
  }
}

function confirmDeleteContact(contact) {
  contactToDelete.value = contact
  showDeleteContactDialog.value = true
}

async function executeDeleteContact() {
  contactProcessing.value = true
  try {
    await axios.delete(
      `/customers/${props.customer.id}/contact-persons/${contactToDelete.value.id}`,
    )
    contactPersons.value = contactPersons.value.filter(c => c.id !== contactToDelete.value.id)
    showDeleteContactDialog.value = false
    contactToDelete.value = null
    toast(t('contact_person_deleted'), 'success')
  } finally {
    contactProcessing.value = false
  }
}
</script>

<template>
  <AppLayout :title="customer.name" help-page="customers">
    <Breadcrumb :items="[{ label: t('customers'), href: '/customers' }, { label: customer.name }]" class="mb-4" />

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <h2 class="text-lg font-semibold">{{ customer.name }}</h2>
      <Button as="a" :href="`/customers/${customer.id}/edit`" variant="outline" size="sm">
        <Pencil class="mr-2 h-4 w-4" />
        {{ t('edit') }}
      </Button>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <!-- Contact details -->
      <Card class="lg:col-span-1">
        <CardHeader>
          <CardTitle>{{ customer.name }}</CardTitle>
        </CardHeader>
        <CardContent class="space-y-3 text-sm">
          <div v-if="customer.email">
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('email') }}:</span>
            <a :href="`mailto:${customer.email}`" class="ml-2 underline">{{ customer.email }}</a>
          </div>
          <div v-if="customer.phone">
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('phone') }}:</span>
            <span class="ml-2">{{ customer.phone }}</span>
          </div>
          <div v-if="customer.address || customer.city">
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('address') }}:</span>
            <span class="ml-2">
              {{ [customer.address, customer.postal_code, customer.city, customer.country].filter(Boolean).join(', ') }}
            </span>
          </div>
          <div v-if="customer.vat_number">
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('vat_number') }}:</span>
            <span class="ml-2 font-mono">{{ customer.vat_number }}</span>
          </div>
          <div>
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('currency') }}:</span>
            <span class="ml-2">{{ customer.currency }}</span>
          </div>
          <div v-if="customer.payment_terms">
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('payment_terms') }}:</span>
            <span class="ml-2">{{ t('days_count', { count: customer.payment_terms }) }}</span>
          </div>
          <div v-if="customer.internal_notes" class="border-t pt-3">
            <p class="text-[hsl(var(--muted-foreground))] mb-1">{{ t('internal_notes') }}</p>
            <p class="italic">{{ customer.internal_notes }}</p>
          </div>
        </CardContent>
      </Card>

      <!-- Invoices -->
      <Card class="lg:col-span-2">
        <CardHeader>
          <CardTitle>{{ t('invoices') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <EmptyState v-if="!invoices.length" :title="t('no_invoices_yet')" />
          <table v-else class="w-full text-sm">
            <thead>
              <tr class="border-b text-[hsl(var(--muted-foreground))]">
                <th class="pb-2 text-left font-medium">{{ t('number') }}</th>
                <th class="pb-2 text-left font-medium">{{ t('date') }}</th>
                <th class="pb-2 text-right font-medium">{{ t('total') }}</th>
                <th class="pb-2 text-left font-medium">{{ t('status') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="invoice in invoices"
                :key="invoice.id"
                class="border-b last:border-0"
              >
                <td class="py-2">
                  <a :href="`/invoices/${invoice.id}`" class="font-medium underline">
                    {{ invoice.number }}
                  </a>
                </td>
                <td class="py-2">{{ formatDate(invoice.issue_date) }}</td>
                <td class="py-2 text-right">{{ formatCurrency(invoice.total, invoice.currency) }}</td>
                <td class="py-2">
                  <Badge :variant="statusVariant[invoice.status] ?? 'secondary'">
                    {{ t('invoice_status_' + invoice.status) }}
                  </Badge>
                </td>
              </tr>
            </tbody>
          </table>
        </CardContent>
      </Card>
    </div>

    <!-- Contact Persons -->
    <Card class="mt-6">
      <CardHeader>
        <div class="flex items-center justify-between">
          <CardTitle>{{ t('contact_persons') }}</CardTitle>
          <Button size="sm" @click="openAddContact">
            <Plus class="mr-1 h-4 w-4" />
            {{ t('add_contact_person') }}
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <EmptyState v-if="!contactPersons.length" :title="t('no_contact_persons')" />
        <div v-else class="space-y-3">
          <div
            v-for="contact in contactPersons"
            :key="contact.id"
            class="flex items-start justify-between rounded-lg border p-3"
          >
            <div>
              <div class="flex items-center gap-2">
                <span class="font-medium">{{ contact.first_name }} {{ contact.last_name }}</span>
                <Star v-if="contact.is_primary" class="h-4 w-4 fill-yellow-400 text-yellow-400" />
                <span v-if="contact.position" class="text-sm text-[hsl(var(--muted-foreground))]">
                  — {{ contact.position }}
                </span>
              </div>
              <div class="mt-1 flex gap-4 text-sm text-[hsl(var(--muted-foreground))]">
                <a v-if="contact.email" :href="`mailto:${contact.email}`" class="underline">{{ contact.email }}</a>
                <span v-if="contact.phone">{{ contact.phone }}</span>
              </div>
              <p v-if="contact.notes" class="mt-1 text-sm italic text-[hsl(var(--muted-foreground))]">{{ contact.notes }}</p>
            </div>
            <div class="flex gap-1">
              <Button variant="ghost" size="sm" @click="openEditContact(contact)">
                <Pencil class="h-4 w-4" />
              </Button>
              <Button variant="ghost" size="sm" @click="confirmDeleteContact(contact)">
                <Trash2 class="h-4 w-4 text-[hsl(var(--destructive))]" />
              </Button>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Contact Person Modal -->
    <Modal :open="showContactModal" :title="editingContact ? t('edit_contact_person') : t('add_contact_person')" @close="showContactModal = false">
      <form class="space-y-6" @submit.prevent="submitContact">
        <div class="grid grid-cols-2 gap-4">
          <FormInput
            id="cp-first-name"
            v-model="contactForm.first_name"
            :label="t('first_name')"
            :error="contactErrors.first_name?.[0]"
            required
          />
          <FormInput
            id="cp-last-name"
            v-model="contactForm.last_name"
            :label="t('last_name')"
            :error="contactErrors.last_name?.[0]"
            required
          />
        </div>
        <FormInput
          id="cp-email"
          v-model="contactForm.email"
          type="email"
          :label="t('email')"
          :error="contactErrors.email?.[0]"
        />
        <FormInput
          id="cp-phone"
          v-model="contactForm.phone"
          :label="t('phone')"
          :error="contactErrors.phone?.[0]"
        />
        <FormInput
          id="cp-position"
          v-model="contactForm.position"
          :label="t('position')"
          :error="contactErrors.position?.[0]"
        />
        <FormInput
          id="cp-notes"
          v-model="contactForm.notes"
          :label="t('notes')"
          :error="contactErrors.notes?.[0]"
        />
        <label class="flex items-center gap-2 text-sm">
          <input v-model="contactForm.is_primary" type="checkbox" class="rounded border-[hsl(var(--border))]" />
          {{ t('primary_contact') }}
        </label>
        <div class="flex justify-end gap-3">
          <Button type="button" variant="outline" @click="showContactModal = false">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="contactProcessing">{{ editingContact ? t('save_changes') : t('create') }}</Button>
        </div>
      </form>
    </Modal>

    <!-- Delete Contact Person Confirmation -->
    <ConfirmDialog
      :open="showDeleteContactDialog"
      :title="t('delete_contact_person')"
      :message="t('delete_contact_person_confirm')"
      :confirm-label="t('delete')"
      :processing="contactProcessing"
      @confirm="executeDeleteContact"
      @cancel="showDeleteContactDialog = false"
    />
  </AppLayout>
</template>
