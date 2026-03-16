<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'

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
  <Head title="Login" />

  <div class="flex min-h-screen items-center justify-center bg-[hsl(var(--muted))] p-6">
    <div class="w-full max-w-md">
      <div class="mb-8 text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))] text-xl font-bold">
          G
        </div>
        <h1 class="mt-4 text-2xl font-bold">Welcome to Gäld</h1>
        <p class="mt-1 text-sm text-[hsl(var(--muted-foreground))]">Sign in to your account</p>
      </div>

      <Card>
        <CardContent class="pt-6">
          <form class="space-y-4" @submit.prevent="submit">
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

            <div class="flex items-center justify-between">
              <label class="flex items-center gap-2 text-sm">
                <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-[hsl(var(--input))]">
                Remember me
              </label>
              <Link href="/forgot-password" class="text-sm text-[hsl(var(--primary))] hover:underline">
                Forgot password?
              </Link>
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">
              Sign in
            </Button>
          </form>
        </CardContent>
      </Card>

      <p class="mt-4 text-center text-sm text-[hsl(var(--muted-foreground))]">
        Don't have an account?
        <Link href="/register" class="font-medium text-[hsl(var(--primary))] hover:underline">
          Create one
        </Link>
      </p>
    </div>
  </div>
</template>