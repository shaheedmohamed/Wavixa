import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { t, setLocale, locale } from './i18n'
import './style.css'

const app = createApp(App)

app.use(router)
app.config.globalProperties.$t = t
app.config.globalProperties.$setLocale = setLocale
app.config.globalProperties.$locale = locale

app.mount('#app')
