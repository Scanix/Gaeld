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
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { formatDate } from '@/lib/utils'
import { useTranslations } from '@/lib/useTranslations'
import { ref, computed } from 'vue'
import { ArrowLeft, Webhook, Plus, Trash2, Pencil, RotateCcw, Copy, Check, ExternalLink } from 'lucide-vue-next'

const props = defineProps({
  webhooks: { type: Array, default: () => [] },
  availableEvents: { type: Array, default: () => [] },
})

const { t } = useTranslations()
const page = usePage()
const flash = computed(() => page.props.flash || {})

// Create/Edit modal
const showModal = ref(false)
const editingWebhook = ref(null)

const form = useForm({
  url: '',
  events: [],
  is_active: true,
})

function openCreateModal() {
  editingWebhook.value = null
  form.reset()
  form.events = []
  form.is_active = true
  showModal.value = true
}

function openEditModal(webhook) {
  editingWebhook.value = webhook
  form.url = webhook.url
  form.events = [...webhook.events]
  form.is_active = webhook.is_active
  showModal.value = true
}

function submitForm() {
  if (editingWebhook.value) {
    form.put(`/settings/webhooks/${editingWebhook.value.id}`, {
      onSuccess: () => { showModal.value = false; form.reset() },
    })
  } else {
    form.post('/settings/webhooks', {
      onSuccess: () => { showModal.value = false; form.reset() },
    })
  }
}

// Delete
const confirmingDelete = ref(false)
const deletingWebhook = ref(null)

function confirmDelete(webhook) {
  deletingWebhook.value = webhook
  confirmingDelete.value = true
}

function deleteWebhook() {
  router.delete(`/settings/webhooks/${deletingWebhook.value.id}`, {
    onSuccess: () => {
      confirmingDelete.value = false
      deletingWebhook.value = null
    },
  })
}

// Regenerate secret
const regenerating = ref(null)
function regenerateSecret(webhook) {
  regenerating.value = webhook.id
  router.post(`/settings/webhooks/${webhook.id}/regenerate-secret`, {}, {
    onFinish: () => { regenerating.value = null },
  })
}

