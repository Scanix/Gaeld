import { ref, reactive } from 'vue'
import axios from 'axios'
import { useTranslations } from '@/lib/useTranslations'
import { useToast } from '@/lib/useToast'

/**
 * Composable for contact person CRUD operations.
 *
 * @param {string} entityType - 'customers' or 'suppliers'
 * @param {number|string} entityId - The parent entity ID
 * @param {Array} initialContacts - Initial contact persons array
 */
export function useContactPersons(entityType, entityId, initialContacts = []) {
  const { t } = useTranslations()
  const { toast } = useToast()

  const contactPersons = ref([...initialContacts])
  const showContactModal = ref(false)
  const showDeleteContactDialog = ref(false)
  const contactToDelete = ref(null)
  const editingContact = ref(null)
  const contactErrors = ref({})
  const contactProcessing = ref(false)

  const contactForm = reactive({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    position: '',
    is_primary: false,
    notes: '',
  })

  function resetContactForm() {
    Object.assign(contactForm, {
      first_name: '',
      last_name: '',
      email: '',
      phone: '',
      position: '',
      is_primary: false,
      notes: '',
    })
    contactErrors.value = {}
    editingContact.value = null
  }

  function openAddContact() {
    resetContactForm()
    showContactModal.value = true
  }

  function openEditContact(contact) {
    editingContact.value = contact
    Object.assign(contactForm, {
      first_name: contact.first_name,
      last_name: contact.last_name,
      email: contact.email ?? '',
      phone: contact.phone ?? '',
      position: contact.position ?? '',
      is_primary: contact.is_primary ?? false,
      notes: contact.notes ?? '',
    })
    contactErrors.value = {}
    showContactModal.value = true
  }

  async function submitContact() {
    contactProcessing.value = true
    contactErrors.value = {}
    try {
      if (editingContact.value) {
        const { data } = await axios.put(
          `/${entityType}/${entityId}/contact-persons/${editingContact.value.id}`,
          contactForm,
        )
        const idx = contactPersons.value.findIndex(c => c.id === editingContact.value.id)
        if (idx !== -1) contactPersons.value[idx] = data.contact_person
        if (data.contact_person.is_primary) {
          contactPersons.value.forEach((c, i) => {
            if (i !== idx) c.is_primary = false
          })
        }
      } else {
        const { data } = await axios.post(
          `/${entityType}/${entityId}/contact-persons`,
          contactForm,
        )
        if (data.contact_person.is_primary) {
          contactPersons.value.forEach(c => { c.is_primary = false })
        }
        contactPersons.value.push(data.contact_person)
      }
      showContactModal.value = false
      resetContactForm()
      toast(editingContact.value ? t('contact_person_updated') : t('contact_person_created'), 'success')
    } catch (err) {
      if (err.response?.status === 422) {
        contactErrors.value = err.response.data.errors ?? {}
      }
    } finally {
      contactProcessing.value = false
    }
  }

  function confirmDeleteContact(contact) {
    contactToDelete.value = contact
    showDeleteContactDialog.value = true
  }

  async function executeDeleteContact() {
    contactProcessing.value = true
    try {
      await axios.delete(
        `/${entityType}/${entityId}/contact-persons/${contactToDelete.value.id}`,
      )
      contactPersons.value = contactPersons.value.filter(c => c.id !== contactToDelete.value.id)
      showDeleteContactDialog.value = false
      contactToDelete.value = null
      toast(t('contact_person_deleted'), 'success')
    } finally {
      contactProcessing.value = false
    }
  }

  return {
    contactPersons,
    showContactModal,
    showDeleteContactDialog,
    contactToDelete,
    editingContact,
    contactErrors,
    contactProcessing,
    contactForm,
    openAddContact,
    openEditContact,
    submitContact,
    confirmDeleteContact,
    executeDeleteContact,
  }
}
