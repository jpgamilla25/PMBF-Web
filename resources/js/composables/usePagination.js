import { ref, reactive } from 'vue'
import { useNotificationStore } from '../stores/notification'

export function usePagination(fetchFn) {
  const items = ref([])
  const loading = ref(false)
  const filters = reactive({})
  const meta = reactive({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: 15,
  })

  async function fetch(page = 1) {
    loading.value = true
    try {
      const params = { page, ...filters }
      const { data } = await fetchFn(params)

      items.value = data.data ?? data
      if (data.meta) {
        meta.currentPage = data.meta.current_page
        meta.lastPage = data.meta.last_page
        meta.total = data.meta.total
        meta.perPage = data.meta.per_page
      } else if (data.current_page !== undefined) {
        meta.currentPage = data.current_page
        meta.lastPage = data.last_page
        meta.total = data.total
        meta.perPage = data.per_page
      }
    } catch (error) {
      const notification = useNotificationStore()
      const message =
        error.response?.data?.message || error.message || 'Failed to load data'
      notification.error(message)
    } finally {
      loading.value = false
    }
  }

  function nextPage() {
    if (meta.currentPage < meta.lastPage) {
      fetch(meta.currentPage + 1)
    }
  }

  function prevPage() {
    if (meta.currentPage > 1) {
      fetch(meta.currentPage - 1)
    }
  }

  function setFilter(key, val) {
    filters[key] = val
    fetch(1)
  }

  return { items, meta, loading, filters, fetch, nextPage, prevPage, setFilter }
}
