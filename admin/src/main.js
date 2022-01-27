import { createApp } from 'vue'
import App from './App.vue'
import ElementPlus from 'element-plus'
import router from './router/index'
import 'element-plus/theme-chalk/index.css'
import './styles/common.scss'
import * as ElIcons from '@element-plus/icons-vue'

const app = createApp(App)
for (const name in ElIcons) {
    app.component(name, ElIcons[name])
}
app.use(ElementPlus).use(router).mount('#app')
