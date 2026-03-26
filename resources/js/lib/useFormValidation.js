import { reactive } from 'vue'

/**
 * Lightweight client-side validation composable that works alongside Inertia useForm.
 *
 * Usage:
 *   const rules = {
 *     name: { required: true, maxLength: 255 },
 *     email: { required: true, email: true },
 *     amount: { required: true, min: 0 },
 *   }
 *   const { errors, validate, validateField, isValid } = useFormValidation(rules)
 *
 *   // On blur:  validateField('name', form.name)
 *   // On submit: if (validate(form.data())) form.post(...)
 */
export function useFormValidation(rules) {
  const errors = reactive({})

  function validateField(field, value) {
    const rule = rules[field]
    if (!rule) return true

    delete errors[field]

    if (rule.required && (value === null || value === undefined || String(value).trim() === '')) {
      errors[field] = 'This field is required.'
      return false
    }

    if (value !== null && value !== undefined && String(value).trim() !== '') {
      if (rule.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value))) {
        errors[field] = 'Please enter a valid email address.'
        return false
      }
      if (rule.maxLength && String(value).length > rule.maxLength) {
        errors[field] = `Must be at most ${rule.maxLength} characters.`
        return false
      }
      if (rule.minLength && String(value).length < rule.minLength) {
        errors[field] = `Must be at least ${rule.minLength} characters.`
        return false
      }
      if (rule.min !== undefined && Number(value) < rule.min) {
        errors[field] = `Must be at least ${rule.min}.`
        return false
      }
      if (rule.max !== undefined && Number(value) > rule.max) {
        errors[field] = `Must be at most ${rule.max}.`
        return false
      }
      if (rule.pattern && !rule.pattern.test(String(value))) {
        errors[field] = rule.patternMessage || 'Invalid format.'
        return false
      }
    }

    return true
  }

  function validate(data) {
    let valid = true
    for (const field of Object.keys(rules)) {
      if (!validateField(field, data[field])) {
        valid = false
      }
    }
    return valid
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
