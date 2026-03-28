<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Badge from '@/Components/UI/Badge.vue'
import Button from '@/Components/UI/Button.vue'
import HelpText from '@/Components/HelpText.vue'
import { useTranslations } from '@/lib/useTranslations'
import { formatDate } from '@/lib/utils'
import { Archive, ShieldCheck, Download, ChevronDown, ChevronRight } from 'lucide-vue-next'

const { t } = useTranslations()

const props = defineProps({
  archivesByYear: Object,
})

const expandedYears = ref({})

function toggleYear(year) {
  expandedYears.value[year] = !expandedYears.value[year]
}

function isExpanded(year) {
  return !!expandedYears.value[year]
}

const verifying = ref({})

function verifyArchive(archive) {
  verifying.value[archive.id] = true
  router.post(`/accounting/archives/${archive.id}/verify`, {}, {
    preserveScroll: true,
    onFinish: () => {
      verifying.value[archive.id] = false
    },
  })
}
</script>

<template>
  <AppLayout :title="t('legal_archives')">
    <HelpText :title="t('legal_archives')" class="mb-6">
      <p>{{ t('legal_archives_desc') }}</p>
    </HelpText>

    <div v-if="archivesByYear && Object.keys(archivesByYear).length > 0" class="space-y-4">
      <Card v-for="(archives, year) in archivesByYear" :key="year">
        <CardHeader class="cursor-pointer" @click="toggleYear(year)">
          <div class="flex items-center justify-between">
            <CardTitle class="flex items-center gap-2">
              <Archive class="h-5 w-5" />
              {{ t('archive_fiscal_year') }} {{ year }}
              <Badge variant="secondary">{{ archives.length }}</Badge>
            </CardTitle>
            <component :is="isExpanded(year) ? ChevronDown : ChevronRight" class="h-5 w-5 text-[hsl(var(--muted-foreground))]" />
          </div>
        </CardHeader>
        <CardContent v-if="isExpanded(year)">
          <div class="divide-y divide-[hsl(var(--border))]">
            <div
              v-for="archive in archives"
              :key="archive.id"
              class="flex items-center justify-between py-3 first:pt-0 last:pb-0"
            >
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium">{{ archive.document_type }}</p>
                <p class="text-xs text-[hsl(var(--muted-foreground))]">
                  {{ t('archived') }}: {{ formatDate(archive.archived_at) }}
                  · {{ t('archive_expires_at') }}: {{ formatDate(archive.expires_at) }}
                </p>
                <p v-if="archive.verified_at" class="mt-0.5 text-xs text-green-600">
                  <ShieldCheck class="mr-1 inline h-3 w-3" />
                  {{ t('verified') }} {{ formatDate(archive.verified_at) }}
                </p>
                <p
                  v-if="archive.is_expiring_soon"
                  class="mt-0.5 text-xs text-amber-600"
                >
                  {{ t('archive_expiring_soon') }}
                </p>
              </div>
              <div class="flex items-center gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  :disabled="!!verifying[archive.id]"
                  @click.stop="verifyArchive(archive)"
                >
                  <ShieldCheck class="mr-1 h-4 w-4" />
                  {{ t('archive_verify') }}
                </Button>
                <a :href="`/accounting/archives/${archive.id}/download`">
                  <Button variant="outline" size="sm" type="button">
                    <Download class="mr-1 h-4 w-4" />
                    {{ t('archive_download') }}
                  </Button>
                </a>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>

    <Card v-else>
      <CardContent class="py-12 text-center text-sm text-[hsl(var(--muted-foreground))]">
        {{ t('no_archives', 'No archived documents yet.') }}
      </CardContent>
    </Card>
  </AppLayout>
</template>
