<script setup>
import { ref, computed } from 'vue'
import Modal from '@/Components/UI/Modal.vue'
import Button from '@/Components/UI/Button.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import { useTranslations } from '@/lib/useTranslations'

const props = defineProps({
  open: Boolean,
  headers: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'confirm'])
const { t } = useTranslations()

const dateCol = ref('')
const amountCol = ref('')
const descriptionCol = ref('')
const referenceCol = ref('')
const delimiter = ref(',')

const columnOptions = computed(() => [
  { value: '', label: '—' },
  ...props.headers.map((h, i) => ({ value: String(i), label: h || `Column ${i + 1}` })),
])

const delimiterOptions = [
  { value: ',', label: t('delimiter_comma') },
  { value: ';', label: t('delimiter_semicolon') },
  { value: '\t', label: t('delimiter_tab') },
]

const canConfirm = computed(() => dateCol.value !== '' && amountCol.value !== '')

function confirm() {
  emit('confirm', {
    mapping: {
      date: parseInt(dateCol.value),
      amount: parseInt(amountCol.value),
      description: descriptionCol.value !== '' ? parseInt(descriptionCol.value) : null,
      reference: referenceCol.value !== '' ? parseInt(referenceCol.value) : null,
    },
    delimiter: delimiter.value,
  })
}
</script>

<template>
  <Modal :open="open" :title="t('csv_column_mapping')" @close="$emit('close')">
    <div class="space-y-4">
      <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('csv_mapping_desc') }}</p>

      <FormSelect
        id="delimiter"
        v-model="delimiter"
        :label="t('csv_delimiter')"
        :options="delimiterOptions"
      />

      <FormSelect
        id="date_col"
        v-model="dateCol"
        :label="t('csv_date_column')"
        :options="columnOptions"
        required
      />

      <FormSelect
        id="amount_col"
        v-model="amountCol"
        :label="t('csv_amount_column')"
        :options="columnOptions"
        required
      />

      <FormSelect
        id="description_col"
        v-model="descriptionCol"
        :label="t('csv_description_column')"
        :options="columnOptions"
      />

      <FormSelect
        id="reference_col"
        v-model="referenceCol"
        :label="t('csv_reference_column')"
        :options="columnOptions"
      />

      <div class="flex justify-end gap-3">
        <Button variant="outline" @click="$emit('close')">{{ t('cancel') }}</Button>
        <Button :disabled="!canConfirm" @click="confirm">{{ t('start_import') }}</Button>
      </div>
    </div>
  </Modal>
</template>
