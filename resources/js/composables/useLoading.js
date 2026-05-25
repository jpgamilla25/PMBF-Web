import { ref } from 'vue'
import { useNotificationStore } from '../stores/notification'

export function useLoading() {
  const loading = ref(false)

  async function withLoading(asyncFn) {
    loading.value = true
    try {
      return await asyncFn()
    } catch (error) {
      const notification = useNotificationStore()
      const message =
        error.response?.data?.message ||
        error.message ||
        'An unexpected error occurred'
      notification.error(message)
      throw error
    } finally {
      loading.value = false
    }
  }

  return { loading, withLoading }
}
