<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import FileUploadDropzone from '@/Components/FileUploadDropzone.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import {
  Check,
  ChevronRight,
  ChevronLeft,
  AlertTriangle,
  CheckCircle,
  XCircle,
  Loader2,
  Upload,
} from 'lucide-vue-next'

const { t } = useTranslations()
const { formatDate } = useFormatters()

const props = defineProps({
  session: { type: Object, required: true },
  supportedDataTypes: { type: Array, default: () => [] },
  acceptedExtensions: { type: Array, default: () => [] },
})

// ──────────────────────────────────────────────────
// Wizard step management
// ──────────────────────────────────────────────────
const step = ref(props.session.status === 'completed' ? 4 : 1)

const steps = computed(() => [
  { n: 1, label: t('migration.step_upload') },
  { n: 2, label: t('migration.step_preview') },
  { n: 3, label: t('migration.step_import') },
  { n: 4, label: t('migration.import_results') },
])

// ──────────────────────────────────────────────────
// Step 1: Upload
// ──────────────────────────────────────────────────
const selectedDataType = ref(props.supportedDataTypes[0]?.value ?? props.supportedDataTypes[0] ?? '')
const uploadedTypes = ref({}) // { [dataType]: { count, preview } }
const uploadError = ref('')

const uploadForm = useForm({
  file: null,
  data_type: '',
  column_mapping: null,
  delimiter: ',',
})

const dataTypeOptions = computed(() =>
  props.supportedDataTypes.map(dt => {
    const val = typeof dt === 'string' ? dt : dt.value
    return { value: val, label: t(`migration.data_types.${val}`) }
  })
)

const acceptString = computed(() =>
  props.acceptedExtensions.map(ext => `.${ext}`).join(',')
)

function onFileSelected(file) {
  uploadForm.file = file
  uploadError.value = ''
}

function submitUpload() {
  if (!uploadForm.file) return

  uploadForm.data_type = selectedDataType.value
  uploadForm.post(`/migration/${props.session.id}/upload`, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: (page) => {
      const flash = page.props.flash
      if (flash?.preview) {
        const { data_type, preview } = flash.preview
        uploadedTypes.value[data_type] = {
          count: preview.totalRows,
          valid: preview.validRows,
          invalid: preview.invalidRows,
          errors: preview.rowErrors,
          accountMappings: preview.accountMappings,
          sampleRows: preview.sampleRows,
        }
      }
      uploadForm.reset()
    },
    onError: (errors) => {
      uploadError.value = Object.values(errors).flat().join(', ')
    },
  })
}

const hasUploads = computed(() => Object.keys(uploadedTypes.value).length > 0)

// ──────────────────────────────────────────────────
// Step 2: Preview
// ──────────────────────────────────────────────────
const previewingType = ref('')

const currentPreview = computed(() => {
  if (!previewingType.value) {
    // Auto-select first uploaded type
    const keys = Object.keys(uploadedTypes.value)
    if (keys.length > 0) previewingType.value = keys[0]
  }
  return uploadedTypes.value[previewingType.value] ?? null
})

const previewTypeOptions = computed(() =>
  Object.keys(uploadedTypes.value).map(dt => ({
    value: dt,
    label: t(`migration.data_types.${dt}`),
  }))
)

// ──────────────────────────────────────────────────
// Step 3: Execute import
// ──────────────────────────────────────────────────
const importing = ref(false)
const importResults = ref([])

const executeForm = useForm({
  data_types: [],
  account_mappings: null,
})

function executeImport() {
  executeForm.data_types = Object.keys(uploadedTypes.value)
  importing.value = true

  executeForm.post(`/migration/${props.session.id}/execute`, {
    preserveScroll: true,
    onSuccess: () => {
      step.value = 4
    },
    onFinish: () => {
      importing.value = false
    },
  })
}

// ──────────────────────────────────────────────────
// Status helpers
// ──────────────────────────────────────────────────
const statusVariant = {
  pending: 'secondary',
  validating: 'info',
  importing: 'info',
  completed: 'success',
  failed: 'destructive',
  partially_completed: 'warning',
}

function confidenceVariant(confidence) {
  if (confidence >= 0.7) return 'success'
  if (confidence >= 0.4) return 'warning'
  return 'destructive'
}

