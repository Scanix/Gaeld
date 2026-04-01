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

const { t } = useTranslations()
const { formatCurrency, formatDate } = useFormatters()
const { toast } = useToast()

const props = defineProps({
  supplier: { type: Object, required: true },
  expenses: { type: Array, default: () => [] },
})

const statusVariant = {
  pending: 'secondary',
  approved: 'default',
  paid: 'success',
  rejected: 'destructive',
}

// Contact persons state
const contactPersons = ref(props.supplier.contact_persons ?? [])
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
        `/suppliers/${props.supplier.id}/contact-persons/${editingContact.value.id}`,
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
        `/suppliers/${props.supplier.id}/contact-persons`,
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
      `/suppliers/${props.supplier.id}/contact-persons/${contactToDelete.value.id}`,
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
  <AppLayout :title="supplier.name" help-page="suppliers">
    <Breadcrumb :items="[{ label: t('suppliers'), href: '/suppliers' }, { label: supplier.name }]" class="mb-4" />

    <div class="mb-4 flex items-center justify-end">
      <Button as="a" :href="`/suppliers/${supplier.id}/edit`">
        <Pencil class="mr-2 h-4 w-4" />
        {{ t('edit') }}
      </Button>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <!-- Supplier details -->
      <Card class="lg:col-span-1">
        <CardHeader>
          <CardTitle>{{ supplier.name }}</CardTitle>
        </CardHeader>
        <CardContent class="space-y-3 text-sm">
          <div v-if="supplier.email">
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('email') }}:</span>
            <a :href="`mailto:${supplier.email}`" class="ml-2 underline">{{ supplier.email }}</a>
          </div>
          <div v-if="supplier.phone">
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('phone') }}:</span>
            <span class="ml-2">{{ supplier.phone }}</span>
          </div>
          <div v-if="supplier.address || supplier.city">
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('address') }}:</span>
            <span class="ml-2">
              {{ [supplier.address, supplier.postal_code, supplier.city, supplier.country].filter(Boolean).join(', ') }}
            </span>
          </div>
          <div v-if="supplier.vat_number">
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('vat_number') }}:</span>
            <span class="ml-2 font-mono">{{ supplier.vat_number }}</span>
          </div>
          <div v-if="supplier.iban">
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('iban') }}:</span>
            <span class="ml-2 font-mono">{{ supplier.iban }}</span>
          </div>
          <div v-if="supplier.default_expense_category">
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('default_category') }}:</span>
            <span class="ml-2 capitalize">{{ supplier.default_expense_category }}</span>
          </div>
          <div v-if="supplier.internal_notes" class="border-t pt-3">
            <p class="text-[hsl(var(--muted-foreground))] mb-1">{{ t('internal_notes') }}</p>
            <p class="italic">{{ supplier.internal_notes }}</p>
          </div>
        </CardContent>
      </Card>

      <!-- Expenses -->
      <Card class="lg:col-span-2">
        <CardHeader>
          <CardTitle>{{ t('expenses') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <div v-if="!expenses.length" class="text-sm text-[hsl(var(--muted-foreground))]">
            {{ t('no_expenses_yet') }}
          </div>
          <table v-else class="w-full text-sm">
            <thead>
              <tr class="border-b text-[hsl(var(--muted-foreground))]">
                <th class="pb-2 text-left font-medium">{{ t('description') }}</th>
                <th class="pb-2 text-left font-medium">{{ t('date') }}</th>
                <th class="pb-2 text-right font-medium">{{ t('amount') }}</th>
                <th class="pb-2 text-left font-medium">{{ t('status') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="expense in expenses"
                :key="expense.id"
                class="border-b last:border-0"
              >
                <td class="py-2">
                  <a :href="`/expenses/${expense.id}`" class="font-medium underline">
                    {{ expense.description || expense.vendor }}
                  </a>
                </td>
                <td class="py-2">{{ formatDate(expense.expense_date) }}</td>
                <td class="py-2 text-right">{{ formatCurrency(expense.amount, expense.currency) }}</td>
                <td class="py-2">
                  <Badge :variant="statusVariant[expense.status] ?? 'secondary'">
                    {{ expense.status }}
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
        <div v-if="!contactPersons.length" class="text-sm text-[hsl(var(--muted-foreground))]">
          {{ t('no_contact_persons') }}
        </div>
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
