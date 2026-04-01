<script setup>
import { useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import Modal from '@/Components/UI/Modal.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'
import { ref, computed } from 'vue'

const props = defineProps({
  organization: Object,
  invitations: { type: Array, default: () => [] },
  canManageUsers: { type: Boolean, default: false },
  canAddMember: { type: Boolean, default: true },
})

const page = usePage()

import { useTranslations } from '@/lib/useTranslations'
const { t } = useTranslations()

const currentUser = computed(() => page.props.auth?.user)
const currentUserOrgRole = computed(() =>
  page.props.auth?.organizations?.find(o => o.id === props.organization.id)?.role
)
const isOwner = computed(() => currentUserOrgRole.value === 'owner')
const isOwnerOrAdmin = computed(() => ['owner', 'admin'].includes(currentUserOrgRole.value))

// --- Edit Organization Modal ---
const showEditModal = ref(false)
const form = useForm({
  name: props.organization.name,
  legal_name: props.organization.legal_name || '',
  address: props.organization.address || '',
  city: props.organization.city || '',
  postal_code: props.organization.postal_code || '',
  canton: props.organization.canton || '',
  vat_number: props.organization.vat_number || '',
  currency: props.organization.currency || 'CHF',
  locale: props.organization.locale || 'en',
  require_two_factor: props.organization.require_two_factor || false,
  default_payment_terms_days: props.organization.default_payment_terms_days ?? 30,
})

function submitUpdate() {
  form.put(`/organizations/${props.organization.id}`, {
    onSuccess: () => { showEditModal.value = false },
  })
}

const cantonOptions = [
  'AG', 'AI', 'AR', 'BE', 'BL', 'BS', 'FR', 'GE', 'GL', 'GR',
  'JU', 'LU', 'NE', 'NW', 'OW', 'SG', 'SH', 'SO', 'SZ', 'TG',
  'TI', 'UR', 'VD', 'VS', 'ZG', 'ZH',
].map(c => ({ value: c, label: c }))

const localeOptions = [
  { value: 'en', label: t('locale_en') },
  { value: 'fr', label: t('locale_fr') },
  { value: 'de', label: t('locale_de') },
  { value: 'it', label: t('locale_it') },
]

// --- Members Table ---
const roleVariants = {
  owner: 'default',
  admin: 'info',
  member: 'secondary',
  viewer: 'outline',
}

const roleLabels = {
  owner: t('role_owner'),
  admin: t('role_admin'),
  member: t('role_member'),
  viewer: t('role_viewer'),
}

const roleOptions = [
  { value: 'owner', label: t('role_owner') },
  { value: 'admin', label: t('role_admin') },
  { value: 'member', label: t('role_member') },
  { value: 'viewer', label: t('role_viewer') },
]

const roleOptionsForInvite = computed(() => {
  if (isOwner.value) return roleOptions
  return roleOptions.filter(r => r.value !== 'owner')
})

const userColumns = computed(() => {
  const cols = [
    { key: 'name', label: t('name') },
    { key: 'email', label: t('email') },
    { key: 'pivot', label: t('role') },
  ]
  if (props.canManageUsers) {
    cols.push({ key: 'actions', label: '' })
  }
  return cols
})

// --- Role Change ---
function changeRole(user, newRole) {
  router.post(`/organizations/${props.organization.id}/members/${user.id}/role`, {
    role: newRole,
  }, { preserveScroll: true })
}

// --- Remove Member ---
const removeTarget = ref(null)
function confirmRemove(user) { removeTarget.value = user }
function executeRemove() {
  router.delete(`/organizations/${props.organization.id}/members/${removeTarget.value.id}`, {
    preserveScroll: true,
    onFinish: () => { removeTarget.value = null },
  })
}

// --- Leave Organization ---
const showLeaveConfirm = ref(false)
function executeLeave() {
  router.post(`/organizations/${props.organization.id}/leave`, {}, {
    onFinish: () => { showLeaveConfirm.value = false },
  })
}

// --- Invite Member Modal ---
const showInviteModal = ref(false)
const inviteForm = useForm({
  email: '',
  role: 'member',
})

function submitInvite() {
  inviteForm.post(`/organizations/${props.organization.id}/invitations`, {
    onSuccess: () => {
      showInviteModal.value = false
      inviteForm.reset()
    },
    preserveScroll: true,
  })
}

// --- Invitation Actions ---
function cancelInvitation(invitation) {
  router.delete(`/organizations/${props.organization.id}/invitations/${invitation.id}`, {
    preserveScroll: true,
  })
}

function resendInvitation(invitation) {
  router.post(`/organizations/${props.organization.id}/invitations/${invitation.id}/resend`, {}, {
    preserveScroll: true,
  })
}

// --- Pending Invitations Table ---
const invitationColumns = computed(() => [
  { key: 'email', label: t('email') },
  { key: 'role', label: t('role') },
  { key: 'inviter', label: t('invited_by'), format: (v) => v?.name ?? '—' },
  { key: 'expires_at', label: t('expires'), format: (v) => v ? new Date(v).toLocaleDateString() : '—' },
  { key: 'actions', label: '' },
])

function canChangeUserRole(user) {
  // Can't change own role if sole owner
  if (user.id === currentUser.value?.id) return false
  return true
}
</script>

<template>
  <AppLayout :title="organization.name">
    <Breadcrumb :items="[{ label: t('organizations'), href: '/organizations' }, { label: organization.name }]" class="mb-4" />

    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <h2 class="text-xl font-semibold">{{ organization.name }}</h2>
      </div>
      <div class="flex items-center gap-2">
        <Button
          v-if="!isOwner && organization.users?.length > 1"
          variant="outline"
          @click="showLeaveConfirm = true"
        >{{ t('leave_organization') }}</Button>
        <Button v-if="isOwnerOrAdmin" @click="showEditModal = true">{{ t('edit') }}</Button>
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Organization Details -->
      <Card>
        <CardHeader><CardTitle>{{ t('organization_details') }}</CardTitle></CardHeader>
        <CardContent>
          <dl class="grid grid-cols-2 gap-y-3 text-sm">
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('legal_name') }}</dt>
            <dd>{{ organization.legal_name || '—' }}</dd>
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('address') }}</dt>
            <dd>{{ organization.address || '—' }}</dd>
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('city') }}</dt>
            <dd>{{ [organization.postal_code, organization.city].filter(Boolean).join(' ') || '—' }}</dd>
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('canton') }}</dt>
            <dd>{{ organization.canton || '—' }}</dd>
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('country') }}</dt>
            <dd>{{ organization.country || '—' }}</dd>
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('vat_number') }}</dt>
            <dd>{{ organization.vat_number || '—' }}</dd>
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('currency') }}</dt>
            <dd>{{ organization.currency || 'CHF' }}</dd>
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('locale') }}</dt>
            <dd class="uppercase">{{ organization.locale || '—' }}</dd>
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('require_two_factor') }}</dt>
            <dd>
              <Badge :variant="organization.require_two_factor ? 'default' : 'secondary'">
                {{ organization.require_two_factor ? t('enabled') : t('disabled') }}
              </Badge>
            </dd>
          </dl>
        </CardContent>
      </Card>

      <!-- Settings -->
      <Card>
        <CardHeader><CardTitle>{{ t('organization_settings') }}</CardTitle></CardHeader>
        <CardContent>
          <dl class="grid grid-cols-2 gap-y-3 text-sm">
            <dt class="text-[hsl(var(--muted-foreground))]">{{ t('default_payment_terms') }}</dt>
            <dd>{{ t('payment_terms_days', { days: organization.default_payment_terms_days ?? 30 }) }}</dd>
          </dl>
        </CardContent>
      </Card>
    </div>

    <!-- Members -->
    <Card class="mt-6">
      <CardHeader>
        <div class="flex items-center justify-between">
          <CardTitle>{{ t('members') }}</CardTitle>
          <Button
            v-if="canManageUsers && canAddMember"
            size="sm"
            @click="showInviteModal = true"
          >{{ t('invite_member') }}</Button>
        </div>
      </CardHeader>
      <CardContent>
        <DataTable
          :columns="userColumns"
          :rows="organization.users ?? []"
          :empty-message="t('no_members')"
        >
          <template #cell-pivot="{ value, row }">
            <div v-if="canManageUsers && canChangeUserRole(row)" class="w-28">
              <select
                :value="value?.role"
                class="w-full rounded-md border border-[hsl(var(--input))] bg-transparent px-2 py-1 text-sm"
                @change="changeRole(row, $event.target.value)"
              >
                <option v-for="opt in roleOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>
            <Badge v-else :variant="roleVariants[value?.role] ?? 'secondary'">
              {{ roleLabels[value?.role] ?? value?.role ?? 'member' }}
            </Badge>
          </template>
          <template #cell-actions="{ row }">
            <Button
              v-if="canManageUsers && row.id !== currentUser?.id"
              variant="ghost"
              size="sm"
              class="text-[hsl(var(--destructive))]"
              @click="confirmRemove(row)"
            >{{ t('remove_member') }}</Button>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <!-- Pending Invitations -->
    <Card v-if="canManageUsers && invitations.length > 0" class="mt-6">
      <CardHeader><CardTitle>{{ t('pending_invitations') }}</CardTitle></CardHeader>
      <CardContent>
        <DataTable
          :columns="invitationColumns"
          :rows="invitations"
          :empty-message="'—'"
        >
          <template #cell-role="{ value }">
            <Badge :variant="roleVariants[value] ?? 'secondary'">
              {{ roleLabels[value] ?? value }}
            </Badge>
          </template>
          <template #cell-actions="{ row }">
            <div class="flex gap-2">
              <Button variant="ghost" size="sm" @click="resendInvitation(row)">{{ t('resend') }}</Button>
              <Button variant="ghost" size="sm" class="text-[hsl(var(--destructive))]" @click="cancelInvitation(row)">{{ t('cancel_invitation') }}</Button>
            </div>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <!-- Edit Organization Modal -->
    <Modal :show="showEditModal" @close="showEditModal = false" :title="t('edit_organization')">
      <form class="space-y-6" @submit.prevent="submitUpdate">
        <FormInput id="name" v-model="form.name" :label="t('name')" :error="form.errors.name" required />
        <FormInput id="legal_name" v-model="form.legal_name" :label="t('legal_name')" :error="form.errors.legal_name" />
        <FormInput id="address" v-model="form.address" :label="t('address')" :error="form.errors.address" />
        <div class="grid grid-cols-2 gap-4">
          <FormInput id="city" v-model="form.city" :label="t('city')" :error="form.errors.city" />
          <FormInput id="postal_code" v-model="form.postal_code" :label="t('postal_code')" :error="form.errors.postal_code" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <FormSelect id="canton" v-model="form.canton" :label="t('canton')" :options="cantonOptions" :placeholder="t('select')" :error="form.errors.canton" />
          <FormSelect id="locale" v-model="form.locale" :label="t('locale')" :options="localeOptions" :error="form.errors.locale" />
        </div>
        <FormInput id="vat_number" v-model="form.vat_number" :label="t('vat_number')" :error="form.errors.vat_number" />
        <FormInput
          id="default_payment_terms_days"
          v-model.number="form.default_payment_terms_days"
          type="number"
          min="0"
          max="365"
          :label="t('default_payment_terms')"
          :error="form.errors.default_payment_terms_days"
        />
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('require_two_factor') }}</p>
            <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('require_two_factor_desc') }}</p>
          </div>
          <button
            type="button"
            role="switch"
            :aria-checked="form.require_two_factor"
            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[hsl(var(--ring))] focus-visible:ring-offset-2"
            :class="form.require_two_factor ? 'bg-[hsl(var(--primary))]' : 'bg-[hsl(var(--input))]'"
            @click="form.require_two_factor = !form.require_two_factor"
          >
            <span
              class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
              :class="form.require_two_factor ? 'translate-x-5' : 'translate-x-0'"
            />
          </button>
        </div>
        <div class="flex justify-end gap-3">
          <Button variant="outline" @click="showEditModal = false">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="form.processing">{{ t('save') }}</Button>
        </div>
      </form>
    </Modal>

    <!-- Invite Member Modal -->
    <Modal :show="showInviteModal" @close="showInviteModal = false" :title="t('invite_member')">
      <form class="space-y-6" @submit.prevent="submitInvite">
        <FormInput
          id="invite_email"
          v-model="inviteForm.email"
          type="email"
          :label="t('email')"
          :error="inviteForm.errors.email"
          required
        />
        <FormSelect
          id="invite_role"
          v-model="inviteForm.role"
          :label="t('role')"
          :options="roleOptionsForInvite"
          :error="inviteForm.errors.role"
        />
        <div class="flex justify-end gap-3">
          <Button variant="outline" @click="showInviteModal = false">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="inviteForm.processing">{{ t('invite_member') }}</Button>
        </div>
      </form>
    </Modal>

    <!-- Remove Member Confirmation -->
    <ConfirmDialog
      :open="!!removeTarget"
      :title="t('remove_member')"
      :message="t('confirm_remove_member', { name: removeTarget?.name })"
      @confirm="executeRemove"
      @cancel="removeTarget = null"
    />

    <!-- Leave Organization Confirmation -->
    <ConfirmDialog
      :open="showLeaveConfirm"
      :title="t('leave_organization')"
      :message="t('confirm_leave_organization', { name: organization.name })"
      @confirm="executeLeave"
      @cancel="showLeaveConfirm = false"
    />
  </AppLayout>
</template>
