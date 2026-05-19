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
import { useFormatters } from '@/lib/useFormatters'
import {
  Archive,
  ShieldCheck,
  Download,
  ChevronDown,
  ChevronRight,
  Loader2,
  AlertTriangle,
} from 'lucide-vue-next'

const { t } = useTranslations()
const { formatDate } = useFormatters()

defineProps({
  years: {
    type: Array,
    default: () => [],
  },
})

const expandedYears = ref({})
const loadedItems = ref({})
const loadingYears = ref({})
const verifying = ref({})

async function toggleYear(year) {
  expandedYears.value[year] = !expandedYears.value[year]
  if (expandedYears.value[year] && !loadedItems.value[year]) {
    await loadYear(year)
  }
}

async function loadYear(year) {
  loadingYears.value[year] = true
  try {
    const res = await fetch(`/accounting/archives/year/${year}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data = await res.json()
    loadedItems.value[year] = data.items ?? []
  } finally {
    loadingYears.value[year] = false
  }
}

function verifyArchive(year, archive) {
  verifying.value[archive.id] = true
  router.post(`/accounting/archives/${archive.id}/verify`, {}, {
    preserveScroll: true,
    onFinish: async () => {
      verifying.value[archive.id] = false
      // Refresh the year so verified_at appears immediately.
      loadedItems.value[year] = null
      await loadYear(year)
    },
  })
}
</script>

<template>
  <AppLayout :title="t('legal_archives')">
    <HelpText :title="t('legal_archives')" class="mb-6">
      <p>{{ t('legal_archives_desc') }}</p>
    </HelpText>

    <div v-if="years.length > 0" class="space-y-4">
      <Card v-for="year in years" :key="year.fiscal_year">
        <CardHeader class="cursor-pointer" @click="toggleYear(year.fiscal_year)">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <CardTitle class="flex items-center gap-2">
              <Archive class="h-5 w-5" />
              {{ t('archive_fiscal_year') }} {{ year.fiscal_year }}
              <Badge variant="secondary">{{ year.total_count }}</Badge>
            </CardTitle>
            <div class="flex flex-wrap items-center gap-3 text-xs text-[hsl(var(--muted-foreground))]">
              <span class="inline-flex items-center gap-1">
                <ShieldCheck class="h-3 w-3 text-green-600" />
                {{ t('archive_year_verified', { count: year.verified_count, total: year.total_count }) }}
              </span>
              <span v-if="year.earliest_expiry" class="inline-flex items-center gap-1">
                <AlertTriangle class="h-3 w-3" />
                {{ t('archive_year_earliest_expiry', { date: formatDate(year.earliest_expiry) }) }}
              </span>
              <component
                :is="expandedYears[year.fiscal_year] ? ChevronDown : ChevronRight"
                class="h-5 w-5"
              />
            </div>
          </div>
        </CardHeader>
        <CardContent v-if="expandedYears[year.fiscal_year]">
          <div
            v-if="loadingYears[year.fiscal_year]"
            class="flex items-center gap-2 py-6 text-sm text-[hsl(var(--muted-foreground))]"
          >
            <Loader2 class="h-4 w-4 animate-spin" />
            {{ t('loading') }}
          </div>
          <div
            v-else-if="loadedItems[year.fiscal_year]?.length"
            class="divide-y divide-[hsl(var(--border))]"
          >
            <div
              v-for="archive in loadedItems[year.fiscal_year]"
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
                <p v-if="archive.is_expiring_soon" class="mt-0.5 text-xs text-amber-600">
                  {{ t('archive_expiring_soon') }}
                </p>
              </div>
              <div class="flex items-center gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  :disabled="!!verifying[archive.id]"
                  @click.stop="verifyArchive(year.fiscal_year, archive)"
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
          <div v-else class="py-6 text-center text-sm text-[hsl(var(--muted-foreground))]">
            {{ t('no_archives') }}
          </div>
        </CardContent>
      </Card>
    </div>

    <Card v-else>
      <CardContent class="py-12 text-center text-sm text-[hsl(var(--muted-foreground))]">
        {{ t('no_archives') }}
      </CardContent>
    </Card>
  </AppLayout>
</template>
