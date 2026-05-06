<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import ContactPersonsSection from '@/Components/Contacts/ContactPersonsSection.vue'
import { useFormatters } from '@/lib/useFormatters'
import { useTranslations } from '@/lib/useTranslations'
import { countryLabel } from '@/lib/contactOptions'
import { Pencil } from 'lucide-vue-next'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'
import EmptyState from '@/Components/UI/EmptyState.vue'
import { useContactPersons } from '@/lib/useContactPersons'

const { t } = useTranslations()
const { formatCurrency, formatDate } = useFormatters()

const props = defineProps({
  contact: { type: Object, required: true },
  invoices: { type: Array, default: () => [] },
  expenses: { type: Array, default: () => [] },
})

const invoiceStatusVariant = {
  draft: 'secondary',
  sent: 'default',
  paid: 'success',
  overdue: 'destructive',
  cancelled: 'outline',
}

const expenseStatusVariant = {
  pending: 'secondary',
  approved: 'default',
  paid: 'success',
  rejected: 'destructive',
}

const cp = useContactPersons('contacts', props.contact.uuid, props.contact.contact_persons ?? [])
</script>

<template>
  <AppLayout :title="contact.name" help-page="contacts">
    <Breadcrumb :items="[{ label: t('contacts'), href: '/contacts' }, { label: contact.name }]" class="mb-4" />

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold">{{ contact.name }}</h2>
        <Badge variant="default" class="text-xs">{{ t('contact') }}</Badge>
      </div>
      <Button as="a" :href="`/contacts/${contact.uuid}/edit`" variant="outline" size="sm">
        <Pencil class="mr-2 h-4 w-4" />
        {{ t('edit') }}
      </Button>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <!-- Contact details -->
      <Card class="lg:col-span-1">
        <CardHeader>
          <CardTitle>{{ contact.name }}</CardTitle>
        </CardHeader>
        <CardContent class="space-y-3 text-sm">
          <div v-if="contact.email">
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('email') }}:</span>
            <a :href="`mailto:${contact.email}`" class="ml-2 underline">{{ contact.email }}</a>
          </div>
          <div v-if="contact.phone">
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('phone') }}:</span>
            <span class="ml-2">{{ contact.phone }}</span>
          </div>
          <div v-if="contact.address || contact.city">
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('address') }}:</span>
            <span class="ml-2">
              {{ [contact.address, contact.postal_code, contact.city, countryLabel(contact.country, t)].filter(Boolean).join(', ') }}
            </span>
          </div>
          <div v-if="contact.vat_number">
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('vat_number') }}:</span>
            <span class="ml-2 font-mono">{{ contact.vat_number }}</span>
          </div>
          <div>
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('currency') }}:</span>
            <span class="ml-2">{{ contact.currency }}</span>
          </div>
          <div v-if="contact.payment_terms">
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('payment_terms') }}:</span>
            <span class="ml-2">{{ t('days_count', { count: contact.payment_terms }) }}</span>
          </div>
          <div v-if="contact.iban">
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('iban') }}:</span>
            <span class="ml-2 font-mono">{{ contact.iban }}</span>
          </div>
          <div v-if="contact.default_expense_category">
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('default_category') }}:</span>
            <span class="ml-2 capitalize">{{ contact.default_expense_category }}</span>
          </div>
          <div v-if="contact.internal_notes" class="border-t pt-3">
            <p class="text-[hsl(var(--muted-foreground))] mb-1">{{ t('internal_notes') }}</p>
            <p class="italic">{{ contact.internal_notes }}</p>
          </div>
        </CardContent>
      </Card>

      <div class="lg:col-span-2 flex flex-col gap-6">
        <!-- Invoices -->
        <Card>
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
                <tr v-for="invoice in invoices" :key="invoice.id" class="border-b last:border-0">
                  <td class="py-2">
                    <a :href="`/invoices/${invoice.id}`" class="font-medium underline">{{ invoice.number }}</a>
                  </td>
                  <td class="py-2">{{ formatDate(invoice.issue_date) }}</td>
                  <td class="py-2 text-right">{{ formatCurrency(invoice.total, invoice.currency) }}</td>
                  <td class="py-2">
                    <Badge :variant="invoiceStatusVariant[invoice.status] ?? 'secondary'">
                      {{ t('invoice_status_' + invoice.status) }}
                    </Badge>
                  </td>
                </tr>
              </tbody>
            </table>
          </CardContent>
        </Card>

        <!-- Expenses -->
        <Card>
          <CardHeader>
            <CardTitle>{{ t('expenses') }}</CardTitle>
          </CardHeader>
          <CardContent>
            <EmptyState v-if="!expenses.length" :title="t('no_expenses_yet')" />
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
                <tr v-for="expense in expenses" :key="expense.id" class="border-b last:border-0">
                  <td class="py-2">
                    <a :href="`/expenses/${expense.uuid}`" class="font-medium underline">
                      {{ expense.description || t('expense') }}
                    </a>
                  </td>
                  <td class="py-2">{{ formatDate(expense.date) }}</td>
                  <td class="py-2 text-right">{{ formatCurrency(expense.amount, expense.currency) }}</td>
                  <td class="py-2">
                    <Badge :variant="expenseStatusVariant[expense.status] ?? 'secondary'">
                      {{ t('expense_status_' + expense.status) }}
                    </Badge>
                  </td>
                </tr>
              </tbody>
            </table>
          </CardContent>
        </Card>
      </div>
    </div>

    <!-- Contact Persons -->
    <ContactPersonsSection
      :contact-persons="cp.contactPersons.value"
      :show-contact-modal="cp.showContactModal.value"
      :show-delete-contact-dialog="cp.showDeleteContactDialog.value"
      :editing-contact="cp.editingContact.value"
      :contact-errors="cp.contactErrors.value"
      :contact-processing="cp.contactProcessing.value"
      :contact-form="cp.contactForm"
      @add="cp.openAddContact"
      @edit="cp.openEditContact"
      @submit="cp.submitContact"
      @confirm-delete="cp.confirmDeleteContact"
      @execute-delete="cp.executeDeleteContact"
      @close-modal="cp.showContactModal.value = false"
      @close-dialog="cp.showDeleteContactDialog.value = false"
    />
  </AppLayout>
</template>
