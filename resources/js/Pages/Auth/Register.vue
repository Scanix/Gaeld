<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import Card from '@/Components/UI/Card.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

function submit() {
  form.post('/register', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <Head title="Register" />

  <div class="flex min-h-screen items-center justify-center bg-[hsl(var(--muted))] p-6">
    <div class="w-full max-w-md">
      <div class="mb-8 text-center">
        <img src="/logo-wide.svg" alt="Gäld" class="mx-auto h-14 w-auto mb-4" />
        <h1 class="text-2xl font-bold">{{ t('create_account') }}</h1>
        <p class="mt-1 text-sm text-[hsl(var(--muted-foreground))]">{{ t('start_managing') }}</p>
      </div>

      <Card>
        <CardContent class="pt-6">
          <form class="space-y-4" @submit.prevent="submit">
            <FormInput
              id="name"
              v-model="form.name"
              :label="t('full_name')"
              placeholder="Max Muster"
              :error="form.errors.name"
              required
            />

            <FormInput
              id="email"
              v-model="form.email"
              type="email"
              :label="t('email')"
              placeholder="you@example.com"
              :error="form.errors.email"
              required
            />

            <FormInput
              id="password"
              v-model="form.password"
              type="password"
              :label="t('password')"
              :error="form.errors.password"
              required
            />

            <FormInput
              id="password_confirmation"
              v-model="form.password_confirmation"
              type="password"
              :label="t('confirm_password')"
              required
            />

            <Button type="submit" class="w-full" :disabled="form.processing">
              {{ t('create_account_btn') }}
            </Button>
          </form>
        </CardContent>
      </Card>

      <p class="mt-4 text-center text-sm text-[hsl(var(--muted-foreground))]">
        {{ t('have_account') }}
        <Link href="/login" class="font-medium text-[hsl(var(--primary))] hover:underline">
          {{ t('sign_in') }}
        </Link>
      </p>
    </div>
  </div>
</template>
