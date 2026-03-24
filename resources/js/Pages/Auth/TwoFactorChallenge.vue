<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import Card from '@/Components/UI/Card.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import { useTranslations } from '@/lib/useTranslations'
import { ref } from 'vue'

const { t } = useTranslations()

const useRecovery = ref(false)

const form = useForm({
  code: '',
  recovery_code: '',
})

function submit() {
  form.post('/two-factor-challenge', {
    onFinish: () => form.reset(),
  })
}

function toggleMode() {
  useRecovery.value = !useRecovery.value
  form.code = ''
  form.recovery_code = ''
  form.clearErrors()
}
</script>

<template>
  <Head :title="t('two_factor_challenge_title')" />

  <div class="flex min-h-screen items-center justify-center bg-[hsl(var(--muted))] p-6">
    <div class="w-full max-w-md">
      <div class="mb-8 text-center">
        <img src="/logo-wide.svg" alt="Gäld" class="mx-auto h-14 w-auto mb-4" />
        <h1 class="text-2xl font-bold">{{ t('two_factor_challenge_title') }}</h1>
        <p class="mt-1 text-sm text-[hsl(var(--muted-foreground))]">
          {{ useRecovery ? t('two_factor_recovery_desc') : t('two_factor_challenge_desc') }}
        </p>
      </div>

      <Card>
        <CardContent class="pt-6">
          <form class="space-y-4" @submit.prevent="submit">
            <FormInput
              v-if="!useRecovery"
              id="code"
              v-model="form.code"
              :label="t('two_factor_code')"
              inputmode="numeric"
              autocomplete="one-time-code"
              maxlength="6"
              :error="form.errors.code"
              autofocus
              required
            />

            <FormInput
              v-else
              id="recovery_code"
              v-model="form.recovery_code"
              :label="t('recovery_code')"
              :error="form.errors.recovery_code"
              autofocus
              required
            />

            <Button type="submit" class="w-full" :disabled="form.processing">
              {{ t('verify') }}
            </Button>
          </form>

          <div class="mt-4 text-center">
            <button
              type="button"
              class="text-sm text-[hsl(var(--primary))] hover:underline"
              @click="toggleMode"
            >
              {{ useRecovery ? t('use_auth_code') : t('use_recovery_code') }}
            </button>
          </div>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
