<script setup>
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import { useTranslations } from '@/lib/useTranslations'

defineProps({
  title: { type: String, required: true },
  value: { type: [String, Number], required: true },
  icon: { type: [Object, Function], default: null },
  iconClass: { type: String, default: '' },
  trend: { type: [String, Number], default: null },
})

const { t } = useTranslations()
</script>

<template>
  <Card>
    <CardHeader class="flex flex-row items-center justify-between pb-2">
      <CardDescription>{{ title }}</CardDescription>
      <component :is="icon" v-if="icon" :class="['h-4 w-4', iconClass]" />
    </CardHeader>
    <CardContent>
      <div class="text-2xl font-bold">{{ value }}</div>
      <p
        v-if="trend !== null && Number.isFinite(parseFloat(trend))"
        :class="['mt-1 text-xs', parseFloat(trend) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400']"
      >
        {{ parseFloat(trend) >= 0 ? '+' : '' }}{{ trend }}% {{ t('vs_last_year') }}
      </p>
    </CardContent>
  </Card>
</template>
