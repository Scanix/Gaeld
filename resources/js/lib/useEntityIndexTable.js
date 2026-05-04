import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'

export function useEntityIndexQuery({ basePath, query }) {
  function applyQuery(params) {
    router.get(basePath, {
      ...query.value,
      ...params,
      page: 1,
    }, { preserveState: true, replace: true })
  }

  function handleSort({ sort, direction }) {
    applyQuery({ sort, direction })
  }

  function handleSearch(search) {
    applyQuery({ search })
  }

  function handleFilter({ key, value }) {
    applyQuery({ filter: { ...query.value.filter, [key]: value } })
  }

  return {
    applyQuery,
    handleSort,
    handleSearch,
    handleFilter,
  }
}

export function useCountryFilters({ t, query }) {
  return computed(() => [
    {
      key: 'country',
      label: t('all_countries'),
      value: query.value.filter?.country ?? '',
      options: [
        { value: 'CH', label: t('country_switzerland') },
        { value: 'DE', label: t('country_germany') },
        { value: 'AT', label: t('country_austria') },
        { value: 'FR', label: t('country_france') },
        { value: 'IT', label: t('country_italy') },
      ],
    },
  ])
}

export function useEntityDelete({ basePath }) {
  const deleteTarget = ref(null)
  const deleting = ref(false)

  function confirmDelete(entity) {
    deleteTarget.value = entity
  }

  function executeDelete() {
    if (!deleteTarget.value) return

    deleting.value = true
    router.delete(`${basePath}/${deleteTarget.value.uuid}`, {
      onFinish: () => {
        deleting.value = false
        deleteTarget.value = null
      },
    })
  }

  return {
    deleteTarget,
    deleting,
    confirmDelete,
    executeDelete,
  }
}