// Copy secret
const copied = ref(false)
function copySecret(secret) {
  navigator.clipboard.writeText(secret)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

// Toggle individual event
function toggleEvent(event) {
  const idx = form.events.indexOf(event)
  if (idx >= 0) {
    form.events.splice(idx, 1)
  } else {
    form.events.push(event)
  }
}

function toggleAllEvents() {
  if (form.events.length === props.availableEvents.length) {
    form.events = []
  } else {
    form.events = [...props.availableEvents]
  }
}

// Group events by entity
const eventGroups = computed(() => {
  const groups = {}
  for (const event of props.availableEvents) {
    const [entity] = event.split('.')
    if (!groups[entity]) groups[entity] = []
    groups[entity].push(event)
  }
  return groups
})
</script>

<template>
  <AppLayout :title="t('webhooks') || 'Webhooks'">
    <div class="max-w-3xl space-y-6">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <Button as="a" href="/settings" variant="outline" size="sm">
            <ArrowLeft class="mr-2 h-4 w-4" /> {{ t('settings') || 'Settings' }}
          </Button>
          <h2 class="text-xl font-semibold">
            <Webhook class="inline mr-2 h-5 w-5" />
            {{ t('webhooks') || 'Webhooks' }}
          </h2>
        </div>
        <Button @click="openCreateModal">
          <Plus class="mr-2 h-4 w-4" /> {{ t('add_webhook') || 'Add Webhook' }}
        </Button>
      </div>

      <!-- Flash messages -->
      <div v-if="flash.success" class="rounded-md bg-green-50 p-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">
        {{ flash.success }}
      </div>

      <!-- Webhook secret display (shown once after creation/regeneration) -->
      <div v-if="flash.webhookSecret" class="rounded-md border border-yellow-300 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-950">
        <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200 mb-2">
          {{ t('webhook_secret_copy') || 'Your webhook signing secret. Copy it now — it won\'t be shown again.' }}
        </p>
        <div class="flex items-center gap-2">
          <code class="flex-1 rounded bg-yellow-100 px-3 py-2 text-xs font-mono dark:bg-yellow-900 break-all">
            {{ flash.webhookSecret }}
          </code>
          <Button variant="outline" size="sm" @click="copySecret(flash.webhookSecret)">
            <Check v-if="copied" class="h-4 w-4 text-green-600" />
            <Copy v-else class="h-4 w-4" />
          </Button>
        </div>
      </div>

      <!-- Webhooks list -->
      <Card>
        <CardHeader>
          <CardTitle>{{ t('configured_webhooks') || 'Configured Webhooks' }}</CardTitle>
          <CardDescription>
            {{ t('webhooks_description') || 'Receive HTTP POST notifications when events occur in your organization.' }}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div v-if="!webhooks.length" class="py-8 text-center text-sm text-muted-foreground">
            {{ t('no_webhooks') || 'No webhooks configured yet.' }}
          </div>
          <div v-else class="space-y-4">
            <div
              v-for="webhook in webhooks"
              :key="webhook.id"
              class="rounded-lg border p-4"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1">
                    <Badge :variant="webhook.is_active ? 'default' : 'secondary'">
                      {{ webhook.is_active ? (t('active') || 'Active') : (t('inactive') || 'Inactive') }}
                    </Badge>
                    <span v-if="webhook.calls_count" class="text-xs text-muted-foreground">
                      {{ webhook.calls_count }} {{ t('deliveries') || 'deliveries' }}
                    </span>
                  </div>
                  <p class="text-sm font-mono truncate">
                    <ExternalLink class="inline mr-1 h-3 w-3" />
                    {{ webhook.url }}
                  </p>
                  <div class="flex flex-wrap gap-1 mt-2">
                    <Badge v-for="event in webhook.events" :key="event" variant="outline" class="text-xs">
                      {{ event }}
                    </Badge>
                  </div>
                  <div class="flex gap-3 mt-2 text-xs text-muted-foreground">
                    <span>{{ t('created') || 'Created' }}: {{ formatDate(webhook.created_at) }}</span>
                    <span v-if="webhook.last_triggered_at">
                      {{ t('last_triggered') || 'Last triggered' }}: {{ formatDate(webhook.last_triggered_at) }}
                    </span>
                  </div>
                </div>
                <div class="flex items-center gap-1 ml-4">
                  <Button variant="ghost" size="sm" @click="regenerateSecret(webhook)" :disabled="regenerating === webhook.id">
                    <RotateCcw :class="['h-4 w-4', regenerating === webhook.id && 'animate-spin']" />
                  </Button>
                  <Button variant="ghost" size="sm" @click="openEditModal(webhook)">
                    <Pencil class="h-4 w-4" />
                  </Button>
                  <Button variant="ghost" size="sm" class="text-destructive" @click="confirmDelete(webhook)">
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Create/Edit modal -->
      <Modal :show="showModal" @close="showModal = false" :title="editingWebhook ? (t('edit_webhook') || 'Edit Webhook') : (t('add_webhook') || 'Add Webhook')">
        <form class="space-y-4" @submit.prevent="submitForm">
          <FormInput
            v-model="form.url"
            :label="t('payload_url') || 'Payload URL'"
            :error="form.errors.url"
            required
            type="url"
            placeholder="https://example.com/webhooks/gaeld"
          />

          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="text-sm font-medium">{{ t('events') || 'Events' }} <span class="text-destructive">*</span></label>
              <button type="button" class="text-xs text-primary underline" @click="toggleAllEvents">
                {{ form.events.length === availableEvents.length ? (t('deselect_all') || 'Deselect all') : (t('select_all') || 'Select all') }}
              </button>
            </div>
            <div class="space-y-3">
              <div v-for="(events, entity) in eventGroups" :key="entity">
                <p class="text-xs font-semibold uppercase text-muted-foreground mb-1">{{ entity }}</p>
                <div class="grid grid-cols-2 gap-1">
                  <label
                    v-for="event in events"
                    :key="event"
                    class="flex items-center gap-2 text-sm"
                  >
                    <input
                      type="checkbox"
                      :checked="form.events.includes(event)"
                      @change="toggleEvent(event)"
                      class="rounded border-gray-300"
                    />
                    {{ event.split('.').pop() }}
                  </label>
                </div>
              </div>
            </div>
            <p v-if="form.errors.events" class="text-xs text-destructive mt-1">{{ form.errors.events }}</p>
          </div>

          <div class="flex items-center gap-2">
            <input
              type="checkbox"
              v-model="form.is_active"
              id="webhook-active"
              class="rounded border-gray-300"
            />
            <label for="webhook-active" class="text-sm">{{ t('active') || 'Active' }}</label>
          </div>

          <div class="flex justify-end gap-3">
            <Button variant="outline" type="button" @click="showModal = false">{{ t('cancel') || 'Cancel' }}</Button>
            <Button type="submit" :disabled="form.processing">
              {{ editingWebhook ? (t('save') || 'Save') : (t('create') || 'Create') }}
            </Button>
          </div>
        </form>
      </Modal>

      <!-- Delete confirmation -->
      <ConfirmDialog
        :show="confirmingDelete"
        @close="confirmingDelete = false"
        @confirm="deleteWebhook"
        :title="t('delete_webhook') || 'Delete Webhook'"
        :message="t('delete_webhook_confirm') || 'Are you sure you want to delete this webhook? It will stop receiving notifications immediately.'"
        :confirmLabel="t('delete') || 'Delete'"
        variant="destructive"
      />
    </div>
  </AppLayout>
</template>
