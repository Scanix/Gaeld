<script setup>
import { useForm, usePage, router, Link } from '@inertiajs/vue3'
import { LogOut, User, HelpCircle, BookOpen, Menu, Sun, Moon, ShieldCheck } from 'lucide-vue-next'
import { ref, computed } from 'vue'
import Button from './UI/Button.vue'
import GlobalSearch from './GlobalSearch.vue'
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
  <header class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-[hsl(var(--border))] bg-[hsl(var(--background))]/95 px-3 sm:px-6 backdrop-blur supports-[backdrop-filter]:bg-[hsl(var(--background))]/60">
    <div class="flex items-center gap-2 sm:gap-4 min-w-0">
      <button
        :aria-label="t('toggle_menu')"
        class="shrink-0 rounded-lg p-3 min-h-[44px] min-w-[44px] text-[hsl(var(--muted-foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))] lg:hidden"
        @click="$emit('toggleMobile')"
      >
        <Menu class="h-5 w-5" />
      </button>
      <slot name="title">
        <h1 class="truncate text-lg font-semibold">
          <slot name="heading" />
        </h1>
      </slot>
    </div>

    <div class="flex items-center gap-1 sm:gap-2">
      <GlobalSearch />
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
        class="hidden sm:inline-flex"
        :title="t('help')"
        @click="$emit('toggleHelp')"
      >
        <HelpCircle class="h-4 w-4" />
      </Button>

      <Button
        variant="ghost"
        size="icon"
        class="hidden sm:inline-flex"
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
          aria-haspopup="true"
          :aria-expanded="showUserMenu"
          @click="showUserMenu = !showUserMenu"
        >
          <User class="h-4 w-4" />
          <span class="hidden md:inline">{{ page.props.auth?.user?.name ?? t('account') }}</span>
        </Button>

        <div
          v-if="showUserMenu"
          role="menu"
          class="absolute right-0 top-full mt-1 w-48 rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--popover))] p-1 shadow-lg"
          @mouseleave="showUserMenu = false"
        >
          <Link
            href="/profile"
            class="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))]"
          >
            <User class="h-4 w-4" />
            {{ t('profile') }}
          </Link>
          <button
            v-if="helpPage"
            class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))] sm:hidden"
            @click="$emit('toggleHelp'); showUserMenu = false"
          >
            <HelpCircle class="h-4 w-4" />
            {{ t('help') }}
          </button>
          <button
            v-if="docsUrl"
            class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))] sm:hidden"
            @click="$emit('toggleDocs'); showUserMenu = false"
          >
            <BookOpen class="h-4 w-4" />
            {{ t('documentation') }}
          </button>
          <Link
            v-if="isSaasAdmin"
            href="/saas-admin"
            class="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-[hsl(var(--primary))] hover:bg-[hsl(var(--accent))]"
          >
            <ShieldCheck class="h-4 w-4" />
            {{ t('saas_admin') }}
          </Link>
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