function confidenceLabel(confidence) {
  if (confidence >= 0.7) return t('migration.high_confidence')
  if (confidence >= 0.4) return t('migration.medium_confidence')
  if (confidence > 0) return t('migration.low_confidence')
  return t('migration.no_match')
}

function formatColumns(row) {
  if (!row) return []
  const data = row.data ?? row
  return Object.entries(typeof data === 'function' ? data() : data).filter(
    ([key]) => !['sourceRow', 'warnings', 'valid'].includes(key)
  )
}
</script>

<template>
  <AppLayout :title="t('migration.migration')">
    <!-- Step indicator -->
    <nav class="mb-8">
      <ol class="flex items-center gap-0">
        <li
          v-for="(s, idx) in steps"
          :key="s.n"
          class="flex items-center"
        >
          <div class="flex items-center gap-2">
            <span
              :class="[
                'flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold',
                step > s.n ? 'bg-[hsl(var(--primary))] text-white' :
                step === s.n ? 'bg-[hsl(var(--primary))] text-white' :
                'bg-[hsl(var(--muted))] text-[hsl(var(--muted-foreground))]',
              ]"
            >
              <Check v-if="step > s.n" class="h-4 w-4" />
              <span v-else>{{ s.n }}</span>
            </span>
            <span
              :class="[
                'text-sm font-medium',
                step === s.n ? 'text-[hsl(var(--foreground))]' : 'text-[hsl(var(--muted-foreground))]',
              ]"
            >{{ s.label }}</span>
          </div>
          <ChevronRight v-if="idx < steps.length - 1" class="mx-3 h-4 w-4 text-[hsl(var(--muted-foreground))]" />
        </li>
      </ol>
    </nav>

    <div class="mb-4 flex items-center gap-2">
      <Badge variant="outline">{{ t(`migration.platform_${session.platform}`) }}</Badge>
      <Badge :variant="statusVariant[session.status] || 'secondary'">
        {{ t(`migration.status_${session.status}`) }}
      </Badge>
    </div>

    <!-- ──────────────────────────────────────── -->
    <!-- Step 1: Upload                           -->
    <!-- ──────────────────────────────────────── -->
    <Card v-if="step === 1">
      <CardHeader>
        <CardTitle>{{ t('migration.upload_files') }}</CardTitle>
        <CardDescription>{{ t('migration.upload_files_desc') }}</CardDescription>
      </CardHeader>
      <CardContent class="space-y-6">
        <!-- Already uploaded types -->
        <div v-if="hasUploads" class="space-y-2">
          <div
            v-for="(info, dt) in uploadedTypes"
            :key="dt"
            class="flex items-center gap-3 rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--muted))]/30 p-3"
          >
            <CheckCircle class="h-5 w-5 shrink-0 text-green-600 dark:text-green-400" />
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium">{{ t(`migration.data_types.${dt}`) }}</p>
              <p class="text-xs text-[hsl(var(--muted-foreground))]">
                {{ t('migration.file_parsed', { count: info.count }) }}
                <span v-if="info.invalid > 0" class="text-[hsl(var(--destructive))]">
                  ({{ info.invalid }} {{ t('migration.invalid_rows').toLowerCase() }})
                </span>
              </p>
            </div>
          </div>
        </div>

        <!-- Upload new data type -->
        <div class="space-y-4">
          <FormSelect
            id="data_type"
            v-model="selectedDataType"
            :label="t('migration.select_data_type')"
            :options="dataTypeOptions"
          />

          <FileUploadDropzone
            :accept="acceptString"
            :label="t('migration.upload_file')"
            :error="uploadError"
            @file-selected="onFileSelected"
          />

          <div class="flex justify-end">
            <Button :disabled="!uploadForm.file || uploadForm.processing" @click="submitUpload">
              <Upload class="mr-2 h-4 w-4" />
              {{ uploadForm.processing ? t('migration.importing') : t('migration.upload_file') }}
            </Button>
          </div>
        </div>

        <!-- Next step -->
        <div class="flex justify-end border-t border-[hsl(var(--border))] pt-4">
          <Button :disabled="!hasUploads" @click="step = 2">
            {{ t('migration.step_preview') }}
            <ChevronRight class="ml-2 h-4 w-4" />
          </Button>
        </div>
      </CardContent>
    </Card>

    <!-- ──────────────────────────────────────── -->
    <!-- Step 2: Preview                          -->
    <!-- ──────────────────────────────────────── -->
    <Card v-else-if="step === 2">
      <CardHeader>
        <CardTitle>{{ t('migration.preview_data') }}</CardTitle>
        <CardDescription>{{ t('migration.preview_desc') }}</CardDescription>
      </CardHeader>
      <CardContent class="space-y-6">
        <!-- Type selector -->
        <FormSelect
          v-if="previewTypeOptions.length > 1"
          id="preview_type"
          v-model="previewingType"
          :label="t('migration.select_data_type')"
          :options="previewTypeOptions"
        />

        <!-- Stats -->
        <div v-if="currentPreview" class="grid grid-cols-3 gap-4">
          <div class="rounded-lg border border-[hsl(var(--border))] p-4 text-center">
            <p class="text-2xl font-bold">{{ currentPreview.count }}</p>
            <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('migration.total_rows') }}</p>
          </div>
          <div class="rounded-lg border border-[hsl(var(--border))] p-4 text-center">
            <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ currentPreview.valid }}</p>
            <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('migration.valid_rows') }}</p>
          </div>
          <div class="rounded-lg border border-[hsl(var(--border))] p-4 text-center">
            <p class="text-2xl font-bold text-[hsl(var(--destructive))]">{{ currentPreview.invalid }}</p>
            <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('migration.invalid_rows') }}</p>
          </div>
        </div>

        <!-- Sample data table -->
        <div v-if="currentPreview?.sampleRows?.length" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-[hsl(var(--border))] text-[hsl(var(--muted-foreground))]">
                <th class="pb-2 text-left font-medium">#</th>
                <th
                  v-for="[key] in formatColumns(currentPreview.sampleRows[0])"
                  :key="key"
                  class="pb-2 text-left font-medium"
                >{{ key }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[hsl(var(--border))]">
              <tr
                v-for="(row, idx) in currentPreview.sampleRows.slice(0, 20)"
                :key="idx"
              >
                <td class="py-2 text-[hsl(var(--muted-foreground))]">{{ row.sourceRow ?? idx + 1 }}</td>
                <td
                  v-for="[key, val] in formatColumns(row)"
                  :key="key"
                  class="py-2 max-w-[200px] truncate"
                >{{ val ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Account mappings -->
        <div v-if="currentPreview?.accountMappings && Object.keys(currentPreview.accountMappings).length > 0">
          <h3 class="text-sm font-semibold mb-3">{{ t('migration.account_mapping') }}</h3>
          <p class="text-xs text-[hsl(var(--muted-foreground))] mb-3">{{ t('migration.account_mapping_desc') }}</p>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-[hsl(var(--border))] text-[hsl(var(--muted-foreground))]">
                  <th class="pb-2 text-left font-medium">{{ t('migration.source_account') }}</th>
                  <th class="pb-2 text-left font-medium">{{ t('migration.target_account') }}</th>
                  <th class="pb-2 text-left font-medium">{{ t('migration.confidence') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[hsl(var(--border))]">
                <tr v-for="(mapping, code) in currentPreview.accountMappings" :key="code">
                  <td class="py-2">
                    <span class="font-mono text-xs">{{ mapping.source_code }}</span>
                    <span class="ml-2 text-[hsl(var(--muted-foreground))]">{{ mapping.source_name }}</span>
                  </td>
                  <td class="py-2">
                    <template v-if="mapping.target_code">
                      <span class="font-mono text-xs">{{ mapping.target_code }}</span>
                      <span class="ml-2">{{ mapping.target_name }}</span>
                    </template>
                    <span v-else class="text-[hsl(var(--muted-foreground))] italic">{{ t('migration.no_match') }}</span>
                  </td>
                  <td class="py-2">
                    <Badge :variant="confidenceVariant(mapping.confidence)">
                      {{ confidenceLabel(mapping.confidence) }}
                    </Badge>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Row errors -->
        <div v-if="currentPreview?.errors && Object.keys(currentPreview.errors).length > 0">
          <h3 class="text-sm font-semibold mb-2 text-[hsl(var(--destructive))]">{{ t('migration.row_errors') }}</h3>
          <div class="space-y-1 max-h-48 overflow-y-auto">
            <div
              v-for="(errs, rowNum) in currentPreview.errors"
              :key="rowNum"
              class="flex items-start gap-2 text-xs"
            >
              <AlertTriangle class="h-3.5 w-3.5 shrink-0 mt-0.5 text-[hsl(var(--destructive))]" />
              <span>
                <strong>{{ t('migration.row') }} {{ rowNum }}:</strong>
                {{ Array.isArray(errs) ? errs.join(', ') : errs }}
              </span>
            </div>
          </div>
        </div>

        <!-- Navigation -->
        <div class="flex justify-between border-t border-[hsl(var(--border))] pt-4">
          <Button variant="outline" @click="step = 1">
            <ChevronLeft class="mr-2 h-4 w-4" />
            {{ t('back') }}
          </Button>
          <Button @click="step = 3">
            {{ t('migration.step_import') }}
            <ChevronRight class="ml-2 h-4 w-4" />
          </Button>
        </div>
      </CardContent>
    </Card>

    <!-- ──────────────────────────────────────── -->
    <!-- Step 3: Execute Import                   -->
    <!-- ──────────────────────────────────────── -->
    <Card v-else-if="step === 3">
      <CardHeader>
        <CardTitle>{{ t('migration.execute_import') }}</CardTitle>
        <CardDescription>{{ t('migration.execute_import_desc') }}</CardDescription>
      </CardHeader>
      <CardContent class="space-y-6">
        <!-- Summary of what will be imported -->
        <div class="space-y-2">
          <div
            v-for="(info, dt) in uploadedTypes"
            :key="dt"
            class="flex items-center justify-between rounded-lg border border-[hsl(var(--border))] p-4"
          >
            <div>
              <p class="font-medium text-sm">{{ t(`migration.data_types.${dt}`) }}</p>
              <p class="text-xs text-[hsl(var(--muted-foreground))]">
                {{ info.valid }} {{ t('migration.valid_rows').toLowerCase() }}
                <span v-if="info.invalid > 0" class="text-[hsl(var(--destructive))]">
                  · {{ info.invalid }} {{ t('migration.invalid_rows').toLowerCase() }}
                </span>
              </p>
            </div>
            <CheckCircle class="h-5 w-5 text-green-600 dark:text-green-400" />
          </div>
        </div>

        <!-- Navigation -->
        <div class="flex justify-between border-t border-[hsl(var(--border))] pt-4">
          <Button variant="outline" @click="step = 2">
            <ChevronLeft class="mr-2 h-4 w-4" />
            {{ t('back') }}
          </Button>
          <Button :disabled="importing" @click="executeImport">
            <Loader2 v-if="importing" class="mr-2 h-4 w-4 animate-spin" />
            {{ importing ? t('migration.importing') : t('migration.execute_import') }}
          </Button>
        </div>
      </CardContent>
    </Card>

    <!-- ──────────────────────────────────────── -->
    <!-- Step 4: Results                          -->
    <!-- ──────────────────────────────────────── -->
    <Card v-else>
      <CardContent class="py-12 text-center">
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
          <Check class="h-8 w-8 text-green-600 dark:text-green-400" />
        </div>
        <h2 class="mb-2 text-xl font-bold">{{ t('migration.all_done') }}</h2>
        <p class="mb-6 text-sm text-[hsl(var(--muted-foreground))]">{{ t('migration.all_done_desc') }}</p>

        <!-- Per-type results from session -->
        <div v-if="session.imported_counts && Object.keys(session.imported_counts).length > 0" class="mx-auto mb-6 max-w-md space-y-2">
          <div
            v-for="(count, dt) in session.imported_counts"
            :key="dt"
            class="flex items-center justify-between rounded-lg border border-[hsl(var(--border))] px-4 py-3"
          >
            <span class="text-sm font-medium">{{ t(`migration.data_types.${dt}`) }}</span>
            <div class="flex items-center gap-2">
              <Badge variant="success">{{ t('migration.imported_count', { count }) }}</Badge>
              <Badge
                v-if="session.data_types_status?.[dt] === 'failed'"
                variant="destructive"
              >{{ t('migration.status_failed') }}</Badge>
            </div>
          </div>
        </div>

        <div class="flex justify-center gap-3">
          <Button as="a" href="/">{{ t('migration.go_to_dashboard') }}</Button>
          <Button variant="outline" as="a" href="/migration">{{ t('migration.start_another') }}</Button>
        </div>
      </CardContent>
    </Card>
  </AppLayout>
</template>
