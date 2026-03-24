import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

export function usePermissions() {
  const page = usePage()
  const permissions = computed(() => page.props.auth?.permissions || [])
  const role = computed(() => page.props.auth?.role || null)

  function can(permission) {
    return permissions.value.includes(permission)
  }

  function hasRole(roleName) {
    return role.value === roleName
  }

  function hasAnyRole(...roleNames) {
    return roleNames.includes(role.value)
  }

  return { can, hasRole, hasAnyRole, permissions, role }
}
