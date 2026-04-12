<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import { Building2, ArrowRightLeft } from 'lucide-vue-next'
import EmptyState from '@/Components/UI/EmptyState.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import { useTranslations } from '@/lib/useTranslations'
import { usePage } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
  organizations: { type: Array, default: () => [] },
})

const page = usePage()
const currentOrgId = page.props.auth?.currentOrganization?.id
const switchError = ref('')

function switchForm(orgId) {
  switchError.value = ''
  const form = useForm({})
  form.post(`/organizations/${orgId}/switch`, {
    onError: (errors) => {
      switchError.value = Object.values(errors).flat().join(' ')
    },
  })
}

const { t } = useTranslations()
</script>

<template>
  <AppLayout :title="t('organizations')">
    <PageHeader :description="t('your_organizations')">
      <Button as="a" href="/organizations/create">
        {{ t('new_organization') }}
      </Button>
    </PageHeader>

    <p v-if="switchError" class="mb-4 text-sm text-[hsl(var(--destructive))]">{{ switchError }}</p>

    <div v-if="organizations.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <Card
        v-for="org in organizations"
        :key="org.id"
        :class="[
          'transition-shadow hover:shadow-md',
          org.id === currentOrgId && 'ring-2 ring-[hsl(var(--primary))]',
        ]"
      >
        <CardHeader>
          <div class="flex items-center justify-between">
            <CardTitle class="text-base">{{ org.name }}</CardTitle>
            <Badge v-if="org.id === currentOrgId" variant="default">{{ t('active') }}</Badge>
          </div>
        </CardHeader>
        <CardContent>
          <dl class="grid grid-cols-2 gap-y-2 text-sm mb-4">
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('city') }}</dt>
            <dd>{{ org.city || '—' }}</dd>
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('currency') }}</dt>
            <dd>{{ org.currency || 'CHF' }}</dd>
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('role') }}</dt>
            <dd class="capitalize">{{ org.pivot?.role || '—' }}</dd>
          </dl>
          <div class="flex gap-2">
            <Button
              as="a"
              :href="`/organizations/${org.id}`"
              variant="outline"
              size="sm"
            >
              {{ t('details') }}
            </Button>
            <Button
              v-if="org.id !== currentOrgId"
              size="sm"
              @click="switchForm(org.id)"
            >
              <ArrowRightLeft class="mr-1 h-3 w-3" />
              {{ t('switch') }}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>

    <Card v-else>
      <CardContent>
        <EmptyState :icon="Building2" :title="t('no_organizations')" />
      </CardContent>
    </Card>
  </AppLayout>
</template>
