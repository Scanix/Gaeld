import { ref } from 'vue'
import Modal from './Modal.vue'
import Button from './Button.vue'
import FormInput from './FormInput.vue'

export default {
  title: 'UI/Modal',
  component: Modal,
  tags: ['autodocs'],
  argTypes: {
    size: {
      control: 'select',
      options: ['sm', 'md', 'lg', 'xl', '2xl'],
    },
  },
}

export const Closed = {
  args: {
    open: false,
    title: 'Create customer',
  },
  render: (args) => ({
    components: { Modal, Button },
    setup() {
      const open = ref(false)
      return { args, open }
    },
    template: '<Button @click="open = true">Open modal</Button><Modal v-bind="args" :open="open" @close="open = false"><p class="text-sm">Modal content goes here.</p></Modal>',
  }),
}

export const Form = {
  args: {
    title: 'New customer',
    size: 'lg',
  },
  render: (args) => ({
    components: { Modal, Button, FormInput },
    setup() {
      const open = ref(true)
      return { args, open }
    },
    template: '<Modal v-bind="args" :open="open" @close="open = false"><div class="space-y-4"><FormInput id="customer-name" label="Name" required /><FormInput id="customer-email" label="Email" type="email" /><div class="flex justify-end gap-2"><Button variant="outline" @click="open = false">Cancel</Button><Button>Create customer</Button></div></div></Modal>',
  }),
}

export const LongContent = {
  args: {
    open: true,
    title: 'Supporting information',
    size: 'md',
  },
  render: (args) => ({
    components: { Modal },
    setup() {
      return { args }
    },
    template: '<Modal v-bind="args"><div class="space-y-4"><p v-for="index in 12" :key="index" class="text-sm">Long content row {{ index }} demonstrates the scrollable modal body.</p></div></Modal>',
  }),
}
