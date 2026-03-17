<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import { formatCurrency, formatDate } from '@/lib/utils'
import { useTranslations } from '@/lib/useTranslations'
import { Pencil } from 'lucide-vue-next'

const { t } = useTranslations()

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
</script>

<template>
  <AppLayout :title="customer.name" help-page="customers">
    <div class="mb-4 flex items-center justify-between">
      <Button as="a" href="/customers" variant="outline" size="sm">
        ← {{ t('back') }}
      </Button>
      <Button as="a" :href="`/customers/${customer.id}/edit`">
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
            <span class="text-[hsl(var(--muted-foreground))]">VAT:</span>
            <span class="ml-2 font-mono">{{ customer.vat_number }}</span>
          </div>
          <div>
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('currency') }}:</span>
            <span class="ml-2">{{ customer.currency }}</span>
          </div>
          <div v-if="customer.payment_terms">
            <span class="text-[hsl(var(--muted-foreground))]">{{ t('payment_terms') }}:</span>
            <span class="ml-2">{{ customer.payment_terms }} days</span>
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
          <div v-if="!invoices.length" class="text-sm text-[hsl(var(--muted-foreground))]">
            {{ t('no_invoices_yet') }}
          </div>
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
                    {{ invoice.status }}
                  </Badge>
                </td>
              </tr>
            </tbody>
          </table>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
