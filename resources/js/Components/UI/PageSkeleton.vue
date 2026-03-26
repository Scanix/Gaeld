<script setup>
import Skeleton from '@/Components/UI/Skeleton.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardContent from '@/Components/UI/CardContent.vue'

defineProps({
  variant: {
    type: String,
    default: 'card',
    validator: (v) => ['card', 'table', 'stats'].includes(v),
  },
  rows: {
    type: Number,
    default: 5,
  },
  cols: {
    type: Number,
    default: 4,
  },
})
</script>

<template>
  <!-- Stats row skeleton (e.g. dashboard KPI cards) -->
  <div v-if="variant === 'stats'" class="grid grid-cols-2 gap-4 sm:grid-cols-4">
    <Card v-for="i in cols" :key="i">
      <CardContent class="p-4">
        <Skeleton class="mb-2 h-3 w-20" />
        <Skeleton class="h-7 w-28" />
      </CardContent>
    </Card>
  </div>

  <!-- Table skeleton -->
  <Card v-else-if="variant === 'table'">
    <CardHeader>
      <Skeleton class="h-5 w-32" />
    </CardHeader>
    <CardContent>
      <div class="space-y-3">
        <div v-for="i in rows" :key="i" class="flex items-center gap-4">
          <Skeleton v-for="j in cols" :key="j" class="h-4 flex-1" />
        </div>
      </div>
    </CardContent>
  </Card>

  <!-- Card skeleton (default) -->
  <Card v-else>
    <CardHeader>
      <Skeleton class="h-5 w-40" />
      <Skeleton class="mt-1 h-3 w-60" />
    </CardHeader>
    <CardContent>
      <div class="space-y-3">
        <Skeleton v-for="i in rows" :key="i" class="h-4 w-full" />
      </div>
    </CardContent>
  </Card>
</template>
