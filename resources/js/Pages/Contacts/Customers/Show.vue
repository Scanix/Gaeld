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
import { Pencil } from 'lucide-vue-next'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'
import EmptyState from '@/Components/UI/EmptyState.vue'
import { useContactPersons } from '@/lib/useContactPersons'

const { t } = useTranslations()
const { formatCurrency, formatDate } = useFormatters()

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

const cp = useContactPersons('customers', props.customer.uuid, props.customer.contact_persons ?? [])
</script>

<template>
  <AppLayout :title="customer.name" help-page="customers">
    <Breadcrumb :items="[{ label: t('customers'), href: '/customers' }, { label: customer.name }]" class="mb-4" />

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <h2 class="text-lg font-semibold">{{ customer.name }}</h2>
      <Button as="a" :href="`/customers/${customer.uuid}/edit`" variant="outline" size="sm">
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
