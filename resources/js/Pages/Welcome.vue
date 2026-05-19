<script setup>
import { Head, Link } from '@inertiajs/vue3'
import Button from '@/Components/UI/Button.vue'
import GuestBar from '@/Components/GuestBar.vue'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

defineProps({
  isSaas: {
    type: Boolean,
    default: false,
  },
})
</script>

<template>
  <Head :title="t('welcome')" />

  <GuestBar />

  <div class="flex min-h-screen flex-col items-center justify-center bg-[hsl(var(--muted))] p-6">
    <div class="w-full max-w-2xl text-center">
      <img src="/logo-wide.svg" alt="Gäld" class="mx-auto mb-8 h-16 w-auto" />

      <h1 class="mb-4 text-4xl font-bold tracking-tight">
        {{ t('welcome_to_gaeld') }}
      </h1>
      <p class="mb-10 text-lg text-[hsl(var(--muted-foreground))]">
        {{ t('welcome_tagline') }}
      </p>

      <div class="flex flex-col items-center justify-center gap-4 sm:flex-row">
        <Link href="/register">
          <Button size="lg">
            {{ t('get_started') }}
          </Button>
        </Link>
        <Link href="/login">
          <Button variant="outline" size="lg">
            {{ t('sign_in') }}
          </Button>
        </Link>
      </div>
    </div>
  </div>
</template>
