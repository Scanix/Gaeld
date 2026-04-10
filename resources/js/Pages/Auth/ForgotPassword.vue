<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'
import Alert from '@/Components/UI/Alert.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import Card from '@/Components/UI/Card.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

defineProps({
  status: String,
})

const form = useForm({
  email: '',
})

function submit() {
  form.post('/forgot-password')
}
</script>

<template>
  <Head :title="t('forgot_password')" />

  <div class="flex min-h-screen items-center justify-center bg-[hsl(var(--muted))] p-6">
    <div class="w-full max-w-md">
      <div class="mb-8 text-center">
        <h1 class="text-2xl font-bold">{{ t('reset_password') }}</h1>
        <p class="mt-1 text-sm text-[hsl(var(--muted-foreground))]">
          {{ t('reset_password_desc') }}
        </p>
      </div>

      <Card>
        <CardContent class="pt-6">
          <Alert v-if="status" variant="success" class="mb-4">{{ status }}</Alert>

          <form class="space-y-4" @submit.prevent="submit">
            <FormInput
              id="email"
              v-model="form.email"
              type="email"
              :label="t('email')"
              :error="form.errors.email"
              required
            />

            <Button type="submit" class="w-full" :disabled="form.processing">
              {{ t('send_reset_link') }}
            </Button>
          </form>
        </CardContent>
      </Card>

      <p class="mt-4 text-center text-sm text-[hsl(var(--muted-foreground))]">
        <Link href="/login" class="font-medium text-[hsl(var(--primary))] hover:underline">
          {{ t('back_to_login') }}
        </Link>
      </p>
    </div>
  </div>
</template>
