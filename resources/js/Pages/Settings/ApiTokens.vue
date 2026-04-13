<script setup>
import { useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import Modal from '@/Components/UI/Modal.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { useFormatters } from '@/lib/useFormatters'
import { useTranslations } from '@/lib/useTranslations'
import { ref, computed } from 'vue'
import { ArrowLeft, Key, Plus, Trash2, Copy, Check, Building2 } from 'lucide-vue-next'

const props = defineProps({
  personalTokens: { type: Array, default: () => [] },
  orgTokens: { type: Array, default: () => [] },
  canManageOrgTokens: { type: Boolean, default: false },
  abilities: { type: Array, default: () => [] },
})

const { t } = useTranslations()
const { formatDate } = useFormatters()
const page = usePage()
const flash = computed(() => page.props.flash || {})

// Token creation modal
const showCreateModal = ref(false)
const tokenType = ref('personal') // 'personal' | 'organization'

const createForm = useForm({
  name: '',
  abilities: [],
  expires_in_days: '',
})

function openCreateModal(type) {
  tokenType.value = type
  createForm.reset()
  showCreateModal.value = true
}

function submitCreate() {
  const url = tokenType.value === 'personal'
    ? '/settings/api-tokens/personal'
    : '/settings/api-tokens/organization'

  createForm.post(url, {
    onSuccess: () => {
      showCreateModal.value = false
      createForm.reset()
    },
  })
}

// Token revocation
const confirmingDelete = ref(false)
const deletingToken = ref(null)
const deletingType = ref('personal')

function confirmDelete(token, type) {
  deletingToken.value = token
  deletingType.value = type
  confirmingDelete.value = true
}

function deleteToken() {
  const url = deletingType.value === 'personal'
    ? `/settings/api-tokens/personal/${deletingToken.value.id}`
    : `/settings/api-tokens/organization/${deletingToken.value.id}`

  router.delete(url, {
    onSuccess: () => {
      confirmingDelete.value = false
      deletingToken.value = null
    },
  })
}

// Copy token
const copied = ref(false)
function copyToken(token) {
  navigator.clipboard.writeText(token)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

const expirationOptions = [
  { value: '', label: t('never') },
  { value: '7', label: t('n_days', { n: 7 }) },
  { value: '30', label: t('n_days', { n: 30 }) },
  { value: '90', label: t('n_days', { n: 90 }) },
  { value: '365', label: t('n_days', { n: 365 }) },
]
</script>

<template>
  <AppLayout :title="t('api_tokens')">
    <div class="max-w-3xl space-y-6">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <Button as="a" href="/settings" variant="outline" size="sm">
            <ArrowLeft class="mr-2 h-4 w-4" /> {{ t('settings') }}
          </Button>
          <h2 class="text-xl font-semibold">
            <Key class="inline mr-2 h-5 w-5" />
            {{ t('api_tokens') }}
          </h2>
        </div>
      </div>

      <!-- New token display (shown once after creation) -->
      <div v-if="flash.newToken" class="rounded-md border border-yellow-300 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-950">
        <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200 mb-2">
          {{ t('token_created_copy_now') }}
        </p>
        <div class="flex items-center gap-2">
          <code class="flex-1 rounded bg-yellow-100 px-3 py-2 text-xs font-mono dark:bg-yellow-900 break-all">
            {{ flash.newToken }}
          </code>
          <Button variant="outline" size="sm" @click="copyToken(flash.newToken)">
            <Check v-if="copied" class="h-4 w-4 text-green-600" />
            <Copy v-else class="h-4 w-4" />
          </Button>
        </div>
      </div>

      <!-- Personal tokens -->
      <Card>
        <CardHeader>
          <div class="flex items-center justify-between">
            <div>
              <CardTitle>{{ t('personal_tokens') }}</CardTitle>
              <CardDescription>
                {{ t('personal_tokens_description') }}
              </CardDescription>
            </div>
            <Button size="sm" @click="openCreateModal('personal')">
              <Plus class="mr-2 h-4 w-4" /> {{ t('create_token') }}
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <div v-if="!personalTokens.length" class="py-4 text-center text-sm text-muted-foreground">
            {{ t('no_personal_tokens') }}
          </div>
          <div v-else class="space-y-3">
            <div
              v-for="token in personalTokens"
              :key="token.id"
              class="flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
            >
              <div class="min-w-0">
                <p class="text-sm font-medium truncate">{{ token.name }}</p>
                <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1 text-xs text-muted-foreground">
                  <span v-if="token.last_used_at">
                    {{ t('last_used') }}: {{ formatDate(token.last_used_at) }}
                  </span>
                  <span v-else>{{ t('never_used') }}</span>
                  <span v-if="token.expires_at">
                    {{ t('expires') }}: {{ formatDate(token.expires_at) }}
                  </span>
                </div>
                <div class="flex flex-wrap gap-1 mt-1">
                  <Badge v-for="ability in token.abilities" :key="ability" variant="outline" class="text-xs">
                    {{ ability }}
                  </Badge>
                </div>
              </div>
              <Button variant="ghost" size="sm" class="text-destructive self-end sm:self-auto" @click="confirmDelete(token, 'personal')">
                <Trash2 class="h-4 w-4" />
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Organization tokens -->
      <Card v-if="canManageOrgTokens">
        <CardHeader>
          <div class="flex items-center justify-between">
            <div>
              <CardTitle>
                <Building2 class="inline mr-2 h-4 w-4" />
                {{ t('organization_tokens') }}
              </CardTitle>
              <CardDescription>
                {{ t('org_tokens_description') }}
              </CardDescription>
            </div>
            <Button size="sm" @click="openCreateModal('organization')">
              <Plus class="mr-2 h-4 w-4" /> {{ t('create_token') }}
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <div v-if="!orgTokens.length" class="py-4 text-center text-sm text-muted-foreground">
            {{ t('no_org_tokens') }}
          </div>
          <div v-else class="space-y-3">
            <div
              v-for="token in orgTokens"
              :key="token.id"
              class="flex items-center justify-between rounded-lg border p-3"
            >
              <div>
                <p class="text-sm font-medium">{{ token.name }}</p>
                <div class="flex gap-3 mt-1 text-xs text-muted-foreground">
                  <span v-if="token.created_by">{{ t('created_by') }}: {{ token.created_by }}</span>
                  <span v-if="token.last_used_at">
                    {{ t('last_used') }}: {{ formatDate(token.last_used_at) }}
                  </span>
                  <span v-else>{{ t('never_used') }}</span>
                  <span v-if="token.expires_at">
                    {{ t('expires') }}: {{ formatDate(token.expires_at) }}
                  </span>
                </div>
                <div class="flex flex-wrap gap-1 mt-1">
                  <Badge v-for="ability in token.abilities" :key="ability" variant="outline" class="text-xs">
                    {{ ability }}
                  </Badge>
                </div>
              </div>
              <Button variant="ghost" size="sm" class="text-destructive" @click="confirmDelete(token, 'organization')">
                <Trash2 class="h-4 w-4" />
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Create token modal -->
      <Modal :show="showCreateModal" @close="showCreateModal = false" :title="tokenType === 'personal' ? (t('create_personal_token')) : (t('create_org_token'))">
        <form class="space-y-6" @submit.prevent="submitCreate">
          <FormInput
            v-model="createForm.name"
            :label="t('token_name')"
            :error="createForm.errors.name"
            required
            :placeholder="t('token_name_placeholder')"
          />
          <FormSelect
            v-model="createForm.expires_in_days"
            :label="t('expiration')"
            :options="expirationOptions"
            :error="createForm.errors.expires_in_days"
          />
          <div>
            <label class="text-sm font-medium">{{ t('permissions') }}</label>
            <div class="mt-2 grid grid-cols-2 gap-2">
              <label
                v-for="ability in abilities"
                :key="ability"
                class="flex items-center gap-2 text-sm"
              >
                <input
                  type="checkbox"
                  :value="ability"
                  v-model="createForm.abilities"
                  class="rounded border-gray-300 dark:border-gray-600"
                />
                {{ ability }}
              </label>
            </div>
            <p class="text-xs text-muted-foreground mt-1">
              {{ t('no_selection_all_permissions') }}
            </p>
          </div>
          <div class="flex justify-end gap-3">
            <Button variant="outline" type="button" @click="showCreateModal = false">{{ t('cancel') }}</Button>
            <Button type="submit" :disabled="createForm.processing">
              {{ t('create') }}
            </Button>
          </div>
        </form>
      </Modal>

      <!-- Delete confirmation -->
      <ConfirmDialog
        :show="confirmingDelete"
        @close="confirmingDelete = false"
        @confirm="deleteToken"
        :title="t('revoke_token')"
        :message="t('revoke_token_confirm')"
        :confirmLabel="t('revoke')"
        variant="destructive"
      />
    </div>
  </AppLayout>
</template>
