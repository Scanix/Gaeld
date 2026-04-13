<script setup>
import { Head, useForm, Link, usePage } from '@inertiajs/vue3'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import { useTranslations } from '@/lib/useTranslations'
import { computed } from 'vue'

const { t } = useTranslations()
const page = usePage()
const isSaas = computed(() => page.props.features?.saas ?? false)

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

function submit() {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  })
}
</script>

<template>
  <Head :title="t('sign_in')" />

  <div class="flex min-h-screen items-center justify-center bg-[hsl(var(--muted))] p-6">
    <div class="w-full max-w-md">
      <div class="mb-8 text-center">
        <img src="/logo-wide.svg" alt="Gäld" class="mx-auto h-14 w-auto mb-4" />
        <h1 class="text-2xl font-bold">{{ t('welcome') }}</h1>
        <p class="mt-1 text-sm text-[hsl(var(--muted-foreground))]">{{ t('sign_in_account') }}</p>
      </div>

      <Card>
        <CardContent class="pt-6">
          <form class="space-y-4" @submit.prevent="submit">
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
              autocomplete="current-password"
              required
            />

            <div class="flex items-center justify-between">
              <label class="flex items-center gap-2 text-sm">
                <input id="remember" v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-[hsl(var(--input))]">
                {{ t('remember_me') }}
              </label>
              <Link href="/forgot-password" class="text-sm text-[hsl(var(--primary))] hover:underline">
                {{ t('forgot_password') }}
              </Link>
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing" :loading="form.processing">
              {{ t('sign_in') }}
            </Button>
          </form>
        </CardContent>
      </Card>

      <p class="mt-4 text-center text-sm text-[hsl(var(--muted-foreground))]">
        {{ t('no_account') }}
        <Link :href="isSaas ? '/signup' : '/register'" class="font-medium text-[hsl(var(--primary))] hover:underline">
          {{ t('create_one') }}
        </Link>
      </p>
    </div>
  </div>
</template>