import { reactive } from 'vue'
import { z } from 'zod'

/**
 * Client-side validation composable powered by Zod, for use alongside Inertia useForm.
 *
 * Usage:
 *   const schema = z.object({
 *     name: z.string().min(1, 'This field is required.').max(255),
 *     email: z.string().min(1, 'This field is required.').email('Please enter a valid email address.'),
 *     amount: z.coerce.number().min(0),
 *   })
 *   const { errors, validate, validateField, isValid } = useFormValidation(schema)
 *
 *   // On blur:  validateField('name', form.name)
 *   // On submit: if (validate(form.data())) form.post(...)
 */
export function useFormValidation(schema) {
  const errors = reactive({})

  function validateField(field, value) {
    delete errors[field]

    const fieldSchema = schema.shape[field]
    if (!fieldSchema) return true

    const result = fieldSchema.safeParse(value)
    if (!result.success) {
      errors[field] = result.error.issues[0]?.message || 'Invalid value.'
      return false
    }

    return true
  }

  function validate(data) {
    clearErrors()
    const result = schema.safeParse(data)
    if (result.success) return true

    for (const issue of result.error.issues) {
      const field = issue.path[0]
      if (field && !errors[field]) {
        errors[field] = issue.message
      }
    }
    return false
  }

  function isValid() {
    return Object.keys(errors).length === 0
  }

  function clearErrors() {
    for (const key of Object.keys(errors)) {
      delete errors[key]
    }
  }

  return { errors, validate, validateField, isValid, clearErrors }
}

export { z }
