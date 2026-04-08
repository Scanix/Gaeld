<script setup>
import { ref, computed } from 'vue'
import { Upload, X, FileText } from 'lucide-vue-next'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  accept: { type: String, default: '' },
  maxSizeMb: { type: Number, default: 10 },
  label: { type: String, default: null },
  error: { type: String, default: null },
  helpText: { type: String, default: null },
})

const emit = defineEmits(['file-selected'])

const file = ref(null)
const isDragging = ref(false)
const fileInput = ref(null)
const localError = ref(null)

const displayError = computed(() => props.error || localError.value)

const acceptExtensions = computed(() => {
  if (!props.accept) return ''
  return props.accept
    .split(',')
    .map(ext => ext.trim().replace(/^\./, '').toUpperCase())
    .join(', ')
})

function onDragOver(e) {
  e.preventDefault()
  isDragging.value = true
}

function onDragLeave() {
  isDragging.value = false
}

function onDrop(e) {
  e.preventDefault()
  isDragging.value = false

  const droppedFile = e.dataTransfer?.files?.[0]
  if (droppedFile) {
    processFile(droppedFile)
  }
}

function onInputChange(e) {
  const selectedFile = e.target.files?.[0]
  if (selectedFile) {
    processFile(selectedFile)
  }
}

function processFile(f) {
  localError.value = null

  // Validate file size
  if (f.size > props.maxSizeMb * 1024 * 1024) {
    localError.value = t('file_size_exceeds', { size: props.maxSizeMb })
    return
  }

  // Validate file extension
  if (props.accept) {
    const extensions = props.accept.split(',').map(ext => ext.trim().toLowerCase())
    const fileName = f.name.toLowerCase()
    const matches = extensions.some(ext => {
      if (ext.startsWith('.')) return fileName.endsWith(ext)
      // MIME type check
      return f.type === ext
    })
    if (!matches) {
      localError.value = t('invalid_file_type', { types: acceptExtensions.value })
      return
    }
  }

  file.value = f
  emit('file-selected', f)
}

function removeFile() {
  file.value = null
  localError.value = null
  if (fileInput.value) {
    fileInput.value.value = ''
  }
  emit('file-selected', null)
}

function openFilePicker() {
  fileInput.value?.click()
}

function formatSize(bytes) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
</script>

<template>
  <div class="space-y-2">
    <label v-if="label" class="text-sm font-medium">
      {{ label }}
    </label>

    <!-- Drop zone -->
    <div
      v-if="!file"
      :class="[
        'relative flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-6 transition-colors cursor-pointer',
        isDragging
          ? 'border-[hsl(var(--primary))] bg-[hsl(var(--primary))]/5'
          : 'border-[hsl(var(--border))] hover:border-[hsl(var(--primary))]/50 hover:bg-[hsl(var(--muted))]/50',
      ]"
      @dragover="onDragOver"
      @dragleave="onDragLeave"
      @drop="onDrop"
      @click="openFilePicker"
    >
      <Upload :class="['h-8 w-8 mb-2', isDragging ? 'text-[hsl(var(--primary))]' : 'text-[hsl(var(--muted-foreground))]']" />
      <p class="text-sm font-medium">
        <span class="text-[hsl(var(--primary))]">{{ t('click_to_upload') }}</span>
        {{ t('or_drag_and_drop') }}
      </p>
      <p class="mt-1 text-xs text-[hsl(var(--muted-foreground))]">
        <template v-if="acceptExtensions">{{ acceptExtensions }} · </template>
        {{ t('max_file_size', { size: maxSizeMb }) }}
      </p>
      <input
        ref="fileInput"
        type="file"
        :accept="accept"
        class="sr-only"
        @change="onInputChange"
      />
    </div>

    <!-- Selected file display -->
    <div
      v-else
      class="flex items-center gap-3 rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--muted))]/30 p-3"
    >
      <FileText class="h-8 w-8 shrink-0 text-[hsl(var(--primary))]" />
      <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium">{{ file.name }}</p>
        <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ formatSize(file.size) }}</p>
      </div>
      <button
        type="button"
        class="shrink-0 rounded-md p-1 text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] transition-colors"
        @click.stop="removeFile"
      >
        <X class="h-4 w-4" />
      </button>
    </div>

    <!-- Help text -->
    <p v-if="helpText && !displayError" class="text-xs text-[hsl(var(--muted-foreground))]">{{ helpText }}</p>

    <!-- Error -->
    <p v-if="displayError" class="text-xs text-[hsl(var(--destructive))]">{{ displayError }}</p>
  </div>
</template>
