<script setup>
import { useForm, usePage, router } from '@inertiajs/vue3'
import { LogOut, User, HelpCircle, BookOpen, Menu, Sun, Moon, ShieldCheck } from 'lucide-vue-next'
import { ref, computed } from 'vue'
import Button from './UI/Button.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useTheme } from '@/lib/useTheme'

const { t } = useTranslations()

defineProps({
  helpPage: {
    type: String,
    default: null,
  },
  docsUrl: {
    type: String,
    default: null,
  },
})

const emit = defineEmits(['toggleHelp', 'toggleDocs'])

const page = usePage()
const logoutForm = useForm({})
const showUserMenu = ref(false)

const { isDark, toggleTheme } = useTheme()

const isSaasAdmin = computed(() => page.props.auth?.is_saas_admin)

function logout() {
  logoutForm.post('/logout')
}
</script>

<template>
  <header class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-[hsl(var(--border))] bg-[hsl(var(--background))]/95 px-6 backdrop-blur supports-[backdrop-filter]:bg-[hsl(var(--background))]/60">
    <div class="flex items-center gap-4">
      <button
        class="rounded-lg p-2 text-[hsl(var(--muted-foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))] lg:hidden"
        @click="$emit('toggleMobile')"
      >
        <Menu class="h-5 w-5" />
      </button>
      <slot name="title">
        <h1 class="text-lg font-semibold">
          <slot name="heading" />
        </h1>
      </slot>
    </div>

    <div class="flex items-center gap-2">
      <Button
        variant="ghost"
        size="icon"
        :title="isDark ? t('light_mode') : t('dark_mode')"
        @click="toggleTheme"
      >
        <Sun v-if="isDark" class="h-4 w-4" />
        <Moon v-else class="h-4 w-4" />
      </Button>

      <Button
        v-if="helpPage"
        variant="ghost"
        size="icon"
        :title="t('help')"
        @click="$emit('toggleHelp')"
      >
        <HelpCircle class="h-4 w-4" />
      </Button>

      <Button
        v-if="docsUrl"
        variant="ghost"
        size="icon"
        :title="t('documentation')"
        @click="$emit('toggleDocs')"
      >
        <BookOpen class="h-4 w-4" />
      </Button>

      <div class="relative">
        <Button
          variant="ghost"
          size="sm"
          class="gap-2"
          @click="showUserMenu = !showUserMenu"
        >
          <User class="h-4 w-4" />
          <span class="hidden sm:inline">{{ page.props.auth?.user?.name ?? t('account') }}</span>
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
            {{ t('profile') }}
          </a>
          <a
            v-if="isSaasAdmin"
            href="/saas-admin"
            class="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-[hsl(var(--primary))] hover:bg-[hsl(var(--accent))]"
          >
            <ShieldCheck class="h-4 w-4" />
            {{ t('saas_admin') }}
          </a>
          <button
            class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-[hsl(var(--destructive))] hover:bg-[hsl(var(--accent))]"
            @click="logout"
          >
            <LogOut class="h-4 w-4" />
            {{ t('sign_out') }}
          </button>
        </div>
      </div>
    </div>
  </header>
</template>
