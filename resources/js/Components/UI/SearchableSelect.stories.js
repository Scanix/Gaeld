import { ref } from 'vue'
import SearchableSelect from './SearchableSelect.vue'

const contacts = [
  { value: 'alpha', label: 'Client Alpha SA' },
  { value: 'beta', label: 'Client Beta GmbH' },
  { value: 'gamma', label: 'Client Gamma Sarl' },
]

const manyContacts = Array.from({ length: 12 }, (_, index) => ({
  value: `client-${index + 1}`,
  label: `Client ${String(index + 1).padStart(2, '0')}`,
}))

export default {
  title: 'UI/SearchableSelect',
  component: SearchableSelect,
  tags: ['autodocs'],
}

export const NativeShortList = {
  args: {
    id: 'currency',
    label: 'Currency',
    placeholder: 'Select currency',
    options: [
      { value: 'CHF', label: 'CHF — Swiss Franc' },
      { value: 'EUR', label: 'EUR — Euro' },
      { value: 'USD', label: 'USD — US Dollar' },
    ],
  },
}

export const SearchableCustomers = {
  args: {
    id: 'customer',
    label: 'Client',
    placeholder: 'Select a client',
    searchPlaceholder: 'Search customers',
    options: manyContacts,
    required: true,
  },
}

export const WithError = {
  args: {
    id: 'customer-error',
    label: 'Client',
    placeholder: 'Select a client',
    options: contacts,
    error: 'This field is required.',
    required: true,
  },
}

export const Empty = {
  args: {
    id: 'empty-options',
    label: 'Client',
    placeholder: 'No clients available',
    emptyText: 'Create a client to continue.',
    options: [],
    forceSearchable: true,
  },
}

export const Interactive = {
  render: (args) => ({
    components: { SearchableSelect },
    setup() {
      const selected = ref('')
      return { args, selected }
    },
    template: '<SearchableSelect v-bind="args" v-model="selected" /><p class="mt-2 text-sm">Selected: {{ selected || "(none)" }}</p>',
  }),
  args: {
    id: 'interactive-customer',
    label: 'Interactive customer',
    placeholder: 'Select a client',
    options: manyContacts,
    forceSearchable: true,
  },
}
