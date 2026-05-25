import { reactive, ref } from 'vue'

export function useForm(initialData = {}) {
  const form = reactive({ ...initialData })
  const errors = reactive({})
  const processing = ref(false)

  function reset() {
    Object.keys(form).forEach((key) => {
      form[key] = initialData[key] ?? null
    })
    clearErrors()
  }

  function setErrors(errObj) {
    clearErrors()
    if (errObj && typeof errObj === 'object') {
      Object.keys(errObj).forEach((key) => {
        errors[key] = Array.isArray(errObj[key]) ? errObj[key][0] : errObj[key]
      })
    }
  }

  function clearErrors() {
    Object.keys(errors).forEach((key) => {
      delete errors[key]
    })
  }

  async function submit(asyncFn) {
    processing.value = true
    clearErrors()
    try {
      return await asyncFn(form)
    } catch (error) {
      if (error.response?.status === 422) {
        const validationErrors = error.response.data.errors || {}
        setErrors(validationErrors)
      }
      throw error
    } finally {
      processing.value = false
    }
  }

  return { form, errors, reset, setErrors, clearErrors, processing, submit }
}
