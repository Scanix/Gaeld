<script setup>
import { cva } from 'class-variance-authority'
import { cn } from '@/lib/utils'

const props = defineProps({
  variant: {
    type: String,
    default: 'default',
    validator: (v) => ['default', 'secondary', 'destructive', 'outline', 'warning', 'info', 'success'].includes(v),
  },
  class: String,
})

const badgeVariants = cva(
  'inline-flex items-center rounded-md border px-2.5 py-0.5 text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]',
  {
    variants: {
      variant: {
        default: 'border-transparent bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))] shadow',
        secondary: 'border-transparent bg-[hsl(var(--secondary))] text-[hsl(var(--secondary-foreground))]',
        destructive: 'border-transparent bg-[hsl(var(--destructive))] text-[hsl(var(--destructive-foreground))] shadow',
        outline: 'text-[hsl(var(--foreground))]',
        warning: 'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        info: 'border-transparent bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-300',
        success: 'border-transparent bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  },
)
</script>

<template>
  <span :class="cn(badgeVariants({ variant: props.variant }), props.class)">
    <slot />
  </span>
</template>
