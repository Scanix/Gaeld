<script setup>
import { ref, computed } from 'vue'
import Modal from '@/Components/UI/Modal.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import FileUpload from '@/Components/UI/FileUpload.vue'
import Alert from '@/Components/UI/Alert.vue'
import { useTranslations } from '@/lib/useTranslations'
import { router } from '@inertiajs/vue3'
import { Loader2, AlertCircle } from 'lucide-vue-next'

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
const selectedFile = ref(null)
const previewUrl = ref(null)

// OCR result
const receiptPath = ref(null)
const ocrConfidence = ref(null)
const ocrHasData = ref(false)
const scanElapsed = ref(0)
const scanTimer = ref(null)
const form = ref({
  category: '',
  amount: '',
  date: new Date().toISOString().slice(0, 10),
  vendor: '',
  description: '',
  currency: 'CHF',
  vat: '',
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

function onFileSelected(file) {
  if (!file) return

  selectedFile.value = file

  // Generate preview
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  previewUrl.value = URL.createObjectURL(file)

  // Immediately start scanning
  scanReceipt()
}

async function scanReceipt() {
  stage.value = 'scanning'
  error.value = null
  scanElapsed.value = 0
  ocrConfidence.value = null
  scanTimer.value = setInterval(() => { scanElapsed.value++ }, 1000)

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
      if (extracted.vat != null) form.value.vat = String(extracted.vat)
      ocrConfidence.value = extracted.confidence ?? data.confidence ?? null
      ocrHasData.value = !!(extracted.amount || extracted.date || extracted.vendor)
      clearInterval(scanTimer.value)
      stage.value = 'review'
      return
    }

    if (data.status === 'failed') {
      // Still let user fill in manually
      clearInterval(scanTimer.value)
      stage.value = 'review'
      return
    }
  }

  // Timeout — let user fill in manually
  clearInterval(scanTimer.value)
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
  if (form.value.vat) {
    formData.append('vat_amount', form.value.vat)
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
  clearInterval(scanTimer.value)
  scanElapsed.value = 0
  ocrConfidence.value = null
  ocrHasData.value = false
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
    vat: '',
  }
  emit('close')
}
</script>

<template>
  <Modal :open="open" :title="t('quick_receipt')" @close="resetAndClose">
    <!-- CAPTURE STAGE -->
    <div v-if="stage === 'capture'" class="space-y-4">
      <p class="text-sm text-[hsl(var(--muted-foreground))]">
        {{ t('quick_receipt_description') }}
      </p>

      <FileUpload
        accept="image/jpeg,image/png"
        capture="environment"
        @change="onFileSelected"
      />

      <div v-if="error" class="flex items-center gap-2 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
        <AlertCircle class="h-4 w-4 shrink-0" />
        {{ error }}
      </div>
    </div>

    <!-- SCANNING STAGE -->
    <div v-if="stage === 'scanning'" class="flex flex-col items-center justify-center space-y-4 py-8">
      <Loader2 class="h-8 w-8 animate-spin text-[hsl(var(--primary))]" />
      <p class="text-sm font-medium">{{ t('scanning_receipt') }}</p>
      <p class="text-xs text-[hsl(var(--muted-foreground))]">
        {{ scanElapsed < 90 ? t('ocr_processing') : t('ocr_taking_longer') }}
      </p>
      <div class="w-full max-w-xs">
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-[hsl(var(--muted))]">
          <div
            class="h-full rounded-full bg-[hsl(var(--primary))] transition-all duration-1000"
            :style="{ width: Math.min(scanElapsed * 100 / 120, 95) + '%' }"
          />
        </div>
        <p class="mt-1 text-center text-xs tabular-nums text-[hsl(var(--muted-foreground))]">{{ scanElapsed }}s</p>
      </div>
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

      <Alert :variant="ocrHasData ? 'success' : 'warning'">
        <div class="flex items-center justify-between">
          <span>{{ ocrHasData ? t('scan_complete') : t('scan_no_data') }}</span>
          <span v-if="ocrHasData && ocrConfidence != null" class="text-xs tabular-nums opacity-70">
            {{ t('ocr_confidence') }}: {{ Math.round(ocrConfidence * 100) }}%
          </span>
        </div>
        <p v-if="ocrHasData" class="mt-1 text-xs opacity-75">{{ t('review_and_adjust') }}</p>
      </Alert>

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
        <FormInput
          id="qr-vat"
          v-model="form.vat"
          type="number"
          step="0.01"
          :label="t('vat_amount')"
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

      <Alert v-if="error" variant="error">{{ error }}</Alert>

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
