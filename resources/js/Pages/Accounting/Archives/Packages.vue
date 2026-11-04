<script setup>
import { Link, router, useForm, usePoll } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'

defineProps({ packages: Object, fiscalYears: Array, canManage: Boolean, summary: Object })
const { t } = useTranslations()
const { formatDate } = useFormatters()
const form = useForm({ fiscal_year_id: '' })
usePoll(15000, { only: ['packages', 'summary'] })

function prepare() {
  form.post('/accounting/conservation', { preserveScroll: true })
}

function action(id, operation) {
  router.post(`/accounting/conservation/${id}/${operation}`, {}, { preserveScroll: true })
}
</script>

<template>
  <AppLayout :title="t('conservation_title')">
    <div class="space-y-6">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <p class="max-w-3xl text-sm text-[hsl(var(--muted-foreground))]">{{ t('conservation_description') }}</p>
        <Link href="/accounting/archives" class="text-sm underline">{{ t('conservation_historical') }}</Link>
      </div>

      <div class="grid gap-4 sm:grid-cols-3" aria-live="polite">
        <Card v-for="metric in ['pending', 'incidents', 'overdue']" :key="metric">
          <CardContent class="p-4">
            <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ t(`conservation_${metric}`) }}</p>
            <p class="mt-1 text-2xl font-semibold">{{ summary[metric] }}</p>
          </CardContent>
        </Card>
      </div>

      <Card v-if="canManage">
        <CardContent class="p-5">
          <form class="flex flex-wrap items-end gap-4" @submit.prevent="prepare">
            <div class="min-w-64 flex-1">
              <label for="conservation-year" class="mb-2 block text-sm font-medium">{{ t('archive_fiscal_year') }}</label>
              <select id="conservation-year" v-model="form.fiscal_year_id" required class="w-full rounded-md border border-[hsl(var(--border))] bg-[hsl(var(--background))] px-3 py-2">
                <option disabled value="">{{ t('conservation_choose_year') }}</option>
                <option v-for="year in fiscalYears" :key="year.id" :value="year.id">
                  {{ year.name }} · {{ formatDate(year.start_date) }} – {{ formatDate(year.end_date) }}
                </option>
              </select>
              <p v-if="form.errors.fiscal_year_id" role="alert" class="mt-1 text-sm text-red-600">{{ form.errors.fiscal_year_id }}</p>
            </div>
            <Button type="submit" :disabled="form.processing || !form.fiscal_year_id">{{ t('conservation_prepare') }}</Button>
            <p class="w-full text-xs text-[hsl(var(--muted-foreground))]">{{ t('conservation_content_note') }}</p>
          </form>
        </CardContent>
      </Card>

      <div v-if="!packages.data.length" class="rounded-lg border border-dashed border-[hsl(var(--border))] p-8 text-center text-sm">
        {{ t('conservation_empty') }}
      </div>
      <Card v-for="item in packages.data" :key="item.id">
        <CardContent class="space-y-4 p-5">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 class="font-medium">{{ item.fiscal_year }} · v{{ item.version }}</h2>
              <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ item.from }} – {{ item.to }}</p>
              <p class="mt-1 break-all text-xs text-[hsl(var(--muted-foreground))]">{{ item.id }}</p>
            </div>
            <Badge :variant="item.has_incident ? 'destructive' : 'secondary'">{{ t(`conservation_state_${item.state}`) }}</Badge>
          </div>
          <dl class="grid gap-3 text-sm sm:grid-cols-3">
            <div><dt class="text-[hsl(var(--muted-foreground))]">{{ t('conservation_captured') }}</dt><dd>{{ formatDate(item.captured_at) }}</dd></div>
            <div><dt class="text-[hsl(var(--muted-foreground))]">{{ t('conservation_checked') }}</dt><dd>{{ item.verified_at ? formatDate(item.verified_at) : t('conservation_not_checked') }}</dd></div>
            <div><dt class="text-[hsl(var(--muted-foreground))]">{{ t('conservation_keep_until') }}</dt><dd>{{ formatDate(item.retention_until) }}<span v-if="item.legal_hold"> · {{ t('conservation_hold') }}</span></dd></div>
          </dl>
          <p v-if="item.provenance !== 'native'" class="text-sm text-amber-700 dark:text-amber-400">{{ t('conservation_historical_note') }}</p>
          <p v-if="item.has_incident" role="status" class="text-sm text-red-600 dark:text-red-400">{{ t('conservation_incident_note') }}</p>
          <div class="flex flex-wrap gap-2">
            <a v-if="item.available" :href="`/accounting/conservation/${item.id}/export`" class="inline-flex items-center rounded-md border border-[hsl(var(--border))] px-3 py-2 text-sm">{{ t('conservation_export') }}</a>
            <Button v-if="item.available" variant="secondary" type="button" @click="action(item.id, 'verify')">{{ t('conservation_verify') }}</Button>
            <Button v-if="canManage && ['failed', 'incident', 'pending'].includes(item.state)" variant="outline" type="button" @click="action(item.id, 'resume')">{{ t('conservation_resume') }}</Button>
          </div>
        </CardContent>
      </Card>
      <nav v-if="packages.prev_page_url || packages.next_page_url" class="flex justify-between text-sm" :aria-label="t('pagination')">
        <Link v-if="packages.prev_page_url" :href="packages.prev_page_url" preserve-scroll>{{ t('previous') }}</Link>
        <Link v-if="packages.next_page_url" :href="packages.next_page_url" preserve-scroll>{{ t('next') }}</Link>
      </nav>
    </div>
  </AppLayout>
</template>
