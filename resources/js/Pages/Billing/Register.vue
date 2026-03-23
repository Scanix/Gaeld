<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { CheckCircle2, Zap } from 'lucide-vue-next'

const props = defineProps({
  plans: { type: Array, default: () => [] },
  trial_days: { type: Number, default: 14 },
})

const selectedPlan = ref(props.plans[0]?.id ?? null)

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  org_name: '',
  plan_id: selectedPlan,
})

function submit() {
  form.plan_id = selectedPlan.value
  form.post('/register')
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
      <h2 class="text-center text-3xl font-bold text-gray-900">Start your {{ trial_days }}-day free trial</h2>
      <p class="mt-2 text-center text-sm text-gray-600">No credit card required to start. Cancel anytime.</p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-2xl">

      <!-- Plan selector -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div
          v-for="plan in plans"
          :key="plan.id"
          @click="selectedPlan = plan.id"
          :class="selectedPlan === plan.id ? 'ring-2 ring-blue-500 bg-white' : 'bg-white opacity-70'"
          class="rounded-lg border p-4 cursor-pointer"
        >
          <div class="flex items-center gap-2 mb-1">
            <Zap v-if="plan.slug === 'pro'" class="h-4 w-4 text-blue-500" />
            <span class="font-semibold">{{ plan.name }}</span>
          </div>
          <p class="text-2xl font-bold">CHF {{ plan.price_chf }}<span class="text-sm font-normal text-gray-500">/mo</span></p>
          <p class="text-xs text-gray-500 mt-1">{{ plan.description }}</p>
          <ul class="mt-2 space-y-1">
            <li v-for="feature in plan.features" :key="feature" class="flex items-center gap-1 text-xs text-gray-600">
              <CheckCircle2 class="h-3 w-3 text-green-500" />
              {{ feature.replace(/_/g, ' ') }}
            </li>
          </ul>
        </div>
      </div>

      <!-- Registration form -->
      <div class="bg-white py-8 px-6 shadow rounded-lg">
        <form @submit.prevent="submit" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Your name</label>
              <input v-model="form.name" type="text" required
                     class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" />
              <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Organization name</label>
              <input v-model="form.org_name" type="text" required
                     class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" />
              <p v-if="form.errors.org_name" class="mt-1 text-xs text-red-600">{{ form.errors.org_name }}</p>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Email address</label>
            <input v-model="form.email" type="email" required
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" />
            <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Password</label>
              <input v-model="form.password" type="password" required
                     class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" />
              <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Confirm password</label>
              <input v-model="form.password_confirmation" type="password" required
                     class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" />
            </div>
          </div>

          <button
            type="submit"
            :disabled="form.processing"
            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none disabled:opacity-50"
          >
            {{ form.processing ? 'Creating account…' : `Start ${trial_days}-day free trial` }}
          </button>

          <p class="text-center text-xs text-gray-500">
            After the trial, you'll be redirected to Stripe to add your payment method. Cancel anytime.
          </p>
        </form>
      </div>
    </div>
  </div>
</template>
