<script setup>
import { ref, computed } from 'vue'
import { Upload, X, FileText } from 'lucide-vue-next'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  modelValue: { type: File, default: null },
  size: { type: String, default: 'default' }, // 'default' | 'compact'
  accept: { type: String, default: '' },
  label: { type: String, default: null },
  error: { type: String, default: null },
  helpText: { type: String, default: null },
  maxSizeMb: { type: Number, default: 10 },
  capture: { type: String, default: null },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'change'])

const fileInput = ref(null)
const isDragging = ref(false)
const selectedFile = ref(props.modelValue)
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
  if (droppedFile) processFile(droppedFile)
}

function onInputChange(e) {
  const file = e.target.files?.[0]
  if (file) processFile(file)
}

function processFile(f) {
  localError.value = null

  if (props.maxSizeMb && f.size > props.maxSizeMb * 1024 * 1024) {
    localError.value = t('file_size_exceeds', { size: props.maxSizeMb })
    return
  }

  if (props.accept) {
    const extensions = props.accept.split(',').map(ext => ext.trim().toLowerCase())
    const fileName = f.name.toLowerCase()
    const matches = extensions.some(ext => {
      if (ext.startsWith('.')) return fileName.endsWith(ext)
      return f.type === ext
    })
    if (!matches) {
      localError.value = t('invalid_file_type', { types: acceptExtensions.value })
      return
    }
  }

  selectedFile.value = f
  emit('update:modelValue', f)
  emit('change', f)
}

function removeFile() {
  selectedFile.value = null
  localError.value = null
  if (fileInput.value) fileInput.value.value = ''
  emit('update:modelValue', null)
  emit('change', null)
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
    <label v-if="label" class="text-sm font-medium">{{ label }}</label>

    <!-- DEFAULT SIZE: Full dropzone -->
    <template v-if="size === 'default'">
      <!-- No file selected: drop zone -->
      <div
        v-if="!selectedFile"
        :class="[
          'relative flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-6 transition-colors',
          disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
          isDragging
            ? 'border-[hsl(var(--primary))] bg-[hsl(var(--primary))]/5'
            : 'border-[hsl(var(--border))] hover:border-[hsl(var(--primary))]/50 hover:bg-[hsl(var(--muted))]/50',
        ]"
        @dragover="onDragOver"
        @dragleave="onDragLeave"
        @drop="onDrop"
        @click="!disabled && openFilePicker()"
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
          :accept="accept || undefined"
          :capture="capture || undefined"
          :disabled="disabled"
          class="sr-only"
          @change="onInputChange"
        />
      </div>

      <!-- File selected: display row -->
      <div
        v-else
        class="flex items-center gap-3 rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--muted))]/30 p-3"
      >
        <FileText class="h-8 w-8 shrink-0 text-[hsl(var(--primary))]" />
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium">{{ selectedFile.name }}</p>
          <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ formatSize(selectedFile.size) }}</p>
        </div>
        <button
          type="button"
          class="shrink-0 rounded-md p-1 text-[hsl(var(--muted-foreground))] transition-colors hover:text-[hsl(var(--foreground))]"
          @click.stop="removeFile"
        >
          <X class="h-4 w-4" />
        </button>
      </div>
    </template>

    <!-- COMPACT SIZE: Inline button-style with drag support -->
    <template v-else>
      <div
        class="relative"
        @dragover="onDragOver"
        @dragleave="onDragLeave"
        @drop="onDrop"
      >
        <!-- No file: styled trigger -->
        <div
          v-if="!selectedFile"
          :class="[
            'flex h-9 w-full select-none items-center gap-2 rounded-md border bg-transparent px-3 py-1 text-sm transition-colors',
            isDragging
              ? 'border-[hsl(var(--primary))] bg-[hsl(var(--primary))]/5'
              : 'hover:border-[hsl(var(--primary))]/50 hover:bg-[hsl(var(--muted))]/50',
            disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
            displayError ? 'border-[hsl(var(--destructive))]' : 'border-[hsl(var(--input))]',
          ]"
          @click="!disabled && openFilePicker()"
        >
          <Upload class="h-4 w-4 shrink-0 text-[hsl(var(--muted-foreground))]" />
          <span class="text-[hsl(var(--muted-foreground))]">{{ t('click_to_upload') }}</span>
        </div>

        <!-- File selected: name + remove -->
        <div
          v-else
          :class="[
            'flex h-9 items-center gap-2 rounded-md border bg-[hsl(var(--muted))]/30 px-3 text-sm',
            displayError ? 'border-[hsl(var(--destructive))]' : 'border-[hsl(var(--border))]',
          ]"
        >
          <FileText class="h-4 w-4 shrink-0 text-[hsl(var(--primary))]" />
          <span class="flex-1 truncate font-medium">{{ selectedFile.name }}</span>
          <button
            type="button"
            class="shrink-0 rounded p-0.5 text-[hsl(var(--muted-foreground))] transition-colors hover:text-[hsl(var(--foreground))]"
            @click.stop="removeFile"
          >
            <X class="h-4 w-4" />
          </button>
        </div>

        <!-- Hidden input always present in compact mode -->
        <input
          ref="fileInput"
          type="file"
          :accept="accept || undefined"
          :capture="capture || undefined"
          :disabled="disabled"
          class="sr-only"
          @change="onInputChange"
        />
      </div>

      <!-- Slot for extra content e.g. existing file preview -->
      <slot />
    </template>

    <!-- Help text -->
    <p v-if="helpText && !displayError" class="text-xs text-[hsl(var(--muted-foreground))]">{{ helpText }}</p>

    <!-- Error -->
    <p v-if="displayError" class="text-xs text-[hsl(var(--destructive))]">{{ displayError }}</p>
  </div>
</template>
