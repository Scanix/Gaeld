<script setup>
import { useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Alert from '@/Components/UI/Alert.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useHelp } from '@/lib/useHelp'
import { ref, onMounted, computed } from 'vue'
import { startRegistration, browserSupportsWebAuthn } from '@simplewebauthn/browser'

const props = defineProps({
  user: Object,
})

const page = usePage()
const flash = computed(() => page.props.flash || {})
const twoFactorFlash = computed(() => {
  const session = page.props.flash
  // twoFactor data is passed via session flash
  return page.props.twoFactor || null
})

const profileForm = useForm({
  name: props.user.name,
  locale: props.user.locale || 'en',
})

const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
})

function submitProfile() {
  profileForm.put('/profile', {
    preserveScroll: true,
  })
}

function submitPassword() {
  passwordForm.put('/profile/password', {
    preserveScroll: true,
    onSuccess: () => passwordForm.reset(),
  })
}

// --- Email change ---
const showEmailChange = ref(false)
const emailForm = useForm({
  email: '',
  current_password: '',
})

function submitEmailChange() {
  emailForm.put('/profile/email', {
    preserveScroll: true,
    onSuccess: () => {
      emailForm.reset()
      showEmailChange.value = false
    },
  })
}

function cancelEmailChange() {
  router.delete('/profile/email', { preserveScroll: true })
}

function resetOnboarding() {
  router.post('/profile/onboarding/reset', {}, { preserveScroll: true })
}

const { t } = useTranslations()
const { showHelp, toggleHelp } = useHelp()

const localeOptions = [
  { value: 'en', label: t('locale_en') },
  { value: 'fr', label: t('locale_fr') },
  { value: 'de', label: t('locale_de') },
  { value: 'it', label: t('locale_it') },
]

// --- Two-Factor Authentication ---
const twoFactorEnabled = computed(() => page.props.auth?.user?.two_factor_enabled)
const orgRequiresTwoFactor = computed(() => page.props.auth?.currentOrganization?.require_two_factor)
const showQrSetup = ref(false)
const qrSvg = ref('')
const setupSecret = ref('')
const recoveryCodes = ref(null)

const confirmForm = useForm({ code: '' })
const disableForm = useForm({ current_password: '' })
const recoveryPasswordForm = useForm({ current_password: '' })

function enableTwoFactor() {
  router.post('/profile/two-factor', {}, {
    preserveScroll: true,
    onSuccess: (page) => {
      const tf = page.props?.twoFactor
      if (tf?.qrSvg) {
        qrSvg.value = tf.qrSvg
        setupSecret.value = tf.secret || ''
        showQrSetup.value = true
      }
    },
  })
}

function confirmTwoFactor() {
  confirmForm.post('/profile/two-factor/confirm', {
    preserveScroll: true,
    onSuccess: (page) => {
      const tf = page.props?.twoFactor
      if (tf?.recoveryCodes) {
        recoveryCodes.value = tf.recoveryCodes
        showQrSetup.value = false
        confirmForm.reset()
      }
    },
  })
}

function disableTwoFactor() {
  disableForm.delete('/profile/two-factor', {
    preserveScroll: true,
    onSuccess: () => {
      disableForm.reset()
      recoveryCodes.value = null
      showQrSetup.value = false
    },
  })
}

function showRecoveryCodes() {
  recoveryPasswordForm.post('/profile/two-factor/recovery-codes', {
    preserveScroll: true,
    onSuccess: (page) => {
      const tf = page.props?.twoFactor
      if (tf?.recoveryCodes) {
        recoveryCodes.value = tf.recoveryCodes
        recoveryPasswordForm.reset()
      }
    },
  })
}

function regenerateRecoveryCodes() {
  recoveryPasswordForm.post('/profile/two-factor/recovery-codes/regenerate', {
    preserveScroll: true,
    onSuccess: (page) => {
      const tf = page.props?.twoFactor
      if (tf?.recoveryCodes) {
        recoveryCodes.value = tf.recoveryCodes
        recoveryPasswordForm.reset()
      }
    },
  })
}

