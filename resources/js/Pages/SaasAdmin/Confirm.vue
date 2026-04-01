<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import Card from '@/Components/UI/Card.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import { useTranslations } from '@/lib/useTranslations'
import { ShieldCheck } from 'lucide-vue-next'

const { t } = useTranslations()

const form = useForm({
  code: '',
})

function submit() {
  form.post('/saas-admin/confirm', {
    onFinish: () => form.reset(),
  })
}
</script>

<template>
  <Head :title="t('saas_admin')" />

  <div class="flex min-h-screen items-center justify-center bg-[hsl(var(--muted))] p-6">
    <div class="w-full max-w-md">
      <div class="mb-8 text-center">
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[hsl(var(--primary)/0.1)]">
          <ShieldCheck class="h-8 w-8 text-[hsl(var(--primary))]" />
        </div>
        <h1 class="text-2xl font-bold">{{ t('saas_admin_confirm_title') }}</h1>
        <p class="mt-2 text-sm text-[hsl(var(--muted-foreground))]">
          {{ t('saas_admin_confirm_desc') }}
        </p>
      </div>

      <Card>
        <CardContent class="pt-6">
          <form class="space-y-6" @submit.prevent="submit">
            <FormInput
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

            <Button type="submit" class="w-full" :disabled="form.processing">
              {{ t('verify_and_continue') }}
            </Button>
          </form>

          <div class="mt-4 text-center">
            <a
              href="/"
              class="text-sm text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] hover:underline"
            >
              {{ t('back_to_dashboard') }}
            </a>
          </div>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
