<script setup>
import { ref, watch } from 'vue'
import FormInput from '@/Components/UI/FormInput.vue'
import Button from '@/Components/UI/Button.vue'
import { Wand2, ExternalLink, Loader2 } from 'lucide-vue-next'
import { useTranslations } from '@/lib/useTranslations'

const props = defineProps({
  id: { type: String, required: true },
  modelValue: { type: String, default: '' },
  iban: { type: String, default: '' },
  error: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useTranslations()
const loading = ref(false)
const lookupFailed = ref(false)

function getCsrfToken() {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

async function autoFill() {
  if (!props.iban) return
  loading.value = true
  lookupFailed.value = false
  try {
    const res = await fetch('/banking/bic-lookup', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
      credentials: 'same-origin',
      body: JSON.stringify({ iban: props.iban }),
    })
    if (!res.ok) {
      lookupFailed.value = true
      return
    }
    const data = await res.json()
    if (data.bic) {
      emit('update:modelValue', data.bic)
    } else {
      lookupFailed.value = true
    }
  } catch {
    lookupFailed.value = true
  } finally {
    loading.value = false
  }
}

watch(() => props.iban, () => {
  lookupFailed.value = false
})
</script>

<template>
  <div>
    <div class="flex items-end gap-2">
      <div class="flex-1">
        <FormInput
          :id="id"
          :model-value="modelValue"
          :label="t('bic_swift')"
          :placeholder="t('bic_placeholder')"
          :error="error"
          @update:model-value="emit('update:modelValue', $event)"
        />
      </div>
      <Button
        type="button"
        variant="outline"
        size="sm"
        :disabled="!iban || loading"
        :title="t('bic_autofill_from_iban')"
        @click="autoFill"
      >
        <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
        <Wand2 v-else class="h-4 w-4" />
      </Button>
    </div>
    <p v-if="lookupFailed" class="mt-1 flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
      <span>{{ t('bic_autofill_unknown') }}</span>
      <a
        href="https://www.six-group.com/en/products-services/banking-services/standardization/bank-master-data.html"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center gap-0.5 underline"
      >
        {{ t('bic_lookup_six') }}
        <ExternalLink class="h-3 w-3" />
      </a>
    </p>
  </div>
</template>
