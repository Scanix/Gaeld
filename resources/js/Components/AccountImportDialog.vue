<script setup>
import { ref, watch } from 'vue'
import Modal from '@/Components/UI/Modal.vue'
import Button from '@/Components/UI/Button.vue'
import FileUpload from '@/Components/UI/FileUpload.vue'
import { useTranslations } from '@/lib/useTranslations'
import { router } from '@inertiajs/vue3'

const props = defineProps({ open: Boolean })
const emit = defineEmits(['close'])

const { t } = useTranslations()

const file = ref(null)
const mode = ref('add')
const errors = ref({})
const processing = ref(false)

watch(() => props.open, (val) => {
  if (val) {
    file.value = null
    mode.value = 'add'
    errors.value = {}
  }
})

function onFileChange(selected) {
  file.value = selected ?? null
}

function submit() {
  if (!file.value) return
  processing.value = true
  errors.value = {}

  const formData = new FormData()
  formData.append('file', file.value)
  formData.append('mode', mode.value)

  router.post('/accounting/accounts/import', formData, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      emit('close')
    },
    onError: (errs) => {
      errors.value = errs
    },
    onFinish: () => {
      processing.value = false
    },
  })
}
</script>

<template>
  <Modal :open="open" :title="t('import_accounts')" @close="$emit('close')">
    <form class="space-y-4" @submit.prevent="submit">
      <FileUpload
        accept=".csv,.json"
        :label="t('import_file')"
        :error="errors.file"
        :help-text="t('import_format_help')"
        @change="onFileChange"
      />

      <div class="space-y-2">
        <label class="block text-sm font-medium">{{ t('import_mode') }}</label>
        <div class="flex gap-4">
          <label class="flex items-center gap-2 text-sm">
            <input v-model="mode" type="radio" value="add" class="accent-[hsl(var(--primary))]" />
            {{ t('import_mode_add') }}
          </label>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="mode" type="radio" value="replace" class="accent-[hsl(var(--primary))]" />
            {{ t('import_mode_replace') }}
          </label>
        </div>
        <p v-if="mode === 'replace'" class="text-xs text-amber-600 dark:text-amber-400">
          {{ t('import_mode_replace_warning') }}
        </p>
      </div>

      <div class="flex justify-end gap-3 pt-2">
        <Button variant="outline" type="button" @click="$emit('close')">{{ t('cancel') }}</Button>
        <Button type="submit" :disabled="processing || !file">{{ t('import') }}</Button>
      </div>
    </form>
  </Modal>
</template>
