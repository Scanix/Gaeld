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
  supplier: { type: Object, required: true },
  expenses: { type: Array, default: () => [] },
})

const statusVariant = {
  pending: 'secondary',
  approved: 'default',
  paid: 'success',
  rejected: 'destructive',
}
</script>

<template>
  <AppLayout :title="supplier.name" help-page="suppliers">
    <div class="mb-4 flex items-center justify-between">
      <Button as="a" href="/suppliers" variant="outline" size="sm">
        ← {{ t('back') }}
      </Button>
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
            <span class="text-[hsl(var(--muted-foreground))]">VAT:</span>
            <span class="ml-2 font-mono">{{ supplier.vat_number }}</span>
          </div>
          <div v-if="supplier.iban">
            <span class="text-[hsl(var(--muted-foreground))]">IBAN:</span>
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
  </AppLayout>
</template>
