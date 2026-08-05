import Button from './Button.vue'

export default {
  title: 'UI/Button',
  component: Button,
  tags: ['autodocs'],
  render: (args) => ({
    components: { Button },
    setup() {
      return { args }
    },
    template: '<Button v-bind="args">{{ args.label }}</Button>',
  }),
  argTypes: {
    variant: {
      control: 'select',
      options: ['default', 'destructive', 'outline', 'secondary', 'ghost', 'link'],
    },
    size: {
      control: 'select',
      options: ['default', 'sm', 'lg', 'icon'],
    },
  },
}

export const Default = {
  args: {
    label: 'Save changes',
  },
}

export const Destructive = {
  args: {
    variant: 'destructive',
    label: 'Delete account',
  },
}

export const Loading = {
  args: {
    loading: true,
    label: 'Saving',
  },
}

export const Icon = {
  args: {
    size: 'icon',
    'aria-label': 'Open settings',
    label: '...',
  },
}