// --- Passkeys ---
const supportsPasskeys = browserSupportsWebAuthn()
const passkeys = ref([])
const passkeyLoading = ref(false)
const passkeyError = ref('')
const deletePasskeyForm = useForm({ current_password: '' })
const deletingPasskeyId = ref(null)

// --- Data export & Account deletion ---
const exportLoading = ref(false)
const showDeleteConfirm = ref(false)
const deleteAccountForm = useForm({ current_password: '' })

function exportData() {
  exportLoading.value = true
  router.post('/profile/export', {}, {
    preserveScroll: true,
    onFinish: () => { exportLoading.value = false },
  })
}

function confirmDeleteAccount() {
  deleteAccountForm.delete('/profile', {
    preserveScroll: true,
    onSuccess: () => {
      showDeleteConfirm.value = false
    },
  })
}

async function loadPasskeys() {
  try {
    const res = await fetch('/profile/passkeys', {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
    })
    if (res.ok) {
      passkeys.value = await res.json()
    }
  } catch {
    // silently fail
  }
}

onMounted(() => {
  if (supportsPasskeys) {
    loadPasskeys()
  }
})

function getCsrfToken() {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

async function registerPasskey() {
  passkeyError.value = ''
  passkeyLoading.value = true

  try {
    const optionsRes = await fetch('/profile/passkeys/register/options', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
      credentials: 'same-origin',
    })

    if (!optionsRes.ok) throw new Error(t('passkey_register_options_failed'))

    const options = await optionsRes.json()
    const attestation = await startRegistration({ optionsJSON: options })

    const registerRes = await fetch('/profile/passkeys/register', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
      credentials: 'same-origin',
      body: JSON.stringify(attestation),
    })

    if (!registerRes.ok) throw new Error(t('passkey_register_failed'))

    await loadPasskeys()
  } catch (err) {
    if (err.name !== 'AbortError' && err.name !== 'NotAllowedError') {
      passkeyError.value = err.message || t('passkey_register_failed')
    }
  } finally {
    passkeyLoading.value = false
  }
}

function startDeletePasskey(id) {
  deletingPasskeyId.value = id
  deletePasskeyForm.reset()
}

function confirmDeletePasskey() {
  deletePasskeyForm.delete(`/profile/passkeys/${deletingPasskeyId.value}`, {
    preserveScroll: true,
    onSuccess: () => {
      deletingPasskeyId.value = null
      deletePasskeyForm.reset()
      loadPasskeys()
    },
  })
}

const notificationPrefsForm = useForm({
  notification_preferences: {
    ocr_email: page.props.auth?.user?.notification_preferences?.ocr_email ?? true,
  },
})

function submitNotificationPrefs() {
  notificationPrefsForm.put('/profile/notification-preferences', {
    preserveScroll: true,
  })
}

// --- Active Sessions ---
const deviceSessions = ref([])
const revokeSessionForm = useForm({ current_password: '' })
const revokingSessionId = ref(null)
const revokeAllForm = useForm({ current_password: '' })
const showRevokeAll = ref(false)

async function loadSessions() {
  try {
    const res = await fetch('/profile/sessions', {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
    })
    if (res.ok) {
      deviceSessions.value = await res.json()
    }
  } catch {
    // silently fail
  }
}

onMounted(() => {
  loadSessions()
})

function startRevokeSession(id) {
  revokingSessionId.value = id
  revokeSessionForm.reset()
}

function confirmRevokeSession() {
  revokeSessionForm.delete(`/profile/sessions/${revokingSessionId.value}`, {
    preserveScroll: true,
    onSuccess: () => {
      revokingSessionId.value = null
      revokeSessionForm.reset()
      loadSessions()
    },
  })
}

function confirmRevokeOtherSessions() {
  revokeAllForm.delete('/profile/sessions', {
    preserveScroll: true,
    onSuccess: () => {
      showRevokeAll.value = false
      revokeAllForm.reset()
      loadSessions()
    },
  })
}
</script>

