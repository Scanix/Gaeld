<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import Card from '@/Components/UI/Card.vue'
import CardContent from '@/Components/UI/CardContent.vue'

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
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))] text-xl font-bold">
          G
        </div>
        <h1 class="mt-4 text-2xl font-bold">Create your account</h1>
        <p class="mt-1 text-sm text-[hsl(var(--muted-foreground))]">Start managing your finances with Gäld</p>
      </div>

      <Card>
        <CardContent class="pt-6">
          <form class="space-y-4" @submit.prevent="submit">
            <FormInput
              id="name"
              v-model="form.name"
              label="Full Name"
              placeholder="Max Muster"
              :error="form.errors.name"
              required
            />

            <FormInput
              id="email"
              v-model="form.email"
              type="email"
              label="Email"
              placeholder="you@example.com"
              :error="form.errors.email"
              required
            />

            <FormInput
              id="password"
              v-model="form.password"
              type="password"
              label="Password"
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
              Create account
            </Button>
          </form>
        </CardContent>
      </Card>

      <p class="mt-4 text-center text-sm text-[hsl(var(--muted-foreground))]">
        Already have an account?
        <Link href="/login" class="font-medium text-[hsl(var(--primary))] hover:underline">
          Sign in
        </Link>
      </p>
    </div>
  </div>
</template>
