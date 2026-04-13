<script setup>
import { computed } from 'vue'
import Modal from './Modal.vue'
import Button from './Button.vue'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  open: Boolean,
  title: { type: String, default: null },
  message: { type: String, default: null },
  confirmLabel: { type: String, default: null },
  confirmVariant: { type: String, default: 'destructive' },
  processing: Boolean,
  errors: { type: Object, default: () => ({}) },
})

const errorMessages = computed(() => {
  const entries = Object.entries(props.errors)
  if (!entries.length) return []
  return entries.map(([key, msg]) => typeof msg === 'string' ? msg : String(msg))
})

defineEmits(['confirm', 'cancel'])
</script>

<template>
  <Modal :open="open" :title="title ?? t('confirm_action')" @close="$emit('cancel')">
    <p class="mb-6 text-sm text-[hsl(var(--muted-foreground))]">{{ message ?? t('are_you_sure') }}</p>
    <div v-if="errorMessages.length" class="mb-4 rounded-md border border-[hsl(var(--destructive))]/20 bg-[hsl(var(--destructive))]/5 p-3" role="alert">
      <ul class="list-disc pl-4 text-sm text-[hsl(var(--destructive))]">
        <li v-for="(err, i) in errorMessages" :key="i">{{ err }}</li>
      </ul>
    </div>
    <div class="flex justify-end gap-3">
      <Button variant="outline" @click="$emit('cancel')">{{ t('cancel') }}</Button>
      <Button :variant="confirmVariant" :disabled="processing" @click="$emit('confirm')">
        {{ confirmLabel ?? t('confirm') }}
      </Button>
    </div>
  </Modal>
</template>
