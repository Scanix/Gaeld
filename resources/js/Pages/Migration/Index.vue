<script setup>
import { ref } from 'vue'
import { useForm, router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import Modal from '@/Components/UI/Modal.vue'
import EmptyState from '@/Components/UI/EmptyState.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import {
  ArrowRightLeft,
  ChevronRight,
  FileText,
  Plus,
  Trash2,
} from 'lucide-vue-next'

const { t } = useTranslations()
const { formatDate } = useFormatters()

const props = defineProps({
  sessions: { type: Object, default: () => ({}) },
  platforms: { type: Array, default: () => [] },
})

const showPlatformPicker = ref(false)
const deleteTarget = ref(null)

const form = useForm({ platform: '' })

function selectPlatform(platform) {
  form.platform = platform
  form.post('/migration', {
    onSuccess: () => { showPlatformPicker.value = false },
  })
}

const deleteForm = useForm({})

function confirmDelete(session) {
  deleteTarget.value = session
}

function executeDelete() {
  if (!deleteTarget.value) return
  deleteForm.delete(`/migration/${deleteTarget.value.id}`, {
    onSuccess: () => { deleteTarget.value = null },
  })
}

const statusVariant = {
  pending: 'secondary',
  validating: 'info',
  importing: 'info',
  completed: 'success',
  failed: 'destructive',
  partially_completed: 'warning',
}

const platformIcons = {
  bexio: '🏢',
  banana: '🍌',
  abacus: '🔢',
  generic_csv: '📄',
  manual: '✏️',
}
</script>

<template>
  <AppLayout :title="t('migration.migration')">
    <PageHeader>
      <template #start>
        <div>
          <h1 class="text-2xl font-bold">{{ t('migration.migration') }}</h1>
          <p class="mt-0.5 text-sm text-[hsl(var(--muted-foreground))]">{{ t('migration.migration_description') }}</p>
        </div>
      </template>
      <Button @click="showPlatformPicker = true">
        <Plus class="mr-2 h-4 w-4" />
        {{ t('migration.new_import') }}
      </Button>
    </PageHeader>

    <!-- Import history -->
    <Card>
      <CardHeader>
        <CardTitle>{{ t('migration.import_history') }}</CardTitle>
      </CardHeader>
      <CardContent>
        <EmptyState
          v-if="(sessions?.data ?? []).length === 0"
          :icon="ArrowRightLeft"
          :title="t('migration.no_sessions')"
        />

        <div v-else class="divide-y divide-[hsl(var(--border))]">
          <div
            v-for="session in (sessions?.data ?? [])"
            :key="session.id"
            class="flex items-center gap-4 py-4 first:pt-0 last:pb-0"
          >
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-[hsl(var(--muted))] text-lg">
              {{ platformIcons[session.platform] || '📦' }}
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2">
                <p class="font-medium text-sm">{{ t(`migration.platform_${session.platform}`) }}</p>
                <Badge :variant="statusVariant[session.status] || 'secondary'">
                  {{ t(`migration.status_${session.status}`) }}
                </Badge>
              </div>
              <p class="text-xs text-[hsl(var(--muted-foreground))]">
                {{ t('migration.created_on') }} {{ formatDate(session.created_at) }}
              </p>
            </div>
            <div class="flex items-center gap-2">
              <Button variant="ghost" size="icon" @click="confirmDelete(session)">
                <Trash2 class="h-4 w-4" />
              </Button>
              <Link :href="`/migration/${session.id}`">
                <Button variant="outline" size="sm">
                  <FileText class="mr-2 h-4 w-4" />
                  {{ session.status === 'completed' ? t('migration.import_results') : t('migration.step_upload') }}
                </Button>
              </Link>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="sessions?.last_page > 1" class="mt-4 flex items-center justify-between border-t border-[hsl(var(--border))] pt-4">
          <span class="text-sm text-[hsl(var(--muted-foreground))]">
            {{ t('page') }} {{ sessions.current_page }} / {{ sessions.last_page }}
          </span>
          <div class="flex gap-2">
            <Link v-if="sessions.prev_page_url" :href="sessions.prev_page_url">
              <Button variant="outline" size="sm">{{ t('previous') }}</Button>
            </Link>
            <Link v-if="sessions.next_page_url" :href="sessions.next_page_url">
              <Button variant="outline" size="sm">{{ t('next') }}</Button>
            </Link>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Platform picker modal -->
    <Modal :show="showPlatformPicker" @close="showPlatformPicker = false">
      <div class="p-6">
        <h2 class="text-lg font-bold mb-1">{{ t('migration.select_platform') }}</h2>
        <p class="text-sm text-[hsl(var(--muted-foreground))] mb-6">{{ t('migration.select_platform_desc') }}</p>

        <div class="grid gap-3">
          <button
            v-for="platform in platforms"
            :key="platform.platform"
            class="flex items-start gap-4 rounded-lg border border-[hsl(var(--border))] p-4 text-left transition-colors hover:border-[hsl(var(--primary))] hover:bg-[hsl(var(--accent))]"
            :disabled="form.processing"
            @click="selectPlatform(platform.platform)"
          >
            <span class="mt-0.5 text-2xl">{{ platformIcons[platform.platform] || '📦' }}</span>
            <div class="min-w-0 flex-1">
              <p class="font-medium">{{ t(`migration.platform_${platform.platform}`) }}</p>
              <p class="mt-0.5 text-sm text-[hsl(var(--muted-foreground))]">
                {{ t(`migration.platform_${platform.platform}_desc`) }}
              </p>
              <div class="mt-2 flex flex-wrap gap-1">
                <Badge v-for="dt in platform.data_types" :key="dt" variant="outline" class="text-xs">
                  {{ t(`migration.data_types.${dt}`) }}
                </Badge>
              </div>
            </div>
            <ChevronRight class="mt-1 h-5 w-5 shrink-0 text-[hsl(var(--muted-foreground))]" />
          </button>
        </div>
      </div>
    </Modal>

    <!-- Delete confirmation modal -->
    <Modal :show="!!deleteTarget" @close="deleteTarget = null">
      <div class="p-6">
        <h2 class="text-lg font-bold mb-2">{{ t('migration.delete_session') }}</h2>
        <p class="text-sm text-[hsl(var(--muted-foreground))] mb-6">{{ t('migration.delete_session_confirm') }}</p>
        <div class="flex justify-end gap-3">
          <Button variant="outline" @click="deleteTarget = null">{{ t('cancel') }}</Button>
          <Button variant="destructive" :disabled="deleteForm.processing" @click="executeDelete">
            {{ t('migration.delete_session') }}
          </Button>
        </div>
      </div>
    </Modal>
  </AppLayout>
</template>
