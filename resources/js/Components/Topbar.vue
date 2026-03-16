<script setup>
import { useForm, usePage } from '@inertiajs/vue3'
import { LogOut, User, HelpCircle } from 'lucide-vue-next'
import { ref } from 'vue'
import Button from './UI/Button.vue'

defineProps({
  helpPage: {
    type: String,
    default: null,
  },
})

const emit = defineEmits(['toggleHelp'])

const page = usePage()
const logoutForm = useForm({})
const showUserMenu = ref(false)

function logout() {
  logoutForm.post('/logout')
}
</script>

<template>
  <header class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-[hsl(var(--border))] bg-[hsl(var(--background))]/95 px-6 backdrop-blur supports-[backdrop-filter]:bg-[hsl(var(--background))]/60">
    <div class="flex items-center gap-4">
      <slot name="title">
        <h1 class="text-lg font-semibold">
          <slot name="heading" />
        </h1>
      </slot>
    </div>

    <div class="flex items-center gap-2">
      <Button
        v-if="helpPage"
        variant="ghost"
        size="icon"
        title="Help"
        @click="$emit('toggleHelp')"
      >
        <HelpCircle class="h-4 w-4" />
      </Button>

      <div class="relative">
        <Button
          variant="ghost"
          size="sm"
          class="gap-2"
          @click="showUserMenu = !showUserMenu"
        >
          <User class="h-4 w-4" />
          <span class="hidden sm:inline">{{ page.props.auth?.user?.name ?? 'Account' }}</span>
        </Button>

        <div
          v-if="showUserMenu"
          class="absolute right-0 top-full mt-1 w-48 rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--background))] p-1 shadow-lg"
          @mouseleave="showUserMenu = false"
        >
          <a
            href="/profile"
            class="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))]"
          >
            <User class="h-4 w-4" />
            Profile
          </a>
          <button
            class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-[hsl(var(--destructive))] hover:bg-[hsl(var(--accent))]"
            @click="logout"
          >
            <LogOut class="h-4 w-4" />
            Sign out
          </button>
        </div>
      </div>
    </div>
  </header>
</template>
