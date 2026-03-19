<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useHelp } from '@/lib/useHelp'

const props = defineProps({
  user: Object,
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

const { t } = useTranslations()
const { showHelp, toggleHelp } = useHelp()

const localeOptions = [
  { value: 'en', label: t('locale_en') },
  { value: 'fr', label: t('locale_fr') },
  { value: 'de', label: t('locale_de') },
  { value: 'it', label: t('locale_it') },
  { value: 'rm', label: t('locale_rm') },
]
</script>

<template>
  <AppLayout :title="t('profile')">
    <div class="max-w-2xl space-y-6">
      <Card>
        <CardHeader><CardTitle>{{ t('profile_information') }}</CardTitle></CardHeader>
        <CardContent>
          <form class="space-y-4" @submit.prevent="submitProfile">
            <FormInput id="name" v-model="profileForm.name" :label="t('name')" :error="profileForm.errors.name" required />
            <FormInput id="email" :model-value="user.email" :label="t('email')" disabled />
            <FormSelect
              id="locale"
              v-model="profileForm.locale"
              :label="t('language')"
              :options="localeOptions"
              :error="profileForm.errors.locale"
              required
            />
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

      <Card>
        <CardHeader><CardTitle>{{ t('change_password') }}</CardTitle></CardHeader>
        <CardContent>
          <form class="space-y-4" @submit.prevent="submitPassword">
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
    </div>
  </AppLayout>
</template>
