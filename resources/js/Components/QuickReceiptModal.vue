<script setup>
import { ref, computed } from 'vue'
import Modal from '@/Components/UI/Modal.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import { useTranslations } from '@/lib/useTranslations'
import { router } from '@inertiajs/vue3'
import { Camera, Loader2, Check, AlertCircle } from 'lucide-vue-next'

const props = defineProps({
  open: Boolean,
})

const emit = defineEmits(['close'])

const { t } = useTranslations()

// Stages: capture → scanning → review
const stage = ref('capture') // 'capture' | 'scanning' | 'review'
const error = ref(null)
const submitting = ref(false)

// File & preview
const fileInput = ref(null)
const selectedFile = ref(null)
const previewUrl = ref(null)

// OCR result
const receiptPath = ref(null)
const form = ref({
  category: '',
  amount: '',
  date: new Date().toISOString().slice(0, 10),
  vendor: '',
  description: '',
  currency: 'CHF',
})

const categoryOptions = computed(() => [
  { value: '', label: t('select_category') },
  { value: 'Office Supplies', label: t('cat_office_supplies') },
  { value: 'Travel', label: t('cat_travel') },
  { value: 'Software', label: t('cat_software') },
  { value: 'Professional Services', label: t('cat_professional_services') },
  { value: 'Marketing', label: t('cat_marketing') },
  { value: 'Rent', label: t('cat_rent') },
  { value: 'Utilities', label: t('cat_utilities') },
  { value: 'Insurance', label: t('cat_insurance') },
  { value: 'Other', label: t('cat_other') },
])

function onFileSelected(e) {
  const file = e.target.files[0]
  if (!file) return

  selectedFile.value = file

  // Generate preview
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  previewUrl.value = URL.createObjectURL(file)

  // Immediately start scanning
  scanReceipt()
}

function triggerCapture() {
  fileInput.value?.click()
}

async function scanReceipt() {
  stage.value = 'scanning'
  error.value = null

  const formData = new FormData()
  formData.append('receipt', selectedFile.value)

  try {
    const response = await fetch('/expenses/scan-receipt', {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
      body: formData,
      credentials: 'same-origin',
    })

    if (!response.ok) {
      const data = await response.json().catch(() => ({}))
      throw new Error(data.message || `HTTP ${response.status}`)
    }

    const data = await response.json()
    receiptPath.value = data.receipt_path

    // Poll for OCR results
    await pollForResults(data.scan_id)
  } catch (e) {
    error.value = e.message || t('scan_failed')
    stage.value = 'capture'
  }
}

async function pollForResults(scanId) {
  const maxAttempts = 60
  const intervalMs = 2000

  for (let i = 0; i < maxAttempts; i++) {
    await new Promise(resolve => setTimeout(resolve, intervalMs))

    const response = await fetch(`/expenses/scan-receipt/${scanId}`, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
      credentials: 'same-origin',
    })

    if (!response.ok) continue

    const data = await response.json()

    if (data.status === 'completed') {
      const extracted = data.extracted || {}
      if (extracted.amount) form.value.amount = String(extracted.amount)
      if (extracted.date) form.value.date = extracted.date
      if (extracted.vendor) form.value.vendor = extracted.vendor
      stage.value = 'review'
      return
    }

    if (data.status === 'failed') {
      // Still let user fill in manually
      stage.value = 'review'
      return
    }
  }

  // Timeout — let user fill in manually
  stage.value = 'review'
}

function getCsrfToken() {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

function createExpense() {
  submitting.value = true

  const formData = new FormData()
  formData.append('category', form.value.category || 'Other')
  formData.append('amount', form.value.amount)
  formData.append('date', form.value.date)
  formData.append('vendor', form.value.vendor)
  formData.append('description', form.value.description)
  formData.append('currency', form.value.currency)
  if (receiptPath.value) {
    formData.append('receipt_path', receiptPath.value)
  }

  router.post('/expenses', formData, {
    forceFormData: true,
    onFinish: () => {
      submitting.value = false
      resetAndClose()
    },
    onError: (errors) => {
      submitting.value = false
      error.value = Object.values(errors).flat().join(', ')
    },
  })
}

function resetAndClose() {
  stage.value = 'capture'
  error.value = null
  selectedFile.value = null
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = null
  }
  receiptPath.value = null
  form.value = {
    category: '',
    amount: '',
    date: new Date().toISOString().slice(0, 10),
    vendor: '',
    description: '',
    currency: 'CHF',
  }
  emit('close')
}
</script>

