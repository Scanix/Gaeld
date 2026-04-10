<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'
import Alert from '@/Components/UI/Alert.vue'
import Button from '@/Components/UI/Button.vue'
import Card from '@/Components/UI/Card.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

defineProps({
  status: String,
})

const form = useForm({})

function resend() {
  form.post('/email/verification-notification')
}
</script>

<template>
  <Head :title="t('verify_email_title')" />

  <div class="flex min-h-screen items-center justify-center bg-[hsl(var(--muted))] p-6">
    <div class="w-full max-w-md">
      <div class="mb-8 text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))] text-xl font-bold">
          ✉
        </div>
        <h1 class="mt-4 text-2xl font-bold">{{ t('verify_email_title') }}</h1>
        <p class="mt-2 text-sm text-[hsl(var(--muted-foreground))]">
          {{ t('verify_email_description') }}
        </p>
      </div>

      <Card>
        <CardContent class="pt-6">
          <Alert v-if="status === 'verification-link-sent'" variant="success" class="mb-4">{{ t('verify_email_resent') }}</Alert>

          <p class="mb-4 text-sm text-[hsl(var(--muted-foreground))]">
            {{ t('verify_email_check_inbox') }}
          </p>

          <div class="flex items-center justify-between">
            <Button @click="resend" :disabled="form.processing">
              {{ t('verify_email_resend') }}
            </Button>

            <Link
              href="/logout"
              method="post"
              as="button"
              class="text-sm text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] underline"
            >
              {{ t('logout') }}
            </Link>
          </div>

          <p v-if="form.hasErrors" class="mt-3 text-sm text-[hsl(var(--destructive))]">
            {{ Object.values(form.errors).join(', ') }}
          </p>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