<template>
  <AppLayout :title="t('profile')">
    <div class="max-w-2xl space-y-6">
      <Card>
        <CardHeader><CardTitle>{{ t('profile_information') }}</CardTitle></CardHeader>
        <CardContent>
          <form class="space-y-6" @submit.prevent="submitProfile">
            <FormInput id="name" v-model="profileForm.name" :label="t('name')" :error="profileForm.errors.name" required />
            <div>
              <div class="flex items-center gap-2">
                <FormInput id="email" :model-value="user.email" :label="t('email')" disabled class="flex-1" />
                <Button v-if="!showEmailChange" type="button" variant="outline" size="sm" class="mt-5" @click="showEmailChange = true">
                  {{ t('change_email') }}
                </Button>
              </div>
              <div v-if="user.pending_email" class="mt-1 flex items-center gap-2">
                <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('pending_email_change', { email: user.pending_email }) }}</p>
                <button type="button" class="text-xs text-[hsl(var(--destructive))] underline" @click="cancelEmailChange">
                  {{ t('cancel_email_change') }}
                </button>
              </div>
              <div v-if="showEmailChange" class="mt-3 space-y-3 rounded-md border border-[hsl(var(--border))] p-3">
                <FormInput id="new_email" v-model="emailForm.email" :label="t('new_email')" type="email" :error="emailForm.errors.email" required />
                <FormInput id="email_password" v-model="emailForm.current_password" :label="t('current_password')" type="password" :error="emailForm.errors.current_password" required />
                <div class="flex gap-2 justify-end">
                  <Button type="button" variant="outline" size="sm" @click="showEmailChange = false">{{ t('cancel') }}</Button>
                  <Button type="button" size="sm" :disabled="emailForm.processing" @click="submitEmailChange">{{ t('change_email') }}</Button>
                </div>
              </div>
            </div>
            <FormSelect
              id="locale"
              v-model="profileForm.locale"
              :label="t('language')"
              :options="localeOptions"
              :error="profileForm.errors.locale"
              required
            />
            <p class="mt-1 text-xs text-[hsl(var(--muted-foreground))]">{{ t('user_locale_hint') }}</p>
            <div class="flex justify-end">
              <Button type="submit" :disabled="profileForm.processing">{{ t('save') }}</Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{{ t('help_preferences') }}</CardTitle>
          <CardDescription>{{ t('help_preferences_desc') }}</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('show_help_label') }}</p>
              <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('show_help_desc') }}</p>
            </div>
            <button
              type="button"
              role="switch"
              :aria-checked="showHelp"
              class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[hsl(var(--ring))] focus-visible:ring-offset-2"
              :class="showHelp ? 'bg-[hsl(var(--primary))]' : 'bg-[hsl(var(--input))]'"
              @click="toggleHelp"
            >
              <span
                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                :class="showHelp ? 'translate-x-5' : 'translate-x-0'"
              />
            </button>
          </div>
        </CardContent>
      </Card>

      <!-- Reset Onboarding -->
      <Card v-if="props.user.onboarding_completed_at">
        <CardHeader>
          <CardTitle>{{ t('onboarding_reset_title') }}</CardTitle>
          <CardDescription>{{ t('onboarding_reset_desc') }}</CardDescription>
        </CardHeader>
        <CardContent>
          <Button variant="outline" @click="resetOnboarding">{{ t('reset_onboarding') }}</Button>
        </CardContent>
      </Card>

      <!-- Two-Factor Authentication -->
      <Card>
        <CardHeader>
          <div class="flex items-center justify-between">
            <div>
              <CardTitle>{{ t('two_factor_authentication') }}</CardTitle>
              <CardDescription>{{ t('two_factor_description') }}</CardDescription>
            </div>
            <Badge v-if="twoFactorEnabled" variant="success">{{ t('enabled') }}</Badge>
          </div>
        </CardHeader>
        <CardContent>
          <!-- Org enforcement banner -->
          <Alert v-if="orgRequiresTwoFactor && !twoFactorEnabled" variant="warning" class="mb-4">{{ t('two_factor_required_by_org') }}</Alert>

          <!-- Not enabled: show Enable button -->
          <div v-if="!twoFactorEnabled && !showQrSetup">
            <Button @click="enableTwoFactor">{{ t('enable_two_factor') }}</Button>
          </div>

          <!-- QR Setup: show QR code + confirmation input -->
          <div v-if="showQrSetup" class="space-y-4">
            <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('scan_qr_code') }}</p>
            <div class="flex justify-center" v-html="qrSvg"></div>
            <div v-if="setupSecret" class="text-center">
              <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('manual_entry_key') }}</p>
              <code class="mt-1 block select-all rounded bg-[hsl(var(--muted))] px-3 py-2 font-mono text-sm">{{ setupSecret }}</code>
            </div>
            <form class="space-y-3" @submit.prevent="confirmTwoFactor">
              <FormInput
                id="two_factor_code"
                v-model="confirmForm.code"
                :label="t('two_factor_code')"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                :error="confirmForm.errors.code"
                required
              />
              <Button type="submit" :disabled="confirmForm.processing">{{ t('confirm_code') }}</Button>
            </form>
          </div>

          <!-- Enabled: show recovery codes + disable -->
          <div v-if="twoFactorEnabled && !showQrSetup" class="space-y-4">
            <div v-if="recoveryCodes" class="space-y-2">
              <p class="text-sm font-medium">{{ t('recovery_codes') }}</p>
              <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('recovery_codes_desc') }}</p>
              <div class="grid grid-cols-2 gap-2 rounded-md bg-[hsl(var(--muted))] p-4 font-mono text-sm">
                <span v-for="code in recoveryCodes" :key="code">{{ code }}</span>
              </div>
            </div>

            <div class="flex flex-wrap gap-2">
              <form v-if="!recoveryCodes" class="flex items-end gap-2" @submit.prevent="showRecoveryCodes">
                <FormInput
                  id="recovery_password"
                  v-model="recoveryPasswordForm.current_password"
                  type="password"
                  :label="t('current_password')"
                  :error="recoveryPasswordForm.errors.current_password"
                  required
                />
                <Button type="submit" variant="outline" :disabled="recoveryPasswordForm.processing">
                  {{ t('show_recovery_codes') }}
                </Button>
              </form>

              <Button v-if="recoveryCodes" variant="outline" @click="regenerateRecoveryCodes">
                {{ t('regenerate_recovery_codes') }}
              </Button>
            </div>

            <div class="border-t border-[hsl(var(--border))] pt-4">
              <form class="flex items-end gap-2" @submit.prevent="disableTwoFactor">
                <FormInput
                  id="disable_password"
                  v-model="disableForm.current_password"
                  type="password"
                  :label="t('current_password')"
                  :error="disableForm.errors.current_password"
                  required
                />
                <Button type="submit" variant="destructive" :disabled="disableForm.processing">
                  {{ t('disable_two_factor') }}
                </Button>
              </form>
            </div>
          </div>

          <!-- Passkeys (part of 2FA) -->
          <div v-if="supportsPasskeys" class="mt-6 border-t border-[hsl(var(--border))] pt-6 space-y-4">
            <div>
              <p class="text-sm font-semibold">{{ t('passkeys') }}</p>
              <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('passkeys_description') }}</p>
              <p class="text-xs text-[hsl(var(--muted-foreground))] mt-1">{{ t('passkeys_2fa_hint') }}</p>
            </div>
            <div v-if="passkeys.length > 0" class="space-y-2">
              <div
                v-for="pk in passkeys"
                :key="pk.id"
                class="flex items-center justify-between rounded-md border border-[hsl(var(--border))] p-3"
              >
                <div>
                  <p class="text-sm font-medium">{{ pk.name }}</p>
                  <p class="text-xs text-[hsl(var(--muted-foreground))]">
                    {{ t('created') }}: {{ pk.created_at }}
                  </p>
                </div>
                <Button
                  v-if="deletingPasskeyId !== pk.id"
                  variant="ghost"
                  size="sm"
                  @click="startDeletePasskey(pk.id)"
                >
                  {{ t('delete') }}
                </Button>
                <form
                  v-else
                  class="flex items-center gap-2"
                  @submit.prevent="confirmDeletePasskey"
                >
                  <FormInput
                    :id="'delete_pk_' + pk.id"
                    v-model="deletePasskeyForm.current_password"
                    type="password"
                    :placeholder="t('current_password')"
                    :error="deletePasskeyForm.errors.current_password"
                    class="w-40"
                    required
                  />
                  <Button type="submit" variant="destructive" size="sm" :disabled="deletePasskeyForm.processing">
                    {{ t('confirm') }}
                  </Button>
                  <Button type="button" variant="ghost" size="sm" @click="deletingPasskeyId = null">
                    {{ t('cancel') }}
                  </Button>
                </form>
              </div>
            </div>
            <p v-else class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('no_passkeys') }}</p>

            <div>
              <Button :disabled="passkeyLoading" @click="registerPasskey">
                {{ t('register_passkey') }}
              </Button>
              <p v-if="passkeyError" class="mt-2 text-sm text-[hsl(var(--destructive))]">{{ passkeyError }}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Active Sessions -->
      <Card>
        <CardHeader>
          <CardTitle>{{ t('active_sessions') }}</CardTitle>
          <CardDescription>{{ t('active_sessions_desc') }}</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="space-y-3">
            <div
              v-for="session in deviceSessions"
              :key="session.id"
              class="flex items-center justify-between rounded-md border border-[hsl(var(--border))] p-3"
            >
              <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[hsl(var(--muted))]">
                  <svg v-if="session.is_mobile" class="h-5 w-5 text-[hsl(var(--muted-foreground))]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                  </svg>
                  <svg v-else class="h-5 w-5 text-[hsl(var(--muted-foreground))]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                  </svg>
                </div>
                <div>
                  <div class="flex items-center gap-2">
                    <p class="text-sm font-medium">{{ session.device_name }}</p>
                    <Badge v-if="session.is_current" variant="success" class="text-xs">{{ t('this_device') }}</Badge>
                  </div>
                  <p class="text-xs text-[hsl(var(--muted-foreground))]">
                    {{ session.ip_address }} · {{ t('last_active') }}: {{ session.last_active_at }}
                  </p>
                </div>
              </div>
              <template v-if="!session.is_current">
                <Button
                  v-if="revokingSessionId !== session.id"
                  variant="ghost"
                  size="sm"
                  @click="startRevokeSession(session.id)"
                >
                  {{ t('revoke_session') }}
                </Button>
                <form
                  v-else
                  class="flex items-center gap-2"
                  @submit.prevent="confirmRevokeSession"
                >
                  <FormInput
                    :id="'revoke_' + session.id"
                    v-model="revokeSessionForm.current_password"
                    type="password"
                    :placeholder="t('current_password')"
                    :error="revokeSessionForm.errors.current_password"
                    class="w-40"
                    required
                  />
                  <Button type="submit" variant="destructive" size="sm" :disabled="revokeSessionForm.processing">
                    {{ t('confirm') }}
                  </Button>
                  <Button type="button" variant="ghost" size="sm" @click="revokingSessionId = null">
                    {{ t('cancel') }}
                  </Button>
                </form>
              </template>
            </div>

            <p v-if="deviceSessions.length === 0" class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('no_other_sessions') }}</p>

            <div v-if="deviceSessions.filter(s => !s.is_current).length > 0" class="border-t border-[hsl(var(--border))] pt-4">
              <div v-if="!showRevokeAll">
                <Button variant="outline" @click="showRevokeAll = true">
                  {{ t('revoke_other_sessions') }}
                </Button>
              </div>
              <form v-else class="flex items-end gap-2" @submit.prevent="confirmRevokeOtherSessions">
                <FormInput
                  id="revoke_all_password"
                  v-model="revokeAllForm.current_password"
                  type="password"
                  :label="t('current_password')"
                  :error="revokeAllForm.errors.current_password"
                  required
                />
                <Button type="submit" variant="destructive" :disabled="revokeAllForm.processing">
                  {{ t('revoke_other_sessions') }}
                </Button>
                <Button type="button" variant="ghost" @click="showRevokeAll = false">
                  {{ t('cancel') }}
                </Button>
              </form>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>{{ t('change_password') }}</CardTitle></CardHeader>
        <CardContent>
          <form class="space-y-6" @submit.prevent="submitPassword">
            <FormInput
              id="current_password"
              v-model="passwordForm.current_password"
              :label="t('current_password')"
              type="password"
              :error="passwordForm.errors.current_password"
              required
            />
            <FormInput
              id="password"
              v-model="passwordForm.password"
              :label="t('new_password')"
              type="password"
              :error="passwordForm.errors.password"
              required
            />
            <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('password_requirements_hint') }}</p>
            <FormInput
              id="password_confirmation"
              v-model="passwordForm.password_confirmation"
              :label="t('confirm_new_password')"
              type="password"
              required
            />
            <div class="flex justify-end">
              <Button type="submit" :disabled="passwordForm.processing">{{ t('update_password') }}</Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <!-- Notification Preferences -->
      <Card>
        <CardHeader>
          <CardTitle>{{ t('notification_prefs_title') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <form class="space-y-4" @submit.prevent="submitNotificationPrefs">
            <label class="flex cursor-pointer items-center justify-between gap-4">
              <span class="text-sm text-[hsl(var(--foreground))]">{{ t('notification_prefs_ocr_email') }}</span>
              <input
                v-model="notificationPrefsForm.notification_preferences.ocr_email"
                type="checkbox"
                class="h-4 w-4 rounded border-[hsl(var(--border))] accent-[hsl(var(--primary))]"
              />
            </label>
            <div class="flex justify-end">
              <Button type="submit" :disabled="notificationPrefsForm.processing">
                {{ t('save') }}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <!-- Data & Privacy -->
      <Card>
        <CardHeader>
          <CardTitle>{{ t('data_privacy') }}</CardTitle>
          <CardDescription>{{ t('data_privacy_desc') }}</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="space-y-6">
            <div class="flex items-start justify-between">
              <div>
                <p class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('export_my_data') }}</p>
                <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('export_data_desc') }}</p>
              </div>
              <Button variant="outline" :disabled="exportLoading" @click="exportData">
                {{ exportLoading ? t('exporting') : t('export_my_data') }}
              </Button>
            </div>

            <div class="border-t border-[hsl(var(--border))] pt-4">
              <p class="text-sm font-medium text-[hsl(var(--destructive))]">{{ t('danger_zone') }}</p>
              <p class="mt-1 text-sm text-[hsl(var(--muted-foreground))]">{{ t('delete_account_desc') }}</p>

              <div v-if="!showDeleteConfirm" class="mt-3">
                <Button variant="destructive" @click="showDeleteConfirm = true">
                  {{ t('delete_account') }}
                </Button>
              </div>

              <div v-else class="mt-3 space-y-3 rounded-md border border-[hsl(var(--destructive))] p-4">
                <p class="text-sm font-medium text-[hsl(var(--destructive))]">{{ t('delete_account_confirm') }}</p>
                <form class="flex items-end gap-2" @submit.prevent="confirmDeleteAccount">
                  <FormInput
                    id="delete_account_password"
                    v-model="deleteAccountForm.current_password"
                    type="password"
                    :label="t('current_password')"
                    :error="deleteAccountForm.errors.current_password"
                    required
                  />
                  <Button type="submit" variant="destructive" :disabled="deleteAccountForm.processing">
                    {{ deleteAccountForm.processing ? t('deleting') : t('delete_account') }}
                  </Button>
                  <Button type="button" variant="ghost" @click="showDeleteConfirm = false">
                    {{ t('cancel') }}
                  </Button>
                </form>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
