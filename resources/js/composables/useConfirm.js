export function useConfirm() {
  function confirm(message = 'Are you sure?') {
    return new Promise((resolve) => {
      resolve(window.confirm(message))
    })
  }

  return { confirm }
}
