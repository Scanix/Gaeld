<script setup>
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Modal from '@/Components/UI/Modal.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import EmptyState from '@/Components/UI/EmptyState.vue'
import { useTranslations } from '@/lib/useTranslations'
import { Pencil, Plus, Trash2, Star } from 'lucide-vue-next'

const { t } = useTranslations()

defineProps({
  contactPersons: { type: Array, required: true },
  showContactModal: { type: Boolean, required: true },
  showDeleteContactDialog: { type: Boolean, required: true },
  editingContact: { type: Object, default: null },
  contactErrors: { type: Object, default: () => ({}) },
  contactProcessing: { type: Boolean, default: false },
  contactForm: { type: Object, required: true },
})

const emit = defineEmits([
  'add',
  'edit',
  'submit',
  'confirm-delete',
  'execute-delete',
  'close-modal',
  'close-dialog',
])
</script>

<template>
  <Card class="mt-6">
    <CardHeader>
      <div class="flex items-center justify-between">
        <CardTitle>{{ t('contact_persons') }}</CardTitle>
        <Button size="sm" @click="emit('add')">
          <Plus class="mr-1 h-4 w-4" />
          {{ t('add_contact_person') }}
        </Button>
      </div>
    </CardHeader>
    <CardContent>
      <EmptyState v-if="!contactPersons.length" :title="t('no_contact_persons')" />
      <div v-else class="space-y-3">
        <div
          v-for="contact in contactPersons"
          :key="contact.id"
          class="flex items-start justify-between rounded-lg border p-3"
        >
          <div>
            <div class="flex items-center gap-2">
              <span class="font-medium">{{ contact.first_name }} {{ contact.last_name }}</span>
              <Star v-if="contact.is_primary" class="h-4 w-4 fill-yellow-400 text-yellow-400" />
              <span v-if="contact.position" class="text-sm text-[hsl(var(--muted-foreground))]">
                — {{ contact.position }}
              </span>
            </div>
            <div class="mt-1 flex gap-4 text-sm text-[hsl(var(--muted-foreground))]">
              <a v-if="contact.email" :href="`mailto:${contact.email}`" class="underline">{{ contact.email }}</a>
              <span v-if="contact.phone">{{ contact.phone }}</span>
            </div>
            <p v-if="contact.notes" class="mt-1 text-sm italic text-[hsl(var(--muted-foreground))]">{{ contact.notes }}</p>
          </div>
          <div class="flex gap-1">
            <Button variant="ghost" size="sm" @click="emit('edit', contact)">
              <Pencil class="h-4 w-4" />
            </Button>
            <Button variant="ghost" size="sm" @click="emit('confirm-delete', contact)">
              <Trash2 class="h-4 w-4 text-[hsl(var(--destructive))]" />
            </Button>
          </div>
        </div>
      </div>
    </CardContent>
  </Card>

  <!-- Contact Person Modal -->
  <Modal :open="showContactModal" :title="editingContact ? t('edit_contact_person') : t('add_contact_person')" @close="emit('close-modal')">
    <form class="space-y-6" @submit.prevent="emit('submit')">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormInput
          id="cp-first-name"
          v-model="contactForm.first_name"
          :label="t('first_name')"
          :error="contactErrors.first_name?.[0]"
          required
        />
        <FormInput
          id="cp-last-name"
          v-model="contactForm.last_name"
          :label="t('last_name')"
          :error="contactErrors.last_name?.[0]"
          required
        />
      </div>
      <FormInput
        id="cp-email"
        v-model="contactForm.email"
        type="email"
        :label="t('email')"
        :error="contactErrors.email?.[0]"
      />
      <FormInput
        id="cp-phone"
        v-model="contactForm.phone"
        :label="t('phone')"
        :error="contactErrors.phone?.[0]"
      />
      <FormInput
        id="cp-position"
        v-model="contactForm.position"
        :label="t('position')"
        :error="contactErrors.position?.[0]"
      />
      <FormInput
        id="cp-notes"
        v-model="contactForm.notes"
        :label="t('notes')"
        :error="contactErrors.notes?.[0]"
      />
      <label class="flex items-center gap-2 text-sm">
        <input v-model="contactForm.is_primary" type="checkbox" class="rounded border-[hsl(var(--border))]" />
        {{ t('primary_contact') }}
      </label>
      <div class="flex justify-end gap-3">
        <Button type="button" variant="outline" @click="emit('close-modal')">{{ t('cancel') }}</Button>
        <Button type="submit" :disabled="contactProcessing">{{ editingContact ? t('save_changes') : t('create') }}</Button>
      </div>
    </form>
  </Modal>

  <!-- Delete Contact Person Confirmation -->
  <ConfirmDialog
    :open="showDeleteContactDialog"
    :title="t('delete_contact_person')"
    :message="t('delete_contact_person_confirm')"
    :confirm-label="t('delete')"
    :processing="contactProcessing"
    @confirm="emit('execute-delete')"
    @cancel="emit('close-dialog')"
  />
</template>
