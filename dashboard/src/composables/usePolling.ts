import { onMounted, onUnmounted } from 'vue'

export function usePolling(callback: () => void, intervalMs = 5000) {
  let timer: ReturnType<typeof setInterval>

  onMounted(() => {
    callback()
    timer = setInterval(callback, intervalMs)
  })

  onUnmounted(() => clearInterval(timer))
}