<template>
  <Modal :open="open" :title="t('quick_receipt')" @close="resetAndClose">
    <!-- Hidden file input — opens camera on mobile -->
    <input
      ref="fileInput"
      type="file"
      accept="image/jpeg,image/png"
      capture="environment"
      class="hidden"
      @change="onFileSelected"
    />

    <!-- CAPTURE STAGE -->
    <div v-if="stage === 'capture'" class="space-y-4">
      <p class="text-sm text-[hsl(var(--muted-foreground))]">
        {{ t('quick_receipt_description') }}
      </p>

      <div
        class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-[hsl(var(--border))] p-8 transition-colors hover:border-[hsl(var(--primary))] hover:bg-[hsl(var(--accent))]/50"
        @click="triggerCapture"
      >
        <Camera class="mb-3 h-10 w-10 text-[hsl(var(--muted-foreground))]" />
        <span class="text-sm font-medium">{{ t('take_photo_or_upload') }}</span>
        <span class="mt-1 text-xs text-[hsl(var(--muted-foreground))]">JPG, PNG — max 10 MB</span>
      </div>

      <div v-if="error" class="flex items-center gap-2 rounded-md bg-red-50 p-3 text-sm text-red-700">
        <AlertCircle class="h-4 w-4 shrink-0" />
        {{ error }}
      </div>
    </div>

    <!-- SCANNING STAGE -->
    <div v-if="stage === 'scanning'" class="flex flex-col items-center justify-center space-y-4 py-8">
      <Loader2 class="h-8 w-8 animate-spin text-[hsl(var(--primary))]" />
      <p class="text-sm font-medium">{{ t('scanning_receipt') }}</p>
      <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('extracting_fields') }}</p>
    </div>

    <!-- REVIEW STAGE -->
    <div v-if="stage === 'review'" class="space-y-4">
      <!-- Receipt thumbnail -->
      <div v-if="previewUrl" class="flex justify-center">
        <img
          :src="previewUrl"
          alt="Receipt"
          class="h-32 rounded-md border border-[hsl(var(--border))] object-contain"
        />
      </div>

      <div class="rounded-md bg-green-50 p-3">
        <div class="flex items-center gap-2 text-sm font-medium text-green-700">
          <Check class="h-4 w-4" />
          {{ t('scan_complete') }}
        </div>
        <p class="mt-1 text-xs text-green-600">{{ t('review_and_adjust') }}</p>
      </div>

      <!-- Editable form fields -->
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <FormInput
          id="qr-amount"
          v-model="form.amount"
          type="number"
          :label="t('amount')"
          required
        />
        <FormInput
          id="qr-date"
          v-model="form.date"
          type="date"
          :label="t('date')"
          required
        />
        <FormInput
          id="qr-vendor"
          v-model="form.vendor"
          :label="t('vendor')"
          class="sm:col-span-2"
        />
        <FormSelect
          id="qr-category"
          v-model="form.category"
          :label="t('category')"
          :options="categoryOptions"
          class="sm:col-span-2"
        />
      </div>

      <div v-if="error" class="flex items-center gap-2 rounded-md bg-red-50 p-3 text-sm text-red-700">
        <AlertCircle class="h-4 w-4 shrink-0" />
        {{ error }}
      </div>

      <div class="flex justify-end gap-3">
        <Button variant="outline" @click="resetAndClose">{{ t('cancel') }}</Button>
        <Button :disabled="submitting || !form.amount" @click="createExpense">
          <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
          {{ t('create_expense') }}
        </Button>
      </div>
    </div>
  </Modal>
</template>
