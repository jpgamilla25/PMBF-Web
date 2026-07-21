import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import { initTheme } from './composables/useTheme'
import App from './App.vue'

initTheme()

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.mount('#app')
