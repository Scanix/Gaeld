<script setup>
import { useForm, usePage, router } from '@inertiajs/vue3'
import { LogOut, User, HelpCircle, BookOpen, Building2, ChevronDown, Check, Menu } from 'lucide-vue-next'
import { ref, computed } from 'vue'
import Button from './UI/Button.vue'
import { useTranslations } from '@/lib/useTranslations'

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
const showOrgMenu = ref(false)

const currentOrg = computed(() => page.props.auth?.currentOrganization)
const organizations = computed(() => page.props.auth?.organizations ?? [])
const hasMultipleOrgs = computed(() => organizations.value.length > 1)

function logout() {
  logoutForm.post('/logout')
}

function switchOrg(orgId) {
  showOrgMenu.value = false
  const form = useForm({})
  form.post(`/organizations/${orgId}/switch`)
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
      <!-- Organization Switcher -->
      <div v-if="currentOrg" class="relative">
        <Button
          variant="ghost"
          size="sm"
          class="gap-2"
          @click="showOrgMenu = !showOrgMenu"
        >
          <Building2 class="h-4 w-4" />
          <span class="hidden sm:inline">{{ currentOrg.name }}</span>
          <ChevronDown v-if="hasMultipleOrgs" class="h-3 w-3" />
        </Button>

        <div
          v-if="showOrgMenu && hasMultipleOrgs"
          class="absolute right-0 top-full mt-1 w-56 rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--background))] p-1 shadow-lg"
          @mouseleave="showOrgMenu = false"
        >
          <button
            v-for="org in organizations"
            :key="org.id"
            class="flex w-full items-center justify-between rounded-md px-3 py-2 text-sm text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))]"
            @click="switchOrg(org.id)"
          >
            <span>{{ org.name }}</span>
            <Check v-if="org.id === currentOrg.id" class="h-4 w-4 text-[hsl(var(--primary))]" />
          </button>
        </div>
      </div>

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
