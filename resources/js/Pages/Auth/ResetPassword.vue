<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import Card from '@/Components/UI/Card.vue'
import CardContent from '@/Components/UI/CardContent.vue'

const props = defineProps({
  email: String,
  token: String,
})

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
})

function submit() {
  form.post('/reset-password', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <Head title="Reset Password" />

  <div class="flex min-h-screen items-center justify-center bg-[hsl(var(--muted))] p-6">
    <div class="w-full max-w-md">
      <div class="mb-8 text-center">
        <h1 class="text-2xl font-bold">Set new password</h1>
      </div>

      <Card>
        <CardContent class="pt-6">
          <form class="space-y-4" @submit.prevent="submit">
            <FormInput
              id="email"
              v-model="form.email"
              type="email"
              label="Email"
              :error="form.errors.email"
              required
            />

            <FormInput
              id="password"
              v-model="form.password"
              type="password"
              label="New Password"
              :error="form.errors.password"
              required
            />

            <FormInput
              id="password_confirmation"
              v-model="form.password_confirmation"
              type="password"
              label="Confirm Password"
              required
            />

            <Button type="submit" class="w-full" :disabled="form.processing">
              Reset password
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
