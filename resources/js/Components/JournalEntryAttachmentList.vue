<script setup>
import { Download, Paperclip, X } from 'lucide-vue-next'
import Button from '@/Components/UI/Button.vue'
import { useFormatters } from '@/lib/useFormatters'
import { useTranslations } from '@/lib/useTranslations'

defineProps({
  attachments: { type: Array, required: true },
  allowRemoval: Boolean,
})

defineEmits(['remove'])

const { formatDate } = useFormatters()
const { t } = useTranslations()
</script>

<template>
  <ul class="divide-y divide-[hsl(var(--border))]">
    <li v-for="attachment in attachments" :key="attachment.id" class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-2">
      <div class="min-w-0">
        <a :href="attachment.show_url" target="_blank" rel="noopener noreferrer" class="inline-flex max-w-full items-start gap-2 text-sm font-medium text-[hsl(var(--primary))] underline-offset-4 hover:underline">
          <Paperclip aria-hidden="true" class="mt-0.5 size-4 shrink-0" />
          <span class="break-all">{{ attachment.filename }}</span>
        </a>
        <p class="text-xs text-[hsl(var(--muted-foreground))]">
          {{ attachment.added_by
            ? t('journal_attachment_added_by', { date: formatDate(attachment.added_at), author: attachment.added_by })
            : t('journal_attachment_added_on', { date: formatDate(attachment.added_at) }) }}
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-1">
        <Button as="a" :href="attachment.download_url" :download="attachment.filename" variant="ghost" size="sm">
          <Download aria-hidden="true" class="mr-1 size-4" />
          {{ t('download') }}<span class="sr-only"> {{ attachment.filename }}</span>
        </Button>
        <Button v-if="allowRemoval && attachment.remove_url" type="button" variant="ghost" size="sm" @click="$emit('remove', attachment)">
          <X aria-hidden="true" class="mr-1 size-4" />
          {{ t('journal_attachment_unlink') }}<span class="sr-only"> {{ attachment.filename }}</span>
        </Button>
      </div>
    </li>
  </ul>
</template>
