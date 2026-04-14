<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import Card from '@/Components/UI/Card.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import { useTranslations } from '@/lib/useTranslations'
import { ref, computed } from 'vue'
import GuestBar from '@/Components/GuestBar.vue'
import { startAuthentication, browserSupportsWebAuthn } from '@simplewebauthn/browser'

const { t } = useTranslations()
const page = usePage()

const availableMethods = computed(() => page.props.availableMethods || [])
const hasTotp = computed(() => availableMethods.value.includes('totp'))
const hasPasskey = computed(() => availableMethods.value.includes('passkey') && browserSupportsWebAuthn())
const hasRecovery = computed(() => availableMethods.value.includes('recovery'))

// Default to first available method
const activeMethod = ref(hasTotp.value ? 'totp' : hasPasskey.value ? 'passkey' : 'recovery')

const form = useForm({
  code: '',
  recovery_code: '',
})

const passkeyLoading = ref(false)
const passkeyError = ref('')

function submit() {
  form.post('/two-factor-challenge', {
    onFinish: () => form.reset(),
  })
}

function switchMethod(method) {
  activeMethod.value = method
  form.code = ''
  form.recovery_code = ''
  form.clearErrors()
  passkeyError.value = ''
}

function getCsrfToken() {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

async function verifyWithPasskey() {
  passkeyError.value = ''
  passkeyLoading.value = true

  try {
    const optionsRes = await fetch('/two-factor-challenge/passkey/options', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
      credentials: 'same-origin',
    })

    if (!optionsRes.ok) {
      throw new Error(t('passkey_auth_options_failed'))
    }

    const options = await optionsRes.json()
    const assertion = await startAuthentication({ optionsJSON: options })

    const verifyRes = await fetch('/two-factor-challenge/passkey/verify', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
      credentials: 'same-origin',
      body: JSON.stringify(assertion),
    })

    if (!verifyRes.ok) {
      const data = await verifyRes.json()
      throw new Error(data.message || t('passkey_login_failed'))
    }

    const data = await verifyRes.json()
    window.location.href = data.redirect || '/'
  } catch (err) {
    if (err.name !== 'AbortError' && err.name !== 'NotAllowedError') {
      passkeyError.value = err.message || t('passkey_login_failed')
    }
  } finally {
    passkeyLoading.value = false
  }
}
</script>

<template>
  <Head :title="t('two_factor_challenge_title')" />

  <GuestBar />
  <div class="flex min-h-screen items-center justify-center bg-[hsl(var(--muted))] p-6">
    <div class="w-full max-w-md">
      <div class="mb-8 text-center">
        <img src="/logo-wide.svg" alt="Gäld" class="mx-auto h-14 w-auto mb-4" />
        <h1 class="text-2xl font-bold">{{ t('two_factor_challenge_title') }}</h1>
        <p class="mt-1 text-sm text-[hsl(var(--muted-foreground))]">
          {{ activeMethod === 'recovery' ? t('two_factor_recovery_desc') : activeMethod === 'passkey' ? t('two_factor_passkey_desc') : t('two_factor_challenge_desc') }}
        </p>
      </div>

      <Card>
        <CardContent class="pt-6">
          <!-- TOTP Code -->
          <form v-if="activeMethod === 'totp'" class="space-y-4" @submit.prevent="submit">
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
              {{ t('verify') }}
            </Button>
          </form>

          <!-- Recovery Code -->
          <form v-if="activeMethod === 'recovery'" class="space-y-4" @submit.prevent="submit">
            <FormInput
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

          <!-- Passkey -->
          <div v-if="activeMethod === 'passkey'" class="space-y-4">
            <div class="flex flex-col items-center gap-4 py-4">
              <div class="flex h-16 w-16 items-center justify-center rounded-full bg-[hsl(var(--muted))]">
                <svg class="h-8 w-8 text-[hsl(var(--muted-foreground))]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4" />
                </svg>
              </div>
              <p class="text-sm text-[hsl(var(--muted-foreground))] text-center">
                {{ t('two_factor_passkey_desc') }}
              </p>
            </div>
            <Button class="w-full" :disabled="passkeyLoading" @click="verifyWithPasskey">
              {{ t('use_passkey') }}
            </Button>
            <p v-if="passkeyError" class="text-sm text-[hsl(var(--destructive))] text-center">
              {{ passkeyError }}
            </p>
          </div>

          <!-- Method switcher -->
          <div class="mt-4 flex flex-col items-center gap-1">
            <button
              v-if="activeMethod !== 'totp' && hasTotp"
              type="button"
              class="text-sm text-[hsl(var(--primary))] hover:underline"
              @click="switchMethod('totp')"
            >
              {{ t('use_auth_code') }}
            </button>
            <button
              v-if="activeMethod !== 'passkey' && hasPasskey"
              type="button"
              class="text-sm text-[hsl(var(--primary))] hover:underline"
              @click="switchMethod('passkey')"
            >
              {{ t('use_passkey') }}
            </button>
            <button
              v-if="activeMethod !== 'recovery' && hasRecovery"
              type="button"
              class="text-sm text-[hsl(var(--primary))] hover:underline"
              @click="switchMethod('recovery')"
            >
              {{ t('use_recovery_code') }}
            </button>
          </div>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
