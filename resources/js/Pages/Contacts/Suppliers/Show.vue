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
  supplier: { type: Object, required: true },
  expenses: { type: Array, default: () => [] },
})

const statusVariant = {
  pending: 'secondary',
  approved: 'default',
  paid: 'success',
  rejected: 'destructive',
}

const cp = useContactPersons('suppliers', props.supplier.uuid, props.supplier.contact_persons ?? [])
</script>

<template>
  <AppLayout :title="supplier.name" help-page="suppliers">
    <Breadcrumb :items="[{ label: t('suppliers'), href: '/suppliers' }, { label: supplier.name }]" class="mb-4" />

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <h2 class="text-lg font-semibold">{{ supplier.name }}</h2>
      <Button as="a" :href="`/suppliers/${supplier.uuid}/edit`" variant="outline" size="sm">
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
              {{ [supplier.address, supplier.postal_code, supplier.city, countryLabel(supplier.country, t)].filter(Boolean).join(', ') }}
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
                    {{ t('expense_status_' + expense.status) }}
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
