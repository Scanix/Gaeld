import { ref } from 'vue'
import FormInput from './FormInput.vue'

export default {
  title: 'UI/FormInput',
  component: FormInput,
  tags: ['autodocs'],
  argTypes: {
    type: {
      control: 'select',
      options: ['text', 'email', 'password', 'number', 'date'],
    },
  },
}

export const Default = {
  args: {
    id: 'company-name',
    label: 'Company name',
    placeholder: 'Atelier Gäld',
    modelValue: '',
  },
}

export const Required = {
  args: {
    id: 'email',
    label: 'Email',
    type: 'email',
    required: true,
    modelValue: '',
  },
}

export const WithHint = {
  args: {
    id: 'payment-terms',
    label: 'Payment terms',
    type: 'number',
    hint: 'Number of days until payment is due.',
    modelValue: 30,
  },
}

export const Error = {
  args: {
    id: 'vat-number',
    label: 'VAT number',
    error: 'Enter a valid Swiss VAT number.',
    modelValue: 'CHE-',
  },
}

export const Interactive = {
  render: (args) => ({
    components: { FormInput },
    setup() {
      const value = ref('')
      return { args, value }
    },
    template: '<FormInput v-bind="args" v-model="value" /><p class="mt-2 text-sm">Value: {{ value || "(empty)" }}</p>',
  }),
  args: {
    id: 'interactive-input',
    label: 'Interactive input',
  },
}
