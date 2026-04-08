<script setup>
import Modal from './Modal.vue'
import Button from './Button.vue'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

defineProps({
  open: Boolean,
  saving: Boolean,
  showSave: { type: Boolean, default: true },
})

defineEmits(['save', 'discard', 'stay'])
</script>

<template>
  <Modal :open="open" :title="t('unsaved_changes')" @close="$emit('stay')">
    <p class="mb-6 text-sm text-[hsl(var(--muted-foreground))]">
      {{ t('unsaved_changes_message') }}
    </p>
    <div class="flex justify-end gap-3">
      <Button variant="outline" @click="$emit('discard')">{{ t('discard') }}</Button>
      <Button v-if="showSave" :disabled="saving" @click="$emit('save')">{{ t('save_draft') }}</Button>
      <Button variant="secondary" @click="$emit('stay')">{{ t('stay') }}</Button>
    </div>
  </Modal>
</template>
