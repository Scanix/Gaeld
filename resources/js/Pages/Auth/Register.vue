<script setup>
import { Head, useForm, Link, usePage } from '@inertiajs/vue3'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import PasswordStrength from '@/Components/UI/PasswordStrength.vue'
import Card from '@/Components/UI/Card.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import { useTranslations } from '@/lib/useTranslations'
import { computed } from 'vue'
import GuestBar from '@/Components/GuestBar.vue'

const { t } = useTranslations()
const page = usePage()
const isSaas = computed(() => page.props.features?.saas ?? false)

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  accepted_privacy: false,
})

const passwordConfirmationError = computed(() => {
  if (form.password_confirmation && form.password !== form.password_confirmation) {
    return t('passwords_do_not_match')
  }
  return form.errors.password_confirmation
})

function submit() {
  form.post('/register', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <Head :title="t('create_account')" />

  <GuestBar />
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
              :placeholder="t('placeholder_email')"
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

            <PasswordStrength :password="form.password" />

            <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('password_requirements_hint') }}</p>

            <FormInput
              id="password_confirmation"
              v-model="form.password_confirmation"
              type="password"
              :label="t('confirm_password')"
              :error="passwordConfirmationError"
              required
            />

            <div v-if="isSaas" class="space-y-1">
              <label class="flex items-start gap-2">
                <input
                  id="accepted_privacy"
                  v-model="form.accepted_privacy"
                  type="checkbox"
                  class="mt-1 h-4 w-4 rounded border-[hsl(var(--input))] text-[hsl(var(--primary))] focus:ring-[hsl(var(--ring))]"
                />
                <span class="text-sm text-[hsl(var(--muted-foreground))]">
                  {{ t('accept_privacy_prefix') }}
                  <a href="https://gaeld.ch/privacy" target="_blank" class="font-medium text-[hsl(var(--primary))] hover:underline">{{ t('privacy_policy') }}</a>
                  {{ t('and') }}
                  <a href="https://gaeld.ch/terms" target="_blank" class="font-medium text-[hsl(var(--primary))] hover:underline">{{ t('terms_of_service') }}</a>.
                </span>
              </label>
              <p v-if="form.errors.accepted_privacy" class="text-sm text-[hsl(var(--destructive))]">{{ form.errors.accepted_privacy }}</p>
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing" :loading="form.processing">
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

      <p v-if="isSaas" class="mt-2 text-center text-sm text-[hsl(var(--muted-foreground))]">
        {{ t('want_trial') }}
        <Link href="/signup" class="font-medium text-[hsl(var(--primary))] hover:underline">
          {{ t('start_free_trial') }}
        </Link>
      </p>
    </div>
  </div>
</template>
